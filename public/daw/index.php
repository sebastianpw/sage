<?php
// daw/index.php
// SAGE DAW — Multitrack  (entry point)
// All API logic → classes/DawApi.php
// All JS logic  → js/daw-*.js
// All CSS       → css/daw.css

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../env_locals.php';
require_once __DIR__ . '/../entity_icons.php';

require_once __DIR__ . '/classes/DawConfig.php';
require_once __DIR__ . '/classes/DawApi.php';

// ── Resolve entity & deep-link params ────────────────────────────────────────
$selectedEntity = DawConfig::resolveEntity(
    $_REQUEST['entity']      ??
    $_GET['entity_type']     ??
    'audio_cues'
);
$deepLinkEntityId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;

// ── Shot/Scene deep-link params ──────────────────────────────────────────────
$initSceneId = isset($_GET['scene_id']) ? (int)$_GET['scene_id'] : 0;
$initShotId  = isset($_GET['shot_id'])  ? (int)$_GET['shot_id']  : 0;

// ── API Handler (exits early) ────────────────────────────────────────────────
if (isset($_REQUEST['api_action'])) {
    (new DawApi($pdo, DawConfig::$audioEntities, $selectedEntity))->dispatch();
}

// ── Page Render ──────────────────────────────────────────────────────────────
$pageTitle = 'SAGE DAW — Multitrack';

$_dawFiles = glob(__DIR__ . '/js/*.js') + glob(__DIR__ . '/css/*.css') + [__FILE__];
$_dawVer   = max(array_map('filemtime', array_filter($_dawFiles, 'file_exists')));

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
<link rel="stylesheet" href="/css/base.css?v=<?php echo $_dawVer; ?>">
<link rel="stylesheet" href="/css/toast.css?v=<?php echo $_dawVer; ?>">
<link rel="stylesheet" href="/daw/css/daw.css?v=<?php echo $_dawVer; ?>">

<?php if ($initShotId || $initSceneId): ?>
<style>
/* ── Docked shot player — square, above shell, shot/scene mode only ── */
.daw-shot-player-wrap {
    flex-shrink: 0;
    width: 100%;
    aspect-ratio: 1 / 1;
    background: #000;
    border-bottom: 2px solid var(--amber);
    position: relative;
    z-index: 110;
    max-height: 40dvh;
    overflow: hidden;
    transition: max-height 0.25s ease, border-width 0.25s ease;
}
.daw-shot-player-wrap.docked-hidden {
    max-height: 0;
    border-bottom-width: 0;
}
.daw-shot-player-wrap video {
    width: 100%; height: 100%;
    object-fit: contain; display: block;
}
.daw-shot-player-placeholder {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-dim); font-size: 0.65rem;
    text-transform: uppercase; letter-spacing: 2px;
    font-family: var(--font-mono); pointer-events: none;
}
.daw-shot-player-wrap video.hidden { display: none; }
</style>
<?php endif; ?>

<script src="/js/toast.js?v=<?php echo $_dawVer; ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/howler@2.2.4/dist/howler.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/wavesurfer.js@7/dist/wavesurfer.min.js"></script>
<script src="https://unpkg.com/wavesurfer.js@7/dist/plugins/envelope.min.js"></script>
<script src="https://unpkg.com/tone@15.1.22/build/Tone.js"></script>
<?php if (file_exists(__DIR__ . '/../forge_tool.php')) require_once __DIR__ . '/../forge_tool.php'; ?>

<!-- JS init-time config passed from PHP -->
<script>
window.DAW_INIT_ENTITY    = <?php echo json_encode($selectedEntity); ?>;
window.DAW_INIT_ENTITY_ID = <?php echo $deepLinkEntityId ?: 'null'; ?>;
window.DAW_INIT_SCENE_ID  = <?php echo $initSceneId ?: 'null'; ?>;
window.DAW_INIT_SHOT_ID   = <?php echo $initShotId  ?: 'null'; ?>;
// Track head width override — must match --track-head-w CSS token (110px).
// Declared here so daw-engine.js picks it up before defining TRACK_HEAD_W.
window.DAW_TRACK_HEAD_W   = 150;
</script>

