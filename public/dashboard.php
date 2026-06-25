<?php
// public/dashboard.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
$dbname = $spw->getDbName();
$items =[];
require 'sage_entities_items_array.php';

// Token saving logic from original
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'save_tokens') {
    $tokenDir = PROJECT_ROOT . '/token';
    $results =[];
    if (!is_dir($tokenDir)) {
        @mkdir($tokenDir, 0700, true);
    }
    $tokenMappings =[
        'groq_api_key' => '.groq_api_key',
        'freepik_api_key' => '.freepik_api_key',
        'pollinations_token' => '.pollinationsaitoken',
        'google_api_key' => '.gemini_api_key',
        'mistral_api_key' => '.mistral_api_key',
        'cohere_api_key' => '.cohere_api_key',
        'cerebras_api_key' => '.cerebras_api_key'
    ];
    foreach ($tokenMappings as $postKey => $filename) {
        $value = trim($_POST[$postKey] ?? '');
        if ($value !== '') {
            $filepath = $tokenDir . '/' . $filename;
            $writeResult = @file_put_contents($filepath, $value);
            if ($writeResult !== false) {
                @chmod($filepath, 0600);
                $results['success'][] = $filename;
            } else {
                $results['failed'][] = $filename;
            }
        }
    }
    if (!empty($results['success'])) {
        $msg = "✓ Tokens saved:\n" . implode("\n", $results['success']);
        if (!empty($results['failed'])) {
            $msg .= "\n\n⚠ Failed to save:\n" . implode("\n", $results['failed']);
        }
        $tokenMessage = ['type' => empty($results['failed']) ? 'success' : 'warning', 'text' => $msg];
    } elseif (!empty($results['failed'])) {
        $tokenMessage =['type' => 'error', 'text' => "✗ Failed to save tokens:\n" . implode("\n", $results['failed'])];
    } else {
        $tokenMessage =['type' => 'warning', 'text' => '⚠ No tokens provided to save'];
    }
}
$tokenDir = PROJECT_ROOT . '/token';
$existingTokens =[
    'groq_api_key' => '',
    'freepik_api_key' => '',
    'pollinations_token' => '',
    'google_api_key' => '',
    'mistral_api_key' => '',
    'cohere_api_key' => '',
    'cerebras_api_key' => ''
];
$tokenFiles =[
    'groq_api_key' => '.groq_api_key',
    'freepik_api_key' => '.freepik_api_key',
    'pollinations_token' => '.pollinationsaitoken',
    'google_api_key' => '.gemini_api_key',
    'mistral_api_key' => '.mistral_api_key',
    'cohere_api_key' => '.cohere_api_key',
    'cerebras_api_key' => '.cerebras_api_key'
];
foreach ($tokenFiles as $key => $filename) {
    $filepath = $tokenDir . '/' . $filename;
    if (file_exists($filepath)) {
        $existingTokens[$key] = trim(file_get_contents($filepath));
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.9, viewport-fit=cover">
    <title>Starlight Guardians Dashboard</title>
    <link rel="manifest" href="/site.webmanifest">
    <script>
        (function() {
            try {
                var theme = localStorage.getItem('spw_theme');
                if (theme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else if (theme === 'light') {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            } catch (e) {}
        })();
    </script>
    
    <?php if (\App\Core\SpwBase::CDN_USAGE) : ?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <?php else : ?>
        <link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css">
    <?php endif; ?>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <link rel="stylesheet" href="/css/base.css">
    
    <style>
    /* ═══════════════════════════════════════════════════════════════════════════
       FORGE — Design System Base
    ═══════════════════════════════════════════════════════════════════════════ */
    :root {
        --bg:           #080b10;
        --surface:      #0e1319;
        --card:         #111820;
        --card-hover:   #141e28;
        --border:       #1c2535;
        --border-glow:  #2a3a52;
        --text:         #c8d4e8;
        --text-dim:     #5a6a80;
        --text-bright:  #e8f0ff;
        --amber:        #f5a623;
        --amber-dim:    rgba(245, 166, 35, 0.08);
        --amber-mid:    rgba(245, 166, 35, 0.15);
        --amber-glow:   rgba(245, 166, 35, 0.4);
        --green:        #22d3a0;
        --green-dim:    rgba(34, 211, 160, 0.1);
        --red:          #f05060;
        --red-dim:      rgba(240, 80, 96, 0.1);
        --blue:         #4da6ff;
        --blue-dim:     rgba(77, 166, 255, 0.1);
        --mono:         'Space Mono', 'Fira Mono', monospace;
        --sans:         'Syne', system-ui, sans-serif;
        --radius:       6px;
        --radius-lg:    10px;
    }

    /* LIGHT THEME OVERRIDES */
    @media (prefers-color-scheme: light) {
        :root {
            --bg:           #f6f8fa;
            --surface:      #e1e4e8;
            --card:         #ffffff;
            --card-hover:   #f3f4f6;
            --border:       #d1d5db;
            --border-glow:  #9ca3af;
            --text:         #111827;
            --text-dim:     #4b5563;
            --text-bright:  #000000;
            --amber:        #d97706;
            --amber-dim:    rgba(217, 119, 6, 0.1);
            --amber-mid:    rgba(217, 119, 6, 0.2);
            --amber-glow:   rgba(217, 119, 6, 0.4);
            --green:        #059669;
            --green-dim:    rgba(5, 150, 105, 0.1);
            --red:          #dc2626;
            --red-dim:      rgba(220, 38, 38, 0.1);
            --blue:         #2563eb;
            --blue-dim:     rgba(37, 99, 235, 0.1);
        }
    }

    :root[data-theme="light"], html[data-theme="light"], body[data-theme="light"] {
        --bg: #f6f8fa; --surface: #e1e4e8; --card: #ffffff; --card-hover: #f3f4f6; --border: #d1d5db; --border-glow: #9ca3af;
        --text: #111827; --text-dim: #4b5563; --text-bright: #000000;
        --amber: #d97706; --amber-dim: rgba(217,119,6,0.1); --amber-mid: rgba(217,119,6,0.2); --amber-glow: rgba(217,119,6,0.4);
        --green: #059669; --green-dim: rgba(5,150,105,0.1);
        --red: #dc2626; --red-dim: rgba(220,38,38,0.1);
        --blue: #2563eb; --blue-dim: rgba(37,99,235,0.1);
    }

    :root[data-theme="dark"], html[data-theme="dark"], body[data-theme="dark"] {
        --bg: #080b10; --surface: #0e1319; --card: #111820; --card-hover: #141e28; --border: #1c2535; --border-glow: #2a3a52;
        --text: #c8d4e8; --text-dim: #5a6a80; --text-bright: #e8f0ff;
        --amber: #f5a623; --amber-dim: rgba(245,166,35,0.08); --amber-mid: rgba(245,166,35,0.15); --amber-glow: rgba(245,166,35,0.4);
        --green: #22d3a0; --green-dim: rgba(34,211,160,0.1);
        --red: #f05060; --red-dim: rgba(240,80,96,0.1);
        --blue: #4da6ff; --blue-dim: rgba(77,166,255,0.1);
    }

    /* ── BASE LAYOUT ── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { 
        height: 100vh; 
        display: flex; 
        flex-direction: column; 
        background: var(--bg); 
        color: var(--text); 
        font-family: var(--sans); 
        font-size: 14px; 
        line-height: 1.5; 
        overflow: hidden; 
    }

    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--border-glow); border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--text-dim); }

    /* Header Image Area */
    .dashboard-header-image {
        flex-shrink: 0;
        position: relative;
        text-align: center;
        background: #000;
    }
    .dashboard-header-image img {
        width: 100%;
        max-height: 400px;
        object-fit: cover;
        display: block;
    }

    .forge-layout { 
        flex: 1; 
        display: grid; 
        grid-template-rows: 52px 1fr; 
        grid-template-columns: 280px 1fr; 
        grid-template-areas: "header header" "sidebar main"; 
        overflow: hidden; 
        min-height: 0; /* Important for scroll */
    }

    /* ── HEADER ── */
    .forge-header { grid-area: header; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; background: var(--surface); border-bottom: 1px solid var(--border); border-top: 1px solid var(--border); position: relative; z-index: 100; }
    .forge-logo { display: flex; align-items: center; gap: 10px; font-family: var(--mono); font-size: 0.95rem; font-weight: 700; color: var(--amber); letter-spacing: 2px; text-transform: uppercase; }
    .forge-logo-icon { width: 28px; height: 28px; background: var(--amber-mid); border: 1px solid var(--amber-glow); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-size: 14px; }
    .forge-header-right { display: flex; align-items: center; gap: 10px; }

    .btn-icon-sm { width: 36px; height: 36px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; font-size: 15px; }
    .btn-icon-sm:hover { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }

/* ── SIDEBAR (ICON GRID) ── */
    .forge-sidebar { grid-area: sidebar; background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; }
    .sidebar-search { padding: 12px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
    .sidebar-list { 
        flex: 1; 
        overflow-y: auto; 
        padding: 3px; 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(36px, 1fr)); 
        gap: 10px; 
        align-content: start; 
    }

    .task-card { 
        padding: 0; 
        border-radius: var(--radius); 
        border: 1px solid transparent; 
        cursor: pointer; 
        transition: all 0.15s; 
        position: relative; 
        background: transparent; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        aspect-ratio: 1; /* Makes them perfect squares */
    }
    .task-card:hover { background: var(--card); border-color: var(--border); transform: translateY(-2px); }
    .task-card.active { background: var(--amber-dim); border-color: var(--amber); }
    .task-card.active i { color: var(--amber); opacity: 1 !important; }
    
    .task-card-body { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; }
    .task-card-title { font-size: 1.3rem; display: flex; align-items: center; justify-content: center; margin: 0; padding: 0; color: var(--text-bright); transition: color 0.15s; }
    .task-card-title i { margin-right: 0 !important; transition: opacity 0.15s, color 0.15s; }
    
    .task-card-indicator { position: absolute; left: -1px; top: 50%; transform: translateY(-50%); width: 3px; height: 0; background: var(--amber); border-radius: 0 2px 2px 0; transition: height 0.2s; }
    .task-card.active .task-card-indicator { height: 60%; }
    
    
    
    
    
    /* ── MAIN AREA ── */
    .forge-main { grid-area: main; display: flex; flex-direction: column; overflow: hidden; background: var(--bg); }
    .forge-workspace { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    .workspace-body { flex: 1; overflow-y: auto; padding: 20px; padding-bottom:60px; }

    /* ── LINK CARDS (DASHBOARD SPECIFIC) ── */
    .link-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 6px;
    }
    
    .link-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 10px 12px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        transition: all 0.15s;
        text-decoration: none;
        color: var(--text);
        margin-bottom: 1px;
    }
    a.link-card { cursor: pointer; }
    a.link-card:hover { color: var(--text); }

    .link-card:hover {
        background: var(--card-hover);
        border-color: var(--amber);
    }
    .link-card-title {
        font-family: var(--sans);
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--text-bright);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .link-card-actions {
        display: flex;
        gap: 6px;
        margin-top: auto;
    }
    .link-card-actions > * {
        flex: 1;
        text-align: center;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 10px;
        font-size: 0.75rem;
    }

    /* Buttons */
    .btn-forge-primary { background: var(--amber); color: #000; border: none; border-radius: var(--radius); cursor: pointer; font-family: var(--mono); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; transition: all 0.15s; }
    .btn-forge-primary:hover { filter: brightness(1.1); }
    .btn-forge-secondary { background: transparent; color: var(--text-dim); border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; font-family: var(--mono); transition: all 0.15s; }
    .btn-forge-secondary:hover { border-color: var(--border-glow); color: var(--text); }
    
    
   /* ── DUO BUTTONS (SPLIT CARDS) ── */
    .duo-button-group {
        display: flex;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        transition: all 0.15s;
        margin-bottom: 1px;
    }
    .duo-button-group:hover {
        border-color: var(--amber);
        transform: translateY(-2px);
    }
    .duo-button-half {
        flex: 1;
        padding: 10px;
        text-align: center;
        text-decoration: none;
        color: var(--text-bright);
        font-family: var(--sans);
        font-weight: 600;
        font-size: 0.85rem;
        transition: background 0.15s, color 0.15s;
        display: flex;
        align-items: left;
        justify-content: left;
        gap: 6px;
    }
    .duo-button-half:first-child {
        border-right: 1px solid var(--border);
    }
    .duo-button-half:hover {
        background: var(--card-hover);
        color: var(--amber);
    }
    
    
   /* ── MULTI BUTTONS (TRIPLE CARDS) ── */
    .multi-button-group {
        display: flex;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        transition: all 0.15s;
        margin-bottom: 1px;
    }
    .multi-button-group:hover {
        border-color: var(--amber);
        transform: translateY(-2px);
    }
    .multi-button-main {
        flex: 1;
        padding: 10px;
        text-align: left;
        text-decoration: none;
        color: var(--text-bright);
        font-family: var(--sans);
        font-weight: 600;
        font-size: 0.85rem;
        transition: background 0.15s, color 0.15s;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .multi-button-action {
        flex: 0 0 65px; /* Fixed width for CRUD */
        padding: 0;
        text-align: center;
        text-decoration: none;
        color: var(--text-dim);
        font-family: var(--mono);
        font-size: 0.75rem;
        font-weight: 700;
        border: none;
        border-left: 1px solid var(--border);
        background: transparent;
        transition: background 0.15s, color 0.15s;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    .multi-button-action.run-btn {
        flex: 0 0 45px; /* Slightly narrower for the icon */
        font-size: 0.95rem;
    }
    .multi-button-main:hover, .multi-button-action:hover {
        background: var(--card-hover);
        color: var(--amber);
    }
    
    
    
    
   /* ── TRIO BUTTONS (EQUAL THIRDS) ── */
    .trio-button-group {
        display: flex;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        transition: all 0.15s;
        margin-bottom: 3px;
    }
    .trio-button-group:hover {
        border-color: var(--amber);
        transform: translateY(-2px);
    }
    .trio-button-third {
        flex: 1;
        padding: 12px 6px;
        text-align: center;
        text-decoration: none;
        color: var(--text-bright);
        font-family: var(--sans);
        font-weight: 600;
        font-size: 0.85rem;
        transition: background 0.15s, color 0.15s;
        display: flex;
        align-items: left;
        justify-content: left;
        text-align: left;
        gap: 4px;
        border-right: 1px solid var(--border);
    }
    .trio-button-third:last-child {
        border-right: none;
    }
    .trio-button-third:hover {
        background: var(--card-hover);
        color: var(--amber);
    }
    
    
    
    /* Form */
    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-family: var(--mono); font-size: 0.75rem; color: var(--text-dim); letter-spacing: 0.5px; margin-bottom: 8px; }
    .form-input { width: 100%; padding: 10px 12px; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--mono); font-size: 0.85rem; transition: border-color 0.15s, background 0.15s; }
    .form-input:focus { outline: none; border-color: var(--amber); background: var(--card-hover); }

    /* ── MODALS ── */
    .forge-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(3px); z-index: 10000; display: none; align-items: center; justify-content: center; padding: 3px; }
    .forge-modal-overlay.open { display: flex; animation: fadeIn 0.2s ease; }
    .forge-modal { background: var(--surface); border: 1px solid var(--border-glow); border-radius: var(--radius-lg); width: 100%; display: flex; flex-direction: column; box-shadow: 0 6px 10px rgba(0,0,0,0.6); animation: modalIn 0.2s ease; }
    .forge-modal-header { padding: 6px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
    .forge-modal-title { font-family: var(--mono); font-size: 0.9rem; font-weight: 700; color: var(--amber); text-transform: uppercase; letter-spacing: 1.5px; }
    .forge-modal-close { width: 28px; height: 28px; border-radius: 4px; border: 1px solid var(--border); background: transparent; color: var(--text-dim); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; font-size: 14px; }
    .forge-modal-close:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }
    .forge-modal-body { padding: 20px; overflow-y: auto; flex: 1; }

    /* Category fade in */
    .cat-content { animation: fadeIn 0.2s ease-out; }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes modalIn { from { opacity: 0; transform: scale(0.96) translateY(-10px); } to { opacity: 1; transform: none; } }

    @media (max-width: 900px) {
        .forge-layout { grid-template-columns: 1fr; grid-template-rows: 52px 170px 1fr; grid-template-areas: "header" "sidebar" "main"; }
        .forge-sidebar { border-right: none; border-bottom: 1px solid var(--border); }
    }
    </style>
    
    <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
</head>
<body>
    <?php require_once __DIR__ . '/forge_tool.php'; ?>

    <!-- ── THEATRICAL HEADER ── -->
    
   <div class="dashboard-header-image">
        <img src="theatrical.jpg" alt="Starlight Guardians Theatrical Poster">
        <div style="position:absolute; top:12px; right:16px; display:flex; gap:8px; align-items:center;">
        
                   
            <a href="dashdocs.php" title="Documentation Mode" style="background: none; border: none; font-size:22px; cursor:pointer; text-decoration: none; display: flex; align-items: center; justify-content: center; height: 36px; width: 36px;">✨</a>
            
        
            <button id="themeToggle" aria-pressed="false" title="Toggle theme" style="background: none; border: none; font-size:24px; cursor:pointer;">🌙</button>
            
            

            
            
            
        </div>
    </div>
    
    
    <?php /*
    <div class="dashboard-header-image">
        <img src="theatrical.jpg" alt="Starlight Guardians Theatrical Poster">
        <div style="position:absolute; top:12px; right:16px; display:flex; gap:8px; align-items:center;">
            <button id="themeToggle" aria-pressed="false" title="Toggle theme" style="background: none; border: none; font-size:24px; cursor:pointer;">🌙</button>
        </div>
    </div>
    */
    ?>
    

    <div class="forge-layout">
        <!-- ── FORGE HEADER ── -->
        <header class="forge-header">
            <div class="forge-logo" title="SAGE Dashboard">
                <div class="forge-logo-icon"><i class="bi bi-grid-1x2-fill"></i></div>
            </div>
            <div class="forge-header-right">
                <button class="btn-icon-sm app-modal-btn" data-src="scheduler_forge.php" data-title="Scheduler Forge" data-icon="bi-stopwatch" title="Scheduler Forge">
                    <i class="bi bi-stopwatch"></i>
                </button>
                <button class="btn-icon-sm app-modal-btn" data-src="view_queue.php" data-title="Queue Viewer" data-icon="bi-list-task" title="Queue Viewer">
                    <i class="bi bi-list-task"></i>
                </button>
                
                <!-- NEW: Stats Button -->
                <button class="btn-icon-sm app-modal-btn" data-src="view_stats.php" data-title="Stats" data-icon="bi-graph-up" title="Stats">
                    <i class="bi bi-graph-up"></i>
                </button>

                <button class="btn-icon-sm app-modal-btn" data-src="kaggle.php" data-title="Notebooks" data-icon="bi-laptop" title="Notebooks">
                    <i class="bi bi-laptop"></i>
                </button>
                <button class="btn-icon-sm app-modal-btn" data-src="generator_forge.php" data-title="Generators Forge" data-icon="bi-robot" title="Generators Forge">
                    <i class="bi bi-robot"></i>
                </button>
                <button class="btn-icon-sm app-modal-btn" data-src="view_scheduler_log.php" data-title="Notebook Logs" data-icon="bi-journal-code" title="Notebook Logs">
                    <i class="bi bi-journal-code"></i>
                </button>
                <button class="btn-icon-sm app-modal-btn" data-src="scheduler_runner.php" data-title="Control Deck" data-icon="bi-hurricane" title="Control Deck">
                    <i class="bi bi-hurricane"></i>
                </button>
            </div>
        </header>

        <!-- ── SIDEBAR ── -->
        <aside class="forge-sidebar">
            <div class="sidebar-search">
                <?php require 'ai_search.php'; ?>
            </div>
            <div class="sidebar-list" id="sidebarList">
                <!-- Javascript will render categories here -->
            </div>
        </aside>

        <!-- ── MAIN WORKSPACE ── -->
        <main class="forge-main">
            <div class="forge-workspace">
                <div class="workspace-body" id="workspaceBody">
                    
                    <?php if (isset($tokenMessage)) : ?>
                        <div style="padding: 16px 20px; border-radius: var(--radius); margin-bottom: 20px; background: <?= $tokenMessage['type'] === 'success' ? 'var(--green-dim)' : 'var(--red-dim)' ?>; border: 1px solid <?= $tokenMessage['type'] === 'success' ? 'var(--green)' : 'var(--red)' ?>;">
                            <div style="color: <?= $tokenMessage['type'] === 'success' ? 'var(--green)' : 'var(--red)' ?>; font-family: var(--mono); white-space: pre-wrap; font-size: 0.85rem;">
                                <?= htmlspecialchars($tokenMessage['text']) ?>
                            </div>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', () => selectCategory('cat-tokens'));
                        </script>
                    <?php endif; ?>

                    <!-- CATEGORY CONTENTS -->
                    
                    <!-- Category: Editorial -->
                    <div class="cat-content" id="cat-editorial" style="display:none;">
                        <div class="link-grid">
                        
                        
                            
                            <a href="view_editorial_sequences.php?episode_id=1" class="link-card"><div class="link-card-title">🪯 Editorial</div></a>
                            
                           
                        
                            <a href="boards_view.php" class="link-card"><div class="link-card-title">🛹 Boards</div></a>
                            
                            
                            
                            
                            <a href="view_storyboards.php" class="link-card"><div class="link-card-title">📽️ Storyboards</div></a>
                            
                            
                            
                            
                                                                                   
                            <a href="sbcut.php" class="link-card"><div class="link-card-title">🔪 Storyboard Cut</div></a>
                            
                            
                            
                            
                            
                            
                            <a href="view_storyboards_chains.php" class="link-card"><div class="link-card-title">☍ SB Chains</div></a>
                            
                            <a href="view_storyboards_pg.php" class="link-card"><div class="link-card-title">🧨 Hot Storyboards</div></a>
                            
                            
                            
                            

                            
                            
                            
                            
                            <a href="view_datamining_storyboards.php" class="link-card"><div class="link-card-title">📊 Storyboards Datamining</div></a>
                        </div>
                    </div>

                    <!-- Category: Pre -->
                    <div class="cat-content" id="cat-pre" style="display:none;">
                        <div class="link-grid">
                            
                            <!-- Duo Buttons -->
                            <div class="duo-button-group">
                                <a href="narratives_v11.php" class="duo-button-half">🪡 Narratives</a>
                                <a href="auto_narratives_v11.php" class="duo-button-half">🧵 Auto Sequencer</a>
                            </div>
                            
                            
                            
                            
                            <a href="narseq.php" class="link-card"><div class="link-card-title">✂️ Sequence Cut</div></a>
                            
                           
                                                      
                            <a href="beap.php" class="link-card"><div class="link-card-title">💭 Beap</div></a>
                            
                            
                           
                            <a href="bang.php" class="link-card"><div class="link-card-title">🗯️ Bang</div></a>
                            
                            
                            
                                                       
                            <a href="fuki.php" class="link-card"><div class="link-card-title">🈵️ Fuki</div></a>
                            
                            
                            
                                                                                    
                            <a href="cinemagic_editor.php" class="link-card"><div class="link-card-title">🃏 CineMagic Editor</div></a>
                            
                            
                            
                            
                            <!-- Standard Buttons -->
                            <a href="sceki/index.php" class="link-card"><div class="link-card-title">🍳 Scene Kitchen</div></a>
                            <a href="sketch_continuity_batch.php" class="link-card"><div class="link-card-title">🎩 Continuity</div></a>
                            <a href="view_curated_sketches_analysis.php?sort=newest" class="link-card"><div class="link-card-title">🕵️ Scene Curation</div></a>
                            
                            
                            
                            <a href="view_narrative_sequence_analysis.php" class="link-card"><div class="link-card-title">⚖️ Sequence Curation</div></a>
                            
                            
                            
                            
                            
                            
                            <!-- Duo Buttons -->
                            <div class="duo-button-group">
                                <a href="kgnodeseq.php" class="duo-button-half">🌃 KGNodeSeq</a>
                                <a href="animejseq.php" class="duo-button-half">🌆 AnimeJSeq</a>
                            </div>
                            
                            <div class="duo-button-group">
                                <a href="seqview_story.php" class="duo-button-half">🌇 Seq Viewer</a>
                                <a href="recorder.php" class="duo-button-half">🔴 Recorder</a>
                            </div>

                        </div>
                    </div>

                    <!-- Category: Rapid UI -->
                    <div class="cat-content" id="cat-rapid" style="display:none;">
                        <div class="link-grid">
                        
                        <!--
                            <a href="entity_gen.php?entity_type=sketches" class="link-card"><div class="link-card-title">💫 Rapid Create</div></a>
                        -->
                        
                        
                                                                                                
                            <a href="cli_forge.php" class="link-card"><div class="link-card-title">☣️ CLI Forge</div></a>
            
                                                               
                        
                            <!--                                  
                            <a href="md_curator_extract_forge.php" class="link-card"><div class="link-card-title">🪙 MD Curator Extract</div></a>
            
                                                                                   
                            <a href="md_curator_aggregate_forge.php" class="link-card"><div class="link-card-title">📑 MD Curator Aggregate</div></a>
            
           
                        
                                                
                            <a href="autopilot_forge.php" class="link-card"><div class="link-card-title">✈️ Autopilot</div></a>
            
           
                        
                        
                        
                            <a href="kg_to_sketch_generator_forge.php" class="link-card"><div class="link-card-title">🐆 Rapid KG to Sketches</div></a>
            
           
                            <a href="lore_to_sketch_generator_forge.php" class="link-card"><div class="link-card-title">🦖 Rapid Lore to Sketches</div></a>
                         -->
            
            
            
                            
            
                            
                        
                            <a href="rapid_forge.php" class="link-card"><div class="link-card-title">🚀 Rapid Showcase</div></a>
                            <a href="rapid_kg_api_processor.php" class="link-card"><div class="link-card-title">🐇 Rapid API KG Processor</div></a>
                            <a href="rapid_lore_api_processor.php" class="link-card"><div class="link-card-title">🦄 Rapid API Auto Lore Processor</div></a>
                            <a href="rapid_sketches_processor.php" class="link-card"><div class="link-card-title">🦇 Rapid Scene Beats</div></a>
                            
                            <!--
                            <a href="rapid_sketch_sequence_processor.php" class="link-card"><div class="link-card-title">xxx Rapid Sequence Beats</div></a>
                            
                            
                            <a href="rapid_lore_import.php" class="link-card"><div class="link-card-title">🧨 Rapid Lore Import</div></a>
                            <a href="rapid_lore_processor.php" class="link-card"><div class="link-card-title">🐉 Rapid Lore Processor</div></a>
                            
                            -->
                            
                            
                        </div>
                    </div>

                    <!-- Category: Showrunner -->
                    <div class="cat-content" id="cat-showrunner" style="display:none;">
                        <div class="link-grid">
                            
                            
                            
                            <!--
                            <a href="fuzz_forge.php" class="link-card"><div class="link-card-title">💢 Fuzz</div></a>
                            -->
                            
                                                        
                            <div class="duo-button-group">
                                <a href="fuzz_forge.php" class="duo-button-half">💢 Fuzz</a>
                                <a href="fuzzgraph.php" class="duo-button-half">💢 Fuzz Graph</a>
                            </div>
                            
                            
                            
                            <!-- Duo Buttons -->
                            <div class="duo-button-group">
                                <a href="view_kg_docs.php" class="duo-button-half">☀️ Bible</a>
                                <a href="view_curated_docs.php" class="duo-button-half">🌙 Auto Bible</a>
                            </div>
                            
                            <div class="duo-button-group">
                                <a href="kg_view.php" class="duo-button-half">🌳 KG</a>
                                <a href="kg_graph.php" class="duo-button-half">⚛️ KG Graph</a>
                            </div>
                            
                            <div class="duo-button-group">
                                <a href="kg_staging.php" class="duo-button-half">🌴 KG Staging</a>
                                <a href="kg_staging_graph.php" class="duo-button-half">⚛ Staging Graph</a>
                            </div>
                            
                            <div class="duo-button-group">
                                <a href="ag_view.php" class="duo-button-half">🌲 AG</a>
                                <a href="ag_graph.php" class="duo-button-half">🌐 AG Graph</a>
                            </div>

                            <!-- Standard Buttons -->
                            
                            
                            
                           
                            
                            
                                                        
                            <!-- Duo Buttons -->
                            <div class="duo-button-group">
                           
                                <a href="ag_map_run_export.php" class="duo-button-half">📃 AG Narrative</a>
                                
                                <a href="agnodeseq.php" class="duo-button-half">✈️ AG Travel Guide</a>
                               
                            </div>
                            
                            
                            
                           
                            
                            <!-- Duo Buttons -->
                            <div class="duo-button-group">
                           
                                <a href="kg_travel.php" class="duo-button-half">🛩️ KG Travel</a>
                                
                                <a href="kgnodeseq.php" class="duo-button-half">🌃 KG Travel Guide</a>
                               
                            </div>
                            
                            
                            
                            <!--
                            <a href="kg_travel.php" class="link-card"><div class="link-card-title">🛩️ KG Travel</div></a>
                            -->
                            
                            
                
                           
                            <a href="kg_narrative.php" class="link-card"><div class="link-card-title">📜 KG Narrative</div></a>
                
                            
                            <a href="kg_edge_curator.php" class="link-card"><div class="link-card-title"><i class="fa-solid fa-code-fork"></i> KG Edge Audit</div></a>
            
                            
                            <a href="kg_filter.php" class="link-card"><div class="link-card-title"><i class="fa-solid fa-filter"></i> KG Graph Filter</div></a>
                            
                            
                            
                            
                            
                            
                            
                            
                            
                            
                            
                            <a href="view_lore_explorer.php" class="link-card"><div class="link-card-title">☪️ Auto Bible Match</div></a>
                            <a href="sketch_match.php" class="link-card"><div class="link-card-title">🃏 Sketch Match Stories</div></a>
                            <a href="sketches_viewer.php" class="link-card"><div class="link-card-title">🏞️ Sketch Match Sketches</div></a>
                            
                            
                            
                            <a href="rapid_lore_ag_category_writer.php" class="link-card"><div class="link-card-title">🐈 AG Cats</div></a>
                            
                            
                            
                            
                        </div>
                    </div>

                    <!-- Category: Scripts & Worldbuilding -->
                    <div class="cat-content" id="cat-scripts" style="display:none;">
                        <div class="link-grid">
                        
                        
                        
                        
                            <a href="view_md_categories.php" class="link-card"><div class="link-card-title">📖 Scripts Lib</div></a>
                            

                        
                            <a href="wroom.php" class="link-card"><div class="link-card-title">🏎️ Wroom</div></a>
                            
                            
                                                    
                            <a href="plush.php" class="link-card"><div class="link-card-title">🧸 Plush</div></a>
                            
                            
                        
                            <a href="timelines.php" class="link-card"><div class="link-card-title">🛣️ Timelines</div></a>
                            
                           
                       
                        
                            <a href="plunar.php" class="link-card"><div class="link-card-title">🌜 PluNar</div></a>
                            
                        
                        
                        
                        
                            
                            
                            
                            <a href="style_profiles_forge.php" class="link-card"><div class="link-card-title">💐 Style Profiles</div></a>
                            <a href="sketch_templates_forge.php" class="link-card"><div class="link-card-title">👨‍👩‍👧‍👦 Shot Templates</div></a>
                            <a href="dictionaries_admin.php" class="link-card"><div class="link-card-title">📚 Dictionaries</div></a>
                            <a href="bloom_oracle_admin.php" class="link-card"><div class="link-card-title">🔮 Bloom Oracle</div></a>
                            <a href="view_wordnet_admin.php" class="link-card"><div class="link-card-title">🏛️ WordNet</div></a>
                        </div>
                    </div>

                   <!-- Category: Operations -->
                    <div class="cat-content" id="cat-operations" style="display:none;">
                        <div class="link-grid">
                        
                            
                            <!-- Multi Button (No CRUD link, just Run) -->
                            <!--
                            <div class="multi-button-group">
                                <a href="enhanimaticism.php" class="multi-button-main">✨ Enhanimatics</a>
                                <button class="multi-button-action run-btn runBtn" data-id="48">🌀</button>
                            </div>
                            -->
                            
                            
                            
                            
                            
                            <a href="locahub.php" class="link-card"><div class="link-card-title">🏝️ LocaHub</div></a>

                                                    

                           
                                                    
                            <a href="view_stats.php" class="link-card"><div class="link-card-title">📈 Stats</div></a>
                            
                            
                            
                            <div class="trio-button-group">
                            
                                <a href="enhanimaticism.php" class="trio-button-third">✨ Nhx</a>
                                                            
                                <a href="enhanistobo.php" class="trio-button-third">🎬 StoBo</a>
                                   
                                <a href="exposanimaticism.php" class="trio-button-third">🧘 ExPos</a>

                                <button class="multi-button-action trio-button-third run-btn runBtn" data-id="48">🌀</button>
                           
                            </div>

                           
                            
                            <!-- Trio Buttons (Batches) -->
                            <div class="trio-button-group">
                                <a href="import_character_poses.php" class="trio-button-third">🤺 Pos</a>
                                <a href="import_character_expressions.php" class="trio-button-third">🧖 Exp</a>
                                <a href="import_character_anima_poses.php" class="trio-button-third">🧞 Ani</a>
                            </div>
                            

                            
                            <!-- Trio Buttons (Reviews/Previews) -->
                            <!--
                            <div class="trio-button-group">
                            
                            
                                <a href="view_fuzz_preview.php" class="trio-button-third">🎬 Fuzz</a>
                                
                
                                <a href="view_narrative_preview.php" class="trio-button-third">🎬 Nar</a>
                                <a href="view_storyboard_vidbat.php" class="trio-button-third">🎬 Stb</a>
                                
                            </div>
                            -->
                            
                            
                            <!-- Duo Buttons -->
                            <div class="duo-button-group">
                                <a href="regenerator.php" class="duo-button-half">♻️ Sketch Regen</a>
                                <a href="videos.php" class="duo-button-half">♾️ Video Regen</a>
                            </div>
                            
                            
                            
                            <div class="duo-button-group">
                                <a href="sketchmig.php" class="duo-button-half">🏖️ 2Sketch Migration</a>
                                <a href="view_sketch_migration.php" class="duo-button-half">🏝️ Sketch Migration</a>
                            </div>
                            
                            
                            
                            
                            
                            
                            
                            
                            
                            
                            
                            <a href="taggeranger.php" class="link-card"><div class="link-card-title">🪃 Taggerang</div></a>
                            
                            <!-- Duo Buttons -->
                            <div class="duo-button-group">
                                <a href="sketchtagforwarder.php" class="duo-button-half">📨 Tag Fwd</a>
                                <a href="animatictagforwarder.php" class="duo-button-half">✈️ Ani Tag Fwd</a>
                            </div>
                            
                            <div class="duo-button-group">
                                <a href="frametagger.php" class="duo-button-half">🏷️ Frame Tagger</a>
                                <a href="videotagger.php" class="duo-button-half">🔖 Video Tagger</a>
                            </div>
                            
                            
                            
                                                    
                            <a href="view_gs_assign_forge.php" class="link-card"><div class="link-card-title">🛼 GS Assign</div></a>
                            

                            <a href="videosmatcher.php" class="link-card"><div class="link-card-title">📼 Videosmatcher</div></a>
                            
                            

                        </div>
                    </div>
                    
                    
                    
<!-- Category: Creatives -->
<div class="cat-content" id="cat-creatives" style="display:none;">
    <div class="link-grid">
        
        <div class="multi-button-group">
            <a href="view_map_runs_sketches.php" class="multi-button-main">🪄 Sketches</a>
            <a href="sql_crud_sketches.php" class="multi-button-action">CRUD</a>
            <a href="enhanimaticism.php?entity_type=sketches&map_run_dis=1" class="multi-button-action">NHX</a>
            <button class="multi-button-action run-btn runBtn" data-id="15">🌀</button>
        </div>
        
        <div class="multi-button-group">
            <a href="view_video_admin.php" class="multi-button-main">🎥 Animatics</a>
            <a href="animatics_crud.php" class="multi-button-action">CRUD</a>
            <a href="enhanimaticism.php?entity_type=animatics&map_run_dis=1" class="multi-button-action">NHX</a>
            <button class="multi-button-action run-btn runBtn" data-id="42">🌀</button>
        </div>
        
        <div class="multi-button-group">
            <a href="view_map_runs_generatives.php" class="multi-button-main">⚡ Generatives</a>
            <a href="sql_crud_generatives.php" class="multi-button-action">CRUD</a>
            <a href="enhanimaticism.php?entity_type=generatives&map_run_dis=1" class="multi-button-action">NHX</a>
            <button class="multi-button-action run-btn runBtn" data-id="10">🌀</button>
        </div>
        
        <div class="multi-button-group">
            <a href="gallery_composites_nu.php" class="multi-button-main">🧩 Composites</a>
            <a href="sql_crud_composites.php" class="multi-button-action">CRUD</a>
            <a href="enhanimaticism.php?entity_type=composites&map_run_dis=1" class="multi-button-action">NHX</a>
            <button class="multi-button-action run-btn runBtn" data-id="24">🌀</button>
        </div>
        
        <div class="multi-button-group">
            <a href="upload_spawns.php" class="multi-button-main">🌱 Spawns</a>
            <a href="#" class="multi-button-action" style="opacity: 0.3; cursor: not-allowed; pointer-events: none;">CRUD</a>
            <a href="#" class="multi-button-action" style="opacity: 0.3; cursor: not-allowed; pointer-events: none;">NHX</a>
            <a href="upload_spawns.php#uploader" class="multi-button-action run-btn">📤</a>
        </div>
        
        <div class="multi-button-group">
            <a href="gallery_prompt_matrix_blueprints_nu.php" class="multi-button-main">🌌 Blueprints</a>
            <a href="sql_crud_prompt_matrix_blueprints.php" class="multi-button-action">CRUD</a>
            <a href="enhanimaticism.php?entity_type=prompt_matrix_blueprints&map_run_dis=1" class="multi-button-action">NHX</a>
            <button class="multi-button-action run-btn runBtn" data-id="23">🌀</button>
        </div>
        
        <div class="multi-button-group">
            <a href="gallery_controlnet_maps_nu.php" class="multi-button-main">☠️ Controlnet</a>
            <a href="sql_crud_controlnet_maps.php" class="multi-button-action">CRUD</a>
            <a href="enhanimaticism.php?entity_type=controlnet_maps&map_run_dis=1" class="multi-button-action">NHX</a>
            <button class="multi-button-action run-btn runBtn" data-id="20">🌀</button>
        </div>

    </div>
</div>



<!-- Category: Entities -->
<div class="cat-content" id="cat-entities" style="display:none;">
    <div class="link-grid">
        
        <div class="multi-button-group">
            <a href="view_map_runs_characters.php" class="multi-button-main">🦸 Characters</a>
            <a href="sql_crud_characters.php" class="multi-button-action">CRUD</a>
            <a href="enhanimaticism.php?entity_type=characters&map_run_dis=1" class="multi-button-action">NHX</a>
            <button class="multi-button-action run-btn runBtn" data-id="11">🌀</button>
        </div>
        
        <div class="multi-button-group">
            <a href="view_map_runs_factions.php" class="multi-button-main">🎌 Factions</a>
            <a href="sql_crud_factions.php" class="multi-button-action">CRUD</a>
            <a href="enhanimaticism.php?entity_type=factions&map_run_dis=1" class="multi-button-action">NHX</a>
            <button class="multi-button-action run-btn runBtn" data-id="11">🌀</button>
        </div>
        
        <div class="multi-button-group">
            <a href="view_map_runs_character_poses.php" class="multi-button-main">🤸 Poses</a>
            <a href="sql_crud_character_poses.php" class="multi-button-action">CRUD</a>
            <a href="enhanimaticism.php?entity_type=character_poses&map_run_dis=1" class="multi-button-action">NHX</a>
            <button class="multi-button-action run-btn runBtn" data-id="19">🌀</button>
        </div>
        
        <div class="multi-button-group">
            <a href="view_map_runs_character_anima_poses.php" class="multi-button-main">🤹 Anima Poses</a>
            <a href="sql_crud_character_anima_poses.php" class="multi-button-action">CRUD</a>
            <a href="enhanimaticism.php?entity_type=character_anima_poses&map_run_dis=1" class="multi-button-action">NHX</a>
            <button class="multi-button-action run-btn runBtn" data-id="52">🌀</button>
        </div>
        
        <div class="multi-button-group">
            <a href="view_map_runs_character_expressions.php" class="multi-button-main">🧑‍🎤 Expressions</a>
            <a href="sql_crud_character_expressions.php" class="multi-button-action">CRUD</a>
            <a href="enhanimaticism.php?entity_type=character_expressions&map_run_dis=1" class="multi-button-action">NHX</a>
            <button class="multi-button-action run-btn runBtn" data-id="46">🌀</button>
        </div>
        
        <div class="multi-button-group">
            <a href="view_map_runs_animas.php" class="multi-button-main">🐾 Animas</a>
            <a href="sql_crud_animas.php" class="multi-button-action">CRUD</a>
            <a href="enhanimaticism.php?entity_type=animas&map_run_dis=1" class="multi-button-action">NHX</a>
            <button class="multi-button-action run-btn runBtn" data-id="12">🌀</button>
        </div>
        
        <div class="multi-button-group">
            <a href="view_map_runs_locations.php" class="multi-button-main">🗺️ Locations</a>
            <a href="sql_crud_locations.php" class="multi-button-action">CRUD</a>
            <a href="enhanimaticism.php?entity_type=locations&map_run_dis=1" class="multi-button-action">NHX</a>
            <button class="multi-button-action run-btn runBtn" data-id="13">🌀</button>
        </div>
        
        <div class="multi-button-group">
            <a href="view_map_runs_backgrounds.php" class="multi-button-main">🏞️ Backgrounds</a>
            <a href="sql_crud_backgrounds.php" class="multi-button-action">CRUD</a>
            <a href="enhanimaticism.php?entity_type=backgrounds&map_run_dis=1" class="multi-button-action">NHX</a>
            <button class="multi-button-action run-btn runBtn" data-id="16">🌀</button>
        </div>
        
        <div class="multi-button-group">
            <a href="view_map_runs_artifacts.php" class="multi-button-main">🏺 Artifacts</a>
            <a href="sql_crud_artifacts.php" class="multi-button-action">CRUD</a>
            <a href="enhanimaticism.php?entity_type=artifacts&map_run_dis=1" class="multi-button-action">NHX</a>
            <button class="multi-button-action run-btn runBtn" data-id="18">🌀</button>
        </div>
        
        <div class="multi-button-group">
            <a href="view_map_runs_vehicles.php" class="multi-button-main">🛸 Vehicles</a>
            <a href="sql_crud_vehicles.php" class="multi-button-action">CRUD</a>
            <a href="enhanimaticism.php?entity_type=vehicles&map_run_dis=1" class="multi-button-action">NHX</a>
            <button class="multi-button-action run-btn runBtn" data-id="17">🌀</button>
        </div>

    </div>
</div>
                    
                    

                    <!-- Category: Audio Entities 
                    <div class="cat-content" id="cat-audio" style="display:none;">
                        <div class="link-grid">
                            
                            <div class="multi-button-group">
                                <a href="player_audio_dialogue_lines.php" class="multi-button-main">🗣️ Dialogue Lines</a>
                                <a href="audio_sql_crud_audio_dialogue_lines.php" class="multi-button-action">CRUD</a>
                                <button class="multi-button-action run-btn runBtn" data-id="35">🌀</button>
                            </div>
                            
                            <div class="multi-button-group">
                                <a href="player_audio_ambiences.php" class="multi-button-main">⛈️ Ambiences</a>
                                <a href="audio_sql_crud_audio_ambiences.php" class="multi-button-action">CRUD</a>
                                <button class="multi-button-action run-btn runBtn" data-id="0">🌀</button>
                            </div>
                            
                            <div class="multi-button-group">
                                <a href="player_audio_cues.php" class="multi-button-main">🎵 Cues</a>
                                <a href="audio_sql_crud_audio_cues.php" class="multi-button-action">CRUD</a>
                                <button class="multi-button-action run-btn runBtn" data-id="50">🌀</button>
                            </div>
                            
                            <div class="multi-button-group">
                                <a href="player_audio_foleys.php" class="multi-button-main">👣 Foleys</a>
                                <a href="audio_sql_crud_audio_foleys.php" class="multi-button-action">CRUD</a>
                                <button class="multi-button-action run-btn runBtn" data-id="0">🌀</button>
                            </div>
                            
                            <div class="multi-button-group">
                                <a href="player_audio_fxsounds.php" class="multi-button-main">💥 FX Sounds</a>
                                <a href="audio_sql_crud_audio_fxsounds.php" class="multi-button-action">CRUD</a>
                                <button class="multi-button-action run-btn runBtn" data-id="0">🌀</button>
                            </div>
                            
                            <div class="multi-button-group">
                                <a href="player_audio_themes.php" class="multi-button-main">🎼 Themes</a>
                                <a href="audio_sql_crud_audio_themes.php" class="multi-button-action">CRUD</a>
                                <button class="multi-button-action run-btn runBtn" data-id="0">🌀</button>
                            </div>
                            
                            
                            
                            

                        </div>
                    </div>
                    -->
                    
                    
                    
                    
                    
                                       <!-- Category: Audio Entities -->
                    <div class="cat-content" id="cat-audio" style="display:none;">
                        <div class="link-grid">
                        
                        
                            
                       
                        
                       
                                                    
                            <a href="daw/index.php" class="link-card"><div class="link-card-title">🎛 DAW</div></a>
                            
                            
                                               
                                                 <a href="enhanimaticism_bounces.php?entity=daw_projects" class="link-card">
    <div class="link-card-title">🎧 Project Mixdowns</div>
</a>

<a href="enhanimaticism_bounces.php?entity=editorial_shots" class="link-card">
    <div class="link-card-title">🎞️ Shot Mixdowns</div>
</a>
                            
                       
                        
                            
                            <div class="multi-button-group">
                                <a href="enhanimaticism_audio.php?entity=audio_dialogue_lines" class="multi-button-main">🗣️ Dialogue Lines</a>
                                <button class="multi-button-action run-btn runBtn" data-id="35">🌀</button>
                            </div>
                            
                            <div class="multi-button-group">
                                <a href="enhanimaticism_audio.php?entity=audio_ambiences" class="multi-button-main">⛈️ Ambiences</a>
                                <button class="multi-button-action run-btn runBtn" data-id="0">🌀</button>
                            </div>
                            
                            <div class="multi-button-group">
                                <a href="enhanimaticism_audio.php?entity=audio_cues" class="multi-button-main">🎵 Cues</a>
                                <button class="multi-button-action run-btn runBtn" data-id="50">🌀</button>
                            </div>
                            
                            <div class="multi-button-group">
                                <a href="enhanimaticism_audio.php?entity=audio_foleys" class="multi-button-main">👣 Foleys</a>
                                <button class="multi-button-action run-btn runBtn" data-id="0">🌀</button>
                            </div>
                            
                            <div class="multi-button-group">
                                <a href="enhanimaticism_audio.php?entity=audio_fxsounds" class="multi-button-main">💥 FX Sounds</a>
                                <button class="multi-button-action run-btn runBtn" data-id="0">🌀</button>
                            </div>
                            
                            <div class="multi-button-group">
                                <a href="enhanimaticism_audio.php?entity=audio_themes" class="multi-button-main">🎼 Themes</a>
                                <button class="multi-button-action run-btn runBtn" data-id="0">🌀</button>
                            </div>
                            
                            
                            
         
                            

                        </div>
                    </div>

                    
                    
                    
                    
                    
                    
                    
                    

                    <!-- Category: Motion Lab -->
                    <div class="cat-content" id="cat-motion" style="display:none;">
                        <div class="link-grid">
                        
                        
                        

                         
                            <a href="popkorn.php" class="link-card"><div class="link-card-title">🍿 Popkorn</div></a>


                        
                         
                            <a href="view_vidbat_review.php" class="link-card"><div class="link-card-title">🎟️ Videos</div></a>

                        
                        
                            <a href="view_video_admin.php" class="link-card"><div class="link-card-title">🎥 Video Admin</div></a>
                            
                            
                           
                            
                            
                                                        
                            <a href="vedtriccs/index.php" class="link-card"><div class="link-card-title">🐳 Video Editor</div></a>
                          
                            <a href="muvitriccs.php" class="link-card"><div class="link-card-title">🐬 muvitriccs</div></a>
                          
                            <a href="view_animatic_multiplane.php?animatic_id=11389" class="link-card"><div class="link-card-title">🌊 Parallax Lab</div></a>
                            <a href="view_motion_editor.php?animatic_id=530" class="link-card"><div class="link-card-title">🏄 Motion Lab</div></a>
                            <a href="view_motani_add.php" class="link-card"><div class="link-card-title">🛠️ motaniadd</div></a>
                            <a href="view_mass_glb_upload.php" class="link-card"><div class="link-card-title">🚚 glb upload</div></a>
                        </div>
                    </div>

                    <!-- Category: Frames -->
                    <div class="cat-content" id="cat-frames" style="display:none;">
                        <div class="link-grid">
                        
                        
                        
                       
                        
                            <a href="view_scrollmagic_rating.php" class="link-card"><div class="link-card-title">⭐ ScrollMagic</div></a>

                            
                            
                            <a href="filter_forge_grid.php" class="link-card">
                                <div class="link-card-title">🔦 Filter Forge</div>
                            </a>
                        
                            
                            
                        
                        
                            <!-- Single Link -->
                            <a href="entity_viewer.php?type=sketches&mode=entity&page=1" class="link-card">
                                <div class="link-card-title">⛲ Entities Viewer</div>
                            </a>
                                                    <a href="view_scrollmagic_depth.php" class="link-card"><div class="link-card-title">👻 ScrollMagic Depth</div></a>
                            
                            <!--
                            <a href="view_slideshow.php" class="link-card"><div class="link-card-title">🎞️ Slideshow</div></a>
                            <a href="wall_of_images.php" class="link-card"><div class="link-card-title">🎇 Wall of Frames</div></a>
                            -->
                            
                        </div>
                    </div>

                    <!-- Category: 3D Sets -->
                    <div class="cat-content" id="cat-3d" style="display:none;">
                        <div class="link-grid">
                            <a href="mannequin/editor/posture-editor.html" class="link-card"><div class="link-card-title">🧍 Mannequin</div></a>
                            <a href="babylon_view.php" class="link-card"><div class="link-card-title">🌆 3D Viewer</div></a>
                            <a href="sketchfab.php" class="link-card"><div class="link-card-title">🎭 3D Sketchfab</div></a>
                        </div>
                    </div>

                    <!-- Category: Imports -->
                    <div class="cat-content" id="cat-imports" style="display:none;">
                        <div class="link-grid">
                        
                            <a href="view_mass_upload.php" class="link-card"><div class="link-card-title">🏗️ Mass Image Upload</div></a>
                            <a href="view_mass_audio_upload.php" class="link-card"><div class="link-card-title">📻 Mass Audio Upload</div></a>
                            
                            
                            
                            <a href="view_mass_video_upload.php" class="link-card"><div class="link-card-title">📡 Mass Video Upload</div></a>
                            
                            
                            
                            
                                                        
                            <a href="import_frames_to_storyboard.php" class="link-card"><div class="link-card-title">🧚 Mass Import Frames2Storyboard</div></a>
                            
                            
                            <a href="view_stoboimp.php" class="link-card"><div class="link-card-title">🧞 Mass Import 2 Frames2Storyboard</div></a>
                            
                            
                            
                            
                            
                            
                            <a href="import_spawns.php" class="link-card"><div class="link-card-title">📥 Batch Spawns Import</div></a>
                            
                            
                            <a href="import_entity_from_entity.php" class="link-card"><div class="link-card-title">🦋 Batch Entity2Entity Import</div></a>
                            <a href="sketches2animatics.php" class="link-card"><div class="link-card-title">🏎️ Batch Sketches2Animatics Import</div></a>
                            
                            <!--
                            <a href="view_mass_upload_generic.php" class="link-card"><div class="link-card-title">🛄 GPT Import</div></a>
                            -->
                            
                            
                        </div>
                    </div>

                    <!-- Category: Tools -->
                    <div class="cat-content" id="cat-tools" style="display:none;">
                        <div class="link-grid">
                        
                        
                        
                       
                            <a href="todo.php" class="link-card"><div class="link-card-title">🎫 SAGE TODOs</div></a>
                            
                            
                       
                            <a href="recipe_forge.php" class="link-card"><div class="link-card-title">🥫 Code Recipes</div></a>
                            
                            
                            
                       
                       
                            <a href="sketchup.php" class="link-card"><div class="link-card-title">🍅 Sketchup</div></a>
                      
                            
                        
                            <a href="backup_forge.php" class="link-card"><div class="link-card-title">💾 Backup</div></a>
                      
                            
                            <a href="content_hub/index.php" class="link-card"><div class="link-card-title">📰 Content Hub</div></a>
                            
                             
                            <a href="cinemagic_hub/index.php" class="link-card"><div class="link-card-title">🎴 Magazine Hub</div></a>
                            
                            
                           
                            <a href="pytoon/index.php" class="link-card"><div class="link-card-title">💥 Pytoon</div></a>
                            
                            
                            
                            
                             
                            <a href="mail_hub/index.php" class="link-card"><div class="link-card-title">📬 Mail Hub</div></a>
                            
                            
                            
                            
                            
                            
                            
                            
                            
                            
                            
                            
                        <!--
                            <a href="posts/posts_admin.php" class="link-card"><div class="link-card-title">🗞️ Posts</div></a>
                            -->
                            
                            
                            
                            
                            <a href="view_json_categories.php" class="link-card"><div class="link-card-title">📟 JSON Lib</div></a>
                            
                            
                            
                            <a href="bootstrap-icons.html" class="link-card"><div class="link-card-title">🔣 Bootstrap Icons</div></a>
                            <a href="view_md.php?category_id=1" class="link-card"><div class="link-card-title">🎓 Documentation</div></a>
                            <a href="codeboard.php" class="link-card"><div class="link-card-title">⌨️ SAGE codeboard</div></a>
                            
                            <!--
                            <a href="view_gpt.php" class="link-card"><div class="link-card-title">🏴‍☠️ GPT conversations</div></a>
                            -->
                            
                            <a href="view_profile.php" class="link-card"><div class="link-card-title">👤 Profile</div></a>
                        </div>
                    </div>

                    <!-- Category: Database -->
                    <div class="cat-content" id="cat-database" style="display:none;">
                        <div class="link-grid">
                            <a href="/admin/" class="link-card"><div class="link-card-title">🛢️ phpMyAdmin</div></a>
                            <a href="/dbtool.php" class="link-card"><div class="link-card-title">🛢️ dbtool</div></a>
                            <a href="chromatool.php" class="link-card"><div class="link-card-title">💠 chromatool</div></a>
                            <a href="view_collections_admin.php" class="link-card"><div class="link-card-title">📐 Chroma Collections</div></a>
                            <a href="view_db_migration_admin.php" class="link-card"><div class="link-card-title">🧳 DB Migration</div></a>
                            <a href="view_sql_export.php" class="link-card"><div class="link-card-title">🎎 Entity SQL Export</div></a>
                        </div>
                    </div>

                    <!-- Category: API Tokens -->
                    <div class="cat-content" id="cat-tokens" style="display:none;">
                        <div style="max-width: 600px;">
                            <div style="padding:15px; margin-bottom:20px; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); font-size:0.85rem;">
                                💡 <strong>Tokens are saved to:</strong> <span style="font-family:var(--mono);"><?= htmlspecialchars(PROJECT_ROOT) ?>/token/</span><br>
                                ℹ️ Only changed fields will be saved. Empty fields won't overwrite existing tokens.
                            </div>
                            <form method="post">
                                <input type="hidden" name="action" value="save_tokens">
                                <div class="form-group">
                                    <label class="form-label">🌸 Pollinations AI Token</label>
                                    <input type="password" name="pollinations_token" class="form-input" value="<?= htmlspecialchars($existingTokens['pollinations_token']) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">🤖 Groq API Key</label>
                                    <input type="password" name="groq_api_key" class="form-input" value="<?= htmlspecialchars($existingTokens['groq_api_key']) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">⚡ Cerebras API Key</label>
                                    <input type="password" name="cerebras_api_key" class="form-input" value="<?= htmlspecialchars($existingTokens['cerebras_api_key'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">🎨 Freepik API Key</label>
                                    <input type="password" name="freepik_api_key" class="form-input" value="<?= htmlspecialchars($existingTokens['freepik_api_key']) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Google AI Studio API Key</label>
                                    <input type="password" name="google_api_key" class="form-input" value="<?= htmlspecialchars($existingTokens['google_api_key'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">🧠 Mistral API Key</label>
                                    <input type="password" name="mistral_api_key" class="form-input" value="<?= htmlspecialchars($existingTokens['mistral_api_key'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">🤖 Cohere API Key</label>
                                    <input type="password" name="cohere_api_key" class="form-input" value="<?= htmlspecialchars($existingTokens['cohere_api_key'] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn-forge-primary">💾 Save Tokens</button>
                            </form>
                        </div>
                    </div>

                    <!-- Category: Tabs -->
                    <div class="cat-content" id="cat-tabs" style="display:none;">
                        <div class="link-grid" style="margin-bottom: 20px;">
                            <a href="view_import_links.php" class="link-card"><div class="link-card-title">🖇️ Import Links</div></a>
                            <div class="link-card" id="deleteLinksBtn" data-parent="1001" style="cursor: pointer;"><div class="link-card-title">🗑️ Delete Links</div></div>
                            <div class="link-card" id="addParentBtn" style="cursor: pointer;"><div class="link-card-title">➕ Add New Parent</div></div>
                        </div>
                        <div style="background:var(--surface); padding:20px; border-radius:var(--radius); border:1px solid var(--border);">
                            <?php require "tabs_widget.php"; ?>
                        </div>
                    </div>
                    
                </div>
            </div>
        </main>
    </div>

    <!-- ── MODALS OVERLAYS ── -->
    <div class="forge-modal-overlay" id="appIframeModal">
        <div class="forge-modal" style="max-width:900px; height: 85vh;">
            <div class="forge-modal-header">
                <div class="forge-modal-title" id="appIframeTitle"></div>
                <button class="forge-modal-close" id="closeAppIframeModal"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="forge-modal-body" style="padding:0; overflow:hidden; display:flex;">
                <iframe id="appIframe" src="" frameborder="0" style="flex:1; width:100%; border:none; background:#000;"></iframe>
            </div>
        </div>
    </div>

    <!-- ── TOAST CONTAINER ── -->
    <div id="toast-container"></div>
    <script src="/js/toast.js"></script>

    <!-- ── JAVASCRIPT ── -->
    <script>
        const categories =[
            { id: 'cat-editorial', title: '🪽 Editorial', icon: 'bi-pen' },
            { id: 'cat-pre', title: '🦋 Pre', icon: 'bi-camera-reels' },
            { id: 'cat-rapid', title: '🌠 Rapid UI', icon: 'bi-rocket' },
            { id: 'cat-showrunner', title: '🌏 Showrunner', icon: 'bi-globe' },
            { id: 'cat-scripts', title: '🪐 Scripts & Worldbuilding', icon: 'bi-book' },
            { id: 'cat-operations', title: '🧲 Operations', icon: 'bi-magnet' },
            { id: 'cat-creatives', title: '💡 Creatives', icon: 'bi-lightbulb' },
            { id: 'cat-entities', title: '🧬 Entities', icon: 'bi-virus2' },
            { id: 'cat-audio', title: '🎧 Audio Entities', icon: 'bi-volume-up' },
            { id: 'cat-motion', title: '🎡 Motion Lab', icon: 'bi-film' },
            { id: 'cat-frames', title: '🎞️ Frames', icon: 'bi-images' },
            { id: 'cat-3d', title: '📹 3D sets', icon: 'bi-box' },
            { id: 'cat-imports', title: '📥 Imports', icon: 'bi-download' },
            { id: 'cat-tools', title: '🎛️ Tools', icon: 'bi-tools' },
            { id: 'cat-database', title: '🛢️ Database', icon: 'bi-database' },
            { id: 'cat-tokens', title: '🔑 API Tokens', icon: 'bi-key' },
            //{ id: 'cat-tabs', title: '📑 Tabs', icon: 'bi-folder' }
        ];

        function renderSidebar() {
            const list = document.getElementById('sidebarList');
            
            list.innerHTML = categories.map(c => `
                <div class="task-card" data-cat="${c.id}" onclick="selectCategory('${c.id}')" title="${c.title}">
                    <div class="task-card-indicator"></div>
                    <div class="task-card-body">
                        <div class="task-card-title"><i class="bi ${c.icon}" style="opacity:0.8;"></i></div>
                    </div>
                </div>
            `).join('');
            
            // Reapply active state
            let current = localStorage.getItem('dashboard_last_cat') || 'cat-editorial';
            if (current === 'cat-quick') current = 'cat-editorial'; // Safety fallback
            document.querySelectorAll('.task-card').forEach(el => {
                if (el.dataset.cat === current) el.classList.add('active');
            });
        }

        function selectCategory(id) {
            document.querySelectorAll('.task-card').forEach(el => {
                if (el.dataset.cat === id) el.classList.add('active');
                else el.classList.remove('active');
            });
            
            document.querySelectorAll('.cat-content').forEach(el => {
                if (el.id === id) el.style.display = 'block';
                else el.style.display = 'none';
            });
            
            localStorage.setItem('dashboard_last_cat', id);
        }

        document.addEventListener('DOMContentLoaded', () => {
            renderSidebar();
            let lastCat = localStorage.getItem('dashboard_last_cat') || 'cat-editorial';
            if (lastCat === 'cat-quick') lastCat = 'cat-editorial';
            selectCategory(lastCat);
        });

        // Modals
        // Universal App Modal Logic
        $(document).ready(function() {
            $('.app-modal-btn').click(function() {
                const src = $(this).data('src');
                const title = $(this).data('title');
                const icon = $(this).data('icon');
                
                // Inject the dynamic icon and title
                $('#appIframeTitle').html(`<i class="bi ${icon}" style="margin-right:6px;"></i> ${title}`);
                
                // Load iframe and show modal
                $('#appIframe').attr('src', src);
                $('#appIframeModal').addClass('open');
            });
            
            $('#closeAppIframeModal').click(function() {
                $('#appIframeModal').removeClass('open');
                // Optional: Clear the src to kill background processes like Chat/Notebooks when the modal closes
                setTimeout(() => $('#appIframe').attr('src', ''), 200);
            });
        });

        // Run Buttons
        $(document).on('click', '.runBtn', function(e) {
            e.preventDefault();
            let id = $(this).data('id');
            $.post('scheduler_view.php', {
                action: 'run_now',
                id: id
            }, function(res) {
                if (res === 'success') {
                    Toast.show('Task scheduled to run now!', 'success');
                } else {
                    Toast.show('Failed to trigger task', 'error');
                }
            });
        });

        // Tabs Logic
        $(document).on('click', '#deleteLinksBtn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const parentId = $btn.data('parent');
            if (!confirm('Are you sure you want to delete all links with parent_id=' + parentId + '?')) return;
            $btn.css('opacity', 0.6).find('.link-card-title').text('Deleting...');
            $.post('delete_pages.php', {
                parent_id: parentId
            }, function(res) {
                if (res.ok) {
                    Toast.show('Deleted ' + res.deleted + ' links successfully!', 'success');
                    setTimeout(function() { location.reload(); }, 2500);
                } else {
                    Toast.show('Failed to delete links: ' + (res.error || 'unknown'), 'error');
                    $btn.css('opacity', 1).find('.link-card-title').text('🗑️ Delete Links');
                }
            }, 'json').fail(function() {
                Toast.show('Server error during delete', 'error');
                $btn.css('opacity', 1).find('.link-card-title').text('🗑️ Delete Links');
            });
        });

        $(document).on('click', '#addParentBtn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const timestamp = Date.now();
            $btn.css('opacity', 0.6).find('.link-card-title').text('Adding...');
            $.post('add_parent_page.php', {
                name: 'new' + timestamp
            }, function(res) {
                if (res.ok) {
                    Toast.show('Added new parent successfully!', 'success');
                    setTimeout(() => location.reload(), 2500);
                } else {
                    Toast.show('Failed to add new parent: ' + (res.error || 'unknown'), 'error');
                    $btn.css('opacity', 1).find('.link-card-title').text('➕ Add New Parent');
                }
            }, 'json').fail(function() {
                Toast.show('Server error during add', 'error');
                $btn.css('opacity', 1).find('.link-card-title').text('➕ Add New Parent');
            });
        });
    </script>
    <script src="/js/theme-manager.js"></script>
    <?php // echo $eruda; ?>
</body>
</html>

<?php
/* 🧨🐙🍄🐦‍🔥 */
