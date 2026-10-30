<?php
// public/sage.php
// ─────────────────────────────────────────────────────────────────────────────
// SAGE — Lean Configurable Dashboard
// Fullscreen derivation of forge_tool UI with multi-profile support.
// Integrated Native Scheduler Runner (Control Deck) with Drag-and-Drop Reordering
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/bootstrap.php';
global $pdo;

// ── 1. DB INITIALIZATION & MIGRATIONS ──



// ── 1. DB INITIALIZATION & PRE-SEEDING ──

// Profile settings table
$pdo->exec("CREATE TABLE IF NOT EXISTS `sage_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profile_name` varchar(100) NOT NULL DEFAULT 'Default',
  `settings_json` longtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `profile_name` (`profile_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

// Task wrappers table (Profile-specific Control Deck)
$pdo->exec("CREATE TABLE IF NOT EXISTS `sage_task_wrappers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profile_name` varchar(100) NOT NULL DEFAULT 'Default',
  `task_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `summary` varchar(255) DEFAULT NULL,
  `fixed_args` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT '🚀',
  `display_order` int(11) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_profile` (`profile_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

// Pre-seed Default Profile in sage_settings if empty
$stmt = $pdo->query("SELECT COUNT(*) FROM sage_settings");
if ($stmt->fetchColumn() == 0) {
    $defaultSettings = [
        "shortcuts" => [
            ["icon" => "🪄", "label" => "Sketches", "url" => "/view_map_runs.php", "modal" => true, "fullscreen" => true, "visible" => true],
            ["icon" => "📁", "label" => "CRUZZ", "url" => "/cruzz.php", "modal" => true, "fullscreen" => true, "visible" => true],
            ["icon" => "✨", "label" => "Enhanimatics", "url" => "/enhanimaticism.php", "modal" => false, "fullscreen" => false, "visible" => true],
            ["icon" => "🪞", "label" => "Continuity", "url" => "/continuity_crud.php", "modal" => true, "fullscreen" => true, "visible" => true],
            ["icon" => "♻️", "label" => "Regen", "url" => "/regenerate_frames_set.php", "modal" => true, "fullscreen" => true, "visible" => true],
            ["icon" => "🎞", "label" => "NARSEQ", "url" => "/narseq.php", "modal" => false, "fullscreen" => false, "visible" => true],
            ["icon" => "🃏", "label" => "CineMagic Editor", "url" => "/cinemagic_editor.php", "modal" => true, "fullscreen" => true, "visible" => true],
            ["icon" => "🎴", "label" => "Magazines", "url" => "/cinemagic_hub/index.php", "modal" => false, "fullscreen" => false, "visible" => true],
            ["icon" => "🗯️", "label" => "Bang", "url" => "/bang.php", "modal" => false, "fullscreen" => false, "visible" => true],
            ["icon" => "▦", "label" => "Griditor", "url" => "/griditor.php", "modal" => true, "fullscreen" => true, "visible" => true],
            ["icon" => "👀", "label" => "CV Batch", "url" => "/cvbatch.php", "modal" => false, "fullscreen" => false, "visible" => true],
            ["icon" => "🍅", "label" => "Sketchup", "url" => "/sketchup.php", "modal" => true, "fullscreen" => true, "visible" => true],
            ["icon" => "📖", "label" => "Scripts", "url" => "/view_md_categories.php", "modal" => false, "fullscreen" => false, "visible" => true],
            ["icon" => "🧜‍♀", "label" => "Mermaids", "url" => "/mermaids.php", "modal" => false, "fullscreen" => false, "visible" => true],
            ["icon" => "⌖", "label" => "AIM", "url" => "/ai_idea_manager.php", "modal" => false, "fullscreen" => false, "visible" => true],
            ["icon" => "📜", "label" => "KG Narrative", "url" => "/kg_narrative.php", "modal" => true, "fullscreen" => true, "visible" => true],
            ["icon" => "☀️", "label" => "KG Bible", "url" => "/view_kg_docs.php", "modal" => false, "fullscreen" => false, "visible" => true],
            ["icon" => "🌳", "label" => "KG", "url" => "/kg_view.php", "modal" => false, "fullscreen" => false, "visible" => true],
            ["icon" => "⚛️", "label" => "KG Graph", "url" => "/kg_graph.php", "modal" => false, "fullscreen" => false, "visible" => true],
            ["icon" => "⚗", "label" => "KG Graph Filter", "url" => "/kg_filter.php", "modal" => true, "fullscreen" => true, "visible" => true],
            ["icon" => "🛢️", "label" => "DB Tool", "url" => "/dbtool.php", "modal" => false, "fullscreen" => false, "visible" => true],
            ["icon" => "💾", "label" => "Backup", "url" => "/backup_forge.php", "modal" => false, "fullscreen" => false, "visible" => true],
            ["icon" => "📰", "label" => "PR Hub", "url" => "/content_hub/index.php", "modal" => false, "fullscreen" => false, "visible" => true],
            ["icon" => "💭", "label" => "Pytoon", "url" => "/pytoon/index.php", "modal" => false, "fullscreen" => false, "visible" => true],
            ["icon" => "⛲", "label" => "Entity Viewer", "url" => "/entity_viewer.php?type=sketches&mode=entity&page=1", "modal" => false, "fullscreen" => false, "visible" => true],
            ["icon" => "🏗️", "label" => "Mass Image Upload", "url" => "/view_mass_upload.php", "modal" => true, "fullscreen" => true, "visible" => true],
            ["icon" => "🏖️", "label" => "2Sketch Migration", "url" => "/sketchmig.php", "modal" => true, "fullscreen" => true, "visible" => true],
            ["icon" => "👤", "label" => "Profile", "url" => "/view_profile.php", "modal" => true, "fullscreen" => true, "visible" => true],
            ["icon" => "🔮", "label" => "Sketches AI", "url" => "/sketches_manager.php", "modal" => true, "fullscreen" => true, "visible" => true],
            ["icon" => "🏞️", "label" => "KG AI", "url" => "/kg_manager.php", "modal" => true, "fullscreen" => true, "visible" => true],
            ["icon" => "🥫", "label" => "Code Recipes", "url" => "/recipe_forge.php", "modal" => true, "fullscreen" => true, "visible" => false]
        ],
        "custom_tabs" => [],
        "panel_height" => 81,
        "cb_area" => "global",
        "creatives_visibility" => [
            "vehicles" => false,
            "artifacts" => false,
            "backgrounds" => false,
            "locations" => true,
            "animas" => false,
            "character_expressions" => false,
            "character_anima_poses" => false,
            "character_poses" => false,
            "controlnet" => false,
            "blueprints" => false,
            "spawns" => true,
            "dimensionals" => false,
            "meshes" => false,
            "animatics" => false
        ]
    ];
    $stmtInsert = $pdo->prepare("INSERT INTO sage_settings (profile_name, settings_json) VALUES ('Default', ?)");
    $stmtInsert->execute([json_encode($defaultSettings, JSON_UNESCAPED_UNICODE)]);
}

// Pre-seed Default Task Wrappers in sage_task_wrappers if empty
$stmt = $pdo->query("SELECT COUNT(*) FROM sage_task_wrappers");
if ($stmt->fetchColumn() == 0) {
    $wrappersSeed = [
        ['Default', 63, 'QUEUE WORKER FRAMES', 'Start genworker queue', '7', '🌪️', 0, 1],
        ['Default', 15, 'SKETCHES', 'Start genframe sketches', 'sketches', '🪄', 1, 1],
        ['Default', 48, 'ENHANCE SKETCHES', 'genhance sketches', 'sketches', '✨', 2, 1],
        ['Default', 61, 'ENQ SKETCHES', 'Enqueue Sketches ', 'sketches', '🌀 ⨠', 3, 1],
        ['Default', 61, 'ENQ DIVERSE', 'Enqueue Diverse', 'locations', '🌀 ⨠', 4, 1],
        ['Default', 59, 'ENQ GENHANCES', 'Enqueue Genhances Sketches', '', '✨ ⨠', 5, 1],
        ['Default', 48, 'GENHANCE DIV', 'Enhance Diverse', 'generatives', '✨', 6, 1],
        ['Default', 24, 'COMPOSITES', 'Start genframe composites', 'composites', '🧩', 7, 1],
        ['Default', 10, 'GENERATIVES', 'Start genframe generatives', 'generatives', '⚡', 8, 1],
        ['Default', 53, 'CANCEL ❌ GENFRAMES ', 'Cancel genframes', '', '❌', 9, 1],
        ['Default', 88, 'CANCEL ❌ GENWORKER', 'Cancel genworker', '', '❌', 10, 1],
    ];
    $insertWrap = $pdo->prepare("INSERT INTO sage_task_wrappers (profile_name, task_id, name, summary, fixed_args, icon, display_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($wrappersSeed as $w) {
        $insertWrap->execute($w);
    }
}




// ── 2. API HANDLER ──
if (isset($_GET['sage_api'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    $action = $_GET['sage_api'];
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];

    // Dashboard Profiles
    if ($action === 'save_settings') {
        try {
            $profile = trim($input['profile_name'] ?? 'Default');
            if (!$profile) $profile = 'Default';
            $json = json_encode($input['settings'] ?? [], JSON_UNESCAPED_UNICODE);
            $stmt = $pdo->prepare("INSERT INTO sage_settings (profile_name, settings_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE settings_json = ?");
            $stmt->execute([$profile, $json, $json]);
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) { echo json_encode(['ok' => false, 'error' => $e->getMessage()]); }
        exit;
    }
    if ($action === 'get_profiles') {
        $stmt = $pdo->query("SELECT profile_name FROM sage_settings ORDER BY profile_name ASC");
        echo json_encode(['ok' => true, 'profiles' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
        exit;
    }
    if ($action === 'get_settings') {
        $profile = trim($_GET['profile_name'] ?? 'Default');
        $stmt = $pdo->prepare("SELECT settings_json FROM sage_settings WHERE profile_name = ?");
        $stmt->execute([$profile]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row ? ['ok' => true, 'data' => json_decode($row['settings_json'], true)] : ['ok' => false, 'error' => 'Profile not found']);
        exit;
    }

    // Scheduler (Control Deck)
    if ($action === 'get_wrappers') {
        $profile = trim($_POST['profile_name'] ?? 'Default');
        $stmt = $pdo->prepare("SELECT w.*, t.name as original_task_name, t.args as def_args FROM sage_task_wrappers w LEFT JOIN scheduled_tasks t ON w.task_id = t.id WHERE w.profile_name = ? ORDER BY w.display_order ASC, w.id DESC");
        $stmt->execute([$profile]);
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    if ($action === 'run_wrapper') {
        $wrapperId = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT w.*, t.name as task_name FROM sage_task_wrappers w JOIN scheduled_tasks t ON w.task_id = t.id WHERE w.id = :id");
        $stmt->execute(['id' => $wrapperId]);
        $wrapper = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wrapper) { echo json_encode(['ok'=>false,'error'=>'Wrapper not found']); exit; }

        $upd = $pdo->prepare("UPDATE scheduled_tasks SET args = :args, run_now = 1 WHERE id = :tid");
        $upd->execute(['args' => $wrapper['fixed_args'], 'tid' => $wrapper['task_id']]);
        echo json_encode(['ok' => true, 'msg' => "Queued: {$wrapper['name']}"]);
        exit;
    }
    if ($action === 'save_wrapper') {
        $id = (int)($_POST['wrapper_id'] ?? 0);
        $data = [
            'profile_name'  => trim($_POST['profile_name'] ?? 'Default'),
            'task_id'       => (int)$_POST['task_id'],
            'name'          => $_POST['name'],
            'summary'       => $_POST['summary'],
            'fixed_args'    => $_POST['fixed_args'],
            'icon'          => $_POST['icon'] ?? '🚀',
            'display_order' => (int)$_POST['display_order']
        ];
        if ($id > 0) {
            $sql = "UPDATE sage_task_wrappers SET profile_name=:profile_name, task_id=:task_id, name=:name, summary=:summary, fixed_args=:fixed_args, icon=:icon, display_order=:display_order WHERE id=$id";
            $pdo->prepare($sql)->execute($data);
        } else {
            $sql = "INSERT INTO sage_task_wrappers (profile_name, task_id, name, summary, fixed_args, icon, display_order) VALUES (:profile_name, :task_id, :name, :summary, :fixed_args, :icon, :display_order)";
            $pdo->prepare($sql)->execute($data);
        }
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($action === 'reorder_wrappers') {
        $orders = $input['orders'] ?? [];
        if (empty($orders) && isset($_POST['orders'])) {
            $orders = json_decode($_POST['orders'], true) ?? [];
        }
        if (!empty($orders)) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE sage_task_wrappers SET display_order = ? WHERE id = ?");
            foreach ($orders as $row) {
                $stmt->execute([(int)$row['display_order'], (int)$row['id']]);
            }
            $pdo->commit();
        }
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($action === 'delete_wrapper') {
        $pdo->prepare("DELETE FROM sage_task_wrappers WHERE id = ?")->execute([(int)$_POST['id']]);
        echo json_encode(['ok' => true]);
        exit;
    }
    
    
    
    if ($action === 'copy_shortcuts') {
        $src = trim($_POST['source_profile'] ?? '');
        $dst = trim($_POST['dest_profile'] ?? '');
        if (!$src || !$dst || $src === $dst) {
            echo json_encode(['ok' => false, 'error' => 'Invalid source or destination profiles']);
            exit;
        }
        try {
            // Fetch source profile shortcuts
            $stmt = $pdo->prepare("SELECT settings_json FROM sage_settings WHERE profile_name = ?");
            $stmt->execute([$src]);
            $srcRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$srcRow) throw new Exception("Source profile not found");
            $srcSettings = json_decode($srcRow['settings_json'], true) ?? [];
            $srcShortcuts = $srcSettings['shortcuts'] ?? [];

            // Fetch destination profile settings
            $stmt->execute([$dst]);
            $dstRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$dstRow) throw new Exception("Destination profile not found");
            $dstSettings = json_decode($dstRow['settings_json'], true) ?? [];

            // Overwrite shortcuts in destination profile
            $dstSettings['shortcuts'] = $srcShortcuts;

            $upd = $pdo->prepare("UPDATE sage_settings SET settings_json = ? WHERE profile_name = ?");
            $upd->execute([json_encode($dstSettings, JSON_UNESCAPED_UNICODE), $dst]);

            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    
    if ($action === 'copy_wrappers') {
        $src = trim($_POST['source_profile'] ?? '');
        $dst = trim($_POST['dest_profile'] ?? '');
        if (!$src || !$dst || $src === $dst) {
            echo json_encode(['ok' => false, 'error' => 'Invalid source or destination profiles']);
            exit;
        }
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM sage_task_wrappers WHERE profile_name = ?");
            $stmt->execute([$dst]);
            $stmt = $pdo->prepare("
                INSERT INTO sage_task_wrappers (profile_name, task_id, name, summary, fixed_args, icon, display_order, active)
                SELECT ?, task_id, name, summary, fixed_args, icon, display_order, active 
                FROM sage_task_wrappers 
                WHERE profile_name = ?
            ");
            $stmt->execute([$dst, $src]);
            $pdo->commit();
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    if ($action === 'list_logs') {
        $logsDir = __DIR__ . '/../logs';
        $files = glob($logsDir . '/*.log');
        $files = array_filter($files, function($f) {
            $base = basename($f);
            if (stripos($base, 'err') !== false) return false;
            if (stripos($base, '_exit.log') !== false) return false;
            return true;
        });
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        $files = array_slice($files, 0, 50);
        $data = array_map(fn($f) => [
            'name' => basename($f),
            'ts'   => filemtime($f),
            'time' => date('H:i:s', filemtime($f))
        ], $files);
        echo json_encode(['ok' => true, 'files' => $data]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit;
}

// Global tasks list used directly in the HTML Modal
$allTasks = $pdo->query("SELECT id, name, args FROM scheduled_tasks ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.7, viewport-fit=cover">
    <title>SAGE | Lean Dashboard</title>
    <script>
        (function() {
            try {
                var theme = localStorage.getItem('spw_theme');
                if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
                else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="/css/base.css">
    
    <!-- SortableJS for drag-to-reorder -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

    <style>
    /* ── CSS VARIABLES ─────────────────────────────────────────────────── */
    :root {
        --ft-bg:          #080c12;
        --ft-surface:     #0d1219;
        --ft-card:        #111820;
        --ft-border:      #1c2636;
        --ft-border-hi:   #2a3a52;
        --ft-text:        #c8d4e8;
        --ft-text-dim:    #4a5a70;
        --ft-text-hi:     #e8f2ff;
        --ft-amber:       #f5a623;
        --ft-amber-dim:   rgba(245,166,35,0.08);
        --ft-amber-glow:  rgba(245,166,35,0.35);
        --ft-teal:        #14b8a6;
        --ft-teal-dim:    rgba(20,184,166,0.1);
        --ft-purple:      #8b5cf6;
        --ft-purple-dim:  rgba(139,92,246,0.1);
        --ft-red:         #f05060;
        --ft-red-dim:     rgba(240,80,96,0.1);
        --ft-green:       #22d3a0;
        --ft-green-dim:   rgba(34,211,160,0.08);
        --ft-mono:        'Space Mono', 'DM Mono', 'Fira Mono', monospace;
        --ft-sans:        'Syne', 'DM Sans', system-ui, sans-serif;
        --ft-radius:      8px;
        --ft-radius-lg:   14px;
        --ft-tab-h:       48px;
        --ft-z:           100;
    }

    [data-theme="light"] {
        --ft-bg:          #f0f4fa;
        --ft-surface:     #e6eaf2;
        --ft-card:        #ffffff;
        --ft-border:      #c8d0de;
        --ft-border-hi:   #a0aec0;
        --ft-text:        #2d3748;
        --ft-text-dim:    #718096;
        --ft-text-hi:     #1a202c;
        --ft-amber:       #d97706;
        --ft-amber-dim:   rgba(217,119,6,0.08);
        --ft-amber-glow:  rgba(217,119,6,0.30);
        --ft-teal:        #0d9488;
        --ft-teal-dim:    rgba(13,148,136,0.10);
        --ft-purple:      #7c3aed;
        --ft-purple-dim:  rgba(124,58,237,0.10);
        --ft-red:         #dc2626;
        --ft-red-dim:     rgba(220,38,38,0.08);
        --ft-green:       #059669;
        --ft-green-dim:   rgba(5,150,105,0.08);
    }

    *, *::before, *::after { box-sizing: border-box; }
    html, body { 
        margin: 0; padding: 0; width: 100vw; height: 100vh; height: 100dvh; overflow: hidden; 
        background: var(--ft-bg); color: var(--ft-text); font-family: var(--ft-sans);
    }

    /* ── HEADER IMAGE ── */
    .dashboard-header-image { flex-shrink: 0; position: relative; text-align: center; background: #000; }
    .dashboard-header-image img { width: 100%; max-height: 400px; object-fit: cover; display: block; }

    /* ── LAYOUT ── */
    .sage-layout { display: flex; flex-direction: column; width: 100%; height: 100%; padding-bottom: env(safe-area-inset-bottom, 0px); }
    
    .ft-tab-bar {
        flex-shrink: 0; height: var(--ft-tab-h); display: flex; align-items: stretch;
        background: var(--ft-surface); border-bottom: 1px solid var(--ft-border);
        overflow-x: auto; overflow-y: hidden; scrollbar-width: none; -webkit-overflow-scrolling: touch;
    }
    .ft-tab-bar::-webkit-scrollbar { display: none; }
    .ft-tab {
        flex-shrink: 0; display: flex; align-items: center; gap: 6px; padding: 0 16px;
        font-family: var(--ft-mono); font-size: 0.75rem; font-weight: 700;
        letter-spacing: 0.8px; text-transform: uppercase; color: var(--ft-text-dim);
        cursor: pointer; border: none; background: transparent; border-bottom: 2px solid transparent;
        white-space: nowrap; transition: all 0.15s;
    }
    .ft-tab:hover { color: var(--ft-text); background: rgba(255,255,255,0.03); }
    .ft-tab.active { color: var(--ft-amber); border-bottom-color: var(--ft-amber); background: var(--ft-amber-dim); }
    .ft-tab-icon { font-size: 16px; }
    .ft-tab-close {
        width: 16px; height: 16px; border-radius: 3px; border: none; background: transparent;
        color: var(--ft-text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center;
        font-size: 10px; padding: 0; margin-left: 2px; transition: color 0.12s, background 0.12s;
    }
    .ft-tab-close:hover { color: var(--ft-red); background: var(--ft-red-dim); }
    
    /* Notification Badge */
    .chatty-badge {
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--ft-red); color: #fff; font-family: var(--ft-mono); font-size: 0.65rem; font-weight: 700;
        border-radius: 10px; padding: 0 5px; min-width: 18px; height: 18px; margin-left: 4px;
        animation: popIn 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    @keyframes popIn { 0% { transform: scale(0.5); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

    .sage-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; }
    .ft-tab-content { flex: 1; display: none; flex-direction: column; overflow: hidden; }
    .ft-tab-content.active { display: flex; }
    .ft-iframe-content { flex: 1; display: none; flex-direction: column; overflow: hidden; }
    .ft-iframe-content.active { display: flex; }
    .ft-iframe-content iframe { flex: 1; width: 100%; height: 100%; border: none; background: var(--ft-bg); display: block; }

    /* ── SHORTCUTS TAB ── */
    .ft-shortcuts-grid-wrap { flex: 1; overflow-y: auto; padding: 20px; }
    .ft-shortcuts-grid { display: grid; gap: 14px; grid-template-columns: repeat(3, 1fr); }
    @media (min-width: 480px) { .ft-shortcuts-grid { grid-template-columns: repeat(4, 1fr); } }
    @media (min-width: 768px) { .ft-shortcuts-grid { grid-template-columns: repeat(6, 1fr); } }
    @media (min-width: 1024px) { .ft-shortcuts-grid { grid-template-columns: repeat(8, 1fr); } }

    .ft-shortcut-btn {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 8px; padding: 18px 8px; background: var(--ft-card); border: 1px solid var(--ft-border);
        border-radius: var(--ft-radius); cursor: pointer; transition: all 0.15s; text-decoration: none;
        -webkit-tap-highlight-color: transparent; min-height: 85px; position: relative; overflow: hidden;
    }
    .ft-shortcut-btn::before {
        content: ''; position: absolute; inset: 0;
        background: radial-gradient(circle at 50% 0%, var(--ft-amber-dim), transparent 70%);
        opacity: 0; transition: opacity 0.2s;
    }
    .ft-shortcut-btn:hover { border-color: var(--ft-amber); transform: translateY(-2px); }
    .ft-shortcut-btn:hover::before { opacity: 1; }
    .ft-shortcut-btn:active { transform: scale(0.95); }
    .ft-shortcut-icon { font-size: 26px; line-height: 1; }
    .ft-shortcut-label {
        font-family: var(--ft-mono); font-size: 0.65rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.8px; color: var(--ft-text-dim); text-align: center; line-height: 1.2; word-break: break-word;
    }
    .ft-shortcut-btn:hover .ft-shortcut-label { color: var(--ft-amber); }

    /* ── NATIVE SCHEDULER TAB ── */
    .sage-console-wrapper {
        flex-shrink: 0;
        border-radius: var(--ft-radius); 
        overflow: hidden;
        border: 1px solid var(--ft-border-hi); 
        background: var(--ft-card);
        margin-bottom: 20px; 
        display: flex; 
        flex-direction: column;
    }
    .sage-console-header {
        background: var(--ft-surface); 
        color: var(--ft-text);
        padding: 10px 14px; 
        font-size: 0.85rem; 
        font-family: var(--ft-mono);
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        gap: 10px;
        cursor: pointer; 
        user-select: none; 
        border-bottom: 1px solid transparent;
    }
    .sage-console-wrapper.open .sage-console-header { 
        border-bottom-color: var(--ft-border); 
    }
    .sage-log-box {
        display: none; 
        background: #000; 
        color: #4ade80; 
        font-family: var(--ft-mono); 
        font-size: 0.75rem; 
        line-height: 1.4;
        white-space: pre-wrap; 
        overflow-y: auto; 
        height: 260px;
        max-height: 45vh;
        padding: 12px;
    }
    .sage-console-wrapper.open .sage-log-box,
    .sage-log-box.expanded { 
        display: block !important; 
    }
    
    .pulse-green { animation: pulse-green 1.5s infinite; }
    @keyframes pulse-green { 0% { box-shadow: 0 0 0 0 rgba(34, 211, 160, 0.4); } 70% { box-shadow: 0 0 0 8px rgba(34, 211, 160, 0); } 100% { box-shadow: 0 0 0 0 rgba(34, 211, 160, 0); } }

    .run-task-btn {
        background: var(--ft-amber); color: #000; border: none; border-radius: 5px;
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; cursor: pointer; transition: filter 0.2s; flex-shrink:0; margin-right:8px;
    }
    .run-task-btn:hover { filter: brightness(1.2); }

    /* ── LINK CARDS & MULTI BUTTONS (CREATIVES & ENTITIES) ── */
    .link-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 8px;
    }
    .multi-button-group {
        display: flex;
        background: var(--ft-card);
        border: 1px solid var(--ft-border);
        border-radius: var(--ft-radius);
        overflow: hidden;
        transition: all 0.15s;
    }
    .multi-button-group:hover {
        border-color: var(--ft-amber);
        transform: translateY(-2px);
    }
    .multi-button-main {
        flex: 1;
        padding: 10px 12px;
        text-align: left;
        text-decoration: none;
        color: var(--ft-text-hi);
        font-family: var(--ft-sans);
        font-weight: 600;
        font-size: 0.85rem;
        transition: background 0.15s, color 0.15s;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .multi-button-action {
        flex: 0 0 60px;
        padding: 0;
        text-align: center;
        text-decoration: none;
        color: var(--ft-text-dim);
        font-family: var(--ft-mono);
        font-size: 0.75rem;
        font-weight: 700;
        border: none;
        border-left: 1px solid var(--ft-border);
        background: transparent;
        transition: background 0.15s, color 0.15s;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    .multi-button-action.run-btn {
        flex: 0 0 45px;
        font-size: 0.95rem;
    }
    .multi-button-main:hover, .multi-button-action:hover {
        background: rgba(255,255,255,0.05);
        color: var(--ft-amber);
    }

    /* ── CLIPBOARD TAB ── */
    .ft-cb-layout { display: flex; flex-direction: column; height: 100%; overflow: hidden; }
    .ft-cb-add-row { flex-shrink: 0; padding: 12px 20px; background: var(--ft-surface); border-bottom: 1px solid var(--ft-border); display: flex; flex-direction: column; gap: 8px; }
    .ft-cb-input-row { display: flex; gap: 8px; }
    .ft-cb-input {
        flex: 1; padding: 10px 14px; border-radius: 6px; border: 1px solid var(--ft-border);
        background: rgba(0,0,0,0.4); color: var(--ft-text); font-family: var(--ft-mono); font-size: 0.82rem; min-width: 0; transition: border-color 0.15s; -webkit-appearance: none;
    }
    .ft-cb-input:focus { outline: none; border-color: var(--ft-teal); }
    .ft-cb-input.label { width: 120px; flex: none; }
    .ft-cb-add-btn {
        flex-shrink: 0; padding: 10px 18px; border-radius: 6px; border: none; background: var(--ft-teal); color: #000;
        font-family: var(--ft-mono); font-size: 0.78rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 4px; white-space: nowrap; transition: filter 0.15s;
    }
    .ft-cb-add-btn:hover { filter: brightness(1.1); }
    .ft-cb-list-wrap { flex: 1; overflow-y: auto; padding: 12px; }
    .ft-cb-list { display: flex; flex-direction: column; gap: 6px; list-style: none; margin: 0; padding: 0; }
    .ft-cb-item { background: var(--ft-card); border: 1px solid var(--ft-border); border-radius: 6px; padding: 10px 12px; display: flex; align-items: flex-start; gap: 10px; transition: border-color 0.15s; }
    .ft-cb-item.pinned { border-color: var(--ft-amber); }
    .ft-cb-item.sortable-ghost, .ft-shortcut-row.sortable-ghost { opacity: 0.3; }
    .ft-cb-item.sortable-chosen { border-color: var(--ft-teal); box-shadow: 0 0 0 1px var(--ft-teal); }
    .ft-shortcut-row.sortable-chosen { border-color: var(--ft-amber); box-shadow: 0 0 0 1px var(--ft-amber); }
    .ft-drag-handle { flex-shrink: 0; cursor: grab; color: var(--ft-text-dim); font-size: 18px; padding-top: 2px; touch-action: none; line-height: 1; }
    .ft-drag-handle:active { cursor: grabbing; }
    .ft-cb-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
    .ft-cb-label-row { display: flex; align-items: center; gap: 8px; }
    .ft-cb-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--ft-text-dim); }
    .ft-cb-label.has-label { color: var(--ft-amber); }
    .ft-cb-pin-badge { font-size: 0.6rem; color: var(--ft-amber); font-weight: 700; }
    .ft-cb-text { font-size: 0.8rem; color: var(--ft-text); line-height: 1.4; word-break: break-word; cursor: pointer; padding: 2px 4px; border-radius: 3px; border: 1px solid transparent; transition: border-color 0.12s; }
    .ft-cb-text:hover { border-color: var(--ft-border); }
    .ft-cb-text-edit { display: none; width: 100%; padding: 6px 9px; border-radius: 4px; border: 1px solid var(--ft-teal); background: rgba(0,0,0,0.4); color: var(--ft-text); font-family: var(--ft-mono); font-size: 0.8rem; resize: vertical; line-height: 1.4; -webkit-appearance: none; }
    .ft-cb-text-edit.active { display: block; }
    .ft-cb-text.editing { display: none; }
    .ft-cb-label-input { display: none; width: 100%; padding: 5px 9px; border-radius: 4px; border: 1px solid var(--ft-border); background: rgba(0,0,0,0.4); color: var(--ft-text-dim); font-family: var(--ft-mono); font-size: 0.75rem; -webkit-appearance: none; }
    .ft-cb-label-input.active { display: block; }
    .ft-cb-actions { display: flex; gap: 6px; flex-shrink: 0; align-items: flex-start; }
    .ft-ia { width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--ft-border); background: transparent; color: var(--ft-text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; transition: all 0.12s; flex-shrink: 0; -webkit-tap-highlight-color: transparent; }
    .ft-ia:hover { color: var(--ft-text); border-color: var(--ft-border-hi); }
    .ft-ia.pin:hover, .ft-ia.pin.on { color: var(--ft-amber); border-color: var(--ft-amber); background: var(--ft-amber-dim); }
    .ft-ia.edit:hover, .ft-ia.edit.on { color: var(--ft-teal); border-color: var(--ft-teal); background: var(--ft-teal-dim); }
    .ft-ia.del:hover { color: var(--ft-red); border-color: var(--ft-red); background: var(--ft-red-dim); }
    .ft-ia.save { display: none; color: var(--ft-teal); border-color: var(--ft-teal); }
    .ft-ia.save.on { display: flex; }
    .ft-ia.copy:hover { color: var(--ft-green); border-color: var(--ft-green); background: var(--ft-green-dim); }
    .ft-cb-empty { display: flex; align-items: center; justify-content: center; height: 100px; color: var(--ft-text-dim); font-family: var(--ft-mono); font-size: 0.78rem; font-style: italic; }

    /* ── SETTINGS TAB ── */
    .ft-settings-layout { display: flex; flex-direction: column; height: 100%; overflow: hidden; }
    .ft-settings-scroll { flex: 1; overflow-y: auto; padding: 20px; }
    .ft-settings-section { margin-bottom: 28px; }
    .ft-settings-section-title { font-family: var(--ft-mono); font-size: 0.72rem; font-weight: 700; color: var(--ft-amber); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
    .ft-settings-section-title::after { content: ''; flex: 1; height: 1px; background: var(--ft-border); }

    .ft-setting-row { display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: var(--ft-card); border: 1px solid var(--ft-border); border-radius: 6px; margin-bottom: 8px; transition: border-color 0.15s; }
    .ft-setting-row:hover { border-color: var(--ft-border-hi); }
    .ft-setting-label { flex: 1; font-family: var(--ft-mono); font-size: 0.82rem; color: var(--ft-text); min-width: 0; }
    .ft-setting-sub { font-size: 0.65rem; color: var(--ft-text-dim); margin-top: 3px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .ft-shortcut-list { display: flex; flex-direction: column; gap: 8px; }
    .ft-shortcut-row { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: var(--ft-card); border: 1px solid var(--ft-border); border-radius: 6px; transition: border-color 0.15s; }
    .ft-shortcut-row:hover { border-color: var(--ft-amber); }
    .ft-shortcut-row .ft-drag-handle { font-size: 16px; color: var(--ft-border-hi); }
    .ft-shortcut-row-icon { font-size: 20px; flex-shrink: 0; }
    .ft-shortcut-row-label { flex: 1; font-family: var(--ft-mono); font-size: 0.78rem; color: var(--ft-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ft-shortcut-row-url { font-size: 0.65rem; color: var(--ft-text-dim); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ft-shortcut-row-visible, .ft-shortcut-row-edit, .ft-custom-tab-edit, .ft-shortcut-row-del, .ft-custom-tab-del {
        flex-shrink: 0; width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--ft-border); background: transparent;
        color: var(--ft-text-dim); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; transition: all 0.12s;
    }
    .ft-shortcut-row-visible.on { color: var(--ft-teal); border-color: var(--ft-teal); background: var(--ft-teal-dim); }
    .ft-shortcut-row-edit:hover, .ft-custom-tab-edit:hover { color: var(--ft-teal); border-color: var(--ft-teal); background: var(--ft-teal-dim); }
    .ft-shortcut-row-del:hover, .ft-custom-tab-del:hover { color: var(--ft-red); border-color: var(--ft-red); background: var(--ft-red-dim); }

    .ft-add-shortcut-form { display: flex; flex-direction: column; gap: 8px; padding: 16px; background: var(--ft-card); border: 1px solid var(--ft-border); border-radius: 6px; margin-top: 12px; }
    .ft-add-form-row { display: flex; gap: 8px; }
    .ft-add-input { flex: 1; padding: 9px 12px; border-radius: 6px; border: 1px solid var(--ft-border); background: rgba(0,0,0,0.4); color: var(--ft-text); font-family: var(--ft-mono); font-size: 0.8rem; -webkit-appearance: none; transition: border-color 0.15s; }
    .ft-add-input:focus { outline: none; border-color: var(--ft-teal); }
    .ft-add-input.icon { width: 60px; flex: none; text-align: center; font-size: 1.2rem; }
    .ft-add-btn { flex-shrink: 0; padding: 9px 16px; border-radius: 6px; border: none; background: var(--ft-teal); color: #000; font-family: var(--ft-mono); font-size: 0.78rem; font-weight: 700; cursor: pointer; white-space: nowrap; display: flex; align-items: center; justify-content: center; gap: 4px; transition: filter 0.15s; }
    .ft-add-btn:hover { filter: brightness(1.1); }

    .ft-custom-tabs-list { display: flex; flex-direction: column; gap: 8px; }
    .ft-custom-tab-row { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: var(--ft-card); border: 1px solid var(--ft-border); border-radius: 6px; }
    .ft-custom-tab-icon { font-size: 18px; flex-shrink: 0; }
    .ft-custom-tab-info { flex: 1; min-width: 0; }
    .ft-custom-tab-name { font-family: var(--ft-mono); font-size: 0.78rem; color: var(--ft-text); }
    .ft-custom-tab-url { font-size: 0.65rem; color: var(--ft-text-dim); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .ft-settings-footer { flex-shrink: 0; padding: 16px 20px; border-top: 1px solid var(--ft-border); background: var(--ft-surface); display: flex; justify-content: flex-end; }
    .ft-settings-save-btn { padding: 10px 24px; border-radius: 6px; border: none; background: var(--ft-amber); color: #000; font-family: var(--ft-mono); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; cursor: pointer; transition: filter 0.15s; display: flex; align-items: center; gap: 6px; }
    .ft-settings-save-btn:hover { filter: brightness(1.12); }

    /* ── MODALS (General & Shortcuts) ── */
    .forge-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(3px); z-index: 10000; display: none; align-items: center; justify-content: center; padding: 3px; }
    .forge-modal-overlay.open { display: flex; animation: fadeIn 0.2s ease; }
    .forge-modal { background: var(--ft-surface); border: 1px solid var(--ft-border-hi); border-radius: var(--ft-radius-lg); width: 100%; display: flex; flex-direction: column; box-shadow: 0 6px 20px rgba(0,0,0,0.6); animation: modalIn 0.2s ease; }
    .forge-modal-header { padding: 10px 20px; border-bottom: 1px solid var(--ft-border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
    .forge-modal-title { font-family: var(--ft-mono); font-size: 0.9rem; font-weight: 700; color: var(--ft-amber); text-transform: uppercase; letter-spacing: 1px; }
    .forge-modal-close { width: 28px; height: 28px; border-radius: 4px; border: 1px solid var(--ft-border); background: transparent; color: var(--ft-text-dim); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; font-size: 14px; }
    .forge-modal-close:hover { border-color: var(--ft-red); color: var(--ft-red); background: var(--ft-red-dim); }
    .forge-modal-body { padding: 20px; overflow-y: auto; flex: 1; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes modalIn { from { opacity: 0; transform: scale(0.96) translateY(-10px); } to { opacity: 1; transform: none; } }

    
    .ft-shortcut-modal-overlay { position: fixed; inset: 0; background: var(--ft-bg); z-index: 99999; display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.2s; padding: 0; }
    
    .ft-shortcut-modal-overlay.open { opacity: 1; pointer-events: all; }
    .ft-shortcut-modal-box { width: 100%; height: 100%; background: var(--ft-bg); display: flex; flex-direction: column; transform: scale(0.98); transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1); position: relative; padding-top: 50px; }
    .ft-shortcut-modal-overlay.open .ft-shortcut-modal-box { transform: scale(1); }
    .ft-shortcut-modal-body { flex: 1; overflow: hidden; padding: 0; height: 100%; border-top: 1px solid var(--ft-border); }
    .ft-shortcut-modal-body iframe { width: 100%; height: 100%; border: none; display: block; background: var(--ft-bg); }
    .ft-sm-floating-btn { position: absolute; top: 6px; z-index: 100; height: 38px; border-radius: 8px; background: rgba(17,24,32,0.6); backdrop-filter: blur(8px); border: 1px solid var(--ft-border-hi); color: var(--ft-text-hi); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s; text-decoration: none; box-shadow: 0 4px 12px rgba(0,0,0,0.5); }
    .ft-sm-floating-btn:hover { background: var(--ft-surface); }
    .ft-sm-close { right: 12px; width: 38px; font-size: 18px; }
    .ft-sm-close:hover { color: var(--ft-red); border-color: var(--ft-red); background: var(--ft-red-dim); }
    .ft-sm-open { right: 58px; padding: 0 14px; font-family: var(--ft-mono); font-size: 12px; font-weight: 700; color: var(--ft-teal); }
    .ft-sm-open:hover { border-color: var(--ft-teal); background: var(--ft-teal-dim); }

    /* ── TOAST ── */
    .ft-toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(10px); background: var(--ft-teal); color: #000; padding: 8px 20px; border-radius: 20px; font-family: var(--ft-mono); font-size: 0.8rem; font-weight: 700; pointer-events: none; opacity: 0; transition: opacity 0.2s, transform 0.2s; z-index: calc(var(--ft-z) + 50); white-space: nowrap; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
    .ft-toast.err { background: var(--ft-red); color: #fff; }
    .ft-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    </style>
    
    <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
</head>
<body>

<div class="sage-layout">

    <!-- ── THEATRICAL HEADER ── -->
    <div class="dashboard-header-image">
        <img src="theatrical.jpg" alt="Starlight Guardians Theatrical Poster">
        <div style="position:absolute; top:12px; right:16px; display:flex; gap:8px; align-items:center;">
            <a href="dashdocs.php" title="Documentation Mode" style="background: none; border: none; font-size:22px; cursor:pointer; text-decoration: none; display: flex; align-items: center; justify-content: center; height: 36px; width: 36px;">✨</a>
            <button id="themeToggle" aria-pressed="false" title="Toggle theme" style="background: none; border: none; font-size:24px; cursor:pointer;">🌙</button>
        </div>
    </div>

    <!-- ── TAB BAR ── -->
    <div class="ft-tab-bar" id="ft-tab-bar" role="tablist">
        <button class="ft-tab active" data-tab="shortcuts" role="tab" title="Shortcuts"><span class="ft-tab-icon">⚡</span></button>
        <button class="ft-tab" data-tab="scheduler" role="tab" title="Scheduler"><span class="ft-tab-icon">🌀</span></button>
        <button class="ft-tab" data-tab="queue" role="tab" title="Queue"><span class="ft-tab-icon">☰</span></button>
        <button class="ft-tab" data-tab="logs" role="tab" title="Logs"><span class="ft-tab-icon">📒</span></button>
        <button class="ft-tab" data-tab="chatty" role="tab" title="Chatty"><span class="ft-tab-icon">💬</span></button>
        <button class="ft-tab" data-tab="clipboard" role="tab" title="Clipboard"><span class="ft-tab-icon">📋</span></button>
        
        <!-- Creatives & Entities Tab -->
        <button class="ft-tab" data-tab="creatives" role="tab" title="Creatives & Entities"><span class="ft-tab-icon">💡</span></button>
        
        <button class="ft-tab" data-tab="legacy" role="tab" title="Legacy Dashboard"><span class="ft-tab-icon">🧭</span></button>
        <button class="ft-tab" data-tab="settings" role="tab" title="Settings"><span class="ft-tab-icon">⚙</span></button>
        <!-- Dynamic tabs injected here by JS -->
    </div>

    <div class="sage-main">
        
        <!-- ── SHORTCUTS TAB ── -->
        <div class="ft-tab-content active" id="ft-tab-shortcuts" role="tabpanel">
            
            <!-- AI SEARCH INJECTION -->
            <div class="sage-shortcuts-search" style="padding: 12px 20px; border-bottom: 1px solid var(--ft-border); background: var(--ft-surface); flex-shrink: 0;">
                <?php require 'ai_search.php'; ?>
            </div>

            <div class="ft-shortcuts-grid-wrap">
                <div class="ft-shortcuts-grid" id="ft-shortcuts-grid">
                    <!-- Populated via JS -->
                </div>
            </div>
        </div>

        <!-- ── SCHEDULER TAB (NATIVE UI replacing iframe) ── -->
        <div class="ft-tab-content" id="ft-tab-scheduler" role="tabpanel">
            <div style="padding:20px; max-width:900px; margin:0 auto; width:100%; height:100%; overflow-y:auto; display:flex; flex-direction:column;">
                
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h2 style="margin:0; font-family:var(--ft-sans); font-size:1.1rem; color:var(--ft-amber); display:flex; align-items:center; gap:8px;">
                        <span style="font-size:1.4rem;">🚀</span> Control Deck
                    </h2>
                    <a href="scheduler_forge.php" target="_blank" class="ft-add-btn" style="text-decoration:none; background:var(--ft-surface); color:var(--ft-text); border:1px solid var(--ft-border);">Full Scheduler ↗</a>
                </div>

                <!-- Console Wrapper -->
                <div class="sage-console-wrapper" id="sageConsoleWrapper">
                    <div class="sage-console-header" onclick="SageApp.scheduler.toggleConsole(event)">
                        <div style="display:flex; align-items:center; gap:8px; flex:1; min-width:0;">
                            <span class="toggle-icon" id="sageToggleIcon" style="transition:transform 0.25s ease; display:inline-block;">▶</span>
                            <select id="sageLogFileSelect" style="background:var(--ft-bg); color:var(--ft-text); border:1px solid var(--ft-border); padding:4px 8px; border-radius:4px; font-family:var(--ft-mono); font-size:0.75rem; max-width:250px; outline:none;"></select>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
                            <label style="font-size:0.75rem; cursor:pointer; font-family:var(--ft-mono); display:flex; align-items:center; gap:4px; color:var(--ft-text-dim);">
                                <input type="checkbox" id="sageAutoScroll" checked style="accent-color:var(--ft-teal);"> Auto
                            </label>
                            <button class="ft-ia" id="sageRefreshLogBtn" style="width:28px;height:28px;" title="Refresh Log">↺</button>
                        </div>
                    </div>
                    <div class="sage-log-box" id="sageLogBox">Initializing...</div>
                </div>

                <!-- Add Preset Button -->
                <button class="ft-add-btn" onclick="SageApp.scheduler.openModal()" style="margin-bottom:12px; align-self:flex-start;">+ Add Preset</button>

                <!-- Wrapper List with Drag and Drop -->
                <div class="ft-shortcut-list" id="sageWrapperContainer" style="padding-bottom:40px;">
                    <!-- JS injected rows -->
                </div>
            </div>
        </div>

        <!-- ── QUEUE TAB (Iframe logic) ── -->
        <div class="ft-tab-content ft-iframe-content" id="ft-tab-queue" role="tabpanel">
            <iframe data-src="view_queue.php" allowfullscreen></iframe>
        </div>

        <!-- ── LOG TAB (Iframe logic) ── -->
        <div class="ft-tab-content ft-iframe-content" id="ft-tab-logs" role="tabpanel">
            <iframe data-src="view_scheduler_log.php" allowfullscreen></iframe>
        </div>
        
        <!-- ── CHATTY TAB (Iframe logic) ── -->
        <div class="ft-tab-content ft-iframe-content" id="ft-tab-chatty" role="tabpanel">
            <iframe data-src="chatty.php" allowfullscreen></iframe>
        </div>

        <!-- ── CLIPBOARD TAB ── -->
        <div class="ft-tab-content" id="ft-tab-clipboard" role="tabpanel">
            <div class="ft-cb-layout">
                <div class="ft-cb-add-row">
                    <div class="ft-cb-input-row">
                        <input class="ft-cb-input label" id="ft-cb-new-label" placeholder="Label…" maxlength="120" autocomplete="off">
                        <button class="ft-cb-add-btn" onclick="SageApp.cb.add()">+ Add</button>
                    </div>
                    <input class="ft-cb-input" id="ft-cb-new-content" placeholder="Paste or type your clip here…" maxlength="4000" autocomplete="off"
                           onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();SageApp.cb.add();}">
                </div>
                <div class="ft-cb-list-wrap">
                    <ul class="ft-cb-list" id="ft-cb-list">
                        <li class="ft-cb-empty">Loading…</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ── CREATIVES & ENTITIES TAB (💡) ── -->
        <div class="ft-tab-content" id="ft-tab-creatives" role="tabpanel">
            <div style="padding:20px; max-width:1100px; margin:0 auto; width:100%; height:100%; overflow-y:auto;">
                
                <div class="ft-settings-section-title" id="sec-title-creatives" style="margin-bottom:14px;">💡 Creatives</div>
                <div class="link-grid" style="margin-bottom:30px;">
                    
                    <div class="multi-button-group" data-item-key="sketches">
                        <a href="view_map_runs_sketches.php" class="multi-button-main">🪄 Sketches</a>
                        <a href="cruzz.php?entity=sketches" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=sketches&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="15" title="Run">🌀</button>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="animatics">
                        <a href="view_video_admin.php" class="multi-button-main">🎥 Animatics</a>
                        <a href="cruzz.php?entity=animatics" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=animatics&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="42" title="Run">🌀</button>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="generatives">
                        <a href="view_map_runs_generatives.php" class="multi-button-main">⚡ Generatives</a>
                        <a href="cruzz.php?entity=generatives" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=generatives&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="10" title="Run">🌀</button>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="composites">
                        <a href="gallery_composites_nu.php" class="multi-button-main">🧩 Composites</a>
                        <a href="cruzz.php?entity=composites" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=composites&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="24" title="Run">🌀</button>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="meshes">
                        <a href="meshes.php" class="multi-button-main">📦 Meshes</a>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="dimensionals">
                        <a href="view_map_runs_dimensionals.php" class="multi-button-main">📦 Dimensionals</a>
                        <a href="cruzz.php?entity=dimensionals" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=dimensionals&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="87" title="Run">🌀</button>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="spawns">
                        <a href="upload_spawns.php" class="multi-button-main">🌱 Spawns</a>
                        <a href="#" class="multi-button-action" style="opacity:0.3; cursor:not-allowed; pointer-events:none;">CRUD</a>
                        <a href="#" class="multi-button-action" style="opacity:0.3; cursor:not-allowed; pointer-events:none;">NHX</a>
                        <a href="upload_spawns.php#uploader" class="multi-button-action run-btn">📤</a>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="blueprints">
                        <a href="gallery_prompt_matrix_blueprints_nu.php" class="multi-button-main">🌌 Blueprints</a>
                        <a href="cruzz.php?entity=prompt_matrix_blueprints" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=prompt_matrix_blueprints&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="23" title="Run">🌀</button>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="controlnet">
                        <a href="gallery_controlnet_maps_nu.php" class="multi-button-main">☠️ Controlnet</a>
                        <a href="cruzz.php?entity=controlnet_maps" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=controlnet_maps&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="20" title="Run">🌀</button>
                    </div>

                </div>

                <div class="ft-settings-section-title" id="sec-title-entities" style="margin-bottom:14px;">🧬 Entities</div>
                <div class="link-grid" style="padding-bottom:40px;">
                    
                    <div class="multi-button-group" data-item-key="characters">
                        <a href="view_map_runs_characters.php" class="multi-button-main">🦸 Characters</a>
                        <a href="cruzz.php?entity=characters" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=characters&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="11" title="Run">🌀</button>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="factions">
                        <a href="view_map_runs_factions.php" class="multi-button-main">🎌 Factions</a>
                        <a href="cruzz.php?entity=factions" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=factions&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="76" title="Run">🌀</button>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="character_poses">
                        <a href="view_map_runs_character_poses.php" class="multi-button-main">🤸 Poses</a>
                        <a href="cruzz.php?entity=character_poses" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=character_poses&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="19" title="Run">🌀</button>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="character_anima_poses">
                        <a href="view_map_runs_character_anima_poses.php" class="multi-button-main">🤹 Anima Poses</a>
                        <a href="cruzz.php?entity=character_anima_poses" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=character_anima_poses&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="52" title="Run">🌀</button>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="character_expressions">
                        <a href="view_map_runs_character_expressions.php" class="multi-button-main">🧑‍🎤 Expressions</a>
                        <a href="cruzz.php?entity=character_expressions" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=character_expressions&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="46" title="Run">🌀</button>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="animas">
                        <a href="view_map_runs_animas.php" class="multi-button-main">🐾 Animas</a>
                        <a href="cruzz.php?entity=animas" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=animas&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="12" title="Run">🌀</button>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="locations">
                        <a href="view_map_runs_locations.php" class="multi-button-main">🗺️ Locations</a>
                        <a href="cruzz.php?entity=locations" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=locations&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="13" title="Run">🌀</button>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="backgrounds">
                        <a href="view_map_runs_backgrounds.php" class="multi-button-main">🏞️ Backgrounds</a>
                        <a href="cruzz.php?entity=backgrounds" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=backgrounds&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="16" title="Run">🌀</button>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="artifacts">
                        <a href="view_map_runs_artifacts.php" class="multi-button-main">🏺 Artifacts</a>
                        <a href="cruzz.php?entity=artifacts" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=artifacts&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="18" title="Run">🌀</button>
                    </div>
                    
                    <div class="multi-button-group" data-item-key="vehicles">
                        <a href="view_map_runs_vehicles.php" class="multi-button-main">🛸 Vehicles</a>
                        <a href="cruzz.php?entity=vehicles" class="multi-button-action">CRUD</a>
                        <a href="enhanimaticism.php?entity_type=vehicles&map_run_dis=1" class="multi-button-action">NHX</a>
                        <button class="multi-button-action run-btn runBtn" data-id="17" title="Run">🌀</button>
                    </div>

                </div>

            </div>
        </div>

        <!-- ── LEGACY DASHBOARD TAB (Iframe logic) ── -->
        <div class="ft-tab-content ft-iframe-content" id="ft-tab-legacy" role="tabpanel">
            <iframe data-src="dashboard.php?tab_mode=1" allowfullscreen></iframe>
        </div>

        <!-- ── SETTINGS TAB ── -->
        <div class="ft-tab-content" id="ft-tab-settings" role="tabpanel">
            <div class="ft-settings-layout">
                <div class="ft-settings-scroll" id="ft-settings-scroll">

                    <!-- Section: Dashboard Profiles -->
                    <div class="ft-settings-section">
                        <div class="ft-settings-section-title">🗂 Dashboard Profiles</div>
                        <div class="ft-add-shortcut-form">
                            <div class="ft-add-form-row">
                                <select id="sage-profile-select" class="ft-add-input" style="flex:1;">
                                    <option value="Default">Default</option>
                                </select>
                                <button class="ft-add-btn" onclick="SageApp.loadProfileFromSelect()">Load Profile</button>
                            </div>
                            <div class="ft-add-form-row" style="margin-top:8px;">
                                <input type="text" id="sage-new-profile-name" class="ft-add-input" placeholder="New profile name...">
                                <button class="ft-add-btn" onclick="SageApp.saveAsNewProfile()" style="background:var(--ft-purple);">Save As New</button>
                            </div>
                        </div>
                    </div>
                    
                    
                    
                    
                    <!-- Section: Copy Shortcuts -->
                    <div class="ft-settings-section">
                        <div class="ft-settings-section-title">🔄 Copy All Shortcuts</div>
                        <div class="ft-add-shortcut-form">
                            <div class="ft-add-form-row">
                                <select id="sage-copy-sc-src" class="ft-add-input" style="flex:1;">
                                    <option value="">Source Profile...</option>
                                </select>
                                <span style="display:flex;align-items:center;color:var(--ft-text-dim);">→</span>
                                <select id="sage-copy-sc-dst" class="ft-add-input" style="flex:1;">
                                    <option value="">Destination Profile...</option>
                                </select>
                            </div>
                            <div class="ft-add-form-row" style="margin-top:8px; justify-content:flex-end;">
                                <button class="ft-add-btn" onclick="SageApp.copyShortcuts()" style="background:var(--ft-amber);">Copy Shortcuts</button>
                            </div>
                        </div>
                    </div>
                    
                    
                    <!-- Section: Copy Wrappers -->
                    <div class="ft-settings-section">
                        <div class="ft-settings-section-title">🔄 Copy Control Deck Presets</div>
                        <div class="ft-add-shortcut-form">
                            <div class="ft-add-form-row">
                                <select id="sage-copy-src" class="ft-add-input" style="flex:1;">
                                    <option value="">Source Profile...</option>
                                </select>
                                <span style="display:flex;align-items:center;color:var(--ft-text-dim);">→</span>
                                <select id="sage-copy-dst" class="ft-add-input" style="flex:1;">
                                    <option value="">Destination Profile...</option>
                                </select>
                            </div>
                            <div class="ft-add-form-row" style="margin-top:8px; justify-content:flex-end;">
                                <button class="ft-add-btn" onclick="SageApp.copyWrappers()" style="background:var(--ft-amber);">Copy Presets</button>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Shortcuts Manager -->
                    <div class="ft-settings-section">
                        <div class="ft-settings-section-title">⚡ Shortcuts</div>
                        <div class="ft-add-shortcut-form" id="ft-add-shortcut-form">
                            <div class="ft-add-form-row">
                                <input type="text" class="ft-add-input icon" id="ft-sc-new-icon" placeholder="⚗" maxlength="4" title="Emoji icon">
                                <input type="text" class="ft-add-input" id="ft-sc-new-label" placeholder="Label…" maxlength="40">
                                <button class="ft-add-btn" id="ft-sc-add-btn" onclick="SageApp.settings.addShortcut()">+ Add</button>
                            </div>
                            <div class="ft-add-form-row">
                                <input type="text" class="ft-add-input" id="ft-sc-new-url" placeholder="URL or javascript:… (leave blank for modal)" maxlength="500">
                            </div>
                            <div class="ft-add-form-row" style="gap:10px; align-items:center; flex-wrap:wrap; margin-top:4px;">
                                <label style="font-family:var(--ft-mono);font-size:0.75rem;color:var(--ft-text-dim);display:flex;align-items:center;gap:6px;">
                                    <input type="checkbox" id="ft-sc-new-modal" style="accent-color:var(--ft-teal); transform:scale(1.2);"> Open in Modal
                                </label>
                                <label style="font-family:var(--ft-mono);font-size:0.75rem;color:var(--ft-text-dim);display:flex;align-items:center;gap:6px;">
                                    <input type="checkbox" id="ft-sc-new-fullscreen" style="accent-color:var(--ft-amber); transform:scale(1.2);"> Fullscreen
                                </label>
                            </div>
                        </div>
                        <div class="ft-shortcut-list" id="ft-shortcut-list" style="margin-top:12px;"></div>
                    </div>

                    <!-- Section: Custom Iframe Tabs -->
                    <div class="ft-settings-section">
                        <div class="ft-settings-section-title">🗂 Custom Tabs</div>
                        <div class="ft-add-shortcut-form">
                            <div class="ft-add-form-row">
                                <input type="text" class="ft-add-input icon" id="ft-tab-new-icon" placeholder="🗂" maxlength="4">
                                <input type="text" class="ft-add-input" id="ft-tab-new-label" placeholder="Tab name…" maxlength="30">
                                <button class="ft-add-btn" id="ft-tab-add-btn" onclick="SageApp.settings.addCustomTab()">+ Tab</button>
                            </div>
                            <div class="ft-add-form-row">
                                <input type="text" class="ft-add-input" id="ft-tab-new-url" placeholder="/view.php?… (iframe src)" maxlength="500">
                            </div>
                        </div>
                        <div class="ft-custom-tabs-list" id="ft-custom-tabs-list" style="margin-top:12px;"></div>
                    </div>

                    <!-- Section: Clipboard -->
                    <div class="ft-settings-section">
                        <div class="ft-settings-section-title">📋 Clipboard</div>
                        <div class="ft-setting-row">
                            <div class="ft-setting-label">
                                View Area (Scope)
                                <div class="ft-setting-sub" id="ft-cb-area-display">global</div>
                            </div>
                            <input type="text" class="ft-add-input" id="ft-cb-area-input" placeholder="e.g. enhanimatics" style="max-width:160px; flex:none;" value="global">
                        </div>
                    </div>

                    <!-- Upper Save Settings Button -->
                    <div style="margin-bottom:28px; display:flex; justify-content:flex-end;">
                        <button class="ft-settings-save-btn" onclick="SageApp.saveSettings()">
                            💾 Save Profile
                        </button>
                    </div>

                    <!-- NEW SECTION: Creatives & Entities Visibility -->
                    <div class="ft-settings-section">
                        <div class="ft-settings-section-title">💡 Creatives & Entities Visibility</div>
                        <p style="font-size:0.7rem; color:var(--ft-text-dim); font-family:var(--ft-mono); margin-bottom:12px;">
                            Toggle items to show or hide inside the Creatives & Entities (💡) tab for this profile.
                        </p>
                        <div class="ft-shortcut-list" id="ft-creatives-settings-list">
                            <!-- Populated dynamically by JS -->
                        </div>
                    </div>

                    <!-- Lower Save Settings Button -->
                    <div style="margin-top:20px; display:flex; justify-content:flex-end;">
                        <button class="ft-settings-save-btn" onclick="SageApp.saveSettings()">
                            💾 Save Profile
                        </button>
                    </div>

                </div>
            </div>
        </div>

    </div>

</div>

<!-- ── MODALS OVERLAYS ── -->




<!-- Fullscreen shortcut modal overlay -->
<div class="ft-shortcut-modal-overlay" id="ft-shortcut-modal" onclick="SageApp.shortcutModal.closeOnBg(event)">
    <div class="ft-shortcut-modal-box">
        <button type="button" class="ft-sm-floating-btn" style="right:112px; width:38px; font-size:17px;" onclick="const f=document.getElementById('ft-sm-iframe'); if(f) f.src=f.src;" title="Refresh View">↺</button>
        <a id="ft-sm-open-link" href="#" target="_blank" class="ft-sm-floating-btn ft-sm-open" title="Open Full View">&gt;&gt;</a>
        <button type="button" class="ft-sm-floating-btn ft-sm-close" onclick="SageApp.shortcutModal.close()" title="Close">✕</button>
        <div class="ft-shortcut-modal-body">
            <iframe id="ft-sm-iframe" src="" loading="lazy" allow="clipboard-read; clipboard-write"></iframe>
        </div>
    </div>
</div>



<!-- Scheduler Wrapper Edit Modal -->
<div class="forge-modal-overlay" id="sageWrapperModal" onclick="if(event.target===this) this.classList.remove('open')">
    <div class="forge-modal" style="max-width:500px; padding:0;">
        <div class="forge-modal-header">
            <div class="forge-modal-title" id="sageWrapperModalTitle">New Task Preset</div>
            <button class="forge-modal-close" onclick="document.getElementById('sageWrapperModal').classList.remove('open')">✕</button>
        </div>
        <div class="forge-modal-body">
            <form id="sageWrapperForm" onsubmit="SageApp.scheduler.save(event)">
                <input type="hidden" id="sw_id" value="0">
                
                <div class="form-group" style="margin-bottom:12px;">
                    <label style="display:block; font-family:var(--ft-mono); font-size:0.7rem; color:var(--ft-text-dim); margin-bottom:6px;">Target Scheduler Task</label>
                    <select id="sw_task_id" class="ft-add-input" style="width:100%;" required>
                        <?php foreach($allTasks as $t): ?>
                            <option value="<?= $t['id'] ?>" data-def-args="<?= htmlspecialchars($t['args']??'') ?>">
                                <?= htmlspecialchars($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display:flex; gap:10px; margin-bottom:12px;">
                    <div style="flex:1;">
                        <label style="display:block; font-family:var(--ft-mono); font-size:0.7rem; color:var(--ft-text-dim); margin-bottom:6px;">Button Name</label>
                        <input type="text" id="sw_name" class="ft-add-input" style="width:100%;" required>
                    </div>
                    <div style="width:70px;">
                        <label style="display:block; font-family:var(--ft-mono); font-size:0.7rem; color:var(--ft-text-dim); margin-bottom:6px;">Icon</label>
                        <input type="text" id="sw_icon" class="ft-add-input" style="width:100%; text-align:center;" value="🚀">
                    </div>
                </div>
                
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-family:var(--ft-mono); font-size:0.7rem; color:var(--ft-text-dim); margin-bottom:6px;">Summary (Pill Text)</label>
                    <input type="text" id="sw_summary" class="ft-add-input" style="width:100%;">
                </div>
                
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-family:var(--ft-mono); font-size:0.7rem; color:var(--ft-text-dim); margin-bottom:6px;">Fixed Arguments</label>
                    <textarea id="sw_args" class="ft-add-input" rows="3" style="width:100%; resize:vertical;"></textarea>
                    <div style="text-align:right; font-size:0.7em; margin-top:4px;">
                        <a href="#" onclick="SageApp.scheduler.copyDefaultArgs(event)" style="color:var(--ft-teal); text-decoration:none;">Copy Default</a>
                    </div>
                </div>
                
                <div style="margin-bottom:12px;">
                    <label style="display:block; font-family:var(--ft-mono); font-size:0.7rem; color:var(--ft-text-dim); margin-bottom:6px;">Order</label>
                    <input type="number" id="sw_order" class="ft-add-input" style="width:100%;" value="0">
                </div>
                
                <div style="display:flex; justify-content:space-between; margin-top:20px; padding-top:16px; border-top:1px solid var(--ft-border);">
                    <button type="button" id="sw_delete_btn" class="ft-add-btn" style="background:var(--ft-red); display:none;" onclick="SageApp.scheduler.del()">🗑 Delete</button>
                    <div style="display:flex; gap:10px; margin-left:auto;">
                        <button type="button" class="ft-add-btn" style="background:var(--ft-surface); color:var(--ft-text); border:1px solid var(--ft-border);" onclick="document.getElementById('sageWrapperModal').classList.remove('open')">Cancel</button>
                        <button type="submit" class="ft-add-btn">✓ Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="ft-toast" id="ft-toast"></div>


<!-- ══════════════════════════════════════════════════════════════════════════
     SAGE APP JAVASCRIPT
══════════════════════════════════════════════════════════════════════════ -->
<script>
(function() {
'use strict';

const API_URL = '?sage_api=';
const CB_URL = '/clipboard_manager.php';
let _cbArea = 'global';
let _cbItems = [];
let _settings = {}; 
let _currentProfile = localStorage.getItem('sage_profile') || 'Default';
let _scSortable = null;
let _cbSortable = null;
let _toastTimer;

// Master Definition List of Creatives & Entities
const CREATIVES_ENTITIES_DEFS = [
    { id: 'sketches', label: 'Sketches', icon: '🪄', section: 'Creatives' },
    { id: 'animatics', label: 'Animatics', icon: '🎥', section: 'Creatives' },
    { id: 'generatives', label: 'Generatives', icon: '⚡', section: 'Creatives' },
    { id: 'composites', label: 'Composites', icon: '🧩', section: 'Creatives' },
    { id: 'meshes', label: 'Meshes', icon: '📦', section: 'Creatives' },
    { id: 'dimensionals', label: 'Dimensionals', icon: '📦', section: 'Creatives' },
    { id: 'spawns', label: 'Spawns', icon: '🌱', section: 'Creatives' },
    { id: 'blueprints', label: 'Blueprints', icon: '🌌', section: 'Creatives' },
    { id: 'controlnet', label: 'Controlnet', icon: '☠️', section: 'Creatives' },
    { id: 'characters', label: 'Characters', icon: '🦸', section: 'Entities' },
    { id: 'factions', label: 'Factions', icon: '🎌', section: 'Entities' },
    { id: 'character_poses', label: 'Poses', icon: '🤸', section: 'Entities' },
    { id: 'character_anima_poses', label: 'Anima Poses', icon: '🤹', section: 'Entities' },
    { id: 'character_expressions', label: 'Expressions', icon: '🧑‍🎤', section: 'Entities' },
    { id: 'animas', label: 'Animas', icon: '🐾', section: 'Entities' },
    { id: 'locations', label: 'Locations', icon: '🗺️', section: 'Entities' },
    { id: 'backgrounds', label: 'Backgrounds', icon: '🏞️', section: 'Entities' },
    { id: 'artifacts', label: 'Artifacts', icon: '🏺', section: 'Entities' },
    { id: 'vehicles', label: 'Vehicles', icon: '🛸', section: 'Entities' }
];

function toast(msg, err) {
    const el = document.getElementById('ft-toast');
    el.textContent = msg;
    el.className = 'ft-toast' + (err ? ' err' : '') + ' show';
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => el.classList.remove('show'), 2000);
}

function esc(s) {
    if (!s) return '';
    return s.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

window.SageApp = {
    async init() {
        await this.loadProfilesList();
        await this.loadSettings(_currentProfile);

        // Tab routing logic
        document.getElementById('ft-tab-bar').addEventListener('click', e => {
            const btn = e.target.closest('.ft-tab');
            if (btn && !e.target.classList.contains('ft-tab-close')) {
                this.switchTab(btn.dataset.tab);
            }
        });

        // Universal Copy to Clipboard Delegation
        document.getElementById('ft-cb-list').addEventListener('click', e => {
            const copyBtn = e.target.closest('.ft-copy-action');
            if (copyBtn) {
                e.preventDefault();
                const id = copyBtn.dataset.id;
                const item = _cbItems.find(i => i.id == id);
                if (item && item.content) {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(item.content).then(() => toast('Copied! ✓')).catch(() => toast('Copy failed', true));
                    } else toast('Clipboard API unavailable', true);
                }
            }
        });

        // Task Run Buttons (🌀) from Creatives & Entities
        document.addEventListener('click', e => {
            const btn = e.target.closest('.runBtn');
            if (btn) {
                e.preventDefault();
                const id = btn.dataset.id;
                const fd = new FormData();
                fd.append('action', 'run_now');
                fd.append('id', id);
                fetch('scheduler_view.php', { method: 'POST', body: fd })
                .then(r => r.text())
                .then(res => {
                    if (res.trim() === 'success') toast('Task scheduled to run now! 🌀');
                    else toast('Failed to trigger task', true);
                })
                .catch(() => toast('Request failed', true));
            }
        });

        // Inter-frame Communication logic for Chatty
        window.addEventListener('message', e => {
            if (!e.data) return;
            if (e.data.type === 'CHATTY_READY') {
                const isChattyActive = document.getElementById('ft-tab-chatty').classList.contains('active');
                const iframe = document.querySelector('#ft-tab-chatty iframe');
                if (iframe && iframe.contentWindow) {
                    iframe.contentWindow.postMessage({ type: isChattyActive ? 'SAGE_TAB_VISIBLE' : 'SAGE_TAB_HIDDEN' }, '*');
                }
            }
            else if (e.data.type === 'CHATTY_UNREAD') {
                this.updateChattyBadge(e.data.count);
            }
        });

        // Close on Esc
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && document.getElementById('ft-shortcut-modal').classList.contains('open')) {
                this.shortcutModal.close();
            }
            if (e.key === 'Escape' && document.getElementById('sageWrapperModal').classList.contains('open')) {
                document.getElementById('sageWrapperModal').classList.remove('open');
            }
        });

        let savedTab = localStorage.getItem('sage_active_tab') || 'shortcuts';
        this.switchTab(savedTab);
        
        // Init the new Scheduler UI
        this.scheduler.init();

        // ── DASHBOARD-NATIVE CHATTY POLLING ──
        this.checkChattyUnread();
        setInterval(() => this.checkChattyUnread(), 10000);
    },

    // ── CHATTY NOTIFICATION BADGE LOGIC ──
    async checkChattyUnread() {
        try {
            const r = await fetch('/chatty_api.php?action=check_unread');
            const d = await r.json();
            if (d.success) {
                this.updateChattyBadge(d.unread);
            }
        } catch(e) {}
    },

    updateChattyBadge(count) {
        const tabBtn = document.querySelector('.ft-tab[data-tab="chatty"]');
        if (!tabBtn) return;
        let badge = tabBtn.querySelector('.chatty-badge');
        
        // Don't show badge if they are currently actively looking at the Chatty tab
        const isChattyActive = tabBtn.classList.contains('active');
        
        if (count > 0 && !isChattyActive) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'chatty-badge';
                tabBtn.appendChild(badge);
            }
            badge.textContent = count > 99 ? '99+' : count;
        } else if (badge) {
            badge.remove();
        }
    },

    switchTab(tab) {
        localStorage.setItem('sage_active_tab', tab);
        
        document.querySelectorAll('#ft-tab-bar .ft-tab').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });
        
        document.querySelectorAll('.sage-main > .ft-tab-content').forEach(el => {
            const isActive = (el.id === 'ft-tab-' + tab);
            el.classList.toggle('active', isActive);
            
            // Lazy load iframe (for anything strictly using ft-iframe-content)
            if (el.classList.contains('ft-iframe-content')) {
                const iframe = el.querySelector('iframe');
                if (iframe) {
                    if (isActive && !iframe.getAttribute('src') && iframe.dataset.src) {
                        iframe.setAttribute('src', iframe.dataset.src);
                    }
                    if (iframe.contentWindow) {
                        iframe.contentWindow.postMessage({ type: isActive ? 'SAGE_TAB_VISIBLE' : 'SAGE_TAB_HIDDEN' }, '*');
                    }
                }
            }
        });

        if (tab === 'chatty') {
            this.updateChattyBadge(0); // Instantly clear badge when opening tab
        }
        if (tab === 'clipboard') this.cb.load();
    },

    async loadProfilesList() {
        const r = await fetch(API_URL + 'get_profiles');
        const d = await r.json();
        if (d.ok) {
            const options = d.profiles.map(p => `<option value="${esc(p)}" ${p===_currentProfile?'selected':''}>${esc(p)}</option>`).join('');
            document.getElementById('sage-profile-select').innerHTML = options;
            
            const copyOptions = d.profiles.map(p => `<option value="${esc(p)}">${esc(p)}</option>`).join('');
            document.getElementById('sage-copy-src').innerHTML = copyOptions;
            document.getElementById('sage-copy-dst').innerHTML = copyOptions;
            document.getElementById('sage-copy-src').value = _currentProfile;
            
            
            document.getElementById('sage-copy-sc-src').innerHTML = copyOptions;
            document.getElementById('sage-copy-sc-dst').innerHTML = copyOptions;
            document.getElementById('sage-copy-sc-src').value = _currentProfile;
            
            
            
            
        }
    },

async loadSettings(profile, isUserAction = false) {
        const r = await fetch(API_URL + 'get_settings&profile_name=' + encodeURIComponent(profile));
        const d = await r.json();
        if (d.ok) {
            _settings = d.data;
            if (!_settings.shortcuts) _settings.shortcuts = [];
            if (!_settings.custom_tabs) _settings.custom_tabs = [];
            if (!_settings.creatives_visibility) _settings.creatives_visibility = {};
            
            _cbArea = _settings.cb_area || 'global';
            document.getElementById('ft-cb-area-input').value = _cbArea;
            
            _currentProfile = profile;
            localStorage.setItem('sage_profile', profile);
            
            this.renderShortcutsGrid();
            this.renderCustomTabs();
            this.settings.render();
            this.applyCreativesVisibility();
            
            if (document.querySelector('.ft-tab.active').dataset.tab === 'clipboard') this.cb.load();
            if (this.scheduler) this.scheduler.loadWrappers();
            
            // Only notify if explicitly loaded by user action
            if (isUserAction) {
                toast('Loaded: ' + profile);
            }
        } else {
            toast('Failed to load profile', true);
        }
    },

    async loadProfileFromSelect() {
        const sel = document.getElementById('sage-profile-select');
        await this.loadSettings(sel.value, true);
    },

    

    async saveSettings(profileOverride) {
        const profile = profileOverride || _currentProfile;
        const cbInput = document.getElementById('ft-cb-area-input');
        if (cbInput) _settings.cb_area = cbInput.value.trim() || 'global';
        
        const r = await fetch(API_URL + 'save_settings', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ profile_name: profile, settings: _settings })
        });
        const d = await r.json();
        if (d.ok) {
            toast('Profile saved ✓');
            if (profileOverride) {
                _currentProfile = profileOverride;
                localStorage.setItem('sage_profile', _currentProfile);
                await this.loadProfilesList();
                this.scheduler.loadWrappers();
            }
            _cbArea = _settings.cb_area;
            this.renderShortcutsGrid();
            this.renderCustomTabs();
            this.applyCreativesVisibility();
        } else {
            toast('Save failed', true);
        }
    },

    async saveAsNewProfile() {
        const input = document.getElementById('sage-new-profile-name');
        const name = input.value.trim();
        if (!name) { toast('Profile name required', true); return; }
        await this.saveSettings(name);
        input.value = '';
    },
    
    async copyWrappers() {
        const src = document.getElementById('sage-copy-src').value;
        const dst = document.getElementById('sage-copy-dst').value;
        if (!src || !dst) return toast('Select both profiles', true);
        if (src === dst) return toast('Profiles must be different', true);
        
        if (!confirm(`Are you sure?\nThis will overwrite ALL Control Deck presets in "${dst}" with the presets from "${src}".`)) return;
        
        const fd = new FormData();
        fd.append('source_profile', src);
        fd.append('dest_profile', dst);
        
        const r = await fetch(API_URL + 'copy_wrappers', { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.ok) {
            toast('Presets copied! ✓');
            if (dst === _currentProfile) {
                this.scheduler.loadWrappers();
            }
        } else {
            toast('Copy failed: ' + (d.error || ''), true);
        }
    },
    
    
    
    async copyShortcuts() {
        const src = document.getElementById('sage-copy-sc-src').value;
        const dst = document.getElementById('sage-copy-sc-dst').value;
        if (!src || !dst) return toast('Select both profiles', true);
        if (src === dst) return toast('Profiles must be different', true);
        
        if (!confirm(`Are you sure?\nThis will overwrite ALL Shortcuts in "${dst}" with the shortcuts from "${src}".`)) return;
        
        const fd = new FormData();
        fd.append('source_profile', src);
        fd.append('dest_profile', dst);
        
        const r = await fetch(API_URL + 'copy_shortcuts', { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.ok) {
            toast('Shortcuts copied! ✓');
            // If the active profile was the target, reload to immediately see the changes
            if (dst === _currentProfile) {
                await this.loadSettings(_currentProfile);
            }
        } else {
            toast('Copy failed: ' + (d.error || ''), true);
        }
    },
    

    applyCreativesVisibility() {
        const visibility = _settings.creatives_visibility || {};
        document.querySelectorAll('#ft-tab-creatives [data-item-key]').forEach(el => {
            const key = el.dataset.itemKey;
            const isVisible = (visibility[key] !== false);
            el.style.display = isVisible ? 'flex' : 'none';
        });
    },

    renderShortcutsGrid() {
        const grid = document.getElementById('ft-shortcuts-grid');
        grid.innerHTML = '';
        const visible = _settings.shortcuts.filter(s => s.visible !== false);
        if (!visible.length) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--ft-text-dim);font-family:var(--ft-mono);font-size:0.85rem;font-style:italic;">No shortcuts active in this profile.</div>';
            return;
        }
        visible.forEach(sc => {
            const btn = document.createElement('div');
            btn.className = 'ft-shortcut-btn';
            btn.innerHTML = `<div class="ft-shortcut-icon">${sc.icon || '⚗'}</div><div class="ft-shortcut-label">${esc(sc.label)}</div>`;
            btn.title = sc.url || sc.label;
            btn.addEventListener('click', () => {
                if (!sc.url) return;
                if (sc.fullscreen || sc.modal) this.shortcutModal.open(sc);
                else window.location.href = sc.url;
            });
            grid.appendChild(btn);
        });
    },

    renderCustomTabs() {
        const main  = document.querySelector('.sage-main');
        const tabBar = document.getElementById('ft-tab-bar');

        main.querySelectorAll('.ft-iframe-content[data-custom]').forEach(el => el.remove());
        tabBar.querySelectorAll('.ft-tab[data-custom]').forEach(el => el.remove());

        (_settings.custom_tabs || []).forEach((tab, i) => {
            const tabKey = 'custom_' + i;
            
            // Generate Tab button
            const btn = document.createElement('button');
            btn.className = 'ft-tab';
            btn.dataset.tab = tabKey;
            btn.dataset.custom = '1';
            btn.innerHTML = `<span class="ft-tab-icon">${tab.icon || '🗂'}</span> ${esc(tab.label)} <button class="ft-tab-close" onclick="event.stopPropagation(); SageApp.removeCustomTab(${i})" title="Close">✕</button>`;
            btn.addEventListener('click', () => this.switchTab(tabKey));
            tabBar.appendChild(btn);

            // Generate Tab Content (iframe)
            const content = document.createElement('div');
            content.className = 'ft-iframe-content ft-tab-content';
            content.id = 'ft-tab-' + tabKey;
            content.dataset.tab = tabKey;
            content.dataset.custom = '1';
            content.innerHTML = `<iframe data-src="${esc(tab.url)}" allowfullscreen allow="clipboard-read; clipboard-write"></iframe>`;
            main.appendChild(content);
        });
    },

    removeCustomTab(i) {
        if (!confirm('Remove this custom tab?')) return;
        _settings.custom_tabs.splice(i, 1);
        this.renderCustomTabs();
        this.settings.renderCustomTabsList();
        this.switchTab('shortcuts');
    },

    shortcutModal: {
        open(sc) {
            const overlay = document.getElementById('ft-shortcut-modal');
            const iframe  = document.getElementById('ft-sm-iframe');
            const link    = document.getElementById('ft-sm-open-link');
            link.href     = sc.url || '#';
            iframe.src    = sc.url || '';
            overlay.classList.add('open');
        },
        close() {
            const overlay = document.getElementById('ft-shortcut-modal');
            const iframe  = document.getElementById('ft-sm-iframe');
            overlay.classList.remove('open');
            setTimeout(() => { iframe.src = ''; }, 300);
        },
        closeOnBg(e) {
            if (e.target === document.getElementById('ft-shortcut-modal')) this.close();
        }
    },
    
    // ── INTEGRATED NATIVE SCHEDULER (Control Deck) ──
    scheduler: {
        wrappers: [],
        currentFile: "",
        pendingAutoSwitch: false,
        listInterval: null,
        logInterval: null,
        sortableInstance: null,

        init() {
            const sel = document.getElementById('sageLogFileSelect');
            if (sel) {
                sel.addEventListener('change', () => {
                    this.openConsole();
                    this.fetchContent();
                });
            }
            
            const refBtn = document.getElementById('sageRefreshLogBtn');
            if (refBtn) {
                refBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.fetchContent();
                });
            }
            
            if (this.listInterval) clearInterval(this.listInterval);
            if (this.logInterval) clearInterval(this.logInterval);
            
            this.listInterval = setInterval(() => this.updateLogList(), 4000); 
            this.logInterval = setInterval(() => this.fetchContent(), 2000);  
            
            this.updateLogList(true);
            this.loadWrappers();
        },
        
        toggleConsole(e) {
            if (e && e.target) {
                const tag = e.target.tagName;
                if (tag === 'SELECT' || tag === 'OPTION' || tag === 'INPUT' || tag === 'BUTTON' || e.target.closest('button')) {
                    return;
                }
            }
            
            const lb = document.getElementById('sageLogBox');
            const cw = document.getElementById('sageConsoleWrapper');
            const icon = document.getElementById('sageToggleIcon');
            
            lb.classList.toggle('expanded');
            cw.classList.toggle('open');
            
            if (icon) {
                icon.style.transform = lb.classList.contains('expanded') ? 'rotate(90deg)' : 'rotate(0deg)';
            }
        },
        
        openConsole() {
            const lb = document.getElementById('sageLogBox');
            const cw = document.getElementById('sageConsoleWrapper');
            const icon = document.getElementById('sageToggleIcon');
            
            if (!lb.classList.contains('expanded')) {
                lb.classList.add('expanded');
                cw.classList.add('open');
                if (icon) icon.style.transform = 'rotate(90deg)';
            }
        },
        
        async loadWrappers() {
            const fd = new FormData();
            fd.append('profile_name', _currentProfile);
            const r = await fetch(API_URL + 'get_wrappers', {method:'POST', body:fd});
            const res = await r.json();
            if(res.ok) {
                this.wrappers = res.data;
                this.renderList();
            }
        },
        
        renderList() {
            const container = document.getElementById('sageWrapperContainer');
            container.innerHTML = '';
            this.wrappers.forEach(w => {
                const row = document.createElement('div');
                row.className = 'ft-shortcut-row';
                row.dataset.id = w.id;
                row.innerHTML = `
                    <div class="ft-drag-handle" style="touch-action:none; cursor:grab; padding: 0 4px; font-size:16px; color:var(--ft-border-hi);">⣿</div>
                    <div class="ft-shortcut-row-icon">${esc(w.icon)}</div>
                    <div style="flex:1;min-width:0;">
                        <div class="ft-shortcut-row-label">${esc(w.name)}</div>
                        <div class="ft-shortcut-row-url">${esc(w.summary || '')}</div>
                    </div>
                    <button class="run-task-btn" onclick="event.stopPropagation(); SageApp.scheduler.run(${w.id}, this)" title="Run Task">▶</button>
                    <button class="ft-shortcut-row-edit" onclick="event.stopPropagation(); SageApp.scheduler.openModal(${w.id})" title="Edit Preset">✏️</button>
                `;
                container.appendChild(row);
            });
            this.initSortable();
        },

        initSortable() {
            if (this.sortableInstance) this.sortableInstance.destroy();
            const container = document.getElementById('sageWrapperContainer');
            if (window.Sortable && container) {
                this.sortableInstance = Sortable.create(container, {
                    handle: '.ft-drag-handle',
                    animation: 140,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    onEnd: () => this.saveOrder(),
                });
            }
        },

        async saveOrder() {
            const rows = document.querySelectorAll('#sageWrapperContainer .ft-shortcut-row');
            const orders = Array.from(rows).map((row, i) => ({
                id: parseInt(row.dataset.id),
                display_order: i
            }));

            // 1. Immediately sync in-memory wrapper objects so modal is accurate right away
            orders.forEach(o => {
                const item = this.wrappers.find(x => x.id === o.id);
                if (item) {
                    item.display_order = o.display_order;
                }
            });

            // 2. Keep local array sorted in memory
            this.wrappers.sort((a, b) => (parseInt(a.display_order) || 0) - (parseInt(b.display_order) || 0));

            // 3. Persist to DB and re-fetch to ensure 100% synchronization
            try {
                const r = await fetch(API_URL + 'reorder_wrappers', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ orders })
                });
                const res = await r.json();
                if (res.ok) {
                    await this.loadWrappers();
                }
            } catch(e) {}
        },
        
        run(id, btn) {
            const og = btn.innerHTML;
            btn.innerHTML = '⏳';
            btn.classList.add('pulse-green');
            this.openConsole();
            this.pendingAutoSwitch = true;
            
            const fd = new FormData();
            fd.append('id', id);
            
            fetch(API_URL + 'run_wrapper', {method:'POST', body:fd})
            .then(r=>r.json()).then(res=>{
                if(res.ok) {
                    toast(res.msg);
                    setTimeout(() => this.updateLogList(true), 2500);
                } else {
                    toast(res.error, true);
                    this.pendingAutoSwitch = false;
                }
            }).catch(()=>{this.pendingAutoSwitch=false;})
            .finally(()=>{
                setTimeout(()=>{ btn.innerHTML=og; btn.classList.remove('pulse-green'); }, 500);
            });
        },
        
        updateLogList(andFetchContent = false) {
            fetch(API_URL + 'list_logs')
            .then(r => r.json())
            .then(res => {
                if(!res.ok) return;
                const sel = document.getElementById('sageLogFileSelect');
                const html = res.files.length === 0 
                    ? '<option value="">(No logs)</option>' 
                    : res.files.map(f => `<option value="${f.name}">${esc(f.name)} (${esc(f.time)})</option>`).join('');
                
                const prevFile = sel.value;
                const listChanged = (sel.innerHTML !== html);
                
                if (listChanged) {
                    sel.innerHTML = html;
                }
                
                const newestTaskLog = res.files.find(f => f.name.includes('task_run_') && f.name.includes('_out.log'));
                
                if (this.pendingAutoSwitch && newestTaskLog && newestTaskLog.name !== prevFile) {
                    sel.value = newestTaskLog.name;
                    this.pendingAutoSwitch = false; 
                    toast('Log: ' + newestTaskLog.name);
                    this.fetchContent();
                }
                else if (!prevFile && res.files.length > 0) {
                    sel.selectedIndex = 0;
                    if(andFetchContent) this.fetchContent();
                }
                else if (listChanged && prevFile && res.files.some(f => f.name === prevFile)) {
                    sel.value = prevFile;
                }
            });
        },
        
        fetchContent() {
            const sel = document.getElementById('sageLogFileSelect');
            if (!sel) return;
            this.currentFile = sel.value;
            if(!this.currentFile) return;
            
            fetch('scheduler_log_fetch.php?file=' + encodeURIComponent(this.currentFile))
            .then(r => r.text())
            .then(txt => {
                const lb = document.getElementById('sageLogBox');
                if (!lb) return;
                lb.textContent = txt;
                if(document.getElementById('sageAutoScroll').checked) {
                    lb.scrollTop = lb.scrollHeight;
                }
            });
        },
        
        openModal(id = null) {
            const modal = document.getElementById('sageWrapperModal');
            document.getElementById('sw_delete_btn').style.display = id ? 'block' : 'none';
            document.getElementById('sageWrapperModalTitle').textContent = id ? 'Edit Preset' : 'New Preset';
            
            if (id) {
                const w = this.wrappers.find(x => x.id == id);
                if (w) {
                    document.getElementById('sw_id').value = w.id;
                    document.getElementById('sw_task_id').value = w.task_id;
                    document.getElementById('sw_name').value = w.name;
                    document.getElementById('sw_summary').value = w.summary || '';
                    document.getElementById('sw_args').value = w.fixed_args || '';
                    document.getElementById('sw_icon').value = w.icon || '🚀';
                    document.getElementById('sw_order').value = (w.display_order !== undefined && w.display_order !== null) ? w.display_order : 0;
                }
            } else {
                document.getElementById('sageWrapperForm').reset();
                document.getElementById('sw_id').value = '0';
                const maxOrder = this.wrappers.reduce((max, w) => Math.max(max, parseInt(w.display_order) || 0), -1);
                document.getElementById('sw_order').value = maxOrder + 1;
            }
            modal.classList.add('open');
        },
        
        save(e) {
            e.preventDefault();
            const fd = new FormData();
            fd.append('profile_name', _currentProfile);
            fd.append('wrapper_id', document.getElementById('sw_id').value);
            fd.append('task_id', document.getElementById('sw_task_id').value);
            fd.append('name', document.getElementById('sw_name').value);
            fd.append('summary', document.getElementById('sw_summary').value);
            fd.append('fixed_args', document.getElementById('sw_args').value);
            fd.append('icon', document.getElementById('sw_icon').value);
            fd.append('display_order', document.getElementById('sw_order').value);
            
            fetch(API_URL + 'save_wrapper', {method:'POST', body:fd})
            .then(r=>r.json()).then(res=>{
                if(res.ok) {
                    toast('Preset saved');
                    document.getElementById('sageWrapperModal').classList.remove('open');
                    this.loadWrappers();
                } else toast('Save failed', true);
            });
        },
        
        del() {
            if(!confirm('Delete this preset?')) return;
            const fd = new FormData();
            fd.append('id', document.getElementById('sw_id').value);
            fetch(API_URL + 'delete_wrapper', {method:'POST', body:fd})
            .then(r=>r.json()).then(res=>{
                if(res.ok) {
                    toast('Deleted');
                    document.getElementById('sageWrapperModal').classList.remove('open');
                    this.loadWrappers();
                }
            });
        },
        
        copyDefaultArgs(e) {
            e.preventDefault();
            const sel = document.getElementById('sw_task_id');
            const def = sel.options[sel.selectedIndex].getAttribute('data-def-args');
            document.getElementById('sw_args').value = def;
        }
    },

    // ── CLIPBOARD ENGINE ──
    cb: {
        async load() {
            const list = document.getElementById('ft-cb-list');
            list.innerHTML = '<li class="ft-cb-empty">Loading…</li>';
            try {
                const r = await fetch(CB_URL + '?api_action=cb_get&view_area=' + encodeURIComponent(_cbArea));
                const d = await r.json();
                if (d.status !== 'success') throw new Error(d.message);
                _cbItems = d.data;
                this.render();
            } catch(e) {
                list.innerHTML = '<li class="ft-cb-empty" style="color:var(--ft-red);">Load failed</li>';
            }
        },
        render() {
            const list = document.getElementById('ft-cb-list');
            list.innerHTML = '';
            if (!_cbItems.length) {
                list.innerHTML = '<li class="ft-cb-empty">No items — add one above</li>';
                return;
            }
            _cbItems.forEach(item => {
                const li = document.createElement('li');
                li.className = 'ft-cb-item' + (parseInt(item.pinned) ? ' pinned' : '');
                li.dataset.id = item.id;
                const hasLabel = item.label && item.label.trim();
                
                li.innerHTML = `
                    <div class="ft-drag-handle" title="Drag to reorder">⣿</div>
                    <div class="ft-cb-body">
                        <div class="ft-cb-label-row">
                            <span class="ft-cb-label${hasLabel ? ' has-label' : ''}">${hasLabel ? esc(item.label) : 'unlabelled'}</span>
                            ${parseInt(item.pinned) ? '<span class="ft-cb-pin-badge">📌</span>' : ''}
                        </div>
                        <div class="ft-cb-text" onclick="SageApp.cb.startEdit(${item.id})">${esc(item.content)}</div>
                        <textarea class="ft-cb-text-edit" rows="2"
                                  onblur="SageApp.cb.cancelEdit(${item.id})"
                                  onkeydown="SageApp.cb.editKey(event,${item.id})">${esc(item.content)}</textarea>
                        <input type="text" class="ft-cb-label-input" value="${esc(item.label)}" placeholder="Label…" maxlength="120"
                               onblur="SageApp.cb.cancelEdit(${item.id})"
                               onkeydown="SageApp.cb.editKey(event,${item.id})">
                    </div>
                    <div class="ft-cb-actions">
                        <button class="ft-ia copy ft-copy-action" data-id="${item.id}" title="Copy">📋</button>
                        <button class="ft-ia pin${parseInt(item.pinned) ? ' on' : ''}" title="Pin/Unpin"
                                onclick="SageApp.cb.pin(${item.id})">${parseInt(item.pinned) ? '📌' : '📍'}</button>
                        <button class="ft-ia edit" title="Edit" onclick="SageApp.cb.startEdit(${item.id})">✏️</button>
                        <button class="ft-ia save" id="ft-save-${item.id}" title="Save" onclick="SageApp.cb.saveEdit(${item.id})">✓</button>
                        <button class="ft-ia del" title="Delete" onclick="SageApp.cb.del(${item.id})">🗑</button>
                    </div>`;
                list.appendChild(li);
            });
            this.initSortable();
        },
        initSortable() {
            if (_cbSortable) _cbSortable.destroy();
            const list = document.getElementById('ft-cb-list');
            if (window.Sortable) {
                _cbSortable = Sortable.create(list, {
                    handle: '.ft-drag-handle', animation: 140, ghostClass: 'sortable-ghost', chosenClass: 'sortable-chosen',
                    onEnd: () => this.saveOrder(),
                });
            }
        },
        async saveOrder() {
            const lis = document.querySelectorAll('#ft-cb-list .ft-cb-item');
            const orders = Array.from(lis).map((li, i) => ({ id: parseInt(li.dataset.id), sort_order: i }));
            await this.api('cb_reorder', { view_area: _cbArea, orders });
        },
        async add() {
            const content = document.getElementById('ft-cb-new-content').value.trim();
            const label   = document.getElementById('ft-cb-new-label').value.trim();
            if (!content) { document.getElementById('ft-cb-new-content').focus(); return; }
            const r = await this.api('cb_add', { content, label, view_area: _cbArea });
            if (r.status !== 'success') { toast(r.message || 'Error', true); return; }
            document.getElementById('ft-cb-new-content').value = '';
            document.getElementById('ft-cb-new-label').value   = '';
            toast('Added ✓');
            await this.load();
        },
        startEdit(id) {
            const li = document.querySelector(`.ft-cb-item[data-id="${id}"]`);
            if (!li) return;
            li.querySelector('.ft-cb-text').classList.add('editing');
            li.querySelector('.ft-cb-text-edit').classList.add('active');
            li.querySelector('.ft-cb-label-input').classList.add('active');
            li.querySelector('.ft-ia.edit').classList.add('on');
            const saveBtn = document.getElementById('ft-save-' + id);
            if (saveBtn) saveBtn.classList.add('on');
            const ta = li.querySelector('.ft-cb-text-edit');
            ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length);
        },
        cancelEdit(id) {
            setTimeout(() => {
                const li = document.querySelector(`.ft-cb-item[data-id="${id}"]`);
                if (!li) return;
                const active = document.activeElement;
                if (active && active.closest(`.ft-cb-item[data-id="${id}"]`)) return;
                li.querySelector('.ft-cb-text').classList.remove('editing');
                li.querySelector('.ft-cb-text-edit').classList.remove('active');
                li.querySelector('.ft-cb-label-input').classList.remove('active');
                li.querySelector('.ft-ia.edit').classList.remove('on');
                const saveBtn = document.getElementById('ft-save-' + id);
                if (saveBtn) saveBtn.classList.remove('on');
            }, 150);
        },
        editKey(e, id) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.saveEdit(id); }
            if (e.key === 'Escape') this.cancelEdit(id);
        },
        async saveEdit(id) {
            const li = document.querySelector(`.ft-cb-item[data-id="${id}"]`);
            if (!li) return;
            const content = li.querySelector('.ft-cb-text-edit').value.trim();
            const label   = li.querySelector('.ft-cb-label-input').value.trim();
            if (!content) return;
            const r = await this.api('cb_update', { id, content, label });
            if (r.status !== 'success') { toast(r.message || 'Error', true); return; }
            const item = _cbItems.find(i => i.id == id);
            if (item) { item.content = content; item.label = label; }
            toast('Saved ✓');
            this.render();
        },
        async pin(id) {
            const r = await this.api('cb_pin', { id, view_area: _cbArea });
            if (r.status !== 'success') { toast(r.message || 'Error', true); return; }
            const item = _cbItems.find(i => i.id == id);
            if (item) item.pinned = r.pinned;
            _cbItems.sort((a,b) => parseInt(b.pinned) - parseInt(a.pinned));
            toast(r.pinned ? 'Pinned 📌' : 'Unpinned');
            this.render();
        },
        async del(id) {
            if (!confirm('Delete this item?')) return;
            const r = await this.api('cb_delete', { id });
            if (r.status !== 'success') { toast(r.message || 'Error', true); return; }
            _cbItems = _cbItems.filter(i => i.id != id);
            toast('Deleted');
            this.render();
        },
        async api(action, body) {
            const url = CB_URL + '?api_action=' + action + '&view_area=' + encodeURIComponent(_cbArea);
            const res = await fetch(url, {
                method: body ? 'POST' : 'GET',
                headers: body ? { 'Content-Type': 'application/json' } : {},
                body: body ? JSON.stringify(body) : undefined,
            });
            return res.json();
        }
    },

    // ── SETTINGS ENGINE ──
    settings: {
        render() {
            this.renderShortcutList();
            this.renderCustomTabsList();
            this.renderCreativesVisibilityList();
        },
        renderShortcutList() {
            const list = document.getElementById('ft-shortcut-list');
            list.innerHTML = '';
            (_settings.shortcuts || []).forEach((sc, i) => {
                const row = document.createElement('div');
                row.className = 'ft-shortcut-row';
                row.dataset.idx = i;
                row.innerHTML = `
                    <div class="ft-drag-handle">⣿</div>
                    <div class="ft-shortcut-row-icon">${sc.icon || '⚗'}</div>
                    <div style="flex:1;min-width:0;">
                        <div class="ft-shortcut-row-label">${esc(sc.label)}</div>
                        <div class="ft-shortcut-row-url">${esc(sc.url || '')}</div>
                    </div>
                    <button class="ft-shortcut-row-visible${sc.visible !== false ? ' on' : ''}" title="${sc.visible !== false ? 'Visible' : 'Hidden'}" onclick="SageApp.settings.toggleVisible(${i})">${sc.visible !== false ? '☑️' : '⬛'}</button>
                    <button class="ft-shortcut-row-edit" title="Edit" onclick="SageApp.settings.editShortcut(${i})">✏️</button>
                    <button class="ft-shortcut-row-del" title="Remove" onclick="SageApp.settings.removeShortcut(${i})">🗑</button>`;
                list.appendChild(row);
            });
            if (_scSortable) _scSortable.destroy();
            if (window.Sortable) {
                _scSortable = Sortable.create(list, {
                    handle: '.ft-drag-handle', animation: 120,
                    onEnd: () => {
                        const rows = list.querySelectorAll('.ft-shortcut-row');
                        _settings.shortcuts = Array.from(rows).map(r => _settings.shortcuts[parseInt(r.dataset.idx)]);
                        this.renderShortcutList();
                    },
                });
            }
        },
        toggleVisible(i) {
            _settings.shortcuts[i].visible = !(_settings.shortcuts[i].visible !== false);
            this.renderShortcutList();
        },
        removeShortcut(i) {
            _settings.shortcuts.splice(i, 1);
            this.renderShortcutList();
        },
        editShortcut(i) {
            const sc = _settings.shortcuts[i];
            document.getElementById('ft-sc-new-icon').value = sc.icon || '';
            document.getElementById('ft-sc-new-label').value = sc.label || '';
            document.getElementById('ft-sc-new-url').value = sc.url || '';
            document.getElementById('ft-sc-new-modal').checked = !!sc.modal;
            document.getElementById('ft-sc-new-fullscreen').checked = !!sc.fullscreen;
            
            const btn = document.getElementById('ft-sc-add-btn');
            btn.textContent = '✓ Save';
            btn.dataset.editIdx = i;
        },
        addShortcut() {
            const icon  = document.getElementById('ft-sc-new-icon').value.trim() || '⚗';
            const label = document.getElementById('ft-sc-new-label').value.trim();
            const url   = document.getElementById('ft-sc-new-url').value.trim();
            const modal = document.getElementById('ft-sc-new-modal').checked;
            const full  = document.getElementById('ft-sc-new-fullscreen').checked;
            if (!label) { toast('Label required', true); return; }
            
            const btn = document.getElementById('ft-sc-add-btn');
            const editIdx = btn.dataset.editIdx;
            
            if (editIdx !== undefined && editIdx !== "") {
                _settings.shortcuts[editIdx] = { ..._settings.shortcuts[editIdx], icon, label, url, modal: modal || full, fullscreen: full };
                btn.textContent = '+ Add';
                btn.dataset.editIdx = "";
                toast('Shortcut updated ✓');
            } else {
                _settings.shortcuts.push({ icon, label, url, modal: modal || full, fullscreen: full, visible: true });
                toast('Shortcut added ✓');
            }
            document.getElementById('ft-sc-new-icon').value  = '';
            document.getElementById('ft-sc-new-label').value = '';
            document.getElementById('ft-sc-new-url').value   = '';
            document.getElementById('ft-sc-new-modal').checked = false;
            document.getElementById('ft-sc-new-fullscreen').checked = false;
            this.renderShortcutList();
        },
        renderCustomTabsList() {
            const list = document.getElementById('ft-custom-tabs-list');
            list.innerHTML = '';
            (_settings.custom_tabs || []).forEach((tab, i) => {
                const row = document.createElement('div');
                row.className = 'ft-custom-tab-row';
                row.innerHTML = `
                    <div class="ft-custom-tab-icon">${tab.icon || '🗂'}</div>
                    <div class="ft-custom-tab-info">
                        <div class="ft-custom-tab-name">${esc(tab.label)}</div>
                        <div class="ft-custom-tab-url">${esc(tab.url)}</div>
                    </div>
                    <button class="ft-custom-tab-edit" title="Edit" onclick="SageApp.settings.editCustomTab(${i})">✏️</button>
                    <button class="ft-custom-tab-del" title="Remove" onclick="SageApp.settings.removeCustomTab(${i})">🗑</button>`;
                list.appendChild(row);
            });
        },
        editCustomTab(i) {
            const tab = _settings.custom_tabs[i];
            document.getElementById('ft-tab-new-icon').value = tab.icon || '';
            document.getElementById('ft-tab-new-label').value = tab.label || '';
            document.getElementById('ft-tab-new-url').value = tab.url || '';
            const btn = document.getElementById('ft-tab-add-btn');
            btn.textContent = '✓ Save';
            btn.dataset.editIdx = i;
        },
        addCustomTab() {
            const icon  = document.getElementById('ft-tab-new-icon').value.trim() || '🗂';
            const label = document.getElementById('ft-tab-new-label').value.trim();
            const url   = document.getElementById('ft-tab-new-url').value.trim();
            if (!label || !url) { toast('Label and URL required', true); return; }
            if (!_settings.custom_tabs) _settings.custom_tabs = [];
            
            const btn = document.getElementById('ft-tab-add-btn');
            const editIdx = btn.dataset.editIdx;
            
            if (editIdx !== undefined && editIdx !== "") {
                _settings.custom_tabs[editIdx] = { ..._settings.custom_tabs[editIdx], icon, label, url };
                btn.textContent = '+ Tab';
                btn.dataset.editIdx = "";
                toast('Tab updated — save settings to apply ✓');
            } else {
                _settings.custom_tabs.push({ icon, label, url });
                toast('Tab added — save settings to apply ✓');
            }
            document.getElementById('ft-tab-new-icon').value  = '';
            document.getElementById('ft-tab-new-label').value = '';
            document.getElementById('ft-tab-new-url').value   = '';
            this.renderCustomTabsList();
        },
        removeCustomTab(i) {
            _settings.custom_tabs.splice(i, 1);
            this.renderCustomTabsList();
        },

        // ── Creatives & Entities Visibility Section ──
        renderCreativesVisibilityList() {
            const list = document.getElementById('ft-creatives-settings-list');
            if (!list) return;
            list.innerHTML = '';
            
            const visibility = _settings.creatives_visibility || {};
            
            CREATIVES_ENTITIES_DEFS.forEach(def => {
                const isVisible = (visibility[def.id] !== false);
                const row = document.createElement('div');
                row.className = 'ft-shortcut-row';
                row.innerHTML = `
                    <div class="ft-shortcut-row-icon">${def.icon}</div>
                    <div style="flex:1;min-width:0;">
                        <div class="ft-shortcut-row-label">${esc(def.label)}</div>
                        <div class="ft-shortcut-row-url">${esc(def.section)}</div>
                    </div>
                    <button class="ft-shortcut-row-visible${isVisible ? ' on' : ''}" 
                            title="${isVisible ? 'Visible' : 'Hidden'}" 
                            onclick="SageApp.settings.toggleCreativeVisibility('${def.id}')">
                        ${isVisible ? '☑️' : '⬛'}
                    </button>`;
                list.appendChild(row);
            });
        },
        toggleCreativeVisibility(id) {
            if (!_settings.creatives_visibility) _settings.creatives_visibility = {};
            const current = (_settings.creatives_visibility[id] !== false);
            _settings.creatives_visibility[id] = !current;
            this.renderCreativesVisibilityList();
            SageApp.applyCreativesVisibility();
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    function waitForDependencies() {
        if (window.Sortable) SageApp.init();
        else setTimeout(waitForDependencies, 50);
    }
    waitForDependencies();
});

})();
</script>
<script src="/js/theme-manager.js"></script>
</body>
</html>