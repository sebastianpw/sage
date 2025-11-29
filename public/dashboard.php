<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
$dbname = $spw->getDbName();
$items = [];
require 'sage_entities_items_array.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <link rel="stylesheet" href="/css/base.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f9f9f9;
        }

        .header {
            position: relative;
            text-align: center;
            color: white;
        }

        .header img {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
        }

        .header h1 {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(0, 0, 0, 0.5);
            padding: 10px 20px;
            border-radius: 8px;
            display: none;
        }

        .script-list {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            max-width: 900px;
            margin: auto;
        }

        .script-list a {
            display: block;
            padding: 10px 25px;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            font-weight: bold;
            transition: all 0.2s ease;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 5px;
        }

        .script-list a:last-child {
            margin-bottom: 0;
        }

        .script-list a:hover {
            background: #e0e0e0;
            border-color: #999;
        }

        .script-list a.btn-disabled {
            opacity: 0.3;
            filter: grayscale(40%);
            cursor: not-allowed;
            pointer-events: none;
        }

        .horizontal-line {
            display: block;
            width: 90%;
            border-bottom: 1px solid var(--border) !important;
            margin: 10px auto;
            height: 0;
        }

        @media (max-width: 600px) {
            .script-list a {
                text-align: center;
            }
        }

        .collapsible-group {
            width: 100%;
            margin-bottom: 5px;
        }

        .group-header {
            cursor: pointer;
            padding: 10px 15px;
            font-weight: bold;
            font-size: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f0f0f0;
            border-radius: 8px;
        }

        .group-header::after {
            content: "▼";
            display: inline-block;
            transition: transform 0.2s;
            transform: rotate(-90deg);
        }

        .group-header.active::after {
            transform: rotate(0deg);
        }

        .group-content {
            display: none;
        }

        .button-group {
            display: flex;
            width: 100%;
            margin-bottom: 5px;
            border: 1px solid #ccc;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }

        .button-group a {
            flex: 1;
            padding: 10px 20px;
            text-decoration: none;
            color: #333;
            font-weight: bold;
            background: #fff;
            transition: all 0.2s ease;
            text-align: center;
            border: none;
            margin-bottom: 0;
        }

        .button-group a.gallerylink {
            flex: 1;
        }

        .button-group a.crud {
            flex: 0 0 80px;
            font-weight: normal;
            border-right: 1px solid #ccc;
            border-left: 0 !important;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .button-group a.scheduler {
            flex: 0 0 80px;
            font-weight: normal;
            border-right: 1px solid var(--border) !important;
        }

        .button-group a:first-child {
            border-right: 1px solid #ccc;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .button-group a:last-child {
            border-left: 0 !important;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .button-group a:hover {
            background: #e0e0e0;
        }

        #dashboardLogOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #dashboardLogOverlayContent {
            width: 80%;
            height: 80%;
            background: #111;
            padding: 20px;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        #dashboardLogFrame {
            flex: 1;
            width: 100%;
            background: #000;
        }

        #closeDashboardLogOverlay {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #b42318;
            color: #fff;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
        }

        #stylesOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #stylesOverlayContent {
            width: 80%;
            height: 80%;
            background: #111;
            padding: 20px;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        #stylesFrame {
            flex: 1;
            width: 100%;
            background: #000;
        }

        #closeStylesOverlay {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #b42318;
            color: #fff;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: #f9f9f9;
            padding: 10px 0;
            box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .footer a {
            display: block;
            padding: 15px 25px;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            font-weight: bold;
            transition: all 0.2s ease;
            width: 90%;
            max-width: 900px;
            box-sizing: border-box;
            margin: 0 auto;
        }

        .footer a:hover {
            background: #e0e0e0;
            border-color: #999;
        }
    </style>
    <style>
        .token-message {
            padding: 12px 16px;
            margin: 12px 0;
            border-radius: 8px;
            border-left: 4px solid;
            background: white;
            font-size: 14px;
            white-space: pre-wrap;
        }

        .token-message.success {
            border-color: #10b981;
            background: #f0fdf4;
            color: #166534;
        }

        .token-message.error {
            border-color: #ef4444;
            background: #fef2f2;
            color: #991b1b;
        }

        .token-message.warning {
            border-color: #f59e0b;
            background: #fffbeb;
            color: #92400e;
        }

        .token-form-group {
            margin-bottom: 16px;
        }

        .token-form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
            color: #333;
        }

        .token-form-input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s ease;
            box-sizing: border-box;
        }

        .token-form-input:focus {
            outline: none;
            border-color: #2563eb;
        }

        .token-info-box {
            background: #f0f0f0;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #666;
            margin-bottom: 16px;
            width: 327px;
            max-width: 327px;
            overflow: auto;
        }

        [data-theme="dark"] .token-info-box {
            background: var(--card) !important;
        }

        .token-submit-btn {
            display: inline-block;
            padding: 12px 24px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .token-submit-btn:hover {
            background: #1e40af;
            transform: translateY(-1px);
        }

        .token-code {
            background: #f0f0f0;
            padding: 2px 6px;
            margin: 3px 0 0 0;
            display: block;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
        }

        [data-theme="dark"] .token-code {
            background: var(--bg) !important;
        }
    </style>
    <style>
        html,
        body {
            font-family: var(--font-stack) !important;
            background: var(--bg) !important;
            color: var(--text) !important;
            transition: background-color .18s ease, color .18s ease;
        }

        .script-list a,
        .footer a,
        .button-group a {
            background: var(--card) !important;
            border: 1px solid var(--border) !important;
            color: var(--text) !important;
            transition: background-color .12s ease, border-color .12s ease;
        }

        .script-list a:hover,
        .button-group a:hover,
        .footer a:hover {
            background: var(--hover) !important;
        }

        .group-header {
            background: var(--card) !important;
            color: var(--text) !important;
            border: 1px solid var(--border) !important;
        }

        .button-group {
            background: transparent !important;
            border: 0px solid var(--border) !important;
        }

        .footer {
            background-color: rgba(from var(--card) r g b / 0.9) !important;
            color: var(--text) !important;
            box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.06);
        }

        #dashboardLogOverlay,
        #stylesOverlay {
            background: var(--overlay-bg) !important;
        }

        #dashboardLogOverlayContent,
        #stylesOverlayContent {
            background: var(--card) !important;
            color: var(--text) !important;
        }

        .token-message.success {
            background: #f0fdf4;
            color: #166534;
        }

        .token-message.error {
            background: #fef2f2;
            color: #991b1b;
        }

        .token-form-input {
            background: var(--card) !important;
            color: var(--text) !important;
            border: 2px solid var(--border) !important;
        }

        .card,
        .script-list a,
        .button-group {
            box-shadow: var(--card-elevation);
        }

        #themeToggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        [style*="background: #fff"],
        [style*="background:#fff"] {
            background: var(--card) !important;
        }

        button:focus,
        a:focus,
        .group-header:focus {
            outline: 3px solid rgba(37, 99, 235, 0.14);
            outline-offset: 2px;
        }

        html,
        body {
            font-weight: 400;
            letter-spacing: 0.01em;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.querySelectorAll(".collapsible-group").forEach((group, index) => {
                const header = group.querySelector(".group-header");
                const content = group.querySelector(".group-content");
                const key = "collapsible_state_" + index;
                const savedState = localStorage.getItem(key);
                if (savedState === "open") {
                    content.style.display = "block";
                    header.classList.add("active");
                } else {
                    content.style.display = "none";
                    header.classList.remove("active");
                }
                header.addEventListener("click", () => {
                    const isOpen = content.style.display === "block";
                    content.style.display = isOpen ? "none" : "block";
                    header.classList.toggle("active");
                    localStorage.setItem(key, isOpen ? "closed" : "open");
                });
            });
        });
    </script>
    <?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