<!-- DAW JS Modules (order matters: engine first, then dependents) -->
<script src="/daw/js/daw-engine.js?v=<?php echo $_dawVer; ?>"></script>
<script src="/daw/js/daw-history.js?v=<?php echo $_dawVer; ?>"></script>
<script src="/daw/js/daw-envelope.js?v=<?php echo $_dawVer; ?>"></script>
<script src="/daw/js/daw-tracks.js?v=<?php echo $_dawVer; ?>"></script>
<script src="/daw/js/daw-playback.js?v=<?php echo $_dawVer; ?>"></script>
<script src="/daw/js/daw-drag.js?v=<?php echo $_dawVer; ?>"></script>
<script src="/daw/js/daw-bin.js?v=<?php echo $_dawVer; ?>"></script>
<script src="/daw/js/daw-modal.js?v=<?php echo $_dawVer; ?>"></script>
<script src="/daw/js/daw-init.js?v=<?php echo $_dawVer; ?>"></script>

<!-- ════════════════════════════════════════════════════════
     MARKUP
════════════════════════════════════════════════════════ -->
<div class="daw-shell">

<?php if ($initShotId || $initSceneId): ?>
    <!-- ── Docked Shot Video Player ─────────────────────── -->
    <div class="daw-shot-player-wrap" id="dawShotPlayerWrap">
        <div class="daw-shot-player-placeholder" id="dawShotPlayerPlaceholder">
            <span>Loading shot video…</span>
        </div>
        <video id="dawShotVideo" class="hidden" controls playsinline preload="auto"></video>
    </div>
<?php endif; ?>

    <!-- ── Sub-Header Menu Bar ───────────────────────────── -->
    <div class="daw-menubar">
        <button class="mb-btn" onclick="addTrackLane('New Track')" title="Add Empty Lane">
            <i class="bi bi-plus-lg"></i> Add
        </button>
        <span class="mb-sep"></span>
        <button class="mb-btn" id="mbBtnCut" onclick="toggleEditMode('cut')" title="Cut clip (Split)">
            <i class="bi bi-scissors"></i> Cut
        </button>
        <button class="mb-btn" id="mbBtnRem" onclick="toggleEditMode('rem')" title="Remove clip">
            <i class="bi bi-eraser"></i> Rem
        </button>
        <span class="mb-sep"></span>
        <button class="mb-btn" id="mbBtnSaveLoad" onclick="openSaveLoadModal()" title="Save/Load Project Files">
            <i class="bi bi-folder2-open"></i> Prj
        </button>
        <button class="mb-btn" id="mbBtnProject" onclick="openParamModal()" title="Project settings">
            <i class="bi bi-sliders2"></i> Set
        </button>
        <span class="mb-sep"></span>
        <button class="mb-btn" id="mbBtnUndo" onclick="historyUndo()" title="Undo (Ctrl+Z)">
            <i class="bi bi-arrow-counterclockwise"></i> Undo
        </button>
        <button class="mb-btn" id="mbBtnRedo" onclick="historyRedo()" title="Redo (Ctrl+Y)">
            <i class="bi bi-arrow-clockwise"></i> Redo
        </button>
        <span class="mb-sep"></span>
        <button class="mb-btn" id="mbBtnBounce" onclick="bounceProject()" title="Export Mixdown via PyAPI" style="color:var(--teal);">
            <i class="bi bi-file-earmark-music"></i> Bnc
        </button>
        <span class="mb-sep" style="margin-left:auto;"></span>
        <!-- Bin button (moved here from header) -->
        <button class="mb-btn" id="btnBin" onclick="openBin()" title="Asset Bin">
            <i class="bi bi-collection-play"></i> Bin
        </button>
        <span class="mb-sep"></span>
<?php if ($initShotId || $initSceneId): ?>
        <!-- Float/dock video button — only in shot/scene mode -->
        <button class="mb-btn" id="btnFloatVideo" onclick="toggleVideoFloat()" title="Float / dock video player">
            <i class="bi bi-pip"></i> Vid
        </button>
        <span class="mb-sep"></span>
