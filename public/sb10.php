<?php
// public/import_storyboard_10.php
// One-time script to ingest frames into Storyboard ID = 10.
// Chapter VI: The Monastery — mountain approach, audit, Theo arrives,
// reunion, drawing revealed, compass and scarf exchange.

require "error_reporting.php";
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require_once __DIR__ . '/StoryboardHelper.php';

$isCli = php_sapi_name() === 'cli';
$br = $isCli ? "\n" : "<br>\n";

echo "Starting one-time import to Storyboard ID 10..." . $br;

try {
    $spw = \App\Core\SpwBase::getInstance();
    $pdo = $spw->getPDO();

    $storyboardId = 10;

    // 34 frames — curated from storyboard 9 sorts 22-55:
    // Mountain approach → monastery gate → Lentio's question → water vessel
    // → epistemology of silence → tea → forty years → cell observation
    // → interior audit × 3 → emergence → courtyard reunion
    // → Theo arrives → reunion at gate → honest love
    // → drawing revealed → compass & scarf exchange × 3 → exchange of objects
    $frameIds = [
        154, 187, 196,  70,   // mountain approach: Spine, Pass Chokepoint,
                               //   Cloud Parting, Mountain Spine (concept)
        231, 121, 155, 197,   // monastery: Cliff Approach, Gates 433,
                               //   Monastery of Stilled Breath, Gate at Dawn
        127,  79, 204,  81,   // arrival & question, Weight of Water,
                               //   Epistemology of Silence, The Tea
         82, 232,  74, 128,   // Forty Years, Cell Observation,
                               //   Interior Audit, Audit Witnessing
        233,  80, 259, 180,   // Cell Confession, Work of Presence,
                               //   Interior Audit (3), Confession/Becoming
         83, 129,             // Emily's Emergence, Courtyard Reunion 809
        105,                   // Recognition at the Gate (callback/echo)
        116, 234, 108, 122,   // Courtyard Exchange, Theo Arrives,
                               //   Reunion at Gate 321, Honest Love 618
        116, 257, 122, 235,   // Courtyard Exchange (repeat), Drawing Revealed,
                               //   Honest Love (repeat), Compass & Scarf Exchange
        116, 250, 128,        // Courtyard Exchange (repeat), Exchange of Objects,
                               //   Audit Witnessing (echo/flashback)
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
        echo "Import complete — Storyboard 10 (Chapter VI: The Monastery). No errors." . $br;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Fatal Error: " . htmlspecialchars($e->getMessage()) . $br;
}