</head>
<body>
    <?php require "floatool.php"; ?>
    <div class="header" style="margin: 0; padding: 0;">
        <img src="theatrical.jpg" alt="Starlight Guardians Theatrical Poster">
        <div style="position:absolute; top:12px; right:16px; display:flex; gap:8px; align-items:center;">
            <button id="themeToggle" aria-pressed="false" title="Toggle theme" style="background: none; border: none;">🌙</button>
        </div>
    </div>
    <div class="script-list" style="padding-bottom: 70px;">
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'save_tokens') {
            $tokenDir = PROJECT_ROOT . '/token';
            $results = [];
            if (!is_dir($tokenDir)) {
                @mkdir($tokenDir, 0700, true);
            }
            $tokenMappings = [
                'groq_api_key' => '.groq_api_key',
                'freepik_api_key' => '.freepik_api_key',
                'pollinations_token' => '.pollinationsaitoken',
                'google_api_key' => '.gemini_api_key',
                'mistral_api_key' => '.mistral_api_key',
                'cohere_api_key' => '.cohere_api_key'
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
                $tokenMessage = ['type' => 'error', 'text' => "✗ Failed to save tokens:\n" . implode("\n", $results['failed'])];
            } else {
                $tokenMessage = ['type' => 'warning', 'text' => '⚠ No tokens provided to save'];
            }
        }
        $tokenDir = PROJECT_ROOT . '/token';
        $existingTokens = [
            'groq_api_key' => '',
            'freepik_api_key' => '',
            'pollinations_token' => '',
            'google_api_key' => '',
            'mistral_api_key' => '',
            'cohere_api_key' => ''
        ];
        $tokenFiles = [
            'groq_api_key' => '.groq_api_key',
            'freepik_api_key' => '.freepik_api_key',
            'pollinations_token' => '.pollinationsaitoken',
            'google_api_key' => '.gemini_api_key',
            'mistral_api_key' => '.mistral_api_key',
            'cohere_api_key' => '.cohere_api_key'
        ];
        foreach ($tokenFiles as $key => $filename) {
            $filepath = $tokenDir . '/' . $filename;
            if (file_exists($filepath)) {
                $existingTokens[$key] = trim(file_get_contents($filepath));
            }
        }
        ?>
        <div class="button-group">
            <a class="gallerylink" href="scheduler_view.php">🌀 Scheduler</a>
            <a id="dashboardLogBtn">📓 Logs</a>
        </div>
        <a href="kaggle.php">💻 Notebooks</a>
        <div id="dashboardLogOverlay" style="display:none;">
            <div id="dashboardLogOverlayContent">
                <button id="closeDashboardLogOverlay">✖ Close</button>
                <iframe id="dashboardLogFrame" src="" frameborder="0"></iframe>
            </div>
        </div>
        <div id="stylesOverlay" style="display:none;">
            <div id="stylesOverlayContent">
                <button id="closeStylesOverlay">✖ Close</button>
                <iframe id="stylesFrame" src="" frameborder="0"></iframe>
            </div>
        </div>
        <?php require 'ai_search.php'; ?>

        <div class="collapsible-group">
            <div class="group-header">💡 Creatives</div>
            <div class="group-content">
                <div class="horizontal-line"></div>
                <div class="button-group">
                    <a href="gallery_generatives_nu.php" class="gallerylink">⚡ Generatives</a>
                    <a href="sql_crud_generatives.php" class="crud">CRUD</a>
                    <a class="runBtn scheduler" data-id="10">🌀</a>
                </div>
                <div class="button-group">
                    <a href="gallery_composites_nu.php" class="gallerylink">🧩 Composites</a>
                    <a href="sql_crud_composites.php" class="crud">CRUD</a>
                    <a class="runBtn scheduler" data-id="24">🌀</a>
                </div>
                <div class="button-group">
                    <a href="upload_spawns.php" class="gallerylink">🌱 Spawns</a>
                    <a href=#" class="crud btn-disabled" style="">CRUD</a>
                    <a class="scheduler" href="upload_spawns.php#uploader">📤</a>
                </div>
                <div class="button-group">
                    <a href="gallery_sketches_nu.php" class="gallerylink">🪄 Sketches</a>
                    <a href="sql_crud_sketches.php" class="crud">CRUD</a>
                    <a class="runBtn scheduler" data-id="15">🌀</a>
                </div>
                <div class="button-group">
                    <a href="gallery_prompt_matrix_blueprints_nu.php" class="gallerylink">🌌 Blueprints</a>
                    <a href="sql_crud_prompt_matrix_blueprints.php" class="crud">CRUD</a>
                    <a class="runBtn scheduler" data-id="23">🌀</a>
                </div>
                <div class="button-group">
                    <a href="gallery_controlnet_maps_nu.php" class="gallerylink">☠️ Controlnet</a>
                    <a href="sql_crud_controlnet_maps.php" class="crud">CRUD</a>
                    <a class="runBtn scheduler" data-id="20">🌀</a>
                </div>
