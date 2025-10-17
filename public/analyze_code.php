<?php
// public/analyze_code.php
// CLI runner for the CodeIntelligence module
// Usage:
//   php public/analyze_code.php            # analyze entire src/ (default)
//   php public/analyze_code.php path/to/file.php
//   php public/analyze_code.php src        # analyze all php/js/sh files under src/

require __DIR__ . '/error_reporting.php';
require __DIR__ . '/bootstrap.php';

$spw = \App\Core\SpwBase::getInstance();
$aiProvider = new \App\Core\AIProvider($spw->getFileLogger());
$rateLimiter = new \App\Core\ModelRateLimiter();
$ci = new \App\Core\CodeIntelligence($spw, $aiProvider, $rateLimiter);

/**
 * Resolve a possibly-relative path (relative to project root) into an absolute path.
 * Returns string (resolved) or false if cannot be resolved at all.
 */
function resolvePath(string $raw, \App\Core\SpwBase $spw) {
    // If absolute already
    if (preg_match('#^/#', $raw)) {
        $candidate = $raw;
    } else {
        // resolve relative to project root
        $candidate = rtrim($spw->getProjectPath(), '/') . '/' . ltrim($raw, './');
    }

    $real = @realpath($candidate);
    if ($real !== false) return $real;

    // fallback: if candidate exists as provided try it (permissions / symlink)
    if (file_exists($candidate)) return $candidate;

    return false;
}

/**
 * Iterate files under $dir and call $cb for each file whose extension is in $extensions.
 * $extensions is an array of allowed extensions without dot, e.g. ['php','js','sh']
 */
function iterateFilesUnder(string $dir, array $extensions, callable $cb) {
    $ri = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
    $exts = array_map('strtolower', $extensions);
    foreach ($ri as $f) {
        if ($f->isFile()) {
            $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
            if (in_array($ext, $exts, true)) {
                $cb($f->getPathname());
            }
        }
    }
}

/**
 * Print usage helper
 */
function usage(): void {
    echo "Usage:\n";
    echo "  php public/analyze_code.php              # analyze entire project src/\n";
    echo "  php public/analyze_code.php <path>       # analyze a file or directory (resolved relative to project root)\n";
    echo "Examples:\n";
    echo "  php public/analyze_code.php public/bootstrap.php\n";
    echo "  php public/analyze_code.php src\n";
}

// CLI entry
if ($argc <= 1) {
    echo "No path given — analyzing entire project src/ ...\n";
    $ci->analyzeAll();
    echo "Done: analyzed project src/\n";
    exit(0);
}

$raw = $argv[1];

// allow some common flags
if (in_array($raw, ['-h','--help','help'], true)) {
    usage();
    exit(0);
}

$resolved = resolvePath($raw, $spw);

if ($resolved === false) {
    echo "Error: cannot resolve path '{$raw}'. Make sure it exists relative to project root.\n";
    exit(2);
}

if (is_dir($resolved)) {
    echo "Directory detected: {$resolved}\n";
    echo "Scanning for PHP/JS/SH files under the directory and analyzing each...\n";

    $count = 0;
    iterateFilesUnder($resolved, ['php','js','sh'], function($filePath) use ($ci, &$count) {
        try {
            $ci->analyzeFile($filePath);
            $count++;
            echo "Analyzed: {$filePath}\n";
        } catch (\Throwable $e) {
            // log and continue
            echo "Error analyzing {$filePath}: " . $e->getMessage() . "\n";
        }
        // small polite throttle to avoid provider rate-limits
        usleep(150000); // 150ms
    });

    echo "Done. Analyzed {$count} files under {$resolved}\n";
    exit(0);
}

if (is_file($resolved)) {
    echo "File detected: {$resolved}\n";
    try {
        $ci->analyzeFile($resolved);
        echo "Analyzed {$resolved}\n";
        exit(0);
    } catch (\Throwable $e) {
        echo "Error analyzing file: " . $e->getMessage() . "\n";
        exit(3);
    }
}

echo "Unsupported path type for: {$resolved}\n";
exit(4);
