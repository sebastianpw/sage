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
/* Use base.css variables where possible to integrate with the theme */
/* Fallbacks included for environments without base.css loaded */

:root {
  --spw-bg: var(--card, #161b22);
  --spw-border-rgb: var(--muted-border-rgb, 48,54,61);
  --spw-text: var(--text, #c9d1d9);
  --spw-text-muted: var(--text-muted, #8b949e);
  --spw-accent: var(--accent, #3b82f6);
  --spw-green: var(--green, #238636);
  --spw-shadow: var(--card-elevation, 0 6px 18px rgba(2,6,23,0.4));
  --spw-blue-light-bg: var(--blue-light-bg, rgba(56, 139, 253, 0.1));
  --spw-blue-light-text: var(--blue-light-text, #79c0ff);
  --spw-blue-light-border: var(--blue-light-border, rgba(59,130,246,0.3));
}

/* Scoped to the modal content — minimal visual integration */
.spw-styles-modal {
    color: var(--spw-text);
    padding: 12px;
    font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background: transparent;
    height: 100%;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}

/* Header */
.spw-styles-header {
    display:flex;
    justify-content:space-between;
    align-items:baseline;
    gap:12px;
    margin-bottom:10px;
}
.spw-styles-header .title {
    font-weight:700;
    font-size:16px;
    color: var(--spw-text);
}
.spw-styles-header .subtitle {
    font-size:12px;
    color: var(--spw-text-muted);
    opacity:0.95;
}

/* status toast */
.spw-status {
    margin: 8px 0;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 13px;
    color: var(--spw-text);
    background: var(--spw-bg);
    border: 1px solid rgba(var(--spw-border-rgb), 0.06);
    display: none;
}

/* list wrapper */
.spw-styles-list {
    overflow: auto;
    border-radius: 8px;
    margin: 6px 0 12px 0;
    padding: 6px;
    background: linear-gradient(180deg, rgba(255,255,255,0.01), rgba(0,0,0,0.01));
    border: 1px solid rgba(var(--spw-border-rgb), 0.06);
    flex: 1 1 auto;
}

/* single row */
.spw-row {
    display:flex;
    align-items:center;
    gap:12px;
    padding:10px 8px;
    border-bottom: 1px solid rgba(var(--spw-border-rgb), 0.06);
    transition: background 0.12s, transform 0.08s;
    background: transparent;
}
.spw-row:last-child { border-bottom: none; }

.spw-row:hover {
    background: rgba(var(--spw-border-rgb), 0.03);
    transform: translateY(-1px);
}

/* name column (left) */
.spw-name {
    font-weight: 600;
    font-size: 14px;
    width: 220px;
    overflow: hidden;
    color: var(--spw-text);
    white-space: nowrap;
    text-overflow: ellipsis;
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
    gap:10px;
    padding:6px 12px;
    min-width:84px;
    border-radius:999px;
    border: 1px solid rgba(var(--spw-border-rgb), 0.06);
    background: rgba(255,255,255,0.01);
    color: var(--spw-text);
    font-size:13px;
    cursor:pointer;
    transition: all 0.16s ease;
    box-shadow: none;
    outline: none;
    justify-content: center;
}
.spw-switch .dot {
    width:12px; height:12px; border-radius:50%; display:inline-block;
    background: rgba(255,255,255,0.06);
    transition: transform 0.18s, background 0.18s;
    box-shadow: 0 1px 0 rgba(0,0,0,0.15) inset;
}
.spw-switch .label { font-weight:600; color: inherit; font-size:13px; }


.spw-switch.on {
    /* gentle blue gradient (soft -> vivid) based on badge-blue / accent */
    background: linear-gradient(90deg,
                var(--spw-blue-light-bg) 0%,
                rgba(59,130,246,0.38) 45%,
                var(--spw-accent) 100%);
    border-color: var(--spw-blue-light-border) !important;
    /* CHANGED: Use a dark anthracite color for high contrast */
    color: #1f2937 !important;
    box-shadow: 0 6px 18px rgba(59,130,246,0.08);
    transition: transform .12s ease, filter .12s ease;
}

/* ensure label remains readable */
.spw-switch.on .label {
    /* CHANGED: Use the same dark color here */
    color: #1f2937;
}

/* Dot: bright/contrasty so it reads on both ends of the gradient */
.spw-switch.on .dot {
    transform: translateX(4px);
    background: #ffffff;
    box-shadow: 0 1px 2px rgba(0,0,0,0.12);
}

/* subtle hover & active affordances */
.spw-switch.on:hover {
    filter: brightness(1.03);
    transform: translateY(-0.6px);
}
.spw-switch.on:active { transform: translateY(0); }

/* OFF state — muted */
.spw-switch.off {
    opacity:0.88;
    background: transparent;
    color: var(--spw-text-muted);
}
.spw-switch:active { transform: translateY(1px); }

/* small animation on click */
.spw-switch.pulse {
    animation: spw-pulse 350ms ease-out;
}
@keyframes spw-pulse {
    0% { transform: scale(1); }
    50% { transform: scale(0.985); }
    100% { transform: scale(1); }
}

/* footer */
.spw-footer { margin-top:8px; text-align:center; color:var(--spw-text-muted); font-size:12px; }

/* responsive: stack on very small widths */
@media (max-width:480px) {
    .spw-row { flex-direction: column; align-items:flex-start; gap:8px; padding:8px; }
    .spw-toggles { width:100%; justify-content:flex-end; }
}

</style>


<script>
(function(){
    // helper: show temporary status (uses notification classes if present)
    function showStatus(msg, type = 'success') {
        var el = document.getElementById('spw-status');
        if (!el) return;
        el.textContent = msg;
        el.style.display = 'block';
        el.className = 'spw-status';
        if (type === 'success') {
            el.classList.add('notification', 'notification-success');
        } else if (type === 'error') {
            el.classList.add('notification', 'notification-error');
        } else {
            el.classList.add('notification');
        }
        clearTimeout(showStatus._t);
        showStatus._t = setTimeout(function(){ el.style.display = 'none'; el.className = 'spw-status'; }, 1600);
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
            if (json.value == 1 || json.value === true || json.value === '1') {
                target.classList.remove('off'); target.classList.add('on');
                target.setAttribute('aria-checked', 'true');
                var lbl = target.querySelector('.label'); if (lbl) lbl.textContent = 'On';
            } else {
                target.classList.remove('on'); target.classList.add('off');
                target.setAttribute('aria-checked', 'false');
                var lbl2 = target.querySelector('.label'); if (lbl2) lbl2.textContent = 'Off';
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
$spw->renderLayout($content, "Styles", $spw->getProjectPath() . '/templates/styles.php'   );
?>