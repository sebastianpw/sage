<?php
// public/taggeranger_api.php
// Taggeranger -- Auto-Tag Engine API
// The file that will outlive us all.
// V4: persist_staged now also clears reviewed rows from staging after commit
// ──────────────────────────────────────────────────────
// GET  actions:
//   get_tags              — tags WHERE show_in_ui=1 (from taggerang)
//   get_doc_sources       — documentations with keywords for mass loading
//   get_staged_frames     — paginated staged frames with proposals
//   staged_count          — total/reviewed/pending counts
//   get_map_runs          — paginated list of sketches map runs + frame count
//   get_map_run_frames    — frames for a given map run (with sketch info)
//   get_narrative_docs    — docs with narrative_utility set (for Narratives Filter tab)
//
// POST actions:
//   run_autotag           — score frames vs tags, write to staging (SSE stream)
//                           Supports three modes:
//                             frame_range: from_id, to_id
//                             map_run:     frame_ids[], map_run_id,
//                                          tag_all_frames_of_sketch (bool)
//                             narratives:  narratives_doc_id, narratives_filter
//                                          {text, items:[{cat,name}]}
//   resolve_narrative_frames — resolve narrative filter → frame list (no scoring)
//                              Used by gallery preview (two-step UI flow)
//   toggle_staged         — flip active flag on a staged proposal
//   set_reviewed          — mark a frame reviewed/unreviewed in staging
//   persist_staged        — flush reviewed+active staging rows → tags_2_frames,
//                           then DELETE all reviewed staging rows (cleanup)
//   hide_tag              — set show_in_ui=0 on a single tag
//   hide_all_tags         — set show_in_ui=0 on all tags
//   save_tag_defs         — upsert tag definitions
//   apply_doc_keywords    — clear UI tags and load keywords from a doc
// ──────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

require_once __DIR__ . '/../src/Core/AbstractContextEngine.php';
require_once __DIR__ . '/../src/Core/VectorContextEngine.php';
require_once __DIR__ . '/SketchLibrary.php';
require_once __DIR__ . '/../src/Core/PyApiVectorService.php';
require_once __DIR__ . '/../src/Service/LoreAccessService.php';

// Narratives Filter run mode dependencies
require_once __DIR__ . '/narratives_query_helpers.php';

use App\Core\VectorContextEngine;
use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);


// ==============================================================================
// QUERY ENRICHER INITIALIZATION
// Copied verbatim from narratives_api.php — powers AI enrichment in narratives
// filter run mode. Safe to call even if the config is missing; enrichFilterQuery()
// falls back to the raw lore query string gracefully.
// ==============================================================================
$queryEnricherConfig      = null;
$queryEnricherService     = null;
$queryEnricherSeriesBible = '';
$queryEnricherKeywords    = '';
$enricherInitError        = null;

try {
    global $spw;
    if (!isset($spw)) {
        throw new Exception("Showrunner Core (SPW) not initialized");
    }

    $em   = $spw->getEntityManager();
    $repo = $em->getRepository(GeneratorConfig::class);
    $queryEnricherConfig = $repo->findOneBy(['configId' => 'filter_query_enricher_v1']);

    if ($queryEnricherConfig) {
        $aiProvider = $spw->getAIProvider();

        if (!class_exists('App\Service\GeneratorService')) {
            throw new Exception("GeneratorService class missing");
        }

        $queryEnricherService = new GeneratorService(
            $aiProvider,
            new SchemaValidator(),
            new ResponseNormalizer(),
            $spw->getFileLogger()
        );
    } else {
        $enricherInitError = "Config 'filter_query_enricher_v1' not found in DB";
    }
} catch (Exception $e) {
    $enricherInitError    = $e->getMessage();
    $queryEnricherConfig  = null;
    $queryEnricherService = null;
}


// ============================================================
// HELPERS
// ============================================================

// Generate a simple run UUID
function run_uuid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
}

// SSE: send a data event
function sse_emit(array $payload): void {
    echo 'data: ' . json_encode($payload) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

// Build a search query string for a tag name.
function buildTagQuery(string $tagName): string {
    return $tagName;
}


// ============================================================
// ROUTING
// ============================================================
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// SSE run action needs special headers — handle before generic JSON header
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $body     = json_decode($rawInput, true) ?? [];
    $action   = $body['action'] ?? ($_POST['action'] ?? '');

    if ($action === 'run_autotag') {
        handleRunAutotag($pdo, $body);
        exit;
    }
}

header('Content-Type: application/json');


