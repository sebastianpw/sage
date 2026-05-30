<?php
// src/UI/Modules/ImageEditorModule.php
namespace App\UI\Modules;

/**
 * ImageEditorModule
 *
 * Tabs: Crop | Mask | Transform | Grade | Presets
 *
 * PRESERVED exactly:
 *   - Crop tab   (Cropper.js, aspect ratio, coords, Apply Crop, Remove BG)
 *   - Mask tab   (Fabric.js, Add Box, opacity toggle, Apply Masks)
 *   - Transform  (Rotate, Resize, Outpaint/Green Screen)
 *
 * REPLACED:
 *   - Adjust tab  → Grade tab (full canvas-based color grader)
 *   - Presets tab → merged into Grade tab as "Film Looks" section
 *
 * Grade tab features:
 *   - Live Canvas2D preview (pixel-exact, matches Pillow math)
 *   - Exposure: Brightness, Highlights, Shadows, Whites, Blacks
 *   - Color:    Temperature, Tint, Saturation, Vibrance, Hue Rotate
 *   - Tone:     Contrast, Gamma, Lift, Gain
 *   - Creative: Vignette, Grain
 *   - Curves:   RGB master + per-channel R/G/B
 *   - Film Looks presets (replaces old Presets tab)
 *   - Save / Load named grade profiles
 */