<div class="horizontal-line"></div>
            </div>
        </div>
        <div class="collapsible-group">
            <div class="group-header">🧬 Entities</div>
            <div class="group-content">
                <div class="horizontal-line"></div>
                <div class="button-group">
                    <a href="gallery_characters_nu.php" class="gallerylink">🦸 Characters</a>
                    <a href="sql_crud_characters.php" class="crud">CRUD</a>
                    <a class="runBtn scheduler" data-id="11">🌀</a>
                </div>
                <div class="button-group">
                    <a href="gallery_character_poses_nu.php" class="gallerylink">🤸 Character Poses</a>
                    <a href="sql_crud_character_poses.php" class="crud">CRUD</a>
                    <a class="runBtn scheduler" data-id="19">🌀</a>
                </div>
                <div class="button-group">
                    <a href="gallery_animas_nu.php" class="gallerylink">🐾 Animas</a>
                    <a href="sql_crud_animas.php" class="crud">CRUD</a>
                    <a class="runBtn scheduler" data-id="12">🌀</a>
                </div>
                <div class="button-group">
                    <a href="gallery_locations_nu.php" class="gallerylink">🗺️ Locations</a>
                    <a href="sql_crud_locations.php" class="crud">CRUD</a>
                    <a class="runBtn scheduler" data-id="13">🌀</a>
                </div>
                <div class="button-group">
                    <a href="gallery_backgrounds_nu.php" class="gallerylink">🏞️ Backgrounds</a>
                    <a href="sql_crud_backgrounds.php" class="crud">CRUD</a>
                    <a class="runBtn scheduler" data-id="16">🌀</a>
                </div>
                <div class="button-group">
                    <a href="gallery_artifacts_nu.php" class="gallerylink">🏺 Artifacts</a>
                    <a href="sql_crud_artifacts.php" class="crud">CRUD</a>
                    <a class="runBtn scheduler" data-id="18">🌀</a>
                </div>
                <div class="button-group">
                    <a href="gallery_vehicles_nu.php" class="gallerylink">🛸 Vehicles</a>
                    <a href="sql_crud_vehicles.php" class="crud">CRUD</a>
                    <a class="runBtn scheduler" data-id="17">🌀</a>
                </div>
