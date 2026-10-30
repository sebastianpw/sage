<?php
namespace App\Core;

use PDO;

class KgAgentModule extends AiAgentModule {

    public function getModulePrefix(): string {
        return 'kg';
    }

    protected function getDbSchemaInstructions(): string {
        return <<<EOT
You have full CRUD access to the Knowledge Graph (`kg_categories`, `kg_nodes`, `kg_node_items`).
CRITICAL: To reliably link nodes with edges in a single response, you should first reserve an ID block, then assign explicit IDs from that block when adding nodes.

1. Reserve an ID Block (Allocates a guaranteed empty range of IDs for your nodes or edges):
{"action": "reserve_id_block", "project_name": "Emberveil Characters", "target_table": "kg_nodes", "size": 1000, "start_id": 2000}
- `target_table` MUST be either "kg_nodes" or "kg_node_items".
- `start_id` is OPTIONAL. If requested by the user, use it. Otherwise, omit it and the system will auto-calculate a safe start ID above the current MAX(id).

2. Add Category:
{"action": "add_category", "id": 150, "name": "Factions", "parent_id": null}

3. Add Node (Use an ID from your reserved block so you can immediately reference it in edges!):
{"action": "add_node", "id": 5001, "name": "Kaelen", "node_type": "character", "category_id": 150, "description": "A rogue.", "keywords": "rogue, hero"}
*node_types: note, relationship, character, location, event, concept, arc, episode*

4. Add Edge (Link two nodes):
{"action": "add_edge", "id": 85000, "node_id": 5001, "item_type": "kg_node", "item_id": 5002, "relationship": "allies_with", "note": "Since year 5"}

5. Update Node:
{"action": "update_node", "id": 5001, "name": "Kaelen Vance", "description": "Updated lore..."}

6. Search / Read Nodes (Adds existing nodes to the session working set):
{"action": "search_nodes", "query": "Kaelen"}

7. Unlink Node from Session Working Set (Does NOT delete globally):
{"action": "unlink_node", "id": 5001}
EOT;
    }

