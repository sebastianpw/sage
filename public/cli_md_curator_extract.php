<?php
// public/cli_md_curator_extract.php
// PART 1: EXTRACTOR (Crawler) - UPDATED v2
// - Added broader detection for variants like "6_CHUNK_SUMMARY", "1_VISUAL_KEYWORDS", etc.
// - find_summary_in_data now matches key patterns with numeric prefixes/suffixes and varied separators.
// - find_summary_in_raw uses regex to capture keys containing 'chunk' and 'summary' in any casing/format.
// - Minimal changes, rest of script unchanged.
//
// Usage: php cli_md_curator_extract.php
// ----------------------------------------------------

if (php_sapi_name() !== 'cli') die("CLI only\n");

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;

// ANSI Colors
const C_RESET   = "\033[0m";
const C_GREEN   = "\033[0m\033[32m";
const C_YELLOW  = "\033[0m\033[33m";
const C_CYAN    = "\033[0m\033[36m";
const C_RED     = "\033[0m\033[31m";
const C_GRAY    = "\033[0m\033[90m";
const C_BLUE    = "\033[0m\033[34m";

// --- DEBUG MODE ---
const DEBUG_MODE = true; 
const ONLY_DOCS_WITH_GLOBAL_SUMMARY = true;

// CONFIG
$limit = 10;
//$charLimit = 4000;
$charLimit = 4000;
$curatorConfigId = 'md_curator_v1';
$showrunnerConfigId = 'md_curator_showrunner_v1';
$targetCategoryId = null;
$overrideModel = null;

// Parse CLI args
foreach ($argv as $arg) {
    if (strpos($arg, '--cat=') === 0) $targetCategoryId = (int)substr($arg, 6);
    if (strpos($arg, '--limit=') === 0) $limit = (int)substr($arg, 8);
    if (strpos($arg, '--chars=') === 0) $charLimit = (int)substr($arg, 8);
    if (strpos($arg, '--model=') === 0) $overrideModel = substr($arg, 8);
}

// 1. SETUP
$em = $spw->getEntityManager();
$repo = $em->getRepository(GeneratorConfig::class);
$configLore = $repo->findOneBy(['configId' => $curatorConfigId]);
$configShow = $repo->findOneBy(['configId' => $showrunnerConfigId]);

if (!$configLore || !$configShow) {
    die(C_RED . "Error: Missing generator configs ($curatorConfigId, $showrunnerConfigId).\n" . C_RESET);
}
if ($overrideModel) {
    if (method_exists($configLore,'setModel')) $configLore->setModel($overrideModel);
    if (method_exists($configShow,'setModel')) $configShow->setModel($overrideModel);
}

$aiProvider = $spw->getAIProvider();
$service = new GeneratorService($aiProvider, new SchemaValidator(), new ResponseNormalizer(), $spw->getFileLogger());

// --- HELPER: Balanced-brace extractor (global helper for reuse) ---
function extract_balanced($text, $startPos = null) {
    $len = strlen($text);
    if ($startPos === null) {
        $startPos = strpos($text, '{');
        if ($startPos === false) return null;
    }
    $inString = false;
    $escaped = false;
    $depth = 0;
    for ($i = $startPos; $i < $len; $i++) {
        $ch = $text[$i];
        if ($inString) {
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($ch === '\\') {
                $escaped = true;
                continue;
            }
            if ($ch === '"') {
                $inString = false;
                continue;
            }
        } else {
            if ($ch === '"') {
                $inString = true;
                continue;
            }
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $startPos, $i - $startPos + 1);
                }
            }
        }
    }
    return null;
}

