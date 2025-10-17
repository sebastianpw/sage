<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$pdo = $spw->getPDO();
$styles = $pdo->query("SELECT * FROM styles ORDER BY `order`, id")->fetchAll(PDO::FETCH_ASSOC);

// echo $eruda;

?>

<?php ob_start(); ?>
<div class="spw-styles-modal">
    <div class="spw-styles-header">
        <div class="title">🎨🖌️ Styles</div>
        <div class="subtitle">Quick toggles — active / visible</div>
    </div>


    <div class="spw-styles-list" role="list">
        <?php foreach ($styles as $style): ?>
            <div class="spw-row" role="listitem" data-id="<?= (int)$style['id'] ?>">
                <div class="spw-col spw-name" title="<?= htmlspecialchars($style['description'] ?: '') ?>">
                    <?= htmlspecialchars($style['name']) ?>
                </div>

                <div class="spw-col spw-toggles" aria-hidden="false">
                    <button class="spw-switch spw-active-switch <?= $style['active'] ? 'on' : 'off' ?>"
                        data-field="active"
                        aria-checked="<?= $style['active'] ? 'true' : 'false' ?>"
                        title="Toggle active"
                        type="button">
                        <span class="dot" aria-hidden="true"></span>
                        <span class="label"><?= $style['active'] ? 'On' : 'Off' ?></span>
                    </button>

                    <button class="spw-switch spw-visible-switch <?= $style['visible'] ? 'on' : 'off' ?>"
                        data-field="visible"
                        aria-checked="<?= $style['visible'] ? 'true' : 'false' ?>"
                        title="Toggle visible"
                        type="button">
                        <span class="dot" aria-hidden="true"></span>
                        <span class="label"><?= $style['visible'] ? 'On' : 'Off' ?></span>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="spw-footer">
<div id="spw-status" class="spw-status" aria-live="polite" style="display:none;"></div>
        <small class="muted">Minimal • quick • keyboard friendly (enter / space)</small>
    </div>
</div>

<style>
/* Scoped to the modal content — minimal visual pimping */
.spw-styles-modal {
    color: #EAEAEA;
    padding: 10px 12px;
    font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
    background: transparent;
    height: 100%;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}

.spw-styles-header {
    display:flex;
    justify-content:space-between;
    align-items:baseline;
    gap:12px;
    margin-bottom:8px;
}
.spw-styles-header .title {
    font-weight:700;
    font-size:16px;
    color: #fff;
}
.spw-styles-header .subtitle {
    font-size:12px;
    color: #bfc7cf;
    opacity:0.9;
}

/* status toast */
.spw-status {
    margin: 6px 0;
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 13px;
    color: #0f172a;
    background: #d1fae5; /* green success by default */
}

/* list rows */
.spw-styles-list {
    overflow: auto;
    border-radius: 8px;
    margin: 6px 0 10px 0;
    padding: 6px;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.03);
    flex: 1 1 auto;
}

/* single row */
.spw-row {
    display:flex;
    align-items:center;
    gap:12px;
    padding:10px 8px;
    border-bottom: 1px solid #ccc;
    transition: background 0.12s, transform 0.08s;
}
.spw-row:last-child { border-bottom: none; }

.spw-row:hover {
    background: rgba(255,255,255,0.02);
    transform: translateY(-1px);
}

/* name column (left) */
.spw-name {
    font-weight: 600;
    font-size: 14px;
    width: 200px;
    overflow: auto;
}

/* toggles column (right) */
.spw-toggles {
    display:flex;
    gap:8px;
    align-items:center;
    flex: 0 0 auto;
}

/* switch button (pill) */
.spw-switch {
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:6px 10px;
    min-width:72px;
    border-radius:999px;
    border: 1px solid rgba(255,255,255,0.04);
    background: rgba(255,255,255,0.02);
    color: #d1d5db;
    font-size:13px;
    cursor:pointer;
    transition: all 0.16s ease;
    box-shadow: none;
    outline: none;
}
.spw-switch .dot {
    width:10px; height:10px; border-radius:50%; display:inline-block;
    background: rgba(255,255,255,0.18);
    transition: transform 0.18s, background 0.18s;
}
.spw-switch .label { font-weight:600; }

