<?php
// public/import_storyboard_11.php
// One-time script to ingest frames into Storyboard ID = 11.
// Chapter VII: The Return — the long absence bridge, Emily returns home.

require "error_reporting.php";
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require_once __DIR__ . '/StoryboardHelper.php';

$isCli = php_sapi_name() === 'cli';
$br = $isCli ? "\n" : "<br>\n";

echo "Starting one-time import to Storyboard ID 11..." . $br;

try {
    $spw = \App\Core\SpwBase::getInstance();
    $pdo = $spw->getPDO();

    $storyboardId = 11;

    // 6 frames — curated from storyboard 9 sorts 56-61:
    // The Long Absence (emotional bridge) → Emily Returns → Homecoming
    // → The Return (2) — with intentional repeats as visual echoes
    $frameIds = [
        113,        // The Long Absence — Theo's absence as sustained pressure
        130,        // Emily Returns to Alcaverde 407
        150,        // Homecoming
        260,        // The Return (2)
        130,        // Emily Returns (repeat — visual echo / return beat)
        260,        // The Return (2) (repeat — final image loop)
    ];

    $stmt = $pdo->prepare("SELECT name FROM storyboards WHERE id = ?");
    $stmt->execute([$storyboardId]);
    $sb = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sb) {
        die("Error: Storyboard ID {$storyboardId} does not exist." . $br);
    }
    echo "Found Target Storyboard: '{$sb['name']}'" . $br;

    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) as max_order FROM storyboard_frames WHERE storyboard_id = ?");
    $stmt->execute([$storyboardId]);
    $maxOrder = (int)$stmt->fetchColumn();

    $insertedCount = 0;
    $pdo->beginTransaction();

    foreach ($frameIds as $frameId) {
        $stmt = $pdo->prepare("SELECT name, filename FROM frames WHERE id = ?");
        $stmt->execute([$frameId]);
        $fData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fData) {
            $maxOrder++;
            $insert = $pdo->prepare("
                INSERT INTO storyboard_frames (storyboard_id, frame_id, name, filename, sort_order, is_copied, original_filename)
                VALUES (?, ?, ?, ?, ?, 0, ?)
            ");
            $insert->execute([
                $storyboardId,
                $frameId,
                $fData['name'],
                $fData['filename'],
                $maxOrder,
                $fData['filename']
            ]);
            $insertedCount++;
        } else {
            echo "Warning: Source Frame ID $frameId not found. Skipped." . $br;
        }
    }

    $pdo->commit();
    echo "Successfully queued {$insertedCount} frames in Storyboard {$storyboardId}." . $br;
    echo "Triggering file copy via StoryboardHelper..." . $br;

    $helper = new \App\Helper\StoryboardHelper($pdo, __DIR__);
    $copyResult = $helper->copyPendingFrames($storyboardId);

    echo "Physically copied " . count($copyResult['copied']) . " files to storyboard directory." . $br;

    if (!empty($copyResult['errors'])) {
        echo "Errors during file copy:" . $br;
        foreach ($copyResult['errors'] as $err) {
            echo " - " . htmlspecialchars($err) . $br;
        }
    } else {
        echo "Import complete — Storyboard 11 (Chapter VII: The Return). No errors." . $br;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Fatal Error: " . htmlspecialchars($e->getMessage()) . $br;
}
