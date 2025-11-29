<?php
// public/image_testing.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\PyApiImageService;

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// Initialize image service
$imageService = new PyApiImageService();

// Create temp directory for test results
$tempDir = $projectPath . '/public/temp_image_tests';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

// Get frames from database
$frames = [];
try {
    $stmt = $pdo->query("SELECT id, name, filename, created_at FROM frames ORDER BY created_at DESC LIMIT 50");
    $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error fetching frames: " . $e->getMessage();
}

// Process form submission
$testResults = [];
$selectedFrame = null;
$selectedMethod = '';

if ($_POST['action'] ?? '' === 'run_test') {
    $frameId = $_POST['frame_id'] ?? '';
    $method = $_POST['method'] ?? '';
    $params = $_POST['params'] ?? [];
    
    // Find selected frame
    foreach ($frames as $frame) {
        if ($frame['id'] == $frameId) {
            $selectedFrame = $frame;
            break;
        }
    }
    
    if ($selectedFrame && $method) {
        try {
            // CORRECTED: Use the filename directly as it already contains the path
            $sourcePath = $projectPath . '/public/' . $selectedFrame['filename'];
            
            if (!file_exists($sourcePath)) {
                throw new Exception("Source image file not found: " . $sourcePath);
            }
            
            // Generate output filename
            $timestamp = date('Ymd_His');
            $outputFilename = "test_{$method}_{$selectedFrame['id']}_{$timestamp}.png";
            $outputPath = $tempDir . '/' . $outputFilename;
            
            // Execute the selected method
            $result = executeImageMethod($imageService, $method, $sourcePath, $params, $outputPath);
            
            $testResults[] = [
                'success' => true,
                'method' => $method,
                'input_frame' => $selectedFrame,
                'output_path' => $outputPath,
                'output_url' => '/temp_image_tests/' . $outputFilename,
                'result' => $result,
                'timestamp' => $timestamp
            ];
            
        } catch (Exception $e) {
            $testResults[] = [
                'success' => false,
                'method' => $method,
                'input_frame' => $selectedFrame,
                'error' => $e->getMessage(),
                'timestamp' => date('Ymd_His')
            ];
        }
    }
}

// Helper function to execute image methods
function executeImageMethod($service, $method, $sourcePath, $params, $outputPath) {
    switch ($method) {

case 'applyPreset':
    $presetName = $params['preset_name'] ?? 'vintage';
    $result = $service->applyPreset($sourcePath, $presetName);
    break;
        case 'resize':
            $width = intval($params['width'] ?? 300);
            $height = intval($params['height'] ?? 300);
            $keepAspect = boolval($params['keep_aspect'] ?? true);
            $result = $service->resize($sourcePath, $width, $height, $keepAspect);
            break;



case 'crop':
    $x = intval($params['x'] ?? 0);
    $y = intval($params['y'] ?? 0);
    $width = intval($params['width'] ?? 200);
    $height = intval($params['height'] ?? 200);
    
    // CHANGE: Calculate x2 and y2 from x,y,width,height
    $x2 = $x + $width;
    $y2 = $y + $height;
    
    $result = $service->crop($sourcePath, $x, $y, $x2, $y2);
    break;



case 'rotate':
    $angle = floatval($params['angle'] ?? 45);
    $expand = boolval($params['expand'] ?? true);
    $result = $service->rotate($sourcePath, $angle, $expand);
    break;


            
        case 'flip':
            $direction = $params['direction'] ?? 'horizontal';
            $result = $service->flip($sourcePath, $direction);
            break;
            
        case 'grayscale':
            $result = $service->grayscale($sourcePath);
            break;




case 'blur':
    $radius = intval($params['radius'] ?? 2);
    $result = $service->applyCustomFilter($sourcePath, ['blur_radius' => $radius]);
    break;




case 'adjustBrightness':
    $factor = floatval($params['factor'] ?? 1.5);
    $result = $service->enhance($sourcePath, ['brightness' => $factor]);
    break;

case 'adjustContrast':
    $factor = floatval($params['factor'] ?? 1.5);
    $result = $service->enhance($sourcePath, ['contrast' => $factor]);
    break;


            
            
        case 'createThumbnail':
            $maxWidth = intval($params['max_width'] ?? 200);
            $maxHeight = intval($params['max_height'] ?? 200);
            $result = $service->createThumbnail($sourcePath, $maxWidth, $maxHeight);
            break;
            
        case 'convert':
            $format = $params['format'] ?? 'webp';
            $quality = isset($params['quality']) ? intval($params['quality']) : null;
            $result = $service->convert($sourcePath, $format, $quality);
            $outputPath = str_replace('.png', '.' . $format, $outputPath);
            break;
            
        case 'getInfo':
            $result = $service->getInfo($sourcePath);
            return $result; // Return info directly, no image to save
            
        default:
            throw new Exception("Unknown method: {$method}");
    }
    
    // Save the result if it's image data
    if ($result && $method !== 'getInfo') {
        file_put_contents($outputPath, $result);
    }
    
    return $result;
}

