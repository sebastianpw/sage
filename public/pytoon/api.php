<?php
/**
 * SAGE Pytoon — API
 * public/pytoon/api.php
 */
session_start();
require_once __DIR__ . '/../bootstrap.php';
require_once PROJECT_ROOT . '/src/Pytoon/PytoonManager.php';

use App\Pytoon\PytoonManager;

global $pdo;

$spw           = \App\Core\SpwBase::getInstance();
$publicPathAbs = $spw->getPublicPath();

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// Download actions skip JSON header
$downloadActions = ['download_zip', 'download_cover', 'compose_cover_download', 'download_pdf'];

if (!in_array($action, $downloadActions)) {
    header('Content-Type: application/json; charset=utf-8');
}

try {
    $pyapiUrl = $_POST['pyapi_url']
        ?? $_GET['pyapi_url']
        ?? (getenv('PYTOON_PYAPI_URL') ?: 'http://127.0.0.1:8009');

    $mgr = new PytoonManager($pdo, $pyapiUrl, $publicPathAbs);

    switch ($action) {

        // ── Listing ─────────────────────────────────────────────────────────
        case 'get_series_list':
            echo json_encode($mgr->getSeriesList());
            break;

        case 'get_pdf_inbox':
            echo json_encode($mgr->getPdfInboxList());
            break;

        case 'get_series_episodes':
            echo json_encode($mgr->getSeriesEpisodes((int)($_POST['series_id'] ?? 0)));
            break;

        case 'get_jobs':
            echo json_encode($mgr->getJobs(40));
            break;

        case 'get_saved_covers':
            echo json_encode($mgr->getSavedCovers());
            break;

        // ── Canvas sizes ─────────────────────────────────────────────────────
        case 'get_canvas_sizes':
            echo json_encode($mgr->getCanvasSizes());
            break;

        case 'add_canvas_size':
            $label  = trim($_POST['label']  ?? '');
            $width  = (int)($_POST['width']  ?? 0);
            $height = (int)($_POST['height'] ?? 0);
            if (!$label) $label = "{$width} × {$height}";
            echo json_encode($mgr->addCanvasSize($label, $width, $height));
            break;

        case 'delete_canvas_size':
            echo json_encode($mgr->deleteCanvasSize((int)($_POST['id'] ?? 0)));
            break;

        // ── Cinemagic PDF browser ────────────────────────────────────────────
        case 'get_cinemagic_pdfs':
            $seriesId = (int)($_POST['series_id'] ?? 0);
            if (!$seriesId) {
                echo json_encode(['success' => false, 'error' => 'series_id required']);
                break;
            }
            echo json_encode($mgr->getCinemagicPdfsForSeries($seriesId));
            break;

        case 'split_cinemagic_pdf':
            $relPath = $_POST['rel_path'] ?? '';
            $dpi     = (int)($_POST['dpi']     ?? 150);
            $quality = (int)($_POST['quality'] ?? 88);
            echo json_encode($mgr->splitCinemagicPdf($relPath, $dpi, $quality));
            break;

        // ── PDF split ────────────────────────────────────────────────────────
        case 'upload_and_split':
            if (empty($_FILES['pdf_file'])) {
                echo json_encode(['success' => false, 'error' => 'No file uploaded']);
                break;
            }
            $dpi     = (int)($_POST['dpi']     ?? 150);
            $quality = (int)($_POST['quality'] ?? 88);
            echo json_encode($mgr->handleUploadedPdf($_FILES['pdf_file'], $dpi, $quality));
            break;

        case 'split_inbox_pdf':
            $relPath = $_POST['rel_path'] ?? '';
            $dpi     = (int)($_POST['dpi']     ?? 150);
            $quality = (int)($_POST['quality'] ?? 88);
            echo json_encode($mgr->reprocessInboxPdf($relPath, $dpi, $quality));
            break;

        case 'poll_pdf_job':
            $jobDbId    = (int)($_POST['job_db_id']    ?? 0);
            $pyapiJobId = $_POST['pyapi_job_id'] ?? '';
            if (!$jobDbId || !$pyapiJobId) {
                echo json_encode(['success' => false, 'error' => 'Missing params']);
                break;
            }
            echo json_encode($mgr->pollPdfJob($jobDbId, $pyapiJobId));
            break;

        // ── Cover compose ────────────────────────────────────────────────────
        case 'compose_cover_save':
            if (empty($_FILES['cover_file'])) {
                echo json_encode(['success' => false, 'error' => 'No cover file uploaded']);
                break;
            }
            $x        = (float)($_POST['x']        ?? 0);
            $y        = (float)($_POST['y']         ?? 0);
            $scale    = (float)($_POST['scale']     ?? 1.0);
            $canvasW  = (int)  ($_POST['canvas_w']  ?? 1080);
            $canvasH  = (int)  ($_POST['canvas_h']  ?? 1920);
            $quality  = (int)  ($_POST['quality']   ?? 92);
            $label    = trim   ($_POST['label']      ?? 'cover');

            $result = $mgr->composeCoverFromUpload($_FILES['cover_file'], $x, $y, $scale, $canvasW, $canvasH, $quality);
            if (!$result['success']) {
                echo json_encode($result);
                break;
            }
            $relUrl = $mgr->saveComposedCover($result['jpeg_bytes'], $label, $canvasW, $canvasH);
            echo json_encode(['success' => true, 'url' => '/' . $relUrl, 'rel' => $relUrl]);
            break;

        case 'compose_cover_download':
            // Return JPEG directly to browser
            if (empty($_FILES['cover_file'])) { http_response_code(400); die('No file'); }
            $x       = (float)($_POST['x']        ?? 0);
            $y       = (float)($_POST['y']         ?? 0);
            $scale   = (float)($_POST['scale']     ?? 1.0);
            $canvasW = (int)  ($_POST['canvas_w']  ?? 1080);
            $canvasH = (int)  ($_POST['canvas_h']  ?? 1920);
            $quality = (int)  ($_POST['quality']   ?? 92);
            $label   = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($_POST['label'] ?? 'cover'));

            $result = $mgr->composeCoverFromUpload($_FILES['cover_file'], $x, $y, $scale, $canvasW, $canvasH, $quality);
            if (!$result['success']) { http_response_code(500); die($result['error']); }

            header('Content-Type: image/jpeg');
            header("Content-Disposition: attachment; filename=\"{$label}_{$canvasW}x{$canvasH}.jpg\"");
            header('Content-Length: ' . strlen($result['jpeg_bytes']));
            echo $result['jpeg_bytes'];
            break;

        // ── Downloads ────────────────────────────────────────────────────────
        case 'download_zip':
            $relPath = ltrim(str_replace('..', '', $_GET['path'] ?? ''), '/');
            if (!str_starts_with($relPath, 'media/webtoon/')) { http_response_code(403); die('Forbidden'); }
            $abs = $publicPathAbs . '/' . $relPath;
            if (!file_exists($abs)) { http_response_code(404); die('Not found'); }
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($abs) . '"');
            header('Content-Length: ' . filesize($abs));
            readfile($abs);
            break;

        case 'download_cover':
            $relPath = ltrim(str_replace('..', '', $_GET['path'] ?? ''), '/');
            if (!str_starts_with($relPath, 'media/webtoon/covers/')) { http_response_code(403); die('Forbidden'); }
            $abs = $publicPathAbs . '/' . $relPath;
            if (!file_exists($abs)) { http_response_code(404); die('Not found'); }
            header('Content-Type: image/jpeg');
            header('Content-Disposition: attachment; filename="' . basename($abs) . '"');
            header('Content-Length: ' . filesize($abs));
            readfile($abs);
            break;

        case 'download_pdf':
            $relPath = ltrim(str_replace('..', '', $_GET['path'] ?? ''), '/');
            // Safe bounds: Only allow downloading from the allowed PDF directories
            if (!str_starts_with($relPath, 'media/magazines/') && !str_starts_with($relPath, 'media/webtoon/pdf_inbox/')) { 
                http_response_code(403); die('Forbidden'); 
            }
            $abs = $publicPathAbs . '/' . $relPath;
            if (!file_exists($abs) || !str_ends_with(strtolower($abs), '.pdf')) { 
                http_response_code(404); die('Not found'); 
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($abs) . '"');
            header('Content-Length: ' . filesize($abs));
            readfile($abs);
            break;

        // ── Delete ───────────────────────────────────────────────────────────
        case 'delete_asset':
            echo json_encode($mgr->deleteAsset($_POST['rel_path'] ?? ''));
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
    }
} catch (\Throwable $e) {
    if (in_array($action, $downloadActions)) {
        http_response_code(500);
        die($e->getMessage());
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}