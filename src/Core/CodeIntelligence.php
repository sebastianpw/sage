<?php
namespace App\Core;

use PDO;
use RuntimeException;

class CodeIntelligence
{
    private SpwBase $spw;
    private AIProvider $ai;
    private PDO $pdo;
    private ModelRateLimiter $rateLimiter;

    private const CHUNK_CHARS = 20000;
    private const ANALYZER_MODEL = 'qwen/Qwen2.5-Coder-14B-Instruct-AWQ'; //'qwen2.5-coder-32b-instruct'; // 'qwen/qwen3-32b';
    //private const ANALYZER_MODEL = 'qwen/qwen3-32b';

    public function __construct(SpwBase $spw, AIProvider $ai, ModelRateLimiter $rateLimiter)
    {
        $this->spw = $spw;
        $this->ai = $ai;
        $this->rateLimiter = $rateLimiter;
        $this->pdo = $this->spw->getSysPDO();
    }

    /**
     * Delete a single file and all its related data
     */
    public function deleteFile(int $fileId): bool
    {
        try {
            $this->pdo->beginTransaction();
            
            // Delete analysis logs
            $stmt = $this->pdo->prepare('DELETE FROM code_analysis_log WHERE file_id = ?');
            $stmt->execute([$fileId]);
            
            // Delete classes
            $stmt = $this->pdo->prepare('DELETE FROM code_classes WHERE file_id = ?');
            $stmt->execute([$fileId]);
            
            // Delete file
            $stmt = $this->pdo->prepare('DELETE FROM code_files WHERE id = ?');
            $stmt->execute([$fileId]);
            
            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw new RuntimeException("Failed to delete file: " . $e->getMessage());
        }
    }

    /**
     * Delete multiple files by IDs
     */
    public function deleteFiles(array $fileIds): int
    {
        $deleted = 0;
        foreach ($fileIds as $id) {
            try {
                if ($this->deleteFile((int)$id)) {
                    $deleted++;
                }
            } catch (\Exception $e) {
                // Log but continue
                $this->spw->getFileLogger()->error(['Delete failed', ['id' => $id, 'err' => $e->getMessage()]]);
            }
        }
        return $deleted;
    }

    /**
     * Export file data as JSON with full structure
     */
    public function exportFileAsJson(int $fileId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM code_files WHERE id = ?');
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM code_classes WHERE file_id = ? ORDER BY id');
        $stmt->execute([$fileId]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse JSON fields
        foreach ($classes as &$class) {
            $class['interfaces'] = json_decode($class['interfaces'] ?? '[]', true);
            $class['methods'] = json_decode($class['methods'] ?? '[]', true);
        }

        $stmt = $this->pdo->prepare('SELECT chunk_index, tokens_estimate, provider, created_at FROM code_analysis_log WHERE file_id = ? ORDER BY chunk_index');
        $stmt->execute([$fileId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'file' => [
                'path' => $file['path'],
                'file_type' => $this->getFileType($file['path']),
                'hash' => $file['file_hash'],
                'last_analyzed' => $file['last_analyzed_at'],
                'chunks' => (int)$file['chunk_count']
            ],
            'classes' => $classes,
            'analysis_metadata' => $logs
        ];
    }

    /**
     * Export multiple files with folder structure preserved
     */
    public function exportFilesAsJson(array $fileIds): array
    {
        $export = [
            'export_date' => date('Y-m-d H:i:s'),
            'total_files' => count($fileIds),
            'structure' => []
        ];

        foreach ($fileIds as $id) {
            $data = $this->exportFileAsJson((int)$id);
            if ($data) {
                $path = $data['file']['path'];
                $parts = explode('/', $path);
                $filename = array_pop($parts);
                
                // Build nested structure
                $current = &$export['structure'];
                foreach ($parts as $dir) {
                    if (!isset($current[$dir])) {
                        $current[$dir] = ['_type' => 'directory', '_files' => []];
                    }
                    $current = &$current[$dir];
                }
                
                $current['_files'][$filename] = $data;
            }
        }

        return $export;
    }

    private function getFileType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'php' => 'PHP',
            'js' => 'JavaScript',
            'sh' => 'Shell',
            'py' => 'Python',
            default => 'Unknown'
        };
    }

    // ... (keep all existing methods: analyzeAll, analyzeFile, etc.) ...

    public function analyzeAll(): void
    {
        $root = rtrim($this->spw->getProjectPath(), '/') . '/src/';
        if (!is_dir($root)) {
            throw new RuntimeException("Source directory not found: {$root}");
        }

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $allowed = ['php','js','sh','py'];

        foreach ($rii as $file) {
            if ($file->isFile()) {
                $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                if (in_array($ext, $allowed, true)) {
                    $this->analyzeFile($file->getPathname());
                    $this->rateLimiter->acquire(self::ANALYZER_MODEL);
                }
            }
        }
    }