class ImageEditorModule
{
    private array $config     = [];
    private bool  $includeCSS = true;
    private bool  $includeJS  = true;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'api_endpoint'       => '/image_editor_api.php',
            'save_endpoint'      => '/save_final_image_edit.php',
            'grade_api_endpoint' => '/color_grade_api.php',
            'show_transform_tab' => true,
            'enable_rotate'      => true,
            'enable_resize'      => true,
            // preset_filters kept for BC but rendered inside Grade tab
            'preset_filters'     => [
                'grayscale', 'vintage', 'sepia', 'clarendon',
                'gingham', 'moon', 'lark', 'reyes', 'juno', 'slumber',
            ],
        ], $config);
    }

    public function render(): string
    {
        $html = '';
        if ($this->includeCSS) $html .= $this->renderCSS();
        $html .= $this->renderHTML();
        if ($this->includeJS)  $html .= $this->renderJS();
        return $html;
    }

    // ── HTML ─────────────────────────────────────────────────────────────

    private function renderHTML(): string
    {
        $transformTab = $this->config['show_transform_tab']
            ? '<button class="ie-tab" data-tab="transform">Transform</button>'
            : '';

        return <<<HTML
<div id="imageEditorModal" class="ie-modal" style="display: none;">
    <div class="ie-modal-overlay"></div>
    <div class="ie-modal-container">

        <!-- Loading Overlay -->
        <div id="ieLoadingOverlay" class="ie-loading-overlay" style="display: none;">
            <div class="ie-loading-spinner"></div>
            <p>Processing image, please wait...</p>
            <button id="ieCancelActionBtn" class="ie-btn ie-btn-secondary">Cancel</button>
        </div>

        <div class="ie-modal-header">
            <h2 class="ie-modal-title">Image Editor</h2>
            <button class="ie-close-btn" title="Close">×</button>
        </div>

        <div class="ie-modal-body">
            <div class="ie-editor-layout">

                <!-- Canvas area -->
                <div class="ie-canvas-wrapper">
                    <!-- Standard img used by Cropper.js -->
                    <img id="ieCanvas" class="ie-canvas-img" src="" alt="Edit canvas">
                    <!-- Fabric.js wrapper (Mask tab) -->
                    <div id="ieFabricWrapper" style="display:none; width:100%; height:100%; justify-content:center; align-items:center;">
                        <canvas id="ieFabricCanvas"></canvas>
                    </div>
                    <!-- Grade preview canvas -->
                    <canvas id="ieGradeCanvas" style="display:none; max-width:100%; max-height:60vh;"></canvas>
                </div>

                <!-- Tools panel -->
                <div class="ie-tools-panel">
                    <div class="ie-tabs">
                        <button class="ie-tab active" data-tab="crop">Crop</button>
                        <button class="ie-tab" data-tab="mask">Mask</button>
                        {$transformTab}
                        <button class="ie-tab" data-tab="grade">Grade</button>
                    </div>

                    <!-- ── TAB: CROP (UNCHANGED) ── -->
                    <div class="ie-tab-content active" data-tab-content="crop">
                        <div class="ie-tool-group">
                            <label><strong>Crop Mode</strong></label>
                            <input type="hidden" id="ieEditMode" value="crop">
                            <p style="color:#888; font-size:12px; margin-top:5px;">Drag handles on image to crop.</p>
                        </div>
                        <div class="ie-tool-group">
                            <label>Aspect Ratio</label>
                            <select id="ieAspectRatio" class="ie-select">
                                <option value="free">Free</option>
                                <option value="1">1:1</option>
                                <option value="1.777">16:9</option>
                                <option value="0.75">3:4</option>
                            </select>
                        </div>
                        <div class="ie-coords-display" id="ieCoordsDisplay">X: 0, Y: 0<br>W: 0, H: 0</div>
                        <button class="ie-btn ie-btn-primary" id="ieApplyCrop">Apply Crop</button>
                        <hr style="border:0; border-top:1px solid #333; margin: 15px 0;">
                        <div class="ie-tool-group">
                            <label><strong>AI Tools</strong></label>
                            <button class="ie-btn ie-btn-secondary" id="ieRemoveBgBtn">Remove Background</button>
                        </div>
                    </div>

                    <!-- ── TAB: MASK (UNCHANGED) ── -->
                    <div class="ie-tab-content" data-tab-content="mask">
                        <div class="ie-tool-group">
                            <label><strong>Add Masks</strong></label>
                            <p style="color:#888; font-size:12px;">Add green boxes to mask areas.</p>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                            <button class="ie-btn ie-btn-secondary" id="ieAddMaskBox" style="flex:1; margin-bottom:0;">
                                <span style="font-size:16px;">+</span> Add Box
                            </button>
                            <label style="font-size:12px; cursor:pointer; display:flex; align-items:center; gap:4px;">
                                <input type="checkbox" id="ieMaskOpacity" checked> Render Opacity
                            </label>
                        </div>
                        <div class="ie-tool-group">
                            <label style="font-size:11px; color:#666;">Use controls on boxes to clone/delete.</label>
                        </div>
                        <hr style="border:0; border-top:1px solid #333; margin: 15px 0;">
                        <button class="ie-btn ie-btn-primary" id="ieApplyMask">Apply Masks</button>
                    </div>

                    <!-- ── TAB: TRANSFORM (UNCHANGED) ── -->
                    {$this->renderTransformTabContent()}

                    <!-- ── TAB: GRADE (NEW) ── -->
                    {$this->renderGradeTabContent()}

                </div><!-- /ie-tools-panel -->
            </div><!-- /ie-editor-layout -->
        </div><!-- /ie-modal-body -->

        <div class="ie-modal-footer">
            <button class="ie-btn ie-btn-secondary" id="ieUndoBtn" disabled>Undo</button>
            <button class="ie-btn ie-btn-primary"   id="ieSaveBtn" disabled>Save</button>
            <button class="ie-btn ie-btn-secondary" id="ieCloseBtn">Close</button>
        </div>
    </div>
</div>
HTML;
    }

    // ── Transform tab (completely unchanged from original) ────────────────

    private function renderTransformTabContent(): string
    {
        if (!$this->config['show_transform_tab']) return '';

        $rotateSection = '';
        if ($this->config['enable_rotate']) {
            $rotateSection = <<<HTML
<div class="ie-tool-group">
    <label>Rotate (degrees)</label>
    <input type="number" id="ieRotateAngle" class="ie-input" value="0" min="-360" max="360">
</div>
<button class="ie-btn ie-btn-primary" id="ieApplyRotate">Apply Rotation</button>
HTML;
        }

        $resizeSection = '';
        if ($this->config['enable_resize']) {
            $resizeSection = <<<HTML
<div class="ie-tool-group" style="margin-top: 20px;">
    <label><strong>Resize</strong></label>
    <label>Width (px)</label>
    <input type="number" id="ieResizeWidth" class="ie-input" value="1024">
</div>
<div class="ie-tool-group">
    <label>Height (px)</label>
    <input type="number" id="ieResizeHeight" class="ie-input" value="1024">
</div>
<div class="ie-tool-group">
    <label><input type="checkbox" id="ieMaintainAspect" checked> Maintain aspect</label>
</div>
<button class="ie-btn ie-btn-primary" id="ieApplyResize">Apply Resize</button>
HTML;
        }

        $outpaintSection = <<<HTML
<div class="ie-tool-group" style="margin-top: 20px; border-top: 1px solid #444; padding-top: 15px;">
    <label><strong>Outpaint / Green Screen</strong></label>
    <div style="display:flex; gap:5px; margin-bottom:5px;">
        <div style="flex:1">
            <label>Canvas Width</label>
            <input type="number" id="ieOutpaintWidth" class="ie-input" placeholder="W" value="1024">
        </div>
        <div style="flex:1">
            <label>Canvas Height</label>
            <input type="number" id="ieOutpaintHeight" class="ie-input" placeholder="Square if empty">
        </div>
    </div>
    <div style="display:flex; gap:5px; margin-bottom:5px;">
        <div style="flex:1">
            <label>X (Left)</label>
            <input type="number" id="ieOutpaintX" class="ie-input" placeholder="Center">
        </div>
        <div style="flex:1">
            <label>Y (Top)</label>
            <input type="number" id="ieOutpaintY" class="ie-input" placeholder="Center">
        </div>
    </div>
    <div class="ie-tool-group">
        <label>Background Color (Hex)</label>
        <div style="display:flex; gap:5px;">
            <input type="color" id="ieOutpaintColorPicker" value="#00FF00" style="width:40px; height:30px; border:none; padding:0; cursor:pointer;">
            <input type="text" id="ieOutpaintColorText" class="ie-input" value="#00FF00" style="flex:1;">
        </div>
    </div>
    <button class="ie-btn ie-btn-primary" id="ieApplyOutpaint">Apply Outpaint</button>
</div>
HTML;

        return <<<HTML
<div class="ie-tab-content" data-tab-content="transform">
    {$rotateSection}
    {$resizeSection}
    {$outpaintSection}
</div>
HTML;
    }

    // ── Grade tab ─────────────────────────────────────────────────────────

    private function renderGradeTabContent(): string
    {
        $filmLooks = $this->renderFilmLooks();

        return <<<HTML
<div class="ie-tab-content" data-tab-content="grade">

    <!-- Sub-nav for grade sections -->
    <div class="ig-subnav">
        <button class="ig-subbtn active" data-section="exposure">Exposure</button>
        <button class="ig-subbtn" data-section="color">Color</button>
        <button class="ig-subbtn" data-section="tone">Tone</button>
        <button class="ig-subbtn" data-section="creative">Creative</button>
        <button class="ig-subbtn" data-section="curves">Curves</button>
        <button class="ig-subbtn" data-section="looks">Looks</button>
        <button class="ig-subbtn" data-section="profiles">Profiles</button>
    </div>

    <!-- ── EXPOSURE ── -->
    <div class="ig-section active" data-section="exposure">
        {$this->gradeSlider('Brightness',  'igBrightness',  -100, 100, 0)}
        {$this->gradeSlider('Highlights',  'igHighlights',  -100, 100, 0)}
        {$this->gradeSlider('Shadows',     'igShadows',     -100, 100, 0)}
        {$this->gradeSlider('Whites',      'igWhites',      -100, 100, 0)}
        {$this->gradeSlider('Blacks',      'igBlacks',      -100, 100, 0)}
    </div>

    <!-- ── COLOR ── -->
    <div class="ig-section" data-section="color">
        {$this->gradeSlider('Temperature', 'igTemperature', -100, 100, 0)}
        {$this->gradeSlider('Tint',        'igTint',        -100, 100, 0)}
        {$this->gradeSlider('Saturation',  'igSaturation',  -100, 100, 0)}
        {$this->gradeSlider('Vibrance',    'igVibrance',    -100, 100, 0)}
        {$this->gradeSlider('Hue Rotate',  'igHueRotate',   -180, 180, 0)}
    </div>

    <!-- ── TONE ── -->
    <div class="ig-section" data-section="tone">
        {$this->gradeSlider('Contrast',    'igContrast',    -100, 100, 0)}
        {$this->gradeSlider('Gamma',       'igGamma',       -100, 100, 0)}
        {$this->gradeSlider('Lift',        'igLift',        -100, 100, 0)}
        {$this->gradeSlider('Gain',        'igGain',        -100, 100, 0)}
    </div>

    <!-- ── CREATIVE ── -->
    <div class="ig-section" data-section="creative">
        {$this->gradeSlider('Vignette',    'igVignette',    0,    100,  0)}
        {$this->gradeSlider('Grain',       'igGrain',       0,    100,  0)}
        {$this->gradeSlider('Blur',        'igBlur',        0,    20,   0, 0.5)}
        {$this->gradeSlider('Sharpen',     'igSharpen',     0,    300,  0)}
    </div>

    <!-- ── CURVES ── -->
    <div class="ig-section" data-section="curves">
        <div class="ig-curves-wrap">
            <div class="ig-curves-header">
                <button class="ig-curve-chan active" data-chan="rgb">RGB</button>
                <button class="ig-curve-chan" data-chan="r" style="color:#f55;">R</button>
                <button class="ig-curve-chan" data-chan="g" style="color:#5f5;">G</button>
                <button class="ig-curve-chan" data-chan="b" style="color:#58f;">B</button>
                <button class="ig-curve-reset" id="igCurveReset" title="Reset active curve">↺</button>
            </div>
            <canvas id="igCurveCanvas" width="240" height="240" class="ig-curve-canvas"></canvas>
            <p class="ig-curve-hint">Click to add points · Drag to adjust · Right-click to remove</p>
        </div>
    </div>

    <!-- ── FILM LOOKS ── -->
    <div class="ig-section" data-section="looks">
        <p style="color:#888; font-size:11px; margin-bottom:8px;">Applies preset grade settings. Combines with current adjustments.</p>
        {$filmLooks}
    </div>

    <!-- ── PROFILES ── -->
    <div class="ig-section" data-section="profiles">
        <div class="ig-profile-save-row">
            <input type="text" id="igProfileName" class="ie-input" placeholder="Profile name…" style="flex:1;">
            <button class="ie-btn ie-btn-primary" id="igSaveProfileBtn" style="width:auto; margin:0; padding:7px 12px; font-size:12px;">Save</button>
        </div>
        <div id="igProfileList" class="ig-profile-list">
            <div class="ig-profile-empty">No saved profiles yet.</div>
        </div>
    </div>

    <!-- Footer: reset + apply grade -->
    <div class="ig-grade-footer">
        <button class="ie-btn ie-btn-secondary" id="igResetAllBtn" style="width:auto; margin:0; flex:1;">Reset All</button>
        <button class="ie-btn ie-btn-primary"   id="igApplyGradeBtn" style="width:auto; margin:0; flex:2;">Apply Grade</button>
    </div>

</div><!-- /grade tab -->
HTML;
    }

    private function gradeSlider(string $label, string $id, int $min, int $max, int $val, float $step = 1): string
    {
        return <<<HTML
<div class="ie-tool-group">
    <div class="range-row">
        <div class="range-label">{$label}</div>
        <input type="range" id="{$id}" class="ig-slider" min="{$min}" max="{$max}" step="{$step}" value="{$val}" data-default="{$val}">
        <div class="range-value" id="{$id}Val">{$val}</div>
    </div>
</div>
HTML;
    }

    private function renderFilmLooks(): string
    {
        $looks = [
            ['Teal & Orange',  '{"temperature":-20,"tint":5,"saturation":15,"lift":-5}'],
            ['Bleach Bypass',  '{"saturation":-60,"contrast":40,"brightness":-5}'],
            ['Cross Process',  '{"temperature":30,"tint":-20,"saturation":25,"contrast":20}'],
            ['Faded Film',     '{"brightness":8,"saturation":-25,"lift":12,"blacks":15}'],
            ['Cold Day',       '{"temperature":-45,"tint":8,"shadows":-10,"highlights":5}'],
            ['Golden Hour',    '{"temperature":60,"tint":-5,"saturation":20,"highlights":10}'],
            ['Noir',           '{"saturation":-100,"contrast":30,"brightness":-5}'],
            ['Vintage 70s',    '{"temperature":35,"saturation":-15,"lift":8,"grain":25}'],
            ['Day for Night',  '{"temperature":-60,"brightness":-30,"saturation":-20}'],
            ['Kodachrome',     '{"temperature":15,"saturation":30,"contrast":15,"shadows":-5}'],
        ];

        $buttons = '';
        foreach ($looks as [$label, $json]) {
            $escaped = htmlspecialchars($json, ENT_QUOTES);
            $buttons .= "<button class=\"ig-look-btn\" data-look='{$escaped}'>{$label}</button>\n";
        }

        return "<div class=\"ig-looks-grid\">{$buttons}</div>";
    }

    // ── CSS ───────────────────────────────────────────────────────────────

    private function renderCSS(): string
    {
        $cropperCSS = \App\Core\SpwBase::CDN_USAGE
            ? '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" />'
            : '<link rel="stylesheet" href="/vendor/cropper/cropper.min.css" />';

        return $cropperCSS . <<<'CSS'
<style id="ie-modal-styles">
/* ── FORGE DESIGN SYSTEM VARS (matches generator_forge aesthetic) ── */
.ie-modal {
    --ig-bg:        #080b10;
    --ig-surface:   #0e1319;
    --ig-card:      #111820;
    --ig-border:    #1c2535;
    --ig-amber:     #f5a623;
    --ig-amber-dim: rgba(245,166,35,0.10);
    --ig-amber-glow:rgba(245,166,35,0.40);
    --ig-green:     #22d3a0;
    --ig-red:       #f05060;
    --ig-blue:      #4da6ff;
    --ig-text:      #c8d4e8;
    --ig-muted:     #5a6a80;
    --ig-mono:      'Space Mono', 'Fira Mono', monospace;
    --ig-sans:      'Syne', system-ui, sans-serif;
}

/* ── MODAL SHELL ── */
.ie-modal{position:fixed;top:0;left:0;right:0;bottom:0;z-index:99999999;display:flex;align-items:center;justify-content:center}
.ie-modal-overlay{position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.88);backdrop-filter:blur(3px)}
.ie-modal-container{
    position:relative;background:var(--ig-bg);border:1px solid var(--ig-border);
    border-radius:10px;width:95%;max-width:1400px;max-height:90vh;
    display:flex;flex-direction:column;
    box-shadow:0 20px 60px rgba(0,0,0,.7),0 0 0 1px rgba(245,166,35,.06);
    font-family:var(--ig-sans);
}
.ie-modal-header{
    padding:12px 18px;border-bottom:1px solid var(--ig-border);
    display:flex;justify-content:space-between;align-items:center;
    background:var(--ig-surface);border-radius:10px 10px 0 0;
}
.ie-modal-title{margin:0;color:var(--ig-amber);font-family:var(--ig-mono);font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase}
.ie-close-btn{background:none;border:1px solid var(--ig-border);color:var(--ig-muted);font-size:20px;cursor:pointer;padding:0;width:28px;height:28px;line-height:1;border-radius:4px;display:flex;align-items:center;justify-content:center;transition:all .15s}
.ie-close-btn:hover{border-color:var(--ig-red);color:var(--ig-red)}
.ie-modal-body{flex:1;overflow:hidden;padding:14px}
.ie-editor-layout{display:grid;grid-template-columns:1fr 300px;gap:14px;height:100%}
.ie-canvas-wrapper{background:var(--ig-card);border:1px solid var(--ig-border);border-radius:8px;display:flex;align-items:center;justify-content:center;overflow:hidden;min-height:360px;position:relative}
.ie-canvas-img{max-width:100%;max-height:60vh;display:block}
.ie-tools-panel{background:var(--ig-surface);border:1px solid var(--ig-border);border-radius:8px;padding:12px;overflow-y:auto;display:flex;flex-direction:column;gap:0}