// --- HELPER: Aggressive JSON Extraction (balanced-brace + cleanup heuristics) ---
function extract_json_robust($raw) {
    if (!is_string($raw)) return null;
    $raw = trim($raw);

    // normalize encoding and remove BOM
    $raw = preg_replace('/^\x{FEFF}/u', '', $raw);
    $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');

    // 1. Try standard decode first
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) return $decoded;

    // small utility: try to decode a given candidate, with optional cleanup attempts
    $try_decode = function($candidate) {
        // quick normalize: collapse doubled single-quotes (common in SQL dumps)
        $candidate = str_replace("''", "'", $candidate);

        // normalize common "smart" quotes to straight ones (just in case)
        $candidate = str_replace(["\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x9C", "\xE2\x80\x9D"], ["'", "'", '"', '"'], $candidate);

        // remove control characters except \n \r \t
        $candidate = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $candidate);

        // try straight decode
        $d = json_decode($candidate, true);
        if (json_last_error() === JSON_ERROR_NONE) return $d;

        // try removing trailing commas before ] or }
        $fixed = preg_replace('/,\s*(\]|\})/m', '$1', $candidate);
        $d = json_decode($fixed, true);
        if (json_last_error() === JSON_ERROR_NONE) return $d;

        return null;
    };

    // 2. If fenced code block exists, attempt to extract balanced JSON from inside it
    if (preg_match_all('/```(?:json)?\s*(.*?)\s*```/is', $raw, $blocks)) {
        foreach ($blocks[1] as $block) {
            // Try direct decode first
            $d = $try_decode($block);
            if ($d !== null) return $d;

            // If block contains a '{', extract a balanced JSON object
            $pos = strpos($block, '{');
            if ($pos !== false) {
                $sub = extract_balanced($block, $pos);
                if ($sub !== null) {
                    $d = $try_decode($sub);
                    if ($d !== null) return $d;
                }
            }
        }
    }

    // 3. Fallback: find first '{' in whole raw and extract balanced JSON
    $first = strpos($raw, '{');
    if ($first !== false) {
        $sub = extract_balanced($raw, $first);
        if ($sub !== null) {
            $d = $try_decode($sub);
            if ($d !== null) return $d;
        }
    }

    // 4. As a last resort: attempt the old brute force (first '{' .. last '}') then cleanup
    $start = strpos($raw, '{');
    $end = strrpos($raw, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $sub = substr($raw, $start, $end - $start + 1);
        $d = $try_decode($sub);
        if ($d !== null) return $d;
    }

    return null;
}

// --- HELPER: Iterative Search for Chunk Summary within parsed data ---
// Enhanced to match keys like "6_CHUNK_SUMMARY", "1-CHUNK-SUMMARY", "chunksummary6" etc.
function find_summary_in_data($data) {
    if (is_object($data)) {
        $data = json_decode(json_encode($data), true);
    }
    if (!is_array($data)) return null;

    $stack = [$data];
    $keyPattern = '/(^[\d\-_]*\s*chunk[\s\-_]*summary[\d\-_]*$)|(^chunk[\s\-_]*summary$)|(^summary$)/i';

    while (!empty($stack)) {
        $node = array_pop($stack);
        if (!is_array($node)) continue;

        foreach ($node as $k => $v) {
            // direct match
            if (preg_match($keyPattern, (string)$k)) {
                return $v;
            }
            // also check nested arrays/objects quickly
            if (is_array($v) || is_object($v)) {
                if (is_object($v)) $v = json_decode(json_encode($v), true);
                $stack[] = $v;
            }
        }
    }

    return null;
}