    public function analyzeFile(string $path): void
    {
        if (!is_readable($path)) {
            throw new RuntimeException("File not readable: $path");
        }

        $code = file_get_contents($path);
        $hash = sha1($code);

        $fileId = $this->ensureFileRow($path, $hash);

        if ($this->isUpToDate($fileId, $hash)) {
            return;
        }

        $chunks = $this->chunkCode($code, self::CHUNK_CHARS);

        $globalSummary = "The file is being analyzed. No summary yet.";
        $itemAggregate = [];

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $language = match ($ext) {
            'php' => 'PHP',
            'js'  => 'JavaScript',
            'sh'  => 'Shell script',
            'py' => 'Python',
            default => 'text',
        };

        foreach ($chunks as $i => $chunk) {
            $chunkIndex = $i + 1;

            switch ($language) {
                case 'PHP':
                    $system = "You are a senior PHP code analyzer. Return only JSON with this schema:\n" .
                        "{ \"classes\": [ { \"class_name\": string, \"extends\": string|null, \"interfaces\": [string], \"methods\": [string], \"summary\": string } ], \"update_summary\": string }";
                    $userPromptIntro = "This is part {$chunkIndex}/" . count($chunks) . " of a PHP file.";
                    break;

                case 'JavaScript':
                    $system = "You are a senior JavaScript code analyzer. Return only JSON with this schema:\n" .
                        "{ \"modules\": [ { \"name\": string, \"exports\": [string], \"functions\": [string], \"classes\": [string], \"summary\": string } ], \"update_summary\": string }";
                    $userPromptIntro = "This is part {$chunkIndex}/" . count($chunks) . " of a JavaScript file.";
                    break;

                case 'Shell script':
                    $system = "You are an experienced shell script reviewer. Return only JSON with this schema:\n" .
                        "{ \"scripts\": [ { \"name\": string|null, \"functions\": [string], \"described_purpose\": string } ], \"update_summary\": string }";
                    $userPromptIntro = "This is part {$chunkIndex}/" . count($chunks) . " of a shell script.";
                    break;

                default:
                    $system = "You are a code analyst. Return only JSON with schema: { \"items\": [ { \"name\": string, \"kind\": string, \"summary\": string } ], \"update_summary\": string }";
                    $userPromptIntro = "This is part {$chunkIndex}/" . count($chunks) . " of a source file.";
                    break;
            }

            $userPrompt = $userPromptIntro . "\nPrevious summary:\n" . $globalSummary . "\n\n" .
                "Analyze the following " . $language . " code chunk and output strictly a JSON object that conforms to the system-described schema.\n\nCode:\n" . $chunk;

            try {
                $responseRaw = $this->ai->sendMessage(self::ANALYZER_MODEL, [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $userPrompt]
                ], ['temperature' => 0.0, 'max_tokens' => 1500]);

            } catch (\Throwable $e) {
                $this->spw->getFileLogger()->error(['AI call failed', ['err' => $e->getMessage()]]);
                sleep(2);
                continue;
            }

            $this->insertAnalysisLog($fileId, $chunkIndex, $this->estimateTokens($chunk), strlen($responseRaw), self::ANALYZER_MODEL, $responseRaw);

            $jsonText = $this->extractFirstJsonObject($responseRaw);
            $json = $jsonText ? json_decode($jsonText, true) : null;
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
                $globalSummary = $this->fallbackSummarize($globalSummary, $responseRaw);
                $this->rateLimiter->acquire(self::ANALYZER_MODEL);
                continue;
            }

            if (!empty($json['update_summary']) && is_string($json['update_summary'])) {
                $globalSummary = $this->mergeSummaries($globalSummary, $json['update_summary']);
            }

            if (isset($json['classes']) && is_array($json['classes'])) {
                foreach ($json['classes'] as $c) $itemAggregate[] = $c;
            }
            if (isset($json['modules']) && is_array($json['modules'])) {
                foreach ($json['modules'] as $m) $itemAggregate[] = $m;
            }
            if (isset($json['scripts']) && is_array($json['scripts'])) {
                foreach ($json['scripts'] as $s) $itemAggregate[] = $s;
            }
            if (isset($json['items']) && is_array($json['items'])) {
                foreach ($json['items'] as $it) $itemAggregate[] = $it;
            }

