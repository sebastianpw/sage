<?php
// src/Service/SplashEngine.php
// ─────────────────────────────────────────────────────────────────────────────
// SPLASH — The Writers' Room Engine
//
// A linear execution engine that passes a document through a configured chain
// of AI roles. Each role takes the output of the previous role (plus the 
// original source material) and refines it according to its specific prompt.
// ─────────────────────────────────────────────────────────────────────────────
namespace App\Service;

use PDO;
use Exception;

class SplashEngine
{
    private PDO $pdo;
    private $spw;
    private GeneratorRepository $repo;

    public function __construct()
    {
        global $spw;
        $this->spw = $spw;
        $this->pdo = $spw->getPDO();
        $this->repo = new GeneratorRepository($this->pdo);
    }

    // ── TEMPLATES ────────────────────────────────────────────────────────────

    public function listTemplates(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM splash_templates WHERE user_id = ? ORDER BY updated_at DESC");
        $stmt->execute([$userId]);
        return array_map([$this, 'hydrateTemplate'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Paginated variant of listTemplates(). Returns a page envelope
     * ({items, page, limit, total, total_pages}) rather than a bare array,
     * so the UI can render prev/next controls and a jump-to-page input
     * without needing a separate count query of its own.
     */
    public function listTemplatesPaged(int $userId, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM splash_templates WHERE user_id = ?");
        $countStmt->execute([$userId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT * FROM splash_templates WHERE user_id = ? ORDER BY updated_at DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = array_map([$this, 'hydrateTemplate'], $stmt->fetchAll(PDO::FETCH_ASSOC));

        return [
            'templates' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => max(1, (int)ceil($total / $limit)),
        ];
    }

    public function getTemplate(int $userId, int $templateId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM splash_templates WHERE id = ? AND user_id = ?");
        $stmt->execute([$templateId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrateTemplate($row) : null;
    }

    private function hydrateTemplate(array $row): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM splash_template_roles WHERE template_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$row['id']]);
        $row['roles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $row;
    }

    public function saveTemplate(int $userId, array $tpl): array
    {
        $name = trim($tpl['name'] ?? '');
        $roles = $tpl['roles'] ?? [];

        if ($name === '') throw new Exception('Template name is required.');
        if (empty($roles)) throw new Exception('At least one role is required.');

        $this->pdo->beginTransaction();
        try {
            if (!empty($tpl['id'])) {
                $stmt = $this->pdo->prepare("UPDATE splash_templates SET name=?, description=?, is_active=? WHERE id=? AND user_id=?");
                $stmt->execute([$name, $tpl['description'] ?? '', !empty($tpl['is_active']) ? 1 : 1, $tpl['id'], $userId]);
                $templateId = (int)$tpl['id'];
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO splash_templates (user_id, name, description, is_active) VALUES (?, ?, ?, 1)");
                $stmt->execute([$userId, $name, $tpl['description'] ?? '']);
                $templateId = (int)$this->pdo->lastInsertId();
            }

            $this->pdo->prepare("DELETE FROM splash_template_roles WHERE template_id = ?")->execute([$templateId]);

            $rStmt = $this->pdo->prepare("
                INSERT INTO splash_template_roles (template_id, sort_order, role_name, generator_config_id, prompt_instructions)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($roles as $i => $r) {
                if (empty($r['generator_config_id'])) throw new Exception("Role at index {$i} is missing a generator.");
                $rStmt->execute([
                    $templateId,
                    $i,
                    $r['role_name'] ?? 'Role ' . ($i + 1),
                    $r['generator_config_id'],
                    $r['prompt_instructions'] ?? ''
                ]);
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->getTemplate($userId, $templateId);
    }

    public function deleteTemplate(int $userId, int $templateId): void
    {
        $this->pdo->prepare("DELETE FROM splash_template_roles WHERE template_id = ?")->execute([$templateId]);
        $this->pdo->prepare("DELETE FROM splash_templates WHERE id=? AND user_id=?")->execute([$templateId, $userId]);
    }

    // ── RUNS ─────────────────────────────────────────────────────────────────

    public function listRuns(int $userId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, t.name AS template_name
            FROM splash_runs r
            JOIN splash_templates t ON t.id = r.template_id
            WHERE r.user_id = ?
            ORDER BY r.updated_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Paginated variant of listRuns(). Returns a page envelope
     * ({items, page, limit, total, total_pages}) rather than a bare array,
     * so the UI can render prev/next controls and a jump-to-page input
     * without needing a separate count query of its own.
     */
    public function listRunsPaged(int $userId, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM splash_runs WHERE user_id = ?");
        $countStmt->execute([$userId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT r.*, t.name AS template_name
            FROM splash_runs r
            JOIN splash_templates t ON t.id = r.template_id
            WHERE r.user_id = ?
            ORDER BY r.updated_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'runs' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => max(1, (int)ceil($total / $limit)),
        ];
    }

    public function getRun(int $userId, int $runId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, t.name AS template_name
            FROM splash_runs r
            JOIN splash_templates t ON t.id = r.template_id
            WHERE r.id = ? AND r.user_id = ?
        ");
        $stmt->execute([$runId, $userId]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$run) return null;

        $stmt = $this->pdo->prepare("SELECT * FROM splash_run_roles WHERE run_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$runId]);
        $run['roles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $run;
    }

    public function startRun(int $userId, int $templateId, string $sourceText, ?string $title = null): array
    {
        $template = $this->getTemplate($userId, $templateId);
        if (!$template) throw new Exception("Template {$templateId} not found.");
        if (empty($template['roles'])) throw new Exception("Template has no roles.");

        $runTitle = $title ?: ($template['name'] . ' — ' . date('Y-m-d H:i'));

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO splash_runs (user_id, template_id, title, source_text, status)
                VALUES (?, ?, ?, ?, 'running')
            ");
            $stmt->execute([$userId, $templateId, $runTitle, $sourceText]);
            $runId = (int)$this->pdo->lastInsertId();

            $rStmt = $this->pdo->prepare("
                INSERT INTO splash_run_roles (run_id, template_role_id, sort_order, role_name, generator_config_id, prompt_instructions, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            foreach ($template['roles'] as $i => $r) {
                $rStmt->execute([
                    $runId, $r['id'], $i, $r['role_name'], $r['generator_config_id'], $r['prompt_instructions']
                ]);
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->getRun($userId, $runId);
    }

    public function cancelRun(int $userId, int $runId): void
    {
        $this->pdo->prepare("UPDATE splash_runs SET status='cancelled', finished_at=NOW() WHERE id=? AND user_id=?")->execute([$runId, $userId]);
    }

    // ── EXECUTION ────────────────────────────────────────────────────────────

    /**
     * Executes the NEXT pending role in the chain. 
     * Called by WTF polling loop or CLI script.
     */
    public function runNextRole(int $userId, int $runId): array
    {
        $run = $this->getRun($userId, $runId);
        if (!$run) throw new Exception("Run {$runId} not found.");
        if ($run['status'] !== 'running') return $run;

        // Find the active or next pending role
        $targetRole = null;
        $previousOutput = null;

        foreach ($run['roles'] as $role) {
            if ($role['status'] === 'done') {
                $previousOutput = $role['output_text'];
            } elseif ($role['status'] === 'pending' || $role['status'] === 'active') {
                $targetRole = $role;
                break;
            }
        }

        if (!$targetRole) {
            // All roles are done! Mark run complete.
            $this->pdo->prepare("UPDATE splash_runs SET status='done', final_output_text=?, finished_at=NOW() WHERE id=?")
                ->execute([$previousOutput, $runId]);
            return $this->getRun($userId, $runId);
        }

        $roleId = $targetRole['id'];
        
        // Mark active
        if ($targetRole['status'] === 'pending') {
            $this->pdo->prepare("UPDATE splash_run_roles SET status='active', started_at=NOW(), input_text=? WHERE id=?")
                ->execute([$previousOutput, $roleId]);
        }

        // Build prompt (Avoiding the Telephone Game)
        $promptParts = [];
        if ($previousOutput === null) {
            $promptParts[] = "═══ SOURCE LORE & PLOT ═══\n" . $run['source_text'] . "\n══════════════════════════";
            $promptParts[] = "INSTRUCTIONS: You are the first writer. Draft the scene/script based on the source lore above.";
        } else {
            $promptParts[] = "═══ ORIGINAL SOURCE LORE ═══\n" . $run['source_text'] . "\n════════════════════════════";
            $promptParts[] = "═══ CURRENT DRAFT (From Previous Role) ═══\n" . $previousOutput . "\n══════════════════════════════════════════";
            $promptParts[] = "INSTRUCTIONS: You are refining the CURRENT DRAFT. Do not lose context from the ORIGINAL LORE.";
        }
        
        if (!empty($targetRole['prompt_instructions'])) {
            $promptParts[] = "YOUR SPECIFIC ROLE INSTRUCTIONS:\n" . $targetRole['prompt_instructions'];
        }

        $prompt = implode("\n\n", $promptParts);

        try {
            $resultText = $this->callGenerator($userId, $targetRole['generator_config_id'], [
                ['role' => 'user', 'content' => $prompt]
            ]);

            $this->pdo->prepare("UPDATE splash_run_roles SET status='done', output_text=?, finished_at=NOW() WHERE id=?")
                ->execute([$resultText, $roleId]);

        } catch (Exception $e) {
            $this->pdo->prepare("UPDATE splash_run_roles SET status='failed', error_text=?, finished_at=NOW() WHERE id=?")
                ->execute([$e->getMessage(), $roleId]);
            $this->pdo->prepare("UPDATE splash_runs SET status='failed', last_error=?, finished_at=NOW() WHERE id=?")
                ->execute(["Role '{$targetRole['role_name']}' failed: " . $e->getMessage(), $runId]);
        }

        // Check if there are any roles left, if not, mark run done on next tick.
        return $this->getRun($userId, $runId);
    }

    private function callGenerator(int $userId, string $configId, array $conversationMessages): string
    {
        $rec = $this->repo->findActiveForUser($configId, $userId);
        if (!$rec) throw new Exception("Generator config '{$configId}' not found.");

        $sysParts = [];
        if ($rec->systemRole) $sysParts[] = $rec->systemRole;
        if (!empty($rec->instructions)) $sysParts[] = "═══ RULES & PROTOCOLS ═══\n" . implode("\n\n", $rec->instructions);

        $messages = [['role' => 'system', 'content' => implode("\n\n", $sysParts)]];
        foreach ($conversationMessages as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $aiOptions = [];
        if (isset($rec->parameters['temperature'])) $aiOptions['temperature'] = (float)$rec->parameters['temperature']['default'];
        $aiOptions['max_tokens'] = isset($rec->parameters['max_tokens']) ? (int)$rec->parameters['max_tokens']['default'] : 16384;

        return $this->spw->getAIProvider()->sendMessage($rec->model, $messages, $aiOptions);
    }
}