/* ── TABS ── */
.ie-tabs{display:flex;gap:4px;margin-bottom:12px;border-bottom:1px solid var(--ig-border);padding-bottom:6px;flex-wrap:wrap}
.ie-tab{background:none;border:none;color:var(--ig-muted);padding:6px 10px;cursor:pointer;border-radius:4px;font-family:var(--ig-mono);font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;transition:all .15s}
.ie-tab:hover{color:var(--ig-amber);background:var(--ig-amber-dim)}
.ie-tab.active{color:var(--ig-amber);background:var(--ig-amber-dim);border-bottom:2px solid var(--ig-amber)}
.ie-tab-content{display:none}
.ie-tab-content.active{display:block}

/* ── TOOL GROUPS / FORM ELEMENTS ── */
.ie-tool-group{margin-bottom:8px}
.ie-tool-group label{display:block;color:var(--ig-muted);margin-bottom:4px;font-size:11px;font-family:var(--ig-mono);text-transform:uppercase;letter-spacing:.8px}
.ie-select,.ie-input,.ie-tool-group input[type=number],.ie-tool-group input[type=range],.ie-tool-group input[type=text]{
    width:100%;padding:6px 8px;background:var(--ig-card);border:1px solid var(--ig-border);
    border-radius:4px;color:var(--ig-text);font-size:12px;font-family:var(--ig-mono);
    transition:border-color .15s}
.ie-select:focus,.ie-input:focus,.ie-tool-group input:focus{outline:none;border-color:var(--ig-amber)}

/* ── RANGE ROWS ── */
.range-row{display:flex;align-items:center;gap:6px}
.range-row .range-label{min-width:68px;flex:0 0 auto;font-size:11px;color:var(--ig-muted);font-family:var(--ig-mono)}
.range-row input[type=range]{flex:1;margin:0;height:22px;cursor:pointer;accent-color:var(--ig-amber)}
.range-row .range-value{min-width:36px;text-align:right;font-size:11px;color:var(--ig-amber);font-family:var(--ig-mono)}

/* ── BUTTONS ── */
.ie-btn{padding:8px 12px;border:none;border-radius:4px;cursor:pointer;font-size:12px;font-family:var(--ig-mono);font-weight:700;letter-spacing:.5px;transition:all .15s;width:100%;margin-bottom:8px}
.ie-btn-primary{background:var(--ig-amber);color:#000}
.ie-btn-primary:hover:not(:disabled){filter:brightness(1.15);transform:translateY(-1px)}
.ie-btn-primary:disabled{background:#333;color:#555;cursor:not-allowed}
.ie-btn-secondary{background:var(--ig-card);border:1px solid var(--ig-border);color:var(--ig-text)}
.ie-btn-secondary:hover:not(:disabled){border-color:var(--ig-amber);color:var(--ig-amber)}
.ie-btn-secondary:disabled{opacity:.4;cursor:not-allowed}
.ie-coords-display{background:var(--ig-card);border:1px solid var(--ig-border);padding:7px;border-radius:4px;font-family:var(--ig-mono);font-size:11px;color:var(--ig-amber);margin-bottom:10px}

/* ── FOOTER ── */
.ie-modal-footer{padding:10px 16px;border-top:1px solid var(--ig-border);display:flex;gap:8px;justify-content:flex-end;background:var(--ig-surface);border-radius:0 0 10px 10px}
.ie-modal-footer .ie-btn{width:auto;margin:0;min-width:80px}

/* ── LOADING OVERLAY ── */
.ie-loading-overlay{position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(8,11,16,.92);z-index:100;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--ig-text);border-radius:10px}
.ie-loading-spinner{border:3px solid var(--ig-border);border-top:3px solid var(--ig-amber);border-radius:50%;width:40px;height:40px;animation:ie-spin 0.9s linear infinite;margin-bottom:16px}
.ie-loading-overlay p{margin:0 0 12px;font-family:var(--ig-mono);font-size:12px;color:var(--ig-muted)}
.ie-loading-overlay .ie-btn{width:auto;min-width:100px}
@keyframes ie-spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}

/* ── GRADE TAB: SUBNAV ── */
.ig-subnav{display:flex;gap:3px;flex-wrap:wrap;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--ig-border)}
.ig-subbtn{background:none;border:1px solid transparent;color:var(--ig-muted);padding:4px 8px;border-radius:3px;cursor:pointer;font-family:var(--ig-mono);font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;transition:all .15s}
.ig-subbtn:hover{color:var(--ig-text);border-color:var(--ig-border)}
.ig-subbtn.active{color:var(--ig-amber);border-color:var(--ig-amber);background:var(--ig-amber-dim)}
.ig-section{display:none}
.ig-section.active{display:block}

/* Slider double-click reset hint */
.ig-slider{cursor:pointer}
.ig-slider:active{accent-color:var(--ig-green)}

/* ── CURVES ── */
.ig-curves-wrap{display:flex;flex-direction:column;align-items:center;gap:8px}
.ig-curves-header{display:flex;gap:6px;align-items:center;width:100%;margin-bottom:4px}
.ig-curve-chan{background:none;border:1px solid var(--ig-border);color:var(--ig-muted);padding:3px 8px;border-radius:3px;cursor:pointer;font-family:var(--ig-mono);font-size:11px;font-weight:700;transition:all .15s}
.ig-curve-chan.active{border-color:var(--ig-amber);color:var(--ig-amber);background:var(--ig-amber-dim)}
.ig-curve-reset{margin-left:auto;background:none;border:1px solid var(--ig-border);color:var(--ig-muted);width:26px;height:26px;border-radius:3px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;transition:all .15s}
.ig-curve-reset:hover{border-color:var(--ig-red);color:var(--ig-red)}
.ig-curve-canvas{border:1px solid var(--ig-border);border-radius:4px;cursor:crosshair;background:var(--ig-card);display:block;max-width:100%}
.ig-curve-hint{font-size:10px;color:var(--ig-muted);font-family:var(--ig-mono);text-align:center;margin:0}

/* ── FILM LOOKS ── */
.ig-looks-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.ig-look-btn{padding:7px 8px;background:var(--ig-card);border:1px solid var(--ig-border);border-radius:4px;color:var(--ig-text);font-family:var(--ig-mono);font-size:11px;cursor:pointer;transition:all .15s;text-align:left}
.ig-look-btn:hover{border-color:var(--ig-amber);color:var(--ig-amber);background:var(--ig-amber-dim)}
.ig-look-btn.applied{border-color:var(--ig-green);color:var(--ig-green)}

