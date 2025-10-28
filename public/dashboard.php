<?php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
$dbname = $spw->getDbName();

// load entities tables for the menu
$items = [];
require 'sage_entities_items_array.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Starlight Guardians Dashboard</title>
<link rel="manifest" href="/site.webmanifest">
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- Font Awesome CSS via CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<?php else: ?>
    <!-- Font Awesome CSS via local copy -->
    <link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css">
<?php endif; ?>
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        background: #f9f9f9;
    }

    /* Header image */
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
        background-color: rgba(0,0,0,0.5);
        padding: 10px 20px;
        border-radius: 8px;
        display: none;
    }

    /* Dashboard links container */
    .script-list {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 20px;
        max-width: 900px;
        margin: auto;
    }

    /* Card-style buttons */
    .script-list a {
        display: block;
        padding: 15px 25px;
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 8px;
        text-decoration: none;
        color: #333;
        font-weight: bold;
        transition: all 0.2s ease;
        width: 100%;
        box-sizing: border-box;
        margin-bottom: 5px; /* small gap between buttons */
    }

    .script-list a:last-child {
        margin-bottom: 0;
    }

    .script-list a:hover {
        background: #e0e0e0;
        border-color: #999;
    }

.script-list a.btn-disabled {
    background: #ffe !important;
    border-color: #dde;
    color: #ccc;   
}


    /* Horizontal line */
    .horizontal-line {
        display: block;
        width: 90%;
        border-bottom: 1px solid #ccc;
        margin: 10px auto;
        height: 0;
    }

    @media (max-width: 600px) {
        .script-list a {
            text-align: center;
        }
    }

    /* Collapsible group styles */
    .collapsible-group {
        width: 100%;
        margin-bottom: 15px;
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
        transform: rotate(-90deg); /* start pointing right (collapsed) */
    }

    .group-header.active::after {
        transform: rotate(0deg); /* points down when open */
    }

    .group-content {
        display: none; /* start collapsed */
    }





/* Split button group */
.button-group {
    display: flex;
    width: 100%;
    margin-bottom: 5px;
    border: 1px solid #ccc;
    border-radius: 8px;
    overflow: hidden; /* keep them joined */
    background: #fff;
}

.button-group a {
    flex: 1; /* equal size by default */
    padding: 15px 20px;
    text-decoration: none;
    color: #333;
    font-weight: bold;
    background: #fff;
    transition: all 0.2s ease;
    text-align: center; /* center text for symmetry */
    border: none; /* remove inner borders */
    margin-bottom: 0;
}

/* Optional: make CRUD smaller instead of 50% */
.button-group a.gallerylink {
    flex: 1;    // left side grows
}
.button-group a.crud {
    flex: 0 0 80px;  // fixed width
    font-weight: normal;
    border-right: 1px solid #ccc;
border-top-right-radius: 0; 
border-bottom-right-radius: 0;
border-top-left-radius: 0;
border-bottom-left-radius: 0;
}

.button-group a.scheduler {
    flex: 0 0 80px;  // fixed width
    font-weight: normal;   
}

.button-group a:first-child {
    border-right: 1px solid #ccc;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.button-group a:last-child {
    border-left: 0 !important;
    border-right: 0 !important;;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

.button-group a:hover {
    background: #e0e0e0;
}


#dashboardLogOverlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.85); z-index: 9999;
    display: flex; justify-content: center; align-items: center;
}
#dashboardLogOverlayContent {
    width: 80%; height: 80%; background: #111; padding: 20px;
    position: relative; display: flex; flex-direction: column;
}
#dashboardLogFrame { flex: 1; width: 100%; background: #000; }
#closeDashboardLogOverlay {
    position: absolute; top: 10px; right: 10px;
    background: #b42318; color: #fff; border: none; padding: 5px 10px;
    cursor: pointer;
}

#stylesOverlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.85); z-index: 9999;
    display: flex; justify-content: center; align-items: center;
}
#stylesOverlayContent {
    width: 80%; height: 80%; background: #111; padding: 20px;
    position: relative; display: flex; flex-direction: column;
}
#stylesFrame { flex: 1; width: 100%; background: #000; }
#closeStylesOverlay {
    position: absolute; top: 10px; right: 10px;
    background: #b42318; color: #fff; border: none; padding: 5px 10px;
    cursor: pointer;
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
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
}
</style>



