// --- HELPER: Search raw text for chunk_summary if JSON parsing failed or missed it ---
// Enhanced regex to capture keys like "6_CHUNK_SUMMARY", possibly uppercase, with prefixes/suffixes.
function find_summary_in_raw($raw) {
    if (!is_string($raw)) return null;
    // Broad regex: captures quoted key that contains 'chunk' and 'summary' in any order with optional prefixes/suffixes
    if (preg_match('/"([\dA-Za-z\-\_]*chunk[\dA-Za-z\-\_\s]*summary[\dA-Za-z\-\_]*)"\s*:\s*/i', $raw, $m, PREG_OFFSET_CAPTURE)) {
        $matchPos = $m[0][1] + strlen($m[0][0]);
        // skip whitespace
        while ($matchPos < strlen($raw) && ctype_space($raw[$matchPos])) $matchPos++;

        if ($matchPos >= strlen($raw)) return null;
        $ch = $raw[$matchPos];
        if ($ch === '"') {
            // parse a JSON string literal with escapes
            $i = $matchPos + 1;
            $len = strlen($raw);
            $escaped = false;
            $buf = '';
            for (; $i < $len; $i++) {
                $c = $raw[$i];
                if ($escaped) { $buf .= $c; $escaped = false; continue; }
                if ($c === '\\') { $escaped = true; continue; }
                if ($c === '"') break;
                $buf .= $c;
            }
            $un = stripcslashes($buf);
            return $un;
        } elseif ($ch === '{') {
            // extract balanced object starting at matchPos
            $obj = extract_balanced($raw, $matchPos);
            if ($obj !== null) {
                $decoded = extract_json_robust($obj);
                if ($decoded !== null) return $decoded;
                return $obj;
            }
        } else {
            // maybe it's an array, number, or bareword - grab until comma or closing brace
            if (preg_match('/\s*([^\n\r,}]+)/', substr($raw, $matchPos), $m2)) {
                $val = trim($m2[1]);
                $val = trim($val, " \t\n\r,");
                $val = trim($val, "\"'");
                return $val;
            }
        }
    }

    // If not found with the 'chunk...summary' pattern, fall back to earlier approaches:
    // try to locate "chunk_summary" substring (covers cases where key had different separators)
    $lower = strtolower($raw);
    $pos = strpos($lower, 'chunk_summary');
    if ($pos === false) {
        if (strpos($lower, 'chunk summary') !== false) $pos = strpos($lower, 'chunk summary');
        elseif (strpos($lower, 'chunk-summary') !== false) $pos = strpos($lower, 'chunk-summary');
    }
    if ($pos === false) return null;

    if (!preg_match('/("chunk_summary"|"chunk-summary"|"chunk summary"|"chunksummary"|"summary")\s*:\s*/i', $raw, $m3, PREG_OFFSET_CAPTURE, max(0, $pos - 60))) {
        return null;
    }
    $matchPos = $m3[0][1] + strlen($m3[0][0]);
    while ($matchPos < strlen($raw) && ctype_space($raw[$matchPos])) $matchPos++;

    if ($matchPos >= strlen($raw)) return null;
    $ch = $raw[$matchPos];
    if ($ch === '"') {
        $i = $matchPos + 1;
        $len = strlen($raw);
        $escaped = false;
        $buf = '';
        for (; $i < $len; $i++) {
            $c = $raw[$i];
            if ($escaped) { $buf .= $c; $escaped = false; continue; }
            if ($c === '\\') { $escaped = true; continue; }
            if ($c === '"') break;
            $buf .= $c;
        }
        return stripcslashes($buf);
    } elseif ($ch === '{') {
        $obj = extract_balanced($raw, $matchPos);
        if ($obj !== null) {
            $decoded = extract_json_robust($obj);
            if ($decoded !== null) return $decoded;
            return $obj;
        }
    } else {
        if (preg_match('/\s*([^\n\r,}]+)/', substr($raw, $matchPos), $m4)) {
            $val = trim($m4[1]);
            $val = trim($val, " \t\n\r,");
            $val = trim($val, "\"'");
            return $val;
        }
    }

    return null;
}

// --- HELPER: Formatter for Context ---
function format_summary_for_context($input) {
    if (is_string($input)) return $input;
    if (is_numeric($input)) return (string)$input;
    
    if (is_array($input)) {
        $out = [];
        if (isset($input['synopsis'])) {
            $val = is_array($input['synopsis']) ? implode(' ', $input['synopsis']) : $input['synopsis'];
            $out[] = "SYNOPSIS: " . $val;
        }
        if (isset($input['key_events'])) {
            $val = is_array($input['key_events']) ? implode('; ', $input['key_events']) : $input['key_events'];
            $out[] = "KEY EVENTS: " . $val;
        }
        if (isset($input['thematic_weight'])) {
            $val = is_array($input['thematic_weight']) ? implode(' ', $input['thematic_weight']) : $input['thematic_weight'];
            $out[] = "THEMATIC WEIGHT: " . $val;
        }

        if (!empty($out)) return implode("\n", $out);
        return json_encode($input, JSON_UNESCAPED_UNICODE);
    }
    return '';
}