/* ── PROFILES ── */
.ig-profile-save-row{display:flex;gap:6px;margin-bottom:10px;align-items:center}
.ig-profile-list{display:flex;flex-direction:column;gap:4px;max-height:220px;overflow-y:auto}
.ig-profile-empty{font-family:var(--ig-mono);font-size:11px;color:var(--ig-muted);text-align:center;padding:20px 0}
.ig-profile-row{display:flex;align-items:center;gap:6px;padding:7px 10px;background:var(--ig-card);border:1px solid var(--ig-border);border-radius:4px;cursor:pointer;transition:all .15s}
.ig-profile-row:hover{border-color:var(--ig-amber)}
.ig-profile-name{flex:1;font-family:var(--ig-mono);font-size:11px;color:var(--ig-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ig-profile-apply{background:none;border:1px solid var(--ig-border);color:var(--ig-muted);width:24px;height:24px;border-radius:3px;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0}
.ig-profile-apply:hover{border-color:var(--ig-green);color:var(--ig-green)}
.ig-profile-del{background:none;border:1px solid var(--ig-border);color:var(--ig-muted);width:24px;height:24px;border-radius:3px;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0}
.ig-profile-del:hover{border-color:var(--ig-red);color:var(--ig-red)}

/* ── GRADE FOOTER ── */
.ig-grade-footer{display:flex;gap:6px;margin-top:10px;padding-top:10px;border-top:1px solid var(--ig-border);flex-shrink:0}

/* ── FILTER PRESETS (kept for BC, hidden in Grade tab) ── */
.ie-filter-presets{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}

/* ── RESPONSIVE ── */
@media(max-width:768px){
    .ie-editor-layout{grid-template-columns:1fr}
    .ie-modal-container{width:100%;max-width:100%;border-radius:0;max-height:100vh}
    .ig-curves-wrap canvas{width:100%;height:auto}
}
</style>
CSS;
    }

    // ── JavaScript ────────────────────────────────────────────────────────

    private function renderJS(): string
    {
        $gradeApiEndpoint = $this->config['grade_api_endpoint'];

        $cropperJS = \App\Core\SpwBase::CDN_USAGE
            ? '<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>'
            : '<script src="/vendor/cropper/cropper.min.js"></script>';

        $fabricJS = '<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js" referrerpolicy="no-referrer"></script>';

        // Grade API endpoint injected into JS constant
        $gradeEndpointJS = json_encode($gradeApiEndpoint);

        // We use a regular Heredoc so the variable is interpolated cleanly,
        // then append the rest as a Nowdoc so we don't need to escape any JavaScript syntax!
        $jsVars = <<<JS
<script id="ie-modal-script">
(function() {
    'use strict';
    const GRADE_API = {$gradeEndpointJS};

JS;

        $jsLogic = <<<'JS'
    // ── State ──────────────────────────────────────────────────────────────
    let cropper          = null;
    let fabricCanvas     = null;
    let currentData      = {};
    let abortController  = null;
    let editHistory      = [];
    let operationHistory = [];
    let hasUnsavedChanges = false;
    let currentTempFile  = null;
    let activeTab        = 'crop';

    // Grade state
    let gradeState = getDefaultGradeState();
    let activeCurveChannel = 'rgb'; // 'rgb' | 'r' | 'g' | 'b'
    let curvePoints = { rgb: getDefaultCurve(), r: getDefaultCurve(), g: getDefaultCurve(), b: getDefaultCurve() };
    let gradePreviewTimer = null;

    function getDefaultGradeState() {
        return {
            brightness:  0,
            highlights:  0,
            shadows:     0,
            whites:      0,
            blacks:      0,
            temperature: 0,
            tint:        0,
            saturation:  0,
            vibrance:    0,
            hueRotate:   0,
            contrast:    0,
            gamma:       0,
            lift:        0,
            gain:        0,
            vignette:    0,
            grain:       0,
            blur:        0,
            sharpen:     0,
        };
    }
    function getDefaultCurve() {
        // Diagonal: [inputX 0-255, outputY 0-255]
        return [[0,0],[64,64],[128,128],[192,192],[255,255]];
    }
    function isGradeDefault(state) {
        const def = getDefaultGradeState();
        return Object.keys(def).every(k => state[k] === def[k]);
    }

    // ── Fabric icon data ─────────────────────────────────────────────────
    const deleteIcon = "data:image/svg+xml,%3C%3Fxml version='1.0' encoding='utf-8'%3F%3E%3C!DOCTYPE svg PUBLIC '-//W3C//DTD SVG 1.1//EN' 'http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd'%3E%3Csvg version='1.1' id='Ebene_1' xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink' x='0px' y='0px' width='595.275px' height='595.275px' viewBox='200 215 230 470' xml:space='preserve'%3E%3Ccircle style='fill:%23F44336;' cx='299.76' cy='439.067' r='218.516'/%3E%3Cg%3E%3Crect x='267.162' y='307.978' transform='matrix(0.7071 -0.7071 0.7071 0.7071 -222.6202 340.6915)' style='fill:white;' width='65.545' height='262.18'/%3E%3Crect x='266.988' y='308.153' transform='matrix(0.7071 0.7071 -0.7071 0.7071 398.3889 -83.3116)' style='fill:white;' width='65.544' height='262.179'/%3E%3C/g%3E%3C/svg%3E";
    const cloneIcon  = "data:image/svg+xml,%3C%3Fxml version='1.0' encoding='iso-8859-1'%3F%3E%3Csvg version='1.1' xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink' viewBox='0 0 55.699 55.699' width='100px' height='100px' xml:space='preserve'%3E%3Cpath style='fill:%23010002;' d='M51.51,18.001c-0.006-0.085-0.022-0.167-0.05-0.248c-0.012-0.034-0.02-0.067-0.035-0.1 c-0.049-0.106-0.109-0.206-0.194-0.291v-0.001l0,0c0,0-0.001-0.001-0.001-0.002L34.161,0.293c-0.086-0.087-0.188-0.148-0.295-0.197 c-0.027-0.013-0.057-0.02-0.086-0.03c-0.086-0.029-0.174-0.048-0.265-0.053C33.494,0.011,33.475,0,33.453,0H22.177 c-3.678,0-6.669,2.992-6.669,6.67v1.674h-4.663c-3.678,0-6.67,2.992-6.67,6.67V49.03c0,3.678,2.992,6.669,6.67,6.669h22.677 c3.677,0,6.669-2.991,6.669-6.669v-1.675h4.664c3.678,0,6.669-2.991,6.669-6.669V18.069C51.524,18.045,51.512,18.025,51.51,18.001z M34.454,3.414l13.655,13.655h-8.985c-2.575,0-4.67-2.095-4.67-4.67V3.414z M38.191,49.029c0,2.574-2.095,4.669-4.669,4.669H10.845 c-2.575,0-4.67-2.095-4.67-4.669V15.014c0-2.575,2.095-4.67,4.67-4.67h5.663h4.614v10.399c0,3.678,2.991,6.669,6.668,6.669h10.4 v18.942L38.191,49.029L38.191,49.029z M36.777,25.412h-8.986c-2.574,0-4.668-2.094-4.668-4.669v-8.985L36.777,25.412z M44.855,45.355h-4.664V26.412c0-0.023-0.012-0.044-0.014-0.067c-0.006-0.085-0.021-0.167-0.049-0.249 c-0.012-0.033-0.021-0.066-0.036-0.1c-0.048-0.105-0.109-0.205-0.194-0.29l0,0l0,0c0-0.001-0.001-0.002-0.001-0.002L22.829,8.637 c-0.087-0.086-0.188-0.147-0.295-0.196c-0.029-0.013-0.058-0.021-0.088-0.031c-0.086-0.03-0.172-0.048-0.263-0.053 c-0.021-0.002-0.04-0.013-0.062-0.013h-4.614V6.67c0-2.575,2.095-4.67,4.669-4.67h10.277v10.4c0,3.678,2.992,6.67,6.67,6.67h10.399 v21.616C49.524,43.26,47.429,45.355,44.855,45.355z'/%3E%3C/svg%3E";
    const deleteImg  = document.createElement('img'); deleteImg.src = deleteIcon;
    const cloneImg   = document.createElement('img'); cloneImg.src  = cloneIcon;

    const TEMP_API_ENDPOINT  = '/image_editor_api.php';
    const SAVE_FINAL_ENDPOINT = '/save_final_image_edit.php';

    // ╔═══════════════════════════════════════════════════════════════════╗
    // ║  EXISTING: Crop / Mask / Transform — ALL UNCHANGED              ║
    // ╚═══════════════════════════════════════════════════════════════════╝

    function initCropper() {
        if (activeTab === 'mask') return;
        const canvas = document.getElementById('ieCanvas');
        if (!canvas) return;
        destroyFabric();
        canvas.style.display = 'block';
        document.getElementById('ieFabricWrapper').style.display = 'none';
        document.getElementById('ieGradeCanvas').style.display = 'none';
        if (cropper) cropper.destroy();
        cropper = new Cropper(canvas, {
            viewMode: 1, dragMode: 'move', autoCropArea: 1,
            guides: true, center: true, highlight: true,
            cropBoxMovable: true, cropBoxResizable: true,
            crop: updateCoordsDisplay
        });
    }

    function initFabric() {
        if (activeTab !== 'mask') return;
        if (cropper) { cropper.destroy(); cropper = null; }
        const imgEl  = document.getElementById('ieCanvas');
        const wrapper = document.getElementById('ieFabricWrapper');
        imgEl.style.display   = 'none';
        wrapper.style.display = 'flex';
        document.getElementById('ieGradeCanvas').style.display = 'none';
        const canvasEl = document.getElementById('ieFabricCanvas');
        canvasEl.width  = wrapper.clientWidth;
        canvasEl.height = wrapper.clientHeight;
        if (fabricCanvas) { fabricCanvas.clear(); }
        else {
            fabricCanvas = new fabric.Canvas('ieFabricCanvas', { selection: false, preserveObjectStacking: true });
            fabric.Object.prototype.transparentCorners = false;
            fabric.Object.prototype.cornerColor = 'white';
            fabric.Object.prototype.cornerStyle = 'circle';
        }
        const src = currentTempFile ? '/' + currentTempFile : currentData.originalSrc;
        const srcT = src + (src.indexOf('?') > -1 ? '&' : '?') + 't=' + Date.now();
        fabric.Image.fromURL(srcT, function(img) {
            if (!img) { showToast('Failed to load image for masking', 'error'); return; }
            const wW = wrapper.clientWidth, wH = wrapper.clientHeight;
            const scale = Math.min(wW / img.width, wH / img.height);
            fabricCanvas.setWidth(img.width * scale);
            fabricCanvas.setHeight(img.height * scale);
            img.set({ originX:'left', originY:'top', left:0, top:0, scaleX:scale, scaleY:scale, selectable:false, evented:false, hasControls:false, hasBorders:false });
            fabricCanvas.setBackgroundImage(img, fabricCanvas.renderAll.bind(fabricCanvas));
            fabricCanvas.bgImageScale = scale;
        }, { crossOrigin: 'anonymous' });
    }

    function destroyFabric() {
        if (fabricCanvas) { fabricCanvas.dispose(); fabricCanvas = null; }
    }

    function renderIcon(icon) {
        return function(ctx, left, top, styleOverride, fabricObject) {
            const size = this.cornerSize || 24;
            ctx.save(); ctx.translate(left, top);
            ctx.rotate(fabric.util.degreesToRadians(fabricObject.angle || 0));
            ctx.drawImage(icon, -size/2, -size/2, size, size);
            ctx.restore();
        };
    }
    function deleteObject(_ev, transform) {
        const obj = transform.target, c = obj.canvas;
        if (!c) return; c.remove(obj); c.requestRenderAll();
    }
    function cloneObject(_ev, transform) {
        const obj = transform.target, c = obj.canvas;
        if (!c) return;
        obj.clone(function(cloned) {
            cloned.left += 10; cloned.top += 10;
            addCustomControlsTo(cloned); c.add(cloned); c.setActiveObject(cloned); c.requestRenderAll();
        });
    }
    function addCustomControlsTo(obj) {
        obj.controls = obj.controls || {};
        obj.controls.deleteControl = new fabric.Control({ x:0.5, y:-0.5, offsetY:-16, offsetX:16, cursorStyle:'pointer', mouseUpHandler:deleteObject, render:renderIcon(deleteImg), cornerSize:24 });
        obj.controls.cloneControl  = new fabric.Control({ x:-0.5, y:-0.5, offsetY:-16, offsetX:-16, cursorStyle:'pointer', mouseUpHandler:cloneObject, render:renderIcon(cloneImg), cornerSize:24 });
        obj.set({ borderColor:'transparent', cornerColor:'white', cornerStrokeColor:'transparent', cornerStyle:'circle', rotatingPointOffset:40 });
    }
    function addMaskBox() {
        if (!fabricCanvas) return;
        const w = fabricCanvas.width, h = fabricCanvas.height;
        const rect = new fabric.Rect({ left:w/2-50, top:h/2-50, fill:'rgba(0,255,0,0.35)', width:100, height:100, objectCaching:false, stroke:null, strokeWidth:0 });
        addCustomControlsTo(rect); fabricCanvas.add(rect); fabricCanvas.setActiveObject(rect); fabricCanvas.requestRenderAll();
    }
    function updateCoordsDisplay() {
        if (!cropper) return;
        const d = cropper.getData(true);
        const el = document.getElementById('ieCoordsDisplay');
        if (el) el.innerHTML = `X: ${Math.round(d.x)}, Y: ${Math.round(d.y)}<br>W: ${Math.round(d.width)}, H: ${Math.round(d.height)}`;
    }
    function showLoadingOverlay() { const o = document.getElementById('ieLoadingOverlay'); if(o) o.style.display='flex'; }
    function hideLoadingOverlay() { const o = document.getElementById('ieLoadingOverlay'); if(o) o.style.display='none'; }
    function updateButtonStates() {
        const u = document.getElementById('ieUndoBtn'), s = document.getElementById('ieSaveBtn');
        if (u) u.disabled = editHistory.length === 0;
        if (s) s.disabled = !hasUnsavedChanges;
    }
    function pushToHistory(imageSrc, filename, operation) {
        editHistory.push({ src: imageSrc, filename: filename });
        operationHistory.push(operation);
        hasUnsavedChanges = true;
        updateButtonStates();
    }
    function performUndo() {
        if (editHistory.length === 0) return;
        editHistory.pop(); operationHistory.pop();
        let prevSrc, prevFilename;
        if (editHistory.length > 0) { const p = editHistory[editHistory.length-1]; prevSrc = p.src; prevFilename = p.filename; }
        else { prevSrc = currentData.originalSrc; prevFilename = currentData.originalFilename; }
        currentTempFile = prevFilename;
        const canvas = document.getElementById('ieCanvas');
        canvas.src = prevSrc + (prevSrc.indexOf('?')>-1?'&':'?') + 'v=' + Date.now();
        if (activeTab === 'mask') initFabric();
        else { if(cropper) cropper.destroy(); canvas.onload = initCropper; }
        if (editHistory.length === 0) hasUnsavedChanges = false;
        updateButtonStates();
        showToast('Undone', 'success');
    }

    async function applyCrop() {
        if (!cropper) return;
        const d = cropper.getData(true);
        await executeAction(TEMP_API_ENDPOINT, { action:'crop', source_file: currentTempFile || currentData.originalFilename, coords:{ x:Math.round(d.x), y:Math.round(d.y), width:Math.round(d.width), height:Math.round(d.height) }, mode:'crop' }, 'Crop');
    }
    async function applyMask() {
        if (!fabricCanvas) return;
        const scale = fabricCanvas.bgImageScale || 1;
        const polygons = [];
        fabricCanvas.getObjects().forEach(obj => {
            if (obj.type === 'rect') {
                const coords = obj.getCoords();
                polygons.push(coords.map(c => [c.x / scale, c.y / scale]));
            }
        });
        if (polygons.length === 0) { showToast('No masks added', 'info'); return; }
        const useOpacity = document.getElementById('ieMaskOpacity').checked;
        const color = useOpacity ? '#00FF0059' : '#00FF00';
        await executeAction(TEMP_API_ENDPOINT, { action:'draw_mask', source_file: currentTempFile || currentData.originalFilename, polygons, color, fill:true }, 'Mask');
    }
    async function applyRemoveBg() {
        await executeAction(TEMP_API_ENDPOINT, { action:'remove_bg', source_file: currentTempFile || currentData.originalFilename }, 'Background Removal');
    }
    async function applyRotate() {
        const angle = parseFloat(document.getElementById('ieRotateAngle').value);
        await executeAction(TEMP_API_ENDPOINT, { action:'rotate', source_file: currentTempFile || currentData.originalFilename, angle }, `Rotate ${angle}°`);
    }
    async function applyResize() {
        const width  = parseInt(document.getElementById('ieResizeWidth').value);
        const height = parseInt(document.getElementById('ieResizeHeight').value);
        await executeAction(TEMP_API_ENDPOINT, { action:'resize', source_file: currentTempFile || currentData.originalFilename, width, height }, `Resize ${width}x${height}`);
    }
    async function applyOutpaint() {
        const width    = parseInt(document.getElementById('ieOutpaintWidth').value);
        const heightV  = document.getElementById('ieOutpaintHeight').value;
        const height   = heightV ? parseInt(heightV) : null;
        const xV       = document.getElementById('ieOutpaintX').value;
        const yV       = document.getElementById('ieOutpaintY').value;
        const x        = xV !== '' ? parseInt(xV) : null;
        const y        = yV !== '' ? parseInt(yV) : null;
        const color    = document.getElementById('ieOutpaintColorText').value;
        if (!width || width <= 0) { showToast('Invalid width for outpaint', 'error'); return; }
        await executeAction(TEMP_API_ENDPOINT, { action:'outpaint', source_file: currentTempFile || currentData.originalFilename, width, height, x, y, color }, 'Outpaint');
    }

    async function executeAction(endpoint, payload, operationName) {
        abortController = new AbortController();
        showLoadingOverlay();
        try {
            const canvas     = document.getElementById('ieCanvas');
            const currentSrc = canvas.src.split('?')[0];
            const currentFile = currentTempFile || currentData.originalFilename;
            pushToHistory(currentSrc, currentFile, operationName);
            const resp   = await fetch(endpoint, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload), signal:abortController.signal });
            const result = await resp.json();
            if (result.success) {
                showToast(`${operationName} applied`, 'success');
                currentTempFile = result.filename;
                canvas.src = '/' + result.filename + '?v=' + Date.now();
                if (activeTab === 'mask') initFabric();
                else { if(cropper) cropper.destroy(); canvas.onload = initCropper; }
            } else {
                editHistory.pop(); operationHistory.pop();
                hasUnsavedChanges = editHistory.length > 0;
                updateButtonStates();
                showToast(`${operationName} failed: ` + (result.message || 'unknown'), 'error');
            }
        } catch(err) {
            editHistory.pop(); operationHistory.pop();
            hasUnsavedChanges = editHistory.length > 0;
            updateButtonStates();
            if (err.name === 'AbortError') showToast('Operation cancelled.', 'info');
            else { showToast(`${operationName} request failed.`, 'error'); console.error(err); }
        } finally {
            hideLoadingOverlay(); abortController = null;
        }
    }

    async function saveToDatabase() {
        if (!hasUnsavedChanges) return;
        if (!currentTempFile) return;
        showLoadingOverlay();
        try {
            const resp = await fetch(SAVE_FINAL_ENDPOINT, {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ entity: currentData.entity, original_frame_id: currentData.originalFrameId, temp_filename: currentTempFile, operations: operationHistory })
            });
            const result = await resp.json();
            if (result.success) {
                showToast('Saved successfully!', 'success');
                currentData.frameId = result.new_frame_id;
                currentData.originalFilename = result.filename;
                currentTempFile = result.filename;
                hasUnsavedChanges = false; editHistory = []; operationHistory = [];
                updateButtonStates();
                if (typeof $ !== 'undefined') $(document).trigger('imageEdit.saved', [result]);
            } else {
                showToast('Save failed: ' + (result.message || 'unknown'), 'error');
            }
        } catch(err) {
            showToast('Save request failed', 'error'); console.error(err);
        } finally { hideLoadingOverlay(); }
    }

    // ╔═══════════════════════════════════════════════════════════════════╗
    // ║  NEW: Grade Tab — Canvas2D pixel engine                         ║
    // ╚═══════════════════════════════════════════════════════════════════╝

    // Build a LUT (0-255) from curve control points using monotone cubic interpolation
    function buildCurveLUT(points) {
        // Sort by input
        const pts = [...points].sort((a,b) => a[0]-b[0]);
        const lut  = new Uint8ClampedArray(256);
        for (let i = 0; i < 256; i++) {
            // Find surrounding points
            let lo = pts[0], hi = pts[pts.length-1];
            for (let j = 0; j < pts.length-1; j++) {
                if (pts[j][0] <= i && pts[j+1][0] >= i) { lo = pts[j]; hi = pts[j+1]; break; }
            }
            if (lo === hi) { lut[i] = Math.round(lo[1]); continue; }
            const t = (i - lo[0]) / (hi[0] - lo[0]);
            // Smooth step (Hermite)
            const v = lo[1] + (hi[1]-lo[1]) * (t*t*(3-2*t));
            lut[i] = Math.round(Math.max(0, Math.min(255, v)));
        }
        return lut;
    }

    // Build per-channel LUTs for current curve state
    function buildAllLUTs() {
        return {
            rgb: buildCurveLUT(curvePoints.rgb),
            r:   buildCurveLUT(curvePoints.r),
            g:   buildCurveLUT(curvePoints.g),
            b:   buildCurveLUT(curvePoints.b),
        };
    }

    // Main grade engine — pure pixel math, matches Pillow server logic
    function applyGradeToImageData(srcImageData, state, luts) {
        const d    = new Uint8ClampedArray(srcImageData.data);
        const len  = d.length;

        // Pre-compute scalar transforms
        const bFactor  = 1 + state.brightness  / 100;   // multiply luminance
        const cFactor  = 1 + state.contrast     / 100;   // around midpoint
        const gFactor  = state.gamma !== 0 ? 1 / (1 + state.gamma / 100) : 1.0; // gamma exponent
        const satFactor = 1 + state.saturation  / 100;
        const tempShift = state.temperature / 2;     // R += tempShift, B -= tempShift
        const tintShift = state.tint        / 4;     // G += tintShift
        const liftV     = state.lift        * 0.5;   // raise blacks
        const gainV     = 1 + state.gain    / 100;   // multiply highlights
        const hueRad    = (state.hueRotate || 0) * Math.PI / 180;

        // Shadow/highlight recovery coefficients
        const shadowBoost    = state.shadows    / 200;
        const highlightBoost = state.highlights / 200;
        const whitesBoost    = state.whites     / 200;
        const blacksBoost    = state.blacks     / 200;
        const vibranceV      = state.vibrance   / 100;

        for (let i = 0; i < len; i += 4) {
            let r = d[i], g = d[i+1], b = d[i+2];

            // ── 1. Lift (raise shadows uniformly) ──
            if (liftV !== 0) {
                r = r + liftV * (255 - r) / 255 * 128;
                g = g + liftV * (255 - g) / 255 * 128;
                b = b + liftV * (255 - b) / 255 * 128;
            }

            // ── 2. Gain (multiply bright areas) ──
            if (gainV !== 1.0) { r *= gainV; g *= gainV; b *= gainV; }

            // ── 3. Brightness (uniform scale) ──
            if (bFactor !== 1.0) { r *= bFactor; g *= bFactor; b *= bFactor; }

            // ── 4. Contrast (pivot at 128) ──
            if (cFactor !== 1.0) {
                r = (r - 128) * cFactor + 128;
                g = (g - 128) * cFactor + 128;
                b = (b - 128) * cFactor + 128;
            }

            // ── 5. Gamma ──
            if (gFactor !== 1.0) {
                r = 255 * Math.pow(r / 255, gFactor);
                g = 255 * Math.pow(g / 255, gFactor);
                b = 255 * Math.pow(b / 255, gFactor);
            }

            // ── 6. Temperature & Tint ──
            r += tempShift; b -= tempShift; g += tintShift;

            // ── 7. Shadow / Highlight recovery ──
            const lum0 = (r*0.299 + g*0.587 + b*0.114) / 255; // 0-1
            if (shadowBoost !== 0) {
                const sw = Math.max(0, 1 - lum0 * 3); // weight: 1 in deep shadows, 0 at 1/3 lum
                r += shadowBoost * sw * 255;
                g += shadowBoost * sw * 255;
                b += shadowBoost * sw * 255;
            }
            if (highlightBoost !== 0) {
                const hw = Math.max(0, lum0 * 3 - 2); // weight: 1 in bright highlights
                r += highlightBoost * hw * 255;
                g += highlightBoost * hw * 255;
                b += highlightBoost * hw * 255;
            }
            if (blacksBoost !== 0) {
                const bw = Math.max(0, 1 - lum0 * 5);
                r += blacksBoost * bw * 255;
                g += blacksBoost * bw * 255;
                b += blacksBoost * bw * 255;
            }
            if (whitesBoost !== 0) {
                const ww = Math.max(0, lum0 * 5 - 4);
                r += whitesBoost * ww * 255;
                g += whitesBoost * ww * 255;
                b += whitesBoost * ww * 255;
            }

            // Clamp before colorimetric ops
            r = Math.max(0, Math.min(255, r));
            g = Math.max(0, Math.min(255, g));
            b = Math.max(0, Math.min(255, b));

            // ── 8. Saturation ──
            if (satFactor !== 1.0) {
                const lum = r*0.299 + g*0.587 + b*0.114;
                r = lum + (r - lum) * satFactor;
                g = lum + (g - lum) * satFactor;
                b = lum + (b - lum) * satFactor;
            }

            // ── 9. Vibrance (protect saturated colours) ──
            if (vibranceV !== 0) {
                const mx = Math.max(r,g,b), mn = Math.min(r,g,b);
                const sat = mx > 0 ? (mx - mn) / mx : 0;
                const vw  = 1 - sat; // boost more where less saturated
                const lum = r*0.299 + g*0.587 + b*0.114;
                const vf  = 1 + vibranceV * vw;
                r = lum + (r - lum) * vf;
                g = lum + (g - lum) * vf;
                b = lum + (b - lum) * vf;
            }

            // ── 10. Hue Rotate ──
            if (hueRad !== 0) {
                // Convert RGB -> HSL, rotate H, back
                const nr = Math.max(0,Math.min(255,r))/255;
                const ng = Math.max(0,Math.min(255,g))/255;
                const nb = Math.max(0,Math.min(255,b))/255;
                const mx2 = Math.max(nr,ng,nb), mn2 = Math.min(nr,ng,nb), delta = mx2-mn2;
                let h2 = 0, s2 = 0, l2 = (mx2+mn2)/2;
                if (delta > 0) {
                    s2 = delta / (1 - Math.abs(2*l2-1));
                    if (mx2===nr) h2 = ((ng-nb)/delta % 6) * 60;
                    else if (mx2===ng) h2 = ((nb-nr)/delta + 2) * 60;
                    else h2 = ((nr-ng)/delta + 4) * 60;
                }
                h2 = ((h2 + state.hueRotate) % 360 + 360) % 360;
                const c2 = (1 - Math.abs(2*l2-1)) * s2;
                const x2 = c2 * (1 - Math.abs((h2/60)%2 - 1));
                const m2 = l2 - c2/2;
                const seg = Math.floor(h2/60);
                const rgb2 = [[c2,x2,0],[x2,c2,0],[0,c2,x2],[0,x2,c2],[x2,0,c2],[c2,0,x2]][seg] || [0,0,0];
                r = (rgb2[0]+m2)*255; g = (rgb2[1]+m2)*255; b = (rgb2[2]+m2)*255;
            }

            // Clamp
            r = Math.max(0,Math.min(255,r));
            g = Math.max(0,Math.min(255,g));
            b = Math.max(0,Math.min(255,b));

            // ── 11. Curves ──
            const rI = Math.round(r), gI = Math.round(g), bI = Math.round(b);
            r = luts.rgb[luts.r[rI]];
            g = luts.rgb[luts.g[gI]];
            b = luts.rgb[luts.b[bI]];

            d[i] = r; d[i+1] = g; d[i+2] = b;
            // alpha unchanged
        }
        return new ImageData(d, srcImageData.width, srcImageData.height);
    }

    // Render grade preview onto #ieGradeCanvas
    function renderGradePreview() {
        const src = currentTempFile ? '/' + currentTempFile : currentData.originalSrc;
        if (!src) return;

        const gradeCanvas = document.getElementById('ieGradeCanvas');
        if (!gradeCanvas) return;

        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function() {
            gradeCanvas.width  = img.naturalWidth;
            gradeCanvas.height = img.naturalHeight;
            const ctx = gradeCanvas.getContext('2d');
            ctx.drawImage(img, 0, 0);

            if (isGradeDefault(gradeState) && isCurvesDefault()) {
                return; // nothing to do, show unmodified
            }

            const imgData = ctx.getImageData(0, 0, gradeCanvas.width, gradeCanvas.height);
            const luts    = buildAllLUTs();
            const out     = applyGradeToImageData(imgData, gradeState, luts);

            // Grain (applied after pixel ops)
            if (gradeState.grain > 0) {
                applyGrainToImageData(out, gradeState.grain);
            }

            ctx.putImageData(out, 0, 0);

            // Vignette (canvas overlay — no per-pixel needed)
            if (gradeState.vignette > 0) {
                applyVignetteToCanvas(ctx, gradeCanvas.width, gradeCanvas.height, gradeState.vignette / 100);
            }
        };
        img.src = src + (src.indexOf('?')>-1?'&':'?') + 'gr=' + Date.now();
    }

    function isCurvesDefault() {
        const def = JSON.stringify(getDefaultCurve());
        return ['rgb','r','g','b'].every(c => JSON.stringify(curvePoints[c]) === def);
    }

    function applyGrainToImageData(imgData, amount) {
        const d = imgData.data, len = d.length;
        const strength = amount * 1.2;
        for (let i = 0; i < len; i += 4) {
            const n = (Math.random() - 0.5) * strength;
            d[i]   = Math.max(0,Math.min(255, d[i]   + n));
            d[i+1] = Math.max(0,Math.min(255, d[i+1] + n));
            d[i+2] = Math.max(0,Math.min(255, d[i+2] + n));
        }
    }

    function applyVignetteToCanvas(ctx, w, h, strength) {
        const cx = w/2, cy = h/2, r = Math.sqrt(cx*cx + cy*cy);
        const grad = ctx.createRadialGradient(cx, cy, r*0.4, cx, cy, r);
        grad.addColorStop(0, 'rgba(0,0,0,0)');
        grad.addColorStop(1, `rgba(0,0,0,${Math.min(0.85, strength * 0.85)})`);
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, w, h);
    }

    function scheduleGradePreview() {
        clearTimeout(gradePreviewTimer);
        gradePreviewTimer = setTimeout(renderGradePreview, 80);
    }

    // Read all grade sliders into gradeState
    function readGradeSliders() {
        const map = {
            igBrightness: 'brightness', igHighlights: 'highlights', igShadows: 'shadows',
            igWhites: 'whites', igBlacks: 'blacks', igTemperature: 'temperature',
            igTint: 'tint', igSaturation: 'saturation', igVibrance: 'vibrance',
            igHueRotate: 'hueRotate', igContrast: 'contrast', igGamma: 'gamma',
            igLift: 'lift', igGain: 'gain', igVignette: 'vignette', igGrain: 'grain',
            igBlur: 'blur', igSharpen: 'sharpen',
        };
        Object.entries(map).forEach(([id, key]) => {
            const el = document.getElementById(id);
            if (el) gradeState[key] = parseFloat(el.value) || 0;
        });
    }

    function setGradeSliders(state) {
        const map = {
            igBrightness: 'brightness', igHighlights: 'highlights', igShadows: 'shadows',
            igWhites: 'whites', igBlacks: 'blacks', igTemperature: 'temperature',
            igTint: 'tint', igSaturation: 'saturation', igVibrance: 'vibrance',
            igHueRotate: 'hueRotate', igContrast: 'contrast', igGamma: 'gamma',
            igLift: 'lift', igGain: 'gain', igVignette: 'vignette', igGrain: 'grain',
            igBlur: 'blur', igSharpen: 'sharpen',
        };
        Object.entries(map).forEach(([id, key]) => {
            const el = document.getElementById(id), valEl = document.getElementById(id+'Val');
            if (el && state[key] !== undefined) {
                el.value = state[key];
                if (valEl) valEl.textContent = state[key];
            }
        });
    }

    function resetAllGrade() {
        gradeState   = getDefaultGradeState();
        curvePoints  = { rgb: getDefaultCurve(), r: getDefaultCurve(), g: getDefaultCurve(), b: getDefaultCurve() };
        setGradeSliders(gradeState);
        drawCurveEditor();
        scheduleGradePreview();
    }

    // Apply grade: sends to Pillow via image_editor_api.php 'grade' action,
    // which returns a temp file — same flow as other actions.
    async function applyGradeViaPillow() {
        readGradeSliders();
        const settings = buildGradeSettings();

        const payload = {
            action:      'grade',
            source_file: currentTempFile || currentData.originalFilename,
            settings:    settings,
        };

        await executeAction(TEMP_API_ENDPOINT, payload, 'Color Grade');

        // After executeAction updates currentTempFile, switch back to grade canvas
        if (activeTab === 'grade') {
            showGradeCanvas();
        }
    }

    // Save grade directly (calls color_grade_api.php render_and_save)
    async function saveGradeToDatabase() {
        readGradeSliders();
        const settings = buildGradeSettings();

        showLoadingOverlay();
        try {
            const resp = await fetch(GRADE_API, {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({
                    action:    'render_and_save',
                    frame_id:  currentData.originalFrameId,
                    settings:  settings,
                    operations:['Color Grade'],
                })
            });
            const result = await resp.json();
            if (result.success) {
                showToast('Grade saved!', 'success');
                currentData.frameId = result.new_frame_id;
                hasUnsavedChanges = false;
                editHistory = []; operationHistory = [];
                updateButtonStates();
                if (typeof $ !== 'undefined') $(document).trigger('imageEdit.saved', [result]);
            } else {
                showToast('Grade save failed: ' + (result.message || 'unknown'), 'error');
            }
        } catch(err) {
            showToast('Grade save request failed', 'error'); console.error(err);
        } finally { hideLoadingOverlay(); }
    }

    function buildGradeSettings() {
        return {
            brightness:  gradeState.brightness,
            highlights:  gradeState.highlights,
            shadows:     gradeState.shadows,
            whites:      gradeState.whites,
            blacks:      gradeState.blacks,
            temperature: gradeState.temperature,
            tint:        gradeState.tint,
            saturation:  gradeState.saturation,
            vibrance:    gradeState.vibrance,
            hue_rotate:  gradeState.hueRotate,
            contrast:    gradeState.contrast,
            gamma:       gradeState.gamma,
            lift:        gradeState.lift,
            gain:        gradeState.gain,
            vignette:    gradeState.vignette,
            grain:       gradeState.grain,
            blur:        gradeState.blur,
            sharpen:     gradeState.sharpen,
            curves: {
                rgb: curvePoints.rgb,
                r:   curvePoints.r,
                g:   curvePoints.g,
                b:   curvePoints.b,
            }
        };
    }

    function applyLookDelta(deltaJSON) {
        let delta;
        try { delta = typeof deltaJSON === 'string' ? JSON.parse(deltaJSON) : deltaJSON; }
        catch(e) { showToast('Invalid look data', 'error'); return; }

        const keyMap = { temperature:'temperature', tint:'tint', saturation:'saturation',
                         contrast:'contrast', brightness:'brightness', lift:'lift',
                         blacks:'blacks', highlights:'highlights', shadows:'shadows',
                         grain:'grain', vibrance:'vibrance', gamma:'gamma', gain:'gain' };
        Object.entries(delta).forEach(([k, v]) => {
            if (keyMap[k] !== undefined) gradeState[keyMap[k]] = Math.max(-100, Math.min(100, (gradeState[keyMap[k]] || 0) + v));
        });
        setGradeSliders(gradeState);
        scheduleGradePreview();

        // Highlight applied button briefly
        document.querySelectorAll('.ig-look-btn').forEach(b => b.classList.remove('applied'));
    }

    // ── Curves editor ──────────────────────────────────────────────────

    function drawCurveEditor() {
        const canvas = document.getElementById('igCurveCanvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const W = canvas.width, H = canvas.height;

        // Background
        ctx.fillStyle = '#111820';
        ctx.fillRect(0, 0, W, H);

        // Grid
        ctx.strokeStyle = '#1c2535';
        ctx.lineWidth = 1;
        for (let i = 1; i < 4; i++) {
            const x = W * i / 4, y = H * i / 4;
            ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke();
        }

        // Diagonal reference
        ctx.strokeStyle = '#2a3a52';
        ctx.lineWidth = 1;
        ctx.setLineDash([4, 4]);
        ctx.beginPath(); ctx.moveTo(0, H); ctx.lineTo(W, 0); ctx.stroke();
        ctx.setLineDash([]);

        // Curve
        const pts = curvePoints[activeCurveChannel];
        const chanColors = { rgb:'#f5a623', r:'#f05060', g:'#22d3a0', b:'#4da6ff' };
        ctx.strokeStyle = chanColors[activeCurveChannel] || '#f5a623';
        ctx.lineWidth = 2;
        ctx.beginPath();
        for (let x = 0; x <= 255; x++) {
            const lut = buildCurveLUT(pts);
            const cx  = (x / 255) * W;
            const cy  = H - (lut[x] / 255) * H;
            if (x === 0) ctx.moveTo(cx, cy); else ctx.lineTo(cx, cy);
        }
        ctx.stroke();

        // Control points
        pts.forEach(([px, py]) => {
            const cx = (px / 255) * W;
            const cy = H - (py / 255) * H;
            ctx.beginPath();
            ctx.arc(cx, cy, 5, 0, Math.PI * 2);
            ctx.fillStyle = chanColors[activeCurveChannel];
            ctx.fill();
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 1.5;
            ctx.stroke();
        });
    }

    let draggingCurvePoint = null;

    function curveCanvasCoordToValue(canvas, clientX, clientY) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width  / rect.width;
        const scaleY = canvas.height / rect.height;
        const x = Math.max(0, Math.min(255, Math.round(((clientX - rect.left) * scaleX) / canvas.width  * 255)));
        const y = Math.max(0, Math.min(255, Math.round((1 - ((clientY - rect.top)  * scaleY) / canvas.height) * 255)));
        return [x, y];
    }

    function initCurveEditor() {
        const canvas = document.getElementById('igCurveCanvas');
        if (!canvas) return;

        canvas.addEventListener('mousedown', function(e) {
            if (e.button === 2) return; // right-click handled separately
            const [vx, vy] = curveCanvasCoordToValue(canvas, e.clientX, e.clientY);
            const pts = curvePoints[activeCurveChannel];

            // Check if near existing point (within 12px in value space)
            const hit = pts.findIndex(([px]) => Math.abs(px - vx) < 10);
            if (hit !== -1) { draggingCurvePoint = hit; return; }

            // Add new point
            pts.push([vx, vy]);
            pts.sort((a,b) => a[0]-b[0]);
            draggingCurvePoint = pts.findIndex(([px]) => px === vx);
            drawCurveEditor();
            scheduleGradePreview();
        });

        canvas.addEventListener('mousemove', function(e) {
            if (draggingCurvePoint === null) return;
            const [vx, vy] = curveCanvasCoordToValue(canvas, e.clientX, e.clientY);
            curvePoints[activeCurveChannel][draggingCurvePoint] = [vx, vy];
            curvePoints[activeCurveChannel].sort((a,b) => a[0]-b[0]);
            drawCurveEditor();
            scheduleGradePreview();
        });

        canvas.addEventListener('mouseup',    () => { draggingCurvePoint = null; });
        canvas.addEventListener('mouseleave', () => { draggingCurvePoint = null; });

        // Touch support
        canvas.addEventListener('touchstart', function(e) {
            e.preventDefault();
            const t = e.touches[0];
            const [vx, vy] = curveCanvasCoordToValue(canvas, t.clientX, t.clientY);
            const pts = curvePoints[activeCurveChannel];
            const hit = pts.findIndex(([px]) => Math.abs(px - vx) < 15);
            if (hit !== -1) { draggingCurvePoint = hit; return; }
            pts.push([vx, vy]); pts.sort((a,b) => a[0]-b[0]);
            draggingCurvePoint = pts.findIndex(([px]) => px === vx);
            drawCurveEditor(); scheduleGradePreview();
        }, { passive: false });

        canvas.addEventListener('touchmove', function(e) {
            e.preventDefault();
            if (draggingCurvePoint === null) return;
            const t = e.touches[0];
            const [vx, vy] = curveCanvasCoordToValue(canvas, t.clientX, t.clientY);
            curvePoints[activeCurveChannel][draggingCurvePoint] = [vx, vy];
            curvePoints[activeCurveChannel].sort((a,b) => a[0]-b[0]);
            drawCurveEditor(); scheduleGradePreview();
        }, { passive: false });

        canvas.addEventListener('touchend', () => { draggingCurvePoint = null; });

        // Right-click to remove point (but not endpoints)
        canvas.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            const [vx] = curveCanvasCoordToValue(canvas, e.clientX, e.clientY);
            const pts  = curvePoints[activeCurveChannel];
            const hit  = pts.findIndex(([px], i) => Math.abs(px - vx) < 12 && i > 0 && i < pts.length - 1);
            if (hit !== -1) { pts.splice(hit, 1); drawCurveEditor(); scheduleGradePreview(); }
        });
    }

    // ── Profiles ───────────────────────────────────────────────────────

    async function loadProfiles() {
        try {
            const resp = await fetch(GRADE_API, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'list_presets' }) });
            const result = await resp.json();
            if (result.success) renderProfileList(result.presets || []);
        } catch(e) { console.error('Profile load failed', e); }
    }

    function renderProfileList(presets) {
        const container = document.getElementById('igProfileList');
        if (!container) return;
        if (presets.length === 0) {
            container.innerHTML = '<div class="ig-profile-empty">No saved profiles yet.</div>';
            return;
        }
        container.innerHTML = presets.map(p => `
            <div class="ig-profile-row">
                <span class="ig-profile-name" title="${escHtml(p.name)}">${escHtml(p.name)}</span>
                <button class="ig-profile-apply" title="Apply profile" data-settings='${escAttr(JSON.stringify(p.settings))}'>▶</button>
                <button class="ig-profile-del"   title="Delete profile" data-id="${escHtml(p.id)}">✕</button>
            </div>`
        ).join('');

        container.querySelectorAll('.ig-profile-apply').forEach(btn => {
            btn.addEventListener('click', function() {
                try {
                    const s = JSON.parse(this.getAttribute('data-settings'));
                    applyProfileSettings(s);
                } catch(e) { showToast('Could not apply profile', 'error'); }
            });
        });
        container.querySelectorAll('.ig-profile-del').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (!confirm('Delete this profile?')) return;
                const id = this.getAttribute('data-id');
                const r  = await fetch(GRADE_API, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete_preset', preset_id: parseInt(id) }) });
                const d  = await r.json();
                if (d.success) { showToast('Deleted', 'success'); loadProfiles(); }
                else showToast('Delete failed', 'error');
            });
        });
    }

    function applyProfileSettings(settings) {
        if (!settings) return;
        const gs = {
            brightness:  settings.brightness  || 0,
            highlights:  settings.highlights  || 0,
            shadows:     settings.shadows     || 0,
            whites:      settings.whites      || 0,
            blacks:      settings.blacks      || 0,
            temperature: settings.temperature || 0,
            tint:        settings.tint        || 0,
            saturation:  settings.saturation  || 0,
            vibrance:    settings.vibrance    || 0,
            hueRotate:   settings.hue_rotate  || 0,
            contrast:    settings.contrast    || 0,
            gamma:       settings.gamma       || 0,
            lift:        settings.lift        || 0,
            gain:        settings.gain        || 0,
            vignette:    settings.vignette    || 0,
            grain:       settings.grain       || 0,
            blur:        settings.blur        || 0,
            sharpen:     settings.sharpen     || 0,
        };
        gradeState = gs;
        if (settings.curves) {
            curvePoints.rgb = settings.curves.rgb || getDefaultCurve();
            curvePoints.r   = settings.curves.r   || getDefaultCurve();
            curvePoints.g   = settings.curves.g   || getDefaultCurve();
            curvePoints.b   = settings.curves.b   || getDefaultCurve();
        }
        setGradeSliders(gradeState);
        drawCurveEditor();
        scheduleGradePreview();
        showToast('Profile applied', 'success');
    }

    async function saveProfile() {
        const nameEl = document.getElementById('igProfileName');
        const name   = nameEl ? nameEl.value.trim() : '';
        if (!name) { showToast('Enter a profile name first', 'error'); return; }
        readGradeSliders();
        const settings = buildGradeSettings();
        const thumbId  = currentData.frameId || currentData.originalFrameId || null;
        try {
            const resp = await fetch(GRADE_API, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'save_preset', name, settings, thumbnail_frame_id: thumbId }) });
            const result = await resp.json();
            if (result.success) {
                showToast('Profile saved: ' + name, 'success');
                if (nameEl) nameEl.value = '';
                loadProfiles();
            } else {
                showToast('Save failed: ' + (result.message || 'unknown'), 'error');
            }
        } catch(e) { showToast('Profile save failed', 'error'); console.error(e); }
    }

    // ── Grade canvas show/hide helpers ─────────────────────────────────

    function showGradeCanvas() {
        const imgEl   = document.getElementById('ieCanvas');
        const fabric  = document.getElementById('ieFabricWrapper');
        const gradeC  = document.getElementById('ieGradeCanvas');
        if (imgEl)  imgEl.style.display  = 'none';
        if (fabric) fabric.style.display = 'none';
        if (gradeC) gradeC.style.display = 'block';
        if (cropper) { cropper.destroy(); cropper = null; }
        destroyFabric();
        renderGradePreview();
    }

    // ── Modal open / close ─────────────────────────────────────────────

    function openModal(opts) {
        currentData = {
            entity:          opts.entity || opts.entityType,
            entityId:        opts.entityId || opts.entity_id,
            frameId:         opts.frameId || opts.frame_id,
            originalFrameId: opts.frameId || opts.frame_id,
            src:             opts.src,
            originalSrc:     opts.src,
            originalFilename: extractFilenameFromSrc(opts.src)
        };
        editHistory = []; operationHistory = []; hasUnsavedChanges = false; currentTempFile = null;
        gradeState  = getDefaultGradeState();
        curvePoints = { rgb: getDefaultCurve(), r: getDefaultCurve(), g: getDefaultCurve(), b: getDefaultCurve() };
        setGradeSliders(gradeState);
        updateButtonStates();
        activeTab = 'crop';

        document.querySelectorAll('.ie-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.ie-tab-content').forEach(c => c.classList.remove('active'));
        document.querySelector('[data-tab="crop"]').classList.add('active');
        document.querySelector('[data-tab-content="crop"]').classList.add('active');

        const canvas = document.getElementById('ieCanvas');
        canvas.src = currentData.src;
        canvas.onload = function() {
            const w = this.naturalWidth, h = this.naturalHeight;
            const rW = document.getElementById('ieResizeWidth'),  rH = document.getElementById('ieResizeHeight');
            const oW = document.getElementById('ieOutpaintWidth');
            if (rW) rW.value = w; if (rH) rH.value = h;
            if (oW) oW.value = Math.max(w, h) + 200;
            initCropper();
        };

        document.getElementById('imageEditorModal').style.display = 'flex';

        // Load profiles async
        loadProfiles();
        // Init curve editor once
        setTimeout(initCurveEditor, 100);
        setTimeout(drawCurveEditor, 120);
    }

    function extractFilenameFromSrc(src) { return (src || '').replace(/^\//, ''); }

    function closeModal() {
        if (hasUnsavedChanges) { if (!confirm('You have unsaved changes. Close anyway?')) return; }
        if (cropper) { cropper.destroy(); cropper = null; }
        destroyFabric();
        document.getElementById('imageEditorModal').style.display = 'none';
        currentData = {}; editHistory = []; operationHistory = [];
        hasUnsavedChanges = false; currentTempFile = null;
    }

    // ── Utility ────────────────────────────────────────────────────────
    const escHtml = s => {
        if (s == null) return '';
        const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML;
    };
    function escAttr(s) { return String(s).replace(/'/g, '&#39;').replace(/"/g, '&quot;'); }

    function showToast(message, type) {
        if (typeof Toast !== 'undefined' && Toast.show) Toast.show(message, type);
        else console.log(`[${type}] ${message}`);
    }

    // ── DOMContentLoaded wiring ────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('imageEditorModal');
        if (!modal) return;

        // Existing buttons (unchanged)
        modal.querySelector('.ie-close-btn')?.addEventListener('click', closeModal);
        modal.querySelector('#ieCloseBtn')?.addEventListener('click', closeModal);
        modal.querySelector('#ieSaveBtn')?.addEventListener('click', function() {
            // If grade tab is active, use grade save; otherwise standard save
            if (activeTab === 'grade') saveGradeToDatabase();
            else saveToDatabase();
        });
        modal.querySelector('#ieUndoBtn')?.addEventListener('click', performUndo);
        modal.querySelector('.ie-modal-overlay')?.addEventListener('click', closeModal);
        modal.querySelector('#ieCancelActionBtn')?.addEventListener('click', () => { if (abortController) abortController.abort(); });

        // Tab switching
        modal.querySelectorAll('.ie-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const targetTab = this.dataset.tab;
                activeTab = targetTab;
                modal.querySelectorAll('.ie-tab').forEach(t => t.classList.remove('active'));
                modal.querySelectorAll('.ie-tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                modal.querySelector(`[data-tab-content="${targetTab}"]`)?.classList.add('active');

                if (targetTab === 'mask') {
                    initFabric();
                } else if (targetTab === 'grade') {
                    showGradeCanvas();
                    drawCurveEditor();
                    scheduleGradePreview();
                } else if (targetTab === 'crop') {
                    document.getElementById('ieGradeCanvas').style.display = 'none';
                    initCropper();
                } else {
                    document.getElementById('ieFabricWrapper').style.display = 'none';
                    document.getElementById('ieGradeCanvas').style.display = 'none';
                    document.getElementById('ieCanvas').style.display = 'block';
                    if (!cropper) initCropper();
                }
            });
        });

        // Existing control bindings (unchanged)
        modal.querySelector('#ieAspectRatio')?.addEventListener('change', function() {
            if (!cropper) return;
            cropper.setAspectRatio(this.value === 'free' ? NaN : parseFloat(this.value));
        });
        modal.querySelector('#ieApplyCrop')?.addEventListener('click', applyCrop);
        modal.querySelector('#ieRemoveBgBtn')?.addEventListener('click', applyRemoveBg);
        modal.querySelector('#ieAddMaskBox')?.addEventListener('click', addMaskBox);
        modal.querySelector('#ieApplyMask')?.addEventListener('click', applyMask);
        modal.querySelector('#ieApplyRotate')?.addEventListener('click', applyRotate);
        modal.querySelector('#ieApplyResize')?.addEventListener('click', applyResize);
        modal.querySelector('#ieApplyOutpaint')?.addEventListener('click', applyOutpaint);

        const colorPicker = modal.querySelector('#ieOutpaintColorPicker');
        const colorText   = modal.querySelector('#ieOutpaintColorText');
        if (colorPicker && colorText) {
            colorPicker.addEventListener('input', function() { colorText.value = this.value; });
            colorText.addEventListener('input',   function() { try { colorPicker.value = this.value; } catch(e){} });
        }

        // ── Grade tab bindings ──

        // All grade sliders → live preview
        modal.querySelectorAll('.ig-slider').forEach(slider => {
            slider.addEventListener('input', function() {
                const valEl = document.getElementById(this.id + 'Val');
                if (valEl) valEl.textContent = parseFloat(this.value).toFixed(this.step && parseFloat(this.step) < 1 ? 1 : 0);
                readGradeSliders();
                scheduleGradePreview();
            });
            // Double-click resets to default
            slider.addEventListener('dblclick', function() {
                const def = parseFloat(this.getAttribute('data-default')) || 0;
                this.value = def;
                const valEl = document.getElementById(this.id + 'Val');
                if (valEl) valEl.textContent = def;
                readGradeSliders();
                scheduleGradePreview();
            });
        });

        // Grade sub-nav
        modal.querySelectorAll('.ig-subbtn').forEach(btn => {
            btn.addEventListener('click', function() {
                modal.querySelectorAll('.ig-subbtn').forEach(b => b.classList.remove('active'));
                modal.querySelectorAll('.ig-section').forEach(s => s.classList.remove('active'));
                this.classList.add('active');
                modal.querySelector(`.ig-section[data-section="${this.dataset.section}"]`)?.classList.add('active');
                if (this.dataset.section === 'curves') { setTimeout(drawCurveEditor, 50); }
                if (this.dataset.section === 'profiles') loadProfiles();
            });
        });

        // Curve channel buttons
        modal.querySelectorAll('.ig-curve-chan').forEach(btn => {
            btn.addEventListener('click', function() {
                modal.querySelectorAll('.ig-curve-chan').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                activeCurveChannel = this.dataset.chan;
                drawCurveEditor();
            });
        });

        // Curve reset
        modal.querySelector('#igCurveReset')?.addEventListener('click', function() {
            curvePoints[activeCurveChannel] = getDefaultCurve();
            drawCurveEditor();
            scheduleGradePreview();
        });

        // Film looks
        modal.querySelectorAll('.ig-look-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                applyLookDelta(this.getAttribute('data-look'));
                modal.querySelectorAll('.ig-look-btn').forEach(b => b.classList.remove('applied'));
                this.classList.add('applied');
            });
        });

        // Apply grade (sends to Pillow, creates temp file in history)
        modal.querySelector('#igApplyGradeBtn')?.addEventListener('click', applyGradeViaPillow);

        // Reset all
        modal.querySelector('#igResetAllBtn')?.addEventListener('click', resetAllGrade);

        // Save profile
        modal.querySelector('#igSaveProfileBtn')?.addEventListener('click', saveProfile);
    });

    // ── Public API ─────────────────────────────────────────────────────
    window.ImageEditorModal = { open: openModal, close: closeModal };

})();
</script>
JS;

        return $cropperJS . $fabricJS . $jsVars . $jsLogic;
    }

    public function withoutCSS(): self { $this->includeCSS = false; return $this; }
    public function withoutJS():  self { $this->includeJS  = false; return $this; }
}