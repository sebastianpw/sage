<?php
// public/ajax_frame_rating.php
require "error_reporting.php";
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

header('Content-Type: application/json; charset=utf-8');

try {
    // --- GET: Fetch Rating ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $frameId = (int)($_GET['frame_id'] ?? 0);
        if (!$frameId) throw new Exception('Missing frame ID');

        $stmt = $pdo->prepare("SELECT rating FROM frames WHERE id = ?");
        $stmt->execute([$frameId]);
        $rating = (int)$stmt->fetchColumn();

        echo json_encode(['success' => true, 'rating' => $rating]);
        exit;
    }

    // --- POST: Save Rating ---
    $frameId = (int)($_POST['frame_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);

    if (!$frameId) {
        throw new Exception('Missing frame ID');
    }

    if ($rating < 0) $rating = 0;
    if ($rating > 5) $rating = 5;

    $stmt = $pdo->prepare("UPDATE frames SET rating = ? WHERE id = ?");
    $stmt->execute([$rating, $frameId]);

    echo json_encode([
        'success' => true,
        'rating' => $rating,
        'frame_id' => $frameId,
        'message' => $rating > 0 ? "Rated {$rating} stars" : "Rating removed"
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