// 2. CATEGORY SELECTION
if ($targetCategoryId === null) {
    echo "\n" . C_CYAN . "📡 MD CURATOR: EXTRACT (API -> DB)" . C_RESET . "\n";
    $catStmt = $pdo->query("SELECT id, name FROM documentation_categories ORDER BY name ASC");
    $cats = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    $map = []; $i = 1;
    echo "  [0] " . C_YELLOW . "Process EVERYTHING" . C_RESET . "\n";
    foreach ($cats as $c) { echo "  [$i] {$c['name']} (ID: {$c['id']})\n"; $map[$i] = $c['id']; $i++; }
    while ($targetCategoryId === null) {
        $input = readline("Select Category [0-$i]: ");
        $val = (int)$input;
        if ($val === 0) { $targetCategoryId = 0; }
        elseif (isset($map[$val])) { $targetCategoryId = $map[$val]; }
    }
}

// 3. FETCH DOCS
$whereSql = "WHERE d.is_active = 1";
$params = [];
if ($targetCategoryId > 0) { $whereSql .= " AND d.category_id = :cat"; $params['cat'] = $targetCategoryId; }

$sql = "SELECT d.id, d.name, d.content, d.description FROM documentations d LEFT JOIN md_doc_analysis da ON d.id = da.doc_id $whereSql ORDER BY d.updated_at DESC, d.id DESC LIMIT $limit";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($docs)) die(C_GREEN . "No documents found.\n" . C_RESET);

