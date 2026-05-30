<?php
// check_punctuation.php
// Usage: php check_punctuation.php <START_ID>
// Example: php check_punctuation.php 500

require_once "bootstrap.php";
require "env_locals.php";
global $pdo;
mb_internal_encoding('UTF-8');

// ---------------------------------------------------------
// CONFIGURATION
// ---------------------------------------------------------
$SAFETY_THRESHOLD = 500; // Characters. Piper usually crashes around 500-1000 without breaks.
$TABLE_NAME = 'audio_dialogue_lines';

// ---------------------------------------------------------
// ARGUMENT PARSING
// ---------------------------------------------------------
if (!isset($argv[1])) {
    die("Usage: php check_punctuation.php <START_ID>\nExample: php check_punctuation.php 1500\n");
}

$startId = (int)$argv[1];

echo "------------------------------------------------------------\n";
echo " PIPER SAFETY CHECKER\n";
echo " Scanning table '$TABLE_NAME' from ID $startId...\n";
echo " Safety Threshold: $SAFETY_THRESHOLD characters without punctuation.\n";
echo "------------------------------------------------------------\n";

// ---------------------------------------------------------
// FETCH DATA
// ---------------------------------------------------------
$sql = "SELECT id, name, description FROM $TABLE_NAME WHERE id >= :startId ORDER BY id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':startId' => $startId]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    die("No entries found starting from ID $startId.\n");
}

$failCount = 0;
$passCount = 0;

foreach ($rows as $row) {
    $id = $row['id'];
    $name = $row['name'];
    $text = $row['description'];
    
    // Calculate the longest run of text without sentence terminators
    $maxRun = get_max_unpunctuated_run($text);
    $totalLen = mb_strlen($text);

    // Determine Status
    if ($maxRun > $SAFETY_THRESHOLD) {
        $status = "\033[31mDANGEROUS\033[0m"; // Red text
        $failCount++;
    } else {
        $status = "\033[32mSAFE\033[0m";      // Green text
        $passCount++;
    }

    // Output only if dangerous or verbose (uncomment else block for full list)
    if ($maxRun > $SAFETY_THRESHOLD) {
        echo sprintf(
            "[%s] ID: %-6d | Name: %-20s | Len: %-5d | Max Run: %-4d\n",
            $status, $id, substr($name, 0, 20), $totalLen, $maxRun
        );
    } 
    // else { echo "."; } // Progress dots for safe ones
}

echo "\n------------------------------------------------------------\n";
echo " SCAN COMPLETE\n";
echo " Passed: $passCount\n";
echo " Failed: $failCount\n";

if ($failCount > 0) {
    echo "\033[31mWARNING: found $failCount entries that might crash Piper.\033[0m\n";
    echo "You should re-import these or update them manually.\n";
} else {
    echo "\033[32mAll checked entries look safe for TTS generation.\033[0m\n";
}


// ---------------------------------------------------------
// HELPER FUNCTION
// ---------------------------------------------------------
function get_max_unpunctuated_run($text) {
    // Treat newlines as punctuation for safety calculation? 
    // Piper usually needs real punctuation, but newlines often trigger pauses depending on processing.
    // The "Lawn Mower" importer inserts '.' so we look for that.
    
    // We split by punctuation marks: . ! ? : ;
    // We do NOT count commas as full stops for memory clearing purposes.
    $parts = preg_split('/[\.!\?:\;]+/', $text);
    
    $maxLen = 0;
    
    if ($parts) {
        foreach ($parts as $part) {
            $len = mb_strlen(trim($part));
            if ($len > $maxLen) {
                $maxLen = $len;
            }
        }
    }
    
    return $maxLen;
}
?>
