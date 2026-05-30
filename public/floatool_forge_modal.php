<?php
// public/floatool_forge_modal.php
// ─────────────────────────────────────────────────────────────────────────────
// Generator Forge fullscreen modal — included by floatool.php.
// Replaces the old #generatorDialogOverlay + flyout menu approach.
// The ⚗️ button in floatool now opens generator_forge.php in an iframe
// that fills the screen, with a close ✕ button top-right.
// No changes to generator_forge.php needed.
// ─────────────────────────────────────────────────────────────────────────────
?>

<!-- ── FORGE FULLSCREEN MODAL ── -->
<div id="forgeFullModal" style="
    display: none;
    position: fixed; inset: 0; z-index: 99999;
    background: rgba(0,0,0,0.92);
    align-items: center; justify-content: center;
">
    <!-- Close button — outside the iframe so it's always reachable -->
    <button id="forgeFullClose" title="Close Generator Forge" style="
        position: absolute; top: 14px; right: 16px; z-index: 100000;
        width: 38px; height: 38px;
        background: #1c2535; border: 1px solid #2a3a52; border-radius: 6px;
        color: #c8d4e8; font-size: 18px; line-height: 1;
        cursor: pointer; display: flex; align-items: center; justify-content: center;
        transition: background 0.15s, color 0.15s;
    " onmouseover="this.style.background='#f05060';this.style.color='#fff';"
       onmouseout="this.style.background='#1c2535';this.style.color='#c8d4e8';">
        ✕
    </button>

    <!-- The iframe — lazy-loaded on first open, kept alive after -->
    <iframe
        id="forgeFullFrame"
        src=""
        style="
            width: calc(100vw - 20px);
            height: calc(100vh - 20px);
            border: 1px solid #2a3a52;
            border-radius: 8px;
            background: #080b10;
            display: block;
        "
        allowfullscreen
    ></iframe>
</div>

<style>
/* Touch-friendly close button tap area on mobile */
@media (max-width: 768px) {
    #forgeFullClose {
        top: 8px !important;
        right: 8px !important;
        width: 44px !important;
        height: 44px !important;
        font-size: 20px !important;
    }
    #forgeFullFrame {
        width: 100vw !important;
        height: 100vh !important;
        border-radius: 0 !important;
        border: none !important;
    }
}
</style>

<script>
(function () {
    const modal  = document.getElementById('forgeFullModal');
    const frame  = document.getElementById('forgeFullFrame');
    const close  = document.getElementById('forgeFullClose');
    const FORGE_URL = '/generator_forge.php?embed=1';
    let   loaded = false;

    window.openForgeModal = function () {
        if (!loaded) {
            // Lock iframe to exact screen pixels — breaks dependency on parent
            // page viewport zoom entirely. The child page then controls its own scale.
            frame.style.width  = window.screen.width  + 'px';
            frame.style.height = window.screen.height + 'px';
            frame.src = FORGE_URL;
            loaded = true;
        }
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.closeForgeModal = function () {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    close.addEventListener('click', window.closeForgeModal);

    // Tap the dark backdrop (outside iframe) to close
    modal.addEventListener('click', function (e) {
        if (e.target === modal) window.closeForgeModal();
    });

    // Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            window.closeForgeModal();
        }
    });
})();
</script>