    public function getCurrentStateContext(string $sessionId, int $userId): string {
        // 1. Get Table Max IDs (Crucial for AI math)
        $maxNode = (int)$this->pdo->query("SELECT MAX(id) FROM kg_nodes")->fetchColumn();
        $maxEdge = (int)$this->pdo->query("SELECT MAX(id) FROM kg_node_items")->fetchColumn();

        // 2. Get ID Reservations
        $reservations = $this->pdo->query("SELECT * FROM kg_id_reservations ORDER BY id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
        
        // 3. Get Categories (Context for where to put things)
        $categories = $this->pdo->query("SELECT id, parent_id, name FROM kg_categories ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        // 4. Get Nodes actively touched in this session (Working Set)
        $stmt = $this->pdo->prepare("
            SELECT n.id, n.name, n.node_type, n.category_id, n.description
            FROM agent_session_items i
            JOIN kg_nodes n ON i.item_id = n.id
            WHERE i.session_id = ?
            ORDER BY i.created_at DESC LIMIT 25
        ");
        $stmt->execute([$sessionId]);
        $sessionNodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $state = [
            'database_max_ids' => [
                'kg_nodes_max_id' => $maxNode,
                'kg_node_items_max_id' => $maxEdge
            ],
            'reserved_id_blocks' => $reservations,
            'existing_categories' => $categories,
            'session_working_set' => $sessionNodes
        ];

        return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function executeOperations(array $operations, string $sessionId, int $userId): array {
        $log = [];

        foreach ($operations as $op) {
            $action = strtolower(trim($op['action'] ?? ''));
            
            // Forgiving Parser fallback
            if (!$action) {
                foreach (['reserve_id_block', 'add_category', 'add_node', 'add_edge', 'update_node', 'search_nodes', 'unlink_node'] as $possibleAction) {
                    if (isset($op[$possibleAction])) {
                        $action = $possibleAction;
                        break;
                    }
                }
            }

            try {
                if ($action === 'reserve_id_block') {
                    $project = trim($op['project_name'] ?? 'Unnamed Project');
                    $targetTable = trim($op['target_table'] ?? 'kg_nodes');
                    if (!in_array($targetTable, ['kg_nodes', 'kg_node_items'])) $targetTable = 'kg_nodes';
                    
                    $size = max(1, min(100000, (int)($op['size'] ?? 100)));
                    $startId = !empty($op['start_id']) ? (int)$op['start_id'] : 0;
                    
                    if ($startId <= 0) {
                        // Auto calc if AI omitted it
                        $maxTable = (int)$this->pdo->query("SELECT MAX(id) FROM `{$targetTable}`")->fetchColumn();
                        
                        $stmtRes = $this->pdo->prepare("SELECT MAX(end_id) FROM kg_id_reservations WHERE target_table = ?");
                        $stmtRes->execute([$targetTable]);
                        $maxReserved = (int)$stmtRes->fetchColumn();
                        
                        $startId = max($maxTable, $maxReserved) + 100; 
                    }
                    
                    $endId = $startId + $size - 1;

                    $stmt = $this->pdo->prepare("INSERT INTO kg_id_reservations (project_name, target_table, start_id, end_id, current_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$project, $targetTable, $startId, $endId, $startId]);
                    
                    $log[] = "Reserved block '{$project}' for {$targetTable}: IDs {$startId} through {$endId}.";
                }
                elseif ($action === 'add_category') {
                    $id = !empty($op['id']) ? (int)$op['id'] : null;
                    $name = trim($op['name'] ?? 'New Category');
                    $parentId = !empty($op['parent_id']) ? (int)$op['parent_id'] : null;

                    if ($id) {
                        $stmt = $this->pdo->prepare("INSERT INTO kg_categories (id, name, parent_id) VALUES (?, ?, ?)");
                        $stmt->execute([$id, $name, $parentId]);
                    } else {
                        $stmt = $this->pdo->prepare("INSERT INTO kg_categories (name, parent_id) VALUES (?, ?)");
                        $stmt->execute([$name, $parentId]);
                        $id = (int)$this->pdo->lastInsertId();
                    }
                    $log[] = "Added category #{$id} ({$name})";
                }
                elseif ($action === 'add_node') {
                    $id = !empty($op['id']) ? (int)$op['id'] : null;
                    $name = trim($op['name'] ?? 'Unnamed Node');
                    $nodeType = trim($op['node_type'] ?? 'note');
                    $catId = !empty($op['category_id']) ? (int)$op['category_id'] : null;
                    $desc = trim($op['description'] ?? '');
                    $keywords = trim($op['keywords'] ?? '');

                    if ($id) {
                        $stmt = $this->pdo->prepare("INSERT INTO kg_nodes (id, name, node_type, category_id, description, keywords, content) VALUES (?, ?, ?, ?, ?, ?, '')");
                        $stmt->execute([$id, $name, $nodeType, $catId, $desc, $keywords]);
                    } else {
                        $stmt = $this->pdo->prepare("INSERT INTO kg_nodes (name, node_type, category_id, description, keywords, content) VALUES (?, ?, ?, ?, ?, '')");
                        $stmt->execute([$name, $nodeType, $catId, $desc, $keywords]);
                        $id = (int)$this->pdo->lastInsertId();
                    }
                    
                    $this->pdo->prepare("INSERT IGNORE INTO agent_session_items (session_id, item_id) VALUES (?, ?)")
                        ->execute([$sessionId, $id]);

                    $log[] = "Added node #{$id} ({$name})";
                }
                elseif ($action === 'update_node') {
                    $id = (int)($op['id'] ?? 0);
                    if (!$id) continue;

                    $updates = [];
                    $params = [];
                    if (isset($op['name'])) { $updates[] = "name = ?"; $params[] = trim($op['name']); }
                    if (isset($op['description'])) { $updates[] = "description = ?"; $params[] = trim($op['description']); }
                    if (isset($op['keywords'])) { $updates[] = "keywords = ?"; $params[] = trim($op['keywords']); }
                    if (isset($op['node_type'])) { $updates[] = "node_type = ?"; $params[] = trim($op['node_type']); }
                    if (array_key_exists('category_id', $op)) { $updates[] = "category_id = ?"; $params[] = $op['category_id'] ? (int)$op['category_id'] : null; }

                    if (!empty($updates)) {
                        $params[] = $id;
                        $this->pdo->prepare("UPDATE kg_nodes SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
                        $log[] = "Updated node #{$id}";
                    }
                }
                elseif ($action === 'add_edge') {
                    $id = !empty($op['id']) ? (int)$op['id'] : null;
                    $nodeId = (int)($op['node_id'] ?? 0);
                    $itemType = trim($op['item_type'] ?? 'kg_node');
                    $itemId = !empty($op['item_id']) ? (int)$op['item_id'] : null;
                    $itemLabel = trim($op['item_label'] ?? '');
                    $rel = trim($op['relationship'] ?? '');
                    $note = trim($op['note'] ?? '');

                    if ($nodeId) {
                        $max = $this->pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM kg_node_items WHERE node_id=?");
                        $max->execute([$nodeId]);
                        $nextOrder = $max->fetchColumn();

                        if ($id) {
                            $stmt = $this->pdo->prepare("INSERT INTO kg_node_items (id, node_id, item_type, item_id, item_label, relationship, note, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$id, $nodeId, $itemType, $itemId, $itemLabel, $rel, $note, $nextOrder]);
                        } else {
                            $stmt = $this->pdo->prepare("INSERT INTO kg_node_items (node_id, item_type, item_id, item_label, relationship, note, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$nodeId, $itemType, $itemId, $itemLabel, $rel, $note, $nextOrder]);
                            $id = (int)$this->pdo->lastInsertId();
                        }
                        
                        $log[] = "Added edge #{$id}: Node #{$nodeId} -> {$itemType} #" . ($itemId ?? "'$itemLabel'") . " [$rel]";
                    }
                }
                elseif ($action === 'search_nodes') {
                    $queryStr = trim($op['query'] ?? '');
                    $like = '%' . $queryStr . '%';
                    $stmt = $this->pdo->prepare("SELECT id, name FROM kg_nodes WHERE status='active' AND (name LIKE ? OR description LIKE ?) LIMIT 15");
                    $stmt->execute([$like, $like]);
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $linked = 0;
                    foreach ($results as $r) {
                        $chk = $this->pdo->prepare("INSERT IGNORE INTO agent_session_items (session_id, item_id) VALUES (?, ?)");
                        $chk->execute([$sessionId, $r['id']]);
                        if ($chk->rowCount()) $linked++;
                    }
                    $log[] = "Searched '{$queryStr}': Found " . count($results) . ", linked {$linked} to session working set.";
                }
                elseif ($action === 'unlink_node') {
                    $id = (int)($op['id'] ?? 0);
                    if ($id) {
                        $this->pdo->prepare("DELETE FROM agent_session_items WHERE session_id = ? AND item_id = ?")->execute([$sessionId, $id]);
                        $log[] = "Unlinked node #{$id} from session working set.";
                    }
                }
                else {
                    $log[] = "Ignored unknown action requested: '{$action}'";
                }
            } catch (\Throwable $t) {
                $log[] = "Failed {$action}: " . $t->getMessage();
            }
        }

        return $log;
    }
}

