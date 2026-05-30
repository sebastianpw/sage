<?php
// public/exposanimaticism.php
// Exposanimaticism — Character Variant Browser (Poses / Expressions / Anima Poses)
// ─────────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

// ───────────────────────────────────────────────────────
// API HANDLER
// ───────────────────────────────────────────────────────
if (isset($_REQUEST['api_action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['api_action'];

    try {
        // ── 1. SEARCH CHARACTERS (AJAX select) ─────────────────────────────
        if ($action === 'search_characters') {
            $q     = trim($_GET['q'] ?? '');
            $limit = min(2000, max(1, (int)($_GET['limit'] ?? 2000)));
            $where = '1=1';
            $params = [];
            if ($q !== '') {
                $where = '(name LIKE ? OR id = ?)';
                $params = ["%$q%", intval($q)];
            }
            $rows = $pdo->prepare("SELECT id, name FROM characters WHERE $where ORDER BY id ASC LIMIT $limit");
            $rows->execute($params);
            echo json_encode(['status' => 'success', 'data' => $rows->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        // ── 2. SEARCH VARIANTS (poses / expressions / anima poses) ─────────
        if ($action === 'search_variants') {
            $area  = $_GET['area'] ?? 'poses';
            $q     = trim($_GET['q'] ?? '');
            $limit = min(2000, max(1, (int)($_GET['limit'] ?? 2000)));

            $tableMap = [
                'poses'       => 'poses',
                'expressions' => 'anivoc_expressions',
                'anima_poses' => 'poses_anima',
            ];
            $table = $tableMap[$area] ?? 'poses';

            $where  = '1=1';
            $params = [];
            if ($q !== '') {
                $where  = '(name LIKE ? OR id = ?)';
                $params = ["%$q%", intval($q)];
            }
            $safeTable = '`' . str_replace('`', '', $table) . '`';
            $rows = $pdo->prepare("SELECT id, name FROM $safeTable WHERE $where ORDER BY id ASC LIMIT $limit");
            $rows->execute($params);
            echo json_encode(['status' => 'success', 'data' => $rows->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        // ── 3. GET RENDERED FRAMES FOR A COMBINATION ───────────────────────
        if ($action === 'get_variant_frames') {
            $area        = $_GET['area']         ?? 'poses';
            $char_id     = (int)($_GET['char_id']    ?? 0);
            $variant_id  = (int)($_GET['variant_id'] ?? 0);

            if (!$char_id) {
                echo json_encode(['status' => 'success', 'frames' => [], 'has_record' => false]);
                exit;
            }

            // Determine entity table, junction table, entity FK column
            $cfg = [
                'poses'       => [
                    'entity_table'   => 'character_poses',
                    'junction_table' => 'frames_2_character_poses',
                    'entity_fk'      => 'pose_id',
                    'entity_type'    => 'character_poses',
                ],
                'expressions' => [
                    'entity_table'   => 'character_expressions',
                    'junction_table' => 'frames_2_character_expressions',
                    'entity_fk'      => 'expression_id',
                    'entity_type'    => 'character_expressions',
                ],
                'anima_poses' => [
                    'entity_table'   => 'character_anima_poses',
                    'junction_table' => 'frames_2_character_anima_poses',
                    'entity_fk'      => 'pose_id',
                    'entity_type'    => 'character_anima_poses',
                ],
            ];
            $c = $cfg[$area] ?? $cfg['poses'];

            $et = '`' . str_replace('`', '', $c['entity_table'])   . '`';
            $jt = '`' . str_replace('`', '', $c['junction_table']) . '`';
            $fk = '`' . str_replace('`', '', $c['entity_fk'])      . '`';

            // Find matching entity record(s)
            $where = "character_id = ?";
            $params = [$char_id];
            if ($variant_id > 0) {
                $where   .= " AND $fk = ?";
                $params[] = $variant_id;
            }

            $entityRows = $pdo->prepare("SELECT id FROM $et WHERE $where ORDER BY id ASC");
            $entityRows->execute($params);
            $entityIds = $entityRows->fetchAll(PDO::FETCH_COLUMN);

            $hasRecord = count($entityIds) > 0;

            if (empty($entityIds)) {
                echo json_encode(['status' => 'success', 'frames' => [], 'has_record' => false]);
                exit;
            }

            $inPh = implode(',', array_fill(0, count($entityIds), '?'));

            $sql = "SELECT f.id as frame_id, f.filename, f.name, f.prompt,
                        CASE WHEN EXISTS (SELECT 1 FROM animatics a WHERE a.img2img_frame_id = f.id) THEN 1 ELSE 0 END as is_imported,
                        CASE WHEN EXISTS (SELECT 1 FROM frame_enhancements fe WHERE fe.img2img_frame_id = f.id) THEN 1 ELSE 0 END as is_enhanced
                    FROM $jt m
                    JOIN frames f ON f.id = m.from_id
                    WHERE m.to_id IN ($inPh)
                    ORDER BY f.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($entityIds);
            $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'success', 'frames' => $frames, 'has_record' => $hasRecord]);
            exit;
        }

        // ── 4. GET SINGLE FRAME (for +Frames modal) ────────────────────────
        if ($action === 'get_single_frame') {
            $fid  = (int)($_GET['frame_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, filename FROM frames WHERE id = ?");
            $stmt->execute([$fid]);
            $frame = $stmt->fetch(PDO::FETCH_ASSOC);
            echo $frame
                ? json_encode(['status' => 'success', 'data' => $frame])
                : json_encode(['status' => 'error', 'message' => 'Frame not found']);
            exit;
        }

        // ── 5. IMPORT FRAME TO ANIMATICS ───────────────────────────────────
        if ($action === 'submit_import') {
            $input    = json_decode(file_get_contents('php://input'), true);
            $frameIds = array_map('intval', $input['frame_ids'] ?? []);
            if (empty($frameIds)) throw new Exception("No frames selected.");

            $idsStr = implode(',', $frameIds);
            $rows   = $pdo->query(
                "SELECT f.id as frame_id, f.filename, f.name as frame_name, f.prompt as frame_prompt
                 FROM frames f WHERE f.id IN ($idsStr)"
            )->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("
                INSERT INTO animatics (name, description, img2img, img2img_frame_id, regenerate_videos, created_at, updated_at)
                VALUES (?, ?, 1, ?, 1, NOW(), NOW())
            ");
            $count = 0;
            $pdo->beginTransaction();
            foreach ($rows as $row) {
                $stmt->execute([
                    $row['frame_name'] ?: $row['filename'],
                    $row['frame_prompt'] ?: '',
                    $row['frame_id'],
                ]);
                $count++;
            }
            $pdo->commit();
            echo json_encode(['status' => 'success', 'count' => $count]);
            exit;
        }

        // ── 6. ENHANCE FRAMES ──────────────────────────────────────────────
        if ($action === 'submit_enhancement') {
            $input       = json_decode(file_get_contents('php://input'), true);
            $frameIds    = array_map('intval', $input['frame_ids']    ?? []);
            $extraFrames = array_map('intval', $input['extra_frames'] ?? []);
            $description = trim($input['description'] ?? '');
            $depth2img   = !empty($input['depth2img']) ? 1 : 0;

            if (empty($frameIds))  throw new Exception("No frames selected.");
            if (!$description)     throw new Exception("Please enter an enhancement instruction.");

            $idsStr  = implode(',', $frameIds);
            $metaData = $pdo->query("SELECT id, entity_id, entity_type FROM frames WHERE id IN ($idsStr)")->fetchAll(PDO::FETCH_ASSOC);

            $stmt      = $pdo->prepare("INSERT INTO frame_enhancements (entity_type, entity_id, description, img2img_frame_id, regenerate_images, depth2img) VALUES (?, ?, ?, ?, 1, ?)");
            $stmtExtra = $pdo->prepare("INSERT INTO frame_enhancement_frames (frame_enhancement_id, frame_id) VALUES (?, ?)");

            $count = 0;
            $pdo->beginTransaction();
            foreach ($metaData as $row) {
                if (!$row['entity_id']) continue;
                $stmt->execute([$row['entity_type'], $row['entity_id'], $description, $row['id'], $depth2img]);
                $enhId = $pdo->lastInsertId();
                foreach ($extraFrames as $exFid) {
                    $stmtExtra->execute([$enhId, $exFid]);
                }
                $count++;
            }
            $pdo->commit();
            echo json_encode(['status' => 'success', 'count' => $count]);
            exit;
        }

        // ── 7. INLINE IMPORT (blank card quick-import) ─────────────────────
        if ($action === 'quick_import') {
            $input       = json_decode(file_get_contents('php://input'), true);
            $area        = $input['area']          ?? 'poses';
            $char_id     = (int)($input['char_id']     ?? 0);
            $variant_id  = (int)($input['variant_id']  ?? 0);
            $imgFrameId  = (int)($input['img2img_frame_id'] ?? 0);
            $exclChar    = !empty($input['exclude_char_desc']);
            $neutralBg   = !empty($input['neutral_white_bg']);
            $forceUpdate = !empty($input['force_update']);

            if (!$char_id || !$variant_id) throw new Exception('Missing character or variant ID.');

            $cfg = [
                'poses'       => ['entity_table' => 'character_poses',       'variant_fk' => 'pose_id',       'variant_table' => 'poses',             'combo_view' => 'v_character_pose_angle_combinations',       'entity_type' => 'character_poses'],
                'expressions' => ['entity_table' => 'character_expressions',  'variant_fk' => 'expression_id', 'variant_table' => 'anivoc_expressions', 'combo_view' => 'v_character_expression_angle_combinations',  'entity_type' => 'character_expressions'],
                'anima_poses' => ['entity_table' => 'character_anima_poses',  'variant_fk' => 'pose_id',       'variant_table' => 'poses_anima',        'combo_view' => 'v_character_anima_pose_angle_combinations',  'entity_type' => 'character_anima_poses'],
            ];
            $c = $cfg[$area] ?? $cfg['poses'];

            $promptSql = $exclChar ? 'v.base_prompt' : 'v.description';
            if ($neutralBg) {
                $promptSql = "CONCAT('((neutral greenscreen background - Pantone: 354 C, Hex: #00FB00)), ', $promptSql)";
            }

            $variantFkCol = '`' . str_replace('`', '', $c['variant_fk']) . '`';
            $et           = '`' . str_replace('`', '', $c['entity_table']) . '`';
            $view         = '`' . str_replace('`', '', $c['combo_view']) . '`';

            $notExists = "AND NOT EXISTS (SELECT 1 FROM $et WHERE character_id = v.character_id AND $variantFkCol = v." . $c['variant_fk'] . " AND angle_id = v.angle_id AND perspective_id = v.perspective_id)";

            $insertCols  = "name, \`order\`, description, character_id, $variantFkCol, angle_id, perspective_id, regenerate_images";
            $selectCols  = "CONCAT(v.character_name, ' - ', v." . ($area === 'expressions' ? 'expression_name' : 'pose_name') . ", ' - ', v.angle_name, ' - ', v.perspective_name), 0, $promptSql, v.character_id, v." . $c['variant_fk'] . ", v.angle_id, v.perspective_id, 1";

            if ($imgFrameId > 0) {
                $insertCols .= ", img2img_frame_id, img2img";
                $selectCols .= ", $imgFrameId, 1";
            }

            $variantIdCol = ($area === 'expressions') ? 'expression_id' : 'pose_id';

            $sql  = "INSERT INTO $et ($insertCols) SELECT $selectCols FROM $view v WHERE v.character_id = ? AND v.$variantIdCol = ? $notExists";
            $pdo->beginTransaction();
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$char_id, $variant_id]);
            $inserted = $stmt->rowCount();
            $pdo->commit();

            echo json_encode(['status' => 'success', 'inserted' => $inserted]);
            exit;
        }

        // ── 8. GET STORYBOARDS (for STBA picker) ───────────────────────────
        if ($action === 'get_storyboards') {
            $sql = "
                SELECT s.id, s.name, s.custom_tag, s.category_id,
                       COUNT(sf.id) as frame_count,
                       cat.name as category_name, cat.code as category_code,
                       sc.name as scene_name,
                       ep.number as episode_number,
                       ep.id as episode_id,
                       sq.id as sequence_id
                FROM storyboards s
                LEFT JOIN storyboard_frames sf ON s.id = sf.storyboard_id
                LEFT JOIN storyboard_categories cat ON s.category_id = cat.id
                LEFT JOIN editorial_scenes sc ON s.editorial_scene_id = sc.id
                LEFT JOIN editorial_sequences sq ON sc.sequence_id = sq.id
                LEFT JOIN editorial_episodes ep ON sq.episode_id = ep.id
                WHERE s.is_archived = 0
                GROUP BY s.id
                ORDER BY s.updated_at DESC
            ";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            $cats = $pdo->query("SELECT id, name, code FROM storyboard_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
            $eps  = $pdo->query("SELECT id, name, number FROM editorial_episodes ORDER BY number ASC")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'success', 'boards' => $rows, 'cats' => $cats, 'eps' => $eps]);
            exit;
        }

        // ── 9. IMPORT FRAMES TO STORYBOARD (STBA) ──────────────────────────
        if ($action === 'import_frames_to_storyboard') {
            $input    = json_decode(file_get_contents('php://input'), true);
            $frameIds = array_map('intval', $input['frame_ids'] ?? []);
            $sbId     = (int)($input['storyboard_id'] ?? 0);

            if (empty($frameIds) || !$sbId) throw new Exception('Invalid parameters');

            $sbStmt = $pdo->prepare("SELECT id, name FROM storyboards WHERE id = ?");
            $sbStmt->execute([$sbId]);
            $sb = $sbStmt->fetch(PDO::FETCH_ASSOC);
            if (!$sb) throw new Exception('Storyboard not found');

            $idsStr = implode(',', $frameIds);
            $fStmt = $pdo->query("SELECT id, filename, name, prompt FROM frames WHERE id IN ($idsStr) ORDER BY FIELD(id, $idsStr)");
            $frames = $fStmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($frames)) throw new Exception('No frames found');

            $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM storyboard_frames WHERE storyboard_id = $sbId")->fetchColumn();

            $insertStmt = $pdo->prepare("
                INSERT INTO storyboard_frames (storyboard_id, frame_id, name, description, filename, sort_order, is_copied, original_filename)
                VALUES (?, ?, ?, ?, ?, ?, 0, ?)
            ");

            $count = 0;
            $pdo->beginTransaction();
            foreach ($frames as $i => $f) {
                $insertStmt->execute([
                    $sbId,
                    $f['id'],
                    $f['name'] ?: ('Frame #' . $f['id']),
                    $f['prompt'] ?: '',
                    '',
                    $maxOrder + $i + 1,
                    ltrim($f['filename'], '/')
                ]);
                $count++;
            }
            $pdo->prepare("UPDATE storyboards SET updated_at = NOW() WHERE id = ?")->execute([$sbId]);
            $pdo->commit();

            echo json_encode(['status' => 'success', 'count' => $count, 'storyboard_name' => $sb['name']]);
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    exit;
}

// ─── Page render ────────────────────────────────────────
$pageTitle = 'Exposanimaticism — Character Variant Browser';
ob_start();
?>
<!-- Dependencies -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<!-- PhotoSwipe -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe-lightbox.umd.min.js"></script>

<?php require_once __DIR__ . '/modal_frame_details.php'; ?>

<style>
/* ──────────────────────────────────────────────────
   FORGE THEME — identical vars to enhanimaticism
────────────────────────────────────────────────── */
:root {
    --bg:         #0a0a0f;
    --card:       #111118;
    --border:     #1e1e2e;
    --text:       #e2e2f0;
    --text-muted: #555570;
    --purple:     #8b5cf6;
    --purple-dim: rgba(139,92,246,0.1);
    --amber:      #f59e0b;
    --amber-dim:  rgba(245,158,11,0.1);
    --red:        #ef4444;
    --teal:       #14b8a6;
    --teal-dim:   rgba(20,184,166,0.12);
    --gold:       #fbbf24;
}
[data-theme="light"] {
    --bg:         #f4f4f8;
    --card:       #ffffff;
    --border:     #d0d0e0;
    --text:       #1a1a2e;
    --text-muted: #888899;
}
*, *::before, *::after { box-sizing: border-box; }
html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono','Fira Mono',monospace; height: 100%; overflow: hidden; }

/* ── LAYOUT ── */
.eh-layout { display: flex; flex-direction: column; height: 100vh; height: 100dvh; overflow: hidden; }

/* ── HEADER ── */
.eh-header {
    flex-shrink: 0; padding: 0 16px; height: 50px;
    background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
}
.eh-title { font-size: 1rem; font-weight: 700; letter-spacing: 1px; color: var(--text); display: flex; align-items: center; gap: 8px; }
.eh-title span { color: var(--purple); }

/* ── FILTER PANEL (replaces map-run list) ── */
.eh-filter-panel {
    flex-shrink: 0; background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; flex-direction: column; gap: 0;
}

.fp-row {
    display: flex; gap: 8px; padding: 8px 12px; border-bottom: 1px solid rgba(255,255,255,0.04);
    align-items: center; flex-wrap: wrap;
}
.fp-row:last-child { border-bottom: none; }

/* Area toggle pills */
.area-toggle {
    flex-shrink: 0; padding: 4px 10px; border-radius: 20px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); font-family: inherit;
    font-size: 0.65rem; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
    transition: all 0.15s; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;
}
.area-toggle.active-poses       { border-color: var(--purple); color: #000; background: var(--purple); box-shadow: 0 0 10px rgba(139,92,246,0.4); }
.area-toggle.active-expressions { border-color: var(--teal);   color: #000; background: var(--teal);   box-shadow: 0 0 10px rgba(20,184,166,0.4); }
.area-toggle.active-anima_poses { border-color: var(--gold);   color: #000; background: var(--gold);   box-shadow: 0 0 10px rgba(251,191,36,0.4); }

/* AJAX select+search combo */
.ajax-select-wrap { flex: 1; min-width: 0; position: relative; }
.ajax-select-wrap .search-input {
    width: 100%; padding: 6px 28px 6px 10px; border-radius: 4px; border: 1px solid var(--border);
    background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.8rem; outline: none;
}
.ajax-select-wrap .search-input:focus { border-color: var(--purple); }
.ajax-select-wrap .search-clear {
    position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
    background: transparent; border: none; color: var(--text-muted); cursor: pointer;
    font-size: 12px; line-height: 1; padding: 2px; display: none;
}
.ajax-select-wrap .search-clear.visible { display: block; }
.ajax-dropdown {
    position: absolute; top: calc(100% + 3px); left: 0; right: 0; z-index: 9999;
    background: var(--card); border: 1px solid var(--border); border-radius: 4px;
    max-height: 200px; overflow-y: auto; display: none;
    box-shadow: 0 4px 16px rgba(0,0,0,0.5);
}
.ajax-dropdown.open { display: block; }
.ajax-option {
    padding: 8px 12px; font-size: 0.75rem; cursor: pointer; color: var(--text);
    border-bottom: 1px solid rgba(255,255,255,0.04); transition: background 0.1s;
    display: flex; justify-content: space-between; align-items: center;
}
.ajax-option:hover { background: var(--purple-dim); }
.ajax-option .opt-id { font-size: 0.65rem; color: var(--text-muted); }
.ajax-selected-tag {
    display: inline-flex; align-items: center; gap: 6px; padding: 3px 8px;
    border-radius: 20px; font-size: 0.7rem; font-weight: 700;
    background: var(--purple-dim); border: 1px solid rgba(139,92,246,0.4); color: var(--purple);
    white-space: nowrap; flex-shrink: 0;
}
.ajax-selected-tag .tag-clear { cursor: pointer; font-size: 10px; opacity: 0.6; }
.ajax-selected-tag .tag-clear:hover { opacity: 1; }

/* Angle/perspective chips row — reuse importer style */
.chips-filter-row {
    display: flex; gap: 6px; flex-wrap: wrap; padding: 6px 12px;
    border-bottom: 1px solid rgba(255,255,255,0.04); align-items: center;
}
.chips-filter-label { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); white-space: nowrap; margin-right: 4px; }
.filter-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 20px; border: 1px solid var(--border); background: rgba(255,255,255,0.02); color: var(--text-muted); font-family: inherit; font-size: 0.65rem; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.filter-chip:hover { border-color: var(--purple); color: var(--purple); }
.filter-chip.active-a { background: rgba(6,182,212,0.15); border-color: rgba(6,182,212,0.5); color: #06b6d4; }
.filter-chip.active-p { background: var(--purple-dim); border-color: rgba(139,92,246,0.5); color: var(--purple); }

/* ── TOOLBAR ── */
.eh-mid-panel { flex-shrink: 0; background: var(--card); border-bottom: 1px solid var(--border); z-index: 5; }

.config-bar { padding: 6px 12px 4px; display: flex; flex-direction: column; gap: 5px; border-bottom: 1px solid rgba(255,255,255,0.05); }
.prompt-row  { display: flex; gap: 6px; align-items: center; }
.prompt-input {
    flex: 1; padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border);
    background: rgba(0,0,0,0.3); color: var(--text); font-family: inherit; font-size: 0.85rem; min-width: 0;
}
.prompt-input:focus { outline: none; border-color: var(--amber); }

.btn-compose {
    flex-shrink: 0; padding: 6px 10px; border-radius: 4px;
    border: 1px solid var(--teal); background: var(--teal-dim); color: var(--teal);
    font-family: inherit; font-size: 0.7rem; font-weight: 700; cursor: pointer;
    text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 4px;
    white-space: nowrap; transition: background 0.15s, color 0.15s;
}
.btn-compose:hover { background: var(--teal); color: #000; }

.chips-row { display: flex; gap: 8px; align-items: center; border-top: 1px solid rgba(255,255,255,0.04); padding: 5px 0; }
.phrase-chips { flex: 1; display: flex; gap: 5px; overflow-x: auto; scrollbar-width: none; min-width: 0; align-items: center; }
.phrase-chips::-webkit-scrollbar { display: none; }
.phrase-chip { flex-shrink: 0; padding: 3px 9px; border-radius: 20px; border: 1px solid var(--border); background: rgba(255,255,255,0.04); color: var(--text-muted); font-family: inherit; font-size: 0.65rem; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.phrase-chip:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }
.phrase-chip.pinned { border-color: rgba(245,158,11,0.3); color: var(--amber); }
.phrase-chip.pinned:hover { border-color: var(--amber); background: var(--amber-dim); }
.chips-empty { font-size: 0.6rem; color: var(--text-muted); opacity: 0.5; padding: 0 8px; white-space: nowrap; font-style: italic; }
.btn-cb-manage {
    flex-shrink: 0; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); font-family: inherit;
    font-size: 0.65rem; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.15s;
}
.btn-cb-manage:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }

.grid-toolbar { background: rgba(0,0,0,0.2); display: flex; flex-direction: column; }
.gt-row1 { padding: 6px 12px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.04); }
.gt-row2 { padding: 5px 12px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.gt-info  { font-size: 0.7rem; color: var(--text-muted); }
.action-btn { padding: 4px 10px; border-radius: 3px; font-size: 0.65rem; font-weight: 700; border: 1px solid var(--border); background: transparent; color: var(--text-muted); cursor: pointer; text-transform: uppercase; font-family: inherit; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
.action-btn:hover { color: var(--purple); border-color: var(--purple); }
.action-btn.primary { border-color: var(--text); color: var(--text); }

.chk-label { display: flex; align-items: center; gap: 6px; font-size: 0.7rem; color: var(--text); cursor: pointer; }
#hideImported:checked { accent-color: var(--purple); }
#hideEnhanced:checked { accent-color: var(--amber); }
#showRaw:checked      { accent-color: #4ade80; }

/* Col select */
.col-select-wrap { display: flex; align-items: center; gap: 5px; font-size: 0.7rem; color: var(--text-muted); }
.col-select {
    padding: 2px 4px; background: var(--bg); border: 1px solid var(--border); color: var(--text);
    font-family: inherit; font-size: 0.7rem; border-radius: 3px; cursor: pointer; outline: none;
}
.col-select:focus { border-color: var(--teal); }

/* ── GRID ── */
.eh-grid-area { flex: 1; overflow-y: auto; padding: 10px; position: relative; background: var(--bg); min-height: 0; }
.frames-grid { display: grid; grid-template-columns: repeat(var(--grid-cols,2), 1fr); gap: 8px; padding-bottom: 20px; }
@media (min-width: 600px) {
    .frames-grid { grid-template-columns: repeat(var(--grid-cols,2), 1fr); }
}

/* ── FRAME CARDS ── */
.f-card { aspect-ratio: 1; background: #111; border: 2px solid var(--border); border-radius: 4px; position: relative; overflow: hidden; }
.f-card.selected { border-color: var(--text); box-shadow: 0 0 0 1px var(--text); }
.f-card.is-imported { border-color: #333; opacity: 0.25; filter: grayscale(80%); }
.f-card.is-imported::before { content: "IMPORTED"; position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 0.7rem; font-weight: 900; color: rgba(139,92,246,0.6); transform: rotate(-15deg); pointer-events: none; z-index: 5; }
.f-card.is-imported.hidden-in-grid { display: none; }
.f-card.is-enhanced { border-color: rgba(245,158,11,0.4); opacity: 0.4; filter: sepia(80%) hue-rotate(-10deg) saturate(1.5) brightness(0.6); }
.f-card.is-enhanced::after { content: "ENHANCED"; position: absolute; bottom: 35px; left: 0; right: 0; text-align: center; font-size: 0.65rem; font-weight: 800; color: rgba(245,158,11,0.8); pointer-events: none; z-index: 5; text-shadow: 0 1px 2px #000; }
.f-card.is-enhanced.hidden-in-grid { display: none; }

.frames-grid.show-raw .f-card.is-imported,
.frames-grid.show-raw .f-card.is-enhanced { opacity: 1; filter: none; border-color: var(--border); }
.frames-grid.show-raw .f-card.is-imported::before,
.frames-grid.show-raw .f-card.is-enhanced::after { display: none; }

.f-link { display: block; width: 100%; height: calc(100% - 24px); overflow: hidden; cursor: zoom-in; }
.f-link img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.2s; }
.f-link:hover img { transform: scale(1.03); }

.f-view-btn { position: absolute; top: 5px; right: 5px; width: 24px; height: 24px; background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; opacity: 0; transition: all 0.2s; font-size: 14px; }
.f-card:hover .f-view-btn { opacity: 1; }
.f-view-btn:hover { background: var(--text); border-color: var(--text); color: #000; }

.f-label { position: absolute; bottom: 0; left: 0; right: 0; height: 24px; background: rgba(20,20,25,0.95); padding: 0 6px; font-size: 0.65rem; color: #aaa; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; z-index: 2; }
.f-card.selected .f-label { background: rgba(255,255,255,0.1); color: #fff; border-top-color: #fff; }
.f-select-trigger { width: 18px; height: 18px; border: 1px solid #555; border-radius: 3px; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); font-size: 0; }
.f-card.selected .f-select-trigger { background: #fff; border-color: #fff; color: #000; font-size: 10px; font-weight: 900; }
.f-card.selected .f-select-trigger::after { content: '✓'; }

/* ── BLANK CARD (not yet rendered) ── */
.f-card.blank-card {
    aspect-ratio: 1; background: rgba(255,255,255,0.02); border: 2px dashed var(--border);
    border-radius: 4px; position: relative; overflow: hidden; display: flex; flex-direction: column;
    align-items: center; justify-content: flex-start; padding: 8px; gap: 4px;
}
.blank-card-title { font-size: 0.6rem; font-weight: 700; color: var(--text-muted); text-align: center; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
.blank-card-input {
    width: 100%; padding: 4px 6px; border-radius: 3px; border: 1px solid var(--border);
    background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.65rem; outline: none;
}
.blank-card-input:focus { border-color: var(--purple); }
.blank-card-chk { display: flex; align-items: center; gap: 4px; font-size: 0.6rem; color: var(--text-muted); cursor: pointer; width: 100%; }
.blank-card-chk input { cursor: pointer; accent-color: var(--teal); }
.blank-import-btn {
    width: 100%; margin-top: auto; padding: 5px 4px; border-radius: 3px; border: 1px solid var(--purple);
    background: var(--purple-dim); color: var(--purple); font-family: inherit; font-size: 0.6rem;
    font-weight: 700; text-transform: uppercase; cursor: pointer; transition: all 0.15s;
}
.blank-import-btn:hover { background: var(--purple); color: #000; }
.blank-import-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* ── FOOTER ── */
.eh-footer {
    flex-shrink: 0; padding: 10px 16px;
    padding-bottom: max(10px, env(safe-area-inset-bottom));
    background: var(--card); border-top: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    z-index: 10; position: relative;
}
.ft-summary { font-size: 0.75rem; color: var(--text-muted); }
.ft-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
.btn-action { padding: 10px 12px; border-radius: 4px; border: none; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; cursor: pointer; font-family: inherit; transition: filter 0.15s; color: #fff; min-width: 0; white-space: nowrap; }
@media (min-width: 600px) {
    .ft-actions { gap: 10px; flex-wrap: nowrap; }
    .btn-action { padding: 12px 20px; font-size: 0.85rem; min-width: 100px; }
}
.btn-action:disabled { opacity: 0.5; cursor: not-allowed; background: var(--border) !important; color: #888 !important; }
.btn-enhance { background: var(--amber); color: #000; }
.btn-import  { background: var(--purple); }
.btn-stba    { background: var(--teal); color: #000; }

/* ── VIEW MODAL ── */
.view-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 100000; display: none; align-items: center; justify-content: center; }
.view-modal.active { display: flex; }
.view-modal-content { width: 95vw; height: 95vh; background: #000; position: relative; border: 1px solid var(--border); box-shadow: 0 0 30px rgba(0,0,0,0.5); }
.view-close { position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; background: rgba(0,0,0,0.8); color: #fff; border: 1px solid #444; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; z-index: 200; transition: all 0.2s; }
.view-close:hover { background: #fff; color: #000; }
iframe.frame-viewer { width: 100%; height: 100%; border: none; }

.state-msg { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); font-size: 0.8rem; gap: 8px; }
.spinner { width: 20px; height: 20px; border: 2px solid var(--border); border-top-color: var(--text); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.pswp { z-index: 99999; }

/* ── COMPOSE MODAL ── */
.compose-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 200000; display: none; align-items: flex-end; justify-content: center; }
.compose-modal-backdrop.active { display: flex; }
.compose-modal { width: 100%; max-width: 520px; background: var(--card); border: 1px solid var(--border); border-bottom: none; border-radius: 14px 14px 0 0; padding: 0 0 max(16px,env(safe-area-inset-bottom)); font-family: 'DM Mono','Fira Mono',monospace; max-height: 88vh; display: flex; flex-direction: column; box-shadow: 0 -8px 40px rgba(0,0,0,0.6); animation: slideUp 0.22s ease; }
@keyframes slideUp { from { transform: translateY(60px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.cm-handle { text-align: center; padding: 10px 0 4px; cursor: pointer; }
.cm-handle-bar { display: inline-block; width: 40px; height: 4px; background: var(--border); border-radius: 2px; }
.cm-header { padding: 6px 16px 10px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.cm-title { font-size: 0.8rem; font-weight: 700; color: var(--teal); text-transform: uppercase; letter-spacing: 1px; }
.cm-close-btn { background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 4px; width: 26px; height: 26px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.cm-close-btn:hover { color: var(--text); border-color: var(--text); }
.cm-preview { margin: 10px 16px 6px; padding: 8px 10px; border-radius: 5px; background: rgba(128,128,128,0.1); border: 1px solid var(--amber); font-size: 0.78rem; color: var(--amber); min-height: 36px; word-break: break-word; line-height: 1.5; flex-shrink: 0; cursor: text; transition: border-color 0.15s; }
.cm-preview:empty::before { content: "Compose your instruction below…"; color: var(--text-muted); font-style: italic; }
.cm-body { overflow-y: auto; padding: 0 16px; flex: 1; }
.cm-section { margin-top: 14px; }
.cm-section-title { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-muted); margin-bottom: 7px; display: flex; align-items: center; gap: 6px; }
.cm-section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }
.cm-tags { display: flex; flex-wrap: wrap; gap: 6px; }
.cm-tag { padding: 5px 11px; border-radius: 20px; font-size: 0.68rem; font-family: inherit; border: 1px solid var(--border); background: transparent; color: var(--text-muted); cursor: pointer; transition: all 0.12s; white-space: nowrap; }
.cm-tag:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-dim); }
.cm-tag.selected { border-color: var(--teal); color: #000; background: var(--teal); }
.cm-color-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
.cm-color-label { font-size: 0.65rem; color: var(--text-muted); min-width: 28px; }
.cm-color-swatch { width: 26px; height: 26px; border-radius: 50%; border: 2px solid var(--border); cursor: pointer; transition: transform 0.12s, border-color 0.12s; flex-shrink: 0; }
.cm-color-swatch:hover { transform: scale(1.15); border-color: #fff; }
.cm-color-swatch.selected { border-color: #fff; outline: 2px solid rgba(255,255,255,0.3); outline-offset: 2px; }
.cm-freetext { width: 100%; margin-top: 10px; padding: 7px 10px; border-radius: 4px; border: 1px solid var(--border); background: rgba(0,0,0,0.3); color: var(--text); font-family: inherit; font-size: 0.78rem; resize: none; }
.cm-freetext:focus { outline: none; border-color: var(--amber); }
.cm-intensity { display: flex; gap: 6px; align-items: center; }
.cm-int-btn { flex: 1; padding: 5px 4px; border-radius: 4px; font-size: 0.65rem; font-family: inherit; border: 1px solid var(--border); background: transparent; color: var(--text-muted); cursor: pointer; text-align: center; transition: all 0.12s; }
.cm-int-btn:hover { border-color: var(--purple); color: var(--purple); }
.cm-int-btn.selected { border-color: var(--purple); color: #000; background: var(--purple); }
.cm-footer { padding: 12px 16px 0; flex-shrink: 0; display: flex; gap: 8px; }
.cm-use-btn { flex: 1; padding: 13px; border-radius: 5px; border: none; background: var(--teal); color: #000; font-family: inherit; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; cursor: pointer; transition: filter 0.15s; }
.cm-use-btn:hover { filter: brightness(1.12); }
.cm-clear-btn { padding: 13px 16px; border-radius: 5px; border: 1px solid var(--border); background: transparent; color: var(--text-muted); font-family: inherit; font-size: 0.75rem; cursor: pointer; transition: all 0.15s; }
.cm-clear-btn:hover { border-color: var(--red); color: var(--red); }

/* Add frames modal */
.btn-remove-frame { width: 26px; height: 26px; border-radius: 4px; border: 1px solid var(--border); background: transparent; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: all 0.12s; flex-shrink: 0; }
.btn-remove-frame:hover { color: var(--red); border-color: var(--red); background: rgba(239,68,68,0.12); }

/* ── ADD FRAMES MODAL: Browse tab extras ── */
.af-tabs { display: flex; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.af-tab {
    flex: 1; padding: 8px 10px; font-size: 0.65rem; font-weight: 700; text-align: center;
    text-transform: uppercase; letter-spacing: 0.5px; cursor: pointer;
    color: var(--text-muted); border-bottom: 2px solid transparent; transition: 0.15s;
}
.af-tab.active { color: var(--teal); border-bottom-color: var(--teal); }
.af-tab-content { display: none; flex: 1; flex-direction: column; overflow: hidden; }
.af-tab-content.active { display: flex; }

/* Browse sub-panel */
.af-browse-filters {
    flex-shrink: 0; padding: 8px 12px; display: flex; flex-direction: column; gap: 6px;
    border-bottom: 1px solid var(--border);
}
.af-browse-row { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.af-area-btn {
    padding: 3px 8px; border-radius: 20px; border: 1px solid var(--border);
    background: transparent; color: var(--text-muted); font-family: inherit;
    font-size: 0.6rem; cursor: pointer; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.15s;
}
.af-area-btn.active { border-color: var(--teal); color: #000; background: var(--teal); }

/* Inline AJAX selects for browse */
.af-select-wrap { flex: 1; min-width: 0; position: relative; }
.af-search-input {
    width: 100%; padding: 5px 24px 5px 8px; border-radius: 4px; border: 1px solid var(--border);
    background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.72rem; outline: none;
}
.af-search-input:focus { border-color: var(--teal); }
.af-search-clear {
    position: absolute; right: 5px; top: 50%; transform: translateY(-50%);
    background: transparent; border: none; color: var(--text-muted); cursor: pointer;
    font-size: 11px; padding: 2px; display: none;
}
.af-search-clear.visible { display: block; }
.af-dropdown {
    position: absolute; top: calc(100% + 2px); left: 0; right: 0; z-index: 99999;
    background: var(--card); border: 1px solid var(--border); border-radius: 4px;
    max-height: 160px; overflow-y: auto; display: none;
    box-shadow: 0 4px 16px rgba(0,0,0,0.6);
}
.af-dropdown.open { display: block; }
.af-option {
    padding: 7px 10px; font-size: 0.7rem; cursor: pointer; color: var(--text);
    border-bottom: 1px solid rgba(255,255,255,0.04); display: flex; justify-content: space-between;
}
.af-option:hover { background: var(--teal-dim); }
.af-option .opt-id { font-size: 0.6rem; color: var(--text-muted); }

/* Browse grid inside modal */
.af-browse-grid-wrap { flex: 1; overflow-y: auto; padding: 8px; }
.af-browse-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; }
.af-frame-card {
    aspect-ratio: 1; border-radius: 3px; overflow: hidden; cursor: pointer;
    border: 2px solid transparent; position: relative; background: #111;
    transition: border-color 0.12s;
}
.af-frame-card:hover { border-color: var(--teal); }
.af-frame-card.selected { border-color: var(--text); }
.af-frame-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
.af-frame-card .af-fid {
    position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.7);
    font-size: 0.55rem; color: #aaa; padding: 2px 4px; text-align: center;
}
.af-frame-card.selected .af-fid { background: rgba(255,255,255,0.15); color: #fff; }
.af-browse-state { padding: 20px; text-align: center; font-size: 0.72rem; color: var(--text-muted); font-style: italic; }

/* ── STORYBOARD PICKER ── */
.sb-picker-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.7);
    z-index: 300000; display: none; align-items: flex-end; justify-content: center;
}
.sb-picker-backdrop.active { display: flex; }
.sb-picker-sheet {
    width: 100%; max-width: 520px;
    background: var(--card); border: 1px solid var(--border);
    border-bottom: none; border-radius: 14px 14px 0 0;
    padding: 0 0 max(16px, env(safe-area-inset-bottom));
    font-family: 'DM Mono', 'Fira Mono', monospace;
    max-height: 80vh; display: flex; flex-direction: column;
    box-shadow: 0 -8px 40px rgba(0,0,0,0.6);
    animation: slideUp 0.22s ease;
}
.sb-picker-handle { text-align: center; padding: 10px 0 4px; cursor: pointer; }
.sb-picker-handle-bar { display: inline-block; width: 40px; height: 4px; background: var(--border); border-radius: 2px; }
.sb-picker-header {
    padding: 6px 16px 10px; display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.sb-picker-title { font-size: 0.8rem; font-weight: 700; color: var(--purple); text-transform: uppercase; letter-spacing: 1px; }
.sb-picker-close { background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 4px; width: 26px; height: 26px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.sb-picker-close:hover { color: var(--text); border-color: var(--text); }
.sb-picker-filters {
    padding: 10px 16px; border-bottom: 1px solid var(--border); flex-shrink: 0; display: flex; flex-direction: column; gap: 8px;
}
.sb-picker-select {
    width: 100%; padding: 6px 8px; background: rgba(0,0,0,0.4); border: 1px solid var(--border);
    border-radius: 4px; color: var(--text); font-family: inherit; font-size: 0.75rem; outline: none;
}
.sb-picker-select:focus { border-color: var(--purple); }
.sb-picker-select:disabled { opacity: 0.4; cursor: not-allowed; }
.sb-picker-editorial { display: none; flex-direction: column; gap: 6px; }
.sb-picker-list { overflow-y: auto; flex: 1; }
.sb-picker-item {
    display: flex; flex-direction: column; padding: 10px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.04); cursor: pointer; transition: background 0.12s;
}
.sb-picker-item:hover { background: rgba(139,92,246,0.1); }
.sb-picker-item-name { font-size: 0.78rem; font-weight: 600; color: var(--text); }
.sb-picker-item-meta { font-size: 0.65rem; color: var(--text-muted); margin-top: 2px; display: flex; justify-content: space-between; }
.sb-picker-empty { padding: 24px 16px; text-align: center; font-size: 0.75rem; color: var(--text-muted); font-style: italic; }
.sb-picker-loading { padding: 24px 16px; text-align: center; font-size: 0.75rem; color: var(--text-muted); }
.sb-picker-footer {
    padding: 10px 16px 0; flex-shrink: 0; border-top: 1px solid var(--border);
    font-size: 0.65rem; color: var(--text-muted); text-align: center;
}
.sb-picker-footer a { color: var(--purple); text-decoration: none; }
.sb-picker-footer a:hover { text-decoration: underline; }
</style>

<?php require_once "forge_tool.php"; ?>

<div class="eh-layout">

    <!-- ── HEADER ── -->
    <div class="eh-header">
        <div class="eh-title"><span>✦</span> Exposanimaticism <span style="font-size:0.7em; opacity:0.6; margin-left:8px;">Character Variant Browser</span></div>
    </div>

    <!-- ── FILTER PANEL ── -->
    <div class="eh-filter-panel">

        <!-- Row 1: Area toggles + Character select -->
        <div class="fp-row">
            <!-- Area tabs -->
            <button class="area-toggle active-poses" id="areaBtn-poses"       onclick="setArea('poses')">Poses</button>
            <button class="area-toggle"              id="areaBtn-expressions" onclick="setArea('expressions')">Expressions</button>
            <button class="area-toggle"              id="areaBtn-anima_poses" onclick="setArea('anima_poses')">Anima Poses</button>

            <!-- Character AJAX select -->
            <div class="ajax-select-wrap" id="charSelectWrap" style="max-width: 220px;">
                <div id="charSelectedTag" style="display:none;"></div>
                <input type="text" class="search-input" id="charSearchInput" placeholder="Character…" autocomplete="off"
                       oninput="onCharSearch()" onfocus="onCharFocus()" onblur="onCharBlur()">
                <button class="search-clear" id="charClear" onclick="clearChar()"><i class="bi bi-x"></i></button>
                <div class="ajax-dropdown" id="charDropdown"></div>
            </div>
        </div>

        <!-- Row 2: Variant AJAX select -->
        <div class="fp-row">
            <span style="font-size:0.65rem; color:var(--text-muted); white-space:nowrap; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;" id="variantLabel">Pose:</span>
            <div class="ajax-select-wrap" id="variantSelectWrap">
                <div id="variantSelectedTag" style="display:none;"></div>
                <input type="text" class="search-input" id="variantSearchInput" placeholder="All variants…" autocomplete="off"
                       oninput="onVariantSearch()" onfocus="onVariantFocus()" onblur="onVariantBlur()">
                <button class="search-clear" id="variantClear" onclick="clearVariant()"><i class="bi bi-x"></i></button>
                <div class="ajax-dropdown" id="variantDropdown"></div>
            </div>
        </div>

    </div>

    <!-- ── MID PANEL (enhancement prompt + clipboard + toolbar) ── -->
    <div class="eh-mid-panel">
        <div class="config-bar">
            <div class="prompt-row">
                <input type="text" class="prompt-input" id="enhancePrompt"
                       value="Remove all speech bubbles, text boxes, captions and text while preserving everything exactly as is"
                       placeholder="Enhancement Instruction...">
                <button class="btn-compose" onclick="openComposeModal()" title="Quick-compose instruction">
                    <i class="bi bi-magic"></i> Compose
                </button>
            </div>
            <div class="chips-row">
                <div class="phrase-chips" id="clipboardChips"><span class="chips-empty">loading…</span></div>
                <button class="btn-cb-manage" onclick="openClipboardManager()"><i class="bi bi-clipboard2-plus"></i> Manage</button>
                <button class="btn-cb-manage" id="btnAddFrames" onclick="openAddFramesModal()" title="Add Additional Reference Frames"><i class="bi bi-images"></i> +Frames</button>
            </div>
        </div>

        <div class="grid-toolbar">
            <div class="gt-row1">
                <div class="gt-info" id="gridInfo">Select a character above</div>
                <div id="gridActions" style="display:none; display:flex; gap:6px; align-items:center;">
                    <button class="action-btn" onclick="toggleAll(false)">None</button>
                    <button class="action-btn primary" onclick="toggleAll(true)">All</button>
                </div>
            </div>
            <div class="gt-row2">
                <label class="chk-label" title="Hide frames imported to Animatics">
                    <input type="checkbox" id="hideImported" onchange="applyGridFilters()"> Hide Imp
                </label>
                <label class="chk-label" title="Hide frames already enhanced">
                    <input type="checkbox" id="hideEnhanced" onchange="applyGridFilters()"> Hide Enh
                </label>
                <label class="chk-label" title="Show all frames without opacity/darkness indicators">
                    <input type="checkbox" id="showRaw" onchange="applyGridFilters()"> Show Raw
                </label>
                <label class="chk-label" title="Use depth2img for enhancement inference">
                    <input type="checkbox" id="useDepth2Img" style="accent-color: #3b82f6;"> d2i
                </label>
                <!-- Column count select -->
                <div class="col-select-wrap">
                    <span>Cols:</span>
                    <select class="col-select" id="colSelect" onchange="applyColCount()">
                        <option value="1">1</option>
                        <option value="2" selected>2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- ── GRID ── -->
    <div class="eh-grid-area">
        <div class="state-msg" id="gridState"><div>↑ Select a character to browse variants</div></div>
        <div class="frames-grid pswp-gallery" id="framesGrid" style="display:none;"></div>
    </div>

    <!-- ── FOOTER ── -->
    <div class="eh-footer">
        <div class="ft-summary" id="footerSummary">0 selected</div>
        <div class="ft-actions">
            <button class="btn-action btn-stba"    id="btnStba"    disabled onclick="openSbPickerForFrames()">STBA</button>
            <button class="btn-action btn-enhance" id="btnEnhance" disabled onclick="submitEnhancement()">Enhance Frames</button>
            <button class="btn-action btn-import"  id="btnImport"  disabled onclick="submitImport()">Import to Animatics</button>
        </div>
    </div>
</div>

<!-- Frame viewer Modal -->
<div class="view-modal" id="viewModal">
    <div class="view-modal-content">
        <div class="view-close" onclick="closeFrameModal()"><i class="bi bi-x-lg"></i></div>
        <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
    </div>
</div>

<!-- Compose Modal — identical structure to enhanimaticism -->
<div class="compose-modal-backdrop" id="composeBackdrop" onclick="onBackdropClick(event)">
    <div class="compose-modal" id="composeModal">
        <div class="cm-handle" onclick="closeComposeModal()"><div class="cm-handle-bar"></div></div>
        <div class="cm-header">
            <div class="cm-title"><i class="bi bi-magic"></i> Quick Compose</div>
            <button class="cm-close-btn" onclick="closeComposeModal()"><i class="bi bi-x"></i></button>
        </div>
        <div class="cm-preview" id="cmPreview"></div>
        <div class="cm-body">
            <div class="cm-section"><div class="cm-section-title">Operation</div><div class="cm-tags" id="cm-ops"></div></div>
            <div class="cm-section"><div class="cm-section-title">Subject</div><div class="cm-tags" id="cm-subjects"></div></div>
            <div class="cm-section" id="cm-color-section" style="display:none;">
                <div class="cm-section-title">Color Change</div>
                <div class="cm-color-row"><span class="cm-color-label">From</span><div id="cm-from-swatches"></div></div>
                <div class="cm-color-row" style="margin-top:6px;"><span class="cm-color-label">To</span><div id="cm-to-swatches"></div></div>
            </div>
            <div class="cm-section"><div class="cm-section-title">Style / Modifier</div><div class="cm-tags" id="cm-modifiers"></div></div>
            <div class="cm-section"><div class="cm-section-title">Intensity</div><div class="cm-intensity" id="cm-intensity"></div></div>
            <div class="cm-section">
                <div class="cm-section-title">Custom detail (optional)</div>
                <textarea class="cm-freetext" id="cm-freetext" rows="2" placeholder="e.g. only on the left side, keep shadows…" oninput="updatePreview()"></textarea>
            </div>
            <div style="height:8px;"></div>
        </div>
        <div class="cm-footer">
            <button class="cm-clear-btn" onclick="clearCompose()"><i class="bi bi-arrow-counterclockwise"></i></button>
            <button class="cm-use-btn" onclick="useComposed()">Use This ↑</button>
        </div>
    </div>
</div>

<!-- ADD FRAMES MODAL — upgraded with Browse tab -->
<div class="compose-modal-backdrop" id="addFramesBackdrop" onmousedown="onAddFramesBackdropClick(event)">
    <div class="compose-modal" id="addFramesModal">
        <div class="cm-handle" onclick="closeAddFramesModal(false)"><div class="cm-handle-bar"></div></div>
        <div class="cm-header">
            <div class="cm-title"><i class="bi bi-images"></i> Additional Reference Frames</div>
            <button class="cm-close-btn" onclick="closeAddFramesModal(false)"><i class="bi bi-x"></i></button>
        </div>

        <!-- Tab bar -->
        <div class="af-tabs">
            <div class="af-tab active" id="afTab-id"     onclick="switchAfTab('id')">By Frame ID</div>
            <div class="af-tab"        id="afTab-browse" onclick="switchAfTab('browse')">Browse Variants</div>
        </div>

        <!-- Tab: By Frame ID (original behaviour) -->
        <div class="af-tab-content active" id="afContent-id">
            <div class="cm-body" style="padding:16px;">
                <p style="font-size:0.75rem; color:var(--text-muted); margin-top:0;">Add frames by entering their ID directly.</p>
                <div style="display:flex; gap:8px; margin-bottom:16px; align-items:center;">
                    <input type="number" id="addFrameIdInput" class="prompt-input" placeholder="Frame ID..." style="max-width:120px; flex:none;"
                           onkeydown="if(event.key==='Enter'){event.preventDefault();addReferenceFrame();}">
                    <button type="button" class="btn-compose" onclick="addReferenceFrame()"><i class="bi bi-plus-lg"></i> Add Frame</button>
                </div>
                <div id="addFramesList" style="display:flex; flex-direction:column; gap:8px;"></div>
                <div style="height:8px;"></div>
            </div>
        </div>

        <!-- Tab: Browse Variants -->
        <div class="af-tab-content" id="afContent-browse">
            <!-- filters -->
            <div class="af-browse-filters">
                <div class="af-browse-row">
                    <button type="button" class="af-area-btn active" id="afAreaBtn-poses"       onclick="afSetArea('poses')">Poses</button>
                    <button type="button" class="af-area-btn"        id="afAreaBtn-expressions" onclick="afSetArea('expressions')">Expressions</button>
                    <button type="button" class="af-area-btn"        id="afAreaBtn-anima_poses" onclick="afSetArea('anima_poses')">Anima</button>
                </div>
                <div class="af-browse-row">
                    <div class="af-select-wrap">
                        <input type="text" class="af-search-input" id="afCharInput" placeholder="Character…" autocomplete="off"
                               oninput="afOnCharSearch()" onfocus="afOnCharFocus()" onblur="afOnCharBlur()"
                               onkeydown="return afHandleCharKeydown(event)">
                        <button type="button" class="af-search-clear" id="afCharClear" onclick="afClearChar()"><i class="bi bi-x"></i></button>
                        <div class="af-dropdown" id="afCharDropdown"></div>
                    </div>
                    <div class="af-select-wrap">
                        <input type="text" class="af-search-input" id="afVariantInput" placeholder="All variants…" autocomplete="off"
                               oninput="afOnVariantSearch()" onfocus="afOnVariantFocus()" onblur="afOnVariantBlur()"
                               onkeydown="return afHandleVariantKeydown(event)">
                        <button type="button" class="af-search-clear" id="afVariantClear" onclick="afClearVariant()"><i class="bi bi-x"></i></button>
                        <div class="af-dropdown" id="afVariantDropdown"></div>
                    </div>
                </div>
            </div>
            <!-- frame grid -->
            <div class="af-browse-grid-wrap">
                <div class="af-browse-state" id="afBrowseState">Select a character to browse frames</div>
                <div class="af-browse-grid" id="afBrowseGrid" style="display:none;"></div>
            </div>
        </div>

        <div class="cm-footer" style="padding-bottom:16px;">
            <button type="button" class="cm-clear-btn" onclick="closeAddFramesModal(false)">Cancel</button>
            <button type="button" class="cm-use-btn" onclick="closeAddFramesModal(true)">Confirm</button>
        </div>
    </div>
</div>

<!-- Storyboard Picker Bottom-Sheet -->
<div class="sb-picker-backdrop" id="sbPickerBackdrop" onmousedown="onSbPickerBackdropClick(event)">
    <div class="sb-picker-sheet" id="sbPickerSheet">
        <div class="sb-picker-handle" onclick="closeSbPicker()"><div class="sb-picker-handle-bar"></div></div>
        <div class="sb-picker-header">
            <div class="sb-picker-title"><i class="bi bi-film"></i> Assign to Storyboard</div>
            <button class="sb-picker-close" onclick="closeSbPicker()"><i class="bi bi-x"></i></button>
        </div>
        <div class="sb-picker-filters">
            <select class="sb-picker-select" id="sbPickerCatFilter" onchange="sbPickerRenderList()">
                <option value="all">All Categories</option>
            </select>
            <div class="sb-picker-editorial" id="sbPickerEditorial">
                <select class="sb-picker-select" id="sbPickerEpFilter" onchange="sbPickerOnEpChange()">
                    <option value="">All Episodes</option>
                </select>
                <select class="sb-picker-select" id="sbPickerSeqFilter" disabled onchange="sbPickerRenderList()">
                    <option value="">All Sequences</option>
                </select>
            </div>
        </div>
        <div class="sb-picker-list" id="sbPickerList">
            <div class="sb-picker-loading">Loading storyboards…</div>
        </div>
        <div class="sb-picker-footer">
            <a href="/view_storyboards.php" target="_blank">Manage Storyboards ↗</a>
        </div>
    </div>
</div>

<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true"></div>

<script>
// ───────────────────────────────────────────────────────
// STATE
// ───────────────────────────────────────────────────────
const DEFAULT_PROMPT = "Remove all speech bubbles, text boxes, captions and text while preserving everything exactly as is";
const CB_AREA = 'exposanimaticism';

let currentArea      = 'poses';          // poses | expressions | anima_poses
let currentCharId    = null;
let currentCharName  = '';
let currentVariantId = null;
let currentVariantName = '';

let currentFrames    = [];
let selectedFrameIds = new Set();
let confirmedReferenceFrames = [];
let tempReferenceFrames      = [];

let charDebounce    = null;
let variantDebounce = null;

const AREA_LABELS = {
    poses:       'Pose',
    expressions: 'Expression',
    anima_poses: 'Anima Pose',
};

// ───────────────────────────────────────────────────────
// AREA SWITCHING
// ───────────────────────────────────────────────────────
function setArea(area) {
    currentArea = area;
    // Update toggle button styles
    ['poses','expressions','anima_poses'].forEach(a => {
        const btn = document.getElementById('areaBtn-' + a);
        btn.className = 'area-toggle' + (a === area ? ' active-' + a : '');
    });
    // Update variant label
    document.getElementById('variantLabel').textContent = AREA_LABELS[area] + ':';
    // Reset variant selection
    clearVariant(false);
    // Reload grid
    loadGrid();
}

// ───────────────────────────────────────────────────────
// CHARACTER AJAX SELECT
// ───────────────────────────────────────────────────────
function onCharSearch() {
    clearTimeout(charDebounce);
    charDebounce = setTimeout(fetchChars, 250);
}
function onCharFocus() { fetchChars(); }
function onCharBlur()  { setTimeout(() => closeDropdown('charDropdown'), 180); }

function fetchChars() {
    const q = document.getElementById('charSearchInput').value.trim();
    document.getElementById('charClear').classList.toggle('visible', q.length > 0);
    fetch('?api_action=search_characters&q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') return;
            renderDropdown('charDropdown', res.data, item => selectChar(item.id, item.name), 'char');
        });
}

function selectChar(id, name) {
    currentCharId   = id;
    currentCharName = name;
    closeDropdown('charDropdown');
    document.getElementById('charSearchInput').value = name;
    document.getElementById('charClear').classList.add('visible');
    loadGrid();
}

function clearChar() {
    currentCharId   = null;
    currentCharName = '';
    document.getElementById('charSearchInput').value = '';
    document.getElementById('charClear').classList.remove('visible');
    clearVariant(false);
    loadGrid();
}

// ───────────────────────────────────────────────────────
// VARIANT AJAX SELECT
// ───────────────────────────────────────────────────────
function onVariantSearch() {
    clearTimeout(variantDebounce);
    variantDebounce = setTimeout(fetchVariants, 250);
}
function onVariantFocus() { fetchVariants(); }
function onVariantBlur()  { setTimeout(() => closeDropdown('variantDropdown'), 180); }

function fetchVariants() {
    const q = document.getElementById('variantSearchInput').value.trim();
    document.getElementById('variantClear').classList.toggle('visible', q.length > 0);
    fetch('?api_action=search_variants&area=' + encodeURIComponent(currentArea) + '&q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') return;
            renderDropdown('variantDropdown', res.data, item => selectVariant(item.id, item.name), 'variant');
        });
}

function selectVariant(id, name) {
    currentVariantId   = id;
    currentVariantName = name;
    closeDropdown('variantDropdown');
    document.getElementById('variantSearchInput').value = name;
    document.getElementById('variantClear').classList.add('visible');
    loadGrid();
}

function clearVariant(reload = true) {
    currentVariantId   = null;
    currentVariantName = '';
    document.getElementById('variantSearchInput').value = '';
    document.getElementById('variantClear').classList.remove('visible');
    if (reload) loadGrid();
}

// ───────────────────────────────────────────────────────
// DROPDOWN RENDER HELPER
// ───────────────────────────────────────────────────────
function renderDropdown(dropId, items, onSelect) {
    const dd = document.getElementById(dropId);
    dd.innerHTML = '';
    if (!items.length) {
        dd.innerHTML = '<div class="ajax-option" style="color:var(--text-muted); font-style:italic;">No results</div>';
    } else {
        items.forEach(item => {
            const el = document.createElement('div');
            el.className = 'ajax-option';
            el.innerHTML = `<span>${esc(item.name)}</span><span class="opt-id">#${item.id}</span>`;
            el.onmousedown = (e) => { e.preventDefault(); onSelect(item); };
            dd.appendChild(el);
        });
    }
    dd.classList.add('open');
}

function closeDropdown(id) {
    document.getElementById(id).classList.remove('open');
}

// ───────────────────────────────────────────────────────
// GRID LOADING
// ───────────────────────────────────────────────────────
function loadGrid() {
    const state = document.getElementById('gridState');
    const grid  = document.getElementById('framesGrid');

    if (!currentCharId) {
        state.style.display = 'flex';
        state.innerHTML = '<div>↑ Select a character to browse variants</div>';
        grid.style.display = 'none';
        document.getElementById('gridActions').style.display = 'none';
        document.getElementById('gridInfo').textContent = 'Select a character above';
        currentFrames = [];
        selectedFrameIds.clear();
        updateSummary();
        return;
    }

    state.style.display = 'flex';
    state.innerHTML = '<div class="spinner"></div><div>Loading…</div>';
    grid.style.display = 'none';
    selectedFrameIds.clear();
    updateSummary();

    const params = new URLSearchParams({
        api_action: 'get_variant_frames',
        area:       currentArea,
        char_id:    currentCharId,
    });
    if (currentVariantId) params.set('variant_id', currentVariantId);

    fetch('?' + params.toString())
        .then(r => r.json())
        .then(res => {
            state.style.display = 'none';
            grid.style.display  = 'grid';
            document.getElementById('gridActions').style.display = 'flex';

            currentFrames = res.frames || [];

            // Info line
            let infoStr = `Character <strong>#${currentCharId}</strong>`;
            if (currentVariantId) infoStr += ` · ${AREA_LABELS[currentArea]} <strong>#${currentVariantId}</strong>`;
            infoStr += ` · ${currentFrames.length} frames`;
            document.getElementById('gridInfo').innerHTML = infoStr;

            renderGrid(res.has_record);
            updateSummary();
        })
        .catch(() => {
            state.style.display = 'flex';
            state.innerHTML = '<div>Error loading frames</div>';
        });
}

// ───────────────────────────────────────────────────────
// GRID RENDER
// ───────────────────────────────────────────────────────
function renderGrid(hasRecord) {
    const grid      = document.getElementById('framesGrid');
    const hideImp   = document.getElementById('hideImported').checked;
    const hideEnh   = document.getElementById('hideEnhanced').checked;
    const showRaw   = document.getElementById('showRaw').checked;
    grid.innerHTML  = '';
    grid.classList.toggle('show-raw', showRaw);

    // If variant selected but no entity record and no frames → show blank card
    if (currentVariantId && !hasRecord && currentFrames.length === 0) {
        grid.appendChild(buildBlankCard());
    }

    currentFrames.forEach(f => {
        const isImp = parseInt(f.is_imported) === 1;
        const isEnh = parseInt(f.is_enhanced) === 1;

        const card = document.createElement('div');
        card.className = 'f-card';
        if (isImp) card.classList.add('is-imported');
        if (isEnh) card.classList.add('is-enhanced');
        if ((isImp && hideImp) || (isEnh && hideEnh)) card.classList.add('hidden-in-grid');
        card.dataset.fid      = f.frame_id;
        card.dataset.imported = isImp ? "1" : "0";
        card.dataset.enhanced = isEnh ? "1" : "0";

        const link = document.createElement('a');
        link.className = 'f-link';
        link.href = f.filename; link.target = '_blank';
        link.dataset.pswpWidth = 1024; link.dataset.pswpHeight = 1024;

        const img = document.createElement('img');
        img.src = f.filename; img.loading = 'lazy';
        img.onload = function() { link.dataset.pswpWidth = this.naturalWidth; link.dataset.pswpHeight = this.naturalHeight; };
        link.appendChild(img);

        const viewBtn = document.createElement('div');
        viewBtn.className = 'f-view-btn';
        viewBtn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
        viewBtn.onclick = (e) => { e.stopPropagation(); e.preventDefault(); openFrameModal(f.frame_id); };

        const label = document.createElement('div');
        label.className = 'f-label';
        label.onclick = (e) => { e.preventDefault(); toggleFrame(f.frame_id, card); };
        label.innerHTML = `<span>#${f.frame_id}</span><div class="f-select-trigger"></div>`;

        card.appendChild(link);
        card.appendChild(viewBtn);
        card.appendChild(label);
        grid.appendChild(card);
    });

    if (typeof PhotoSwipeLightbox !== 'undefined') {
        new PhotoSwipeLightbox({ gallery: '#framesGrid', children: 'a.f-link', pswpModule: PhotoSwipe }).init();
    }
}

// ── BLANK CARD (not yet rendered combo) ──────────────────
function buildBlankCard() {
    const card = document.createElement('div');
    card.className = 'f-card blank-card';
    card.id = 'blankCard';

    card.innerHTML = `
        <div class="blank-card-title">Not Yet Rendered</div>
        <label class="blank-card-chk">
            <span style="font-size:0.58rem; color:var(--text-muted);">Ref Frame ID:</span>
        </label>
        <input type="number" class="blank-card-input" id="bcFrameId" placeholder="optional">
        <label class="blank-card-chk">
            <input type="checkbox" id="bcExclChar"> Excl. char prompt
        </label>
        <label class="blank-card-chk">
            <input type="checkbox" id="bcNeutralBg"> Neutral BG
        </label>
        <label class="blank-card-chk">
            <input type="checkbox" id="bcForce"> Force update
        </label>
        <button class="blank-import-btn" id="bcImportBtn" onclick="quickImport()">
            <i class="bi bi-lightning-charge-fill"></i> Import Now
        </button>
    `;
    return card;
}

async function quickImport() {
    const btn = document.getElementById('bcImportBtn');
    if (!btn) return;
    btn.disabled = true; btn.textContent = 'Importing…';

    const payload = {
        area:              currentArea,
        char_id:           currentCharId,
        variant_id:        currentVariantId,
        img2img_frame_id:  parseInt(document.getElementById('bcFrameId')?.value || '0') || 0,
        exclude_char_desc: document.getElementById('bcExclChar')?.checked  || false,
        neutral_white_bg:  document.getElementById('bcNeutralBg')?.checked || false,
        force_update:      document.getElementById('bcForce')?.checked     || false,
    };

    try {
        const res = await fetch('?api_action=quick_import', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        }).then(r => r.json());

        if (res.status === 'success') {
            Toast.show('Imported ' + res.inserted + ' combination(s). Regeneration queued.');
            loadGrid();
        } else {
            Toast.show(res.message || 'Import failed', 'error');
            btn.disabled = false; btn.textContent = 'Import Now';
        }
    } catch (e) {
        Toast.show('Network error', 'error');
        btn.disabled = false; btn.textContent = 'Import Now';
    }
}

// ───────────────────────────────────────────────────────
// GRID FILTERS & SELECTION
// ───────────────────────────────────────────────────────
function applyGridFilters() {
    const hideImp = document.getElementById('hideImported').checked;
    const hideEnh = document.getElementById('hideEnhanced').checked;
    const showRaw = document.getElementById('showRaw').checked;
    document.getElementById('framesGrid').classList.toggle('show-raw', showRaw);

    document.querySelectorAll('.f-card').forEach(c => {
        if (c.classList.contains('blank-card')) return;
        const isImp = c.dataset.imported === "1";
        const isEnh = c.dataset.enhanced === "1";
        if ((isImp && hideImp) || (isEnh && hideEnh)) {
            if (c.classList.contains('selected')) {
                selectedFrameIds.delete(parseInt(c.dataset.fid));
                c.classList.remove('selected');
            }
            c.classList.add('hidden-in-grid');
        } else {
            c.classList.remove('hidden-in-grid');
        }
    });
    updateSummary();
}

function applyColCount() {
    const cols = parseInt(document.getElementById('colSelect').value) || 2;
    document.getElementById('framesGrid').style.setProperty('--grid-cols', cols);
    try { localStorage.setItem('exposanim_cols', cols); } catch(e) {}
}

function toggleFrame(fid, card) {
    if (card.classList.contains('hidden-in-grid')) return;
    if (selectedFrameIds.has(fid)) { selectedFrameIds.delete(fid); card.classList.remove('selected'); }
    else { selectedFrameIds.add(fid); card.classList.add('selected'); }
    updateSummary();
}

function toggleAll(select) {
    document.querySelectorAll('.f-card').forEach(c => {
        if (c.classList.contains('hidden-in-grid') || c.classList.contains('blank-card')) { c.classList.remove('selected'); return; }
        const fid = parseInt(c.dataset.fid);
        if (select) { selectedFrameIds.add(fid); c.classList.add('selected'); }
        else { selectedFrameIds.delete(fid); c.classList.remove('selected'); }
    });
    updateSummary();
}

function updateSummary() {
    const count = selectedFrameIds.size;
    document.getElementById('footerSummary').textContent = `${count} selected`;
    const disabled = count === 0;
    document.getElementById('btnEnhance').disabled = disabled;
    document.getElementById('btnImport').disabled  = disabled;
    const btnStba = document.getElementById('btnStba');
    if (btnStba) btnStba.disabled = disabled;
}

// ───────────────────────────────────────────────────────
// ACTIONS — Import & Enhance
// ───────────────────────────────────────────────────────
function submitImport() {
    performAction('submit_import', { frame_ids: Array.from(selectedFrameIds) }, 'Import to Animatics');
}

function submitEnhancement() {
    const prompt = document.getElementById('enhancePrompt').value.trim();
    if (!prompt) { Toast.show('Enter instruction for enhancement', 'error'); return; }
    const extraFrames = confirmedReferenceFrames.map(f => f.id);
    const useD2i = document.getElementById('useDepth2Img').checked ? 1 : 0;
    performAction('submit_enhancement', {
        frame_ids:    Array.from(selectedFrameIds),
        description:  prompt,
        extra_frames: extraFrames,
        depth2img:    useD2i,
    }, 'Enhance Frames');
}

function performAction(action, data, btnText) {
        const btnId = action === 'submit_import' ? 'btnImport' : 'btnEnhance';
        const btn   = document.getElementById(btnId);
        btn.disabled = true; btn.textContent = 'Processing…';

        fetch('?api_action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        }).then(r => r.json()).then(res => {
            if (res.status === 'success') {
                Toast.show(`Success: ${res.count} frames processed`);
                const processedIds = new Set(data.frame_ids.map(Number));
                if (action === 'submit_enhancement') {
                    addToHistory(data.description || '');
                    document.getElementById('enhancePrompt').value = DEFAULT_PROMPT;
                    confirmedReferenceFrames = [];
                    updateAddFramesButton();
                    currentFrames = currentFrames.map(f =>
                        processedIds.has(Number(f.frame_id)) ? { ...f, is_enhanced: 1 } : f
                    );
                } else if (action === 'submit_import') {
                    currentFrames = currentFrames.map(f =>
                        processedIds.has(Number(f.frame_id)) ? { ...f, is_imported: 1 } : f
                    );
                }
                selectedFrameIds.clear();
                renderGrid(true);
                updateSummary();
            } else {
                Toast.show(res.message, 'error');
            }
        }).catch(() => Toast.show('Network error', 'error'))
        .finally(() => {
            btn.disabled = false; btn.textContent = btnText;
            updateSummary();
        });
    }

// ───────────────────────────────────────────────────────
// STORYBOARD PICKER
// ───────────────────────────────────────────────────────
let sbPickerData   = { boards: [], cats: [], eps: [] };
let sbPickerLoaded = false;

function openSbPickerForFrames() {
    if (selectedFrameIds.size === 0) return;
    document.getElementById('sbPickerBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
    if (!sbPickerLoaded) sbPickerLoad(); else sbPickerRenderList();
}

function closeSbPicker() {
    document.getElementById('sbPickerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

function onSbPickerBackdropClick(e) {
    if (e.target === document.getElementById('sbPickerBackdrop')) closeSbPicker();
}

function sbPickerLoad() {
    const list = document.getElementById('sbPickerList');
    list.innerHTML = '<div class="sb-picker-loading">Loading storyboards…</div>';
    fetch('?api_action=get_storyboards')
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') { list.innerHTML = '<div class="sb-picker-empty">Failed to load</div>'; return; }
            sbPickerData = { boards: res.boards || [], cats: res.cats || [], eps: res.eps || [] };
            sbPickerLoaded = true;
            sbPickerBuildFilters();
            sbPickerRenderList();
        })
        .catch(() => { list.innerHTML = '<div class="sb-picker-empty">Network error</div>'; });
}

function sbPickerBuildFilters() {
    const catSel = document.getElementById('sbPickerCatFilter');
    catSel.innerHTML = '<option value="all">All Categories</option>';
    sbPickerData.cats.forEach(c => { const o = document.createElement('option'); o.value = c.id; o.textContent = c.name; o.dataset.code = c.code; catSel.appendChild(o); });
    const epSel = document.getElementById('sbPickerEpFilter');
    epSel.innerHTML = '<option value="">All Episodes</option>';
    sbPickerData.eps.forEach(ep => { const o = document.createElement('option'); o.value = ep.id; o.textContent = 'Ep ' + ep.number + ': ' + ep.name; epSel.appendChild(o); });
}

function sbPickerOnEpChange() { sbPickerRenderList(); }

function sbPickerRenderList() {
    const catSel = document.getElementById('sbPickerCatFilter');
    const catId  = catSel.value;
    const catCode = catSel.selectedOptions[0]?.dataset?.code || '';
    const isEd = catCode === 'editorial';
    document.getElementById('sbPickerEditorial').style.display = isEd ? 'flex' : 'none';
    const epId = document.getElementById('sbPickerEpFilter').value;
    let items = sbPickerData.boards;
    if (catId !== 'all') items = items.filter(b => String(b.category_id) === String(catId));
    if (isEd && epId) items = items.filter(b => String(b.episode_id) === String(epId));
    const list = document.getElementById('sbPickerList');
    list.innerHTML = '';
    if (!items.length) { list.innerHTML = '<div class="sb-picker-empty">No storyboards found</div>'; return; }
    items.forEach(sb => {
        let meta = '';
        if (sb.category_code === 'editorial' && sb.scene_name) meta = 'Ep ' + sb.episode_number + ' · ' + sb.scene_name;
        else meta = sb.custom_tag || sb.category_name || '';
        const el = document.createElement('div');
        el.className = 'sb-picker-item';
        el.innerHTML = `<div class="sb-picker-item-name">${esc(sb.name)}</div><div class="sb-picker-item-meta"><span>${esc(meta)}</span><span>${sb.frame_count} fr</span></div>`;
        el.onclick = () => sbPickerDoImport(sb.id, sb.name);
        list.appendChild(el);
    });
}

function sbPickerDoImport(storyboardId, storyboardName) {
    closeSbPicker();
    if (selectedFrameIds.size === 0) return;
    const btn = document.getElementById('btnStba');
    if (btn) { btn.disabled = true; btn.textContent = '…'; }

    Toast.show('Adding ' + selectedFrameIds.size + ' frames → ' + storyboardName + '…');
    fetch('?api_action=import_frames_to_storyboard', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            frame_ids: Array.from(selectedFrameIds),
            storyboard_id: storyboardId
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            Toast.show('Imported ' + res.count + ' frames → "' + res.storyboard_name + '"');
            selectedFrameIds.clear();
            document.querySelectorAll('.f-card.selected').forEach(c => c.classList.remove('selected'));
            updateSummary();
        } else {
            Toast.show(res.message || 'Assignment failed', 'error');
        }
    })
    .catch(() => Toast.show('Network error', 'error'))
    .finally(() => {
        if (btn) { btn.disabled = false; btn.textContent = 'STBA'; }
        updateSummary();
    });
}

// ───────────────────────────────────────────────────────
// CLIPBOARD CHIPS
// ───────────────────────────────────────────────────────
function loadClipboardChips() {
    fetch('clipboard_manager.php?api_action=cb_get&view_area=' + CB_AREA)
        .then(r => r.json())
        .then(res => { if (res.status === 'success') renderClipboardChips(res.data); })
        .catch(() => {});
}

function renderClipboardChips(items) {
    const container = document.getElementById('clipboardChips');
    container.innerHTML = '';
    if (!items || !items.length) { container.innerHTML = '<span class="chips-empty">Clipboard empty</span>'; return; }
    items.forEach(item => {
        const btn = document.createElement('button');
        btn.className = 'phrase-chip' + (parseInt(item.pinned) ? ' pinned' : '');
        let displayTxt = item.label ? item.label : item.content;
        btn.textContent = displayTxt.length > 28 ? displayTxt.slice(0, 27) + '…' : displayTxt;
        btn.title = item.content;
        btn.onclick = () => { document.getElementById('enhancePrompt').value = item.content; };
        container.appendChild(btn);
    });
}

function openClipboardManager() {
    const url    = `clipboard_manager.php?view_area=${CB_AREA}`;
    const modal  = document.getElementById('frameDetailsModal');
    const iframe = document.getElementById('frameDetailsIframe');
    const loader = document.getElementById('ieLoadingOverlay');
    const footer = document.getElementById('iePickerFooter');
    if (modal && iframe) {
        if (footer) footer.style.display = 'none';
        if (loader) { loader.style.display = 'flex'; const p = loader.querySelector('p'); if (p) p.textContent = 'Loading Clipboard…'; }
        iframe.style.opacity = '0';
        iframe.src = url;
        modal.style.display = 'flex';
    } else {
        window.open(url, '_blank', 'width=400,height=600');
    }
}

window.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'clipboard_updated' && e.data.view_area === CB_AREA) {
        renderClipboardChips(e.data.items);
    }
});

function addToHistory(prompt) {
    if (!prompt || prompt === DEFAULT_PROMPT) return;
    fetch(`clipboard_manager.php?api_action=cb_get&view_area=${CB_AREA}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                const exists = res.data.find(i => i.content === prompt);
                if (!exists) {
                    fetch(`clipboard_manager.php?api_action=cb_add&view_area=${CB_AREA}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ content: prompt, label: '' })
                    }).then(() => loadClipboardChips());
                }
            }
        });
}

// ───────────────────────────────────────────────────────
// ADD FRAMES MODAL — upgraded with Browse tab
// ───────────────────────────────────────────────────────
let afArea        = 'poses';
let afCharId      = null;
let afVariantId   = null;
let afCharDebounce    = null;
let afVariantDebounce = null;

// ── Tab switching ─────────────────────────────────────
function switchAfTab(tab) {
    ['id','browse'].forEach(t => {
        document.getElementById('afTab-' + t).classList.toggle('active', t === tab);
        document.getElementById('afContent-' + t).classList.toggle('active', t === tab);
    });
}

// ── Open / Close ──────────────────────────────────────
function openAddFramesModal() {
    tempReferenceFrames = [...confirmedReferenceFrames];
    document.getElementById('addFrameIdInput').value = '';
    renderAddFramesList();
    renderAfBrowseGrid([]);
    document.getElementById('addFramesBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
    switchAfTab('id');
}

function closeAddFramesModal(save) {
    if (save) { confirmedReferenceFrames = [...tempReferenceFrames]; updateAddFramesButton(); }
    document.getElementById('addFramesBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

function onAddFramesBackdropClick(e) {
    if (e.target === document.getElementById('addFramesBackdrop')) closeAddFramesModal(false);
}

// ── By-ID tab ─────────────────────────────────────────
function addReferenceFrame() {
    const input = document.getElementById('addFrameIdInput');
    const fid   = parseInt(input.value);
    if (!fid || isNaN(fid)) return;
    if (tempReferenceFrames.find(f => f.id === fid)) { input.value = ''; return; }
    fetch(`?api_action=get_single_frame&frame_id=${fid}`)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                tempReferenceFrames.push({ id: res.data.id, filename: res.data.filename });
                renderAddFramesList(); input.value = '';
            } else { Toast.show('Frame not found', 'error'); }
        });
}

function removeReferenceFrame(fid) {
    tempReferenceFrames = tempReferenceFrames.filter(f => f.id !== fid);
    renderAddFramesList();
    const card = document.querySelector(`.af-frame-card[data-fid="${fid}"]`);
    if (card) card.classList.remove('selected');
}

function renderAddFramesList() {
    const list = document.getElementById('addFramesList');
    list.innerHTML = '';
    if (!tempReferenceFrames.length) {
        list.innerHTML = '<div style="font-size:0.7rem; color:var(--text-muted); font-style:italic;">No additional frames assigned.</div>';
        return;
    }
    tempReferenceFrames.forEach(f => {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex; align-items:center; gap:10px; background:rgba(255,255,255,0.05); padding:6px; border-radius:4px; border:1px solid var(--border);';
        row.innerHTML = `<img src="${f.filename}" style="width:40px;height:40px;object-fit:cover;border-radius:3px;"><div style="flex:1;font-size:0.75rem;color:var(--text);">Frame #${f.id}</div><button type="button" class="btn-remove-frame" onclick="removeReferenceFrame(${f.id})" title="Remove"><i class="bi bi-trash3"></i></button>`;
        list.appendChild(row);
    });
}

function updateAddFramesButton() {
    const btn = document.getElementById('btnAddFrames');
    if (confirmedReferenceFrames.length > 0) {
        btn.innerHTML = `<i class="bi bi-images"></i> +Frames (${confirmedReferenceFrames.length})`;
        btn.style.borderColor = 'var(--amber)'; btn.style.color = 'var(--amber)'; btn.style.background = 'var(--amber-dim)';
    } else {
        btn.innerHTML = `<i class="bi bi-images"></i> +Frames`;
        btn.style.borderColor = ''; btn.style.color = ''; btn.style.background = '';
    }
}

// ── Browse tab: area ──────────────────────────────────
function afSetArea(area) {
    afArea = area;
    ['poses','expressions','anima_poses'].forEach(a => {
        document.getElementById('afAreaBtn-' + a).classList.toggle('active', a === area);
    });
    afClearVariant();
    afLoadGrid();
}

// ── Browse tab: Keydown Handlers ──────────────────────
function afHandleCharKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault(); e.stopPropagation();
        const dd = document.getElementById('afCharDropdown');
        if (dd.classList.contains('open')) {
            const firstOpt = dd.querySelector('.af-option:not(.no-res)');
            if (firstOpt && firstOpt.onmousedown) firstOpt.onmousedown(e);
        }
        return false;
    }
    return true;
}

function afHandleVariantKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault(); e.stopPropagation();
        const dd = document.getElementById('afVariantDropdown');
        if (dd.classList.contains('open')) {
            const firstOpt = dd.querySelector('.af-option:not(.no-res)');
            if (firstOpt && firstOpt.onmousedown) firstOpt.onmousedown(e);
        }
        return false;
    }
    return true;
}

// ── Browse tab: character AJAX ────────────────────────
function afOnCharSearch()  { clearTimeout(afCharDebounce); afCharDebounce = setTimeout(afFetchChars, 250); }
function afOnCharFocus()   { afFetchChars(); }
function afOnCharBlur()    { setTimeout(() => afCloseDropdown('afCharDropdown'), 180); }

function afFetchChars() {
    const q = document.getElementById('afCharInput').value.trim();
    document.getElementById('afCharClear').classList.toggle('visible', q.length > 0);
    const dd = document.getElementById('afCharDropdown');

    if (q.length > 0 && !dd.classList.contains('open')) {
        dd.innerHTML = '<div class="af-option no-res" style="color:var(--text-muted);font-style:italic;" onmousedown="event.preventDefault(); event.stopPropagation();">Searching…</div>';
        dd.classList.add('open');
    }

    fetch('?api_action=search_characters&q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') return;
            afRenderDropdown('afCharDropdown', res.data, item => {
                afCharId = item.id;
                document.getElementById('afCharInput').value = item.name;
                document.getElementById('afCharClear').classList.add('visible');
                afCloseDropdown('afCharDropdown');
                afLoadGrid();
            });
        });
}

function afClearChar() {
    afCharId = null;
    document.getElementById('afCharInput').value = '';
    document.getElementById('afCharClear').classList.remove('visible');
    afClearVariant();
    afLoadGrid();
}

// ── Browse tab: variant AJAX ──────────────────────────
function afOnVariantSearch()  { clearTimeout(afVariantDebounce); afVariantDebounce = setTimeout(afFetchVariants, 250); }
function afOnVariantFocus()   { afFetchVariants(); }
function afOnVariantBlur()    { setTimeout(() => afCloseDropdown('afVariantDropdown'), 180); }

function afFetchVariants() {
    const q = document.getElementById('afVariantInput').value.trim();
    document.getElementById('afVariantClear').classList.toggle('visible', q.length > 0);
    const dd = document.getElementById('afVariantDropdown');

    if (q.length > 0 && !dd.classList.contains('open')) {
        dd.innerHTML = '<div class="af-option no-res" style="color:var(--text-muted);font-style:italic;" onmousedown="event.preventDefault(); event.stopPropagation();">Searching…</div>';
        dd.classList.add('open');
    }

    fetch('?api_action=search_variants&area=' + encodeURIComponent(afArea) + '&q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') return;
            afRenderDropdown('afVariantDropdown', res.data, item => {
                afVariantId = item.id;
                document.getElementById('afVariantInput').value = item.name;
                document.getElementById('afVariantClear').classList.add('visible');
                afCloseDropdown('afVariantDropdown');
                afLoadGrid();
            });
        });
}

function afClearVariant() {
    afVariantId = null;
    document.getElementById('afVariantInput').value = '';
    document.getElementById('afVariantClear').classList.remove('visible');
    afLoadGrid();
}

// ── Browse tab: dropdown helper ───────────────────────
function afRenderDropdown(dropId, items, onSelect) {
    const dd = document.getElementById(dropId);
    dd.innerHTML = '';
    if (!items.length) {
        dd.innerHTML = '<div class="af-option no-res" style="color:var(--text-muted);font-style:italic;" onmousedown="event.preventDefault(); event.stopPropagation();">No results</div>';
    } else {
        items.slice(0, 100).forEach(item => {
            const el = document.createElement('div');
            el.className = 'af-option';
            el.innerHTML = `<span>${afEsc(item.name)}</span><span class="opt-id">#${item.id}</span>`;
            el.onmousedown = e => {
                e.preventDefault();
                e.stopPropagation();
                setTimeout(() => onSelect(item), 0);
            };
            dd.appendChild(el);
        });
    }
    dd.classList.add('open');
}

function afCloseDropdown(id) { document.getElementById(id).classList.remove('open'); }

// ── Browse tab: load frame grid ───────────────────────
function afLoadGrid() {
    if (!afCharId) {
        document.getElementById('afBrowseState').textContent = 'Select a character to browse frames';
        document.getElementById('afBrowseState').style.display = 'block';
        document.getElementById('afBrowseGrid').style.display = 'none';
        return;
    }

    document.getElementById('afBrowseState').textContent = 'Loading…';
    document.getElementById('afBrowseState').style.display = 'block';
    document.getElementById('afBrowseGrid').style.display = 'none';

    const params = new URLSearchParams({ api_action: 'get_variant_frames', area: afArea, char_id: afCharId });
    if (afVariantId) params.set('variant_id', afVariantId);

    fetch('?' + params.toString())
        .then(r => r.json())
        .then(res => {
            document.getElementById('afBrowseState').style.display = 'none';
            renderAfBrowseGrid(res.frames || []);
        })
        .catch(() => {
            document.getElementById('afBrowseState').textContent = 'Error loading frames';
            document.getElementById('afBrowseState').style.display = 'block';
        });
}

function renderAfBrowseGrid(frames) {
    const grid = document.getElementById('afBrowseGrid');
    grid.innerHTML = '';

    if (!frames.length) {
        document.getElementById('afBrowseState').textContent = 'No frames found for this selection';
        document.getElementById('afBrowseState').style.display = 'block';
        grid.style.display = 'none';
        return;
    }

    document.getElementById('afBrowseState').style.display = 'none';
    grid.style.display = 'grid';

    const selectedIds = new Set(tempReferenceFrames.map(f => f.id));

    frames.forEach(f => {
        const card = document.createElement('div');
        card.className = 'af-frame-card' + (selectedIds.has(f.frame_id) ? ' selected' : '');
        card.dataset.fid = f.frame_id;
        card.innerHTML = `<img src="${afEsc(f.filename)}" loading="lazy"><div class="af-fid">#${f.frame_id}</div>`;
        card.onclick = () => afToggleFrame(f.frame_id, f.filename, card);
        grid.appendChild(card);
    });
}

function afToggleFrame(fid, filename, card) {
    const idx = tempReferenceFrames.findIndex(f => f.id === fid);
    if (idx > -1) {
        tempReferenceFrames.splice(idx, 1);
        card.classList.remove('selected');
    } else {
        tempReferenceFrames.push({ id: fid, filename: filename });
        card.classList.add('selected');
    }
    renderAddFramesList();
}

function afEsc(s) { return s ? s.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }

// ═══════════════════════════════════════════════════════
// COMPOSE MODAL — identical to enhanimaticism
// ═══════════════════════════════════════════════════════
const CM_OPS = [
    { label: '🧹 Remove', value: 'Remove' }, { label: '🎨 Change color of', value: 'Change color of' },
    { label: '✨ Enhance', value: 'Enhance' }, { label: '💡 Adjust lighting on', value: 'Adjust lighting on' },
    { label: '🔍 Sharpen', value: 'Sharpen' }, { label: '🖌️ Stylize', value: 'Stylize' },
    { label: '🫥 Erase', value: 'Erase' }, { label: '🔄 Replace', value: 'Replace' },
];
const CM_SUBJECTS = [
    'all text & speech bubbles','people & characters','hair','eyes','skin','outfit / clothing',
    'background','shadows','highlights','outlines / edges','face','hands','sky','water','fire','armor','weapon','logo / watermark',
];
const CM_COLORS = [
    {name:'Red',hex:'#ef4444'},{name:'Orange',hex:'#f97316'},{name:'Yellow',hex:'#eab308'},{name:'Green',hex:'#22c55e'},
    {name:'Teal',hex:'#14b8a6'},{name:'Blue',hex:'#3b82f6'},{name:'Indigo',hex:'#6366f1'},{name:'Purple',hex:'#a855f7'},
    {name:'Pink',hex:'#ec4899'},{name:'White',hex:'#f8fafc'},{name:'Silver',hex:'#94a3b8'},{name:'Black',hex:'#1e293b'},
    {name:'Brown',hex:'#92400e'},{name:'Gold',hex:'#d97706'},
];
const CM_MODIFIERS  = ['naturally','seamlessly','dramatically','subtly','realistically','in anime style','in painterly style','with hard edges','with soft edges','while preserving everything else exactly as is'];
const CM_INTENSITIES = ['Lightly','Moderately','Strongly','Completely'];
let cmState = { op:'', subject:'', fromColor:'', toColor:'', modifier:'', intensity:'', freetext:'' };

function buildComposeModal() {
    const opsEl = document.getElementById('cm-ops');
    CM_OPS.forEach(o => {
        const btn = document.createElement('button'); btn.className = 'cm-tag'; btn.textContent = o.label;
        btn.onclick = () => { cmState.op = cmState.op === o.value ? '' : o.value; document.getElementById('cm-color-section').style.display = cmState.op === 'Change color of' ? '' : 'none'; syncTagGroup(opsEl, o.value, cmState.op); updatePreview(); };
        opsEl.appendChild(btn);
    });
    const subEl = document.getElementById('cm-subjects');
    CM_SUBJECTS.forEach(s => {
        const btn = document.createElement('button'); btn.className = 'cm-tag'; btn.textContent = s;
        btn.onclick = () => { cmState.subject = cmState.subject === s ? '' : s; syncTagGroup(subEl, s, cmState.subject); updatePreview(); };
        subEl.appendChild(btn);
    });
    const fromEl = document.getElementById('cm-from-swatches');
    fromEl.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;';
    CM_COLORS.forEach(c => { const sw = document.createElement('div'); sw.className = 'cm-color-swatch'; sw.style.background = c.hex; sw.title = c.name; sw.onclick = () => { cmState.fromColor = cmState.fromColor === c.name ? '' : c.name; syncSwatches(fromEl, c.name, cmState.fromColor); updatePreview(); }; fromEl.appendChild(sw); });
    const toEl = document.getElementById('cm-to-swatches');
    toEl.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;';
    CM_COLORS.forEach(c => { const sw = document.createElement('div'); sw.className = 'cm-color-swatch'; sw.style.background = c.hex; sw.title = c.name; sw.onclick = () => { cmState.toColor = cmState.toColor === c.name ? '' : c.name; syncSwatches(toEl, c.name, cmState.toColor); updatePreview(); }; toEl.appendChild(sw); });
    const modEl = document.getElementById('cm-modifiers');
    CM_MODIFIERS.forEach(m => { const btn = document.createElement('button'); btn.className = 'cm-tag'; btn.textContent = m; btn.onclick = () => { cmState.modifier = cmState.modifier === m ? '' : m; syncTagGroup(modEl, m, cmState.modifier); updatePreview(); }; modEl.appendChild(btn); });
    const intEl = document.getElementById('cm-intensity');
    CM_INTENSITIES.forEach(i => { const btn = document.createElement('button'); btn.className = 'cm-int-btn'; btn.textContent = i; btn.onclick = () => { cmState.intensity = cmState.intensity === i ? '' : i; syncIntensity(intEl, i, cmState.intensity); updatePreview(); }; intEl.appendChild(btn); });
}
function syncTagGroup(container, value, activeValue) {
    container.querySelectorAll('.cm-tag').forEach(b => { b.classList.toggle('selected', b.textContent.trim() === value && value === activeValue); });
}
function syncSwatches(container, name, activeName) {
    container.querySelectorAll('.cm-color-swatch').forEach(sw => { sw.classList.toggle('selected', sw.title === name && name === activeName); });
}
function syncIntensity(container, value, activeValue) {
    container.querySelectorAll('.cm-int-btn').forEach(b => { b.classList.toggle('selected', b.textContent === value && value === activeValue); });
}
function buildComposedString() {
    const parts = [];
    if (cmState.intensity) parts.push(cmState.intensity);
    if (cmState.op) parts.push(cmState.op);
    if (cmState.subject) parts.push(cmState.subject);
    if (cmState.op === 'Change color of' && cmState.fromColor) parts.push('from ' + cmState.fromColor);
    if (cmState.op === 'Change color of' && cmState.toColor)   parts.push('to '   + cmState.toColor);
    if (cmState.modifier) parts.push(cmState.modifier);
    if (cmState.freetext.trim()) parts.push(cmState.freetext.trim());
    return parts.join(' ');
}
function updatePreview() { cmState.freetext = document.getElementById('cm-freetext').value; document.getElementById('cmPreview').textContent = buildComposedString(); }
function clearCompose() {
    cmState = { op:'', subject:'', fromColor:'', toColor:'', modifier:'', intensity:'', freetext:'' };
    document.getElementById('cm-freetext').value = '';
    ['cm-ops','cm-subjects','cm-modifiers'].forEach(id => document.getElementById(id).querySelectorAll('.cm-tag').forEach(b => b.classList.remove('selected')));
    ['cm-from-swatches','cm-to-swatches'].forEach(id => document.getElementById(id).querySelectorAll('.cm-color-swatch').forEach(b => b.classList.remove('selected')));
    document.getElementById('cm-intensity').querySelectorAll('.cm-int-btn').forEach(b => b.classList.remove('selected'));
    document.getElementById('cm-color-section').style.display = 'none';
    updatePreview();
}
function useComposed() { const str = buildComposedString().trim(); if (str) document.getElementById('enhancePrompt').value = str; closeComposeModal(); }
function openComposeModal()  { document.getElementById('composeBackdrop').classList.add('active');    document.body.style.overflow = 'hidden'; }
function closeComposeModal() { document.getElementById('composeBackdrop').classList.remove('active'); document.body.style.overflow = ''; }
function onBackdropClick(e)  { if (e.target === document.getElementById('composeBackdrop')) closeComposeModal(); }

// ═══════════════════════════════════════════════════════
// FRAME MODAL
// ═══════════════════════════════════════════════════════
function openFrameModal(id) {
    document.getElementById('frameViewer').src = `view_frame.php?frame_id=${id}&view=modal`;
    document.getElementById('viewModal').classList.add('active');
}
function closeFrameModal() {
    document.getElementById('viewModal').classList.remove('active');
    setTimeout(() => { document.getElementById('frameViewer').src = ''; }, 200);
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeFrameModal(); closeComposeModal(); closeSbPicker();
        if (document.getElementById('addFramesBackdrop').classList.contains('active')) closeAddFramesModal(false);
        document.querySelectorAll('.ajax-dropdown.open, .af-dropdown.open').forEach(d => d.classList.remove('open'));
    }
});

// ═══════════════════════════════════════════════════════
// UTIL
// ═══════════════════════════════════════════════════════
function esc(s) { return s ? s.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }

// ═══════════════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    loadClipboardChips();
    buildComposeModal();

    // Restore col count
    try {
        const savedCols = localStorage.getItem('exposanim_cols');
        if (savedCols) {
            document.getElementById('colSelect').value = savedCols;
            document.getElementById('framesGrid').style.setProperty('--grid-cols', parseInt(savedCols));
        }
    } catch(e) {}
});
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