<script>
document.addEventListener("DOMContentLoaded", () => {
    // Iterate all collapsible groups
    document.querySelectorAll(".collapsible-group").forEach((group, index) => {
        const header = group.querySelector(".group-header");
        const content = group.querySelector(".group-content");

        // Unique key for localStorage
        const key = "collapsible_state_" + index;

        // Restore saved state
        const savedState = localStorage.getItem(key);
        if (savedState === "open") {
            content.style.display = "block";
            header.classList.add("active");
        } else {
            content.style.display = "none";
            header.classList.remove("active");
        }

        // Toggle on click
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
<div class="header">
    <img src="theatrical.jpg" alt="Starlight Guardians Theatrical Poster">
</div>

<div class="script-list">













<?php
// Handle token saves
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'save_tokens') {
    $tokenDir = PROJECT_ROOT . '/token';
    $results = [];
    
    // Ensure token directory exists
    if (!is_dir($tokenDir)) {
        @mkdir($tokenDir, 0700, true);
    }
    
    // Define token mappings (POST key => filename)
    $tokenMappings = [
        'groq_api_key' => '.groq_api_key',
        'freepik_api_key' => '.freepik_api_key',
        'pollinations_token' => '.pollinationsaitoken',
        'google_api_key' => '.gemini_api_key'
    ];
    
    foreach ($tokenMappings as $postKey => $filename) {
        $value = trim($_POST[$postKey] ?? '');
        
        // Only write if value is provided (not empty)
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
    
    // Build message
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

// Read existing tokens for form initialization
$tokenDir = PROJECT_ROOT . '/token';
$existingTokens = [
    'groq_api_key' => '',
    'freepik_api_key' => '',
    'pollinations_token' => '',
    'google_api_key' => '' 
];

$tokenFiles = [
    'groq_api_key' => '.groq_api_key',
    'freepik_api_key' => '.freepik_api_key',
    'pollinations_token' => '.pollinationsaitoken',
    'google_api_key' => '.gemini_api_key' 
];

foreach ($tokenFiles as $key => $filename) {
    $filepath = $tokenDir . '/' . $filename;
    if (file_exists($filepath)) {
        $existingTokens[$key] = trim(file_get_contents($filepath));
    }
}
?>




























<?php /*
<!-- Dynamic entity select box -->
<div class="horizontal-line"></div>
<select name="item_name" onchange="if(this.value) window.location='sql_crud_' + this.value + '.php';">
    <option value=""> ☆🏄 -- Entity CRUD -- 🏄☆ </option>
    <?php foreach ($items as $i): ?>
        <option value="<?= htmlspecialchars($i['name']) ?>">
            <?= htmlspecialchars($i['name']) ?> <?= $i['type']==='VIEW' ? '(view)' : '' ?>
        </option>
    <?php endforeach; ?>
</select>
<div class="horizontal-line"></div>
 */
?>


        <!-- Scheduler split button -->
	<div class="button-group">
            <a class="gallerylink" href="scheduler_view.php">🌀 Scheduler</a>
	    <a id="dashboardLogBtn">📓 Logs</a>
	</div>


<a style="margin-bottom: 20px;" href="kaggle.php">💻 Notebooks</a>


<!-- Log Overlay -->
<div id="dashboardLogOverlay" style="display:none;">
    <div id="dashboardLogOverlayContent">
        <button id="closeDashboardLogOverlay">✖ Close</button>
        <iframe id="dashboardLogFrame" src="" frameborder="0"></iframe>
    </div>
</div>


<!-- Styles Overlay -->
<div id="stylesOverlay" style="display:none;">
    <div id="stylesOverlayContent">
        <button id="closeStylesOverlay">✖ Close</button>
        <iframe id="stylesFrame" src="" frameborder="0"></iframe>
    </div>
</div>




<!--
<a href="regenerate_frames_set.php">♻️ Regenerate Frames</a>
<a style="margin-bottom: 15px;" href="view_imgdir.php">📁 Frames Browser</a>

-->





<!-- 3D Group -->
<div class="collapsible-group">
    <div class="group-header">📹 3D sets</div>
    <div class="group-content">
	<div class="horizontal-line"></div>


<!--
<a target="_blank" href="https://posemy.art/app/?lang=en">🕺 posemyart</a>
-->


<a href="mannequin/editor/posture-editor.html">🧍 Mannequin</a>

<a href="babylon_view.php">🌆 3D Viewer</a>
<a href="sketchfab.php">🎭 3D Sketchfab</a>
<!--
<a href="posemaniacs.php">🤸 Poses</a>
-->


    </div>
</div>









<!-- Creatives Group -->
<div class="collapsible-group">
    <div class="group-header">💡 Creatives</div>
    <div class="group-content">
	<div class="horizontal-line"></div>





        

        <!-- Generatives split button -->
        <div class="button-group">
            <a href="gallery_generatives.php" class="gallerylink">⚡ Generatives</a>
            <a href="sql_crud_generatives.php" class="crud">CRUD</a>
            <a class="runBtn scheduler" data-id="10">🌀</a>
        </div>
           
        <!-- Composites split button -->
        <div class="button-group">
            <a href="gallery_composites.php" class="gallerylink">🧩 Composites</a>
            <a href="sql_crud_composites.php" class="crud">CRUD</a>
            <a class="runBtn scheduler" data-id="24">🌀</a>
        </div>
        
        
        <!-- Spawns split button -->
        <div class="button-group">
	    <a href="upload_spawns.php" class="gallerylink">🌱 Spawns</a>

<a href=#" class="crud btn-disabled" style="">CRUD</a>

<!--
	    <a href="sql_crud_spawns.php" class="crud">CRUD</a>
-->

            <a class="scheduler" href="upload_spawns.php#uploader">📤</a>
        </div>


        <!-- Sketches split button -->
        <div class="button-group">
            <a href="gallery_sketches.php" class="gallerylink">🪄 Sketches</a>
            <a href="sql_crud_sketches.php" class="crud">CRUD</a>
            <a class="runBtn scheduler" data-id="15">🌀</a>
        </div>




        <!-- Blueprints split button -->
        <div class="button-group">
            <a href="gallery_prompt_matrix_blueprints.php" class="gallerylink">🌌 Blueprints</a>
            <a href="sql_crud_prompt_matrix_blueprints.php" class="crud">CRUD</a>
            <a class="runBtn scheduler" data-id="23">🌀</a>
        </div>





	




        <!-- Controlnet split button -->
        <div class="button-group">
            <a href="gallery_controlnet_maps.php" class="gallerylink">☠️ Controlnet</a>
	    <a href="sql_crud_controlnet_maps.php" class="crud">CRUD</a>
            <a class="runBtn scheduler" data-id="20">🌀</a>
        </div>





    </div>
</div>













<!-- Entity Group -->
<div class="collapsible-group">
    <div class="group-header">🧬 Entity</div>
    <div class="group-content">
        <div class="horizontal-line"></div>

        <div class="button-group">
            <a href="gallery_characters.php" class="gallerylink">🦸 Characters</a>
            <a href="sql_crud_characters.php" class="crud">CRUD</a>
            <a class="runBtn scheduler" data-id="11">🌀</a>
        </div>

        <div class="button-group">
            <a href="gallery_character_poses.php" class="gallerylink">🤸 Character Poses</a>
            <a href="sql_crud_character_poses.php" class="crud">CRUD</a>
            <a class="runBtn scheduler" data-id="19">🌀</a>
        </div>

        <div class="button-group">
            <a href="gallery_animas.php" class="gallerylink">🐾 Animas</a>
            <a href="sql_crud_animas.php" class="crud">CRUD</a>
            <a class="runBtn scheduler" data-id="12">🌀</a>
        </div>

        <div class="button-group">
            <a href="gallery_locations.php" class="gallerylink">🗺️ Locations</a>
            <a href="sql_crud_locations.php" class="crud">CRUD</a>
            <a class="runBtn scheduler" data-id="13">🌀</a>
        </div>

        <div class="button-group">
            <a href="gallery_backgrounds.php" class="gallerylink">🏞️ Backgrounds</a>
            <a href="sql_crud_backgrounds.php" class="crud">CRUD</a>
            <a class="runBtn scheduler" data-id="16">🌀</a>
        </div>

        <div class="button-group">
            <a href="gallery_artifacts.php" class="gallerylink">🏺 Artifacts</a>
            <a href="sql_crud_artifacts.php" class="crud">CRUD</a>
            <a class="runBtn scheduler" data-id="18">🌀</a>
        </div>

        <div class="button-group">
            <a href="gallery_vehicles.php" class="gallerylink">🛸 Vehicles</a>
            <a href="sql_crud_vehicles.php" class="crud">CRUD</a>
            <a class="runBtn scheduler" data-id="17">🌀</a>
        </div>

        <div class="button-group">
            <a href="gallery_scene_parts.php" class="gallerylink">🎬 Scene Parts</a>
            <a href="sql_crud_scene_parts.php" class="crud">CRUD</a>
            <a class="scheduler" data-id=""></a>
        </div>

    </div>
</div>









<!-- Frames Group -->
<div class="collapsible-group">
    <div class="group-header">🎞️  Frames</div>
    <div class="group-content">
	<div class="horizontal-line"></div>


<?php /*


<!--
<a href="adminer/index.php?server=127.0.0.1&username=adm
iner&db=<?php echo $dbname; ?>&select=frames&order%5B0
%5D=id&desc%5B0%5D=1">🌄 Frames</a>
-->


<a href="view_imgdir.php">🌄 Frames</a>


<a href="adminer/index.php?server=127.0.0.1&username=adminer&db=<?php echo $dbname; ?>&select=frames&order%5B0  %5D=id&desc%5B0%5D=1">📁 Frames DB</a>

 */ ?>


<a href="regenerate_frames_set.php">♻️ Regenerate Frames</a>





<a href="view_storyboards_v2.php">📖 Storyboards</a>


<?php /*
<a href="view_storyboard.php?dir=storyboard">📖 Storyboard</a>
*/ ?>


<a href="view_slideshow.php">🎞️ Slideshow</a>


<a href="/view_scrollmagic_dir.php?dir=<?php echo \App\Core\SpwBase::getInstance()->getFramesDirRel(); ?>">📜 ScrollMagic</a>

<a href="/view_scrollmagic.php?dbt=frames">🧚 ScrollMagic DB</a>


<a href="view_video_playlist.php">📽️  Videos</a>


<!--
<div class="button-group" style="margin-bottom: 15px;">
    <a class="gallerylink" href="javascript:void(0);" id="openStylesModal">🖌️ Styles</a>
</div>

	<a href="adminer/index.php?server=127.0.0.1&username=adminer&db=<?php echo $dbname; ?>&select=styles&order%5B0  %5D=id&desc%5B0%5D=1">🖌️  Styles</a>                           
-->


    </div>
</div>












<!-- Database Group -->
<div class="collapsible-group">
    <div class="group-header">🛢️  Database</div>
    <div class="group-content">
	<div class="horizontal-line"></div>

<!--

<a href="adminer/index.php?server=127.0.0.1&username=adm
iner&db=<?php echo $dbname; ?>&select=frames&order%5B0
%5D=id&desc%5B0%5D=1">🌄 Frames</a>

                                                  <a href="adminer/index.php?server=127.0.0.1&username=adminer&db=<?php echo $dbname; ?>&select=styles&order%5B0  %5D=id&desc%5B0%5D=1">🖌️ Styles</a>

-->


<a target="_blank" href="/admin/">🛢️ phpMyAdmin</a>

	<a href="/adminer/index.php?server=127.0.0.1&username=adminer&db=<?php echo $dbname; ?>">🛢️ adminer</a>

	<a href="/adminer/index.php?server=127.0.0.1&username=adminer&db=<?php echo $dbname; ?>&sql=">▶️ Run SQL</a>
	<a href="/adminer/index.php?server=127.0.0.1&username=adminer&db=<?php echo $dbname; ?>&dump=">💾 SQL Table Dump</a>

        <a href="sql_table_structure_dump.php">🏗️ SQL Table Structure</a>
        <a href="sql_dump.php">🗄️ SQL Dump</a>
    </div>
</div>

<!-- Imports Group -->
<div class="collapsible-group">
    <div class="group-header">📥 Imports</div>
    <div class="group-content">
	<div class="horizontal-line"></div>

	<a href="import_spawns.php">📥 Batch Spawns Import</a>

<!--
	<a href="import_generative_from_entity.php">🦋 Batch Entity2Generative Import</a>
-->

<a href="import_entity_from_entity.php">🦋 Batch Entity2Entity Import</a>

<a href="import_character_poses.php">🤺 Batch Character Pose Import
</a>

    </div>
</div>

<!-- Tools Group -->
<div class="collapsible-group">
    <div class="group-header">⚙️ Tools</div>
    <div class="group-content">
	<div class="horizontal-line"></div>

<!--
<a href="adminer/index.php?server=127.0.0.1&username=adminer&db=<?php echo $dbname; ?>&select=frames&order%5B0%5D=id&desc%5B0%5D=1">🌄 Frames</a>



<a href="regenerate_frames_set.php">♻️ Regenerate Frames</a>

-->

<!--
<a href="adminer/index.php?server=127.0.0.1&username=adminer&db=<?php echo $dbname; ?>&select=sage_todos&order%5B0%5D=id&desc%5B0%5D=1">🎫 SAGE TODOs</a>
-->


<!--
<a href="kaggle.php">💻 Notebooks</a>
-->


<!--
<a href="view_zrok.php">🌐 zrok</a>
-->

<a href="view_md.php">🎓 Documentation</a>


<a href="todo.php">🎫 SAGE TODOs</a>


<a href="view_gpt.php">🏴‍☠️ GPT conversations</a>


<a href="codeboard.php">⌨️ SAGE codeboard</a>


<!--
<a href="generator_admin.php">🤖 Generator Admin</a>
-->


<a href="generator_admin_v2.php">🤖 Generator Admin</a>    
<!--
<a href="generator_test_client.php">🧑‍💻 Generator Test Client</a> 
-->




<!--
<a href="tabs.php"><span class="icon">📑 Tabs</span></a>
-->

<!--
<a href="text2mp3.php">🗣️ Text to mp3</a>
-->
<a class="btn-disabled">🗣️  Text to mp3</a>




<?php /*
<a target="_blank" href="https://console.byteplus.com/ark/region:ark+ap-southeast-1/experience/vision?foundationModelName=seedance-1-0-lite-t2v&type=GenVideo">🎥 Seedance</a>





<a target="_blank" href="https://www.minifier.org/text-to-html-converter">❮❯ Text to HTML</a>
 */ ?>

	<a href="sql_crud_pastebin.php">📋 Pastebin</a>

<!--
<a href="view_imgdir.php">📁 Frames Browser</a>
-->


	<a href="pages_content_elements.php">🧭 HTML Pages</a>
	<a href="order_recalc.php">🦍 Entity Order Reset</a>




<?php /*
<!--
	<a href="image_stash.php">🌈 Image Stash</a>
-->


<a href="https://huggingface.co/spaces/VAST-AI/TripoSG" target="_blank">🧊 Tripo SG</a>

<a href="https://gen.hexa3d.io/creations" target="_blank">🐆 gen.hexa3d.io</a>

<a href="https://convert3d.org/app" target="_blank">🔀 3D Model Converter</a>


<a href="https://www.mixamo.com/#/" target="_blank">🏃 Mixamo</a>



<!--
<a href="twain.php">🌊 Twain</a>
-->

	<a href="stockcake.php">🍰 Stockcake</a>
	<a href="https://bulkimagegeneration.com/tools/image-to-text-converter" target="_blank">🌆 img2txt</a>

<a href="https://danbooru.donmai.us/wiki_pages/tag_groups" target="_blank">🏷️ Danbooru Tags</a>


<!--
<a href="stockimg2txt.php">🌆 img2txt</a>
-->
 */ ?>

<a href="amplitude/index.php"><span class="icon">🎶</span> Original Movie Soundtrack</a>


<a href="weather.php"><span class="icon">🌤️ Weather</span></a>

    </div>
</div>



















<?php if (isset($tokenMessage)): ?>
    <div class="token-message <?= htmlspecialchars($tokenMessage['type']) ?>">
        <?= nl2br(htmlspecialchars($tokenMessage['text'])) ?>
    </div>
<?php endif; ?>

<!-- API Tokens Group -->
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
                <input type="password" 
                       name="pollinations_token" 
                       class="token-form-input" 
                       value="<?= htmlspecialchars($existingTokens['pollinations_token']) ?>" 
                       placeholder="Insert Pollinations AI Token here" />
            </div>
            
            <div class="token-form-group">
                <label>🤖 Groq API Key</label>
                <input type="password" 
                       name="groq_api_key" 
                       class="token-form-input" 
                       value="<?= htmlspecialchars($existingTokens['groq_api_key']) ?>" 
                       placeholder="Insert Groq API Key here" />
            </div>

            <div class="token-form-group">
                <label>🎨 Freepik API Key</label>
                <input type="password" 
                       name="freepik_api_key" 
                       class="token-form-input" 
                       value="<?= htmlspecialchars($existingTokens['freepik_api_key']) ?>" 
                       placeholder="Insert Freepik API Key here" />
            </div>

            <!-- New Google API Key Field -->
            <div class="token-form-group">
                <label><i class="fab fa-google"></i> Google AI Studio API Key</label>
                <input type="password" 
                       name="google_api_key" 
                       class="token-form-input" 
                       value="<?= htmlspecialchars($existingTokens['google_api_key'] ?? '') ?>" 
                       placeholder="Insert Google Gemini API Key here" />
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
        e.preventDefault(); // prevent default link behavior
        const $btn = $(this);
        const parentId = $btn.data('parent');

        if (!confirm('Are you sure you want to delete all links with parent_id=' + parentId + '?')) return;

        $btn.css('opacity', 0.6).text('Deleting...'); // visual feedback

        $.post('delete_pages.php', { parent_id: parentId }, function(res) {
            if (res.ok) {
                Toast.show('Deleted ' + res.deleted + ' links successfully!', 'success');
                // wait 2.5 seconds before refreshing so toast is visible
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
    // Add new parent
    $('#addParentBtn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const timestamp = Date.now();
        $btn.css('opacity', 0.6).text('Adding...');

        $.post('add_parent_page.php', { name: 'new' + timestamp }, function(res) {
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







<a href="chat.php">💬 AI Chat Prompts</a>









</div>






<?php echo $eruda; ?>

<div id="toast-container"></div>
<script src="/js/toast.js"></script> 

<script>
$(document).ready(function() {
    $(document).on('click', '.runBtn', function() {
        let id = $(this).data('id'); // read from button itself

        $.post('scheduler_view.php', { action: 'run_now', id: id }, function(res) {
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
    // Open logs overlay
    $('#dashboardLogBtn').click(function(){
        $('#dashboardLogFrame').attr('src', 'view_scheduler_log.php');
        $('#dashboardLogOverlay').fadeIn();
    });

    // Close logs overlay
    $('#closeDashboardLogOverlay').click(function(){
        $('#dashboardLogOverlay').fadeOut();
    });
});




$(document).ready(function() {
    // Open styles overlay
    $('#openStylesModal').click(function(){
        $('#stylesFrame').attr('src', 'styles_toggle.php');
        $('#stylesOverlay').fadeIn();
    });

    // Close styles overlay
    $('#closeStylesOverlay').click(function(){
        $('#stylesOverlay').fadeOut();
    });
});


</script>


</body>
</html>

<?php
/*
🪩 disco ball
📸 Camera with flash → action shot, photography
🎞️ Film frames → film, photos, sequence of images
📹 Video camera → video, moving images
🏞️ National park → landscape photo / background
🌄 Sunrise over mountains → scenic photo
🌆 Cityscape → city photo
🖌️ Paintbrush → artistic imagery
🖋️ Pen → annotation, creative image

Collections / albums:
🗂️ Card index dividers → photo collection / organized items
🗃️ Card file box → stored photos, physical prints
📁 Folder → digital album (less ideal for your “loose” style)
 */
?>
