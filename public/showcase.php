<?php

require "e.php";
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
$dbname = $spw->getDbName();

// load entities tables for the menu
$items = [];
require 'sage_entities_items_array.php';
//require "eruda.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SAGE showcase</title>
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

<link rel="stylesheet" href="/css/toast.css">

<link rel="stylesheet" href="showcase.css">  
</head>
<body>
<?php require "floatool.php"; ?>
<div class="header">
    <img src="theatrical.jpg" alt="Starlight Guardians Theatrical Poster">
</div>






    
    <div style="margin: 20px;" class="orbitron-style">
        <strong>WELCOME</strong><br /> TO SAGE SHOWCASE <br /> <span style="font-size: 0.5em;">Storyboard Animation Generative Environment</span>
    </div>




<div class="script-list">


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


        <!-- Scheduler split button
	<div class="button-group" style="margin-bottom: 15px;">
            <a href="scheduler_view.php">🌀 Scheduler</a>
	    <a id="dashboardLogBtn">📓 Logs</a>
	</div>
-->




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





<!-- 3D Group 
<div class="collapsible-group">
    <div class="group-header">📹 3D sets</div>
    <div class="group-content">
	<div class="horizontal-line"></div>





<a href="mannequin/editor/posture-editor.html">🧍 Mannequin</a>

<a href="babylon_view.php">🌆 3D Viewer</a>
<a href="sketchfab.php">🎭 3D Sketchfab</a>

<a href="posemaniacs.php">🤸 Poses</a>



    </div>
</div>


-->







<!-- Entity Group -->
<div class="collapsible-group">
    <div class="group-header"><span class="icon">🧬</span> Movie Characters &amp; Assets</div>
    <div class="group-content">
        <div class="horizontal-line"></div>

        
            <a href="gallery_characters.php"><span class="icon">👤</span> Characters</a>
        
            <a href="gallery_character_poses.php"><span class="icon">🤸</span> Character Poses</a>

        
            <a href="gallery_animas.php"><span class="icon">🐾</span> Animas</a>

        
            <a href="gallery_locations.php"><span class="icon">🗺️</span> Locations</a>

        
            <a href="gallery_backgrounds.php"><span class="icon">🏞️</span> Backgrounds</a>

        
            <a href="gallery_artifacts.php"><span class="icon">🏺</span> Artifacts</a>

        
            <a href="gallery_vehicles.php"><span class="icon">🛸</span> Vehicles</a>

       <!-- 
            <a href="gallery_scene_parts.php"><span class="icon">🎬</span> Scene Parts</a>
-->



    </div>
</div>






<!-- Creatives Group -->
<div class="collapsible-group">
    <div class="group-header"><span class="icon">💡</span> Creatives</div>
    <div class="group-content">
	<div class="horizontal-line"></div>





        <!-- Spawns split button -->
        
            <a href="gallery_spawns.php"><span class="icon">🌱</span> Spawns</a>

        <!-- Generatives split button -->
        
            <a href="gallery_generatives.php"><span class="icon">⚡</span> Generatives</a>

        <!-- Sketches split button -->
        
            <a href="gallery_sketches.php"><span class="icon">🪄</span> Sketches</a>



        <!-- Controlnet split button -->
        
            <a href="gallery_controlnet_maps.php"><span class="icon">☠️</span> Controlnet</a>





    </div>
</div>



<a href="amplitude/index.php"><span class="icon">🎶</span> Original Movie Soundtrack</a>









    <div class="lora-style" style="margin: 20px 0 0 0;">
        <strong>Thank you!</strong>
        
        Please also read our 
        <a style="padding: 5px; margin: 5px 0;" href="/sage.html">
        SAGE introduction</a>
         as well as the 
         <a style="padding: 5px; margin: 5px 0;" href="/nusage.html">
        SAGE update
        </a>
        
    </div>
    






<!-- Frames Group 
<div class="collapsible-group">
    <div class="group-header">🎞️  Frames</div>
    <div class="group-content">
	<div class="horizontal-line"></div>





<!--
<a href="adminer/index.php?server=127.0.0.1&username=adm
iner&db=<?php echo $dbname; ?>&select=frames&order%5B0
%5D=id&desc%5B0%5D=1">🌄 Frames</a>



<a href="view_imgdir.php">🌄 Frames</a>


<a href="adminer/index.php?server=127.0.0.1&username=adminer&db=<?php echo $dbname; ?>&select=frames&order%5B0  %5D=id&desc%5B0%5D=1">📁 Frames DB</a>

<a href="regenerate_frames_set.php">♻️ Regenerate Frames</a>




<!--
<div class="button-group" style="margin-bottom: 15px;">
    <a href="javascript:void(0);" id="openStylesModal">🖌️ Styles</a>
</div>

	<a href="adminer/index.php?server=127.0.0.1&username=adminer&db=<?php echo $dbname; ?>&select=styles&order%5B0  %5D=id&desc%5B0%5D=1">🖌️  Styles</a>                           



    </div>
</div>


-->







<?php /*

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




	<a href="/phpmyadmin.php">🛢️  phpMyAdmin</a>

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

<a href="todo.php">🎫 SAGE TODOs</a>

<!--
<a href="text2mp3.php">🗣️ Text to mp3</a>
-->
<a class="btn-disabled">🗣️  Text to mp3</a>


	<a href="sql_crud_pastebin.php">📋 Pastebin</a>

<!--
<a href="view_imgdir.php">📁 Frames Browser</a>
-->


	<a href="pages_content_elements.php">🧭 HTML Pages</a>
	<a href="order_recalc.php">🦍 Entity Order Reset</a>

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


    </div>
</div>

<a href="chat.php">💬 AI Chat Prompts</a>
 */
?>


</div>

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
