<?php
// public/vedtriccs/index.php
// SAGE VedTriccs — VED timeline meets MuviTriccs transitions
// Phase 1: Full VED shell + Transition Connector layer + MuviTriccs PyAPI render pipeline
// New module — does NOT modify ved/ or muvitriccs.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../env_locals.php';
require_once __DIR__ . '/classes/VedTriccsConfig.php';
require_once __DIR__ . '/classes/VedTriccsApi.php';

$initAnimaticId = VedTriccsConfig::resolveAnimaticId($_GET['animatic_id'] ?? 0);

if (isset($_REQUEST['api_action'])) {
    (new VedTriccsApi($pdo))->dispatch();
}

// Cache-bust version
$_files = array_merge(
    glob(__DIR__ . '/js/*.js') ?: [],
    glob(__DIR__ . '/css/*.css') ?: [],
    [__FILE__]
);
$_ver = max(array_map('filemtime', array_filter($_files, 'file_exists')));

$pageTitle = 'SAGE VedTriccs — Timeline + Transitions';
ob_start();
?>
<script>
(function(){
    var t = localStorage.getItem('spw_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', t);
})();
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/themes/default/style.min.css"/>
<link rel="stylesheet" href="/css/base.css?v=<?= $_ver ?>">
<link rel="stylesheet" href="/css/toast.css?v=<?= $_ver ?>">
<link rel="stylesheet" href="/vedtriccs/css/vedtriccs.css?v=<?= $_ver ?>">

<script src="/js/toast.js?v=<?= $_ver ?>"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>

<script>
window.VT_INIT_ANIMATIC_ID = <?= $initAnimaticId ?: 'null' ?>;
window.VT_TRACK_HEAD_W     = 160;
</script>

<!-- Core modules (load order matters) -->
<script src="/vedtriccs/js/vt-engine.js?v=<?= $_ver ?>"></script>
<script src="/vedtriccs/js/vt-history.js?v=<?= $_ver ?>"></script>
<script src="/vedtriccs/js/vt-transitions.js?v=<?= $_ver ?>"></script>
<script src="/vedtriccs/js/vt-tracks.js?v=<?= $_ver ?>"></script>
<script src="/vedtriccs/js/vt-playback.js?v=<?= $_ver ?>"></script>
<script src="/vedtriccs/js/vt-drag.js?v=<?= $_ver ?>"></script>
<script src="/vedtriccs/js/vt-bin.js?v=<?= $_ver ?>"></script>
<script src="/vedtriccs/js/vt-modal.js?v=<?= $_ver ?>"></script>
<script src="/vedtriccs/js/vt-render.js?v=<?= $_ver ?>"></script>
<script src="/vedtriccs/js/vt-init.js?v=<?= $_ver ?>"></script>

<!-- ═══════════════════════════════════════════════════════════
     MARKUP
