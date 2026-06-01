<?php
// public/dashdocs.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
$dbname = $spw->getDbName();
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>SAGE Documentation Hub</title>
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
       FORGE — Design System Base (Identical to Dashboard)
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
        --red:          #f05060;
        --blue:         #4da6ff;
        --mono:         'Space Mono', 'Fira Mono', monospace;
        --sans:         'Syne', system-ui, sans-serif;
        --radius:       6px;
        --radius-lg:    10px;
    }

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
        }
    }

    :root[data-theme="light"], html[data-theme="light"], body[data-theme="light"] {
        --bg: #f6f8fa; --surface: #e1e4e8; --card: #ffffff; --card-hover: #f3f4f6; --border: #d1d5db; --border-glow: #9ca3af;
        --text: #111827; --text-dim: #4b5563; --text-bright: #000000;
        --amber: #d97706; --amber-dim: rgba(217,119,6,0.1); 
    }

    :root[data-theme="dark"], html[data-theme="dark"], body[data-theme="dark"] {
        --bg: #080b10; --surface: #0e1319; --card: #111820; --card-hover: #141e28; --border: #1c2535; --border-glow: #2a3a52;
        --text: #c8d4e8; --text-dim: #5a6a80; --text-bright: #e8f0ff;
        --amber: #f5a623; --amber-dim: rgba(245,166,35,0.08); 
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { 
        height: 100vh; display: flex; flex-direction: column; 
        background: var(--bg); color: var(--text); font-family: var(--sans); 
        font-size: 14px; line-height: 1.5; overflow: hidden; 
    }

    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--border-glow); border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--text-dim); }

    .dashboard-header-image { flex-shrink: 0; position: relative; text-align: center; background: #000; }
    .dashboard-header-image img { width: 100%; max-height: 400px; object-fit: cover; display: block; opacity: 0.85; filter: sepia(0.3) hue-rotate(-15deg); }

    .forge-layout { 
        flex: 1; display: grid; 
        grid-template-rows: 52px 1fr; grid-template-columns: 280px 1fr; 
        grid-template-areas: "header header" "sidebar main"; 
        overflow: hidden; min-height: 0; 
    }

    .forge-header { grid-area: header; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; background: var(--surface); border-bottom: 1px solid var(--border); border-top: 1px solid var(--border); position: relative; z-index: 100; }
    .forge-logo { display: flex; align-items: center; gap: 10px; font-family: var(--mono); font-size: 0.95rem; font-weight: 700; color: var(--amber); letter-spacing: 2px; text-transform: uppercase; }
    .forge-logo-icon { width: 28px; height: 28px; background: var(--amber); color: var(--bg); border: 1px solid var(--amber-glow); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-size: 14px; box-shadow: 0 0 10px var(--amber-glow); }
    .forge-header-right { display: flex; align-items: center; gap: 10px; }

    .btn-icon-sm { width: 36px; height: 36px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--card); color: var(--text-dim); cursor: pointer; transition: all 0.15s; display: flex; align-items: center; justify-content: center; font-size: 15px; }
    .btn-icon-sm:hover { border-color: var(--amber); color: var(--amber); background: var(--amber-dim); }

    .forge-sidebar { grid-area: sidebar; background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; }
    .sidebar-search { padding: 12px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
    .sidebar-list { flex: 1; overflow-y: auto; padding: 3px; display: grid; grid-template-columns: repeat(auto-fill, minmax(36px, 1fr)); gap: 10px; align-content: start; }

    .task-card { padding: 0; border-radius: var(--radius); border: 1px solid transparent; cursor: pointer; transition: all 0.15s; position: relative; background: transparent; display: flex; align-items: center; justify-content: center; aspect-ratio: 1; }
    .task-card:hover { background: var(--card); border-color: var(--border); transform: translateY(-2px); }
    .task-card.active { background: var(--amber-dim); border-color: var(--amber); }
    .task-card.active i { color: var(--amber); opacity: 1 !important; }
    .task-card-body { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; }
    .task-card-title { font-size: 1.3rem; display: flex; align-items: center; justify-content: center; margin: 0; padding: 0; color: var(--text-bright); transition: color 0.15s; }
    .task-card-title i { margin-right: 0 !important; transition: opacity 0.15s, color 0.15s; }
    .task-card-indicator { position: absolute; left: -1px; top: 50%; transform: translateY(-50%); width: 3px; height: 0; background: var(--amber); border-radius: 0 2px 2px 0; transition: height 0.2s; }
    .task-card.active .task-card-indicator { height: 60%; }
    
    .forge-main { grid-area: main; display: flex; flex-direction: column; overflow: hidden; background: var(--bg); }
    .forge-workspace { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    .workspace-body { flex: 1; overflow-y: auto; padding: 20px; padding-bottom:60px; }

    .link-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 6px; }
    .link-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 10px 12px; display: flex; flex-direction: column; gap: 8px; transition: all 0.15s; text-decoration: none; color: var(--text); margin-bottom: 1px; cursor: pointer; }
    .link-card:hover { background: var(--card-hover); border-color: var(--amber); color: var(--text); }
    .link-card-title { font-family: var(--sans); font-weight: 600; font-size: 0.85rem; color: var(--text-bright); display: flex; align-items: center; gap: 8px; }
    
    .duo-button-group { display: flex; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; transition: all 0.15s; margin-bottom: 1px; cursor: pointer; }
    .duo-button-group:hover { border-color: var(--amber); transform: translateY(-2px); }
    .duo-button-half { flex: 1; padding: 10px; text-align: center; text-decoration: none; color: var(--text-bright); font-family: var(--sans); font-weight: 600; font-size: 0.85rem; transition: background 0.15s, color 0.15s; display: flex; align-items: left; justify-content: left; gap: 6px; cursor: pointer; }
    .duo-button-half:first-child { border-right: 1px solid var(--border); }
    .duo-button-half:hover { background: var(--card-hover); color: var(--amber); }
    
    .multi-button-group { display: flex; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; transition: all 0.15s; margin-bottom: 1px; cursor: pointer; }
    .multi-button-group:hover { border-color: var(--amber); transform: translateY(-2px); }
    .multi-button-main { flex: 1; padding: 10px; text-align: left; text-decoration: none; color: var(--text-bright); font-family: var(--sans); font-weight: 600; font-size: 0.85rem; transition: background 0.15s, color 0.15s; display: flex; align-items: center; gap: 8px; cursor: pointer; }
    .multi-button-action { flex: 0 0 65px; padding: 0; text-align: center; text-decoration: none; color: var(--text-dim); font-family: var(--mono); font-size: 0.75rem; font-weight: 700; border: none; border-left: 1px solid var(--border); background: transparent; transition: background 0.15s, color 0.15s; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    .multi-button-action.run-btn { flex: 0 0 45px; font-size: 0.95rem; }
    .multi-button-main:hover, .multi-button-action:hover { background: var(--card-hover); color: var(--amber); }
    
    .trio-button-group { display: flex; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; transition: all 0.15s; margin-bottom: 3px; cursor: pointer; }
    .trio-button-group:hover { border-color: var(--amber); transform: translateY(-2px); }
    .trio-button-third { flex: 1; padding: 12px 6px; text-align: center; text-decoration: none; color: var(--text-bright); font-family: var(--sans); font-weight: 600; font-size: 0.85rem; transition: background 0.15s, color 0.15s; display: flex; align-items: left; justify-content: left; text-align: left; gap: 4px; border-right: 1px solid var(--border); cursor: pointer; }
    .trio-button-third:last-child { border-right: none; }
    .trio-button-third:hover { background: var(--card-hover); color: var(--amber); }
    
    @media (max-width: 900px) {
        .forge-layout { grid-template-columns: 1fr; grid-template-rows: 52px 170px 1fr; grid-template-areas: "header" "sidebar" "main"; }
        .forge-sidebar { border-right: none; border-bottom: 1px solid var(--border); }
    }
    </style>
    
    <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
</head>
<body>

    <!-- ── THEATRICAL HEADER ── -->
    <div class="dashboard-header-image">
        <img src="theatrical.jpg" alt="Starlight Guardians Theatrical Poster">
        <div style="position:absolute; top:12px; right:16px; display:flex; gap:8px; align-items:center;">
            <a href="dashboard.php" title="Exit Documentation Mode" style="background: none; border: none; font-size:22px; cursor:pointer; text-decoration: none; display: flex; align-items: center; justify-content: center; height: 36px; width: 36px; margin-left: 4px;">⭐</a>
            <button id="themeToggle" aria-pressed="false" title="Toggle theme" style="background: none; border: none; font-size:24px; cursor:pointer;">🌙</button>
        </div>
    </div>

    <div class="forge-layout">
        <!-- ── FORGE HEADER ── -->
        <header class="forge-header">
            <div class="forge-logo" title="SAGE Docs Hub">
                <div class="forge-logo-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
                <span></span>
            </div>
            <div class="forge-header-right">
                <button class="btn-icon-sm" onclick="openDoc(496, 'Scheduler Forge')" title="Scheduler Forge Docs">
                    <i class="bi bi-stopwatch"></i>
                </button>
                <button class="btn-icon-sm" onclick="openDoc(536, 'Queue Viewer')" title="Queue Viewer Docs">
                    <i class="bi bi-list-task"></i>
                </button>
                <button class="btn-icon-sm" onclick="openDoc(548, 'Stats')" title="Stats Docs">
                    <i class="bi bi-graph-up"></i>
                </button>
                <button class="btn-icon-sm" onclick="openDoc(542, 'Kaggle Notebooks')" title="Notebooks Docs">
                    <i class="bi bi-laptop"></i>
                </button>
                <button class="btn-icon-sm" onclick="openDoc(500, 'Generators Forge')" title="Generators Forge Docs">
                    <i class="bi bi-robot"></i>
                </button>
                <button class="btn-icon-sm" onclick="openDoc(547, 'Notebook Logs')" title="Notebook Logs Docs">
                    <i class="bi bi-journal-code"></i>
                </button>
                <button class="btn-icon-sm" onclick="openDoc(497, 'Control Deck')" title="Control Deck Docs">
                    <i class="bi bi-hurricane"></i>
                </button>
            </div>
        </header>

        <!-- ── SIDEBAR ── -->
        <aside class="forge-sidebar">
            <div class="sidebar-search">
                <input type="text" placeholder="Documentation Browse Mode..." disabled style="width:100%; padding:10px; background:var(--card); border:1px solid var(--border); color:var(--text-dim); border-radius:6px; box-sizing:border-box;">
            </div>
            <div class="sidebar-list" id="sidebarList">
                <!-- Javascript will render categories here -->
            </div>
        </aside>

        <!-- ── MAIN WORKSPACE ── -->
        <main class="forge-main">
            <div class="forge-workspace">
                <div class="workspace-body" id="workspaceBody">
                    
                    <!-- Category: Editorial -->
                    <div class="cat-content" id="cat-editorial" style="display:none;">
                        <div class="link-grid">
                            <a onclick="openDoc(491, 'Editorial')" class="link-card"><div class="link-card-title">🪯 Editorial</div></a>
                            <a onclick="openDoc(526, 'Boards')" class="link-card"><div class="link-card-title">🛹 Boards</div></a>
                            <a onclick="openDoc(516, 'Storyboards')" class="link-card"><div class="link-card-title">📽️ Storyboards</div></a>
                            <a onclick="openDoc(516, 'Storyboard Cut')" class="link-card"><div class="link-card-title">🔪 Storyboard Cut</div></a>
                            <a onclick="openDoc(537, 'SB Chains')" class="link-card"><div class="link-card-title">☍ SB Chains</div></a>
                            <a onclick="openDoc(549, 'Hot Storyboards')" class="link-card"><div class="link-card-title">🧨 Hot Storyboards</div></a>
                            <a onclick="openDoc(9999, 'Storyboards Datamining')" class="link-card"><div class="link-card-title">📊 Storyboards Datamining</div></a>
                        </div>
                    </div>

                    <!-- Category: Pre -->
                    <div class="cat-content" id="cat-pre" style="display:none;">
                        <div class="link-grid">
                            <div class="duo-button-group">
                                <a onclick="openDoc(482, 'Narratives')" class="duo-button-half">🪡 Narratives</a>
                                <a onclick="openDoc(482, 'Auto Sequencer')" class="duo-button-half">🧵 Auto Sequencer</a>
                            </div>
                            <a onclick="openDoc(489, 'Sequence Cut')" class="link-card"><div class="link-card-title">✂️ Sequence Cut</div></a>
                            <a onclick="openDoc(489, 'CineMagic Editor')" class="link-card"><div class="link-card-title">🃏 CineMagic Editor</div></a>
                            <a onclick="openDoc(481, 'Scene Kitchen')" class="link-card"><div class="link-card-title">🍳 Scene Kitchen</div></a>
                            <a onclick="openDoc(533, 'Continuity')" class="link-card"><div class="link-card-title">🎩 Continuity</div></a>
                            <a onclick="openDoc(546, 'Scene Curation')" class="link-card"><div class="link-card-title">🕵️ Scene Curation</div></a>
                            <a onclick="openDoc(478, 'Sequence Curation')" class="link-card"><div class="link-card-title">⚖️ Sequence Curation</div></a>
                            
                            <div class="duo-button-group">
                                <a onclick="openDoc(9999, 'Seq Matrix')" class="duo-button-half">🌃 Seq Matrix</a>
                                <a onclick="openDoc(9999, 'AnimeJSeq')" class="duo-button-half">🌆 AnimeJSeq</a>
                            </div>
                            <div class="duo-button-group">
                                <a onclick="openDoc(9999, 'Seq Viewer')" class="duo-button-half">🌇 Seq Viewer</a>
                                <a onclick="openDoc(9999, 'Recorder')" class="duo-button-half">🔴 Recorder</a>
                            </div>
                        </div>
                    </div>

                    <!-- Category: Rapid UI -->
                    <div class="cat-content" id="cat-rapid" style="display:none;">
                        <div class="link-grid">
                            <a onclick="openDoc(498, 'CLI Forge')" class="link-card"><div class="link-card-title">☣️ CLI Forge</div></a>
                            <a onclick="openDoc(517, 'Rapid Showcase')" class="link-card"><div class="link-card-title">🚀 Rapid Showcase</div></a>
                            <a onclick="openDoc(532, 'Rapid API KG Processor')" class="link-card"><div class="link-card-title">🐇 Rapid API KG Processor</div></a>
                            <a onclick="openDoc(510, 'Rapid API Auto Lore Processor')" class="link-card"><div class="link-card-title">🦄 Rapid API Auto Lore Processor</div></a>
                            <a onclick="openDoc(532, 'Rapid Scene Beats')" class="link-card"><div class="link-card-title">🦇 Rapid Scene Beats</div></a>
                        </div>
                    </div>

                    <!-- Category: Showrunner -->
                    <div class="cat-content" id="cat-showrunner" style="display:none;">
                        <div class="link-grid">
                            <div class="duo-button-group">
                                <a onclick="openDoc(501, 'Fuzz Forge')" class="duo-button-half">💢 Fuzz</a>
                                <a onclick="openDoc(501, 'Fuzz Graph')" class="duo-button-half">💢 Fuzz Graph</a>
                            </div>
                            <div class="duo-button-group">
                                <a onclick="openDoc(9999, 'Bible Docs')" class="duo-button-half">☀️ Bible</a>
                                <a onclick="openDoc(495, 'Auto Bible')" class="duo-button-half">🌙 Auto Bible</a>
                            </div>
                            <div class="duo-button-group">
                                <a onclick="openDoc(485, 'KG View')" class="duo-button-half">🌳 KG</a>
                                <a onclick="openDoc(485, 'KG Graph')" class="duo-button-half">⚛️ KG Graph</a>
                            </div>
                            <div class="duo-button-group">
                                <a onclick="openDoc(485, 'KG Staging')" class="duo-button-half">🌴 KG Staging</a>
                                <a onclick="openDoc(485, 'Staging Graph')" class="duo-button-half">⚛ Staging Graph</a>
                            </div>
                            <div class="duo-button-group">
                                <a onclick="openDoc(485, 'AG View')" class="duo-button-half">🌲 AG</a>
                                <a onclick="openDoc(485, 'AG Graph')" class="duo-button-half">🌐 AG Graph</a>
                            </div>

                            <a onclick="openDoc(485, 'KG Graph Filter')" class="link-card"><div class="link-card-title"><i class="fa-solid fa-filter"></i> KG Graph Filter</div></a>
                            <a onclick="openDoc(495, 'Auto Bible Match')" class="link-card"><div class="link-card-title">☪️ Auto Bible Match</div></a>
                            <a onclick="openDoc(543, 'Sketch Match Stories')" class="link-card"><div class="link-card-title">🃏 Sketch Match Stories</div></a>
                            <a onclick="openDoc(543, 'Sketch Match Sketches')" class="link-card"><div class="link-card-title">🏞️ Sketch Match Sketches</div></a>
                            <a onclick="openDoc(9999, 'AG Cats')" class="link-card"><div class="link-card-title">🐈 AG Cats</div></a>
                        </div>
                    </div>

                    <!-- Category: Scripts & Worldbuilding -->
                    <div class="cat-content" id="cat-scripts" style="display:none;">
                        <div class="link-grid">
                            <a onclick="openDoc(509, 'Scripts Lib')" class="link-card"><div class="link-card-title">📖 Scripts Lib</div></a>
                            <a onclick="openDoc(477, 'Wroom')" class="link-card"><div class="link-card-title">🏎️ Wroom</div></a>
                            <a onclick="openDoc(488, 'Plush')" class="link-card"><div class="link-card-title">🧸 Plush</div></a>
                            <a onclick="openDoc(490, 'Timelines')" class="link-card"><div class="link-card-title">🛣️ Timelines</div></a>
                            <a onclick="openDoc(488, 'PluNar')" class="link-card"><div class="link-card-title">🌜 PluNar</div></a>
                            <a onclick="openDoc(9999, 'Style Profiles')" class="link-card"><div class="link-card-title">💐 Style Profiles</div></a>
                            <a onclick="openDoc(9999, 'Shot Templates')" class="link-card"><div class="link-card-title">👨‍👩‍👧‍👦 Shot Templates</div></a>
                            <a onclick="openDoc(528, 'Dictionaries')" class="link-card"><div class="link-card-title">📚 Dictionaries</div></a>
                            <a onclick="openDoc(525, 'Bloom Oracle')" class="link-card"><div class="link-card-title">🔮 Bloom Oracle</div></a>
                            <a onclick="openDoc(550, 'WordNet Admin')" class="link-card"><div class="link-card-title">🏛️ WordNet</div></a>
                        </div>
                    </div>

                   <!-- Category: Operations -->
                    <div class="cat-content" id="cat-operations" style="display:none;">
                        <div class="link-grid">
                            <a onclick="openDoc(9999, 'LocaHub')" class="link-card"><div class="link-card-title">🏝️ LocaHub</div></a>
                            <a onclick="openDoc(548, 'Stats')" class="link-card"><div class="link-card-title">📈 Stats</div></a>
                            
                            <div class="trio-button-group">
                                <a onclick="openDoc(486, 'Nhx')" class="trio-button-third">✨ Nhx</a>
                                <a onclick="openDoc(9999, 'StoBo')" class="trio-button-third">🎬 StoBo</a>
                                <a onclick="openDoc(9999, 'ExPos')" class="trio-button-third">🧘 ExPos</a>
                            </div>

                            <div class="trio-button-group">
                                <a onclick="openDoc(515, 'Poses Import')" class="trio-button-third">🤺 Pos</a>
                                <a onclick="openDoc(515, 'Expressions Import')" class="trio-button-third">🧖 Exp</a>
                                <a onclick="openDoc(515, 'Anima Poses Import')" class="trio-button-third">🧞 Ani</a>
                            </div>
                            
                            <div class="duo-button-group">
                                <a onclick="openDoc(9999, 'Sketch Regen')" class="duo-button-half">♻️ Sketch Regen</a>
                                <a onclick="openDoc(9999, 'Video Regen')" class="duo-button-half">♾️ Video Regen</a>
                            </div>
                            
                            <div class="duo-button-group">
                                <a onclick="openDoc(555, '2Sketch Migration')" class="duo-button-half">🏖️ 2Sketch Migration</a>
                                <a onclick="openDoc(554, 'Sketch Migration')" class="duo-button-half">🏝️ Sketch Migration</a>
                            </div>

                            <a onclick="openDoc(502, 'Taggeranger')" class="link-card"><div class="link-card-title">🪃 Taggerang</div></a>
                            
                            <div class="duo-button-group">
                                <a onclick="openDoc(534, 'Tag Fwd')" class="duo-button-half">📨 Tag Fwd</a>
                                <a onclick="openDoc(524, 'Ani Tag Fwd')" class="duo-button-half">✈️ Ani Tag Fwd</a>
                            </div>
                            
                            <div class="duo-button-group">
                                <a onclick="openDoc(530, 'Frame Tagger')" class="duo-button-half">🏷️ Frame Tagger</a>
                                <a onclick="openDoc(535, 'Video Tagger')" class="duo-button-half">🔖 Video Tagger</a>
                            </div>
                            
                            <a onclick="openDoc(9999, 'GS Assign')" class="link-card"><div class="link-card-title">🛼 GS Assign</div></a>
                            <a onclick="openDoc(556, 'Videosmatcher')" class="link-card"><div class="link-card-title">📼 Videosmatcher</div></a>
                        </div>
                    </div>
                    
                    <!-- Category: Creatives -->
                    <div class="cat-content" id="cat-creatives" style="display:none;">
                        <div class="link-grid">
                            <div class="multi-button-group" onclick="openDoc(544, 'Sketches Gallery')">
                                <a class="multi-button-main">🪄 Sketches</a>
                                <a class="multi-button-action">CRUD</a>
                                <a class="multi-button-action">NHX</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(541, 'Animatics Gallery')">
                                <a class="multi-button-main">🎥 Animatics</a>
                                <a class="multi-button-action">CRUD</a>
                                <a class="multi-button-action">NHX</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(541, 'Generatives Gallery')">
                                <a class="multi-button-main">⚡ Generatives</a>
                                <a class="multi-button-action">CRUD</a>
                                <a class="multi-button-action">NHX</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(541, 'Composites Gallery')">
                                <a class="multi-button-main">🧩 Composites</a>
                                <a class="multi-button-action">CRUD</a>
                                <a class="multi-button-action">NHX</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(541, 'Spawns Gallery')">
                                <a class="multi-button-main">🌱 Spawns</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(541, 'Blueprints Gallery')">
                                <a class="multi-button-main">🌌 Blueprints</a>
                                <a class="multi-button-action">CRUD</a>
                                <a class="multi-button-action">NHX</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(541, 'Controlnet Maps')">
                                <a class="multi-button-main">☠️ Controlnet</a>
                                <a class="multi-button-action">CRUD</a>
                                <a class="multi-button-action">NHX</a>
                            </div>
                        </div>
                    </div>

                    <!-- Category: Entities -->
                    <div class="cat-content" id="cat-entities" style="display:none;">
                        <div class="link-grid">
                            <div class="multi-button-group" onclick="openDoc(523, 'Entity Form: Characters')">
                                <a class="multi-button-main">🦸 Characters</a><a class="multi-button-action">CRUD</a><a class="multi-button-action">NHX</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(523, 'Entity Form: Factions')">
                                <a class="multi-button-main">🎌 Factions</a><a class="multi-button-action">CRUD</a><a class="multi-button-action">NHX</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(523, 'Entity Form: Poses')">
                                <a class="multi-button-main">🤸 Poses</a><a class="multi-button-action">CRUD</a><a class="multi-button-action">NHX</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(523, 'Entity Form: Anima Poses')">
                                <a class="multi-button-main">🤹 Anima Poses</a><a class="multi-button-action">CRUD</a><a class="multi-button-action">NHX</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(523, 'Entity Form: Expressions')">
                                <a class="multi-button-main">🧑‍🎤 Expressions</a><a class="multi-button-action">CRUD</a><a class="multi-button-action">NHX</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(523, 'Entity Form: Animas')">
                                <a class="multi-button-main">🐾 Animas</a><a class="multi-button-action">CRUD</a><a class="multi-button-action">NHX</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(523, 'Entity Form: Locations')">
                                <a class="multi-button-main">🗺️ Locations</a><a class="multi-button-action">CRUD</a><a class="multi-button-action">NHX</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(523, 'Entity Form: Backgrounds')">
                                <a class="multi-button-main">🏞️ Backgrounds</a><a class="multi-button-action">CRUD</a><a class="multi-button-action">NHX</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(523, 'Entity Form: Artifacts')">
                                <a class="multi-button-main">🏺 Artifacts</a><a class="multi-button-action">CRUD</a><a class="multi-button-action">NHX</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(523, 'Entity Form: Vehicles')">
                                <a class="multi-button-main">🛸 Vehicles</a><a class="multi-button-action">CRUD</a><a class="multi-button-action">NHX</a>
                            </div>
                        </div>
                    </div>

                    <!-- Category: Audio Entities -->
                    <div class="cat-content" id="cat-audio" style="display:none;">
                        <div class="link-grid">
                            <a onclick="openDoc(492, 'SAGE DAW')" class="link-card"><div class="link-card-title">🎛 DAW</div></a>
                            <a onclick="openDoc(492, 'Project Mixdowns')" class="link-card"><div class="link-card-title">🎧 Project Mixdowns</div></a>
                            <a onclick="openDoc(492, 'Shot Mixdowns')" class="link-card"><div class="link-card-title">🎞️ Shot Mixdowns</div></a>
                            
                            <div class="multi-button-group" onclick="openDoc(523, 'Entity Form: Dialogue')">
                                <a class="multi-button-main">🗣️ Dialogue Lines</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(523, 'Entity Form: Ambiences')">
                                <a class="multi-button-main">⛈️ Ambiences</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(523, 'Entity Form: Cues')">
                                <a class="multi-button-main">🎵 Cues</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(523, 'Entity Form: Foleys')">
                                <a class="multi-button-main">👣 Foleys</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(523, 'Entity Form: FX Sounds')">
                                <a class="multi-button-main">💥 FX Sounds</a>
                            </div>
                            <div class="multi-button-group" onclick="openDoc(523, 'Entity Form: Themes')">
                                <a class="multi-button-main">🎼 Themes</a>
                            </div>
                        </div>
                    </div>

                    <!-- Category: Motion Lab -->
                    <div class="cat-content" id="cat-motion" style="display:none;">
                        <div class="link-grid">
                            <a onclick="openDoc(514, 'VidBat Review')" class="link-card"><div class="link-card-title">🍿 Videos</div></a>
                            <a onclick="openDoc(557, 'Video Admin')" class="link-card"><div class="link-card-title">🎥 Video Admin</div></a>
                            <a onclick="openDoc(479, 'VedTriccs')" class="link-card"><div class="link-card-title">🐳 Video Editor</div></a>
                            <a onclick="openDoc(531, 'MuviTriccs Engine')" class="link-card"><div class="link-card-title">🐬 muvitriccs</div></a>
                            <a onclick="openDoc(499, 'MultiVid Multiplane')" class="link-card"><div class="link-card-title">🌊 Parallax Lab</div></a>
                            <a onclick="openDoc(511, 'Motion Editor')" class="link-card"><div class="link-card-title">🏄 Motion Lab</div></a>
                            <a onclick="openDoc(511, 'Motani Add')" class="link-card"><div class="link-card-title">🛠️ motaniadd</div></a>
                            <a onclick="openDoc(9999, 'GLB Upload')" class="link-card"><div class="link-card-title">🚚 glb upload</div></a>
                        </div>
                    </div>

                    <!-- Category: Frames -->
                    <div class="cat-content" id="cat-frames" style="display:none;">
                        <div class="link-grid">
                            <a onclick="openDoc(9999, 'ScrollMagic')" class="link-card"><div class="link-card-title">⭐ ScrollMagic</div></a>
                            <a onclick="openDoc(529, 'Filter Forge')" class="link-card"><div class="link-card-title">🔦 Filter Forge</div></a>
                            <a onclick="openDoc(553, 'Entities Viewer')" class="link-card"><div class="link-card-title">⛲ Entities Viewer</div></a>
                            <a onclick="openDoc(9999, 'ScrollMagic Depth')" class="link-card"><div class="link-card-title">👻 ScrollMagic Depth</div></a>
                        </div>
                    </div>

                    <!-- Category: 3D Sets -->
                    <div class="cat-content" id="cat-3d" style="display:none;">
                        <div class="link-grid">
                            <a onclick="openDoc(9999, 'Mannequin')" class="link-card"><div class="link-card-title">🧍 Mannequin</div></a>
                            <a onclick="openDoc(9999, 'Babylon Viewer')" class="link-card"><div class="link-card-title">🌆 3D Viewer</div></a>
                            <a onclick="openDoc(9999, '3D Sketchfab')" class="link-card"><div class="link-card-title">🎭 3D Sketchfab</div></a>
                        </div>
                    </div>

                    <!-- Category: Imports -->
                    <div class="cat-content" id="cat-imports" style="display:none;">
                        <div class="link-grid">
                            <a onclick="openDoc(9999, 'Mass Image Upload')" class="link-card"><div class="link-card-title">🏗️ Mass Image Upload</div></a>
                            <a onclick="openDoc(9999, 'Mass Audio Upload')" class="link-card"><div class="link-card-title">📻 Mass Audio Upload</div></a>
                            <a onclick="openDoc(9999, 'Mass Video Upload')" class="link-card"><div class="link-card-title">📡 Mass Video Upload</div></a>
                            <a onclick="openDoc(9999, 'Mass Import Frames2Storyboard')" class="link-card"><div class="link-card-title">🧚 Mass Import Frames2Storyboard</div></a>
                            <a onclick="openDoc(9999, 'Mass Import 2')" class="link-card"><div class="link-card-title">🧞 Mass Import 2 Frames2Storyboard</div></a>
                            <a onclick="openDoc(9999, 'Batch Spawns Import')" class="link-card"><div class="link-card-title">📥 Batch Spawns Import</div></a>
                            <a onclick="openDoc(9999, 'Batch Entity2Entity Import')" class="link-card"><div class="link-card-title">🦋 Batch Entity2Entity Import</div></a>
                            <a onclick="openDoc(9999, 'Batch Sketches2Animatics')" class="link-card"><div class="link-card-title">🏎️ Batch Sketches2Animatics Import</div></a>
                        </div>
                    </div>

                    <!-- Category: Tools -->
                    <div class="cat-content" id="cat-tools" style="display:none;">
                        <div class="link-grid">
                            <a onclick="openDoc(545, 'SAGE TODOs')" class="link-card"><div class="link-card-title">🎫 SAGE TODOs</div></a>
                            <a onclick="openDoc(507, 'Code Recipes')" class="link-card"><div class="link-card-title">🥫 Code Recipes</div></a>
                            <a onclick="openDoc(480, 'Sketchup Export')" class="link-card"><div class="link-card-title">🍅 Sketchup</div></a>
                            <a onclick="openDoc(505, 'Backup Forge')" class="link-card"><div class="link-card-title">💾 Backup</div></a>
                            <a onclick="openDoc(506, 'Content Hub')" class="link-card"><div class="link-card-title">📰 Content Hub</div></a>
                            <a onclick="openDoc(483, 'Magazine Hub')" class="link-card"><div class="link-card-title">🎴 Magazine Hub</div></a>
                            <a onclick="openDoc(9999, 'Pytoon')" class="link-card"><div class="link-card-title">💥 Pytoon</div></a>
                            <a onclick="openDoc(508, 'Mail Hub')" class="link-card"><div class="link-card-title">📬 Mail Hub</div></a>
                            <a onclick="openDoc(9999, 'JSON Lib')" class="link-card"><div class="link-card-title">📟 JSON Lib</div></a>
                            <a onclick="openDoc(9999, 'Bootstrap Icons')" class="link-card"><div class="link-card-title">🔣 Bootstrap Icons</div></a>
                            <a onclick="openDoc(509, 'Documentation System')" class="link-card"><div class="link-card-title">🎓 Documentation</div></a>
                            <a onclick="openDoc(9999, 'Codeboard')" class="link-card"><div class="link-card-title">⌨️ SAGE codeboard</div></a>
                            <a onclick="openDoc(9999, 'Profile')" class="link-card"><div class="link-card-title">👤 Profile</div></a>
                        </div>
                    </div>

                    <!-- Category: Database -->
                    <div class="cat-content" id="cat-database" style="display:none;">
                        <div class="link-grid">
                            <a onclick="openDoc(9999, 'phpMyAdmin')" class="link-card"><div class="link-card-title">🛢️ phpMyAdmin</div></a>
                            <a onclick="openDoc(527, 'DB Tool')" class="link-card"><div class="link-card-title">🛢️ dbtool</div></a>
                            <a onclick="openDoc(484, 'ChromaTool')" class="link-card"><div class="link-card-title">💠 chromatool</div></a>
                            <a onclick="openDoc(9999, 'Chroma Collections')" class="link-card"><div class="link-card-title">📐 Chroma Collections</div></a>
                            <a onclick="openDoc(9999, 'DB Migration')" class="link-card"><div class="link-card-title">🧳 DB Migration</div></a>
                            <a onclick="openDoc(9999, 'Entity SQL Export')" class="link-card"><div class="link-card-title">🎎 Entity SQL Export</div></a>
                        </div>
                    </div>
                    
                </div>
            </div>
        </main>
    </div>

    <!-- Include universal modal framework -->
    <?php require_once 'modal_frame_details.php'; ?>

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
            { id: 'cat-database', title: '🛢️ Database', icon: 'bi-database' }
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
            
            let current = localStorage.getItem('dashdocs_last_cat') || 'cat-editorial';
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
            localStorage.setItem('dashdocs_last_cat', id);
        }

        document.addEventListener('DOMContentLoaded', () => {
            renderSidebar();
            let lastCat = localStorage.getItem('dashdocs_last_cat') || 'cat-editorial';
            selectCategory(lastCat);
        });

        // ── CORE DOCS ROUTER ──
        function openDoc(id, title) {
            if (id === 9999) {
                // If it's a global/undocumented module, load a placeholder or prompt
                alert("Documentation for [" + title + "] is pending. (ID 9999)");
                return;
            }
            // Use the .ie-fullscreen capability of modal_frame_details.php
            window.showFullscreenIframeModal(`view_md.php?id=${id}&embed=1`, `📖 ${title} Docs`);
        }
    </script>
    <script src="/js/theme-manager.js"></script>
</body>
</html>