// ============================================================
// GET ACTIONS
// ============================================================
if ($method === 'GET') {
    try {
        switch ($action) {

            // ── Tags list (shared with taggerang) ────────────────
            case 'get_tags':
                $tags = $pdo->query("SELECT id, name FROM tags WHERE show_in_ui = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $tags]);
                break;

            // ── Document sources (keywords) ──────────────────────────
            case 'get_doc_sources':
                $docs = $pdo->query("
                    SELECT d.id, d.name 
                    FROM documentations d 
                    INNER JOIN md_doc_analysis mda ON d.id = mda.doc_id 
                    WHERE d.keywords IS NOT NULL AND d.keywords != '' 
                    ORDER BY d.name ASC
                ")->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $docs]);
                break;

            // ── Staged frames (paginated) ─────────────────────────
            case 'get_staged_frames':
                $page       = max(1, (int)($_GET['page']     ?? 1));
                $perPage    = max(1, (int)($_GET['per_page'] ?? 20));
                $reviewed   = isset($_GET['reviewed']) ? (int)$_GET['reviewed'] : 0;
                $offset     = ($page - 1) * $perPage;

                $countStmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT frame_id)
                    FROM tags_2_frames_staged
                    WHERE reviewed = ?
                ");
                $countStmt->execute([$reviewed]);
                $total      = (int)$countStmt->fetchColumn();
                $totalPages = max(1, (int)ceil($total / $perPage));

                $frameStmt = $pdo->prepare("
                    SELECT DISTINCT frame_id
                    FROM tags_2_frames_staged
                    WHERE reviewed = ?
                    ORDER BY frame_id DESC
                    LIMIT {$perPage} OFFSET {$offset}
                ");
                $frameStmt->execute([$reviewed]);
                $frameIds = $frameStmt->fetchAll(PDO::FETCH_COLUMN);

                if (empty($frameIds)) {
                    echo json_encode([
                        'status' => 'success',
                        'data'   => [],
                        'meta'   =>['current_page' => $page, 'total_pages' => $totalPages, 'total' => $total]
                    ]);
                    break;
                }

                $placeholders = implode(',', array_fill(0, count($frameIds), '?'));
                $frameData = $pdo->prepare("
                    SELECT f.id, f.filename, f.entity_type, f.entity_id
                    FROM frames f
                    WHERE f.id IN ($placeholders)
                ");
                $frameData->execute($frameIds);
                $framesById =[];
                foreach ($frameData->fetchAll(PDO::FETCH_ASSOC) as $f) {
                    $framesById[$f['id']] = $f;
                }

                $stagingStmt = $pdo->prepare("
                    SELECT s.id as staged_id, s.frame_id, s.tag_id, s.score, s.active, s.reviewed,
                           t.name as tag_name
                    FROM tags_2_frames_staged s
                    JOIN tags t ON t.id = s.tag_id
                    WHERE s.frame_id IN ($placeholders)
                    ORDER BY s.score DESC
                ");
                $stagingStmt->execute($frameIds);
                $proposalsByFrame =[];
                foreach ($stagingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $proposalsByFrame[$row['frame_id']][] = $row;
                }

                $rows =[];
                foreach ($frameIds as $fid) {
                    $f = $framesById[$fid] ?? null;
                    if (!$f) continue;
                    $proposals = $proposalsByFrame[$fid] ??[];
                    $isReviewed = !empty($proposals) && $proposals[0]['reviewed'];
                    $rows[] =[
                        'frame_id'    => (int)$fid,
                        'filename'    => $f['filename'],
                        'entity_type' => $f['entity_type'],
                        'entity_id'   => $f['entity_id'],
                        'reviewed'    => (bool)$isReviewed,
                        'proposals'   => array_map(fn($p) => [
                            'staged_id' => (int)$p['staged_id'],
                            'tag_id'    => (int)$p['tag_id'],
                            'tag_name'  => $p['tag_name'],
                            'score'     => round((float)$p['score'], 4),
                            'active'    => (bool)$p['active'],
                        ], $proposals)
                    ];
                }

                echo json_encode([
                    'status' => 'success',
                    'data'   => $rows,
                    'meta'   =>['current_page' => $page, 'total_pages' => $totalPages, 'total' => $total]
                ]);
                break;

            // ── Counts ───────────────────────────────────────────
            case 'staged_count':
                $total    = (int)$pdo->query("SELECT COUNT(DISTINCT frame_id) FROM tags_2_frames_staged")->fetchColumn();
                $reviewed = (int)$pdo->query("SELECT COUNT(DISTINCT frame_id) FROM tags_2_frames_staged WHERE reviewed = 1")->fetchColumn();
                echo json_encode([
                    'status'   => 'success',
                    'total'    => $total,
                    'reviewed' => $reviewed,
                    'pending'  => $total - $reviewed
                ]);
                break;

            // ── Map Runs (sketches) — for run modal ───────────────
            case 'get_map_runs':
                $limit  = max(1, min(50, (int)($_GET['limit']  ?? 15)));
                $offset = max(0, (int)($_GET['offset'] ?? 0));
                $search = trim($_GET['search'] ?? '');

                $where  = "WHERE EXISTS (
                    SELECT 1 FROM frames fi
                    WHERE fi.map_run_id = mr.id AND fi.entity_type = 'sketches'
                )";
                $params =[];

                if ($search !== '') {
                    if (is_numeric($search)) {
                        $where .= " AND mr.id = :sid";
                        $params['sid'] = (int)$search;
                    } else {
                        $where .= " AND mr.note LIKE :swild";
                        $params['swild'] = "%$search%";
                    }
                }

                $sql = "
                    SELECT mr.id, mr.created_at, mr.note,
                           COUNT(DISTINCT f.id) as frame_count
                    FROM map_runs mr
                    LEFT JOIN frames f ON f.map_run_id = mr.id AND f.entity_type = 'sketches'
                    $where
                    GROUP BY mr.id
                    ORDER BY mr.id DESC
                    LIMIT {$limit} OFFSET {$offset}
                ";
                $stmt = $pdo->prepare($sql);
                foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
                $stmt->execute();
                $runs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $countSql = "SELECT COUNT(*) FROM map_runs mr $where";
                $cStmt = $pdo->prepare($countSql);
                foreach ($params as $k => $v) { $cStmt->bindValue($k, $v); }
                $cStmt->execute();
                $totalCount = (int)$cStmt->fetchColumn();

                $data = array_map(fn($r) => [
                    'id'          => (int)$r['id'],
                    'created_at'  => date('M d Y H:i', strtotime($r['created_at'])),
                    'note'        => $r['note'] ?? '',
                    'frame_count' => (int)$r['frame_count'],
                ], $runs);

                echo json_encode([
                    'status'   => 'success',
                    'data'     => $data,
                    'has_more' => ($offset + $limit) < $totalCount,
                    'total'    => $totalCount,
                ]);
                break;

            // ── Map Run Frames — frames for a single run ──────────
            case 'get_map_run_frames':
                $mapRunId = (int)($_GET['map_run_id'] ?? 0);
                if (!$mapRunId) {
                    echo json_encode(['status' => 'error', 'message' => 'map_run_id required']);
                    break;
                }

                $stmt = $pdo->prepare("
                    SELECT f.id as frame_id, f.filename, f.entity_id,
                           f.entity_type, s.name as sketch_name,
                           f2s.to_id as sketch_id
                    FROM frames f
                    LEFT JOIN frames_2_sketches f2s ON f2s.from_id = f.id
                    LEFT JOIN sketches s ON s.id = f2s.to_id
                    WHERE f.map_run_id = ?
                    ORDER BY f.id ASC
                ");
                $stmt->execute([$mapRunId]);
                $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $data = array_map(fn($f) => [
                    'frame_id'    => (int)$f['frame_id'],
                    'filename'    => $f['filename'],
                    'entity_id'   => (int)($f['entity_id'] ?? 0),
                    'entity_type' => $f['entity_type'] ?? 'sketches',
                    'sketch_id'   => $f['sketch_id'] ? (int)$f['sketch_id'] : null,
                    'sketch_name' => $f['sketch_name'] ?? null,
                ], $frames);

                echo json_encode(['status' => 'success', 'data' => $data]);
                break;

            // ── Narrative Docs — for Narratives Filter tab ────────
            // Returns docs that have narrative_utility set, ordered by
            // narrative_utility DESC — same query as narratives.php uses
            // for its context doc dropdown.
            case 'get_narrative_docs':
                $docs = $pdo->query("
                    SELECT d.id, d.name
                    FROM documentations d
                    JOIN md_doc_analysis da ON d.id = da.doc_id
                    WHERE da.narrative_utility IS NOT NULL
                    ORDER BY da.narrative_utility DESC
                ")->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $docs]);
                break;

            default:
                echo json_encode(['status' => 'error', 'message' => "Unknown action: $action"]);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}


// ============================================================
// POST ACTIONS (non-streaming)
// ============================================================
if ($method === 'POST') {
    try {
        switch ($action) {

            // ── Toggle active on a staged proposal ───────────────
            case 'toggle_staged':
                $stagedId = (int)($body['staged_id'] ?? 0);
                $active   = (int)($body['active']    ?? 0);
                if (!$stagedId) { echo json_encode(['status' => 'error', 'message' => 'staged_id required']); exit; }
                $pdo->prepare("UPDATE tags_2_frames_staged SET active = ? WHERE id = ?")
                    ->execute([$active, $stagedId]);
                echo json_encode(['status' => 'success']);
                break;

            // ── Mark frame reviewed / unreviewed ─────────────────
            case 'set_reviewed':
                $frameId  = (int)($body['frame_id'] ?? 0);
                $reviewed = (int)($body['reviewed'] ?? 0);
                if (!$frameId) { echo json_encode(['status' => 'error', 'message' => 'frame_id required']); exit; }
                $pdo->prepare("UPDATE tags_2_frames_staged SET reviewed = ? WHERE frame_id = ?")
                    ->execute([$reviewed, $frameId]);
                echo json_encode(['status' => 'success']);
                break;

            // ── Persist approved staging rows → tags_2_frames ─────
            // 1. INSERT active+reviewed rows into the live table (dupes ignored)
            // 2. DELETE all reviewed rows from staging (they've been decided on —
            //    active ones are now live, inactive ones were consciously rejected)
            case 'persist_staged':
                $insert = $pdo->prepare("
                    INSERT IGNORE INTO tags_2_frames (from_id, to_id)
                    SELECT tag_id, frame_id
                    FROM tags_2_frames_staged
                    WHERE reviewed = 1 AND active = 1
                ");
                $insert->execute();
                $written = $insert->rowCount();

                // Clean up staging — remove all reviewed rows (both active and inactive).
                // Active ones are now safely in tags_2_frames; inactive ones were rejected.
                $pdo->prepare("
                    DELETE FROM tags_2_frames_staged
                    WHERE reviewed = 1
                ")->execute();

                echo json_encode(['status' => 'success', 'written' => $written]);
                break;

            // ── Resolve narrative frames for gallery preview ──────
            // Runs the same sketch/frame resolution as the narratives run mode
            // but returns the frame list WITHOUT running vector scoring.
            // Used by the two-step gallery preview in the UI.
            case 'resolve_narrative_frames':
                $narrativesDocId  = !empty($body['narratives_doc_id']) ? (int)$body['narratives_doc_id'] : null;
                $narrativesFilter = !empty($body['narratives_filter'])  ? $body['narratives_filter']       : null;

                if (!$narrativesDocId || !$narrativesFilter) {
                    echo json_encode(['status' => 'error', 'message' => 'narratives_doc_id and narratives_filter required']);
                    exit;
                }

                global $queryEnricherSeriesBible, $queryEnricherKeywords;
                $queryEnricherSeriesBible = '';
                $queryEnricherKeywords    = '';
                $bibleStmt2 = $pdo->prepare("
                    SELECT COALESCE(LEFT(d.desc_short, 800), LEFT(mda.series_bible, 800)) as bible,
                           d.keywords
                    FROM md_doc_analysis mda
                    JOIN documentations d ON d.id = mda.doc_id
                    WHERE mda.doc_id = ?
                ");
                $bibleStmt2->execute([$narrativesDocId]);
                $bibleRow2 = $bibleStmt2->fetch(PDO::FETCH_ASSOC);
                if ($bibleRow2) {
                    $queryEnricherSeriesBible = $bibleRow2['bible']    ?? '';
                    $queryEnricherKeywords    = $bibleRow2['keywords'] ?? '';
                }

                $debugLog2    =[];
                $queryResult2 = buildRichQuery($pdo, $narrativesDocId, $narrativesFilter, $debugLog2);

                $engine2 = new VectorContextEngine($pdo);
                if (is_array($queryResult2)) {
                    $rankedSketches2 = $engine2->getRankedItemsMulti($narrativesDocId, $queryResult2);
                } else {
                    $rankedSketches2 = $engine2->getRankedItems($narrativesDocId, $queryResult2);
                }

                $sketchIds2 = array_column($rankedSketches2, 'id');

                if (empty($sketchIds2)) {
                    echo json_encode(['status' => 'success', 'data' => [], 'message' => 'No sketches matched the filter']);
                    exit;
                }

                $sph2 = implode(',', array_fill(0, count($sketchIds2), '?'));
                // Use ORDER BY FIELD to preserve the ranking order from vector search
                $orderStr = implode(',', array_map('intval', $sketchIds2));
                
                $frameStmt2 = $pdo->prepare("
                    SELECT f.id as frame_id, f.filename, f.entity_type, f.entity_id,
                           s.name as sketch_name, f2s.to_id as sketch_id
                    FROM frames f
                    JOIN frames_2_sketches f2s ON f2s.from_id = f.id
                    LEFT JOIN sketches s ON s.id = f2s.to_id
                    WHERE f2s.to_id IN ($sph2)
                    ORDER BY FIELD(f2s.to_id, $orderStr), f.id DESC
                    LIMIT 100
                ");
                $frameStmt2->execute($sketchIds2);
                $frames2 = $frameStmt2->fetchAll(PDO::FETCH_ASSOC);

                $data2 = array_map(fn($f) =>[
                    'frame_id'    => (int)$f['frame_id'],
                    'filename'    => $f['filename'],
                    'entity_id'   => (int)($f['entity_id'] ?? 0),
                    'entity_type' => $f['entity_type'] ?? 'sketches',
                    'sketch_id'   => $f['sketch_id'] ? (int)$f['sketch_id'] : null,
                    'sketch_name' => $f['sketch_name'] ?? null,
                ], $frames2);

                echo json_encode(['status' => 'success', 'data' => $data2]);
                break;

            // ── Apply document keywords ───────────────────────────
            case 'apply_doc_keywords':
                $docId = (int)($body['doc_id'] ?? 0);
                if (!$docId) { echo json_encode(['status' => 'error', 'message' => 'doc_id required']); exit; }

                $stmt = $pdo->prepare("SELECT d.keywords FROM documentations d INNER JOIN md_doc_analysis mda ON d.id = mda.doc_id WHERE d.id = ?");
                $stmt->execute([$docId]);
                $keywordsStr = $stmt->fetchColumn();

                if ($keywordsStr) {
                    // Hide all tags from UI first
                    $pdo->exec("UPDATE tags SET show_in_ui = 0, updated_at = NOW()");
                    
                    $kwArray = array_map('trim', explode(',', $keywordsStr));
                    $kwArray = array_filter($kwArray);
                    
                    $upsert = $pdo->prepare("INSERT INTO tags (name, show_in_ui) VALUES (?, 1) ON DUPLICATE KEY UPDATE show_in_ui = 1, updated_at = NOW()");
                    foreach ($kwArray as $kw) {
                        $upsert->execute([$kw]);
                    }
                }
                
                $tags = $pdo->query("SELECT id, name FROM tags WHERE show_in_ui = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $tags]);
                break;

            // ── Hide single tag from UI (sets show_in_ui=0) ──────
            case 'hide_tag':
                $tagId = (int)($body['tag_id'] ?? 0);
                if (!$tagId) { echo json_encode(['status' => 'error', 'message' => 'tag_id required']); exit; }
                $pdo->prepare("UPDATE tags SET show_in_ui = 0, updated_at = NOW() WHERE id = ?")->execute([$tagId]);
                echo json_encode(['status' => 'success']);
                break;

            // ── Hide all tags from UI ─────────────────────────────
            case 'hide_all_tags':
                $pdo->exec("UPDATE tags SET show_in_ui = 0, updated_at = NOW()");
                echo json_encode(['status' => 'success', 'data' => []]);
                break;

            // ── Save / upsert tag definitions ─────────────────────
            case 'save_tag_defs':
                $tagsInput = $body['tags'] ??[];
                if (!is_array($tagsInput)) { echo json_encode(['status' => 'error', 'message' => 'tags must be array']); exit; }
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
                echo json_encode(['status' => 'success', 'data' => $tags]);
                break;

            default:
                echo json_encode(['status' => 'error', 'message' => "Unknown POST action: $action"]);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}


// ============================================================
// STREAMING: run_autotag
// Scores frames against tags using vector search, writes
// proposals to tags_2_frames_staged, streams SSE log.
//
// Supports three modes:
//   Frame range mode:    from_id, to_id  (or null = all frames)
//   Map run mode:        map_run_id, frame_ids[], tag_all_frames_of_sketch
//   Narratives filter:   narratives_doc_id, narratives_filter
//                          {text, items:[{cat,name}]}
//
// tag_all_frames_of_sketch: when true, for each marked frame we
//   resolve its linked sketch, then include ALL frames of that
//   sketch from ANY map run in the scoring target set.
//   When false, only the explicitly marked frame_ids are scored.
//
// use_explicit_frame_ids: when true (narratives mode only), skips
//   vector re-resolution and uses the provided frame_ids directly.
//   Intended for use after gallery preview has already filtered frames.
// ============================================================
function handleRunAutotag(PDO $pdo, array $body): void {
    // SSE headers
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    if (ob_get_level()) ob_end_clean();
    ob_implicit_flush(true);

    $tagIds         = array_filter(array_map('intval', (array)($body['tag_ids']            ?? [])));
    $threshold      = isset($body['threshold'])         ? (float)$body['threshold']      : 0.70;
    $maxPerFrame    = isset($body['max_tags_per_frame']) ? (int)$body['max_tags_per_frame']: 5;
    $runId          = run_uuid();
    $batchSize      = 50;

    // ── Determine target frame set ────────────────────────────
    $mapRunId              = !empty($body['map_run_id'])    ? (int)$body['map_run_id']    : null;
    $explicitFrameIds      = !empty($body['frame_ids'])     ? array_filter(array_map('intval', (array)$body['frame_ids'])) : [];
    $tagAllFramesOfSketch  = !empty($body['tag_all_frames_of_sketch']);
    $skipValidation        = !empty($body['skip_validation']);

    // Narratives filter mode
    $narrativesDocId  = !empty($body['narratives_doc_id'])  ? (int)$body['narratives_doc_id']  : null;
    $narrativesFilter = !empty($body['narratives_filter'])   ? $body['narratives_filter']        : null;

    // Frame range mode (classic)
    $fromId = !empty($body['from_id']) ? (int)$body['from_id'] : null;
    $toId   = !empty($body['to_id'])   ? (int)$body['to_id']   : null;

    if (empty($tagIds)) {
        sse_emit(['type' => 'error', 'message' => 'No tag IDs provided']);
        return;
    }

    // Load tag definitions
    $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
    $tagStmt = $pdo->prepare("SELECT id, name FROM tags WHERE id IN ($placeholders)");
    $tagStmt->execute($tagIds);
    $tags = $tagStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($tags)) {
        sse_emit(['type' => 'error', 'message' => 'None of the tag IDs found in DB']);
        return;
    }

    $tagQueries =[];
    foreach ($tags as $tag) {
        $tagQueries[$tag['id']] = buildTagQuery($tag['name']);
    }

    sse_emit(['type' => 'batch', 'batch' => 0, 'count' => count($tags),
              'message' => 'Tags loaded: ' . implode(', ', array_column($tags, 'name'))]);

    // ── Resolve target frames ─────────────────────────────────
    $allFrames =[];

    if ($mapRunId !== null && !empty($explicitFrameIds)) {
        // ── Map run mode ─────────────────────────────────────────
        if ($tagAllFramesOfSketch) {
            $ph = implode(',', array_fill(0, count($explicitFrameIds), '?'));
            $sketchStmt = $pdo->prepare("
                SELECT DISTINCT f2s.to_id as sketch_id
                FROM frames_2_sketches f2s
                WHERE f2s.from_id IN ($ph)
            ");
            $sketchStmt->execute($explicitFrameIds);
            $sketchIds = $sketchStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($sketchIds)) {
                $sph = implode(',', array_fill(0, count($sketchIds), '?'));
                $frameStmt = $pdo->prepare("
                    SELECT DISTINCT f.id, f.filename, f.entity_type, f.entity_id
                    FROM frames f
                    JOIN frames_2_sketches f2s ON f2s.from_id = f.id
                    WHERE f2s.to_id IN ($sph)
                    ORDER BY f.id DESC
                ");
                $frameStmt->execute($sketchIds);
                $allFrames = $frameStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            sse_emit(['type' => 'batch', 'batch' => 0, 'count' => count($allFrames),
                      'message' => 'Resolved ' . count($sketchIds) . ' sketch(es) → ' . count($allFrames) . ' frames (all frames of sketch mode)']);
        } else {
            $ph = implode(',', array_fill(0, count($explicitFrameIds), '?'));
            $frameStmt = $pdo->prepare("
                SELECT id, filename, entity_type, entity_id
                FROM frames
                WHERE id IN ($ph)
                ORDER BY id DESC
            ");
            $frameStmt->execute($explicitFrameIds);
            $allFrames = $frameStmt->fetchAll(PDO::FETCH_ASSOC);

            sse_emit(['type' => 'batch', 'batch' => 0, 'count' => count($allFrames),
                      'message' => 'Map run mode — ' . count($allFrames) . ' explicitly marked frames']);
        }

    } elseif ($narrativesDocId !== null && $narrativesFilter !== null) {
        // ── Narratives Filter mode ────────────────────────────────────────────

        // SHORT-CIRCUIT: gallery-preview already resolved & filtered frames;
        // skip vector resolution entirely and go straight to scoring.
        if (!empty($body['use_explicit_frame_ids']) && !empty($explicitFrameIds)) {
            $ph = implode(',', array_fill(0, count($explicitFrameIds), '?'));
            $frameStmt = $pdo->prepare("
                SELECT id, filename, entity_type, entity_id
                FROM frames
                WHERE id IN ($ph)
                ORDER BY id DESC
            ");
            $frameStmt->execute($explicitFrameIds);
            $allFrames = $frameStmt->fetchAll(PDO::FETCH_ASSOC);

            sse_emit(['type' => 'batch', 'batch' => 0, 'count' => count($allFrames),
                      'message' => 'Gallery-preview mode — ' . count($allFrames) . ' pre-selected frames']);

            goto narratives_frames_resolved;
        }

        $debugLog =[];
        global $queryEnricherSeriesBible, $queryEnricherKeywords;
        $queryEnricherSeriesBible = '';
        $queryEnricherKeywords    = '';

        // Load world context (series bible + keywords) for the selected doc
        $bibleStmt = $pdo->prepare("
            SELECT COALESCE(LEFT(d.desc_short, 800), LEFT(mda.series_bible, 800)) as bible,
                   d.keywords
            FROM md_doc_analysis mda
            JOIN documentations d ON d.id = mda.doc_id
            WHERE mda.doc_id = ?
        ");
        $bibleStmt->execute([$narrativesDocId]);
        $bibleRow = $bibleStmt->fetch(PDO::FETCH_ASSOC);
        if ($bibleRow) {
            $queryEnricherSeriesBible = $bibleRow['bible']    ?? '';
            $queryEnricherKeywords    = $bibleRow['keywords'] ?? '';
        }

        // Build the lore-enriched query string (AI-enriched via filter_query_enricher_v1)
        $queryResult = buildRichQuery($pdo, $narrativesDocId, $narrativesFilter, $debugLog);

        // Run vector search to get ranked sketch IDs
        $engine = new VectorContextEngine($pdo);
        if (is_array($queryResult)) {
            $rankedSketches = $engine->getRankedItemsMulti($narrativesDocId, $queryResult);
        } else {
            $rankedSketches = $engine->getRankedItems($narrativesDocId, $queryResult);
        }

        $sketchIds = array_column($rankedSketches, 'id');

        if (empty($sketchIds)) {
            sse_emit(['type' => 'error', 'message' => 'Narratives filter returned no sketches — try broader filter']);
            return;
        }

        // Resolve sketch IDs → frame IDs via frames_2_sketches
        $sph = implode(',', array_fill(0, count($sketchIds), '?'));
        $frameStmt = $pdo->prepare("
            SELECT f.id, f.filename, f.entity_type, f.entity_id
            FROM frames f
            JOIN frames_2_sketches f2s ON f2s.from_id = f.id
            WHERE f2s.to_id IN ($sph)
            ORDER BY f.id DESC
        ");
        $frameStmt->execute($sketchIds);
        $allFrames = $frameStmt->fetchAll(PDO::FETCH_ASSOC);

        sse_emit(['type' => 'batch', 'batch' => 1, 'count' => count($allFrames),
            'message' => 'Narratives filter: ' . count($sketchIds) . ' sketches → ' . count($allFrames) . ' frames resolved']);

        narratives_frames_resolved:
    } else {
        // ── Classic frame range mode ──────────────────────────────
        $frameSql    = "SELECT id, filename, entity_type, entity_id FROM frames WHERE 1=1";
        $frameParams =[];
        if ($fromId) { $frameSql .= " AND id >= ?"; $frameParams[] = $fromId; }
        if ($toId)   { $frameSql .= " AND id <= ?"; $frameParams[] = $toId; }
        $frameSql .= " ORDER BY id DESC";

        $frameStmt = $pdo->prepare($frameSql);
        $frameStmt->execute($frameParams);
        $allFrames = $frameStmt->fetchAll(PDO::FETCH_ASSOC);

        sse_emit(['type' => 'batch', 'batch' => 1, 'count' => count($allFrames),
                  'message' => count($allFrames) . ' frames in range']);
    }

    if (empty($allFrames)) {
        sse_emit(['type' => 'error', 'message' => 'No frames found for the given parameters']);
        return;
    }

    sse_emit(['type' => 'batch', 'batch' => 1, 'count' => count($allFrames),
              'message' => count($allFrames) . ' frames to process']);

    // ── Skip validation fast path ─────────────────────────────
    if ($skipValidation) {
        sse_emit(['type' => 'batch', 'batch' => 2, 'count' => count($allFrames),
                  'message' => 'Skip-validation mode — writing all frames at score 1.0']);

        $insertStaged = $pdo->prepare("
            INSERT INTO tags_2_frames_staged (tag_id, frame_id, score, active, reviewed, run_id)
            VALUES (?, ?, 1.0, 1, 0, ?)
            ON DUPLICATE KEY UPDATE score = GREATEST(score, 1.0), run_id = VALUES(run_id)
        ");

        foreach ($allFrames as $frame) {
            $fid = (int)$frame['id'];
            $written = 0;
            foreach ($tags as $tag) {
                try {
                    $insertStaged->execute([$tag['id'], $fid, $runId]);
                    $written++;
                } catch (Exception $e) { /* skip dupes */ }
            }
            sse_emit(['type' => 'frame', 'frame_id' => $fid, 'proposals' => $written]);
        }

        sse_emit(['type' => 'done', 'run_id' => $runId]);
        return;
    }

    // ── Vector scoring ─────────────────────────────────────────
    $engine = new VectorContextEngine($pdo);
    $frameScores = []; // frame_id => [tag_id => score]

    $tagCount = count($tags);
    $t = 0;
    foreach ($tags as $tag) {
        $t++;
        sse_emit(['type' => 'batch', 'batch' => 1, 'count' => $tagCount,
                  'message' => "Scoring tag $t/$tagCount: {$tag['name']}"]);

        try {
            $rankedSketches = $engine->getRankedItems(null, $tagQueries[$tag['id']], 2000);
            if (empty($rankedSketches)) continue;

            $sketchScores = [];
            foreach ($rankedSketches as $rs) {
                $sid   = (int)($rs['id'] ?? $rs['sketch_id'] ?? 0);
                $score = (float)($rs['score'] ?? 0);
                if ($sid > 0) $sketchScores[$sid] = $score;
            }
            if (empty($sketchScores)) continue;

            $sketchIds = array_keys($sketchScores);
            $sph       = implode(',', array_fill(0, count($sketchIds), '?'));
            $fmapStmt  = $pdo->prepare("SELECT from_id as frame_id, to_id as sketch_id FROM frames_2_sketches WHERE to_id IN ($sph)");
            $fmapStmt->execute($sketchIds);

            foreach ($fmapStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $fid   = (int)$row['frame_id'];
                $score = $sketchScores[(int)$row['sketch_id']] ?? 0;
                if (!isset($frameScores[$fid])) $frameScores[$fid] = [];
                if (!isset($frameScores[$fid][$tag['id']]) || $score > $frameScores[$fid][$tag['id']]) {
                    $frameScores[$fid][$tag['id']] = $score;
                }
            }
        } catch (Exception $e) {
            sse_emit(['type' => 'error', 'message' => "Tag {$tag['name']}: " . $e->getMessage()]);
        }
    }

    sse_emit(['type' => 'batch', 'batch' => 2, 'count' => count($frameScores),
              'message' => 'Scoring complete. Writing proposals...']);

    // ── Write staging rows ─────────────────────────────────────
    $insertStaged = $pdo->prepare("
        INSERT INTO tags_2_frames_staged (tag_id, frame_id, score, active, reviewed, run_id)
        VALUES (?, ?, ?, 1, 0, ?)
        ON DUPLICATE KEY UPDATE score = GREATEST(score, VALUES(score)), run_id = VALUES(run_id)
    ");

    $batchNum    = 0;
    $frameChunks = array_chunk($allFrames, $batchSize);

    foreach ($frameChunks as $chunk) {
        $batchNum++;
        sse_emit(['type' => 'batch', 'batch' => $batchNum + 2, 'count' => count($chunk),
                  'message' => "Writing batch $batchNum (" . count($chunk) . " frames)"]);

        foreach ($chunk as $frame) {
            $fid       = (int)$frame['id'];
            $tagScores = $frameScores[$fid] ??[];

            $qualified = array_filter($tagScores, fn($s) => $s >= $threshold);
            arsort($qualified);
            $qualified = array_slice($qualified, 0, $maxPerFrame, true);

            foreach ($qualified as $tagId => $score) {
                try {
                    $insertStaged->execute([$tagId, $fid, $score, $runId]);
                } catch (Exception $e) {
                    // Skip duplicates / constraint violations silently
                }
            }

            sse_emit(['type' => 'frame', 'frame_id' => $fid, 'proposals' => count($qualified)]);
        }
    }

    sse_emit(['type' => 'done', 'run_id' => $runId]);
}