═══════════════════════════════════════════════════════════ -->
<div class="vt-shell">

    <!-- ── Menu Bar ─────────────────────────────────────────── -->
    <div class="vt-menubar" style="padding-left:60px;">
        <button class="mb-btn" onclick="vtAddTrack('Video Track')" title="Add Track">
            <i class="bi bi-plus-lg"></i> Track
        </button>
        <span class="mb-sep"></span>
        <button class="mb-btn" id="mbBtnCut" onclick="vtToggleEditMode('cut')" title="Razor/Split">
            <i class="bi bi-scissors"></i> Cut
        </button>
        <button class="mb-btn" id="mbBtnRem" onclick="vtToggleEditMode('rem')" title="Remove clip">
            <i class="bi bi-eraser"></i> Rem
        </button>
        <span class="mb-sep"></span>
        <button class="mb-btn" id="mbBtnSaveLoad" onclick="vtOpenSaveLoadModal()" title="Save/Load">
            <i class="bi bi-folder2-open"></i> Prj
        </button>
        <button class="mb-btn" id="mbBtnSettings" onclick="vtOpenSettingsModal()" title="Settings">
            <i class="bi bi-sliders2"></i> Set
        </button>
        <span class="mb-sep"></span>
        <button class="mb-btn" id="mbBtnUndo" onclick="vtHistoryUndo()" title="Undo (Ctrl+Z)">
            <i class="bi bi-arrow-counterclockwise"></i> Undo
        </button>
        <button class="mb-btn" id="mbBtnRedo" onclick="vtHistoryRedo()" title="Redo (Ctrl+Y)">
            <i class="bi bi-arrow-clockwise"></i> Redo
        </button>
        <span class="mb-sep"></span>

        <!-- Transition panel toggle — new in VedTriccs -->
        <button class="mb-btn" id="mbBtnTrans" onclick="vtToggleTransPanel()" title="Transition panel">
            <i class="bi bi-shuffle"></i> Trans
        </button>
        <span class="mb-sep"></span>

        <button class="mb-btn" id="mbBtnBounce" onclick="vtBounceProject()" title="Export via PyAPI" style="color:var(--teal);">
            <i class="bi bi-film"></i> Export
        </button>
        <span class="mb-sep" style="margin-left:auto;"></span>

        <div class="mb-animatic-lbl" id="mbAnimaticLbl">— no animatic —</div>
        <button class="mb-btn" onclick="vtOpenAnimaticBrowser()" title="Select animatic">
            <i class="bi bi-film"></i>
        </button>
        <span class="mb-sep"></span>

        <button class="mb-btn" id="btnBin" onclick="vtOpenBin()" title="Asset Bin">
            <i class="bi bi-collection-play"></i> Bin
        </button>
        <span class="mb-sep"></span>

        <button class="mb-btn" id="mbBtnPreview" onclick="vtOpenPreviewModal()" title="Preview (P)">
            <i class="bi bi-pip"></i> Preview
        </button>
        <span class="mb-sep"></span>

        <button class="mb-btn" id="btnFullscreen" onclick="vtToggleFullscreen()" title="Fullscreen (F)">
            <i class="bi bi-fullscreen"></i>
        </button>
    </div>

    <!-- ── Transition Panel (collapsible, between menubar and timeline) ── -->
    <div class="vt-trans-panel" id="vtTransPanel" style="display:none;">
        <div class="vt-trans-panel-header">
            <div class="vt-trans-panel-title" id="vtTransPanelTitle">
                <i class="bi bi-shuffle" style="color:var(--amber);"></i>
                <span>Select connector to configure transition</span>
            </div>
            <div class="vt-trans-panel-actions" id="vtTransPanelActions" style="display:none;">
                <button class="tp-action-btn" id="tpBtnBrowse" onclick="vtOpenBrowseDemos()" title="Browse renders of this type">
                    <i class="bi bi-collection-play"></i> Browse
                </button>
                <button class="tp-action-btn acc" id="tpBtnRender" onclick="vtRenderConnector()" title="Render this transition">
                    <i class="bi bi-play-fill"></i> Render
                </button>
                <button class="tp-action-btn" id="tpBtnSave" onclick="vtSaveConnector()" title="Save transition params">
                    <i class="bi bi-floppy"></i> Save
                </button>
            </div>
            <button class="tp-close-btn" onclick="vtToggleTransPanel()">
                <i class="bi bi-chevron-up" id="vtTransPanelChevron"></i>
            </button>
        </div>

        <div class="vt-trans-panel-body" id="vtTransPanelBody">
            <!-- Transition type grid (left) + params (right) -->
            <div class="vt-trans-layout">
                <!-- Type picker -->
                <div class="vt-trans-type-col">
                    <div class="vt-trans-family-scroll" id="vtTransFamilyScroll">
                        <div class="vt-trans-empty-hint">Loading transitions…</div>
                    </div>
                </div>
                <!-- Params -->
                <div class="vt-trans-params-col">
                    <div class="vt-tp-row">
                        <label class="vt-tp-lbl">Duration</label>
                        <input type="range" class="vt-tp-range" id="tcDurRange" min="2" max="90" value="24"
                               oninput="document.getElementById('tcDurNum').value=this.value">
                        <input type="number" class="vt-tp-num" id="tcDurNum" min="2" max="90" value="24"
                               oninput="document.getElementById('tcDurRange').value=this.value">
                        <span class="vt-tp-unit">fr</span>
                    </div>
                    <div class="vt-tp-row">
                        <label class="vt-tp-lbl">Intensity</label>
                        <input type="range" class="vt-tp-range" id="tcIntRange" min="0.1" max="3.0" step="0.05" value="1.0"
                               oninput="document.getElementById('tcIntVal').textContent=parseFloat(this.value).toFixed(2)">
                        <span class="vt-tp-val" id="tcIntVal">1.00</span>
                    </div>
                    <div class="vt-tp-row">
                        <label class="vt-tp-lbl">Easing</label>
                        <select class="vt-tp-select" id="tcEasing">
                            <option value="ease_in_out_cubic">Ease In/Out Cubic</option>
                            <option value="ease_in_cubic">Ease In</option>
                            <option value="ease_out_cubic">Ease Out</option>
                            <option value="ease_in_out_quart">Ease In/Out Quart</option>
                            <option value="ease_overshoot">Overshoot</option>
                            <option value="linear">Linear</option>
                        </select>
                    </div>
                    <div class="vt-tp-row">
                        <label class="vt-tp-lbl">Seed</label>
                        <input type="number" class="vt-tp-num" id="tcSeed" value="42" style="width:64px;">
                    </div>
                    <!-- Status / job indicator -->
                    <div class="vt-tp-status" id="vtConnStatus"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Body ─────────────────────────────────────────────── -->
    <div class="vt-body">
        <div class="vt-timeline-area" id="vtTimelineArea">

            <!-- Ruler -->
            <div class="vt-ruler-row">
                <div class="ruler-spacer"></div>
                <div class="ruler-scroll" id="rulerWrap">
                    <div id="rulerContent" style="height:100%;min-width:100%;">
                        <canvas id="rulerCanvas"></canvas>
                    </div>
                </div>
            </div>

            <!-- Timeline scroll -->
            <div class="vt-timeline-scroll" id="timelineScroll">
                <div class="vt-empty" id="vtEmpty">
                    <div class="vt-empty-icon"><i class="bi bi-film"></i></div>
                    <div class="vt-empty-text">Timeline empty</div>
                    <div class="vt-empty-hint">Open the <strong style="color:var(--amber);">Bin</strong> to browse assets,<br>
                        then drag clips onto tracks.<br>
                        <span style="color:var(--teal);">Clip boundaries become <strong>transition connectors</strong>.</span>
                    </div>
                </div>
                <div class="vt-timeline-content" id="vtTimelineContent">
                    <div class="playhead" id="playhead"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Bottom Transport ───────────────────────────────── -->
    <div class="vt-bottombar">
        <button class="tp-btn" onclick="vtRewind()" title="Rewind"><i class="bi bi-skip-backward-fill"></i></button>
        <button class="tp-btn pp" id="btnPP" onclick="vtPlayPause()" title="Play/Pause (Space)">
            <i class="bi bi-play-fill" id="ppIcon"></i>
        </button>
        <button class="tp-btn" onclick="vtStop()" title="Stop"><i class="bi bi-stop-fill"></i></button>
        <div class="tp-time" id="tpTime">0:00:00.000</div>
        <div class="tp-zoom">
            <i class="bi bi-zoom-out" style="font-size:11px;color:var(--text-dim);"></i>
            <input type="range" id="zoomSlider" min="10" max="500" value="60"
                   oninput="vtSetZoom(this.value)" title="Zoom">
            <i class="bi bi-zoom-in" style="font-size:11px;color:var(--text-dim);"></i>
        </div>
        <div class="tp-fps">
            <span style="font-size:9px;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;">FPS</span>
            <select id="fpsSelect" onchange="VT_STATE.fps = parseInt(this.value)"
                style="background:transparent;border:none;color:var(--amber);font-size:11px;font-family:var(--font-mono);">
                <option value="24">24</option>
                <option value="25">25</option>
                <option value="30" selected>30</option>
                <option value="60">60</option>
            </select>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         ASSET BIN MODAL
    ═══════════════════════════════════════════════ -->
    <div class="param-backdrop" id="binBackdrop" onclick="if(event.target===this) vtCloseBin()">
        <div class="picker-box" onclick="event.stopPropagation()">
            <div class="picker-head">
                <button class="picker-tree-toggle" id="picker-tree-toggle" title="Filters" onclick="vtTogglePickerTree()"><i class="bi bi-list"></i></button>
                <h3>Add Videos</h3>
                <span id="picker-active-label" style="font-size:11px;color:var(--amber);display:none;margin-left:8px;"></span>
                <button class="picker-head-close" onclick="vtCloseBin()"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="picker-body">
                <div class="picker-tree-backdrop" id="picker-tree-backdrop" onclick="vtClosePickerTree()"></div>

                <div class="picker-tree-panel" id="picker-tree-panel">
                    <div class="picker-tree-header">
                        <div class="picker-mode-btns">
                            <button class="picker-mode-btn active" id="picker-mode-animatic" onclick="vtSwitchPickerMode('animatic')" title="Animatics"><i class="bi bi-film"></i></button>
                            <button class="picker-mode-btn" id="picker-mode-tree"      onclick="vtSwitchPickerMode('tree')" title="Folders"><i class="bi bi-folder2"></i></button>
                            <button class="picker-mode-btn" id="picker-mode-seq"       onclick="vtSwitchPickerMode('seq')" title="Sequences"><i class="bi bi-collection-play"></i></button>
                            <button class="picker-mode-btn" id="picker-mode-fuzz"      onclick="vtSwitchPickerMode('fuzz')" title="Concepts"><i class="bi bi-puzzle"></i></button>
                            <button class="picker-mode-btn" id="picker-mode-storyboard" onclick="vtSwitchPickerMode('storyboard')" title="Storyboards"><i class="bi bi-images"></i></button>
                        </div>
                        <button class="picker-tree-clear" id="picker-tree-clear" style="display:none;" onclick="vtClearPickerFilter()">All</button>
                    </div>

                    <div class="picker-tab-panel active" id="picker-animatic-panel">
                        <div class="picker-search-wrap">
                            <input type="search" id="abSearch" class="picker-search-input" placeholder="Search animatics…" oninput="vtDebouncedAbSearch()">
                        </div>
                        <div id="abList" class="picker-list-scroll"></div>
                        <div class="picker-pagination" id="abPagination" style="display:none;">
                            <button class="pg-btn" id="abPrev" onclick="vtChangeAbPage(-1)"><i class="bi bi-chevron-left"></i></button>
                            <input type="number" class="pg-input" id="abPageInput" value="1" min="1">
                            <span class="pg-of" id="abOf">/ 1</span>
                            <button class="pg-btn" id="abNext" onclick="vtChangeAbPage(1)"><i class="bi bi-chevron-right"></i></button>
                        </div>
                    </div>

                    <div class="picker-tab-panel" id="picker-tree-panel-inner">
                        <div class="picker-list-scroll" id="picker-tree-scroll">
                            <div id="picker-tree">Loading…</div>
                        </div>
                    </div>

                    <div class="picker-tab-panel" id="picker-seq-panel">
                        <div id="picker-seq-list" class="picker-list-scroll"></div>
                    </div>

                    <div class="picker-tab-panel" id="picker-fuzz-panel">
                        <div class="picker-search-wrap">
                            <input type="search" id="picker-fuzz-search" class="picker-search-input" placeholder="Search concepts…">
                        </div>
                        <div id="picker-fuzz-list" class="picker-list-scroll"></div>
                        <div class="picker-pagination" id="picker-fuzz-pg" style="display:none;">
                            <button class="pg-btn" id="picker-fuzz-prev"><i class="bi bi-chevron-left"></i></button>
                            <input type="number" class="pg-input" id="picker-fuzz-page-input" value="1" min="1">
                            <span class="pg-of" id="picker-fuzz-pg-of">/ 1</span>
                            <button class="pg-btn" id="picker-fuzz-next"><i class="bi bi-chevron-right"></i></button>
                        </div>
                    </div>

                    <div class="picker-tab-panel" id="picker-storyboard-panel">
                        <div class="picker-search-wrap">
                            <input type="search" id="picker-storyboard-search" class="picker-search-input" placeholder="Search storyboards…">
                        </div>
                        <div id="picker-storyboard-list" class="picker-list-scroll"></div>
                        <div class="picker-pagination" id="picker-storyboard-pg" style="display:none;">
                            <button class="pg-btn" id="picker-storyboard-prev"><i class="bi bi-chevron-left"></i></button>
                            <input type="number" class="pg-input" id="picker-storyboard-page-input" value="1" min="1">
                            <span class="pg-of" id="picker-storyboard-pg-of">/ 1</span>
                            <button class="pg-btn" id="picker-storyboard-next"><i class="bi bi-chevron-right"></i></button>
                        </div>
                    </div>
                </div>

                <div class="picker-videos-panel">
                    <div class="picker-search-bar">
                        <input type="text" id="binSearch" placeholder="Search videos…" oninput="vtDebouncedBinSearch()">
                    </div>
                    <div class="picker-videos-scroll">
                        <div id="binAssetList" class="picker-grid"></div>
                        <div id="picker-loading" style="display:none;text-align:center;padding:20px;color:var(--text-dim);font-size:11px;">Loading…</div>
                        <div id="picker-empty"   style="display:none;text-align:center;padding:20px;color:var(--text-dim);font-size:11px;">No videos found.</div>
                    </div>
                    <div class="picker-pagination" id="binPagination" style="display:none;border-top:1px solid var(--border);">
                        <button class="pg-btn" id="pgPrev" onclick="vtChangeBinPage(-1)"><i class="bi bi-chevron-left"></i></button>
                        <div class="picker-page-jump">
                            <input type="number" class="pg-input" id="binPageInput" value="1" min="1">
                            <span class="pg-of" id="pgOf">/ 1</span>
                        </div>
                        <button class="pg-btn" id="pgNext" onclick="vtChangeBinPage(1)"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         FLOATING PREVIEW MODAL
    ═══════════════════════════════════════════════ -->
    <div class="fv-modal" id="vtFvModal" style="display:none;">
        <div class="fv-titlebar" id="vtFvTitlebar">
            <span class="fv-title"><i class="bi bi-pip" style="margin-right:5px;color:var(--amber);"></i>Preview</span>
            <div class="fv-titlebar-btns">
                <button class="fv-btn" onclick="vtClosePreviewModal()"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
        <div class="fv-body" id="vtFvBody">
            <video id="vtPreviewVideo" playsinline
                style="width:100%;height:100%;object-fit:contain;display:block;background:#000;"></video>
        </div>
        <div class="fv-info-bar" id="vtFvInfo"></div>
        <div class="fv-resize-handle" id="vtFvResize"></div>
    </div>

    <!-- ═══════════════════════════════════════════════
         SETTINGS MODAL
    ═══════════════════════════════════════════════ -->
    <div class="param-backdrop" id="settingsBackdrop" onclick="if(event.target===this)vtCloseSettingsModal()">
        <div class="param-modal">
            <div class="pm-header">
                <i class="bi bi-sliders2" style="color:var(--amber);font-size:1rem;"></i>
                <div class="pm-title">Timeline Settings</div>
                <button class="pm-close" onclick="vtCloseSettingsModal()"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="pm-body">
                <div class="pm-row">
                    <div style="flex:1;"><div class="pm-label">Show Grid</div></div>
                    <label class="pm-toggle"><input type="checkbox" id="pmGridVisible" checked>
                        <span class="pm-toggle-track"></span><span class="pm-toggle-thumb"></span></label>
                </div>
                <div class="pm-row">
                    <div style="flex:1;"><div class="pm-label">Snap to Grid</div></div>
                    <label class="pm-toggle"><input type="checkbox" id="pmSnapEnabled">
                        <span class="pm-toggle-track"></span><span class="pm-toggle-thumb"></span></label>
                </div>
                <div class="pm-divider"></div>
                <div class="pm-row">
                    <div style="flex:1;"><div class="pm-label">Grid Division</div><div class="pm-sublabel">Snap unit</div></div>
                    <select class="pm-select" id="pmSnapDiv" style="flex:none;width:110px;">
                        <option value="1">1 s</option>
                        <option value="0.5">0.5 s</option>
                        <option value="0.25" selected>0.25 s</option>
                        <option value="0.1">0.1 s</option>
                    </select>
                </div>
                <div class="pm-divider"></div>
                <div class="pm-row-stack">
                    <div class="pm-label">Grid Colour</div>
                    <div class="pm-swatch-row" id="pmSwatchRow"></div>
                </div>
                <div class="pm-row">
                    <div style="flex:1;"><div class="pm-label">Grid Opacity</div></div>
                    <input type="range" id="pmGridOpacity" min="5" max="80" value="18"
                        style="-webkit-appearance:none;width:120px;height:3px;background:var(--border2);border-radius:2px;outline:none;cursor:pointer;"
                        oninput="document.getElementById('pmOpacityVal').textContent=this.value+'%'">
                    <span id="pmOpacityVal" style="font-size:.72rem;color:var(--amber);min-width:36px;text-align:right;">18%</span>
                </div>
                <div class="pm-divider"></div>
                <div class="pm-row">
                    <div style="flex:1;"><div class="pm-label">Export Resolution</div></div>
                    <select class="pm-select" id="pmResolution" style="flex:none;width:120px;">
                        <option value="1920x1080">1920×1080</option>
                        <option value="1280x720">1280×720</option>
                        <option value="1024x1024" selected>1024×1024</option>
                        <option value="512x512">512×512</option>
                    </select>
                </div>
            </div>
            <div class="pm-footer">
                <button class="pm-btn pm-btn-cancel" onclick="vtCloseSettingsModal()">Cancel</button>
                <button class="pm-btn pm-btn-apply" onclick="vtApplySettings()"><i class="bi bi-check2"></i> Apply</button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         SAVE / LOAD MODAL
    ═══════════════════════════════════════════════ -->
    <div class="param-backdrop" id="fileBackdrop" onclick="if(event.target===this)vtCloseSaveLoadModal()">
        <div class="param-modal" id="fileModal">
            <div class="pm-header">
                <i class="bi bi-folder2-open" style="color:var(--amber);font-size:1rem;"></i>
                <div class="pm-title">Save / Load Project</div>
                <button class="pm-close" onclick="vtCloseSaveLoadModal()"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="pm-body" style="gap:0;padding:0;overflow-y:auto;">
                <div class="sl-section">
                    <div style="padding:10px 14px;display:flex;flex-direction:column;gap:8px;">
                        <div class="pm-label">Project Folder</div>
                        <select class="pm-select" id="fileProjectSelect" onchange="vtLoadProjectFilesList()" style="width:100%;"></select>
                        <div style="display:flex;gap:6px;">
                            <input type="text" id="newProjectName" class="pm-select" placeholder="New project name…" style="flex:1;">
                            <button class="pm-btn pm-btn-apply" onclick="vtCreateNewProject()">Create</button>
                        </div>
                    </div>
                    <div id="fileList" class="sl-list">
                        <div style="padding:10px;color:var(--text-faint);text-align:center;font-size:.72rem;">Select a project above</div>
                    </div>
                    <div style="padding:8px 14px;border-top:1px solid var(--border);display:flex;gap:6px;">
                        <input type="text" id="newFileName" class="pm-select" placeholder="New save file name…" style="flex:1;">
                        <button class="pm-btn pm-btn-apply" onclick="vtSaveCurrentProjectFile()"><i class="bi bi-floppy"></i> Save</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         BOUNCE / EXPORT PROGRESS MODAL
    ═══════════════════════════════════════════════ -->
    <div class="param-backdrop" id="bounceBackdrop">
        <div class="param-modal" style="max-width:360px;">
            <div class="pm-header">
                <i class="bi bi-film" style="color:var(--teal);font-size:1rem;"></i>
                <div class="pm-title">Exporting Video…</div>
            </div>
            <div class="pm-body" style="align-items:center;gap:18px;padding:24px 16px;">
                <div class="bounce-spinner"></div>
                <div id="bounceStatusMsg" style="font-size:.75rem;color:var(--text-dim);text-align:center;line-height:1.5;">
                    Preparing assets…
                </div>
                <button class="pm-btn pm-btn-cancel" onclick="vtCancelBounce()" id="btnCancelBounce">Cancel</button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         CLIP TRIM MODAL
    ═══════════════════════════════════════════════ -->
    <div class="param-backdrop" id="trimBackdrop" onclick="if(event.target===this)vtCloseTrimModal()">
        <div class="param-modal" style="max-width:500px;">
            <div class="pm-header">
                <i class="bi bi-scissors" style="color:var(--teal);font-size:1rem;"></i>
                <div class="pm-title" id="trimModalTitle">Clip Trim</div>
                <button class="pm-close" onclick="vtCloseTrimModal()"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="pm-body" style="gap:14px;">
                <video id="trimVideo" class="trim-video" controls playsinline></video>
                <div class="trim-timeline" id="trimTimeline">
                    <div class="trim-track"              id="trimTrack"></div>
                    <div class="trim-handle trim-handle-start" id="trimHandleStart"></div>
                    <div class="trim-handle trim-handle-end"   id="trimHandleEnd"></div>
                    <div class="trim-playhead"           id="trimPlayhead"></div>
                </div>
                <div class="trim-labels">
                    <span id="trimLblStart">0.00 s</span>
                    <span id="trimLblDur" style="color:var(--amber);">full</span>
                    <span id="trimLblEnd">–</span>
                </div>
                <div class="pm-row">
                    <div style="flex:1;"><div class="pm-label">Speed</div></div>
                    <input type="range" id="trimSpeed" min="0.1" max="3.0" step="0.05" value="1.0"
                        oninput="document.getElementById('trimSpeedVal').textContent=parseFloat(this.value).toFixed(2)+'×'">
                    <span id="trimSpeedVal" style="font-size:.72rem;color:var(--amber);min-width:40px;text-align:right;">1.00×</span>
                </div>
            </div>
            <div class="pm-footer">
                <button class="pm-btn pm-btn-cancel" onclick="vtClearTrim()">Clear Trim</button>
                <button class="pm-btn pm-btn-cancel" onclick="vtCloseTrimModal()">Cancel</button>
                <button class="pm-btn pm-btn-apply" onclick="vtApplyTrim()"><i class="bi bi-check2"></i> Apply</button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         BROWSE TRANSITION RENDERS MODAL
    ═══════════════════════════════════════════════ -->
    <div class="param-backdrop" id="browseBackdrop" onclick="if(event.target===this)vtCloseBrowseModal()">
        <div class="param-modal" style="max-width:560px;">
            <div class="pm-header">
                <i class="bi bi-collection-play" style="color:var(--teal);font-size:1rem;"></i>
                <div class="pm-title" id="browseModalTitle">Browse Renders</div>
                <button class="pm-close" onclick="vtCloseBrowseModal()"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="pm-body" style="gap:10px;padding:10px 14px;max-height:60vh;overflow-y:auto;">
                <div class="browse-search-row">
                    <input type="text" id="browseSearch" class="pm-select" placeholder="Search by name…"
                           oninput="vtDebouncedBrowseSearch()">
                </div>
                <div id="browseList"></div>
                <div class="picker-pagination" id="browsePagination" style="display:none;">
                    <button class="pg-btn" id="browsePrev" onclick="vtChangeBrowsePage(-1)"><i class="bi bi-chevron-left"></i></button>
                    <span id="browsePgInfo" style="font-size:.65rem;color:var(--text-dim);"></span>
                    <button class="pg-btn" id="browseNext" onclick="vtChangeBrowsePage(1)"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
            <div class="pm-footer">
                <button class="pm-btn pm-btn-cancel" onclick="vtCloseBrowseModal()">Close</button>
            </div>
        </div>
    </div>

</div><!-- /.vt-shell -->

<?php
$content = ob_get_clean();
$spw->renderLayout($content . ($eruda ?? ''), $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