// 4. PROCESS LOOP
foreach ($docs as $doc) {
    $id = $doc['id'];
    $content = $doc['content'] ?? '';
    $docSummary = $doc['description'] ?? 'No global description available.';
    
    
    
    // --- NEW CHECK ---
    if (ONLY_DOCS_WITH_GLOBAL_SUMMARY && (empty(trim($docSummary)) || $docSummary === 'No global description available.')) {
        echo C_RED . "\n❌ No global description available. Skipped.\n" . C_RESET;
        continue; // skip this document
    }
    // --- END NEW CHECK ---

    
    
    echo "\nDoc #$id: " . C_CYAN . substr($doc['name'], 0, 30) . C_RESET . "... ";

    if (strlen($content) < 50) { echo "Skip (Short)\n"; continue; }

    // Chunking
    $chunks = [];
    if (strlen($content) > $charLimit) {
        $parts = preg_split('/^#/m', $content, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $buffer = "";
        foreach ($parts as $part) {
            $part = "#" . $part;
            if (strlen($part) > $charLimit) {
                if (!empty($buffer)) { $chunks[] = $buffer; $buffer = ""; }
                $subParts = explode("\n\n", $part);
                $subBuffer = "";
                foreach ($subParts as $sp) {
                    if (strlen($subBuffer) + strlen($sp) > $charLimit) { $chunks[] = $subBuffer; $subBuffer = $sp; } else { $subBuffer .= "\n\n" . $sp; }
                }
                if ($subBuffer) $chunks[] = $subBuffer;
            } else {
                if (strlen($buffer) + strlen($part) > $charLimit) { $chunks[] = $buffer; $buffer = $part; } else { $buffer .= "\n" . $part; }
            }
        }
        if ($buffer) $chunks[] = $buffer;
    } else {
        $chunks[] = $content;
    }

    echo("(" . count($chunks) . " chunks): ");

    // Initialize Accumulator
    $accumulatedSummaries = [];

    foreach ($chunks as $chunkIndex => $chunkText) {
        $chkStmt = $pdo->prepare("SELECT lore_raw, show_raw FROM md_doc_chunks WHERE doc_id = ? AND chunk_index = ?");
        $chkStmt->execute([$id, $chunkIndex]);
        $cached = $chkStmt->fetch(PDO::FETCH_ASSOC);

        $rawLore = $cached['lore_raw'] ?? null;
        $rawShow = $cached['show_raw'] ?? null;
        
        $needsSave = false;
        
        $currentSummaryString = "";

        // --- LORE PASS --- 
        $newLore = $rawLore;
        if (!empty($rawLore)) {
            echo C_GRAY . "." . C_RESET;
        } else {
            $retries = 0; $success = false;
            while (!$success && $retries < 2) {
                try {
                    if ($retries > 0) sleep(2);
                    $res = $service->generate($configLore, ['entity_name' => $chunkText]);
                    $newLore = is_object($res) && method_exists($res, 'getRawResponse') ? $res->getRawResponse() : (string)$res;
                    echo C_GREEN . "L" . C_RESET;
                    $success = true;
                    $needsSave = true;
                } catch (Exception $e) { $retries++; echo C_RED . "x" . C_RESET; }
            }
        }

        // --- SHOWRUNNER PASS ---
        $newShow = $rawShow;

        // 1. CHECK CACHE (Resume)
        if (!empty($rawShow)) {
            echo C_GRAY . "." . C_RESET;
            
            // CLEAN & PARSE
            $decoded = extract_json_robust($rawShow);
            
            // FIND SUMMARY
            $foundSummary = null;
            if ($decoded) $foundSummary = find_summary_in_data($decoded);
            // fallback: also search raw if not found
            if (!$foundSummary) $foundSummary = find_summary_in_raw($rawShow);
            
            if ($foundSummary) {
                $currentSummaryString = format_summary_for_context($foundSummary);
            }
        } 
        // 2. GENERATE NEW
        else {
            // Build Context
            $contextInput = "";
            $contextInput .= "=== DOCUMENT CONTEXT ===\n" . $docSummary . "\n\n";
            if (!empty($accumulatedSummaries)) {
                $contextInput .= "=== PREVIOUSLY ON (Prior Chunk Summaries) ===\n";
                $contextInput .= implode("\n\n", $accumulatedSummaries) . "\n\n";
            }
            $contextInput .= "=== CURRENT CHUNK TO ANALYZE ===\n" . $chunkText;

            $retries = 0; $success = false;
            while (!$success && $retries < 2) {
                try {
                    if ($retries > 0) sleep(2);
                    
                    $res = $service->generate($configShow, ['entity_name' => $contextInput]);
                    $newShow = is_object($res) && method_exists($res, 'getRawResponse') ? $res->getRawResponse() : (string)$res;
                    
                    // CLEAN & PARSE
                    $parsedData = extract_json_robust($newShow);
                    
                    if (!$parsedData && is_object($res) && method_exists($res, 'getData')) {
                        $parsedData = $res->getData();
                    }
                    
                    // FIND SUMMARY
                    $foundSummary = null;
                    if ($parsedData) $foundSummary = find_summary_in_data($parsedData);
                    // fallback: search raw for cases where parser missed (e.g., weird placement)
                    if (!$foundSummary) $foundSummary = find_summary_in_raw($newShow);
                    
                    if ($foundSummary) {
                        $currentSummaryString = format_summary_for_context($foundSummary);
                    } else {
                        $currentSummaryString = "No summary generated.";
                    }

                    echo C_GREEN . "S" . C_RESET;
                    $success = true;
                    $needsSave = true;
                } catch (Exception $e) { $retries++; echo C_RED . "x" . C_RESET; }
            }
        }

        // ACCUMULATE & DEBUG
        if (!empty($currentSummaryString) && $currentSummaryString !== "No summary generated.") {
            if (DEBUG_MODE) {
                echo "\n" . C_YELLOW . "[DEBUG] Found Chunk #$chunkIndex:" . C_RESET . "\n";
                echo C_GRAY . substr(str_replace("\n", " ", $currentSummaryString), 0, 200) . "..." . C_RESET . "\n";
            }
            $accumulatedSummaries[] = "Chunk $chunkIndex Summary:\n" . $currentSummaryString;
        } else {
             if (DEBUG_MODE) {
                echo "\n" . C_RED . "[DEBUG] No Summary for Chunk #$chunkIndex" . C_RESET . "\n";
                $jsonErr = json_last_error_msg();
                echo C_RED . "JSON Error: " . $jsonErr . C_RESET . "\n";
                echo C_RED . "Raw snippet: " . substr(trim($newShow), 0, 300) . "..." . C_RESET . "\n";
            }
        }

        // SAVE
        if ($needsSave) {
            $ins = $pdo->prepare("
                INSERT INTO md_doc_chunks (doc_id, chunk_index, lore_raw, show_raw) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    lore_raw=VALUES(lore_raw), 
                    show_raw=VALUES(show_raw), 
                    updated_at=NOW()
            ");
            $ins->execute([$id, $chunkIndex, $newLore, $newShow]);
        }
    }
}
echo "\n--- Extract Complete ---\n";

