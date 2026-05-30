#!/usr/bin/env php
<?php
// public/cli_import_sketch_frames.php
// Usage:
//   php cli_import_sketch_frames.php                     # default: test first row, base-url http://127.0.0.1:8080
//   php cli_import_sketch_frames.php --base-url=http://localhost --all
//   php cli_import_sketch_frames.php --limit=1
//
// Options:
//   --base-url=<url>   Base URL to call (default http://127.0.0.1:8080)
//   --all              Process all matching rows (default: only first row for testing)
//   --limit=N          Process up to N rows (overrides --all)
//   --dry-run          Print what would be done but don't call HTTP endpoint
//   --verbose          Verbose logging

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// --- CLI args parsing (simple) ---
$opts = [];
foreach ($argv as $i => $a) {
    if ($i === 0) continue;
    if (strpos($a, '--') === 0) {
        $parts = explode('=', $a, 2);
        $key = substr($parts[0], 2);
        $val = $parts[1] ?? true;
        $opts[$key] = $val;
    } else {
        // positional ignored
    }
}

$baseUrl = $opts['base-url'] ?? ($GLOBALS['BASE_URL'] ?? 'http://127.0.0.1:8080');
$processAll = isset($opts['all']);
$dryRun = isset($opts['dry-run']);
$verbose = isset($opts['verbose']);
$limitOpt = isset($opts['limit']) ? (int)$opts['limit'] : null;

if ($limitOpt !== null) {
    if ($limitOpt <= 0) $limitOpt = 1;
}

// For safety default: test with only the first row unless user explicitly asks for --all or --limit > 1
if (!$processAll && $limitOpt === null) {
    $limit = 1;
} elseif ($limitOpt !== null) {
    $limit = $limitOpt;
} else {
    $limit = null; // no limit -> all
}

fwrite(STDOUT, "Base URL: $baseUrl\n");
fwrite(STDOUT, "Mode: " . ($dryRun ? "DRY-RUN" : ($limit === null ? "ALL" : "LIMIT {$limit} rows")) . "\n");

if (!isset($pdo) || !($pdo instanceof PDO)) {
    // try to find $db / $dbPdo (just in case bootstrap exposed a different var)
    if (isset($db) && $db instanceof PDO) {
        $pdo = $db;
    } else {
        fwrite(STDERR, "ERROR: PDO instance (\$pdo) not found after bootstrap. Ensure bootstrap.php creates \$pdo.\n");
        exit(2);
    }
}

// The query you requested:
$sql = "
    SELECT id, entity_id
    FROM frames 
    WHERE rating = 5 
      AND entity_type = 'sketches'
      AND id NOT IN (SELECT img2img_frame_id FROM animatics WHERE img2img_frame_id IS NOT NULL)
    ORDER BY id ASC
";

if ($limit !== null) {
    $sql .= " LIMIT " . (int)$limit;
}

if ($verbose) {
    fwrite(STDOUT, "SQL:\n$sql\n\n");
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    fwrite(STDERR, "DB error: " . $e->getMessage() . "\n");
    exit(3);
}

$count = count($rows);
fwrite(STDOUT, "Found $count row(s) matching criteria.\n");

if ($count === 0) {
    fwrite(STDOUT, "Nothing to process. Exiting.\n");
    exit(0);
}

function call_endpoint_get(string $url, int $timeout = 30) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    // If your import endpoint requires cookies / authentication, you'll need to set them here.
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body, $err];
}

// Process each row
foreach ($rows as $i => $row) {
    $frameId = $row['id'];
    $entityId = $row['entity_id'];

    // Build the ajaxUrl per your example:
    $params = [
        'ajax' => '1',
        'source' => 'sketches',
        'target' => 'animatics',
        'source_entity_id' => $entityId,
        'frame_id' => $frameId,
        'limit' => '1',
        'copy_name_desc' => '1',
    ];

    $query = http_build_query($params);
    $url = rtrim($baseUrl, '/') . '/import_entity_from_entity.php?' . $query;

    fwrite(STDOUT, sprintf("\n[%d] frame_id=%s entity_id=%s\n", $i+1, $frameId, $entityId));
    fwrite(STDOUT, "Calling: $url\n");

    if ($dryRun) {
        fwrite(STDOUT, "DRY-RUN: skipping HTTP call.\n");
        continue;
    }

    list($httpCode, $body, $err) = call_endpoint_get($url);

    if ($err) {
        fwrite(STDERR, "cURL error: $err\n");
        // continue to next row
        continue;
    }

    fwrite(STDOUT, "HTTP status: $httpCode\n");
    // truncate body for display if huge
    $displayBody = (strlen($body) > 4000) ? substr($body, 0, 4000) . "\n...[truncated]\n" : $body;
    fwrite(STDOUT, "Response body:\n" . $displayBody . "\n");

    // Basic success heuristic: http 200 and contains some expected marker
    if ($httpCode >= 200 && $httpCode < 300) {
        fwrite(STDOUT, "Result: HTTP $httpCode (OK-ish)\n");
    } else {
        fwrite(STDOUT, "Result: HTTP $httpCode (non-2xx)\n");
    }

    // If default test-only behavior (limit == 1) we stop after first row automatically
    if ($limit === 1) {
        fwrite(STDOUT, "Test mode (limit=1): stopping after first processed row.\n");
        break;
    }
}

fwrite(STDOUT, "\nDone.\n");
exit(0);