<?php endif; ?>
        <!-- Master channel button -->
        <button class="mb-btn" id="mbBtnMaster" onclick="openMasterModal()" title="Master Channel">
            <i class="bi bi-broadcast-pin"></i> Mst
        </button>
        <span class="mb-sep"></span>
        <!-- Fullscreen button — always last -->
        <button class="mb-btn" id="btnFullscreen" onclick="toggleFullscreen()" title="Toggle Fullscreen (F)">
            <i class="bi bi-fullscreen"></i>
        </button>
    </div>
    <!-- Ghost elements: syncMenuBar() in daw-engine.js writes to these IDs.
         They are hidden but must exist in the DOM to prevent a TypeError crash. -->
    <span id="mbBpm"    style="display:none;"></span>
    <span id="mbSig"    style="display:none;"></span>
    <span id="mbGrid"   style="display:none;"></span>
    <span id="snapBadge" style="display:none;"></span>
    <span id="gridBadge" style="display:none;"></span>

    <!-- ── Body Layout ───────────────────────────────────── -->
    <div class="daw-body">

        <!-- Ruler Row -->
        <div class="daw-ruler-row">
            <div class="ruler-spacer"></div>
            <div class="ruler-scroll" id="rulerWrap">
                <div id="rulerContent" style="height:100%;min-width:100%;">
                    <canvas id="rulerCanvas"></canvas>
                </div>
            </div>
        </div>

        <!-- Master Timeline -->
        <div class="daw-timeline-scroll" id="timelineScroll">
            <div class="daw-empty" id="dawEmpty">
                <div class="daw-empty-icon"><i class="bi bi-soundwave"></i></div>
                <div class="daw-empty-text">Timeline empty</div>
                <div class="daw-empty-hint">Open the <strong style="color:var(--amber);">Bin</strong> and click <i class="bi bi-plus-circle"></i> on any audio asset to add tracks.<br>Or drag and drop items here.</div>
            </div>

            <div class="daw-timeline-content" id="dawTimelineContent">
                <div class="playhead" id="playhead"></div>
                <!-- Track Lanes injected here by JS -->
            </div>
        </div>

    </div>

    <!-- ── Bottom Transport Bar ──────────────────────────── -->
    <div class="daw-bottombar">
        <button class="tp-btn" onclick="dawRewind()" title="Rewind to start"><i class="bi bi-skip-backward-fill"></i></button>
        <button class="tp-btn pp" id="btnPP" onclick="dawPlayPause()" title="Play / Pause (Space)"><i class="bi bi-play-fill" id="ppIcon"></i></button>
        <button class="tp-btn" onclick="dawStop()" title="Stop"><i class="bi bi-stop-fill"></i></button>
        <div class="tp-time" id="tpTime">0:00.000</div>
    </div>

    <!-- ── Asset Bin ─────────────────────────────────────── -->
    <div class="bin-overlay" id="binOverlay" onclick="closeBin()"></div>
    <div class="bin-panel"   id="binPanel">
        <div class="bin-header">
            <div class="bin-title"><i class="bi bi-collection-play" style="color:var(--amber);margin-right:5px;"></i>Asset Bin</div>
            <button class="bin-close" onclick="closeBin()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="bin-entity-bar">
            <select class="bin-entity-select" id="binEntitySelect" onchange="onEntityTypeChange(this.value)">
                <?php foreach (DawConfig::$audioEntities as $ename):
                    $icon = $entityIcons[$ename] ?? '🎧';
                ?>
                    <option value="<?php echo htmlspecialchars($ename, ENT_QUOTES); ?>"
                        <?php echo ($ename === $selectedEntity ? 'selected' : ''); ?>>
                        <?php echo htmlspecialchars($icon . '  ' . $ename, ENT_QUOTES); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="bin-search-row">
                <input type="text" class="bin-search" id="binSearch" placeholder="Search entities…" oninput="debouncedEntitySearch()">
                <button class="bin-new-btn" onclick="createNewEntity()"><i class="bi bi-plus-lg"></i> New</button>
            </div>
        </div>
        <div class="bin-entity-list" id="binEntityList">
            <div class="bin-state"><div class="spin-s"></div> Loading…</div>
        </div>
        <div class="bin-pagination" id="binPagination" style="display:none;">
            <button class="pg-btn" id="pgPrev" onclick="changePage(-1)"><i class="bi bi-chevron-left"></i></button>
            <span class="pg-num" id="pgCur">1</span>
            <span class="pg-of"  id="pgOf">/ 1</span>
            <button class="pg-btn" id="pgNext" onclick="changePage(1)"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="bin-assets-header">
            <span class="bin-assets-lbl">Audios</span>
            <span class="bin-assets-count" id="binAssetsCount">–</span>
        </div>
        <div style="padding:6px 12px;border-bottom:1px solid var(--border);flex-shrink:0;">
            <input type="text" class="bin-search" id="binAssetSearch" placeholder="Filter audios…" oninput="debouncedAssetSearch()" style="width:100%;">
        </div>
        <div class="bin-assets-list" id="binAssetList">
            <div class="bin-state" style="color:var(--text-faint);">↑ Select an entity above</div>
        </div>
    </div>