$pageTitle = "Image Processing Testing Interface";
ob_start();
?>

<!-- Include PhotoSwipe and Swiper -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- PhotoSwipe CSS via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
    <!-- Swiper via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
<?php else: ?>
    <!-- PhotoSwipe CSS via local copy -->
    <link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
    <!-- Swiper via local copy -->
    <link rel="stylesheet" href="/vendor/swiper/swiper-bundle.min.css" />
<?php endif; ?>

<style>
.image-testing-wrap { max-width:1400px; margin:0 auto; padding:20px; }
.testing-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px; }
.testing-header h1 { margin:0; font-weight:600; font-size:1.4rem; color:#333; }
.alert { padding:12px 16px; border-radius:8px; margin-bottom:20px; }
.alert-error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
.alert-success { background:#d1edff; color:#155724; border:1px solid #c3e6cb; }
.card { background:#fff; border-radius:10px; border:1px solid #e0e0e0; margin-bottom:25px; box-shadow:0 2px 8px rgba(0,0,0,0.08); }
.card-header { padding:16px 20px; border-bottom:1px solid #e0e0e0; font-weight:600; font-size:1.1rem; color:#333; background:#f8f9fa; border-radius:10px 10px 0 0; }
.card-body { padding:20px; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
@media (max-width: 768px) { .form-grid { grid-template-columns:1fr; } }
.form-group { margin-bottom:16px; }
.form-label { display:block; font-weight:600; margin-bottom:8px; color:#333; font-size:0.95rem; }
.form-control, .form-select { width:100%; padding:10px 12px; border-radius:6px; border:1px solid #ddd; font-size:0.95rem; transition:border-color 0.15s ease; }
.form-control:focus, .form-select:focus { outline:none; border-color:#0d6efd; box-shadow:0 0 0 2px rgba(13,110,253,0.1); }
.btn { display:inline-flex; align-items:center; justify-content:center; padding:10px 16px; border-radius:6px; text-decoration:none; font-size:0.95rem; font-weight:500; border:1px solid transparent; cursor:pointer; transition:all 0.15s ease; gap:6px; }
.btn-primary { background:#0d6efd; color:#fff; border-color:#0d6efd; }
.btn-primary:hover { background:#0b5ed7; border-color:#0a58ca; }
.btn-success { background:#198754; color:#fff; border-color:#198754; }
.btn-outline-secondary { background:transparent; border:1px solid #6c757d; color:#6c757d; }
.btn-sm { padding:8px 12px; font-size:0.9rem; }
.method-params { background:#f8f9fa; padding:15px; border-radius:6px; margin-top:10px; border-left:4px solid #0d6efd; }
.param-group { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-bottom:12px; }
.image-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:15px; margin-top:20px; }
.image-card { background:#fff; border-radius:8px; border:1px solid #e0e0e0; overflow:hidden; transition:transform 0.2s ease, box-shadow 0.2s ease; }
.image-card:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,0.1); }
.image-preview { width:100%; height:150px; object-fit:cover; cursor:pointer; }
.image-info { padding:12px; }
.image-name { font-weight:600; margin-bottom:4px; font-size:0.9rem; color:#333; }
.image-meta { font-size:0.8rem; color:#666; }
.results-section { margin-top:30px; }
.result-item { background:#f8f9fa; padding:15px; border-radius:8px; margin-bottom:15px; border-left:4px solid #198754; }
.result-item.error { border-left-color:#dc3545; background:#f8d7da; }
.result-images { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:15px; }
@media (max-width: 768px) { .result-images { grid-template-columns:1fr; } }
.result-image { text-align:center; }
.result-image img { max-width:100%; height:auto; max-height:300px; object-fit:contain; border-radius:6px; border:1px solid #ddd; }
.swiper { width:100%; height:300px; margin:20px 0; }
.swiper-slide { text-align:center; background:#fff; display:flex; align-items:center; justify-content:center; border-radius:8px; overflow:hidden; }
.swiper-slide img { width:100%; height:100%; object-fit:contain; }
.empty-state { text-align:center; padding:40px 20px; color:#666; }
.empty-state i { font-size:3rem; margin-bottom:15px; opacity:0.5; }
</style>

<div class="image-testing-wrap">
    <div class="testing-header">
        <h1>🖼️ Image Processing Testing Interface</h1>
        <div style="display:flex;gap:10px;align-items:center;">
            <button class="btn btn-outline-secondary btn-sm" onclick="clearTestResults()">Clear Results</button>
            <button class="btn btn-success btn-sm" onclick="runHealthCheck()">Health Check</button>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Test Configuration Card -->
    <div class="card">
        <div class="card-header">Test Configuration</div>
        <div class="card-body">
            <form method="post" id="testForm">
                <input type="hidden" name="action" value="run_test">
                
                <div class="form-grid">
                    <!-- Frame Selection -->
                    <div class="form-group">
                        <label class="form-label">Select Source Image</label>
                        
                        <select name="frame_id" class="form-select" required onchange="updateFramePreview(this.value)">
                            <option value="">Choose a frame...</option>
                            <?php foreach ($frames as $frame): ?>
                                <option value="<?= $frame['id'] ?>" 
                                        data-filename="<?= htmlspecialchars($frame['filename']) ?>"
                                        data-created="<?= $frame['created_at'] ?>">
                                    #<?= $frame['id'] ?> - <?= htmlspecialchars($frame['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                    
                        
                        
                        <div class="small-muted" style="margin-top:8px;">
                            Showing latest 50 frames from database
                        </div>
                    </div>

                    <!-- Method Selection -->
                    <div class="form-group">
                        <label class="form-label">Select Processing Method</label>





<select name="method" class="form-select" required onchange="showMethodParams(this.value)">
    <option value="">Choose a method...</option>
    <option value="resize">Resize</option>
    <option value="crop">Crop</option>
    <option value="rotate">Rotate</option>
    <option value="flip">Flip</option>
    <option value="grayscale">Grayscale (Noir)</option>
    <option value="applyPreset">Apply Any Preset</option>
    <option value="blur">Blur</option>
    <option value="adjustBrightness">Adjust Brightness</option>
    <option value="adjustContrast">Adjust Contrast</option>
    <option value="createThumbnail">Create Thumbnail</option>
    <option value="convert">Convert Format</option>
    <option value="getInfo">Get Image Info</option>
</select>



                    </div>
                </div>

                <!-- Method Parameters -->
                <div id="methodParams" class="method-params" style="display:none;">
                    <h4 style="margin-top:0;margin-bottom:15px;">Method Parameters</h4>
                    <div id="paramsContainer"></div>
                </div>

                <!-- Selected Frame Preview -->
                <div id="framePreview" style="display:none;margin-top:20px;">
                    <h4>Selected Frame Preview</h4>
                    <div id="previewContent"></div>
                </div>

                <div style="text-align:center;margin-top:25px;">
                    <button type="submit" class="btn btn-primary">
                        🚀 Run Test
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Test Results -->
    <?php if (!empty($testResults)): ?>
        <div class="card">
            <div class="card-header">Test Results</div>
            <div class="card-body">
                <?php foreach ($testResults as $result): ?>
                    <div class="result-item <?= $result['success'] ? '' : 'error' ?>">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
                            <div>
                                <strong><?= htmlspecialchars($result['method']) ?></strong>
                                <div class="small-muted">
                                    Frame: #<?= $result['input_frame']['id'] ?> - <?= htmlspecialchars($result['input_frame']['name']) ?>
                                    | <?= $result['timestamp'] ?>
                                </div>
                            </div>
                            <span style="padding:4px 8px;border-radius:4px;font-size:0.8rem;font-weight:600;background:<?= $result['success'] ? '#d1edff' : '#f8d7da' ?>;color:<?= $result['success'] ? '#155724' : '#721c24' ?>;">
                                <?= $result['success'] ? 'SUCCESS' : 'ERROR' ?>
                            </span>
                        </div>

                        <?php if ($result['success']): ?>
                            <?php if ($result['method'] === 'getInfo'): ?>
                                <div style="margin-top:15px;">
                                    <strong>Image Info:</strong>
                                    <pre style="background:#fff;padding:12px;border-radius:6px;margin-top:8px;font-size:0.85rem;overflow:auto;"><?= json_encode($result['result'], JSON_PRETTY_PRINT) ?></pre>
                                </div>
                            <?php else: ?>
                                <div class="result-images">
                                    <div class="result-image">
                                        <strong>Original</strong>
                                        <img src="<?= $result['input_frame']['filename'] ?>" 
                                             alt="Original" 
                                             onclick="openPhotoSwipe('<?= $result['input_frame']['filename'] ?>')">
                                    </div>
                                    <div class="result-image">
                                        <strong>Processed</strong>
                                        <img src="<?= $result['output_url'] ?>" 
                                             alt="Processed" 
                                             onclick="openPhotoSwipe('<?= $result['output_url'] ?>')">
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="margin-top:15px;color:#721c24;">
                                <strong>Error:</strong> <?= htmlspecialchars($result['error']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Available Frames Gallery -->
    <div class="card">
        <div class="card-header">Available Frames (<?= count($frames) ?>)</div>
        <div class="card-body">
            <?php if (empty($frames)): ?>
                <div class="empty-state">
                    <div>📷</div>
                    <h3>No frames found</h3>
                    <p>No images available in the frames table.</p>
                </div>
            <?php else: ?>
                <div class="image-grid">
                    <?php foreach ($frames as $frame): ?>
                        <div class="image-card">
                            <img src="<?= $frame['filename'] ?>" 
                                 alt="<?= htmlspecialchars($frame['name']) ?>" 
                                 class="image-preview"
                                 onclick="selectFrame(<?= $frame['id'] ?>, '<?= $frame['filename'] ?>', '<?= htmlspecialchars($frame['name']) ?>')">
                            <div class="image-info">
                                <div class="image-name"><?= htmlspecialchars($frame['name']) ?></div>
                                <div class="image-meta">#<?= $frame['id'] ?> | <?= date('M j, Y', strtotime($frame['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- PhotoSwipe -->
<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="pswp__bg"></div>
    <div class="pswp__scroll-wrap">
        <div class="pswp__container">
            <div class="pswp__item"></div>
            <div class="pswp__item"></div>
            <div class="pswp__item"></div>
        </div>
        <div class="pswp__ui pswp__ui--hidden">
            <div class="pswp__top-bar">
                <div class="pswp__counter"></div>
                <button class="pswp__button pswp__button--close" title="Close (Esc)"></button>
                <button class="pswp__button pswp__button--share" title="Share"></button>
                <button class="pswp__button pswp__button--fs" title="Toggle fullscreen"></button>
                <button class="pswp__button pswp__button--zoom" title="Zoom in/out"></button>
                <div class="pswp__preloader">
                    <div class="pswp__preloader__icn">
                        <div class="pswp__preloader__cut">
                            <div class="pswp__preloader__donut"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pswp__share-modal pswp__share-modal--hidden pswp__single-tap">
                <div class="pswp__share-tooltip"></div>
            </div>
            <button class="pswp__button pswp__button--arrow--left" title="Previous (arrow left)"></button>
            <button class="pswp__button pswp__button--arrow--right" title="Next (arrow right)"></button>
            <div class="pswp__caption">
                <div class="pswp__caption__center"></div>
            </div>
        </div>
    </div>
</div>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- PhotoSwipe JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.umd.js"></script>
    <!-- Swiper via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<?php else: ?>
    <!-- PhotoSwipe JS via local copy -->
    <script src="/vendor/photoswipe/photoswipe.umd.js"></script>
    <script src="/vendor/photoswipe/photoswipe-lightbox.umd.js"></script>
    <!-- Swiper via local copy -->
    <script src="/vendor/swiper/swiper-bundle.min.js"></script>
<?php endif; ?>

<script>
// Method parameters configuration
const methodParams = {


applyPreset: `
    <div class="param-group">
        <div>
            <label class="form-label">Preset Name</label>
            <select name="params[preset_name]" class="form-select">
                <option value="vintage">Vintage</option>
                <option value="bright">Bright</option>
                <option value="cool">Cool</option>
                <option value="warm">Warm</option>
                <option value="noir">Noir (Grayscale)</option>
                <option value="soft">Soft</option>
                <option value="sharpen">Sharpen</option>
            </select>
        </div>
    </div>
`,


    resize: `
        <div class="param-group">
            <div>
                <label class="form-label">Width</label>
                <input type="number" name="params[width]" class="form-control" value="300" min="1">
            </div>
            <div>
                <label class="form-label">Height</label>
                <input type="number" name="params[height]" class="form-control" value="300" min="1">
            </div>
            <div>
                <label class="form-label">Keep Aspect Ratio</label>
                <select name="params[keep_aspect]" class="form-select">
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>
        </div>
    `,
    
    

crop: `
    <div class="param-group">
        <div>
            <label class="form-label">X (left)</label>
            <input type="number" name="params[x]" class="form-control" value="0" min="0">
        </div>
        <div>
            <label class="form-label">Y (top)</label>
            <input type="number" name="params[y]" class="form-control" value="0" min="0">
        </div>
        <div>
            <label class="form-label">Width</label>
            <input type="number" name="params[width]" class="form-control" value="200" min="1">
        </div>
        <div>
            <label class="form-label">Height</label>
            <input type="number" name="params[height]" class="form-control" value="200" min="1">
        </div>
    </div>
`,
    






rotate: `
    <div class="param-group">
        <div>
            <label class="form-label">Angle (degrees)</label>
            <input type="number" name="params[angle]" class="form-control" value="45" step="0.1">
            <small class="small-muted">Positive = clockwise, Negative = counter-clockwise</small>
        </div>
        <div>
            <label class="form-label">Expand Canvas</label>
            <select name="params[expand]" class="form-select">
                <option value="1">Yes (keep full image visible)</option>
                <option value="0">No (crop to original dimensions)</option>
            </select>
        </div>
    </div>
`,


    flip: `
        <div class="param-group">
            <div>
                <label class="form-label">Direction</label>
                <select name="params[direction]" class="form-select">
                    <option value="horizontal">Horizontal</option>
                    <option value="vertical">Vertical</option>
                </select>
            </div>
        </div>
    `,
    blur: `
        <div class="param-group">
            <div>
                <label class="form-label">Blur Radius</label>
                <input type="number" name="params[radius]" class="form-control" value="2" min="1" max="10">
            </div>
        </div>
    `,
    






adjustBrightness: `
    <div class="param-group">
        <div>
            <label class="form-label">Brightness Level: <span id="brightnessValue">1.0x</span></label>
            
            <!-- Slider with 25/25/25/25 distribution -->
            <input type="range" name="params[factor]" class="form-control" 
                   min="0" max="100" step="1" value="25"
                   oninput="updateBrightnessSlider(this.value)"
                   style="width: 100%; padding: 8px 0;">
            
            <!-- Range labels showing the distribution -->
            <div style="display: flex; justify-content: space-between; margin-top: 5px; font-size: 0.8rem; color: #666;">
                <span>0.0</span>
                <span>1.0</span>
                <span>2.0</span>
                <span>3.0</span>
                <span>100.0</span>
            </div>
            
            <!-- Hidden field to store the actual value -->
            <input type="hidden" id="brightnessActualValue" name="params[factor]" value="1.0">
        </div>
    </div>
`,





adjustContrast: `
    <div class="param-group">
        <div>
            <label class="form-label">Contrast Level: <span id="contrastValue">1.0x</span></label>
            
            <!-- Slider with 25/25/25/25 distribution -->
            <input type="range" name="params[factor]" class="form-control" 
                   min="0" max="100" step="1" value="25"
                   oninput="updateContrastSlider(this.value)"
                   style="width: 100%; padding: 8px 0;">
            
            <!-- Range labels showing the distribution -->
            <div style="display: flex; justify-content: space-between; margin-top: 5px; font-size: 0.8rem; color: #666;">
                <span>0.0</span>
                <span>1.0</span>
                <span>2.0</span>
                <span>3.0</span>
                <span>100.0</span>
            </div>
            
            <!-- Hidden field to store the actual value -->
            <input type="hidden" id="contrastActualValue" name="params[factor]" value="1.0">
        </div>
    </div>
`,










    createThumbnail: `
        <div class="param-group">
            <div>
                <label class="form-label">Max Width</label>
                <input type="number" name="params[max_width]" class="form-control" value="200" min="1">
            </div>
            <div>
                <label class="form-label">Max Height</label>
                <input type="number" name="params[max_height]" class="form-control" value="200" min="1">
            </div>
        </div>
    `,
    convert: `
        <div class="param-group">
            <div>
                <label class="form-label">Format</label>
                <select name="params[format]" class="form-select">
                    <option value="png">PNG</option>
                    <option value="jpeg">JPEG</option>
                    <option value="webp">WebP</option>
                    <option value="gif">GIF</option>
                </select>
            </div>
            <div>
                <label class="form-label">Quality (1-100)</label>
                <input type="number" name="params[quality]" class="form-control" value="85" min="1" max="100">
                <small class="small-muted">For JPEG and WebP only</small>
            </div>
        </div>
    `,
    getInfo: `
        <div class="param-group">
            <div>
                <small class="small-muted">This method returns image metadata without modifying the image.</small>
            </div>
        </div>
    `
};









// Helper function for contrast
function updateContrastSlider(sliderPos) {
    let actualValue;
    if (sliderPos <= 25) {
        // 0-25 on slider = 0-1 actual
        actualValue = (sliderPos / 25); // 0.0 to 1.0
    } else if (sliderPos <= 50) {
        // 26-50 on slider = 1-2 actual
        actualValue = 1 + ((sliderPos - 25) / 25); // 1.0 to 2.0
    } else if (sliderPos <= 75) {
        // 51-75 on slider = 2-3 actual
        actualValue = 2 + ((sliderPos - 50) / 25); // 2.0 to 3.0
    } else {
        // 76-100 on slider = 3-100 actual
        actualValue = 3 + ((sliderPos - 75) / 25) * 97; // 3.0 to 100.0
    }
    
    document.getElementById('contrastValue').textContent = actualValue.toFixed(2) + 'x';
    document.getElementById('contrastActualValue').value = actualValue;
}

// Initialize both sliders
function initSliders() {
    // Brightness slider starts at 1.0 (position 25)
    document.querySelector('input[name="params[factor]"]').value = 25;
    document.getElementById('brightnessActualValue').value = 1.0;
    document.getElementById('brightnessValue').textContent = '1.00x';
    
    // Contrast slider starts at 1.0 (position 25)
    document.querySelectorAll('input[name="params[factor]"]')[1].value = 25;
    document.getElementById('contrastActualValue').value = 1.0;
    document.getElementById('contrastValue').textContent = '1.00x';
}

document.addEventListener('DOMContentLoaded', initSliders);



// Helper function for brightness
function updateBrightnessSlider(sliderPos) {
    let actualValue;
    if (sliderPos <= 25) {
        // 0-25 on slider = 0-1 actual
        actualValue = (sliderPos / 25); // 0.0 to 1.0
    } else if (sliderPos <= 50) {
        // 26-50 on slider = 1-2 actual
        actualValue = 1 + ((sliderPos - 25) / 25); // 1.0 to 2.0
    } else if (sliderPos <= 75) {
        // 51-75 on slider = 2-3 actual
        actualValue = 2 + ((sliderPos - 50) / 25); // 2.0 to 3.0
    } else {
        // 76-100 on slider = 3-100 actual
        actualValue = 3 + ((sliderPos - 75) / 25) * 97; // 3.0 to 100.0
    }
    
    document.getElementById('brightnessValue').textContent = actualValue.toFixed(2) + 'x';
    document.getElementById('brightnessActualValue').value = actualValue;
}













// Show method parameters when method is selected
function showMethodParams(method) {
    const paramsContainer = document.getElementById('paramsContainer');
    const methodParamsDiv = document.getElementById('methodParams');
    
    if (method && methodParams[method]) {
        paramsContainer.innerHTML = methodParams[method];
        methodParamsDiv.style.display = 'block';
    } else {
        methodParamsDiv.style.display = 'none';
    }
}

// Update frame preview

// Update the updateFramePreview function to use direct path
function updateFramePreview(frameId) {
    const framePreview = document.getElementById('framePreview');
    const previewContent = document.getElementById('previewContent');
    
    if (!frameId) {
        framePreview.style.display = 'none';
        return;
    }
    
    const select = document.querySelector('select[name="frame_id"]');
    const selectedOption = select.options[select.selectedIndex];
    const filename = selectedOption.getAttribute('data-filename');
    const created = selectedOption.getAttribute('data-created');
    
    previewContent.innerHTML = `
        <div style="display:grid;grid-template-columns:auto 1fr;gap:15px;align-items:start;">
            <!-- CORRECTED: Use filename directly -->
            <img src="${filename}" 
                 alt="Preview" 
                 style="width:150px;height:150px;object-fit:cover;border-radius:6px;border:1px solid #ddd;cursor:pointer;"
                 onclick="openPhotoSwipe('${filename}')">
            <div>
                <strong>${selectedOption.textContent}</strong>
                <div class="small-muted" style="margin-top:8px;">
                    Filename: ${filename}<br>
                    Created: ${new Date(created).toLocaleString()}
                </div>
            </div>
        </div>
    `;
    
    framePreview.style.display = 'block';
}

// Also update the selectFrame function
function selectFrame(frameId, imageUrl, frameName) {
    const select = document.querySelector('select[name="frame_id"]');
    select.value = frameId;
    updateFramePreview(frameId);
    
    // Scroll to form
    document.getElementById('testForm').scrollIntoView({ behavior: 'smooth' });
}






// Clear test results
function clearTestResults() {
    if (confirm('Are you sure you want to clear all test results?')) {
        window.location.href = window.location.pathname;
    }
}

// Run health check
function runHealthCheck() {
    alert('Health check feature would be implemented here to test the Python API connection.');
    // You could implement an AJAX call to the health endpoint
}




// Replace the manual openPhotoSwipe with this:

let currentLightbox = null;

function openPhotoSwipe(src) {
    // If we already have a lightbox, destroy it first
    if (currentLightbox) {
        currentLightbox.destroy();
    }
    
    // Create a new lightbox for this single image
    currentLightbox = new PhotoSwipeLightbox({
        dataSource: [{
            src: src,
            width: 1024,
            height: 1024
        }],
            pswpModule: PhotoSwipe,
            initialZoomLevel: 'fit',      
            secondaryZoomLevel: 1,

            paddingFn: (viewportSize) => {

                return {            
                };              
            },                    
        bgOpacity: 0.8

    });

    
    currentLightbox.init();
    currentLightbox.loadAndOpen(0);
}



// Also update the lightbox initialization if you have it:
const lightbox = new PhotoSwipeLightbox({
    gallery: '.image-testing-wrap',
    children: 'img[onclick*="openPhotoSwipe"]',
    pswpModule: PhotoSwipe
    // No need for pswpCSS: true in v5
});

lightbox.init();




// Initialize Swiper for results if needed
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($testResults)): ?>
        const swiper = new Swiper('.swiper', {
            slidesPerView: 1,
            spaceBetween: 10,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            breakpoints: {
                640: { slidesPerView: 2 },
                768: { slidesPerView: 3 },
                1024: { slidesPerView: 4 }
            }
        });
    <?php endif; ?>
});
</script>

<?php
require "floatool.php";
$content = ob_get_clean();
$spw->renderLayout($content.$eruda, $pageTitle);
