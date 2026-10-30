<?php
namespace App\Core;

use PDO;

class SketchesAgentModule extends AiAgentModule {

    public function getModulePrefix(): string {
        return 'sketches';
    }

    protected function getDbSchemaInstructions(): string {
        return <<<EOT
1. Add new sketch:
{"action": "add", "name": "unique_slug", "description": "prompt text", "regenerate": 1}

2. Update sketch:
{"action": "update", "id": 123, "name": "new_slug", "description": "new prompt", "regenerate": 1}

3. Delete sketch (globally):
{"action": "delete", "id": 123}

4. Search and Link (Finds existing sketches in the DB and links them to the session):
{"action": "search_and_link", "query": "search term"}

5. Unlink (Removes from session WITHOUT deleting globally):
{"action": "unlink", "id": 123}
EOT;
    }

    public function getCurrentStateContext(string $sessionId, int $userId): string {
        $stmt = $this->pdo->prepare("
            SELECT s.id, s.name, s.description, s.regenerate_images
            FROM agent_session_items i
            JOIN sketches s ON i.item_id = s.id
            WHERE i.session_id = ?
            ORDER BY s.id DESC
        ");
        $stmt->execute([$sessionId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function executeOperations(array $operations, string $sessionId, int $userId): array {
        $log = [];

        foreach ($operations as $op) {
            $action = strtolower(trim($op['action'] ?? ''));
            
            // Forgiving Parser: If the AI forgot the "action" key, e.g. {"search_and_link": "Emberveil"}
            if (!$action) {
                foreach (['add', 'update', 'delete', 'search_and_link', 'search', 'find', 'unlink'] as $possibleAction) {
                    if (isset($op[$possibleAction])) {
                        $action = $possibleAction;
                        if (in_array($action, ['search_and_link', 'search', 'find'])) $op['query'] = $op[$possibleAction];
                        if (in_array($action, ['delete', 'unlink'])) $op['id'] = (int)$op[$possibleAction];
                        break;
                    }
                }
            }

            // Map common AI aliases to the correct functions
            if (str_starts_with($action, 'add')) $action = 'add';
            elseif (str_starts_with($action, 'update')) $action = 'update';
            elseif (str_starts_with($action, 'delete')) $action = 'delete';
            elseif (str_starts_with($action, 'search') || str_starts_with($action, 'find') || str_starts_with($action, 'get')) {
                $action = 'search_and_link';
            }

            try {
                if ($action === 'add') {
                    $baseName = trim($op['name'] ?? 'Sketch_' . time());
                    $name = $baseName;
                    $counter = 1;
                    while (true) {
                        $chk = $this->pdo->prepare("SELECT COUNT(*) FROM sketches WHERE name = ?");
                        $chk->execute([$name]);
                        if ((int)$chk->fetchColumn() === 0) break;
                        $name = $baseName . '_' . $counter++;
                    }

                    $desc = trim($op['description'] ?? '');
                    $regen = !empty($op['regenerate']) ? 1 : 0;

                    $stmt = $this->pdo->prepare("INSERT INTO sketches (name, description, regenerate_images) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $desc, $regen]);
                    $newId = (int)$this->pdo->lastInsertId();

                    $this->pdo->prepare("INSERT IGNORE INTO agent_session_items (session_id, item_id) VALUES (?, ?)")
                        ->execute([$sessionId, $newId]);

                    $log[] = "Added sketch #{$newId} ({$name})";
                }
                elseif ($action === 'update') {
                    $id = (int)($op['id'] ?? 0);
                    if (!$id) continue;

                    $updates = [];
                    $params = [];
                    if (isset($op['name'])) { $updates[] = "name = ?"; $params[] = trim($op['name']); }
                    if (isset($op['description'])) { $updates[] = "description = ?"; $params[] = trim($op['description']); }
                    if (isset($op['regenerate'])) { $updates[] = "regenerate_images = ?"; $params[] = !empty($op['regenerate']) ? 1 : 0; }

                    if (!empty($updates)) {
                        $params[] = $id;
                        $this->pdo->prepare("UPDATE sketches SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
                        $log[] = "Updated sketch #{$id}";
                    }
                }
                elseif ($action === 'delete') {
                    $id = (int)($op['id'] ?? 0);
                    if (!$id) continue;

                    $this->pdo->prepare("DELETE FROM agent_session_items WHERE item_id = ?")->execute([$id]);
                    $this->pdo->prepare("DELETE FROM sketches WHERE id = ?")->execute([$id]);
                    $log[] = "Globally deleted sketch #{$id}";
                }
                elseif ($action === 'search_and_link') {
                    $queryStr = trim($op['query'] ?? '');
                    if (!$queryStr && isset($op['name'])) $queryStr = trim($op['name']); // Fallback if AI used wrong key
                    
                    $like = '%' . $queryStr . '%';
                    
                    $stmt = $this->pdo->prepare("SELECT id, name FROM sketches WHERE name LIKE ? OR description LIKE ? ORDER BY id DESC LIMIT 15");
                    $stmt->execute([$like, $like]);
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $linkedCount = 0;
                    foreach ($results as $row) {
                        $chk = $this->pdo->prepare("INSERT IGNORE INTO agent_session_items (session_id, item_id) VALUES (?, ?)");
                        $chk->execute([$sessionId, $row['id']]);
                        if ($chk->rowCount() > 0) $linkedCount++;
                    }
                    $log[] = "Searched '{$queryStr}': Found " . count($results) . " and linked {$linkedCount} new sketches to session.";
                }
                elseif ($action === 'unlink') {
                    $id = (int)($op['id'] ?? 0);
                    if ($id) {
                        $this->pdo->prepare("DELETE FROM agent_session_items WHERE session_id = ? AND item_id = ?")->execute([$sessionId, $id]);
                        $log[] = "Unlinked sketch #{$id} from session.";
                    }
                } else {
                    $log[] = "Ignored unknown action requested: '{$action}'";
                }
            } catch (\Throwable $t) {
                $log[] = "Failed {$action}: " . $t->getMessage();
            }
        }

        return $log;
    }
}
