<?php
// public/VideoBatchImporter.php
class VideoBatchImporter
{
    private $pdo;
    private $entityType;
    private $importDir;
    private $videoDir;
    private $thumbDir;
    private $videoDirRel;
    private $projectRoot;

    public function __construct(PDO $pdo, string $entityType = 'animatics')
    {
        $this->pdo = $pdo;
        $this->entityType = $entityType; // Default 'animatics'
        
        $this->projectRoot = defined('PROJECT_ROOT') ? PROJECT_ROOT : __DIR__ . '/..';
        
        // Paths
        $this->importDir = $this->projectRoot . '/public/import/videos/';
        $this->videoDirRel = 'videos/'; // NO leading slash
        $this->videoDir = $this->projectRoot . '/public/videos/';
        $this->thumbDir = $this->videoDir . 'thumbnails/';
        
        if (!is_dir($this->videoDir)) {
            mkdir($this->videoDir, 0775, true);
        }
        if (!is_dir($this->thumbDir)) {
            mkdir($this->thumbDir, 0775, true);
        }
    }

    public function runImport(array $files): array
    {
        $results = [];
        $runDir = $this->prepareDoneDir();

        // Path to scripts
        $getInfoScript = $this->projectRoot . '/bash/get_video_info.sh';
        $genThumbScript = $this->projectRoot . '/bash/generate_thumbnail.sh';

        if (empty($files)) {
            return ['No files provided'];
        }

        try {
            // STEP A: Create ONE Map Run for the entire batch
            $stmtMR = $this->pdo->prepare("INSERT INTO map_runs (entity_type, note, created_at) VALUES (?, 'Batch Import', NOW())");
            $stmtMR->execute([$this->entityType]);
            $sharedMapRunId = $this->pdo->lastInsertId();
        } catch (Exception $e) {
            return ["[Fatal Error] Could not create batch Map Run: " . $e->getMessage()];
        }

        // STEP B: Process each file
        foreach ($files as $filePath) {
            $filename = basename($filePath);
            
            if (!file_exists($filePath)) {
                $results[] = "[Error] File not found: $filename";
                continue;
            }

            try {
                // Start transaction per file so one failure doesn't kill the whole batch
                $this->pdo->beginTransaction();

                // 1. Counter & Naming
                $this->pdo->exec("INSERT IGNORE INTO video_counter VALUES (0)");
                $this->pdo->exec("UPDATE video_counter SET next_video = next_video + 1");
                $countId = $this->pdo->query("SELECT next_video FROM video_counter LIMIT 1")->fetchColumn();
                
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $newBaseName = "video" . str_pad($countId, 7, '0', STR_PAD_LEFT);
                
                // Video Path
                $newFileName = $newBaseName . '.' . $ext;
                $destPath = $this->videoDir . $newFileName;
                $relPath = $this->videoDirRel . $newFileName;

                // Thumbnail Path
                $thumbName = $newBaseName . '.jpg';
                $thumbDestPath = $this->thumbDir . $thumbName;
                $thumbRelPath = 'videos/thumbnails/' . $thumbName;

                // 2. Copy Video File
                if (!copy($filePath, $destPath)) {
                    throw new Exception("Failed to copy file to storage.");
                }

                // 3. Generate Thumbnail
                $duration = 0;
                
                if (file_exists($getInfoScript) && file_exists($genThumbScript)) {
                    // Get Duration
                    $cmd = 'sh ' . escapeshellarg($getInfoScript) . ' ' . escapeshellarg($destPath);
                    $json = trim(shell_exec($cmd . ' 2>&1'));
                    $data = json_decode($json, true);
                    if ($data && isset($data['format']['duration'])) {
                        $duration = (int)$data['format']['duration'];
                    }

                    // Generate Thumb
                    if (file_exists($thumbDestPath)) unlink($thumbDestPath);
                    
                    $thumbTime = min(1, max(0, $duration - 1));
                    $cmd = 'sh ' . escapeshellarg($genThumbScript) . ' '
                           . escapeshellarg($destPath) . ' '
                           . escapeshellarg($thumbDestPath) . ' '
                           . escapeshellarg($thumbTime);
                    shell_exec($cmd . ' 2>&1');
                }

                // 4. Fallback Thumbnail
                if (!file_exists($thumbDestPath) || filesize($thumbDestPath) === 0) {
                    $img = imagecreatetruecolor(320, 180);
                    $bg = imagecolorallocate($img, 60, 60, 60); // Dark grey
                    $text_color = imagecolorallocate($img, 255, 255, 255);
                    imagefilledrectangle($img, 0, 0, 320, 180, $bg);
                    imagestring($img, 3, 10, 80, "Processing...", $text_color);
                    imagejpeg($img, $thumbDestPath, 80);
                    imagedestroy($img);
                }

                // 5. Create Entity (Animatics)
                $entityName = pathinfo($filename, PATHINFO_FILENAME);
                
                $stmtEnt = $this->pdo->prepare("INSERT INTO `{$this->entityType}` (name, description, created_at, active_map_run_id) VALUES (?, 'Batch Import', NOW(), ?)");
                $stmtEnt->execute([$entityName, $sharedMapRunId]);
                $entityId = $this->pdo->lastInsertId();

                // 6. Insert Video Entry (Using Shared Map Run ID)
                $stmtVid = $this->pdo->prepare("INSERT INTO videos (map_run_id, name, url, thumbnail, duration, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                $stmtVid->execute([$sharedMapRunId, $newBaseName, $relPath, $thumbRelPath, $duration]);
                $videoId = $this->pdo->lastInsertId();

                // 7. Link (videos_2_animatics)
                $linkTable = "videos_2_" . $this->entityType;
                $stmtLink = $this->pdo->prepare("INSERT INTO `$linkTable` (from_id, to_id) VALUES (?, ?)");
                $stmtLink->execute([$videoId, $entityId]);

                $this->pdo->commit();
                
                // Move original file to 'done'
                rename($filePath, $this->importDir . 'done/' . $runDir . '/' . $filename);
                
                $results[] = "[Success] Imported '$filename' as $newFileName (#$videoId)";

            } catch (Exception $e) {
                $this->pdo->rollBack();
                if (file_exists($destPath)) @unlink($destPath);
                if (isset($thumbDestPath) && file_exists($thumbDestPath)) @unlink($thumbDestPath);
                $results[] = "[Failed] '$filename': " . $e->getMessage();
            }
        }

        return $results;
    }

    private function prepareDoneDir()
    {
        $base = $this->importDir . 'done/';
        if (!is_dir($base)) mkdir($base, 0775, true);
        
        $dirs = glob($base . '*', GLOB_ONLYDIR);
        $max = 0;
        foreach ($dirs as $d) {
            $n = (int)basename($d);
            if ($n > $max) $max = $n;
        }
        $next = str_pad($max + 1, 5, '0', STR_PAD_LEFT);
        mkdir($base . $next);
        return $next;
    }
}