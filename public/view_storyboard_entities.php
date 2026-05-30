<?php
// public/view_storyboard_entities.php
// Shows storyboard frames + resolves polymorphic entity names.
// Usage examples:
//   view_storyboard_entities.php?storyboard=52
//   view_storyboard_entities.php?storyboard=52&export=csv
//   view_storyboard_entities.php?storyboard=52&export=json

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

header('X-Content-Type-Options: nosniff');

$pdo = $spw->getPDO();

// Params
$storyboardId = isset($_GET['storyboard']) ? (int)$_GET['storyboard'] : 52;
$export = isset($_GET['export']) ? strtolower($_GET['export']) : null;

try {
    // 1) Fetch storyboard frames (include standalone storyboard frames where frame_id IS NULL)
    $sql = "SELECT sf.*, f.entity_type, f.entity_id, f.name AS frame_name
            FROM storyboard_frames sf
            LEFT JOIN frames f ON f.id = sf.frame_id
            WHERE sf.storyboard_id = :sid
            ORDER BY sf.sort_order ASC, sf.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['sid' => $storyboardId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        if ($export) {
            if ($export === 'json') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['rows' => []], JSON_PRETTY_PRINT);
                exit;
            }
            header('Content-Type: text/plain; charset=utf-8');
            echo "No rows for storyboard $storyboardId\n";
            exit;
        }
        // HTML fallback: show message (theme-aware JS still runs)
        echo "<!doctype html><meta charset='utf-8'><title>Storyboard $storyboardId</title>";
        echo "<h2>No frames found for storyboard $storyboardId</h2>";
        echo "<p><a href='?storyboard={$storyboardId}&export=csv'>Export CSV</a> | <a href='?storyboard={$storyboardId}&export=json'>Export JSON</a></p>";
        exit;
    }

    // 2) Build whitelist of tables that contain a `name` column in this DB schema
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $colStmt = $pdo->prepare("
        SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = :db
          AND COLUMN_NAME = 'name'
    ");
    $colStmt->execute(['db' => $dbName]);
    $validTables = $colStmt->fetchAll(PDO::FETCH_COLUMN);
    $validTablesMap = array_flip($validTables); // quick isset check

    // 3) Prepare lookup statements cache for entity tables we'll actually need
    $entityTypes = [];
    foreach ($rows as $r) {
        if (!empty($r['entity_type'])) $entityTypes[$r['entity_type']] = true;
    }
    $lookupStmts = [];
    foreach (array_keys($entityTypes) as $etype) {
        if (isset($validTablesMap[$etype])) {
            // safe: $etype validated via INFORMATION_SCHEMA
            $lookupStmts[$etype] = $pdo->prepare("SELECT name FROM `{$etype}` WHERE id = :id LIMIT 1");
        }
    }

    // 4) Resolve entity names
    $out = [];
    foreach ($rows as $r) {
        $resolvedName = null;
        $etype = $r['entity_type'] ?? null;
        $eid = isset($r['entity_id']) ? (int)$r['entity_id'] : null;

        if ($etype && $eid && isset($lookupStmts[$etype])) {
            try {
                $lookupStmts[$etype]->execute(['id' => $eid]);
                $resolvedName = $lookupStmts[$etype]->fetchColumn();
                if ($resolvedName === false) $resolvedName = null;
            } catch (Throwable $e) {
                $resolvedName = null;
            }
        }

        // Prefer frames.frame.name if present, otherwise storyboard_frames.name
        $frameName = $r['frame_name'] ?? null;
        if (!$frameName && !empty($r['name'])) {
            $frameName = $r['name'];
        }

        $out[] = [
            'storyboard_frame_id' => (int)$r['id'],
            'storyboard_filename'  => $r['filename'] ?? null,
            'storyboard_name'      => $r['name'] ?? null,
            'storyboard_sort'      => (int)($r['sort_order'] ?? 0),
            'frame_id'             => isset($r['frame_id']) ? (int)$r['frame_id'] : null,
            'frame_name'           => $frameName,
            'frames_entity_type'   => $etype,
            'frames_entity_id'     => $eid,
            'entity_name'          => $resolvedName
        ];
    }

    // 5) Export if requested
    if ($export === 'csv') {
        // <-- FIX: explicit escape parameter to avoid deprecation warning -->
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="storyboard_' . $storyboardId . '_entities.csv"');
        $outFp = fopen('php://output', 'w');

        // Column headers
        // Provide explicit separator, enclosure and escape char
        fputcsv($outFp, array_keys($out[0]), ',', '"', '\\');

        foreach ($out as $r) {
            // Ensure scalar values for CSV
            $row = array_map(function($v) {
                if (is_null($v)) return '';
                if (is_bool($v)) return $v ? '1' : '0';
                return (string)$v;
            }, $r);
            // <-- FIX: explicit escape parameter -->
            fputcsv($outFp, $row, ',', '"', '\\');
        }
        fclose($outFp);
        exit;
    } elseif ($export === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['storyboard' => $storyboardId, 'rows' => $out], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // 6) HTML display (theme-aware + storyboard selector)
    ?>
    <!doctype html>
    <html lang="en" data-theme="light">
    <head>
        <meta charset="utf-8">
        <title>Storyboard <?= htmlspecialchars($storyboardId) ?> — Entities</title>

        <meta name="viewport" content="width=device-width,initial-scale=1">
        <style>
            :root {
                --bg: #f8f9fb;
                --card: #ffffff;
                --text: #111;
                --muted: #666;
                --border: #e6e8eb;
                --accent: #2b8aef;
                --btn-bg: #fff;
            }
            [data-theme="dark"] {
                --bg: #0f1720;
                --card: #0b1116;
                --text: #e6eef8;
                --muted: #9fb0c9;
                --border: #14202a;
                --accent: #4aa3ff;
                --btn-bg: #0b1116;
            }

            html,body{height:100%;margin:0;background:var(--bg);color:var(--text);font-family:system-ui,Arial,sans-serif;}
            .wrap{max-width:1100px;margin:18px auto;padding:18px;}
            header{display:flex;align-items:center;gap:12px;margin-bottom:14px;}
            .title{font-size:1.15rem;font-weight:700;}
            .controls{margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
            .sb-input{width:110px;padding:6px 8px;border:1px solid var(--border);background:var(--card);color:var(--text);border-radius:6px;}
            .sb-label{font-size:0.9rem;color:var(--muted);margin-right:6px;}
            a.btn{display:inline-block;padding:6px 10px;border:1px solid var(--border);border-radius:6px;text-decoration:none;color:var(--text);background:var(--btn-bg);box-shadow:none}
            table{border-collapse:collapse;width:100%;background:var(--card);border:1px solid var(--border);border-radius:8px;overflow:hidden}
            th,td{padding:10px;border-bottom:1px solid var(--border);text-align:left}
            th{background:rgba(0,0,0,0.03);font-weight:700;color:var(--muted);font-size:0.85rem;text-transform:uppercase}
            tr:nth-child(even){background:transparent}
            .muted{color:var(--muted);font-size:0.9rem}
            .small{font-size:0.85rem}
            .top-actions{display:flex;gap:8px;align-items:center;margin-bottom:12px}
            .note{margin-top:8px;color:var(--muted);font-size:0.9rem}

            /* Export group: sits next to Apply on wide screens, drops under on narrow */
            .export-group{display:flex;gap:8px;align-items:center}
            @media (max-width:720px){
                .wrap{padding:10px}
                table,thead,tbody,tr,td,th{display:block}
                thead{display:none}
                tr{margin-bottom:12px;padding:10px;border-radius:8px;border:1px solid var(--border)}
                td{border:none;padding:6px 0}
                td::before{content:attr(data-label);display:block;color:var(--muted);font-weight:600;margin-bottom:6px}
                /* ensure export group wraps to next line */
                .export-group{width:100%;margin-top:8px}
            }
        </style>
        <script>
            // Apply theme early to avoid flash
            (function() {
                try {
                    var theme = localStorage.getItem('spw_theme');
                    if (theme === 'dark' || theme === 'light') {
                        document.documentElement.setAttribute('data-theme', theme);
                    } else {
                        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                        document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
                    }
                } catch (e) {}
            })();
        </script>
    </head>
    <body>
    <div class="wrap">
        <header>
            <div>
                <div class="title">Storyboard <span id="sbCurrent"><?= htmlspecialchars($storyboardId) ?></span> — resolved entity names</div>
                <div class="note">This view looks up the entity name from the table stored in <code>frames.entity_type</code>. Exports available.</div>
            </div>

            <div class="controls">
                <label class="sb-label" for="sbInput">Storyboard</label>
                <input id="sbInput" class="sb-input" type="number" min="1" value="<?= htmlspecialchars($storyboardId) ?>" title="Enter storyboard id and press Enter or click Apply">
                <button id="sbApply" class="btn small">Apply</button>

                <div class="export-group">
                    <a class="btn" href="?storyboard=<?= urlencode($storyboardId) ?>&export=csv">Export CSV</a>
                    <a class="btn" href="?storyboard=<?= urlencode($storyboardId) ?>&export=json">Export JSON</a>
                    <a class="btn" href="javascript:location.reload()">Refresh</a>
                </div>
            </div>
        </header>

        <table aria-describedby="table-desc">
            <thead>
                <tr>
                    <th style="width:64px">SB Frame ID</th>
                    <th style="width:64px">Sort</th>
                    <th>SB Name</th>
                    <th>SB Filename</th>
                    <th style="width:80px">Frame ID</th>
                    <th>Frame Name</th>
                    <th>Entity Table</th>
                    <th style="width:80px">Entity ID</th>
                    <th>Entity Name</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($out as $r): ?>
                <tr>
                    <td data-label="SB Frame ID"><?= htmlspecialchars($r['storyboard_frame_id']) ?></td>
                    <td data-label="Sort"><?= htmlspecialchars($r['storyboard_sort']) ?></td>
                    <td data-label="SB Name"><?= htmlspecialchars($r['storyboard_name'] ?? '') ?></td>
                    <td data-label="SB Filename" class="small muted"><?= htmlspecialchars($r['storyboard_filename'] ?? '') ?></td>
                    <td data-label="Frame ID"><?= htmlspecialchars($r['frame_id'] ?? '') ?></td>
                    <td data-label="Frame Name"><?= htmlspecialchars($r['frame_name'] ?? '') ?></td>
                    <td data-label="Entity Table"><?= htmlspecialchars($r['frames_entity_type'] ?? '') ?></td>
                    <td data-label="Entity ID"><?= htmlspecialchars($r['frames_entity_id'] ?? '') ?></td>
                    <td data-label="Entity Name"><?= htmlspecialchars($r['entity_name'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="note">Tip: change the storyboard id above to quickly inspect other storyboards. Theme follows <code>localStorage.spw_theme</code>.</p>
    </div>

    <script>
        (function() {
            const input = document.getElementById('sbInput');
            const apply = document.getElementById('sbApply');

            function gotoStoryboard(id) {
                id = parseInt(id) || 0;
                if (id <= 0) return;
                const params = new URLSearchParams(window.location.search);
                params.set('storyboard', id);
                params.delete('export');
                window.location.search = params.toString();
            }

            apply.addEventListener('click', function() { gotoStoryboard(input.value); });
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { gotoStoryboard(input.value); }
            });

            input.addEventListener('change', function() { document.getElementById('sbCurrent').textContent = input.value; });

            // Watch for theme changes elsewhere in the app
            try {
                window.addEventListener('storage', function(e) {
                    if (e.key === 'spw_theme') {
                        var theme = localStorage.getItem('spw_theme');
                        if (theme === 'dark' || theme === 'light') document.documentElement.setAttribute('data-theme', theme);
                    }
                }, false);
            } catch (e) {}
        })();
    </script>
    </body>
    </html>
    <?php
} catch (Throwable $e) {
    http_response_code(500);
    if ($export === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        echo "<h2>Error</h2><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    }
    exit;
}