            $this->rateLimiter->acquire(self::ANALYZER_MODEL);
        }

        $this->persistClasses($fileId, $itemAggregate, $globalSummary, count($chunks));
        $this->markAnalyzed($fileId, count($chunks), $hash);
    }

    private function ensureFileRow(string $path, string $hash): int
    {
        $normalized = $this->normalizePathForStorage($path);

        $stmt = $this->pdo->prepare('SELECT id, file_hash FROM code_files WHERE path = ?');
        $stmt->execute([$normalized]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return (int)$row['id'];
        }

        $stmt2 = $this->pdo->prepare('SELECT id FROM code_files WHERE path LIKE ? OR path LIKE ? LIMIT 1');
        $stmt2->execute(['%' . basename($normalized), '%/' . basename($normalized)]);
        $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($row2) {
            return (int)$row2['id'];
        }

        $ins = $this->pdo->prepare('INSERT INTO code_files (path, file_hash, created_at) VALUES (?, ?, NOW())');
        $ins->execute([$normalized, $hash]);
        return (int)$this->pdo->lastInsertId();
    }

    private function isUpToDate(int $fileId, string $hash): bool
    {
        $stmt = $this->pdo->prepare('SELECT file_hash FROM code_files WHERE id = ?');
        $stmt->execute([$fileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && $row['file_hash'] === $hash && !empty($row['last_analyzed_at']);
    }

    private function chunkCode(string $code, int $maxChars): array
    {
        $parts = [];
        $offset = 0;
        $len = strlen($code);

        while ($offset < $len) {
            $slice = substr($code, $offset, $maxChars);

            $pos1 = strrpos($slice, "}\n");
            $pos2 = strrpos($slice, "\n\n");
            $pos = false;
            if ($pos1 !== false && $pos1 > (int)($maxChars * 0.5)) $pos = $pos1;
            elseif ($pos2 !== false && $pos2 > (int)($maxChars * 0.5)) $pos = $pos2;

            if ($pos !== false) {
                $slice = substr($code, $offset, $pos + 2);
            }

            $parts[] = $slice;
            $offset += strlen($slice);
        }

        return $parts;
    }

    private function estimateTokens(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
    }

    private function insertAnalysisLog(int $fileId, int $chunkIndex, int $tokens, int $respLen, string $provider, string $raw): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO code_analysis_log (file_id, chunk_index, tokens_estimate, response_length, provider, raw_response, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$fileId, $chunkIndex, $tokens, $respLen, $provider, $raw]);
    }

    private function extractFirstJsonObject(string $text): ?string
    {
        if (preg_match('/\{(?:[^{}]*|(?R))*\}/s', $text, $m)) {
            return $m[0];
        }
        return null;
    }

    private function fallbackSummarize(string $old, string $response): string
    {
        $snippet = trim(substr(strip_tags($response), 0, 300));
        return trim($old . "\n\n" . $snippet);
    }

    private function mergeSummaries(string $old, string $update): string
    {
        $merged = trim($old . "\n\n" . $update);
        if (strlen($merged) > 2000) {
            $merged = substr($merged, 0, 2000) . '...';
        }
        return $merged;
    }

    private function persistClasses(int $fileId, array $items, string $summary, int $chunksCount = 0): void
    {
        $del = $this->pdo->prepare('DELETE FROM code_classes WHERE file_id = ?');
        $del->execute([$fileId]);

        $ins = $this->pdo->prepare('INSERT INTO code_classes (file_id, class_name, extends_class, interfaces, methods, summary, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');

        if (empty($items)) {
            $ins->execute([
                $fileId,
                null,
                null,
                json_encode([]),
                json_encode([]),
                $summary
            ]);
        } else {
            foreach ($items as $c) {
                $name = $c['class_name'] ?? $c['name'] ?? null;
                $extends = $c['extends'] ?? $c['extends_class'] ?? null;
                $interfaces = $c['interfaces'] ?? $c['exports'] ?? [];
                $methods = $c['methods'] ?? $c['functions'] ?? [];
                $summaryText = $c['summary'] ?? $c['described_purpose'] ?? $c['summary'] ?? null;

                $ins->execute([
                    $fileId,
                    $name,
                    $extends,
                    json_encode($interfaces ?? []),
                    json_encode($methods ?? []),
                    $summaryText
                ]);
            }
        }

        $upd = $this->pdo->prepare('UPDATE code_files SET chunk_count = ?, last_analyzed_at = NOW() WHERE id = ?');
        $upd->execute([$chunksCount, $fileId]);
    }

    private function normalizePathForStorage(string $path): string
    {
        $projectRoot = rtrim($this->spw->getProjectPath(), '/');

        if (!preg_match('#^/#', $path)) {
            $candidate = $projectRoot . '/' . ltrim($path, './');
        } else {
            $candidate = $path;
        }

        $real = @realpath($candidate);
        if ($real === false) {
            $real = $this->collapsePath($candidate);
        }

        if (strpos($real, $projectRoot) === 0) {
            $rel = ltrim(substr($real, strlen($projectRoot)), '/');
        } else {
            $rel = $real;
        }

        return $rel;
    }

    private function collapsePath(string $path): string
    {
        $parts = [];
        $segments = preg_split('#[\\\\/]+#', $path);
        foreach ($segments as $seg) {
            if ($seg === '' || $seg === '.') continue;
            if ($seg === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $seg;
        }
        $lead = (strpos($path, '/') === 0) ? '/' : '';
        return $lead . implode('/', $parts);
    }

    private function markAnalyzed(int $fileId, int $chunks, string $hash): void
    {
        $stmt = $this->pdo->prepare('UPDATE code_files SET chunk_count = ?, last_analyzed_at = NOW(), file_hash = ? WHERE id = ?');
        $stmt->execute([$chunks, $hash, $fileId]);
    }
}
