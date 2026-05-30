#!/usr/bin/env php
<?php
// show_lore_structure.php
// Shows nested structure of JSON fields in md_doc_analysis

if (php_sapi_name() !== 'cli') die("CLI only\n");

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$docId = $argv[1] ?? null;
$maxDepth = $argv[2] ?? null; // null = infinite

if (!$docId) {
    echo "Usage: php show_lore_structure.php <doc_id> [max_depth]\n";
    echo "Example: php show_lore_structure.php 104 3\n";
    echo "         php show_lore_structure.php 104    (infinite depth)\n";
    exit(1);
}

function show_structure($data, $depth = 0, $maxDepth = null, $maxArraySample = 2) {
    $indent = str_repeat('  ', $depth);
    
    if ($maxDepth !== null && $depth >= $maxDepth) {
        return $indent . "[MAX DEPTH]\n";
    }
    
    if ($data === null) return $indent . "null\n";
    if (is_bool($data)) return $indent . "bool\n";
    if (is_int($data) || is_float($data)) return $indent . "number\n";
    
    if (is_string($data)) {
        $len = strlen($data);
        if ($len > 80) return $indent . "string ($len chars)\n";
        return $indent . "string: \"" . substr($data, 0, 60) . ($len > 60 ? '...' : '') . "\"\n";
    }
    
    if (is_array($data)) {
        $keys = array_keys($data);
        $isIndexed = ($keys === array_keys($keys));
        
        if ($isIndexed) {
            // Indexed array
            $count = count($data);
            $output = $indent . "array[$count]\n";
            
            $limit = min($maxArraySample, $count);
            for ($i = 0; $i < $limit; $i++) {
                $output .= $indent . "  [$i] =>\n";
                $output .= show_structure($data[$i], $depth + 2, $maxDepth, $maxArraySample);
            }
            
            if ($count > $limit) {
                $output .= $indent . "  ... (" . ($count - $limit) . " more items)\n";
            }
            
            return $output;
        } else {
            // Associative array / object
            $output = $indent . "object\n";
            foreach ($data as $key => $value) {
                $output .= $indent . "  \"$key\" =>\n";
                $output .= show_structure($value, $depth + 2, $maxDepth, $maxArraySample);
            }
            return $output;
        }
    }
    
    return $indent . "unknown\n";
}

echo "=== Nested Structure for doc_id: $docId ===\n";
if ($maxDepth !== null) echo "(Max depth: $maxDepth)\n";
echo "\n";

$stmt = $pdo->prepare("
    SELECT entities, showrunner_analysis, lore_points, thematics, series_bible
    FROM md_doc_analysis 
    WHERE doc_id = ?
");
$stmt->execute([$docId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) die("No analysis found for doc_id $docId\n");

echo "━━━ ENTITIES ━━━\n";
$data = json_decode($row['entities'], true);
echo show_structure($data, 0, $maxDepth);

echo "\n━━━ SHOWRUNNER_ANALYSIS ━━━\n";
$data = json_decode($row['showrunner_analysis'], true);
echo show_structure($data, 0, $maxDepth);

echo "\n━━━ LORE_POINTS ━━━\n";
$data = json_decode($row['lore_points'], true);
echo show_structure($data, 0, $maxDepth);

echo "\n━━━ THEMATICS ━━━\n";
$data = json_decode($row['thematics'], true);
echo show_structure($data, 0, $maxDepth);

echo "\n━━━ SERIES_BIBLE ━━━\n";
echo "string (" . strlen($row['series_bible']) . " chars)\n";

echo "\n=== Done ===\n";

