<?php
// public/videotagger.php
// Videos Tagger -- Mass Tagging Interface for Videos
// Combines the grid UI of Videos Curator with the batch mechanics of Videos Matcher and staging of Taggeranger.
// ----------------------------------------------------
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

use App\UI\Modules\VideoFrameExtractorModule;
use App\UI\Modules\ImageEditorModule;
use App\Core\PyApiCVService;

$videoExtractor = new VideoFrameExtractorModule();
$imageEditor = new ImageEditorModule();

// ═══════════════════════════════════════════════════════
// API HANDLER
// ═══════════════════════════════════════════════════════
if (isset($_REQUEST['api_action'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    $action = $_REQUEST['api_action'];

    try {
        if ($action === 'get_tags') {
            $tags = $pdo->query("SELECT id, name FROM tags WHERE show_in_ui = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status'=>'success', 'data'=>$tags]);
            exit;
        }

        if ($action === 'hide_tag') {
            $tagId = (int)$_POST['tag_id'];
            $pdo->prepare("UPDATE tags SET show_in_ui = 0, updated_at = NOW() WHERE id = ?")->execute([$tagId]);
            echo json_encode(['status'=>'success']);
            exit;
        }

        if ($action === 'hide_all_tags') {
            $pdo->exec("UPDATE tags SET show_in_ui = 0, updated_at = NOW()");
            echo json_encode(['status'=>'success']);
            exit;
        }

        if ($action === 'save_tag_defs') {
            $tagsInput = json_decode($_POST['tags'], true) ??[];
            $upsert = $pdo->prepare("INSERT INTO tags (name, show_in_ui) VALUES (?, 1) ON DUPLICATE KEY UPDATE show_in_ui = 1, updated_at = NOW()");
            $update = $pdo->prepare("UPDATE tags SET name = ?, show_in_ui = 1, updated_at = NOW() WHERE id = ?");
            foreach ($tagsInput as $tag) {
                $name = trim($tag['name'] ?? '');
                if (!$name) continue;
                $id = !empty($tag['id']) ? (int)$tag['id'] : null;
                if ($id) $update->execute([$name, $id]);
                else     $upsert->execute([$name]);
            }
            $tags = $pdo->query("SELECT id, name FROM tags WHERE show_in_ui = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status'=>'success', 'data'=>$tags]);
            exit;
        }

        if ($action === 'get_doc_sources') {
            $docs = $pdo->query("SELECT d.id, d.name FROM documentations d INNER JOIN md_doc_analysis mda ON d.id = mda.doc_id WHERE d.keywords IS NOT NULL AND d.keywords != '' ORDER BY d.name ASC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status'=>'success', 'data'=>$docs]);
            exit;
        }

        if ($action === 'apply_doc_keywords') {
            $docId = (int)$_POST['doc_id'];
            $stmt = $pdo->prepare("SELECT d.keywords FROM documentations d INNER JOIN md_doc_analysis mda ON d.id = mda.doc_id WHERE d.id = ?");
            $stmt->execute([$docId]);
            $keywordsStr = $stmt->fetchColumn();

            if ($keywordsStr) {
                // Hide all first
                $pdo->exec("UPDATE tags SET show_in_ui = 0, updated_at = NOW()");
                
                $kwArray = array_map('trim', explode(',', $keywordsStr));
                $kwArray = array_filter($kwArray);
                
                $upsert = $pdo->prepare("INSERT INTO tags (name, show_in_ui) VALUES (?, 1) ON DUPLICATE KEY UPDATE show_in_ui = 1, updated_at = NOW()");
                foreach ($kwArray as $kw) {
                    $upsert->execute([$kw]);
                }
            }
            
            $tags = $pdo->query("SELECT id, name FROM tags WHERE show_in_ui = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status'=>'success', 'data'=>$tags]);
            exit;
        }

        if ($action === 'get_map_runs') {
            $limit  = (int)($_GET['limit'] ?? 6);
            $offset = (int)($_GET['offset'] ?? 0);
            $search = $_GET['search'] ?? '';
            
            $whereParts =["m.entity_type = 'animatics'"];
            $params =[];
            if ($search) {
                $whereParts[] = "(m.note LIKE ? OR m.id = ?)";
                $params[] = "%$search%";
                $params[] = intval($search);
            }
            $whereSQL = implode(' AND ', $whereParts);

            $countSql = "SELECT COUNT(DISTINCT m.id) FROM map_runs m INNER JOIN videos v ON m.id = v.map_run_id WHERE $whereSQL";
            $stmtCount = $pdo->prepare($countSql);
            $stmtCount->execute($params);
            $total = $stmtCount->fetchColumn();

            $sql = "SELECT m.*, COUNT(v.id) as item_count FROM map_runs m INNER JOIN videos v ON m.id = v.map_run_id WHERE $whereSQL GROUP BY m.id ORDER BY m.id DESC LIMIT $limit OFFSET $offset";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['status'=>'success', 'data'=>$rows, 'total'=>$total]);
            exit;
        }

        if ($action === 'get_videos') {
            $runId = (int)$_GET['map_run_id'];
            
            $sql = "SELECT v.id, v.name, v.thumbnail, v.url, v.duration, v.file_size, v.description, va.to_id as animatic_id
                    FROM videos v LEFT JOIN videos_2_animatics va ON v.id = va.from_id WHERE v.map_run_id = $runId ORDER BY v.id DESC";
            $videos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            
            $vIds = array_column($videos, 'id');
            $proposals =[];
            $persisted =[];
            
            if (!empty($vIds)) {
                $in = implode(',', $vIds);
                // Staged Proposals
                $stSql = "SELECT s.id as staged_id, s.video_id, s.tag_id, s.score, s.active, s.reviewed, t.name as tag_name
                          FROM tags_2_videos_staged s JOIN tags t ON t.id = s.tag_id WHERE s.video_id IN ($in) ORDER BY s.score DESC";
                foreach ($pdo->query($stSql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $proposals[$row['video_id']][] = $row;
                }
                
                // Persisted Tags
                $pSql = "SELECT tv.from_id as tag_id, tv.to_id as video_id, t.name as tag_name 
                         FROM tags_2_videos tv JOIN tags t ON t.id = tv.from_id WHERE tv.to_id IN ($in)";
                foreach ($pdo->query($pSql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $persisted[$row['video_id']][] = $row;
                }
            }

            foreach ($videos as &$v) {
                $v['proposals'] = $proposals[$v['id']] ??[];
                $v['persisted'] = $persisted[$v['id']] ??[];
                $v['reviewed'] = !empty($v['proposals']) ? (int)$v['proposals'][0]['reviewed'] : 0;
            }
            echo json_encode(['status'=>'success', 'data'=>$videos]);
            exit;
        }

        if ($action === 'toggle_staged') {
            $stagedId = (int)$_POST['staged_id'];
            $active = (int)$_POST['active'];
            $pdo->prepare("UPDATE tags_2_videos_staged SET active = ? WHERE id = ?")->execute([$active, $stagedId]);
            echo json_encode(['status'=>'success']);
            exit;
        }

        if ($action === 'set_reviewed') {
            $videoId = (int)$_POST['video_id'];
            $reviewed = (int)$_POST['reviewed'];
            $pdo->prepare("UPDATE tags_2_videos_staged SET reviewed = ? WHERE video_id = ?")->execute([$reviewed, $videoId]);
            echo json_encode(['status'=>'success']);
            exit;
        }

        if ($action === 'save_manual_tags') {
            $videoId = (int)$_POST['video_id'];
            $activeTagIds = json_decode($_POST['tag_ids'], true);
            
            // Clean up persisted tags if user unchecked them
            $persisted = $pdo->query("SELECT from_id FROM tags_2_videos WHERE to_id = $videoId")->fetchAll(PDO::FETCH_COLUMN);
            $toDeleteFromPersisted = array_diff($persisted, $activeTagIds);
            if (!empty($toDeleteFromPersisted)) {
                $inDel = implode(',', array_map('intval', $toDeleteFromPersisted));
                $pdo->prepare("DELETE FROM tags_2_videos WHERE to_id = $videoId AND from_id IN ($inDel)")->execute();
            }
            
            // Update Staged Tags
            $pdo->prepare("DELETE FROM tags_2_videos_staged WHERE video_id = ?")->execute([$videoId]);
            $stmt = $pdo->prepare("INSERT INTO tags_2_videos_staged (tag_id, video_id, score, active, reviewed) VALUES (?, ?, 1.0, 1, 1)");
            foreach ($activeTagIds as $tid) {
                // Only insert into staging if it's not already persisted
                if (!in_array($tid, $persisted)) {
                    $stmt->execute([$tid, $videoId]);
                }
            }
            
            $proposals = $pdo->query("SELECT s.id as staged_id, s.video_id, s.tag_id, s.score, s.active, s.reviewed, t.name as tag_name FROM tags_2_videos_staged s JOIN tags t ON t.id = s.tag_id WHERE s.video_id = $videoId ORDER BY s.score DESC")->fetchAll(PDO::FETCH_ASSOC);
            $persistedTags = $pdo->query("SELECT tv.from_id as tag_id, tv.to_id as video_id, t.name as tag_name FROM tags_2_videos tv JOIN tags t ON t.id = tv.from_id WHERE tv.to_id = $videoId")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status'=>'success', 'proposals'=>$proposals, 'persisted'=>$persistedTags]);
            exit;
        }

        if ($action === 'process_single_video') {
            set_time_limit(120);
            $videoId = (int)$_POST['video_id'];
            $tagIds = json_decode($_POST['tag_ids'], true);
            $runId = $_POST['run_id'] ?? uniqid();
            $skipValidation = filter_var($_POST['skip_validation'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            $tags = $pdo->query("SELECT id, name FROM tags WHERE id IN (" . implode(',', array_map('intval', $tagIds)) . ")")->fetchAll(PDO::FETCH_ASSOC);
            $tagMap =[]; foreach($tags as $t) $tagMap[$t['id']] = $t['name'];
            
            // Get currently persisted tags so we don't stage duplicates
            $persisted = $pdo->query("SELECT from_id FROM tags_2_videos WHERE to_id = $videoId")->fetchAll(PDO::FETCH_COLUMN);

            if ($skipValidation) {
                $appliedIds = array_keys($tagMap);
                foreach ($appliedIds as $tid) {
                    if (in_array($tid, $persisted)) continue;
                    $pdo->prepare("INSERT INTO tags_2_videos_staged (tag_id, video_id, score, active, reviewed, run_id) VALUES (?, ?, 1.0, 1, 1, ?) ON DUPLICATE KEY UPDATE score = GREATEST(score, 1.0), reviewed = 1, run_id = VALUES(run_id)")->execute([$tid, $videoId, $runId]);
                }
                $logMsg = "Directly applied " . count($appliedIds) . " tags (AI skipped).";
            } else {
                $video = $pdo->query("SELECT thumbnail FROM videos WHERE id = $videoId")->fetch(PDO::FETCH_ASSOC);
                if (!$video) throw new Exception("Video not found");
                
                $thumbAbs = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim(strtok($video['thumbnail'], '?'), '/');
                if (!file_exists($thumbAbs)) throw new Exception("Thumbnail not found at $thumbAbs");
                
                $prompt = "Analyze this image. Here is a dictionary of tags mapping ID to Name: " . json_encode($tagMap) . ". Return ONLY a valid JSON array containing the integer IDs of the tags that strongly apply to the image. Example:[12, 45, 8]. Do not write any other text.";
                
                $cv = new PyApiCVService();
                $res = $cv->analyze($thumbAbs, $prompt, "claude-large");
                $desc = is_array($res) ? ($res['description'] ?? $res['text'] ?? $res['content'] ?? json_encode($res)) : (string)$res;
                
                preg_match('/\[.*\]/s', $desc, $matches);
                $appliedIds =[];
                if (!empty($matches[0])) {
                    $json = json_decode($matches[0], true);
                    if (is_array($json)) {
                        foreach ($json as $tid) $appliedIds[] = (int)$tid;
                    }
                }
                
                foreach ($tagMap as $tid => $name) {
                    if (in_array($tid, $persisted)) continue;
                    $score = in_array($tid, $appliedIds) ? 0.95 : 0.05;
                    if ($score > 0.5) { 
                        $pdo->prepare("INSERT INTO tags_2_videos_staged (tag_id, video_id, score, active, reviewed, run_id) VALUES (?, ?, ?, 1, 0, ?) ON DUPLICATE KEY UPDATE score = GREATEST(score, VALUES(score)), run_id = VALUES(run_id)")->execute([$tid, $videoId, $score, $runId]);
                    }
                }
                $logMsg = "Analyzed #$videoId. Applied: " . count($appliedIds) . " tags.";
            }
            
            $proposals = $pdo->query("SELECT s.id as staged_id, s.video_id, s.tag_id, s.score, s.active, s.reviewed, t.name as tag_name FROM tags_2_videos_staged s JOIN tags t ON t.id = s.tag_id WHERE s.video_id = $videoId ORDER BY s.score DESC")->fetchAll(PDO::FETCH_ASSOC);
            $persistedTags = $pdo->query("SELECT tv.from_id as tag_id, tv.to_id as video_id, t.name as tag_name FROM tags_2_videos tv JOIN tags t ON t.id = tv.from_id WHERE tv.to_id = $videoId")->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['status'=>'success', 'proposals'=>$proposals, 'persisted'=>$persistedTags, 'log'=>$logMsg]);
            exit;
        }

        if ($action === 'persist_staged') {
            $insert = $pdo->prepare("INSERT IGNORE INTO tags_2_videos (from_id, to_id) SELECT tag_id, video_id FROM tags_2_videos_staged WHERE reviewed = 1 AND active = 1");
            $insert->execute();
            $written = $insert->rowCount();
            $pdo->prepare("DELETE FROM tags_2_videos_staged WHERE reviewed = 1")->execute();
            echo json_encode(['status'=>'success', 'written'=>$written]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
        exit;
    }
    exit;
}

$pageTitle = 'Videos Tagger';
ob_start();
?>
<!-- Dependencies -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<style>
    :root {
        --bg: #0a0a0f;
        --card: #111118;
        --border: #1e1e2e;
        --text: #e2e2f0;
        --text-muted: #555570;
        --blue: #3b82f6;
        --blue-dim: rgba(59, 130, 246, 0.1);
        --green: #10b981;
        --amber: #f59e0b;
        --cyan: #06b6d4;
        --purple: #8b5cf6;
        --console-bg: #0d0d0d;
    }

    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; height: 100%; overflow: hidden; }

    /* ── LAYOUT ── */
    .eh-layout { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }

    .eh-header { flex-shrink: 0; padding: 10px 16px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
    .eh-title { font-size: 1rem; font-weight: 700; letter-spacing: 1px; color: var(--purple); display: flex; align-items: center; gap: 8px; }

    /* ── MAP RUNS ── */
    .eh-top-panel { flex-shrink: 0; display: flex; flex-direction: column; border-bottom: 1px solid var(--border); background: rgba(0,0,0,0.2); max-height: 25vh; }
    .mr-controls-row { display: flex; gap: 8px; padding: 8px 12px; border-bottom: 1px solid var(--border); align-items: center; background: var(--card); }
    .mr-search-input { flex: 1; padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.8rem; }
    .mr-search-input:focus { outline: none; border-color: var(--blue); }
    .mr-pagination { display: flex; align-items: center; gap: 4px; }
    .pg-btn { width: 26px; height: 26px; background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 3px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
    .pg-btn:hover:not(:disabled) { border-color: var(--blue); color: var(--blue); }
    .pg-input { width: 40px; text-align: center; background: var(--bg); border: 1px solid var(--border); color: var(--blue); border-radius: 3px; font-family: inherit; font-size: 0.75rem; font-weight: 700; padding: 4px 0; -moz-appearance: textfield; }
    .pg-total { font-size: 0.7rem; color: var(--text-muted); padding: 0 4px; }

    .mr-list-scroll { overflow-y: auto; min-height: 60px; }
    .mr-item { padding: 8px 12px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.15s; display: flex; align-items: center; gap: 10px; }
    .mr-item:hover { background: rgba(255,255,255,0.03); }
    .mr-item.active { background: rgba(139, 92, 246, 0.1); border-left: 3px solid var(--purple); padding-left: 9px; }
    .mr-id { font-size: 0.7rem; font-weight: 700; color: var(--purple); min-width: 40px; }
    .mr-note { font-size: 0.75rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
    .mr-meta { font-size: 0.65rem; color: var(--text-muted); white-space: nowrap; }

    /* ── TOOLBAR ── */
    .eh-mid-panel { flex-shrink: 0; background: var(--card); border-bottom: 1px solid var(--border); z-index: 5; }
    .grid-toolbar { padding: 6px 12px; background: rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: space-between; }
    .gt-left { display: flex; align-items: center; gap: 12px; }
    .gt-actions { display: flex; gap: 8px; align-items: center; }
    
    .action-btn { padding: 4px 10px; border-radius: 3px; font-size: 0.65rem; font-weight: 700; border: 1px solid var(--border); background: transparent; color: var(--text-muted); cursor: pointer; text-transform: uppercase; font-family: inherit; display: inline-flex; align-items: center; justify-content: center; }
    .action-btn:hover { color: var(--purple); border-color: var(--purple); }
    .action-btn.primary { border-color: var(--purple); color: var(--purple); }
    .action-btn.green { border-color: var(--green); color: var(--green); }

    /* ── GRID ── */
    .eh-grid-area { flex: 1; overflow-y: auto; padding: 10px; position: relative; background: #000; min-height: 0; }
    .frames-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; padding-bottom: 20px; }
    
    .f-card { display: flex; flex-direction: column; background: #111; border: 2px solid var(--border); border-radius: 4px; overflow: hidden; transition: border-color 0.15s; }
    .f-card.selected { border-color: var(--purple); }
    
    .f-thumb-wrap { position: relative; width: 100%; aspect-ratio: 16/9; background: #000; }
    .f-thumb { width: 100%; height: 100%; object-fit: cover; cursor: pointer; transition: transform 0.2s; }
    .f-thumb:hover { transform: scale(1.03); }
    
    .f-view-btn { position: absolute; top: 5px; right: 5px; width: 38px; height: 38px; background: rgba(0,0,0,0.6); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; opacity: 0; transition: all 0.2s; font-size: 18px; }
    .f-thumb-wrap:hover .f-view-btn { opacity: 1; }
    .f-view-btn:hover { background: var(--blue); border-color: var(--blue); }
    
    .f-select-trigger { position: absolute; bottom: 5px; right: 5px; width: 38px; height: 38px; border: 1px solid #555; border-radius: 3px; background: rgba(0,0,0,0.5); cursor: pointer; z-index: 10; display: flex; align-items: center; justify-content: center; }
    .f-card.selected .f-select-trigger { background: var(--purple); border-color: var(--purple); color: #fff; font-size: 16px; font-weight: 900; }
    .f-card.selected .f-select-trigger::after { content: '✓'; }

    .f-tags { padding: 6px; display: flex; flex-wrap: wrap; gap: 4px; background: #0a0a0f; border-top: 1px solid var(--border); min-height: 32px; align-content: flex-start; }
    .tag-chip { padding: 2px 6px; border-radius: 3px; font-size: 0.65rem; font-weight: 700; cursor: pointer; border: 1px solid transparent; user-select: none; }
    .tag-chip.on { background: rgba(245,158,11,0.15); border-color: rgba(245,158,11,0.5); color: var(--amber); }
    .tag-chip.off { background: rgba(255,255,255,0.03); border-color: var(--border); color: var(--text-muted); text-decoration: line-through; opacity: 0.5; }
    .tag-chip.persisted { background: rgba(16,185,129,0.15); border-color: rgba(16,185,129,0.5); color: var(--green); cursor: default; }

    .f-review-bar { display: flex; justify-content: space-between; align-items: center; padding: 4px 6px; background: #0a0a0f; border-top: 1px solid var(--border); }
    .f-id { font-size: 0.65rem; color: #aaa; font-weight: 700; }
    .review-check { width: 38px; height: 38px; border-radius: 4px; border: 1px solid var(--border); background: transparent; appearance: none; cursor: pointer; position: relative; }
    .review-check:checked { background: var(--green); border-color: var(--green); }
    .review-check:checked::after { content: ''; position: absolute; top: 4px; left: 12px; width: 10px; height: 20px; border: 3px solid #000; border-top: none; border-left: none; transform: rotate(45deg); }

    .state-msg { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); font-size: 0.8rem; gap: 8px; }
    .spinner { width: 20px; height: 20px; border: 2px solid var(--blue-dim); border-top-color: var(--purple); border-radius: 50%; animation: spin 0.8s linear infinite; display: inline-block; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── MODALS ── */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.85); display: none; align-items: center; justify-content: center; z-index: 12000; padding: 10px; backdrop-filter: blur(5px); }
    .modal-content { background: var(--card); border-radius: 8px; border: 1px solid var(--border); display: flex; flex-direction: column; box-shadow: 0 20px 50px rgba(0,0,0,0.5); overflow: hidden; }
    .modal-header { padding: 10px 15px; border-bottom: 1px solid var(--border); font-weight: 700; font-size: 1rem; color: var(--text); display: flex; justify-content: space-between; align-items: center; }
    
    /* Batch Modal specifics */
    .console-header { padding: 5px 10px; background: #1a1a1a; border-bottom: 1px solid #333; font-size: 0.7rem; color: #888; text-transform: uppercase; letter-spacing: 1px; display: flex; justify-content: space-between; }
    .console-scroll { flex: 1; overflow-y: auto; padding: 10px; font-family: 'Consolas', 'Monaco', monospace; font-size: 0.8rem; color: #ccc; line-height: 1.4; background: var(--console-bg); }
    .log-line { margin-bottom: 4px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 2px;}
    .log-time { color: #555; margin-right: 8px; }
    .log-info { color: var(--cyan); }
    .log-success { color: var(--green); }
    .log-error { color: var(--red); }
    
    .batch-card { position: relative; border: 2px solid var(--border); border-radius: 4px; overflow: hidden; background: #000; }
    .batch-card img { width: 100%; aspect-ratio: 16/9; object-fit: cover; display: block; }
    .batch-card.processing { border-color: var(--cyan); box-shadow: 0 0 10px rgba(6,182,212,0.2); }
    .batch-card.matched { border-color: var(--green); opacity: 0.5; }
    .batch-card.matched::after { content: "DONE"; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-10deg); background: var(--green); color: #000; font-weight: 900; font-size: 0.8rem; padding: 4px 10px; border-radius: 4px; }

    /* Video Detail Modal specifics */
    .detail-player-wrapper { width: 100%; background: #000; aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center; }
    .detail-player-wrapper video { width: 100%; height: 100%; max-height: 50vh; }
    .detail-content { padding: 16px; overflow-y: auto; }
    .detail-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 8px; color: var(--text); }
    .detail-meta-row { display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px; }
    .detail-actions-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 16px; }
    .detail-actions-grid .btn { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 10px 4px; font-size: 0.75rem; gap: 4px; border: 1px solid var(--border); background: rgba(255,255,255,0.05); color: var(--text); border-radius: 6px; cursor: pointer; text-decoration: none; }
    .detail-actions-grid .btn:hover { background: rgba(255,255,255,0.1); border-color: var(--blue); color: var(--text); }
    .detail-desc-btn { width: 100%; text-align: left; margin-top: 10px; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 6px; border: 1px solid var(--border); color: var(--text); cursor: pointer; }
</style>

<div class="eh-layout">
    <div class="eh-header">
        <div class="eh-title"><span>&#127909;</span> VIDEOS TAGGER</div>
        <button class="action-btn" style="border-color:var(--purple); color:var(--purple);" onclick="openTagManagerModal()">⚙️ Tags</button>
    </div>

    <div class="eh-top-panel">
        <div class="mr-controls-row">
            <input type="text" class="mr-search-input" id="mrSearch" placeholder="Search Map Run..." oninput="debounceSearch()">
            <div class="mr-pagination">
                <button class="pg-btn" id="mrPrev" onclick="changePage(-1)">&#8592;</button>
                <input type="number" class="pg-input" id="mrPageInput" value="1" onchange="jumpToPage()">
                <span class="pg-total" id="mrTotalPages">/ 1</span>
                <button class="pg-btn" id="mrNext" onclick="changePage(1)">&#8594;</button>
            </div>
        </div>
        <div class="mr-list-scroll" id="mrList"><div class="state-msg">Loading runs...</div></div>
    </div>

    <div class="eh-mid-panel">
        <div class="grid-toolbar">
            <div class="gt-left"></div>
            <div class="gt-actions" id="gridActions" style="display:none;">
                <button class="action-btn" onclick="toggleAll(false)">None</button>
                <button class="action-btn" onclick="toggleAll(true)">All</button>
                <div style="width:1px; background:var(--border); margin:0 8px;"></div>
                <button class="action-btn primary" onclick="openBatchModal()">⚡ Auto-Tag Selected</button>
                <button class="action-btn green" onclick="persistStaged()">💾 Persist Reviewed</button>
            </div>
        </div>
    </div>

    <div class="eh-grid-area">
        <div class="state-msg" id="gridState">
            <div>&#8593; Select a Map Run</div>
        </div>
        <div class="frames-grid" id="framesGrid" style="display:none;"></div>
    </div>
</div>

<!-- TAG MANAGER MODAL -->
<div id="tagManagerModal" class="modal-overlay">
    <div class="modal-content" style="width:480px; max-width:95%;">
        <div class="modal-header">
            <span style="display:flex; align-items:center; gap:8px;">⚙️ Manage Tags</span>
            <button class="action-btn" style="color:#ef4444; border-color:rgba(239,68,68,0.3);" onclick="clearAllTagsFromUI()">✕ Clear All</button>
        </div>
        <div style="padding:15px;">
            <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--border);">
                <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:5px;">Load Keywords from Document:</label>
                <select id="tmDocSelect" class="mr-search-input" style="width:100%; cursor:pointer;" onchange="applyDocKeywords(this.value)">
                    <option value="">-- Select a Document --</option>
                </select>
            </div>

            <p style="font-size:0.75rem; color:var(--text-muted); margin-top:0;">Hiding a tag only removes it from this UI. Add it back by typing the name.</p>
            <div id="tmTagList" style="display:flex; flex-direction:column; gap:6px; max-height:320px; overflow-y:auto; margin-bottom:12px;"></div>
            <button class="action-btn" style="width:100%; border:1px dashed rgba(139,92,246,0.4); color:var(--purple); padding:8px;" onclick="addTmTagRow()">+ Add Tag</button>
            <div style="display:flex; gap:8px; margin-top:15px;">
                <button class="action-btn" style="flex:1;" onclick="closeTaggerModal('tagManagerModal')">Cancel</button>
                <button class="action-btn primary" style="flex:2;" onclick="saveTmTags()">✓ Save Tags</button>
            </div>
        </div>
    </div>
</div>

<!-- MANUAL TAG MODAL -->
<div id="manualTagModal" class="modal-overlay">
    <div class="modal-content" style="width: 500px; max-width:95%;">
        <div class="modal-header">
            <span>Manual Tagging</span>
            <button class="btn btn-sm btn-outline-secondary" onclick="closeTaggerModal('manualTagModal')">Close</button>
        </div>
        <div style="padding: 15px;">
            <img id="manualThumb" style="width:100%; aspect-ratio:16/9; object-fit:cover; margin-bottom:10px; border-radius:4px; border:1px solid var(--border);">
            <div id="manualTagsContainer" style="display:flex; flex-wrap:wrap; gap:5px; margin-bottom:15px; max-height: 200px; overflow-y:auto; padding:5px; background:#000; border:1px solid var(--border); border-radius:4px;"></div>
            <button class="action-btn primary" style="width:100%; padding:10px; font-size:0.8rem;" onclick="saveManualTags()">Save Tags</button>
        </div>
    </div>
</div>

<!-- BATCH TAG MODAL -->
<div id="batchTagModal" class="modal-overlay">
    <div class="modal-content" style="width: 90%; max-width: 1000px; height: 90vh;">
        <div class="modal-header">
            <span>⚡ Batch Auto-Tagging</span>
            <button class="btn btn-sm btn-outline-secondary" onclick="closeTaggerModal('batchTagModal')">Close</button>
        </div>
        <div class="eh-top-panel" style="height: 180px; flex-shrink:0;">
            <div class="console-header">Operations Log</div>
            <div class="console-scroll" id="batchConsole"></div>
        </div>
        <div style="padding: 10px; border-bottom: 1px solid var(--border); background: var(--card); flex-shrink:0;">
            <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:5px;">Select Tags to Evaluate:</div>
            <div id="batchTagsContainer" style="display:flex; flex-wrap:wrap; gap:5px; margin-bottom:10px; max-height:100px; overflow-y:auto;"></div>
            
            <label style="font-size:0.75rem; color:var(--text-muted); cursor:pointer; display:flex; align-items:center; gap:5px; margin-bottom:10px;">
                <input type="checkbox" id="skipValidationBatch" style="accent-color:var(--purple); width:14px; height:14px;">
                Skip AI Validation (Apply selected tags directly at 100% confidence)
            </label>

            <button class="action-btn primary" style="padding:8px 16px;" onclick="startBatchTagging()" id="btnStartBatch">▶ Start Batch Run</button>
        </div>
        <div class="eh-grid-area" style="flex:1;">
            <div class="frames-grid" id="batchGrid" style="grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap:5px;"></div>
        </div>
    </div>
</div>

<!-- RICH VIDEO DETAIL MODAL -->
<div id="videoDetailModal" class="modal-overlay">
    <div class="modal-content" style="width: 100%; max-width: 800px; max-height: 95vh;">
        <div class="modal-header">
            <strong>Video Details</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="closeVideoDetailModal()">Close</button>
        </div>
        <div class="detail-player-wrapper">
            <video id="detailVideoPlayer" controls playsinline controlsList="nodownload"></video>
        </div>
        <div class="detail-content">
            <div class="detail-title" id="detailVideoName"></div>
            <div class="detail-meta-row" id="detailMeta"></div>
            <div class="detail-actions-grid" id="detailActionButtons"></div>
            <button id="detailDescTrigger" class="detail-desc-btn">
                📄 <strong>Description:</strong> <span id="detailDescSnippet"></span>
            </button>
        </div>
    </div>
</div>

<?= $videoExtractor->render() ?>
<?= $imageEditor->render() ?>

<script>
let curPage = 1, totalPages = 1, currentRunId = null, currentVideos =[], selectedIds = new Set();
let debounceTimer;
let allTags =[];

document.addEventListener('DOMContentLoaded', () => { 
    loadTags();
    loadMapRuns(1); 
    loadDocSources();
});

function loadTags() {
    fetch('?api_action=get_tags').then(r=>r.json()).then(res => {
        if(res.status === 'success') allTags = res.data;
    });
}

// ── TAG MANAGER LOGIC ──
function openTagManagerModal() {
    renderTmTagList();
    document.getElementById('tagManagerModal').style.display = 'flex';
}

function loadDocSources() {
    fetch('?api_action=get_doc_sources').then(r=>r.json()).then(res => {
        if(res.status === 'success') {
            const sel = document.getElementById('tmDocSelect');
            res.data.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = d.name;
                sel.appendChild(opt);
            });
        }
    });
}

function applyDocKeywords(docId) {
    if (!docId) return;
    if (!confirm('This will hide all currently visible tags and replace them with the document keywords. Continue?')) {
        document.getElementById('tmDocSelect').value = "";
        return;
    }
    
    const fd = new FormData();
    fd.append('doc_id', docId);
    
    fetch('?api_action=apply_doc_keywords', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if(res.status === 'success') {
            allTags = res.data;
            renderTmTagList();
            Toast.show('Tags loaded from document', 'success');
        } else {
            Toast.show('Error loading tags', 'error');
        }
        document.getElementById('tmDocSelect').value = ""; // reset
    });
}

function renderTmTagList() {
    const list = document.getElementById('tmTagList');
    list.innerHTML = '';
    if (allTags.length === 0) {
        list.innerHTML = '<div style="font-size:0.78rem; color:var(--text-muted); font-style:italic;">No tags loaded.</div>';
        return;
    }
    allTags.forEach(tag => {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex; align-items:center; gap:8px;';
        row.dataset.id = tag.id;

        const nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.value = tag.name;
        nameInput.className = 'tm-name-input';
        nameInput.style.cssText = 'flex:1; padding:5px 8px; border:1px solid var(--border); border-radius:3px; background:var(--bg); color:var(--text); font-family:inherit; font-size:0.82rem;';

        const hideBtn = document.createElement('button');
        hideBtn.title = 'Hide from UI';
        hideBtn.innerHTML = '✕';
        hideBtn.style.cssText = 'background:transparent; border:none; color:#ef4444; font-size:1rem; cursor:pointer; padding:0 4px;';
        hideBtn.onclick = () => hideTmTag(tag.id, tag.name, hideBtn);

        row.appendChild(nameInput);
        row.appendChild(hideBtn);
        list.appendChild(row);
    });
}

function addTmTagRow() {
    allTags.push({ id: null, name: '' });
    renderTmTagList();
    const inputs = document.querySelectorAll('#tmTagList .tm-name-input');
    if (inputs.length) inputs[inputs.length - 1].focus();
}

function hideTmTag(tagId, tagName, btn) {
    if (!tagId) { allTags = allTags.filter(t => t.id !== tagId); renderTmTagList(); return; }
    btn.disabled = true;
    const fd = new FormData(); fd.append('tag_id', tagId);
    fetch('?api_action=hide_tag', { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
        if (res.status === 'success') {
            allTags = allTags.filter(t => t.id !== tagId);
            renderTmTagList();
            Toast.show('"' + tagName + '" hidden');
        } else { Toast.show('Error', 'error'); btn.disabled = false; }
    });
}

function clearAllTagsFromUI() {
    fetch('?api_action=hide_all_tags', { method: 'POST' }).then(r=>r.json()).then(res => {
        if (res.status === 'success') {
            allTags =[];
            renderTmTagList();
            Toast.show('All tags hidden');
        }
    });
}

function saveTmTags() {
    const rows = document.querySelectorAll('#tmTagList [data-id]');
    const defs =[];
    rows.forEach(row => {
        const name = row.querySelector('.tm-name-input').value.trim();
        const id = row.dataset.id || null;
        if (name) defs.push({ id: id ? parseInt(id) : null, name });
    });
    const fd = new FormData(); fd.append('tags', JSON.stringify(defs));
    fetch('?api_action=save_tag_defs', { method: 'POST', body: fd }).then(r=>r.json()).then(res => {
        if (res.status === 'success') {
            allTags = res.data;
            closeTaggerModal('tagManagerModal');
            Toast.show('Tags saved');
        } else Toast.show('Error', 'error');
    });
}

// ── GRID & LIST LOGIC ──
function debounceSearch() { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => loadMapRuns(1), 300); }
function changePage(d) { const n = curPage + d; if (n >= 1 && n <= totalPages) loadMapRuns(n); }
function jumpToPage() { const v = parseInt(document.getElementById('mrPageInput').value); if (v >= 1 && v <= totalPages) loadMapRuns(v); }

function loadMapRuns(page) {
    const list = document.getElementById('mrList');
    const search = document.getElementById('mrSearch').value.trim();
    if(page === 1) list.scrollTop = 0;
    
    fetch(`?api_action=get_map_runs&limit=6&offset=${(page-1)*6}&search=${encodeURIComponent(search)}`)
        .then(r => r.json()).then(res => {
            if(res.status !== 'success') return;
            curPage = page; totalPages = Math.ceil(res.total/6) || 1;
            document.getElementById('mrPageInput').value = curPage;
            document.getElementById('mrTotalPages').textContent = `/ ${totalPages}`;
            
            list.innerHTML = '';
            if(!res.data.length) { list.innerHTML = '<div class="state-msg">No runs found</div>'; return; }
            
            res.data.forEach(run => {
                const el = document.createElement('div');
                el.className = `mr-item ${run.id == currentRunId ? 'active' : ''}`;
                el.onclick = () => selectRun(run.id, el);
                el.innerHTML = `<div class="mr-id">#${run.id}</div><div class="mr-note">${run.note||'No note'}</div><div class="mr-meta">${run.item_count} vids</div>`;
                list.appendChild(el);
            });
        });
}

function selectRun(runId, el) {
    document.querySelectorAll('.mr-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
    currentRunId = runId;
    
    const grid = document.getElementById('framesGrid');
    const state = document.getElementById('gridState');
    
    state.style.display = 'flex'; state.innerHTML = '<div class="spinner"></div><div>Loading videos...</div>';
    grid.style.display = 'none';
    
    fetch(`?api_action=get_videos&map_run_id=${runId}`).then(r => r.json()).then(res => {
        currentVideos = res.data;
        selectedIds.clear();
        renderGrid();
        state.style.display = 'none';
        grid.style.display = 'grid';
        document.getElementById('gridActions').style.display = 'flex';
    });
}

function renderGrid() {
    const grid = document.getElementById('framesGrid');
    grid.innerHTML = '';
    
    currentVideos.forEach(v => {
        const card = document.createElement('div');
        card.className = 'f-card';
        card.dataset.id = v.id;
        card.dataset.reviewed = v.reviewed;
        
        let chipsHtml = '';
        const persistedTagIds = new Set();
        
        v.persisted.forEach(p => {
            persistedTagIds.add(p.tag_id);
            chipsHtml += `<span class="tag-chip persisted" title="Persisted">${p.tag_name}</span>`;
        });
        
        v.proposals.forEach(p => {
            if (persistedTagIds.has(p.tag_id)) return;
            const isOn = p.active ? 'on' : 'off';
            chipsHtml += `<span class="tag-chip ${isOn}" onclick="toggleChip(${p.staged_id}, ${v.id}, this)">${p.tag_name}</span>`;
        });
        
        if(chipsHtml === '') chipsHtml = `<span style="font-size:0.6rem; color:#555; font-style:italic;">No tags</span>`;

        card.innerHTML = `
            <div class="f-thumb-wrap">
                <img src="${v.thumbnail}" class="f-thumb" loading="lazy" onclick="openManualTag(${v.id})">
                <div class="f-view-btn" onclick="openDetailModal(${v.id})"><i class="bi bi-arrows-fullscreen"></i></div>
                <div class="f-select-trigger" onclick="toggleSelect(${v.id}, this.parentElement.parentElement)"></div>
            </div>
            <div class="f-tags" id="tags_${v.id}">${chipsHtml}</div>
            <div class="f-review-bar">
                <span class="f-id">#${v.id}</span>
                <label style="font-size:0.65rem; display:flex; gap:4px; align-items:center; cursor:pointer;">
                    <input type="checkbox" class="review-check" ${v.reviewed ? 'checked' : ''} onchange="toggleReviewed(${v.id}, this.checked)">
                </label>
                <button class="action-btn" style="padding:2px 6px; font-size:0.6rem;" onclick="openManualTag(${v.id})">TAG</button>
            </div>
        `;
        grid.appendChild(card);
    });
}

function toggleSelect(id, card) {
    if(selectedIds.has(id)) { selectedIds.delete(id); card.classList.remove('selected'); }
    else { selectedIds.add(id); card.classList.add('selected'); }
}

function toggleAll(select) {
    document.querySelectorAll('.f-card').forEach(c => {
        const id = parseInt(c.dataset.id);
        if(select) { selectedIds.add(id); c.classList.add('selected'); }
        else { selectedIds.delete(id); c.classList.remove('selected'); }
    });
}

// ── TAGGING LOGIC ──

function toggleChip(stagedId, videoId, chip) {
    const isOn = chip.classList.contains('on');
    const newState = isOn ? 0 : 1;
    chip.classList.toggle('on', !isOn);
    chip.classList.toggle('off', isOn);
    
    const fd = new FormData();
    fd.append('staged_id', stagedId);
    fd.append('active', newState);
    fetch('?api_action=toggle_staged', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if(res.status !== 'success') {
            chip.classList.toggle('on', isOn);
            chip.classList.toggle('off', !isOn);
        } else {
            const v = currentVideos.find(vid => vid.id === videoId);
            const p = v.proposals.find(prop => prop.staged_id == stagedId);
            if(p) p.active = newState;
        }
    });
}

function toggleReviewed(videoId, checked) {
    const fd = new FormData();
    fd.append('video_id', videoId);
    fd.append('reviewed', checked ? 1 : 0);
    fetch('?api_action=set_reviewed', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if(res.status !== 'success') Toast.show('Error updating', 'error');
        else {
            const v = currentVideos.find(vid => vid.id === videoId);
            if(v) v.reviewed = checked ? 1 : 0;
            const c = document.querySelector(`.f-card[data-id="${videoId}"]`);
            if(c) c.dataset.reviewed = checked ? "1" : "0";
        }
    });
}

function persistStaged() {
    fetch('?api_action=persist_staged').then(r=>r.json()).then(res => {
        if(res.status === 'success') {
            Toast.show(`Persisted ${res.written} rows`, 'success');
            if(currentRunId) selectRun(currentRunId, document.querySelector('.mr-item.active'));
        }
    });
}

// ── MANUAL MODAL ──
let manualCurrentVideoId = null;
let manualSelectedTags = new Set();

function openManualTag(id) {
    manualCurrentVideoId = id;
    const v = currentVideos.find(vid => vid.id === id);
    document.getElementById('manualThumb').src = v.thumbnail;
    
    manualSelectedTags.clear();
    v.persisted.forEach(p => manualSelectedTags.add(p.tag_id));
    v.proposals.forEach(p => { if(p.active) manualSelectedTags.add(p.tag_id); });
    
    const tc = document.getElementById('manualTagsContainer');
    tc.innerHTML = '';
    allTags.forEach(t => {
        const chip = document.createElement('span');
        
        if (manualSelectedTags.has(t.id)) {
            chip.className = 'tag-chip on';
        } else {
            chip.className = 'tag-chip off';
        }
        
        chip.innerText = t.name;
        chip.onclick = () => {
            if(manualSelectedTags.has(t.id)) { manualSelectedTags.delete(t.id); chip.className = 'tag-chip off'; }
            else { manualSelectedTags.add(t.id); chip.className = 'tag-chip on'; }
        };
        tc.appendChild(chip);
    });
    
    document.getElementById('manualTagModal').style.display = 'flex';
}

function saveManualTags() {
    const fd = new FormData();
    fd.append('video_id', manualCurrentVideoId);
    fd.append('tag_ids', JSON.stringify(Array.from(manualSelectedTags)));
    fetch('?api_action=save_manual_tags', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if(res.status === 'success') {
            closeTaggerModal('manualTagModal');
            const v = currentVideos.find(vid => vid.id === manualCurrentVideoId);
            if(v) { 
                v.proposals = res.proposals; 
                v.persisted = res.persisted;
                v.reviewed = 1; 
            }
            renderGrid();
        }
    });
}

// ── BATCH MODAL ──
let batchSelectedTags = new Set();
let isBatchRunning = false;

function logBatch(msg, type='info') {
    const consoleEl = document.getElementById('batchConsole');
    const time = new Date().toLocaleTimeString('en-GB', {hour12:false});
    const cls = {success:'log-success', error:'log-error'}[type] || 'log-info';
    consoleEl.innerHTML += `<div class="log-line"><span class="log-time">[${time}]</span> <span class="${cls}">${msg}</span></div>`;
    consoleEl.scrollTop = consoleEl.scrollHeight;
}

function openBatchModal() {
    if(selectedIds.size === 0) return Toast.show('No videos selected');
    batchSelectedTags.clear();
    
    const tc = document.getElementById('batchTagsContainer');
    tc.innerHTML = '';
    allTags.forEach(t => {
        const chip = document.createElement('span');
        chip.className = 'tag-chip off';
        chip.innerText = t.name;
        chip.onclick = () => {
            if(isBatchRunning) return;
            if(batchSelectedTags.has(t.id)) { batchSelectedTags.delete(t.id); chip.className = 'tag-chip off'; }
            else { batchSelectedTags.add(t.id); chip.className = 'tag-chip on'; }
        };
        tc.appendChild(chip);
    });
    
    const gc = document.getElementById('batchGrid');
    gc.innerHTML = '';
    selectedIds.forEach(id => {
        const v = currentVideos.find(vid => vid.id === id);
        if(v) gc.innerHTML += `<div class="batch-card" id="bcard-${id}"><img src="${v.thumbnail}"></div>`;
    });
    
    document.getElementById('batchConsole').innerHTML = '';
    logBatch(`Ready to tag ${selectedIds.size} videos.`);
    document.getElementById('batchTagModal').style.display = 'flex';
}

async function startBatchTagging() {
    if(batchSelectedTags.size === 0) return Toast.show('Select at least one tag to evaluate', 'error');
    isBatchRunning = true;
    const btn = document.getElementById('btnStartBatch');
    btn.disabled = true; btn.innerText = 'Running...';
    
    const runId = 'batch_' + Date.now();
    const tagIdsStr = JSON.stringify(Array.from(batchSelectedTags));
    const skipValidation = document.getElementById('skipValidationBatch').checked;
    
    for (const vidId of Array.from(selectedIds)) {
        const card = document.getElementById(`bcard-${vidId}`);
        if(card) card.classList.add('processing');
        if(card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        try {
            const fd = new FormData();
            fd.append('video_id', vidId);
            fd.append('tag_ids', tagIdsStr);
            fd.append('run_id', runId);
            fd.append('skip_validation', skipValidation ? 'true' : 'false');
            
            const r = await fetch('?api_action=process_single_video', {method:'POST', body:fd});
            const res = await r.json();
            
            if(card) card.classList.remove('processing');
            
            if(res.status === 'success') {
                logBatch(res.log, 'success');
                if(card) card.classList.add('matched');
                const v = currentVideos.find(vid => vid.id === vidId);
                if(v) {
                    v.proposals = res.proposals;
                    v.persisted = res.persisted;
                }
            } else {
                logBatch(`Error #${vidId}: ${res.message}`, 'error');
            }
        } catch(e) {
            if(card) card.classList.remove('processing');
            logBatch(`Network Error #${vidId}`, 'error');
        }
    }
    
    isBatchRunning = false;
    btn.disabled = false; btn.innerText = '▶ Start Batch Run';
    logBatch('Batch completed. Close modal to review tags.', 'success');
    renderGrid();
}

// ── RICH VIDEO DETAIL MODAL ──
function openDetailModal(id) {
    const vid = currentVideos.find(v => v.id == id);
    if(!vid) return;
    
    const p = document.getElementById('detailVideoPlayer');
    p.src = vid.url; p.load();
    
    document.getElementById('detailVideoName').textContent = vid.name;
    document.getElementById('detailMeta').innerHTML = `<span>ID: ${vid.id}</span><span>Size: ${(vid.file_size/1024/1024).toFixed(2)} MB</span><span>Duration: ${vid.duration}s</span>`;
    document.getElementById('detailDescSnippet').textContent = vid.description || 'No description';
    
    let animaticBtn = '';
    if(vid.animatic_id) {
        animaticBtn = `<button class="btn edit-animatic-btn" data-animatic-id="${vid.animatic_id}" style="border-color:var(--blue); color:var(--blue);">🎬 Animatics</button>`;
    }

    const btns = document.getElementById('detailActionButtons');
    btns.innerHTML = `
        <button class="btn" onclick="window.VideoFrameExtractor.open('${vid.url}', ${vid.id})">✂️ Frame</button>
        ${animaticBtn}
        <button class="btn" onclick="triggerAdminAction('regenerate_thumbnail', ${vid.id})">🌇 Thumb</button>
        <button class="btn" onclick="triggerAdminAction('queue_rembg', ${vid.id})">◩ Rembg</button>
        <a class="btn" href="${vid.url}" download target="_blank">⬇️ DL</a>
    `;

    btns.querySelectorAll('.edit-animatic-btn').forEach(b => {
        b.onclick = () => {
            const aId = b.dataset.animaticId;
            if(window.showEntityFormInModal) window.showEntityFormInModal('animatics', aId);
            else alert("CRUD Modal not available.");
        };
    });

    document.getElementById('videoDetailModal').style.display = 'flex';
}

function closeVideoDetailModal() {
    document.getElementById('videoDetailModal').style.display = 'none';
    document.getElementById('detailVideoPlayer').pause();
}

function triggerAdminAction(act, id) {
    if(!confirm('Perform this action?')) return;
    fetch('video_admin_api.php?action='+act, {
        method:'POST', body: JSON.stringify({id: id})
    }).then(r=>r.json()).then(d => {
        if(d.status==='ok') Toast.show('Success', 'success');
        else Toast.show(d.message, 'error');
    });
}

function closeTaggerModal(id) { document.getElementById(id).style.display = 'none'; }
</script>
<?php
// Include modal_frame_details to support showEntityFormInModal
include __DIR__ . '/modal_frame_details.php'; 
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>