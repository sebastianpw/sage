<?php
// floatool.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
$dbname = $spw->getDbName();                            
?>
<!-- Floating Toolbar -->
<div id="floatool" class="floatool collapsed">
    <div class="floatool-handle">☰</div>
    <div class="floatool-buttons">
	<button data-action="open-dashboard">🔮</button>
	<button data-action="open-database">🛢️</button>
        <button data-action="open-profile">👤</button>
        <button data-action="open-styles">🎨</button>
        <button data-action="open-regen">♻️</button>
	<button data-action="open-other">⚙️</button>
        <button data-action="open-logs">📓️</button>
    </div>
</div>

<!-- Gear flyout menu -->
<div id="floatoolGearMenu" class="floatool-gear-menu" style="display:none;">
    <a style="border-bottom: 1px solid #ddd;" class="floatoolgearmenulink" href="scheduler_view.php">🌀 Scheduler</a>



<!-- Generatives -->
<a href="#" class="scheduler" data-id="10" onclick="runScheduler(this)">🌀 run ⚡ now</a>

<!-- Sketches -->
<a href="#" class="scheduler" data-id="15" onclick="runScheduler(this)">🌀 run 🪄 now</a>

<!-- Blueprints -->
<a href="#" class="scheduler" data-id="23" onclick="runScheduler(this)">🌀 run 🌌 now</a>

<!-- Composites -->
<a href="#" class="scheduler" data-id="24" onclick="runScheduler(this)">🌀 run 🧩 now</a>

<!-- Controlnet Maps -->
<a href="#" class="scheduler" data-id="20" onclick="runScheduler(this)">🌀 run ☠️ now</a>

<!-- Characters -->
<a href="#" class="scheduler" data-id="11" onclick="runScheduler(this)">🌀 run 🦸 now</a>

<!-- Character Poses -->
<a href="#" class="scheduler" data-id="19" onclick="runScheduler(this)">🌀 run 🤸 now</a>

<!-- Animas -->
<a href="#" class="scheduler" data-id="12" onclick="runScheduler(this)">🌀 run 🐾 now</a>

<!-- Locations -->
<a href="#" class="scheduler" data-id="13" onclick="runScheduler(this)">🌀 run 🗺️ now</a>

<!-- Backgrounds -->
<a href="#" class="scheduler" data-id="16" onclick="runScheduler(this)">🌀 run 🏞️ now</a>

<!-- Artifacts -->
<a href="#" class="scheduler" data-id="18" onclick="runScheduler(this)">🌀 run 🏺 now</a>

<!-- Vehicles -->
<a href="#" class="scheduler" data-id="17" onclick="runScheduler(this)">🌀 run 🛸 now</a>

<!-- Scene Parts -->
<a href="#" class="scheduler" data-id="" onclick="runScheduler(this)">🌀 run 🎬 now</a>




</div>

