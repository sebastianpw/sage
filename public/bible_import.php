<?php
// bible_import_fixed.php
// THE "LINE-BY-LINE" ENFORCER
// 1. Adds periods to ends of lines.
// 2. Breaks long sentences internally.
// Usage: php bible_import_fixed.php --commit

require_once "bootstrap.php";
require "env_locals.php";
global $pdo;
mb_internal_encoding('UTF-8');

//////////////////////
// CONFIG
//////////////////////

$inputFile           = __DIR__ . '/sg_prot_incmpl.txt';
$chunkSize           = 10000;      // Target chars per chunk
$chunkAmount         = 0;         // 0 = all
$dryRun              = true;      // default; use --commit to write$namePrefix          = 'SG 1ST SUMM. Bible chunk';
$namePrefix          = 'SG PROT INCMPL Bible chunk';
$maxSafeLength       = 500;       // STRICT LIMIT

//////////////////////
// CLI
//////////////////////

foreach ($argv as $a) {
    if ($a === '--commit') $dryRun = false;
    if ($a === '--all') $chunkAmount = 0;
}

//////////////////////
// LOGIC
//////////////////////

/**
 * 1. Ensure every line ends with punctuation.
 * 2. Ensure no line is longer than $limit without punctuation.
 */
function sanitize_text_robust(string $text, int $limit): string {
    // Normalize newlines
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $text);
    $processedLines = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        // STEP A: Apply "Sledgehammer" length limit INSIDE the line
        // (In case one single line is huge)
        $line = force_punctuation_inside_line($line, $limit);

        // STEP B: Ensure line ends with punctuation
        $lastChar = mb_substr($line, -1);
        if (!preg_match('/[\.!\?:\;]$/u', $lastChar)) {
            $line .= '.'; // Force period at end of line
        }

        $processedLines[] = $line;
    }

    // Join back with double newlines for clear separation
    return implode("\n\n", $processedLines);
}

/**
 * Breaks a single string if it runs too long without stops.
 */
function force_punctuation_inside_line(string $text, int $limit): string {
    $len = mb_strlen($text);
    if ($len <= $limit) return $text;

    $out = "";
    $buffer = "";
    
    // Split into words to avoid breaking words in half
    // But track length carefully
    $words = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    
    $currentRun = 0;
    
    foreach ($words as $w) {
        $wLen = mb_strlen($w);
        
        // If this is just a space/separator
        if (trim($w) === '') {
            $buffer .= $w;
            $currentRun += $wLen;
            continue;
        }

        // Check if this word resets the run (has punctuation)
        if (preg_match('/[\.!\?:\;]/u', $w)) {
            $out .= $buffer . $w;
            $buffer = "";
            $currentRun = 0;
            continue;
        }

        // If adding this word exceeds limit, inject period BEFORE it (at end of buffer)
        if (($currentRun + $wLen) > $limit) {
            if ($buffer === '') {
                // Word itself is massive? Just append dot to it.
                $out .= $w . ". ";
            } else {
                // Append dot to previous buffer
                $out .= trim($buffer) . ". " . $w;
            }
            $buffer = "";
            $currentRun = $wLen; 
        } else {
            $buffer .= $w;
            $currentRun += $wLen;
        }
    }
    
    $out .= $buffer;
    return $out;
}

/**
 * Split huge text into chunk-sized pieces
 */
function chunk_text(string $text, int $targetSize): array {
    // We already sanitized newlines in step 1, so we split by \n\n
    $paragraphs = explode("\n\n", $text);
    $chunks = [];
    $current = "";

    foreach ($paragraphs as $para) {
        if (mb_strlen($current) + mb_strlen($para) > $targetSize) {
            if ($current !== '') $chunks[] = trim($current);
            $current = $para;
        } else {
            $current .= ($current === '' ? '' : "\n\n") . $para;
        }
    }
    if ($current !== '') $chunks[] = trim($current);
    
    return $chunks;
}

//////////////////////
// EXECUTION
//////////////////////

if (!file_exists($inputFile)) die("Input file not found.\n");
$rawText = file_get_contents($inputFile);
$rawText = trim($rawText, "\x00..\x1F");

echo "1. Sanitizing Text (Line endings + Length limits)...\n";
// Apply sanitization globally FIRST to ensure all lines are fixed
$cleanText = sanitize_text_robust($rawText, $maxSafeLength);

echo "2. Chunking Text...\n";
$chunks = chunk_text($cleanText, $chunkSize);
$total = count($chunks);

echo "Generated $total safe chunks.\n";
if ($chunkAmount > 0) $toProcess = min($chunkAmount, $total);
else $toProcess = $total;

// DB Setup
try {
    $stmt = $pdo->query("SELECT COALESCE(MAX(`order`), 0) FROM audio_dialogue_lines");
    $startOrder = (int)$stmt->fetchColumn() + 1;
} catch (Exception $e) { $startOrder = 1; }

$insertStmt = $pdo->prepare("
    INSERT INTO audio_dialogue_lines 
    (name, `order`, description, regenerate_audios, created_at, updated_at) 
    VALUES (:name, :order, :desc, 1, NOW(), NOW())
");

if (!$dryRun) $pdo->beginTransaction();

$processed = 0;
$nIndex = $startIndex;

for ($i = 0; $i < $toProcess; $i++) {
    $content = $chunks[$i];
    
    // VERIFICATION: Check against the same logic the Checker uses
    $parts = preg_split('/[\.!\?:\;]+/', $content);
    $maxRun = 0;
    foreach ($parts as $p) {
        $len = mb_strlen($p);
        if ($len > $maxRun) $maxRun = $len;
    }
    
    $status = ($maxRun > $maxSafeLength) ? "\033[31mFAIL ($maxRun)\033[0m" : "\033[32mOK ($maxRun)\033[0m";
    $name = sprintf("%s %03d", $namePrefix, $nIndex);
    
    echo "Chunk $i [$status] : Inserting $name...\n";
    
    if (!$dryRun) {
        $insertStmt->execute([
            ':name' => $name,
            ':order' => $startOrder + $processed,
            ':desc' => $content
        ]);
    }
    
    $processed++;
    $nIndex++;
}

if (!$dryRun) {
    $pdo->commit();
    echo "SUCCESS: Imported $processed chunks.\n";
} else {
    echo "DRY RUN: No DB changes.\n";
}