<?php if ($initShotId || $initSceneId): ?>
    <!-- ── Floating Video Modal (draggable + resizable) ─── -->
    <div class="fv-modal" id="fvModal" style="display:none;">
        <div class="fv-titlebar" id="fvTitlebar">
            <span class="fv-title"><i class="bi bi-pip" style="margin-right:5px;color:var(--amber);"></i>Shot Video</span>
            <div class="fv-titlebar-btns">
                <button class="fv-btn" onclick="dockVideoBack()" title="Dock back above header"><i class="bi bi-arrow-bar-up"></i></button>
                <button class="fv-btn" onclick="closeFvModal()" title="Close"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
        <div class="fv-body" id="fvBody">
            <!-- Video element is moved here by JS when floating -->
        </div>
        <div class="fv-resize-handle" id="fvResizeHandle" title="Drag to resize"></div>
    </div>
<?php endif; ?>

    <!-- ════════════════════════════════════════════════════
         PROJECT PARAM MODAL
    ════════════════════════════════════════════════════ -->
    <div class="param-backdrop" id="paramBackdrop" onclick="onBackdropClick(event)">
        <div class="param-modal" id="paramModal">

            <div class="pm-header">
                <i class="bi bi-sliders2" style="color:var(--amber);font-size:1rem;"></i>
                <div class="pm-title" id="pmTitle">Project Settings</div>
                <button class="pm-close" onclick="closeParamModal()"><i class="bi bi-x-lg"></i></button>
            </div>

            <div class="pm-body" id="pmBody">

                <div class="pm-row">
                    <div style="flex:1;">
                        <div class="pm-label">Tempo</div>
                        <div class="pm-sublabel">Beats per minute</div>
                    </div>
                    <div class="pm-spinner">
                        <button class="pm-spin-btn" onclick="spinBpm(-1)">−</button>
                        <input  class="pm-spin-val" id="pmBpm" type="number" min="20" max="300" value="120" onchange="clampBpm()">
                        <button class="pm-spin-btn" onclick="spinBpm(1)">+</button>
                    </div>
                    <span style="font-size:.7rem;color:var(--text-dim);margin-left:4px;">BPM</span>
                </div>

                <div class="pm-divider"></div>

                <div class="pm-row">
                    <div style="flex:1;">
                        <div class="pm-label">Time Signature</div>
                        <div class="pm-sublabel">Beats per bar / note value</div>
                    </div>
                    <div class="pm-spinner">
                        <button class="pm-spin-btn" onclick="spinSigNum(-1)">−</button>
                        <input  class="pm-spin-val" id="pmSigNum" type="number" min="1" max="16" value="4" style="min-width:40px;" onchange="clampSig()">
                        <button class="pm-spin-btn" onclick="spinSigNum(1)">+</button>
                    </div>
                    <span style="font-size:1.1rem;color:var(--text-dim);margin:0 4px;">/</span>
                    <select class="pm-select" id="pmSigDen" style="flex:none;width:68px;">
                        <option value="2">2</option>
                        <option value="4" selected>4</option>
                        <option value="8">8</option>
                        <option value="16">16</option>
                    </select>
                </div>

                <div class="pm-divider"></div>

                <div class="pm-row">
                    <div style="flex:1;">
                        <div class="pm-label">Grid Division</div>
                        <div class="pm-sublabel">Smallest visible grid unit</div>
                    </div>
                    <select class="pm-select" id="pmGridDiv" style="width:90px;flex:none;">
                        <option value="1">1/1</option>
                        <option value="2">1/2</option>
                        <option value="4" selected>1/4</option>
                        <option value="8">1/8</option>
                        <option value="16">1/16</option>
                        <option value="32">1/32</option>
                    </select>
                </div>

                <div class="pm-divider"></div>

                <div class="pm-row">
                    <div style="flex:1;">
                        <div class="pm-label">Show Grid</div>
                        <div class="pm-sublabel">Draw beat lines on tracks</div>
                    </div>
                    <label class="pm-toggle" id="pmGridVisibleToggle">
                        <input type="checkbox" id="pmGridVisible" checked>
                        <span class="pm-toggle-track"></span>
                        <span class="pm-toggle-thumb"></span>
                    </label>
                </div>

                <div class="pm-row">
                    <div style="flex:1;">
                        <div class="pm-label">Snap to Grid</div>
                        <div class="pm-sublabel">Quantise clip positions</div>
                    </div>
                    <label class="pm-toggle">
                        <input type="checkbox" id="pmSnapEnabled">
                        <span class="pm-toggle-track"></span>
                        <span class="pm-toggle-thumb"></span>
                    </label>
                </div>

                <div class="pm-divider"></div>

                <div class="pm-row-stack">
                    <div class="pm-label">Grid Colour</div>
                    <div class="pm-swatch-row" id="pmSwatchRow"></div>
                </div>

                <div class="pm-row">
                    <div style="flex:1;">
                        <div class="pm-label">Grid Opacity</div>
                    </div>
                    <input type="range" id="pmGridOpacity" min="5" max="80" value="15"
                        style="-webkit-appearance:none;width:120px;height:3px;background:var(--border2);border-radius:2px;outline:none;cursor:pointer;"
                        oninput="document.getElementById('pmOpacityVal').textContent=this.value+'%'">
                    <span id="pmOpacityVal" style="font-size:.72rem;color:var(--amber);min-width:36px;text-align:right;">15%</span>
                </div>

            </div><!-- /pm-body -->

            <div class="pm-footer">
                <button class="pm-btn pm-btn-cancel" onclick="closeParamModal()">Cancel</button>
                <button class="pm-btn pm-btn-apply"  onclick="applyProjectSettings()"><i class="bi bi-check2"></i> Apply</button>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════
         SAVE / LOAD MODAL — merged shot saves + projects
    ════════════════════════════════════════════════════ -->
    <div class="param-backdrop" id="fileBackdrop" onclick="onFileBackdropClick(event)">
        <div class="param-modal" id="fileModal">

            <div class="pm-header">
                <i class="bi bi-folder2-open" style="color:var(--amber);font-size:1rem;"></i>
                <div class="pm-title">Save / Load</div>
                <button class="pm-close" onclick="closeSaveLoadModal()"><i class="bi bi-x-lg"></i></button>
            </div>

            <div class="pm-body" style="gap:0;padding:0;overflow-y:auto;">