<style>
    #floatool {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: white;
        border: 1px solid #ccc;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        z-index: 9999;
        cursor: grab;
        transition: width 0.2s ease, height 0.2s ease;
	overflow: hidden;
        font-size: 180%;
    }

    .floatool.collapsed {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .floatool.expanded {
        width: auto;
        height: auto;
        padding: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .floatool-handle {
        cursor: grab;
        user-select: none;
    }

    .floatool.collapsed .floatool-buttons {
        display: none;
    }

    .floatool.expanded .floatool-buttons {
        display: flex;
    }

    .floatool button {
        font-size: 0.7em;
        border: none;
        background: #f8f9fa;
        padding: 6px 10px;
        border-radius: 8px;
        cursor: pointer;
	transition: background 0.2s;
        margin: 0;
    }

    .floatool button:hover {
        background: #e9ecef;
    }

    /* Gear menu flyout */
    .floatool-gear-menu {
        position: fixed; /* changed to fixed for sticking */
        display: flex;
        flex-direction: column;
        gap: 4px;
        z-index: 10000;
        padding: 4px 0;
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }

    .floatool-gear-menu a {
        padding: 6px 12px;
        text-decoration: none;
        color: #333;
        font-weight: bold;
        white-space: nowrap;
        transition: background 0.2s;
    }

    .floatool-gear-menu a:hover {
        background: #e9ecef;
    }
</style>

<script>
(function() {
    const floatool = document.getElementById('floatool');
    const handle = floatool.querySelector('.floatool-handle');
    const gearMenu = document.getElementById('floatoolGearMenu');
    let isDragging = false, offsetX, offsetY;

    // ----------------------
    // Restore position
    // ----------------------
    const savedPos = localStorage.getItem('spw-floatool-pos');
    if(savedPos){
        try {
            const {left, top, right, bottom} = JSON.parse(savedPos);
            if(left) floatool.style.left = left;
            if(top) floatool.style.top = top;
            if(right) floatool.style.right = right;
            if(bottom) floatool.style.bottom = bottom;
        } catch(e){}
    }

    // ----------------------
    // Restore toggle state
    // ----------------------
    const savedCollapsed = localStorage.getItem('spw-floatool-collapsed');
    if(savedCollapsed !== null){
        if(savedCollapsed === '1'){
            floatool.classList.add('collapsed');
            floatool.classList.remove('expanded');
        } else {
            floatool.classList.add('expanded');
            floatool.classList.remove('collapsed');
        }
    }

    // ----------------------
    // Drag logic
    // ----------------------
    handle.addEventListener('mousedown', startDrag);
    handle.addEventListener('touchstart', startDrag);

    function startDrag(e){
        isDragging = true;
        const rect = floatool.getBoundingClientRect();
        offsetX = (e.touches?e.touches[0].clientX:e.clientX) - rect.left;
        offsetY = (e.touches?e.touches[0].clientY:e.clientY) - rect.top;
        floatool.style.cursor = "grabbing";
    }

    document.addEventListener('mousemove', drag);
    document.addEventListener('touchmove', drag);

    function drag(e){
        if(!isDragging) return;
        const x = (e.touches?e.touches[0].clientX:e.clientX) - offsetX;
        const y = (e.touches?e.touches[0].clientY:e.clientY) - offsetY;
        floatool.style.left = x + "px";
        floatool.style.top = y + "px";
        floatool.style.right = "auto";
        floatool.style.bottom = "auto";

        // Update gear menu position if visible
        if(gearMenu.style.display === 'flex') updateGearMenuPosition();
    }

    document.addEventListener('mouseup', endDrag);
    document.addEventListener('touchend', endDrag);

    function endDrag(){
        if(!isDragging) return;
        isDragging = false;
        floatool.style.cursor = "grab";
        localStorage.setItem('spw-floatool-pos', JSON.stringify({
            left: floatool.style.left,
            top: floatool.style.top,
            right: floatool.style.right,
            bottom: floatool.style.bottom
        }));
    }

    // ----------------------
    // Expand/Collapse toggle
    // ----------------------
    handle.addEventListener('click', () => {
        const isCollapsed = floatool.classList.toggle('collapsed');
        floatool.classList.toggle('expanded', !isCollapsed);

        // Save toggle state
        localStorage.setItem('spw-floatool-collapsed', isCollapsed ? '1' : '0');
    });

    // ----------------------
    // Button actions
    // ----------------------
    floatool.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const action = btn.getAttribute('data-action');
	    if(action==='open-dashboard') 
		    window.location.href='dashboard.php';
	    else if(action==='open-profile')
		    window.location.href='view_profile.php';

	    else if(action==='open-database')
		//window.location.href="/adminer/index.php?server=127.0.0.1&username=adminer&db=<?php echo $dbname; ?>"
		window.location.href="/admin"
	    else if(action==='open-regen')
		    window.location.href="regenerate_frames_set.php"
	    else if(action==='open-styles') window.toggleStylesModal?.();
            else if(action==='open-logs') window.toggleLogsModal?.();
            else if(action==='open-other') {
                e.stopPropagation();
                if(gearMenu.style.display === 'flex'){
                    gearMenu.style.display = 'none';
                } else {
                    updateGearMenuPosition();
                    gearMenu.style.display = 'flex';
                }
            }
        });
    });

    // Close gear menu on click outside
    document.addEventListener('click', ()=>{ gearMenu.style.display='none'; });
    gearMenu.addEventListener('click', e=>e.stopPropagation());

    // ----------------------
    // Gear menu position updater
    // ----------------------
    function updateGearMenuPosition(){
        const rect = floatool.getBoundingClientRect();
        gearMenu.style.left = (rect.left + 100) + "px";
        gearMenu.style.top  = (rect.top - gearMenu.offsetHeight - 390) + "px";
    }

})();
</script>

<script>
(function(){
    function createModal(id, iframeSrc){
        let modal = $('#' + id);
        if(modal.length) return modal;

        modal = $(`
            <div id="${id}" style="position:fixed;top:0;left:0;width:100%;height:100%;
                background:rgba(0,0,0,0.85);z-index:9999;display:flex;justify-content:center;align-items:center;">
                <div style="width:80%;height:80%;background:#111;padding:20px;position:relative;display:flex;flex-direction:column;">
                    <button class="close-btn" style="position:absolute;top:10px;right:10px;
                        background:#b42318;color:#fff;border:none;padding:5px 10px;cursor:pointer;">✖ Close</button>
                    <iframe src="${iframeSrc}" frameborder="0" style="flex:1;width:100%;background:#000;"></iframe>
                </div>
            </div>
        `).hide();

        modal.find('.close-btn').click(()=>modal.fadeOut());
        $('body').append(modal);
        return modal;
    }

    window.toggleStylesModal = function(){
        const modal = createModal('floatool-styles-modal', 'styles_toggle.php');
        modal.fadeToggle();
    };

    window.toggleLogsModal = function(){
        const modal = createModal('floatool-logs-modal', 'view_scheduler_log.php');
        modal.fadeToggle();
    };
})();




$(document).ready(function() {
    window.runScheduler = function(el) {
        const $btn = $(el);
        const id = $btn.data('id');

        console.log("Run scheduler clicked, id=", id);

        // Hide the gear menu
        $('#floatoolGearMenu').hide();

        // Send request
        $.post('scheduler_view.php', { action: 'run_now', id: id }, function(res) {
            if (res === 'success') {
                Toast.show('Task scheduled to run now!', 'success');
            } else {
                Toast.show('Failed to trigger task', 'error');
            }
        });
    };
});






/*
$(document).ready(function() {
    $(document).on('click', '.floatoolRunBtn.scheduler', function(e) {
        e.preventDefault();
	//e.stopPropagation();


        alert("clicked!");


        let id = $(this).data('id'); // read the task ID

        // Hide the gear menu
        $('#floatoolGearMenu').hide();

        // Send request
        $.post('scheduler_view.php', { action: 'run_now', id: id }, function(res) {
            if (res === 'success') {
                Toast.show('Task scheduled to run now!', 'success');
            } else {
                Toast.show('Failed to trigger task', 'error');
            }
        });
    });
});
 */




</script>



