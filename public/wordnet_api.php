<?php
// public/wordnet_api.php
require_once __DIR__ . '/bootstrap.php';
require_once PROJECT_ROOT . '/src/Service/WordnetApi.php'; // adjust autoload path if you use composer

use App\Services\WordnetApi;

header('Content-Type: application/json; charset=utf-8');

$wn = new WordnetApi('http://127.0.0.1:8009');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;

try {
    switch ($action) {
        case 'lemma':
            $q = $payload['q'] ?? $_GET['q'] ?? '';
            if (!$q) throw new Exception('Missing query');
            $resp = $wn->lemma($q);
            break;
        case 'synset':
            $id = (int)($payload['id'] ?? $_GET['id'] ?? 0);
            if (!$id) throw new Exception('Missing id');
            $resp = $wn->synset($id);
            break;
        case 'search':
            $q = $payload['q'] ?? $_GET['q'] ?? '';
            $limit = (int)($payload['limit'] ?? $_GET['limit'] ?? 50);
            if (!$q) throw new Exception('Missing q');
            $resp = $wn->search($q, $limit);
            break;
        case 'hypernyms':
            $id = (int)($payload['id'] ?? $_GET['id'] ?? 0);
            if (!$id) throw new Exception('Missing id');
            $resp = $wn->hypernyms($id);
            break;
        case 'morph':
            $m = $payload['m'] ?? $_GET['m'] ?? '';
            if (!$m) throw new Exception('Missing morph');
            $resp = $wn->morph($m);
            break;
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
            exit;
    }

    if ($resp['ok']) {
        echo json_encode(['status' => 'ok', 'data' => $resp['data']]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $resp['error'] ?? 'Unknown', 'http_status' => $resp['status']]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