<div class="horizontal-line"></div>
            </div>
        </div>
        <div class="collapsible-group">
            <div class="group-header">🎞️ Frames</div>
            <div class="group-content">
                <div class="horizontal-line"></div>
                <a href="view_storyboards.php">📖 Storyboards</a>
                <a href="/view_scrollmagic_prm.php?query=SELECT%20*%20FROM%20frames%20ORDER%20BY%20id%20DESC%20LIMIT%20200">📜 ScrollMagic</a>
<!--
                <a href="/view_scrollmagic_dir.php?dir=<?php echo \App\Core\SpwBase::getInstance()->getFramesDirRel(); ?>">📜 ScrollMagic</a>
                <a href="/view_scrollmagic.php?dbt=frames">🧚 ScrollMagic DB</a>
-->
                <a href="view_video_admin.php">📽️ Video Admin</a>
                <a href="view_slideshow.php">🎞️  Slideshow</a>
                <a href="wall_of_images.php">🎇 Wall of Frames</a>
<div class="horizontal-line"></div>
            </div>
        </div>
        <div class="collapsible-group">
            <div class="group-header">📹 3D sets</div>
            <div class="group-content">
                <div class="horizontal-line"></div>
                <a href="mannequin/editor/posture-editor.html">🧍 Mannequin</a>
                <a href="babylon_view.php">🌆 3D Viewer</a>
                <a href="sketchfab.php">🎭 3D Sketchfab</a>
<div class="horizontal-line"></div>
            </div>
        </div>
        
        <div class="collapsible-group">
            <div class="group-header">📥 Imports</div>
            <div class="group-content">
                <div class="horizontal-line"></div>
                <a href="view_mass_upload.php">🚀 Mass Image Upload</a>
                <a href="import_spawns.php">📥 Batch Spawns Import</a>
                <a href="import_entity_from_entity.php">🦋 Batch Entity2Entity Import</a>
                <a href="import_character_poses.php">🤺 Batch Character Pose Import
                </a>
                <a href="view_mass_upload_generic.php">🛄 GPT Import</a>