<?php if ($initShotId || $initSceneId): ?>
                <!-- ── Shot Saves section (shot/scene mode only) ── -->
                <div class="sl-section sl-section-shot">
                    <div class="sl-section-header">
                        <i class="bi bi-camera-reels"></i>
                        Shot #<?php echo $initShotId; ?> — Saves
                    </div>
                    <div style="padding:10px 14px;display:flex;gap:6px;">
                        <input type="text" id="shotSaveName" class="pm-select" placeholder="Save name…" style="flex:1;">
                        <button class="pm-btn pm-btn-apply" onclick="saveShotDaw()">
                            <i class="bi bi-floppy"></i> Save
                        </button>
                    </div>
                    <div id="shotSavesList" class="sl-list"></div>
                </div>

                <!-- ── Visual divider between the two sections ── -->
                <div class="sl-section-divider">
                    <span>Freestanding Projects</span>
                </div>
<?php endif; ?>

                <!-- ── Freestanding Projects section ── -->
                <div class="sl-section">
                    <div style="padding:10px 14px;display:flex;flex-direction:column;gap:8px;">
                        <div class="pm-label">Project Folder</div>
                        <select class="pm-select" id="fileProjectSelect" onchange="loadProjectFilesList()" style="width:100%;">
                            <option value="">-- Select Project --</option>
                        </select>
                        <div style="display:flex;gap:6px;margin-top:2px;">
                            <input type="text" id="newProjectName" class="pm-select" placeholder="New project name…" style="flex:1;">
                            <button class="pm-btn pm-btn-apply" onclick="createNewProject()">Create</button>
                        </div>
                    </div>
                    <div id="fileList" class="sl-list">
                        <div style="padding:10px;color:var(--text-faint);text-align:center;font-size:.72rem;">Select a project above</div>
                    </div>
                    <div style="padding:8px 14px;border-top:1px solid var(--border);display:flex;gap:6px;">
                        <input type="text" id="newFileName" class="pm-select" placeholder="New save file name…" style="flex:1;">
                        <button class="pm-btn pm-btn-apply" onclick="saveCurrentProjectFile()"><i class="bi bi-floppy"></i> Save</button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════
         MASTER CHANNEL MODAL
    ════════════════════════════════════════════════════ -->
    <div class="master-backdrop" id="masterBackdrop" onclick="onMasterBackdropClick(event)">
        <div class="master-modal">

            <div class="pm-header">
                <i class="bi bi-broadcast-pin" style="color:var(--amber);font-size:1rem;"></i>
                <div class="pm-title">Master Channel</div>
                <button class="pm-close" onclick="closeMasterModal()"><i class="bi bi-x-lg"></i></button>
            </div>

            <div class="mc-body">
                <div class="mc-left">
                    <div class="mc-vu-label">IN</div>
                    <div class="mc-vu-wrap">
                        <canvas id="masterVuCanvasIn"></canvas>
                    </div>
                    <div class="mc-vol-section">
                        <div class="mc-vol-label-top">Vol</div>
                        <input  type="range" class="mc-vol-slider" id="masterVolSlider"
                                min="0" max="1" step="0.01" value="1"
                                oninput="setMasterVol(this.value)">
                        <div class="mc-vol-val" id="masterVolLabel">100%</div>
                    </div>
                </div>
                <div class="mc-center">
                    <div class="mc-plugin-area" id="masterPluginArea">
                        <div class="mc-plugin-empty">
                            <i class="bi bi-plug" style="font-size:2rem;opacity:.2;"></i>
                            <div style="margin-top:10px;font-size:.75rem;color:var(--text-faint);">Select or add a plugin from the FX chain below</div>
                        </div>
                    </div>
                    <div class="mc-fx-strip" style="position:relative;">
                        <div class="mc-fx-label">FX Chain</div>
                        <div class="mc-fx-chain" id="masterFxChain"></div>
                        <div class="mc-plugin-picker" id="mcPluginPicker" style="display:none;"></div>
                    </div>
                </div>
                <div class="mc-right-vu">
                    <div class="mc-vu-label">OUT</div>
                    <div class="mc-vu-wrap">
                        <canvas id="masterVuCanvasOut"></canvas>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ════════════════════════════════════════════════════
         TRACK FX MODAL
    ════════════════════════════════════════════════════ -->
    <div class="master-backdrop" id="trackFxBackdrop" onclick="onTrackFxBackdropClick(event)">
        <div class="master-modal">

            <div class="pm-header">
                <i class="bi bi-sliders" style="color:var(--teal);font-size:1rem;"></i>
                <div class="pm-title" id="trackFxTitle">Track FX</div>
                <button class="pm-close" onclick="closeTrackFxModal()"><i class="bi bi-x-lg"></i></button>
            </div>

            <div class="mc-body">
                <div class="mc-left" style="justify-content:center;">
                    <div class="mc-vol-section">
                        <div class="mc-vol-label-top">Vol</div>
                        <input  type="range" class="mc-vol-slider" id="trackVolSlider"
                                min="0" max="1" step="0.01" value="1"
                                oninput="setTrackFxVol(this.value)">
                        <div class="mc-vol-val" id="trackVolLabel">100%</div>
                    </div>
                </div>
                <div class="mc-center">
                    <div class="mc-plugin-area" id="trackPluginArea"></div>
                    <div class="mc-fx-strip" style="position:relative;">
                        <div class="mc-fx-label">FX Chain</div>
                        <div class="mc-fx-chain" id="trackFxChain"></div>
                        <div class="mc-plugin-picker" id="trackPluginPicker" style="display:none;"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <!-- ════════════════════════════════════════════════════
         BOUNCE PROGRESS MODAL
    ════════════════════════════════════════════════════ -->
    <div class="param-backdrop" id="bounceBackdrop">
        <div class="param-modal" id="bounceModal" style="max-width:360px;">
            <div class="pm-header">
                <i class="bi bi-file-earmark-music" style="color:var(--teal);font-size:1rem;"></i>
                <div class="pm-title">Bouncing Mixdown…</div>
            </div>
            <div class="pm-body" style="align-items:center;gap:18px;padding:24px 16px;">
                <div class="bounce-spinner"></div>
                <div id="bounceStatusMsg" style="font-size:.75rem;color:var(--text-dim);text-align:center;line-height:1.5;">
                    Preparing assets…
                </div>
                <button class="pm-btn pm-btn-cancel" onclick="cancelBounce()" id="btnCancelBounce">
                    Cancel
                </button>
            </div>
        </div>
    </div>

</div><!-- /.daw-shell -->

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');




