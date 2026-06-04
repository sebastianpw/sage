<?php
// public/content_hub/api.php
session_start();
require_once __DIR__ . '/../bootstrap.php';
require_once PROJECT_ROOT . '/src/ContentHub/ContentHubManager.php';

use App\ContentHub\ContentHubManager;

global $pdo, $publicPathAbs;

if (empty($_GET['action']) || $_GET['action'] !== 'preview_grid' && $_GET['action'] !== 'preview_post') {
    header('Content-Type: application/json');
}

try {
    $hub = new ContentHubManager($pdo);
    $action = $_POST['action'] ?? ($_GET['action'] ?? '');

    // Handle File Downloads (Export endpoints)
    if (in_array($action, ['export_grid', 'export_post_zip', 'export_post_html', 'export_all_html', 'preview_grid', 'preview_post'])) {

        if ($action === 'preview_grid') {
            $html = $hub->exportGridHtml(true);
            header('Content-Type: text/html');
            echo $html;
            exit;
        }

        if ($action === 'preview_post') {
            $id = (int)($_GET['id'] ?? 0);
            $post = $hub->getPostById($id);
            
            if ($post) {
                $html = $hub->renderPostHtml($post, false, '', false);
                $html = str_replace('href="./"', 'href="api.php?action=preview_grid"', $html);
                header('Content-Type: text/html');
                echo $html;
                exit;
            }
            die("Post not found");
        }

        if ($action === 'export_grid') {
            $html = $hub->exportGridHtml(false);
            header('Content-Type: text/html');
            header('Content-Disposition: attachment; filename="index.html"');
            echo $html;
            exit;
        }

        if ($action === 'export_post_html') {
            $id = (int)($_REQUEST['id'] ?? 0);
            $post = $hub->getPostById($id);
            if ($post) {
                if ($post['post_type'] === 'magazine_highlight') {
                    die("Magazine highlights are embedded directly within the grid and do not have standalone HTML pages.");
                }
                $html = $hub->renderPostHtml($post, true, $post['asset_url_prefix'] ?? '', true);
                header('Content-Type: text/html');
                header('Content-Disposition: attachment; filename="' . $post['slug'] . '.html"');
                echo $html;
                exit;
            }
            die("Post not found.");
        }

        if ($action === 'export_post_zip') {
            $id = (int)($_REQUEST['id'] ?? 0);
            $zipPath = $hub->exportPostZip($id, $publicPathAbs);
            if ($zipPath && file_exists($zipPath)) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="post_' . $id . '_export.zip"');
                header('Content-Length: ' . filesize($zipPath));
                readfile($zipPath);
                unlink($zipPath);
                exit;
            }
            die("Error generating POST ZIP.");
        }

        if ($action === 'export_all_html') {
            $zipPath = $hub->exportAllHtml();
            if ($zipPath && file_exists($zipPath)) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="content_hub_export.zip"');
                header('Content-Length: ' . filesize($zipPath));
                readfile($zipPath);
                unlink($zipPath);
                exit;
            }
            die("Error generating FULL EXPORT ZIP.");
        }
    }

    // Handle Standard JSON API endpoints
    switch ($action) {
        case 'get_calendar':
            $year  = (int)($_POST['year']  ?? date('Y'));
            $month = (int)($_POST['month'] ?? date('n'));
            echo json_encode($hub->getCalendarData($year, $month));
            exit;

        case 'get_posts':
            $filters = [
                'platform' => $_POST['platform'] ?? '',
                'status'   => $_POST['status']   ?? '',
                'search'   => $_POST['search']   ?? '',
                'page'     => (int)($_POST['page'] ?? 1),
            ];
            echo json_encode($hub->getPosts($filters));
            exit;

        case 'save_post':
            $result = $hub->savePost($_POST, $publicPathAbs);
            echo json_encode($result);
            exit;

        case 'delete_post':
            $result = $hub->deletePost((int)$_POST['id'], $publicPathAbs);
            echo json_encode($result);
            exit;

        case 'update_status':
            $result = $hub->updatePostStatus((int)$_POST['id'], $_POST['status']);
            echo json_encode($result);
            exit;

        case 'get_post':
            $post = $hub->getPostById((int)$_POST['id']);
            echo json_encode(['success' => (bool)$post, 'post' => $post]);
            exit;

        case 'get_stats':
            echo json_encode($hub->getDashboardStats());
            exit;

        case 'get_assets':
            $type      = $_POST['type'] ?? 'videos';
            $q         = $_POST['q']    ?? '';
            $mapRunId  = (int)($_POST['map_run_id'] ?? 0);
            echo json_encode($hub->searchAssets($type, $q, $mapRunId));
            exit;

        case 'search_containers':
            $type = $_POST['container_type'] ?? 'map_runs';
            $q = $_POST['q'] ?? '';
            $page = (int)($_POST['page'] ?? 1);
            echo json_encode($hub->searchContainers($type, $q, $page));
            exit;

        case 'get_container_frames':
            $type = $_POST['container_type'] ?? 'map_runs';
            $id = (int)($_POST['container_id'] ?? 0);
            echo json_encode(['success' => true, 'assets' => $hub->getContainerFrames($type, $id)]);
            exit;

        case 'rollout_post':
            $result = $hub->rolloutPost((int)$_POST['id'], $publicPathAbs);
            echo json_encode($result);
            exit;

        case 'get_published_episodes':
            echo json_encode($hub->getPublishedEpisodes());
            exit;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            exit;
    }
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}



