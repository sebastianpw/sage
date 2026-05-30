<?php
// public/cli_forge.php
// =============================================================================
// CLI FORGE HUB
// Unified interface for all seven CLI pipeline forge sub-views.
// Each sub-view: configuration form + live JSON preview + job queue monitor.
// Job queue uses the forge_jobs table (see forge_hub_queue.sql).
// PHP reads/writes rows only — never alters schema.
// =============================================================================

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;

$em   = $spw->getEntityManager();
$conn = $em->getConnection();
$repo = $em->getRepository(GeneratorConfig::class);

// ─── Nav layout constants ────────────────────────────────────────────────────
// Controls the mobile sidebar icon strip.
// NAV_ROWS:         number of rows in the horizontal icon bar (mobile view)
// NAV_COLS_PER_ROW: max icons per row; 0 = let CSS flex-wrap handle it
define('NAV_ROWS',         2);
define('NAV_COLS_PER_ROW', 0);

// ─── Helpers shared across sub-views ────────────────────────────────────────

function cfe(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function normalizeTags($tags): array {
    if (is_string($tags)) $tags = array_map('trim', explode(',', $tags));
    if (!is_array($tags)) return [];
    return array_values(array_unique(array_filter(array_map('trim', $tags))));
}

function getKgCatFamily($conn, int $topCatId): array {
    if ($topCatId <= 0) return [];
    $all = $conn->fetchAllAssociative("SELECT id, parent_id FROM kg_categories");
    $map = [];
    foreach ($all as $c) $map[(int)($c['parent_id'] ?? 0)][] = (int)$c['id'];
    $family = [$topCatId];
    $queue  = [$topCatId];
    while (!empty($queue)) {
        $curr = array_shift($queue);
        foreach ($map[$curr] ?? [] as $child) { $family[] = $child; $queue[] = $child; }
    }
    return array_values(array_unique(array_map('intval', $family)));
}

function getHistCounts($conn, array $names, string $type): array {
    $names = array_values(array_filter(array_map('strval', $names)));
    if (empty($names)) return ['new' => 0, 'hist' => 0];
    $ph = implode(',', array_fill(0, count($names), '?'));
    $hist = (int)$conn->fetchOne(
        "SELECT COUNT(DISTINCT entity_name) FROM sketch_lore_history WHERE entity_name IN ($ph) AND entity_type = ?",
        array_merge($names, [$type])
    );
    return ['hist' => $hist, 'new' => count($names) - $hist];
}

// ─── Job queue helpers ───────────────────────────────────────────────────────

function queueJob($conn, string $jobType, string $label, array $payload, int $priority = 50, ?int $userId = null): int {
    $conn->executeStatement(
        "INSERT INTO forge_jobs (job_type, label, status, priority, payload, created_by, created_at, updated_at)
         VALUES (?, ?, 'pending', ?, ?, ?, NOW(), NOW())",
        [$jobType, $label, $priority, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $userId]
    );
    return (int)$conn->lastInsertId();
}

function getJobs($conn, string $jobType, int $page = 1, int $perPage = 20): array {
    $offset = ($page - 1) * $perPage;
    // Hardcoding limit and offset prevents DBAL binding them as strings and causing MySQL syntax errors
    $sql = "SELECT id, label, status, priority, created_at, updated_at, started_at, finished_at, error_msg,
                LEFT(payload, 200) AS payload_preview
         FROM forge_jobs WHERE job_type = ? ORDER BY priority ASC, id DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    $rows = $conn->fetchAllAssociative($sql, [$jobType]);
    $total = (int)$conn->fetchOne("SELECT COUNT(*) FROM forge_jobs WHERE job_type = ?", [$jobType]);
    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
}

function getJobCounts($conn, string $jobType): array {
    $rows = $conn->fetchAllAssociative(
        "SELECT status, COUNT(*) AS cnt FROM forge_jobs WHERE job_type = ? GROUP BY status",
        [$jobType]
    );
    $out = ['pending' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0, 'cancelled' => 0];
    foreach ($rows as $r) if (isset($out[$r['status']])) $out[$r['status']] = (int)$r['cnt'];
    return $out;
}

// ─── AJAX / action handlers ──────────────────────────────────────────────────

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action !== '') {
    header('Content-Type: application/json; charset=utf-8');

    // ── Queue monitor (paginated list) ────────────────────────────────────
    if ($action === 'queue_list') {
        $jobType = trim($_GET['job_type'] ?? '');
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $data    = getJobs($conn, $jobType, $page);
        $counts  = getJobCounts($conn, $jobType);
        echo json_encode(['ok' => true, 'data' => $data, 'counts' => $counts], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Queue a new job ───────────────────────────────────────────────────
    if ($action === 'queue_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $jobType  = trim($_POST['job_type'] ?? '');
        $label    = trim($_POST['label'] ?? '');
        $priority = max(1, min(99, (int)($_POST['priority'] ?? 50)));
        $rawJson  = $_POST['payload'] ?? '';
        $payload  = json_decode($rawJson, true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid payload JSON.']);
            exit;
        }
        if (empty($label)) $label = ucfirst(str_replace('_', ' ', $jobType)) . ' — ' . date('Y-m-d H:i');
        $userId = $_SESSION['user_id'] ?? null;
        $newId  = queueJob($conn, $jobType, $label, $payload, $priority, $userId);
        echo json_encode(['ok' => true, 'job_id' => $newId, 'message' => "Job #$newId queued."]);
        exit;
    }

    // ── Cancel / archive a job ────────────────────────────────────────────
    if ($action === 'cancel_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->executeStatement(
            "UPDATE forge_jobs SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND status = 'pending'",
            [$id]
        );
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Raise / lower priority ────────────────────────────────────────────
    if ($action === 'set_priority' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id  = (int)($_POST['id'] ?? 0);
        $pri = max(1, min(99, (int)($_POST['priority'] ?? 50)));
        $conn->executeStatement(
            "UPDATE forge_jobs SET priority = ?, updated_at = NOW() WHERE id = ? AND status = 'pending'",
            [$pri, $id]
        );
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Delete (hard) a cancelled/done/failed job ─────────────────────────
    if ($action === 'delete_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->executeStatement(
            "DELETE FROM forge_jobs WHERE id = ? AND status IN ('cancelled','done','failed')",
            [$id]
        );
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Re-queue a failed job ─────────────────────────────────────────────
    if ($action === 'requeue_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->executeStatement(
            "UPDATE forge_jobs SET status = 'pending', error_msg = NULL, started_at = NULL, finished_at = NULL, updated_at = NOW() WHERE id = ? AND status = 'failed'",
            [$id]
        );
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── KG meta ───────────────────────────────────────────────────────────
    if ($action === 'kg_meta') {
        $mode       = (int)($_GET['mode'] ?? 1);
        $categoryId = (int)($_GET['category_id'] ?? 0);

        $cats = $conn->fetchAllAssociative("SELECT id, name FROM kg_categories ORDER BY name ASC");
        $catsOut = [];
        foreach ($cats as $cat) {
            $catId  = (int)$cat['id'];
            $family = getKgCatFamily($conn, $catId);
            $famSql = implode(',', array_map('intval', $family));
            $total  = (int)$conn->fetchOne("SELECT COUNT(*) FROM kg_nodes WHERE status = 'active' AND category_id IN ($famSql)");
            $names  = $conn->fetchFirstColumn("SELECT name FROM kg_nodes WHERE status = 'active' AND category_id IN ($famSql)");
            $hist   = empty($names) ? 0 : (int)$conn->fetchOne(
                "SELECT COUNT(DISTINCT entity_name) FROM sketch_lore_history WHERE entity_name IN (" . implode(',', array_fill(0, count($names), '?')) . ")", $names
            );
            $catsOut[] = ['id' => $catId, 'name' => $cat['name'], 'total' => $total, 'new' => max(0, $total - $hist), 'hist' => $hist];
        }

        if ($mode === 1 && $categoryId > 0) {
            $family    = getKgCatFamily($conn, $categoryId);
            $familySql = implode(',', array_map('intval', $family));
            $types     = $conn->fetchAllAssociative("SELECT node_type, COUNT(*) as cnt FROM kg_nodes WHERE status='active' AND category_id IN ($familySql) GROUP BY node_type ORDER BY cnt DESC");
        } else {
            $types = $conn->fetchAllAssociative("SELECT node_type, COUNT(*) as cnt FROM kg_nodes WHERE status='active' GROUP BY node_type ORDER BY cnt DESC");
        }

        $typeRows = [];
        foreach ($types as $t) {
            $nt    = (string)$t['node_type'];
            $names = ($mode === 1 && $categoryId > 0)
                ? $conn->fetchFirstColumn("SELECT name FROM kg_nodes WHERE status='active' AND category_id IN (" . implode(',', array_map('intval', getKgCatFamily($conn, $categoryId))) . ") AND node_type = ?", [$nt])
                : $conn->fetchFirstColumn("SELECT name FROM kg_nodes WHERE status='active' AND node_type = ?", [$nt]);
            $hc = getHistCounts($conn, $names ?: [], $nt);
            $typeRows[] = ['node_type' => $nt, 'count' => (int)$t['cnt'], 'new' => $hc['new'], 'hist' => $hc['hist']];
        }

        $configs = $conn->fetchAllAssociative("SELECT config_id, title FROM generator_config WHERE active = 1 ORDER BY title ASC");
        echo json_encode(['ok' => true, 'categories' => $catsOut, 'types' => $typeRows, 'configs' => $configs], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Lore meta ─────────────────────────────────────────────────────────
    if ($action === 'lore_meta') {
        $docId = (int)($_GET['doc_id'] ?? 0);
        $docs  = $conn->fetchAllAssociative("
            SELECT d.id, d.name, d.keywords, c.name AS cat_name
            FROM documentations d
            JOIN md_doc_analysis da ON d.id = da.doc_id
            LEFT JOIN documentation_categories c ON d.category_id = c.id
            WHERE d.is_active = 1 ORDER BY d.updated_at DESC");

        $groups = [];
        if ($docId > 0) {
            try {
                $lore = new \App\Service\LoreAccessService($spw->getPdo());
                $lore->loadDoc($docId);
                $se   = $lore->getStoryEngine();
                foreach (['characters','locations','factions','artifacts','episodes','scene_hooks'] as $cat) {
                    $cnt = in_array($cat, ['episodes','scene_hooks'], true) ? count($se[$cat] ?? []) : count($lore->queryEntities($cat));
                    if ($cnt > 0) $groups[] = ['key' => $cat, 'count' => $cnt];
                }
            } catch (\Throwable $e) { $groups = []; }
        }
        $configs = $conn->fetchAllAssociative("SELECT config_id, title FROM generator_config WHERE active = 1 ORDER BY title ASC");
        echo json_encode(['ok' => true, 'docs' => $docs, 'groups' => $groups, 'configs' => $configs], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Autopilot meta ────────────────────────────────────────────────────
    if ($action === 'autopilot_meta') {
        $sql  = "SELECT g.id, g.title, g.model FROM generator_config g
                 JOIN generator_config_to_display_area map ON g.id = map.generator_config_id
                 JOIN generator_config_display_area da ON map.display_area_id = da.id
                 WHERE da.area_key = 'rapidcreate' AND g.active = 1 ORDER BY g.title ASC";
        $gens = $conn->fetchAllAssociative($sql) ?: [];
        $ingredients = [
            ['key'=>'template',    'label'=>'Sketch Templates',    'default'=>85],
            ['key'=>'interaction', 'label'=>'Interactions',        'default'=>60],
            ['key'=>'style',       'label'=>'Style Profiles',      'default'=>50],
            ['key'=>'anivoc',      'label'=>'Anime Visual Vocab',  'default'=>70],
            ['key'=>'character',   'label'=>'Characters',          'default'=>40],
            ['key'=>'location',    'label'=>'Locations',           'default'=>30],
            ['key'=>'vehicle',     'label'=>'Vehicles',            'default'=>20],
            ['key'=>'artifact',    'label'=>'Artifacts',           'default'=>15],
        ];
        echo json_encode(['ok' => true, 'generators' => $gens, 'ingredients' => $ingredients], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── MD Curator meta ───────────────────────────────────────────────────
    if ($action === 'curator_meta') {
        $cats = $conn->fetchAllAssociative("SELECT id, name FROM documentation_categories ORDER BY name ASC") ?: [];
        $curatorCfg    = $repo->findOneBy(['configId' => 'md_curator_v1']);
        $showrunnerCfg = $repo->findOneBy(['configId' => 'md_curator_showrunner_v1']);
        echo json_encode([
            'ok' => true,
            'categories' => $cats,
            'curator_title'     => $curatorCfg    ? $curatorCfg->getTitle()    : 'md_curator_v1',
            'showrunner_title'  => $showrunnerCfg ? $showrunnerCfg->getTitle() : 'md_curator_showrunner_v1',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Narrative Sequence meta ───────────────────────────────────────────
    if ($action === 'narseq_meta') {
        $seqs = $conn->fetchAllAssociative(
            "SELECT id, name FROM narrative_sequences ORDER BY id DESC LIMIT 200"
        ) ?: [];
        echo json_encode(['ok' => true, 'sequences' => $seqs], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    
    
    

    
    
       // ── Overlay meta ──────────────────────────────────────────────────────
    if ($action === 'overlay_meta') {
        $cinemagics = $conn->fetchAllAssociative(
            "SELECT id, name FROM cinemagics ORDER BY id ASC"
        );
        $sequences = $conn->fetchAllAssociative(
            "SELECT id, name FROM narrative_sequences ORDER BY id ASC"
        );
        echo json_encode([
            'ok'         => true,
            'cinemagics' => $cinemagics,
            'sequences'  => $sequences,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'overlay_sequences') {
        $cinId = (int)($_GET['cinemagic_id'] ?? 0);
        if ($cinId > 0) {
            $sequences = $conn->fetchAllAssociative(
                "SELECT ns.id, ns.name
                   FROM narrative_sequences ns
                   JOIN cinemagics_2_sequences c2s ON c2s.sequence_id = ns.id
                  WHERE c2s.cinemagic_id = ?
                  ORDER BY c2s.sort_order ASC, ns.id ASC",
                [$cinId]
            );
        } else {
            $sequences = $conn->fetchAllAssociative(
                "SELECT id, name FROM narrative_sequences ORDER BY id ASC"
            );
        }
        echo json_encode(['ok' => true, 'sequences' => $sequences], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    
    
    
    
       // ── Translation meta ──────────────────────────────────────────────────
    if ($action === 'translation_meta') {
        $cinemagics = $conn->fetchAllAssociative(
            "SELECT id, name FROM cinemagics ORDER BY id ASC"
        );
        $sequences = $conn->fetchAllAssociative(
            "SELECT DISTINCT ns.id, ns.name
               FROM narrative_sequences ns
               JOIN cinemagics_2_sequences c2s ON c2s.sequence_id = ns.id
              ORDER BY ns.id DESC
              LIMIT 200"
        );
        $languages = $conn->fetchAllAssociative(
            "SELECT code, name FROM system_languages WHERE is_main = 0 ORDER BY code ASC"
        );
        echo json_encode([
            'ok'         => true,
            'cinemagics' => $cinemagics,
            'sequences'  => $sequences,
            'languages'  => $languages,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    

    // ── Tag Extract meta ──────────────────────────────────────────────────
    if ($action === 'tagextract_meta') {
        $minMax = $conn->fetchAssociative("SELECT MIN(id) AS min_id, MAX(id) AS max_id FROM sketches");
        echo json_encode(['ok' => true, 'min_id' => $minMax['min_id'] ?? 0, 'max_id' => $minMax['max_id'] ?? 0], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    exit;
}

// ─── Static data for initial page render ────────────────────────────────────

$kgCats    = $conn->fetchAllAssociative("SELECT id, name FROM kg_categories ORDER BY name ASC");
$genConfig = $conn->fetchAllAssociative("SELECT config_id, title FROM generator_config WHERE active = 1 ORDER BY title ASC");
$loreDocs  = $conn->fetchAllAssociative("
    SELECT d.id, d.name, d.keywords, c.name AS cat_name
    FROM documentations d
    JOIN md_doc_analysis da ON d.id = da.doc_id
    LEFT JOIN documentation_categories c ON d.category_id = c.id
    WHERE d.is_active = 1 ORDER BY d.updated_at DESC
");
$docCats   = $conn->fetchAllAssociative("SELECT id, name FROM documentation_categories ORDER BY name ASC");

// ─── Nav layout CSS derived from constants ───────────────────────────────────
// Build the mobile sidebar multi-row style from NAV_ROWS / NAV_COLS_PER_ROW.
$navRowsCss = (int)NAV_ROWS;
$navColsCss = (int)NAV_COLS_PER_ROW;
// If NAV_COLS_PER_ROW > 0 we force a grid; otherwise flex-wrap handles it naturally.
$navSidebarMobileExtra = '';
if ($navRowsCss > 1) {
    if ($navColsCss > 0) {
        $navSidebarMobileExtra = "display:grid!important;grid-template-columns:repeat({$navColsCss},1fr);grid-template-rows:repeat({$navRowsCss},auto);";
    } else {
        $navSidebarMobileExtra = "flex-wrap:wrap!important;max-height:none!important;";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>CLI Forge Hub</title>
<script>
(function(){
    try {
        var t = localStorage.getItem('spw_theme');
        if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
    } catch(e) {}
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════
   CLI FORGE HUB — Design System
   Dark + Light, mobile-first, Space Mono + Syne, amber
═══════════════════════════════════════════════════════ */
:root {
    --bg:         #080b10;
    --surface:    #0e1319;
    --card:       #111820;
    --card-h:     #141e28;
    --border:     #1c2535;
    --border-g:   #2a3a52;
    --text:       #c8d4e8;
    --dim:        #5a6a80;
    --bright:     #e8f0ff;
    --amber:      #f5a623;
    --amber-d:    rgba(245,166,35,0.08);
    --amber-m:    rgba(245,166,35,0.15);
    --amber-g:    rgba(245,166,35,0.35);
    --green:      #22d3a0;
    --green-d:    rgba(34,211,160,0.1);
    --red:        #f05060;
    --red-d:      rgba(240,80,96,0.1);
    --blue:       #4da6ff;
    --blue-d:     rgba(77,166,255,0.1);
    --mono:       'Space Mono', monospace;
    --sans:       'Syne', system-ui, sans-serif;
    --r:          10px;
    --r-sm:       7px;
    --shadow:     0 8px 28px rgba(0,0,0,.4);
}

[data-theme="light"],
:root[data-theme="light"] {
    --bg:         #f2f4f7;
    --surface:    #ffffff;
    --card:       #ffffff;
    --card-h:     #f3f4f6;
    --border:     #d1d5db;
    --border-g:   #9ca3af;
    --text:       #1f2937;
    --dim:        #6b7280;
    --bright:     #111827;
    --amber:      #d97706;
    --amber-d:    rgba(217,119,6,0.08);
    --amber-m:    rgba(217,119,6,0.16);
    --amber-g:    rgba(217,119,6,0.3);
    --green:      #059669;
    --green-d:    rgba(5,150,105,0.1);
    --red:        #dc2626;
    --red-d:      rgba(220,38,38,0.1);
    --blue:       #2563eb;
    --blue-d:     rgba(37,99,235,0.1);
    --shadow:     0 4px 16px rgba(0,0,0,.1);
}

@media (prefers-color-scheme:light) {
    :root:not([data-theme]) {
        --bg:#f2f4f7; --surface:#fff; --card:#fff; --card-h:#f3f4f6;
        --border:#d1d5db; --border-g:#9ca3af; --text:#1f2937; --dim:#6b7280;
        --bright:#111827; --amber:#d97706; --amber-d:rgba(217,119,6,.08);
        --amber-m:rgba(217,119,6,.16); --amber-g:rgba(217,119,6,.3);
        --green:#059669; --green-d:rgba(5,150,105,.1); --red:#dc2626;
        --red-d:rgba(220,38,38,.1); --blue:#2563eb; --blue-d:rgba(37,99,235,.1);
        --shadow:0 4px 16px rgba(0,0,0,.1);
    }
}

*{box-sizing:border-box;margin:0;padding:0}

html,body{
    height:100%;
    background:var(--bg);
    color:var(--text);
    font-family:var(--sans);
    -webkit-font-smoothing:antialiased;
}

/* ── Layout grid ── */
body{
    display:grid;
    grid-template-rows:54px 1fr;
    min-height:100vh;
    min-height:100dvh;
    overflow:hidden;
}
.hub-grid{
    display:grid;
    grid-template-columns:240px 1fr;
    overflow:hidden;
}

/* ── Header ── */
.hub-header{
    display:flex; align-items:center; justify-content:space-between; gap:12px;
    padding:0 16px;
    background:var(--surface);
    border-bottom:1px solid var(--border);
    position:sticky; top:0; z-index:50;
}
.hub-logo{
    display:flex; align-items:center; gap:10px;
    font-family:var(--mono); font-size:12px; font-weight:700;
    letter-spacing:1.6px; text-transform:uppercase; color:var(--amber);
    min-width:0;
}
.hub-logo i{font-size:18px; flex-shrink:0}
.hub-logo span{white-space:nowrap; overflow:hidden; text-overflow:ellipsis}
.hdr-actions{display:flex; align-items:center; gap:8px}

/* ── Sidebar ── */
.hub-sidebar{
    background:var(--surface);
    border-right:1px solid var(--border);
    overflow-y:auto;
    -webkit-overflow-scrolling:touch;
    padding:10px 8px;
    display:flex; flex-direction:column; gap:4px;
}
.nav-section-label{
    font-family:var(--mono); font-size:9px; letter-spacing:1.8px;
    text-transform:uppercase; color:var(--dim);
    padding:10px 8px 4px;
}
.nav-item{
    display:flex; align-items:center; gap:10px;
    padding:9px 10px; border-radius:var(--r-sm);
    cursor:pointer; border:1px solid transparent;
    transition:all .15s; user-select:none;
    height: 40px;
    flex-shrink: 0;
}
.nav-item:hover{background:var(--card); border-color:var(--border)}
.nav-item.active{background:var(--amber-d); border-color:var(--amber-g); color:var(--amber)}
.nav-item.active .nav-icon{color:var(--amber)}
.nav-icon{
    font-size:16px; color:var(--dim); flex-shrink:0;
    transition:color .15s;
}
.nav-label{
    flex:1; min-width:0;
    font-family:var(--sans); font-size:13px; font-weight:600;
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
    line-height:1.2;
}
.nav-badge{
    font-family:var(--mono); font-size:10px;
    padding:2px 6px; border-radius:999px;
    background:var(--amber-m); border:1px solid var(--amber-g);
    color:var(--amber); flex-shrink:0; min-width:22px; text-align:center;
}
.nav-badge.green{background:var(--green-d); border-color:var(--green); color:var(--green)}

/* ── Main content area ── */
.hub-main{
    overflow:hidden;
    display:flex; flex-direction:column;
}
.hub-view{
    flex:1; overflow:hidden;
    display:none; flex-direction:column;
}
.hub-view.active{display:flex}

/* ── View header ── */
.view-header{
    padding:14px 16px 12px;
    background:var(--surface);
    border-bottom:1px solid var(--border);
    flex-shrink:0;
}
.view-title-row{
    display:flex; align-items:center; gap:10px; margin-bottom:2px;
}
.view-title{
    font-family:var(--sans); font-size:16px; font-weight:700;
    color:var(--bright); flex:1; min-width:0;
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.view-tabs{
    display:flex; gap:6px; margin-top:10px;
    overflow-x:auto; -webkit-overflow-scrolling:touch;
    scrollbar-width:none;
}
.view-tabs::-webkit-scrollbar{display:none}
.vtab{
    padding:5px 14px; border-radius:999px;
    border:1px solid var(--border); background:transparent;
    color:var(--dim); font-family:var(--mono); font-size:11px;
    cursor:pointer; white-space:nowrap; transition:all .15s;
    appearance:none;
}
.vtab:hover{border-color:var(--border-g); color:var(--text)}
.vtab.active{background:var(--amber-d); border-color:var(--amber-g); color:var(--amber)}

/* ── Panel scroll areas ── */
.panel-scroll{
    flex:1; overflow-y:auto; overflow-x:hidden;
    -webkit-overflow-scrolling:touch;
    padding:14px 16px;
    padding-bottom:90px; /* clear sticky bottom bar */
}

/* ── Cards / sections ── */
.forge-section{
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--r);
    overflow:hidden;
    margin-bottom:12px;
}
.forge-section[open]{box-shadow:var(--shadow)}
.section-head{
    list-style:none; cursor:pointer;
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    padding:12px 14px; user-select:none;
}
.section-head::-webkit-details-marker{display:none}
.section-head-l{display:flex; align-items:center; gap:10px; min-width:0}
.section-num{
    width:26px; height:26px; border-radius:7px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    background:var(--amber-m); border:1px solid var(--amber-g);
    color:var(--amber); font-family:var(--mono); font-size:11px;
}
.section-lbl strong{
    display:block; font-family:var(--mono); font-size:11px;
    text-transform:uppercase; letter-spacing:.8px; color:var(--bright);
}
.section-lbl span{font-size:11px; color:var(--dim); line-height:1.35}
.section-body{padding:0 14px 14px}

/* ── Fields ── */
.field{margin-top:12px}
.field:first-child{margin-top:0}
.flabel{
    display:block; font-family:var(--mono); font-size:10px;
    letter-spacing:1.1px; text-transform:uppercase; color:var(--dim);
    margin:0 0 5px 1px;
}
input[type=text], input[type=number], select, textarea{
    width:100%; padding:11px 12px;
    background:var(--card); border:1px solid var(--border);
    border-radius:var(--r-sm); color:var(--text);
    font-family:var(--mono); font-size:13px; outline:none;
    transition:border-color .15s, background .15s;
    appearance:none;
}
input:focus, select:focus, textarea:focus{
    border-color:var(--amber); background:var(--card-h);
    box-shadow:0 0 0 3px rgba(245,166,35,.1);
}
textarea{min-height:90px; resize:vertical; line-height:1.45}
select{
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235a6a80' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 11px center; padding-right:32px;
}
[data-theme="light"] select,
:root[data-theme="light"] select{
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b7280' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
}
.fhint{margin-top:5px; font-size:11px; color:var(--dim); line-height:1.4}
.two-col{display:grid; grid-template-columns:1fr 1fr; gap:10px}
.pill-row{display:flex; flex-wrap:wrap; gap:8px; margin-top:2px}
.pill{
    display:flex; align-items:center; gap:7px;
    padding:9px 12px; border-radius:999px;
    background:var(--card); border:1px solid var(--border);
    font-family:var(--mono); font-size:12px; min-height:40px;
    cursor:pointer; user-select:none;
    transition:border-color .15s;
}
.pill:hover{border-color:var(--amber-g)}
.pill input{margin:0; cursor:pointer}

/* ── JSON preview ── */
.json-pre{
    background:#030705; color:#4ade80;
    border:1px solid #0d2012; border-radius:var(--r-sm);
    padding:12px; font-family:var(--mono); font-size:11px;
    line-height:1.55; white-space:pre-wrap; word-break:break-word;
    min-height:180px; max-height:280px; overflow:auto;
}
[data-theme="light"] .json-pre{
    background:#f0fdf4; color:#166534; border-color:#bbf7d0;
}

/* ── Ingredient sliders ── */
.ingr-grid{display:grid; grid-template-columns:1fr; gap:10px}
.ingr-item{
    border:1px solid var(--border); border-radius:var(--r-sm);
    background:var(--card); padding:10px 12px;
}
.ingr-top{display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:7px}
.ingr-name{font-family:var(--mono); font-size:12px; color:var(--bright)}
.ingr-val{font-family:var(--mono); font-size:12px; color:var(--amber); min-width:38px; text-align:right}
input[type=range]{
    width:100%; accent-color:var(--amber);
    height:4px; cursor:pointer;
}

/* ── Queue monitor ── */
.queue-header{
    display:flex; align-items:center; gap:10px;
    padding:12px 14px; border-bottom:1px solid var(--border);
    background:var(--surface); flex-shrink:0;
    flex-wrap:wrap;
}
.q-count-pills{display:flex; flex-wrap:wrap; gap:6px}
.q-pill{
    font-family:var(--mono); font-size:10px;
    padding:3px 8px; border-radius:999px;
    border:1px solid var(--border); color:var(--dim);
}
.q-pill.pending  {border-color:var(--amber-g); color:var(--amber); background:var(--amber-d)}
.q-pill.processing{border-color:var(--blue); color:var(--blue); background:var(--blue-d)}
.q-pill.done     {border-color:var(--green); color:var(--green); background:var(--green-d)}
.q-pill.failed   {border-color:var(--red); color:var(--red); background:var(--red-d)}
.q-pill.cancelled{border-color:var(--border-g); color:var(--dim)}

.queue-table-wrap{overflow:auto; flex:1; -webkit-overflow-scrolling:touch}
.queue-table{
    width:100%; min-width:600px;
    border-collapse:collapse; font-family:var(--mono); font-size:12px;
}
.queue-table th{
    padding:8px 12px; text-align:left;
    background:var(--surface); border-bottom:1px solid var(--border);
    color:var(--dim); font-size:10px; letter-spacing:1px; text-transform:uppercase;
    white-space:nowrap;
}
.queue-table td{
    padding:9px 12px; border-bottom:1px solid var(--border);
    vertical-align:middle; color:var(--text);
}
.queue-table tr:hover td{background:var(--card-h)}
.status-dot{
    display:inline-block; width:8px; height:8px;
    border-radius:50%; margin-right:6px;
}
.status-dot.pending  {background:var(--amber)}
.status-dot.processing{background:var(--blue); animation:blink 1.2s infinite}
.status-dot.done     {background:var(--green)}
.status-dot.failed   {background:var(--red)}
.status-dot.cancelled{background:var(--dim)}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

.q-action-btn{
    padding:3px 8px; border-radius:5px;
    border:1px solid var(--border); background:transparent;
    color:var(--dim); font-family:var(--mono); font-size:10px;
    cursor:pointer; transition:all .15s; white-space:nowrap;
}
.q-action-btn:hover{border-color:var(--amber-g); color:var(--amber)}
.q-action-btn.danger:hover{border-color:var(--red); color:var(--red); background:var(--red-d)}

.q-pagination{
    display:flex; align-items:center; justify-content:center; gap:8px;
    padding:10px 14px; border-top:1px solid var(--border);
    background:var(--surface); flex-shrink:0;
}
.q-page-btn{
    padding:4px 12px; border-radius:var(--r-sm);
    border:1px solid var(--border); background:transparent;
    color:var(--dim); font-family:var(--mono); font-size:11px;
    cursor:pointer; transition:all .15s;
}
.q-page-btn:hover{border-color:var(--amber-g); color:var(--amber)}
.q-page-btn:disabled{opacity:.4; cursor:default}
.q-page-info{font-family:var(--mono); font-size:11px; color:var(--dim)}
.queue-empty{
    text-align:center; padding:40px 20px;
    font-family:var(--mono); font-size:12px; color:var(--dim);
}

/* ── Bottom bar ── */
.bottom-bar{
    position:fixed; bottom:0; left:0; right:0; z-index:40;
    background:rgba(8,11,16,.97); border-top:1px solid var(--border);
    padding:10px 14px calc(10px + env(safe-area-inset-bottom));
    backdrop-filter:blur(14px);
}
[data-theme="light"] .bottom-bar{background:rgba(255,255,255,.97)}
.bottom-inner{
    max-width:900px; margin:0 auto;
    display:grid; grid-template-columns:repeat(2, 1fr); gap:8px;
}
.bbtn{
    appearance:none; border:none; border-radius:var(--r-sm); cursor:pointer;
    font-family:var(--mono); font-size:12px; font-weight:700;
    display:flex; align-items:center; justify-content:center; gap:7px;
    padding:0 14px; min-height:44px;
    transition:all .15s; white-space:nowrap;
}
.bbtn:active{transform:translateY(1px)}
.bbtn.primary{background:var(--amber); color:#000}
.bbtn.primary:hover{filter:brightness(1.1)}
.bbtn.success{background:var(--green); color:#000}
.bbtn.success:hover{filter:brightness(1.1)}
.bbtn.secondary{background:var(--card); border:1px solid var(--border); color:var(--text)}
.bbtn.secondary:hover{border-color:var(--border-g)}
.bbtn.outline{background:transparent; border:1px solid var(--border); color:var(--dim)}
.bbtn.outline:hover{border-color:var(--amber-g); color:var(--amber)}
.bbtn.span2{grid-column:1/-1}

/* ── Misc utils ── */
.icon-btn{
    appearance:none; border-radius:var(--r-sm); cursor:pointer;
    font-family:var(--mono); font-size:14px;
    display:inline-flex; align-items:center; justify-content:center;
    width:38px; height:38px;
    background:var(--card); border:1px solid var(--border); color:var(--dim);
    text-decoration:none; transition:all .15s;
}
.icon-btn:hover{border-color:var(--amber-g); color:var(--amber); background:var(--amber-d)}
.badge{
    display:inline-block; font-family:var(--mono); font-size:10px;
    padding:2px 7px; border-radius:999px; border:1px solid;
}
.badge.model{border-color:var(--border-g); color:var(--dim); background:var(--card)}
.badge.active{border-color:var(--green); color:var(--green); background:var(--green-d)}
.meta-chip{
    display:inline-flex; align-items:center; gap:6px;
    padding:5px 10px; border:1px solid var(--border);
    border-radius:999px; background:var(--card);
    color:var(--dim); font-family:var(--mono); font-size:10px;
    margin:3px 4px 0 0;
}
.toast-wrap{
    position:fixed; right:14px; bottom:80px; z-index:9999;
    display:flex; flex-direction:column; gap:7px; pointer-events:none;
}
.toast{
    max-width:min(360px, calc(100vw - 28px));
    pointer-events:auto; border:1px solid var(--border);
    background:var(--card); color:var(--text); border-radius:var(--r-sm);
    padding:9px 12px; font-family:var(--mono); font-size:12px;
    box-shadow:var(--shadow);
    animation:tfade-in .2s ease;
}
@keyframes tfade-in{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

/* ── Responsive ── */
@media(max-width:680px){
    .hub-grid{
        grid-template-columns:1fr;
        grid-template-rows: auto minmax(0, 1fr);
    }
    .hub-sidebar{
        display:flex; flex-direction:row; flex-wrap:nowrap;
        overflow-x:auto; overflow-y:hidden;
        border-right:none; border-bottom:1px solid var(--border);
        padding:8px;
        max-height:none;
        -webkit-overflow-scrolling:touch;
        scrollbar-width:none;
        align-items: center;
        <?= $navSidebarMobileExtra ?>
    }
    .hub-sidebar::-webkit-scrollbar{display:none}
    .nav-section-label{display:none}
    .nav-item{flex-shrink:0; padding:8px 10px; height: 38px;}
    .nav-label{display:none}
}
@media(min-width:681px){
    .hub-grid{height:calc(100vh - 54px); height:calc(100dvh - 54px)}
    .hub-sidebar{height:100%}
    .hub-main{height:100%}
}
@media(min-width:900px){
    .hub-sidebar{width:260px}
    .two-col{grid-template-columns:1fr 1fr}
    .ingr-grid{grid-template-columns:1fr 1fr}
    .bottom-inner{grid-template-columns:repeat(4,1fr)}
    .bbtn.span2{grid-column:auto}
}
</style>
</head>
<body>

<?php require_once "forge_tool.php"; ?>

<!-- ── Header ── -->
<header class="hub-header">
    <div class="hub-logo">
        <i class="bi bi-terminal-fill"></i>
        <span>CLI Forge Hub</span>
    </div>
    <div class="hdr-actions">
        <a class="icon-btn" href="/dashboard.php" title="Dashboard"><i class="bi bi-house"></i></a>
        <button class="icon-btn" id="btnTheme" title="Toggle theme" onclick="Hub.toggleTheme()"><i class="bi bi-circle-half"></i></button>
    </div>
</header>

<!-- ── Body grid ── -->
<div class="hub-grid">

    <!-- ── Sidebar nav ── -->
    <nav class="hub-sidebar" id="hubSidebar">
        <div class="nav-section-label">Pipelines</div>

        <div class="nav-item active" data-view="kg" onclick="Hub.switchView('kg',this)">
            <i class="bi bi-diagram-3 nav-icon"></i>
            <span class="nav-label">KG → Sketch</span>
            <span class="nav-badge" id="nb-kg">—</span>
        </div>
        <div class="nav-item" data-view="lore" onclick="Hub.switchView('lore',this)">
            <i class="bi bi-diagram-2 nav-icon"></i>
            <span class="nav-label">Lore → Sketch</span>
            <span class="nav-badge" id="nb-lore">—</span>
        </div>
        <div class="nav-item" data-view="autopilot" onclick="Hub.switchView('autopilot',this)">
            <i class="bi bi-rocket-takeoff nav-icon"></i>
            <span class="nav-label">Autopilot</span>
            <span class="nav-badge" id="nb-autopilot">—</span>
        </div>
        <div class="nav-item" data-view="extract" onclick="Hub.switchView('extract',this)">
            <i class="bi bi-file-earmark-text nav-icon"></i>
            <span class="nav-label">Curator Extract</span>
            <span class="nav-badge" id="nb-extract">—</span>
        </div>
        <div class="nav-item" data-view="aggregate" onclick="Hub.switchView('aggregate',this)">
            <i class="bi bi-journal-text nav-icon"></i>
            <span class="nav-label">Curator Aggregate</span>
            <span class="nav-badge" id="nb-aggregate">—</span>
        </div>
        <div class="nav-item" data-view="narseq" onclick="Hub.switchView('narseq',this)">
            <i class="bi bi-collection-play nav-icon"></i>
            <span class="nav-label">Seq Composer</span>
            <span class="nav-badge" id="nb-narseq">—</span>
        </div>
        <div class="nav-item" data-view="tagextract" onclick="Hub.switchView('tagextract',this)">
            <i class="bi bi-tags nav-icon"></i>
            <span class="nav-label">Tag Extractor</span>
            <span class="nav-badge" id="nb-tagextract">—</span>
        </div>
        <div class="nav-item" data-view="github" onclick="Hub.switchView('github',this)">
            <i class="bi bi-github nav-icon"></i>
            <span class="nav-label">GitHub Sync</span>
            <span class="nav-badge" id="nb-github">—</span>
        </div>
        <div class="nav-item" data-view="translation" onclick="Hub.switchView('translation',this)">
            <i class="bi bi-translate nav-icon"></i>
            <span class="nav-label">Translation</span>
            <span class="nav-badge" id="nb-translation">—</span>
        </div>
 <div class="nav-item" data-view="overlay" onclick="Hub.switchView('overlay',this)">
            <i class="bi bi-card-text nav-icon"></i>
            <span class="nav-label">Overlay</span>
            <span class="nav-badge" id="nb-overlay">—</span>
        </div>
        
    </nav>

    <!-- ── Main content ── -->
    <main class="hub-main" id="hubMain">

        <!-- ════════════════════════════════════════════════
             KG → SKETCH
        ════════════════════════════════════════════════ -->
        <?php require __DIR__ . '/cli_forge_kg.php'; ?>

        <!-- ════════════════════════════════════════════════
             LORE → SKETCH
        ════════════════════════════════════════════════ -->
        <?php require __DIR__ . '/cli_forge_lore.php'; ?>

        <!-- ════════════════════════════════════════════════
             AUTOPILOT
        ════════════════════════════════════════════════ -->
        <?php require __DIR__ . '/cli_forge_autopilot.php'; ?>

        <!-- ════════════════════════════════════════════════
             MD CURATOR EXTRACT
        ════════════════════════════════════════════════ -->
        <?php require __DIR__ . '/cli_forge_extract.php'; ?>

        <!-- ════════════════════════════════════════════════
             MD CURATOR AGGREGATE
        ════════════════════════════════════════════════ -->
        <?php require __DIR__ . '/cli_forge_aggregate.php'; ?>

        <!-- ════════════════════════════════════════════════
             NARRATIVE SEQUENCE COMPOSER
        ════════════════════════════════════════════════ -->
        <?php require __DIR__ . '/cli_forge_narseq.php'; ?>

        <!-- ════════════════════════════════════════════════
             SKETCH TAG EXTRACTOR
        ════════════════════════════════════════════════ -->
        <?php require __DIR__ . '/cli_forge_tagextract.php'; ?>

        <!-- ════════════════════════════════════════════════
             GITHUB SYNC
        ════════════════════════════════════════════════ -->
        <?php require __DIR__ . '/cli_forge_github.php'; ?>
        
        
               <!-- ════════════════════════════════════════════════
             TRANSLATION COMPOSER
        ════════════════════════════════════════════════ -->
        <?php require __DIR__ . '/cli_forge_translation.php'; ?>
        
        
               <!-- ════════════════════════════════════════════════
             OVERLAY TEXT COMPOSER
        ════════════════════════════════════════════════ -->
        <?php require __DIR__ . '/cli_forge_overlay.php'; ?>


        
        
        

    </main><!-- /hub-main -->
</div><!-- /hub-grid -->

<!-- ── Sticky bottom bar ── -->
<div class="bottom-bar">
    <div class="bottom-inner" id="bottomBar">
        <!-- populated by JS per active view -->
    </div>
</div>

<!-- ── Toast ── -->
<div class="toast-wrap" id="toastWrap"></div>

<script>
// ═══════════════════════════════════════════════════════════════════════════
// CLI FORGE HUB — Application
// ═══════════════════════════════════════════════════════════════════════════

const Hub = (() => {
    'use strict';

    const VIEWS = ['kg','lore','autopilot','extract','aggregate','narseq','tagextract','github','translation','overlay'];

    
    
    
    const JOB_TYPE_MAP = {
        kg:         'kg_sketch',
        lore:       'lore_sketch',
        autopilot:  'autopilot',
        extract:    'md_curator_extract',
        aggregate:  'md_curator_aggregate',
        narseq:     'narrative_sequence_compose',
        tagextract: 'sketch_tag_extract',
        github:     'github_sync',
        translation:'translation_compose',
        overlay:    'overlay_compose',

    };

    let _activeView   = 'kg';
    
    
    let _activePanels = { kg:'config', lore:'config', autopilot:'config', extract:'config', aggregate:'config', narseq:'config', tagextract:'config', github:'config', translation:'config', overlay:'config' };

    
    
    let _queuePages   = { kg:1, lore:1, autopilot:1, extract:1, aggregate:1, narseq:1, tagextract:1, github:1, translation:1, overlay:1 };

    
    
    let _loreDocData  = [];
    let _apIngredients = [];

    // ── Utils ────────────────────────────────────────────────────────────

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }

    function toast(msg, dur = 3200) {
        const wrap = document.getElementById('toastWrap');
        const el = document.createElement('div');
        el.className = 'toast';
        el.innerHTML = msg;
        wrap.appendChild(el);
        setTimeout(() => el.remove(), dur);
    }

    async function post(params) {
        const body = new FormData();
        for (const [k, v] of Object.entries(params)) body.append(k, v);
        const res = await fetch(location.pathname, { method:'POST', body });
        return res.json();
    }

    async function get(qs) {
        const res = await fetch(location.pathname + '?' + qs);
        return res.json();
    }

    function fmtDate(s) {
        if (!s) return '—';
        return s.replace('T', ' ').substring(0, 16);
    }

    // ── Theme ────────────────────────────────────────────────────────────

    function toggleTheme() {
        const current = document.documentElement.getAttribute('data-theme') || '';
        const next = (current === 'light') ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('spw_theme', next); } catch(e) {}
    }

    // ── Navigation ───────────────────────────────────────────────────────

    function switchView(view, navEl) {
        _activeView = view;
        document.querySelectorAll('.hub-view').forEach(el => el.classList.remove('active'));
        document.getElementById('view-' + view).classList.add('active');
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
        if (navEl) navEl.classList.add('active');
        updateBottomBar();
        refreshQueueBadge(view);
    }

    function switchPanel(view, panel, tabEl) {
        _activePanels[view] = panel;
        const allPanelIds = [
            `${view}-config`, `${view}-json`, `${view}-queue`
        ];
        allPanelIds.forEach(pid => {
            const el = document.getElementById(pid);
            if (el) {
                el.style.display = 'none';
                el.classList.remove('active');
            }
        });
        const target = document.getElementById(`${view}-${panel}`);
        if (target) {
            target.style.display = panel === 'queue' ? 'flex' : 'block';
            target.classList.add('active');
        }
        const viewEl = document.getElementById('view-' + view);
        if (viewEl) {
            viewEl.querySelectorAll('.vtab').forEach(t => t.classList.remove('active'));
        }
        if (tabEl) tabEl.classList.add('active');

        if (panel === 'json') updateJson(view);
        if (panel === 'queue') refreshQueue(view);
        updateBottomBar();
    }

    // ── Bottom bar ────────────────────────────────────────────────────────

    function updateBottomBar() {
        const bar   = document.getElementById('bottomBar');
        const view  = _activeView;
        const panel = _activePanels[view];

        if (panel === 'json') {
            bar.innerHTML = `
                <button class="bbtn secondary" onclick="Hub.copyJson('${view}')"><i class="bi bi-clipboard"></i> Copy JSON</button>
                <button class="bbtn secondary" onclick="Hub.downloadJson('${view}')"><i class="bi bi-download"></i> Download</button>
                <button class="bbtn primary span2" onclick="Hub.queueJob('${view}')"><i class="bi bi-send"></i> Queue Job</button>
            `;
        } else if (panel === 'queue') {
            bar.innerHTML = `
                <button class="bbtn secondary span2" onclick="Hub.refreshQueue('${view}')"><i class="bi bi-arrow-clockwise"></i> Refresh Queue</button>
                <button class="bbtn primary span2" onclick="Hub.switchPanelById('${view}','config')"><i class="bi bi-sliders"></i> Back to Config</button>
            `;
        } else {
            // config
            bar.innerHTML = `
                <button class="bbtn secondary" onclick="Hub.switchPanelById('${view}','json')"><i class="bi bi-braces"></i> Preview JSON</button>
                <button class="bbtn outline" onclick="Hub.refreshMeta('${view}')"><i class="bi bi-arrow-repeat"></i> Refresh</button>
                <button class="bbtn success span2" onclick="Hub.queueJob('${view}')"><i class="bi bi-send"></i> Queue Job</button>
            `;
        }
    }

    function switchPanelById(view, panel) {
        const viewEl = document.getElementById('view-' + view);
        if (!viewEl) return;
        const tabEl = viewEl.querySelector(`.vtab[data-panel="${view}-${panel}"]`);
        switchPanel(view, panel, tabEl);
    }

    // ── Meta / data loaders ───────────────────────────────────────────────

    async function refreshMeta(view) {
        if (view === 'kg')         await loadKgMeta();
        if (view === 'lore')       await loadLoreMeta();
        if (view === 'autopilot')  await loadAutopilotMeta();
        if (view === 'extract')    await loadCuratorMeta('extract');
        if (view === 'aggregate')  {} // no dynamic meta needed
        if (view === 'narseq')     await loadNarseqMeta();
        if (view === 'tagextract') await loadTagextractMeta();
        if (view === 'github')     updateJson('github');
        if (view === 'translation') await loadTranslationMeta();
        if (view === 'overlay')     await loadOverlayMeta();
        
        
        

    }

    async function loadKgMeta() {
        const mode = parseInt(document.querySelector('input[name="kg_mode"]:checked')?.value || '1', 10);
        const catId = parseInt(document.getElementById('kg_category_id')?.value || '0', 10);
        const data = await get(`action=kg_meta&mode=${mode}&category_id=${catId}`);
        if (!data.ok) return;

        const sel = document.getElementById('kg_node_type');
        const prev = sel.value;
        sel.innerHTML = (data.types || []).map(t =>
            `<option value="${esc(t.node_type)}">${esc(t.node_type)} (${t.count}) — new:${t.new} hist:${t.hist}</option>`
        ).join('');
        if (prev && [...sel.options].some(o => o.value === prev)) sel.value = prev;

        document.getElementById('kg_type_info').textContent =
            `${(data.types||[]).length} node type(s) in current scope.`;
        updateJson('kg');
    }

    async function loadLoreMeta() {
        const docId = parseInt(document.getElementById('lore_doc_id')?.value || '0', 10);
        const data  = await get(`action=lore_meta&doc_id=${docId}`);
        if (!data.ok) return;
        _loreDocData = data.docs || [];

        const kw = (_loreDocData.find(d => parseInt(d.id,10) === docId) || _loreDocData[0] || {}).keywords || '—';
        document.getElementById('lore_kw_box').textContent = kw;

        const groupSel = document.getElementById('lore_group_key');
        const prev = groupSel.value;
        groupSel.innerHTML = (data.groups || []).map(g =>
            `<option value="${esc(g.key)}">${esc(g.key)} (${g.count})</option>`
        ).join('');
        if (prev && [...groupSel.options].some(o => o.value === prev)) groupSel.value = prev;

        const cfgSel = document.getElementById('lore_generator_config_id');
        if (data.configs && !cfgSel.options.length) {
            cfgSel.innerHTML = data.configs.map(c =>
                `<option value="${esc(c.config_id)}">${esc(c.title)}</option>`
            ).join('');
        }
        updateJson('lore');
    }

    async function loadAutopilotMeta() {
        const data = await get('action=autopilot_meta');
        if (!data.ok) return;

        const genSel = document.getElementById('ap_desc_gen_id');
        genSel.innerHTML = (data.generators || []).map(g =>
            `<option value="${esc(g.id)}">${esc(g.title)} (${esc(g.model||'openai')})</option>`
        ).join('') || '<option value="">— no generators —</option>';

        _apIngredients = data.ingredients || [];
        renderIngredients();
        updateJson('autopilot');
    }

    function renderIngredients() {
        const grid = document.getElementById('ap_ingr_grid');
        grid.innerHTML = _apIngredients.map(item => `
            <div class="ingr-item">
                <div class="ingr-top">
                    <div class="ingr-name">${esc(item.label)}</div>
                    <div class="ingr-val" id="iv_${esc(item.key)}">${esc(item.default)}%</div>
                </div>
                <input type="range" id="ip_${esc(item.key)}" min="0" max="100" step="1" value="${esc(item.default)}"
                       oninput="document.getElementById('iv_${esc(item.key)}').textContent=this.value+'%'; Hub.updateJson('autopilot')">
            </div>
        `).join('');
    }

    async function loadCuratorMeta(view) {
        const data = await get('action=curator_meta');
        if (!data.ok) return;
        if (view === 'extract') {
            const box = document.getElementById('ext_fixed_chips');
            if (box) {
                box.innerHTML = `
                    <div class="meta-chip"><i class="bi bi-cpu"></i> Curator: ${esc(data.curator_title)} (md_curator_v1)</div>
                    <div class="meta-chip"><i class="bi bi-journal-text"></i> Showrunner: ${esc(data.showrunner_title)} (md_curator_showrunner_v1)</div>
                `;
            }
        }
    }

    async function loadNarseqMeta() {
        const data = await get('action=narseq_meta');
        if (!data.ok) return;
        const sel = document.getElementById('narseq_sequence_id');
        const prev = sel.value;
        sel.innerHTML = (data.sequences || []).map(s =>
            `<option value="${esc(s.id)}">[#${esc(s.id)}] ${esc(s.name)}</option>`
        ).join('') || '<option value="0">— no sequences found —</option>';
        if (prev && prev !== '0' && [...sel.options].some(o => o.value === prev)) sel.value = prev;
        updateJson('narseq');
    }

    async function loadTagextractMeta() {
        const data = await get('action=tagextract_meta');
        if (!data.ok) return;
        const hint = document.getElementById('tagextract_range_hint');
        if (hint) hint.textContent = `DB Sketch Range: #${data.min_id} – #${data.max_id}`;
    }

    // ── JSON builders ─────────────────────────────────────────────────────

    function getPayload(view) {
        const g = id => document.getElementById(id);
        const v = (id, fallback) => {
            const el = g(id);
            return el ? el.value : fallback;
        };

        if (view === 'kg') {
            const modeEl = document.querySelector('input[name="kg_mode"]:checked');
            const amountRaw = v('kg_amount','').trim();
            const tags = v('kg_tags','').split(',').map(s=>s.trim()).filter(Boolean);
            return {
                mode: parseInt(modeEl?.value || '1', 10),
                category_id: parseInt(v('kg_category_id','0'), 10),
                node_type: v('kg_node_type',''),
                history_filter: v('kg_history_filter','new'),
                offset: parseInt(v('kg_offset','0'), 10),
                amount: amountRaw === '' ? null : parseInt(amountRaw, 10),
                generator_config_id: v('kg_generator_config_id',''),
                tags,
                confirm: !!(g('kg_confirm')?.checked),
                delay_us: parseInt(v('kg_delay_us','500000'), 10),
                dry_run: !!(g('kg_dry_run')?.checked),
            };
        }

        if (view === 'lore') {
            const amountRaw = v('lore_amount','').trim();
            const tags = v('lore_tags','').split(',').map(s=>s.trim()).filter(Boolean);
            return {
                doc_id: parseInt(v('lore_doc_id','0'), 10),
                group_key: v('lore_group_key',''),
                offset: parseInt(v('lore_offset','0'), 10),
                amount: amountRaw === '' ? null : parseInt(amountRaw, 10),
                generator_config_id: v('lore_generator_config_id',''),
                tags,
                confirm: !!(g('lore_confirm')?.checked),
                delay_us: parseInt(v('lore_delay_us','500000'), 10),
                dry_run: !!(g('lore_dry_run')?.checked),
            };
        }

        if (view === 'autopilot') {
            const probs = {};
            for (const item of _apIngredients) {
                const el = document.getElementById('ip_' + item.key);
                probs[item.key] = el ? Math.max(0, Math.min(100, parseInt(el.value, 10))) : item.default;
            }
            return {
                desc_gen_id: parseInt(v('ap_desc_gen_id','0'), 10),
                amount: parseInt(v('ap_amount','0'), 10),
                probabilities: probs,
                dry_run: false,
            };
        }

        if (view === 'extract') {
            const model = v('ext_overrideModel','').trim();
            return {
                targetCategoryId: parseInt(v('ext_targetCategoryId','0'), 10),
                limit: parseInt(v('ext_limit','10'), 10),
                charLimit: parseInt(v('ext_charLimit','4000'), 10),
                overrideModel: model === '' ? null : model,
            };
        }

        if (view === 'aggregate') {
            return {
                targetCategoryId: parseInt(v('agg_targetCategoryId','0'), 10),
                limit: parseInt(v('agg_limit','100'), 10),
            };
        }

        if (view === 'narseq') {
            const model = v('narseq_override_model','').trim();
            return {
                sequence_id: parseInt(v('narseq_sequence_id','0'), 10),
                rerun: !!(g('narseq_rerun')?.checked),
                overrideModel: model === '' ? null : model,
            };
        }

        if (view === 'tagextract') {
            return {
                from_id: parseInt(v('tagextract_from','0'), 10),
                to_id: parseInt(v('tagextract_to','0'), 10),
                batch: parseInt(v('tagextract_batch','5'), 10),
                dry_run: !!(g('tagextract_dry_run')?.checked),
            };
        }

        if (view === 'overlay') {
            const mode = document.querySelector('input[name="overlay_mode"]:checked')?.value || 'cinemagic';
            if (mode === 'sketch') {
                return {
                    sketch_id: parseInt(v('overlay_sketch_id','0'), 10) || null,
                    rerun:     !!(document.getElementById('overlay_rerun')?.checked),
                };
            }
            return {
                cinemagic_id: parseInt(v('overlay_cinemagic_id','0'), 10) || null,
                sequence_id:  parseInt(v('overlay_sequence_id','0'), 10) || null,
                rerun:        !!(document.getElementById('overlay_rerun')?.checked),
            };
        }

        if (view === 'translation') {
            return {
                cinemagic_id: parseInt(v('translation_cinemagic_id','0'), 10) || null,
                sequence_id:  parseInt(v('translation_sequence_id','0'), 10) || null,
                lang:         v('translation_lang','').trim(),
                rerun:        !!(document.getElementById('translation_rerun')?.checked),
            };
        }

        if (view === 'github') {
            return {
                repo_path:       v('github_repo_path', '').trim(),
                branch_name:     v('github_branch_name', 'main').trim(),
                remote_name:     v('github_remote_name', 'origin').trim(),
                commit_message:  v('github_commit_message', 'Auto commit from PHP').trim(),
                add_all:         !!(g('github_add_all')?.checked),
                commit:          !!(g('github_commit')?.checked),
                push:            !!(g('github_push')?.checked),
                pull_rebase:     !!(g('github_pull_rebase')?.checked),
                amend:           !!(g('github_amend')?.checked),
                allow_empty:     !!(g('github_allow_empty')?.checked),
                dry_run:         !!(g('github_dry_run')?.checked),
                force_push:      !!(g('github_force_push')?.checked),
                git_user_name:   v('github_git_user_name', '').trim(),
                git_user_email:  v('github_git_user_email', '').trim(),
            };
        }

        return {};
    }

    function updateJson(view) {
        const outId = { kg:'kg_json_out', lore:'lore_json_out', autopilot:'ap_json_out', extract:'ext_json_out', aggregate:'agg_json_out', narseq:'narseq_json_out', tagextract:'tagextract_json_out', github:'github_json_out' }[view];
        const el = document.getElementById(outId);
        if (el) el.textContent = JSON.stringify(getPayload(view), null, 2);
    }

    // ── Queue job ─────────────────────────────────────────────────────────

    async function queueJob(view) {
        const payload  = getPayload(view);
        const labelEl  = document.getElementById(view === 'autopilot' ? 'ap_label' : view === 'extract' ? 'ext_label' : view === 'aggregate' ? 'agg_label' : view === 'narseq' ? 'narseq_label' : view === 'tagextract' ? 'tagextract_label' : view === 'github' ? 'github_label' : view + '_label');
        const prioEl   = document.getElementById(view === 'autopilot' ? 'ap_priority' : view === 'extract' ? 'ext_priority' : view === 'aggregate' ? 'agg_priority' : view === 'narseq' ? 'narseq_priority' : view === 'tagextract' ? 'tagextract_priority' : view === 'github' ? 'github_priority' : view + '_priority');
        const label    = labelEl ? labelEl.value.trim() : '';
        const priority = prioEl  ? parseInt(prioEl.value, 10) : 50;

        const r = await post({
            action:   'queue_job',
            job_type: JOB_TYPE_MAP[view],
            label,
            priority,
            payload:  JSON.stringify(payload),
        });

        if (r.ok) {
            toast(`✓ ${r.message}`);
            refreshQueueBadge(view);
            switchPanelById(view, 'queue');
            await refreshQueue(view);
        } else {
            toast(`✕ ${r.error || 'Queue failed'}`, 3500);
        }
    }

    // ── Queue monitor ─────────────────────────────────────────────────────

    async function refreshQueueBadge(view) {
        const jt   = JOB_TYPE_MAP[view];
        const data = await get(`action=queue_list&job_type=${jt}&page=1`);
        if (!data.ok) return;
        const counts = data.counts || {};
        const pending = counts.pending || 0;
        const badgeId = `nb-${view}`;
        const badge   = document.getElementById(badgeId);
        if (badge) {
            badge.textContent = pending;
            badge.className   = 'nav-badge' + (pending > 0 ? '' : '');
        }
    }

    async function refreshQueue(view) {
        const jt   = JOB_TYPE_MAP[view];
        const page = _queuePages[view] || 1;
        const data = await get(`action=queue_list&job_type=${jt}&page=${page}`);
        if (!data.ok) return;

        const counts = data.counts || {};
        renderQueueCounts(view, counts);
        renderQueueTable(view, data.data);
        renderQueuePagination(view, data.data);

        // update nav badge
        const badge = document.getElementById(`nb-${view}`);
        if (badge) badge.textContent = counts.pending || 0;
    }

    function renderQueueCounts(view, counts) {
        const box = document.getElementById(`${view}-q-counts`);
        if (!box) return;
        const statuses = ['pending','processing','done','failed','cancelled'];
        box.innerHTML = statuses.map(s =>
            `<span class="q-pill ${s}">${s}: ${counts[s] || 0}</span>`
        ).join('');
    }

    function renderQueueTable(view, qdata) {
        const tbody = document.getElementById(`${view}-q-tbody`);
        if (!tbody) return;
        const rows = qdata?.rows || [];
        if (rows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6"><div class="queue-empty">No jobs in queue.</div></td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const canCancel  = r.status === 'pending';
            const canDelete  = ['cancelled','done','failed'].includes(r.status);
            const canRequeue = r.status === 'failed';
            const dots       = `<span class="status-dot ${r.status}"></span>`;
            const priHtml = canCancel
                ? `<select class="q-action-btn" style="padding:3px 6px" onchange="Hub.setPriority(${r.id},'${view}',this.value)">
                    ${[1,10,25,50,75,90,99].map(p => `<option value="${p}" ${parseInt(r.priority)===p?'selected':''}>${p}</option>`).join('')}
                   </select>`
                : `<span style="color:var(--dim);font-size:11px">${r.priority}</span>`;

            const actions = [
                canCancel  ? `<button class="q-action-btn danger" onclick="Hub.cancelJob(${r.id},'${view}')">Cancel</button>` : '',
                canRequeue ? `<button class="q-action-btn" onclick="Hub.requeueJob(${r.id},'${view}')">Re-queue</button>` : '',
                canDelete  ? `<button class="q-action-btn danger" onclick="Hub.deleteJob(${r.id},'${view}')">Delete</button>` : '',
            ].filter(Boolean).join(' ');

            return `<tr>
                <td style="color:var(--dim)">#${r.id}</td>
                <td title="${esc(r.label)}" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(r.label || '—')}</td>
                <td>${dots}${r.status}</td>
                <td>${priHtml}</td>
                <td style="color:var(--dim)">${fmtDate(r.created_at)}</td>
                <td>${actions || '<span style="color:var(--dim);font-size:10px">—</span>'}</td>
            </tr>`;
        }).join('');
    }

    function renderQueuePagination(view, qdata) {
        const box   = document.getElementById(`${view}-q-pagination`);
        if (!box) return;
        const total   = qdata?.total || 0;
        const perPage = qdata?.per_page || 20;
        const page    = qdata?.page || 1;
        const pages   = Math.max(1, Math.ceil(total / perPage));

        box.innerHTML = `
            <button class="q-page-btn" onclick="Hub.goQueuePage('${view}',${page-1})" ${page<=1?'disabled':''}>‹ Prev</button>
            <span class="q-page-info">Page ${page} / ${pages} · ${total} jobs</span>
            <button class="q-page-btn" onclick="Hub.goQueuePage('${view}',${page+1})" ${page>=pages?'disabled':''}>Next ›</button>
        `;
    }

    function goQueuePage(view, page) {
        _queuePages[view] = Math.max(1, page);
        refreshQueue(view);
    }

    // ── Queue actions ──────────────────────────────────────────────────────

    async function cancelJob(id, view) {
        const r = await post({ action:'cancel_job', id });
        if (r.ok) { toast('Job cancelled.'); refreshQueue(view); }
        else toast(r.error || 'Cancel failed', 3500);
    }

    async function deleteJob(id, view) {
        const r = await post({ action:'delete_job', id });
        if (r.ok) { toast('Job deleted.'); refreshQueue(view); }
        else toast(r.error || 'Delete failed', 3500);
    }

    async function requeueJob(id, view) {
        const r = await post({ action:'requeue_job', id });
        if (r.ok) { toast('Job re-queued.'); refreshQueue(view); }
        else toast(r.error || 'Re-queue failed', 3500);
    }

    async function setPriority(id, view, priority) {
        await post({ action:'set_priority', id, priority });
        refreshQueue(view);
    }

    // ── Copy / download ────────────────────────────────────────────────────

    async function copyJson(view) {
        const json = JSON.stringify(getPayload(view), null, 2);
        try {
            await navigator.clipboard.writeText(json);
            toast('JSON copied to clipboard.');
        } catch(e) {
            toast('Clipboard copy failed.');
        }
    }

    function downloadJson(view) {
        const names = { kg:'kg_sketch_job', lore:'lore_sketch_job', autopilot:'autopilot_job', extract:'md_curator_job', aggregate:'md_curator_job', narseq:'narseq_compose_job', tagextract:'sketch_tag_extract_job', github:'github_sync_job' };
        const blob = new Blob([JSON.stringify(getPayload(view), null, 2)], { type:'application/json' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = (names[view] || 'forge_job') + '.json';
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    }

    // ── Event binding ─────────────────────────────────────────────────────

    function bindEvents() {
        // KG mode toggle
        document.querySelectorAll('input[name="kg_mode"]').forEach(el => {
            el.addEventListener('change', () => {
                const mode = parseInt(document.querySelector('input[name="kg_mode"]:checked').value, 10);
                const catBlock = document.getElementById('kg-cat-block');
                if (catBlock) catBlock.style.display = mode === 1 ? '' : 'none';
                loadKgMeta();
            });
        });

        // KG category change
        const kgCatSel = document.getElementById('kg_category_id');
        if (kgCatSel) kgCatSel.addEventListener('change', loadKgMeta);

        // Lore doc change
        const loreDocSel = document.getElementById('lore_doc_id');
        if (loreDocSel) loreDocSel.addEventListener('change', loadLoreMeta);

        // Generic live JSON update for all field changes
        const liveIds = {
            kg: ['kg_node_type','kg_history_filter','kg_offset','kg_amount','kg_generator_config_id','kg_tags','kg_delay_us','kg_confirm','kg_dry_run'],
            lore: ['lore_group_key','lore_offset','lore_amount','lore_generator_config_id','lore_tags','lore_delay_us','lore_confirm','lore_dry_run'],
            autopilot: ['ap_desc_gen_id','ap_amount'],
            extract: ['ext_targetCategoryId','ext_limit','ext_charLimit','ext_overrideModel'],
            aggregate: ['agg_targetCategoryId','agg_limit'],
            narseq: ['narseq_sequence_id','narseq_rerun','narseq_override_model'],
            tagextract: ['tagextract_from','tagextract_to','tagextract_batch','tagextract_dry_run'],
            github: ['github_repo_path','github_branch_name','github_remote_name','github_commit_message','github_add_all','github_commit','github_push','github_pull_rebase','github_amend','github_allow_empty','github_dry_run','github_force_push','github_git_user_name','github_git_user_email'],
            translation: ['translation_cinemagic_id','translation_sequence_id','translation_lang','translation_rerun'],
            overlay:     ['overlay_cinemagic_id','overlay_sequence_id','overlay_sketch_id','overlay_rerun'],

            

        };

        for (const [view, ids] of Object.entries(liveIds)) {
            for (const id of ids) {
                const el = document.getElementById(id);
                if (!el) continue;
                const ev = (el.type === 'checkbox' || el.type === 'radio') ? 'change' : 'input';
                el.addEventListener(ev, () => updateJson(view));
            }
        }
    }
    
    
    
       
       // ── Overlay meta ──────────────────────────────────────────────────────

    async function loadOverlayMeta() {
        const data = await get('action=overlay_meta');
        if (!data.ok) return;

        const cmSel = document.getElementById('overlay_cinemagic_id');
        if (cmSel && data.cinemagics) {
            const prev = cmSel.value;
            cmSel.innerHTML = '<option value="0">— All sequences (no cinemagic filter) —</option>'
                + (data.cinemagics || []).map(c =>
                    `<option value="${esc(c.id)}">[#${esc(c.id)}] ${esc(c.name)}</option>`
                ).join('');
            if (prev && [...cmSel.options].some(o => o.value === prev)) cmSel.value = prev;
        }

        _overlayAllSequences = data.sequences || [];
        _overlayPopulateSequences(_overlayAllSequences);
        updateJson('overlay');
    }

    let _overlayAllSequences = [];

    function _overlayPopulateSequences(list) {
        const seqSel = document.getElementById('overlay_sequence_id');
        if (!seqSel) return;
        const prev = seqSel.value;
        seqSel.innerHTML = '<option value="0">— All sequences in scope —</option>'
            + list.map(s =>
                `<option value="${esc(s.id)}">[#${esc(s.id)}] ${esc(s.name)}</option>`
            ).join('');
        if (prev && [...seqSel.options].some(o => o.value === prev)) seqSel.value = prev;
    }

    function overlayModeChange() {
        const mode = document.querySelector('input[name="overlay_mode"]:checked')?.value || 'cinemagic';
        document.getElementById('overlay-cinemagic-fields').style.display = mode === 'cinemagic' ? '' : 'none';
        document.getElementById('overlay-sketch-fields').style.display    = mode === 'sketch'    ? '' : 'none';
        updateJson('overlay');
    }

    async function overlayLoadSequences() {
        const cinId = parseInt(document.getElementById('overlay_cinemagic_id')?.value || '0', 10);
        if (cinId === 0) {
            _overlayPopulateSequences(_overlayAllSequences);
        } else {
            const data = await get('action=overlay_sequences&cinemagic_id=' + cinId);
            if (data.ok) _overlayPopulateSequences(data.sequences || []);
        }
        updateJson('overlay');
    }

    
    
    
    
    
    
   // ── Translation meta ──────────────────────────────────────────────────

    async function loadTranslationMeta() {
        const data = await get('action=translation_meta');
        if (!data.ok) return;

        const cmSel = document.getElementById('translation_cinemagic_id');
        if (cmSel && data.cinemagics) {
            const prev = cmSel.value;
            cmSel.innerHTML = '<option value="0">— All cinemagic sequences —</option>'
                + (data.cinemagics || []).map(c =>
                    `<option value="${esc(c.id)}">[#${esc(c.id)}] ${esc(c.name)}</option>`
                ).join('');
            if (prev && [...cmSel.options].some(o => o.value === prev)) cmSel.value = prev;
        }

        const seqSel = document.getElementById('translation_sequence_id');
        if (seqSel && data.sequences) {
            const prev = seqSel.value;
            seqSel.innerHTML = '<option value="0">— All sequences in cinemagic —</option>'
                + (data.sequences || []).map(s =>
                    `<option value="${esc(s.id)}">[#${esc(s.id)}] ${esc(s.name)}</option>`
                ).join('');
            if (prev && [...seqSel.options].some(o => o.value === prev)) seqSel.value = prev;
        }

        const langSel = document.getElementById('translation_lang');
        if (langSel && data.languages) {
            const prev = langSel.value;
            langSel.innerHTML = '<option value="">— Select language —</option>'
                + (data.languages || []).map(l =>
                    `<option value="${esc(l.code)}">[${esc(l.code)}] ${esc(l.name)}</option>`
                ).join('');
            if (prev && [...langSel.options].some(o => o.value === prev)) langSel.value = prev;
        }

        updateJson('translation');
    }
    
    

    // ── Init ─────────────────────────────────────────────────────────────

    async function init() {
        bindEvents();
        updateBottomBar();

        // Load meta for all views in background
        loadKgMeta();
        loadLoreMeta();
        loadAutopilotMeta();
        loadCuratorMeta('extract');
        loadNarseqMeta();
        loadTagextractMeta();
        loadTranslationMeta();
        loadOverlayMeta();
        
        
        

        // Load queue badges for all views
        for (const v of VIEWS) refreshQueueBadge(v);

        // Initial JSON renders
        for (const v of VIEWS) updateJson(v);
    }

    return {
        init,
        switchView,
        switchPanel,
        switchPanelById,
        updateJson,
        queueJob,
        cancelJob,
        deleteJob,
        requeueJob,
        setPriority,
        refreshQueue,
        refreshQueueBadge,
        goQueuePage,
        copyJson,
        downloadJson,
        toggleTheme,
        overlayModeChange,
        overlayLoadSequences,
    };
})();

document.addEventListener('DOMContentLoaded', () => Hub.init());
</script>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>


<?php echo $eruda ?? ''; ?>


</body>
</html>

