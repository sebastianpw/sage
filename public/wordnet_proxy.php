<?php
// public/wordnet_proxy.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

use App\Wordnet\WordnetBridge;

// Autoload (composer) is already present in bootstrap
$logger = $spw->getFileLogger();

$bridge = new App\Wordnet\WordnetBridge($logger);

// Basic allow list for actions to avoid arbitrary requests
$allowed = ['lemma', 'synset', 'search', 'hypernyms', 'debug'];

$action = $_GET['action'] ?? ($_POST['action'] ?? null);

if (!$action || !in_array($action, $allowed)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'lemma':
            $lemma = $_GET['lemma'] ?? $_POST['lemma'] ?? '';
            if ($lemma === '') {
                throw new Exception('Missing lemma');
            }
            $res = $bridge->lemma($lemma);
            echo json_encode(['status' => 'ok', 'data' => $res]);
            exit;

        case 'synset':
            $synsetid = isset($_GET['synsetid']) ? (int)$_GET['synsetid'] : (isset($_POST['synsetid']) ? (int)$_POST['synsetid'] : 0);
            if ($synsetid <= 0) throw new Exception('Invalid synset id');
            $res = $bridge->synset($synsetid);
            echo json_encode(['status' => 'ok', 'data' => $res]);
            exit;

        case 'search':
            $q = $_GET['q'] ?? $_POST['q'] ?? '';
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($_POST['limit']) ? (int)$_POST['limit'] : 50);
            if ($q === '') throw new Exception('Missing query');
            $res = $bridge->search($q, $limit);
            echo json_encode(['status' => 'ok', 'data' => $res]);
            exit;

        case 'hypernyms':
            $synsetid = isset($_GET['synsetid']) ? (int)$_GET['synsetid'] : (isset($_POST['synsetid']) ? (int)$_POST['synsetid'] : 0);
            if ($synsetid <= 0) throw new Exception('Invalid synset id');
            $res = $bridge->hypernyms($synsetid);
            echo json_encode(['status' => 'ok', 'data' => $res]);
            exit;

        case 'debug':
            $res = $bridge->debug();
            echo json_encode(['status' => 'ok', 'data' => $res]);
            exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
