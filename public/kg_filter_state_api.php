<?php
// public/kg_filter_state_api.php
// ─────────────────────────────────────────────────────────────────────────────
// Server-side mirror for the graph-filter selection made in kg_filter.php.
// The browser still uses localStorage for instant load; this endpoint keeps a
// single-row global mirror so non-browser consumers (cli_autopilot.php) can
// read the same selection.
//
// GET  ?mode=live|staging  → { ok, ids: [...]|null }   (null = all nodes visible)
// POST { mode, ids }       → { ok }                    (ids null = clear / all)
// ─────────────────────────────────────────────────────────────────────────────

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => 'Not authenticated']));
}

/** @var \PDO $pdo */

function okArr(array $data): string {
    return json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
}
function fail(string $msg, int $code = 400): string {
    http_response_code($code);
    return json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $mode = $_GET['mode'] ?? 'live';
        if (!in_array($mode, ['live', 'staging'], true)) exit(fail('Invalid mode'));
        $col = $mode . '_ids';

        $stmt = $pdo->query("SELECT `{$col}` FROM kg_filter_state WHERE id = 1 LIMIT 1");
        $row  = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;

        $ids = null;
        if ($row && isset($row[$col]) && $row[$col] !== null && $row[$col] !== '') {
            $decoded = json_decode((string)$row[$col], true);
            if (is_array($decoded)) $ids = array_map('intval', $decoded);
        }
        exit(okArr(['ids' => $ids]));
    }

    if ($method === 'POST') {
        $raw   = file_get_contents('php://input');
        $input = json_decode($raw ?: '{}', true) ?? [];

        $mode = $input['mode'] ?? 'live';
        if (!in_array($mode, ['live', 'staging'], true)) exit(fail('Invalid mode'));
        $col = $mode . '_ids';

        $ids  = $input['ids'] ?? null;
        $json = null;
        if ($ids !== null) {
            if (!is_array($ids)) exit(fail('ids must be an array or null'));
            $ids  = array_values(array_unique(array_map('intval', $ids)));
            $json = json_encode($ids, JSON_UNESCAPED_UNICODE);
        }

        // Upsert the single row (id = 1). Column name is one of live_ids /
        // staging_ids only — safe to interpolate.
        $stmt = $pdo->prepare(
            "INSERT INTO kg_filter_state (id, `{$col}`, updated_at)
             VALUES (1, :json, NOW())
             ON DUPLICATE KEY UPDATE `{$col}` = VALUES(`{$col}`), updated_at = NOW()"
        );
        $stmt->execute([':json' => $json]);

        exit(okArr([]));
    }

    exit(fail('Unsupported method', 405));

} catch (\Throwable $e) {
    http_response_code(500);
    exit(json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
}