<div class="horizontal-line"></div>
            </div>
        </div>
        <div class="collapsible-group">
            <div class="group-header">🪐 Worldbuilding</div>
            <div class="group-content">
                <div class="horizontal-line"></div>
                <a href="entity_gen.php?entity_type=sketches">💫 Rapid Create</a>
                <a href="view_style_profiles_admin.php">💐 Style Profiles</a>
                <a href="generator_admin.php">🤖 Generators</a>
                <a href="view_sketch_templates_admin.php">🎬 Templates</a>
                <a href="dictionaries_admin.php">📚 Dictionaries</a>
                <a href="bloom_oracle_admin.php">🔮 Bloom Oracle</a>
                <a href="view_wordnet_admin.php">🏛️ WordNet</a>


                <div class="horizontal-line"></div>
            </div>
        </div>


        <div class="collapsible-group">
            <div class="group-header">🎛️ Tools</div>
            <div class="group-content">
                <div class="horizontal-line"></div>
                <a href="view_md.php">🎓 Documentation</a>
                <a href="posts/posts_admin.php">🗞️  Posts</a>
                <a href="todo.php">🎫 SAGE TODOs</a>
                <a href="view_gpt.php">🏴‍☠️ GPT conversations</a>
                <a href="view_recipes.php">🥫 Code Recipes</a>
                <a href="codeboard.php">⌨️ SAGE codeboard</a>

                <a href="view_profile.php"><span class="icon">👤 Profile</span></a>
                



<?php /*<a class="btn-disabled">🗣️ Text to mp3</a>*/ ?>
<?php /*<a href="pages_content_elements.php">📰 HTML Pages</a>*/ ?>
<!--
                <a href="regenerate_frames_set.php">♻️ Regenerate Frames</a>
                <a href="order_recalc.php">🦍 Entity Order Reset</a>
-->
                <div class="horizontal-line"></div>     
            </div>
        </div>
        <div class="collapsible-group">
            <div class="group-header">🛢️ Database</div>
            <div class="group-content">
                <div class="horizontal-line"></div>
                <a target="_self" href="/admin/">🛢️ phpMyAdmin</a>
                <a target="_self" href="/dbtool.php">🛢️ dbtool</a>
                <a href="view_db_migration_admin.php">🧳 DB Migration</a>
