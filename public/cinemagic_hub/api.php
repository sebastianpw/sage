<?php
/**
 * SAGE Cinemagic Hub — API Interface
 * public/cinemagic_hub/api.php
 */
session_start();
require_once __DIR__ . '/../bootstrap.php';
require_once PROJECT_ROOT . '/src/CinemagicHub/CinemagicHubManager.php';

use App\CinemagicHub\CinemagicHubManager;

global $pdo;

$spw = \App\Core\SpwBase::getInstance();
$publicPathAbs = $spw->getPublicPath();

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$previewActions = ['preview_series', 'preview_episode', 'serve_image', 'download_local_sitemap_json', 'download_global_sitemap_xml', 'download_pdf'];

if ($action !== 'export_series_zip' && !in_array($action, $previewActions)) {
    header('Content-Type: application/json');
}

try {
    $hub = new CinemagicHubManager($pdo);

    if ($action === 'export_series_zip') {
        $id = (int)($_REQUEST['id'] ?? 0);
        $excludeAssets = (isset($_REQUEST['exclude_assets']) && $_REQUEST['exclude_assets'] === '1');
        
        $zipPath = $hub->exportSeriesZip($id, $publicPathAbs, $excludeAssets);
        if ($zipPath && file_exists($zipPath)) {
            $filename = 'magazine_series_' . $id . ($excludeAssets ? '_light' : '') . '.zip';
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            unlink($zipPath);
        } else {
            die('Error generating ZIP. Ensure sequences exist in attached seasons.');
        }
        exit;
    }

    if ($action === 'download_local_sitemap_json') {
        $baseUrl = $_GET['base_url'] ?? '';
        if (!$baseUrl) die('Base URL required');
        $urls = $hub->generateLocalSitemapUrls($baseUrl);
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="local_sitemap_urls.json"');
        echo json_encode($urls, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'download_global_sitemap_xml') {
        $baseUrl = $_GET['base_url'] ?? '';
        if (!$baseUrl) die('Base URL required');
        $xml = $hub->buildGlobalSitemapXml($baseUrl);
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="sitemap.xml"');
        echo $xml;
        exit;
    }
    
    if ($action === 'download_pdf') {
        $relPath = ltrim(str_replace('..', '', $_GET['path'] ?? ''), '/');
        if (!str_starts_with($relPath, 'media/magazines/')) { 
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
        exit;
    }

    if ($action === 'preview_series') {
        $id = (int)($_REQUEST['id'] ?? 0);
        $lang = $_REQUEST['lang'] ?? 'en';
        header('Content-Type: text/html');
        
        $sStmt = $pdo->prepare("SELECT supported_languages FROM cinemagic_series WHERE id = ?");
        $sStmt->execute([$id]);
        $sRow = $sStmt->fetch(\PDO::FETCH_ASSOC);
        $langsRaw = $sRow['supported_languages'] ?? 'en';
        $langs = array_filter(array_map('trim', explode(',', $langsRaw)));
        if (!in_array('en', $langs)) array_unshift($langs, 'en');
        
        echo $hub->renderSeriesIndexHtml($id, true, '', false, '', $lang, $langs);
        exit;
    }

    if ($action === 'preview_episode') {
        $seriesId = (int)($_REQUEST['series_id'] ?? 0);
        $seqId    = (int)($_REQUEST['seq_id'] ?? 0);
        $lang     = $_REQUEST['lang'] ?? 'en';
        $linkFormat = "api.php?action=preview_episode&series_id={$seriesId}&seq_id=%d&lang={$lang}";
        
        $sStmt = $pdo->prepare("SELECT supported_languages FROM cinemagic_series WHERE id = ?");
        $sStmt->execute([$seriesId]);
        $sRow = $sStmt->fetch(\PDO::FETCH_ASSOC);
        $langsRaw = $sRow['supported_languages'] ?? 'en';
        $langs = array_filter(array_map('trim', explode(',', $langsRaw)));
        if (!in_array('en', $langs)) array_unshift($langs, 'en');

        $epData = $hub->getEpisodeData($seqId, '', false, $linkFormat, '', $lang);
        if (!$epData) die('Episode data not found or empty.');
        
        $epData['is_preview']      = true;
        $epData['series_id']       = $seriesId;
        $epData['available_langs'] = $langs;
        $epData['current_lang']    = $lang;
        
        header('Content-Type: text/html');
        echo $hub->renderEpisodeHtml($epData);
        exit;
    }

    switch ($action) {
        
        case 'get_series_episodes':
            $seriesId = (int)($_POST['series_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT ns.id, ns.name, c.name as season_name, sc.cinemagic_id
                FROM cinemagic_series_2_cinemagics sc
                JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = sc.cinemagic_id
                JOIN narrative_sequences ns ON ns.id = cs.sequence_id
                JOIN cinemagics c ON c.id = sc.cinemagic_id
                WHERE sc.series_id = ?
                ORDER BY sc.sort_order ASC, cs.sort_order ASC
            ");
            $stmt->execute([$seriesId]);
            echo json_encode(['success' => true, 'episodes' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
            break;
        
        case 'serve_image':
            $file = $_GET['file'] ?? '';
            $file = ltrim($file, '/');
            
            if (strpos($file, '..') !== false) {
                http_response_code(400);
                die('Invalid path');
            }
            
            $abs = rtrim($publicPathAbs, '/') . '/' . $file;
            
            if (file_exists($abs)) {
                $mime = mime_content_type($abs) ?: 'image/jpeg';
                header('Content-Type: ' . $mime, true);
                header('Content-Length: ' . filesize($abs));
                header('Cache-Control: max-age=86400');
                readfile($abs);
            } else {
                http_response_code(404);
                die('File not found');
            }
            exit;

        case 'get_stats':
            echo json_encode($hub->getDashboardStats());
            break;

        case 'get_published_magazines':
            echo json_encode($hub->getPublishedMagazines());
            break;
            
        case 'get_published_magazines_for_local':
            echo json_encode($hub->getPublishedMagazinesForLocal());
            break;
            
        case 'get_series_list':
            echo json_encode($hub->getSeriesList());
            break;

        case 'get_series_details':
            echo json_encode($hub->getSeriesDetails((int)$_POST['id']));
            break;

        case 'get_unassigned_seasons':
            echo json_encode($hub->getUnassignedSeasons((int)$_POST['series_id']));
            break;

        case 'save_series':
            echo json_encode($hub->saveSeries($_POST));
            break;

        case 'delete_series':
            echo json_encode($hub->deleteSeries((int)$_POST['id']));
            break;

        case 'assign_season':
            echo json_encode($hub->assignSeason((int)$_POST['series_id'], (int)$_POST['cinemagic_id']));
            break;

        case 'remove_season':
            echo json_encode($hub->removeSeason((int)$_POST['series_id'], (int)$_POST['cinemagic_id']));
            break;

        // ── Series Seasons layer ──────────────────────────────────────────────────

        case 'get_series_seasons':
            echo json_encode($hub->getSeriesSeasons((int)$_POST['series_id']));
            break;

        case 'save_series_season':
            echo json_encode($hub->saveSeriesSeason((int)$_POST['series_id'], $_POST));
            break;

        case 'delete_series_season':
            echo json_encode($hub->deleteSeriesSeason((int)$_POST['series_id'], (int)$_POST['season_id']));
            break;

        case 'assign_cinemagic_to_series_season':
            echo json_encode($hub->assignCinemagicToSeriesSeason(
                (int)$_POST['series_id'],
                (int)$_POST['cinemagic_id'],
                (int)$_POST['season_id']
            ));
            break;

        case 'remove_cinemagic_from_series_season':
            echo json_encode($hub->removeCinemagicFromSeriesSeason(
                (int)$_POST['series_id'],
                (int)$_POST['cinemagic_id']
            ));
            break;

        case 'save_cinemagic_cover':
            echo json_encode($hub->saveCinemagicCover((int)$_POST['series_id'], (int)$_POST['cinemagic_id'], $_POST['cover_image_url'] ?? ''));
            break;

        // ── Episode meta ──────────────────────────────────────────────────────────

        case 'get_episode_meta':
            echo json_encode(['success' => true, 'meta' => $hub->getEpisodeMeta((int)$_POST['cinemagic_id'], (int)$_POST['sequence_id'])]);
            break;

        case 'save_episode_meta':
            echo json_encode($hub->saveEpisodeMeta((int)$_POST['cinemagic_id'], (int)$_POST['sequence_id'], $_POST));
            break;

        case 'get_sitemap_imports':
            echo json_encode($hub->getSitemapImports());
            break;

        case 'import_sitemap_json':
            $name = trim($_POST['system_name'] ?? '');
            $urls = json_decode($_POST['urls_json'] ?? '[]', true) ?: [];
            echo json_encode($hub->importSitemapJson($name, $urls));
            break;

        case 'delete_sitemap_import':
            echo json_encode($hub->deleteSitemapImport((int)$_POST['id']));
            break;

        case 'search_sequences':
            echo json_encode($hub->searchSequences($_POST['q'] ?? '', (int)($_POST['page'] ?? 1)));
            break;

        case 'search_cinemagics':
            echo json_encode($hub->searchCinemagics($_POST['q'] ?? '', (int)($_POST['page'] ?? 1)));
            break;

        case 'get_sequence_frames':
            echo json_encode(['success' => true, 'assets' => $hub->getSequenceFrames((int)$_POST['sequence_id'])]);
            break;

        case 'rollout_series':
            echo json_encode($hub->rolloutSeries((int)$_POST['id'], $publicPathAbs));
            break;

        case 'get_languages':
            echo json_encode($hub->getSystemLanguages());
            break;

        case 'save_language':
            echo json_encode($hub->saveSystemLanguage($_POST['code'] ?? '', $_POST['name'] ?? ''));
            break;

        case 'delete_language':
            echo json_encode($hub->deleteSystemLanguage($_POST['code'] ?? ''));
            break;
            
        case 'get_cinemagic_pdfs':
            $seriesId = (int)($_POST['series_id'] ?? 0);
            if (!$seriesId) {
                echo json_encode(['success' => false, 'error' => 'series_id required']);
                break;
            }
            echo json_encode($hub->getCinemagicPdfsForSeries($seriesId));
            break;

        case 'submit_pdf_export_job':
            $seriesId = (int)($_POST['series_id'] ?? 0);
            $seqId    = (int)($_POST['sequence_id'] ?? 0);
            $langs    = $_POST['languages'] ?? 'en';
            $langsArr = array_filter(array_map('trim', explode(',', $langs)));
            $pyapiUrl = rtrim($_POST['pyapi_url'] ?? '', '/');

            if (!$seriesId || !$seqId || !$pyapiUrl || empty($langsArr)) {
                echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
                break;
            }

            echo json_encode($hub->submitPdfJobToPyAPI($seriesId, $seqId, $langsArr, $pyapiUrl, $publicPathAbs));
            break;

        case 'poll_pyapi_job':
            $jobDbId  = (int)($_POST['job_db_id'] ?? 0);
            $pyJobId  = $_POST['pyapi_job_id'] ?? '';
            $pyapiUrl = $_POST['pyapi_url'] ?? '';

            if (!$jobDbId || !$pyJobId || !$pyapiUrl) {
                echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
                break;
            }

            $url = rtrim($pyapiUrl, '/') . '/magazine-pdf/status/' . $pyJobId;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 400 || !$response) {
                echo json_encode(['success' => false, 'error' => "PyAPI returned HTTP $httpCode: $err"]);
                break;
            }

            $data = json_decode($response, true);
            if (!$data) {
                echo json_encode(['success' => false, 'error' => 'Invalid JSON from PyAPI']);
                break;
            }

            $status = $data['status'] ?? 'pending';
            $errMsg = $data['error_message'] ?? null;

            $stmt = $pdo->prepare(
                "UPDATE magazine_pdf_jobs SET status = :status, error_message = :err WHERE id = :id"
            );
            $stmt->execute([':status' => $status, ':err' => $errMsg, ':id' => $jobDbId]);

            if ($status === 'done') {
                $hub->fetchAndStorePdfJob($jobDbId, $pyJobId, $pyapiUrl, $publicPathAbs);
            }

            echo json_encode(['success' => true, 'status' => $status, 'error_message' => $errMsg]);
            break;

        case 'update_pdf_job_status':
            $jobDbId  = (int)($_POST['job_db_id'] ?? 0);
            $status   = $_POST['status'] ?? '';
            $pyJobId  = $_POST['pyapi_job_id'] ?? '';
            $pyapiUrl = $_POST['pyapi_url'] ?? '';
            
            $allowed = ['pending', 'processing', 'done', 'error'];
            if (!$jobDbId || !in_array($status, $allowed)) {
                echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
                break;
            }
            $errMsg = $_POST['error_message'] ?? null;
            $stmt = $pdo->prepare(
                "UPDATE magazine_pdf_jobs SET status = :status, error_message = :err WHERE id = :id"
            );
            $stmt->bindValue(':status', $status,  \PDO::PARAM_STR);
            $stmt->bindValue(':err',    $errMsg,  $errMsg === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
            $stmt->bindValue(':id',     $jobDbId, \PDO::PARAM_INT);
            $stmt->execute();
            
            if ($status === 'done' && $pyJobId && $pyapiUrl) {
                $hub->fetchAndStorePdfJob($jobDbId, $pyJobId, $pyapiUrl, $publicPathAbs);
            }
            
            echo json_encode(['success' => true]);
            break;

        case 'get_pdf_jobs':
            $seriesId = (int)($_POST['series_id'] ?? 0);
            $stmt = $pdo->prepare(
                "SELECT id, sequence_id, languages, status, error_message, created_at, updated_at
                   FROM magazine_pdf_jobs
                  WHERE series_id = :sid
                  ORDER BY created_at DESC
                  LIMIT 20"
            );
            $stmt->bindValue(':sid', $seriesId, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'jobs' => $rows]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
    }
} catch (\Throwable $e) {
    if ($action === 'export_series_zip' || in_array($action, $previewActions)) die($e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