/* ON state — subtle color */
.spw-switch.on {
    color: #042f2e;
    background: linear-gradient(90deg, rgba(45,212,191,0.95), rgba(96,165,250,0.95));
    border-color: rgba(255,255,255,0.06);
    box-shadow: 0 6px 18px rgba(60,130,180,0.06);
}
.spw-switch.on .dot {
    transform: translateX(2px);
    background: rgba(255,255,255,0.95);
}

/* OFF state — muted */
.spw-switch.off {
    opacity:0.78;
    background: transparent;
    color: #c1c7cf;
}
.spw-switch:active { transform: translateY(1px); }

/* small animation on click */
.spw-switch.pulse {
    animation: spw-pulse 350ms ease-out;
}
@keyframes spw-pulse {
    0% { transform: scale(1); }
    50% { transform: scale(0.98); }
    100% { transform: scale(1); }
}

/* footer */
.spw-footer { margin-top:8px; text-align:center; color:#9aa4ad; font-size:12px; }

/* responsive: stack on very small widths */
@media (max-width:480px) {
    .spw-row { flex-direction: column; align-items:flex-start; gap:6px; padding:8px; }
    .spw-toggles { width:100%; justify-content:flex-end; }
}

/* make sure style names are always visible */
.spw-name {
    color: #111 !important;   /* dark text for light backgrounds */
}
.spw-row:hover .spw-name {
    color: #000 !important;   /* a bit stronger on hover */
}

</style>

<script>
(function(){
    // helper: show temporary status
    function showStatus(msg, type = 'success') {
        var el = document.getElementById('spw-status');
        el.textContent = msg;
        el.style.display = 'block';
        el.style.background = (type === 'success') ? '#d1fae5' : '#fde68a';
        el.style.color = (type === 'success') ? '#064e3b' : '#92400e';
        clearTimeout(showStatus._t);
        showStatus._t = setTimeout(function(){ el.style.display = 'none'; }, 1600);
    }

    // attach handlers
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.spw-switch');
        if (!btn) return;

        // prevent double clicks
        if (btn.disabled) return;
        btn.disabled = true;
        btn.classList.add('pulse');

        var row = btn.closest('.spw-row');
        var id = row.getAttribute('data-id');
        var field = btn.getAttribute('data-field');

        var fd = new FormData();
        fd.append('id', id);
        fd.append('field', field);

        fetch('toggle_styles.php', { method: 'POST', body: fd })
        .then(function(res){ return res.json(); })
        .then(function(json){
            if (!json || typeof json.value === 'undefined') {
                showStatus('Unexpected server reply', 'error');
                return;
            }

            // update the button state
            var target = row.querySelector('[data-field="' + field + '"]');
            if (json.value == 1) {
                target.classList.remove('off'); target.classList.add('on');
                target.setAttribute('aria-checked', 'true');
                target.querySelector('.label').textContent = 'On';
            } else {
                target.classList.remove('on'); target.classList.add('off');
                target.setAttribute('aria-checked', 'false');
                target.querySelector('.label').textContent = 'Off';
            }

            showStatus('Saved', 'success');
        })
        .catch(function(err){
            console.error(err);
            showStatus('Network error', 'error');
        })
        .finally(function(){
            btn.disabled = false;
            setTimeout(function(){ btn.classList.remove('pulse'); }, 250);
        });
    });

    // keyboard: allow Enter/Space to toggle when focus is on the button
    document.addEventListener('keydown', function(e){
        var el = document.activeElement;
        if (!el || !el.classList || !el.classList.contains('spw-switch')) return;
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            el.click();
        }
    });

})();
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, "Styles");
?>