<div class="horizontal-line"></div>
            </div>
        </div>
        <?php if (isset($tokenMessage)) : ?>
            <div class="token-message <?= htmlspecialchars($tokenMessage['type']) ?>">
                <?= nl2br(htmlspecialchars($tokenMessage['text'])) ?>
            </div>
        <?php endif; ?>
        <div class="collapsible-group">
            <div class="group-header">🔑 API Tokens</div>
            <div class="group-content">
                <div class="horizontal-line"></div>
                <div class="token-info-box">
                    💡 <strong>Tokens are saved to:</strong> <span class="token-code"><?= htmlspecialchars(PROJECT_ROOT) ?>/token/</span><br>
                    ℹ️ Only changed fields will be saved. Empty fields won't overwrite existing tokens.
                </div>
                <form method="post" style="max-width: 600px;">
                    <input type="hidden" name="action" value="save_tokens">
                    <div class="token-form-group">
                        <label>🌸 Pollinations AI Token</label>
                        <input type="password" name="pollinations_token" class="token-form-input" value="<?= htmlspecialchars($existingTokens['pollinations_token']) ?>" placeholder="Insert Pollinations AI Token here" />
                    </div>
                    <div class="token-form-group">
                        <label>🤖 Groq API Key</label>
                        <input type="password" name="groq_api_key" class="token-form-input" value="<?= htmlspecialchars($existingTokens['groq_api_key']) ?>" placeholder="Insert Groq API Key here" />
                    </div>
                    <div class="token-form-group">
                        <label>🎨 Freepik API Key</label>
                        <input type="password" name="freepik_api_key" class="token-form-input" value="<?= htmlspecialchars($existingTokens['freepik_api_key']) ?>" placeholder="Insert Freepik API Key here" />
                    </div>
                    <div class="token-form-group">
                        <label><i class="fab fa-google"></i> Google AI Studio API Key</label>
                        <input type="password" name="google_api_key" class="token-form-input" value="<?= htmlspecialchars($existingTokens['google_api_key'] ?? '') ?>" placeholder="Insert Google Gemini API Key here" />
                    </div>
                    <div class="token-form-group">
                        <label>🧠 Mistral API Key</label>
                        <input type="password" name="mistral_api_key" class="token-form-input" value="<?= htmlspecialchars($existingTokens['mistral_api_key'] ?? '') ?>" placeholder="Insert Mistral API Key here" />
                    </div>
                    <div class="token-form-group">
                        <label>🤖 Cohere API Key</label>
                        <input type="password" name="cohere_api_key" class="token-form-input" value="<?= htmlspecialchars($existingTokens['cohere_api_key'] ?? '') ?>" placeholder="Insert Cohere API Key here" />
                    </div>
                    <button type="submit" class="token-submit-btn">💾 Save Tokens</button>
                </form>
                <div class="horizontal-line" style="margin-top: 20px;"></div>
            </div>
        </div>
        <div class="collapsible-group">
            <div class="group-header">📑 Tabs</div>
            <div class="group-content">
                <div class="horizontal-line"></div>
                <a href="view_import_links.php">🖇️ Import Links</a>
                <a href="#" id="deleteLinksBtn" data-parent="1001">🗑️ Delete Links</a>
                <a href="#" id="addParentBtn">➕ Add New Parent</a>
                <script>
                    $(document).ready(function() {
                        $('#deleteLinksBtn').on('click', function(e) {
                            e.preventDefault();
                            const $btn = $(this);
                            const parentId = $btn.data('parent');
                            if (!confirm('Are you sure you want to delete all links with parent_id=' + parentId + '?')) return;
                            $btn.css('opacity', 0.6).text('Deleting...');
                            $.post('delete_pages.php', {
                                parent_id: parentId
                            }, function(res) {
                                if (res.ok) {
                                    Toast.show('Deleted ' + res.deleted + ' links successfully!', 'success');
                                    setTimeout(function() {
                                        location.reload();
                                    }, 2500);
                                } else {
                                    Toast.show('Failed to delete links: ' + (res.error || 'unknown'), 'error');
                                    $btn.css('opacity', 1).text('🗑️ Delete Links');
                                }
                            }, 'json').fail(function() {
                                Toast.show('Server error during delete', 'error');
                                $btn.css('opacity', 1).text('🗑️ Delete Links');
                            });
                        });
                    });
                </script>
                <script>
                    $(document).ready(function() {
                        $('#addParentBtn').on('click', function(e) {
                            e.preventDefault();
                            const $btn = $(this);
                            const timestamp = Date.now();
                            $btn.css('opacity', 0.6).text('Adding...');
                            $.post('add_parent_page.php', {
                                name: 'new' + timestamp
                            }, function(res) {
                                if (res.ok) {
                                    Toast.show('Added new parent successfully!', 'success');
                                    setTimeout(() => location.reload(), 2500);
                                } else {
                                    Toast.show('Failed to add new parent: ' + (res.error || 'unknown'), 'error');
                                    $btn.css('opacity', 1).text('➕ Add New Parent');
                                }
                            }, 'json').fail(function() {
                                Toast.show('Server error during add', 'error');
                                $btn.css('opacity', 1).text('➕ Add New Parent');
                            });
                        });
                    });
                </script>
                <?php require "tabs_widget.php"; ?>
            </div>
        </div>
    </div>
    <?php echo $eruda; ?>
    <div id="toast-container"></div>
    <script src="/js/toast.js"></script>
    <script>
        $(document).ready(function() {
            $(document).on('click', '.runBtn', function() {
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
        });
    </script>
    <script>
        $(document).ready(function() {
            $('#dashboardLogBtn').click(function() {
                $('#dashboardLogFrame').attr('src', 'view_scheduler_log.php');
                $('#dashboardLogOverlay').fadeIn();
            });
            $('#closeDashboardLogOverlay').click(function() {
                $('#dashboardLogOverlay').fadeOut();
            });
        });
        $(document).ready(function() {
            $('#openStylesModal').click(function() {
                $('#stylesFrame').attr('src', 'styles_toggle.php');
                $('#stylesOverlay').fadeIn();
            });
            $('#closeStylesOverlay').click(function() {
                $('#stylesOverlay').fadeOut();
            });
        });
    </script>
    <div class="footer">
        <a href="chat.php">💬 AI Chat</a>
    </div>
    <script src="/js/theme-manager.js"></script>
</body>
</html>
