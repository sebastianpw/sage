<?php

class SpawnBatchImporter
{
    private mysqli $mysqli;
    private string $importDir;
    private string $framesDir;
    private string $framesDirRel;
    private \App\Core\SpwBase $spw;
    private ?int $spawnTypeId = null;

    /**
     * @param mysqli $mysqli
     * @param int|null $spawnTypeId Optional spawn type ID for batch import
     */
    public function __construct(mysqli $mysqli, ?int $spawnTypeId = null)
    {
        $this->mysqli = $mysqli;
        $this->spawnTypeId = $spawnTypeId;

        // Load root paths
        require __DIR__ . '/load_root.php'; // must define PROJECT_ROOT and FRAMES_ROOT

        // Directories
        $this->importDir   = PROJECT_ROOT . '/public/import/frames_2_spawns/';
        $this->framesDir   = FRAMES_ROOT . '/'; // absolute, scheduler-safe
        $this->framesDirRel = str_replace(PROJECT_ROOT . '/public/', '', FRAMES_ROOT); // dynamic relative

        $this->spw = \App\Core\SpwBase::getInstance();

        $this->spw->getLogger()->debug([
            'action'      => 'SpawnBatchImporter::__construct',
            'importDir'   => $this->importDir,
            'framesDir'   => $this->framesDir,
            'framesDirRel'=> $this->framesDirRel,
            'spawnTypeId' => $this->spawnTypeId
        ]);

        // Ensure frames directory exists
        if (!is_dir($this->framesDir)) {
            mkdir($this->framesDir, 0777, true);
            $this->spw->getLogger()->debug([
                'action'    => 'mkdir',
                'framesDir' => $this->framesDir
            ]);
        }
    }

    /**
     * Set spawn type for this import batch
     */
    public function setSpawnTypeId(?int $spawnTypeId): void
    {
        $this->spawnTypeId = $spawnTypeId;
    }

    /**
     * Run import for all files or selected files.
     *
     * @param array|null $selectedFiles Array of full paths to import. If null, import all files in the import folder.
     * @return array List of result messages
     */
    public function runImport(?array $selectedFiles = null): array
    {
        $results = [];

        if ($selectedFiles === null) {
            $selectedFiles = glob($this->importDir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [];
        }

        $this->spw->getLogger()->debug([
            'action' => 'SpawnBatchImporter::runImport',
            'importDir' => $this->importDir,
            'filesCount' => count($selectedFiles),
            'spawnTypeId' => $this->spawnTypeId
        ]);

        // Prepare run directory (done/00001)
        $runDir = $this->getNextImportRunDir();
        $this->spw->getLogger()->debug([
            'action' => 'createdRunDir',
            'runDir' => $runDir
        ]);

        foreach ($selectedFiles as $file) {
            $filename = basename($file);
            $this->spw->getLogger()->debug([
                'action' => 'processingFileStart',
                'file' => $file,
                'basename' => $filename
            ]);

            $spawnName = $this->uniqueSpawnName(pathinfo($filename, PATHINFO_FILENAME));
            $spawnDesc = '';

            $result = $this->importSingle($file, $spawnName, $spawnDesc);

            $this->spw->getLogger()->debug([
                'action' => 'importSingleResult',
                'file' => $file,
                'spawnName' => $spawnName,
                'spawnDesc' => $spawnDesc,
                'result' => $result
            ]);

            $results[] = strip_tags($result);

            // If import succeeded (spawn exists in DB), move original source file to done/<runDir>/
            if ($this->spawnNameExists($spawnName)) {
                $moved = $this->moveImportedFile($file, $runDir);
                if ($moved !== false) {
                    $results[] = "Moved '$filename' to done/$runDir/" . basename($moved);
                } else {
                    $results[] = "Failed to move '$filename' to done/$runDir/.";
                }
            }
        }

        return $results;
    }

    private function uniqueSpawnName(string $baseName): string
    {
        $name = $baseName;
        $i = 1;
        while ($this->spawnNameExists($name)) {
            $name = $baseName . '_' . $i++;
        }
        return $name;
    }

    private function spawnNameExists(string $name): bool
    {
        $stmt = $this->mysqli->prepare("SELECT id FROM spawns WHERE name = ? LIMIT 1");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    private function importSingle(string $filePath, string $spawnName, string $spawnDesc): string
    {
        $frameBase = $this->nextFrameBasenameFromDB();
        $relativeFilename = $this->framesDirRel . '/' . $frameBase . '.jpg';
        $absolutePath = $this->framesDir . $frameBase . '.jpg';

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $okConvert = $this->convertToJpg($filePath, $ext, $absolutePath);

        if (!$okConvert) {
            return $this->err("Failed to convert $filePath to JPG.");
        }

        $this->mysqli->begin_transaction();
        try {
            // --- spawns (now with spawn_type_id) ---
            $stmt = $this->mysqli->prepare(
                "INSERT INTO spawns (name, description, spawn_type_id) VALUES (?, ?, ?)"
            );
            $stmt->bind_param('ssi', $spawnName, $spawnDesc, $this->spawnTypeId);
            $stmt->execute();
            $spawnId = $stmt->insert_id;
            $stmt->close();

            // --- frames ---
            $frameName = $frameBase;
            $prompt = $spawnDesc;
            $entityType = 'spawns';
            $stmt = $this->mysqli->prepare(
                "INSERT INTO frames (name, filename, prompt, entity_type) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param('ssss', $frameName, $relativeFilename, $prompt, $entityType);
            $stmt->execute();
            $frameId = $stmt->insert_id;
            $stmt->close();

            // --- frames_2_spawns ---
            $stmt = $this->mysqli->prepare("INSERT INTO frames_2_spawns (from_id, to_id) VALUES (?, ?)");
            $stmt->bind_param('ii', $frameId, $spawnId);
            $stmt->execute();
            $stmt->close();

            $this->mysqli->commit();

            return $this->ok("Imported spawn '$spawnName' with frame '$relativeFilename'.");

        } catch (\Exception $e) {
            $this->mysqli->rollback();
            if (is_file($absolutePath)) @unlink($absolutePath);
            return $this->err("Failed to import '$spawnName': " . $e->getMessage());
        }
    }

    /**
     * Generate the next unique frame base name from DB counter.
     * Uses frame_counter.next_frame atomically.
     */
    private function nextFrameBasenameFromDB(): string
    {
        $sql = "UPDATE frame_counter SET next_frame = LAST_INSERT_ID(next_frame + 1)";
        if (!$this->mysqli->query($sql)) {
            throw new \RuntimeException("Failed to update frame_counter: " . $this->mysqli->error);
        }

        $res = $this->mysqli->query("SELECT LAST_INSERT_ID() AS frame_num");
        $row = $res->fetch_assoc();
        $num = (int)$row['frame_num'];

        return 'frame' . str_pad((string)$num, 7, '0', STR_PAD_LEFT);
    }

    private function convertToJpg(string $srcPath, string $ext, string $targetPath): bool
    {
        $info = @getimagesize($srcPath);
        $mime = $info['mime'] ?? null;

        if ($mime === 'image/jpeg' || $ext === 'jpg' || $ext === 'jpeg') {
            return copy($srcPath, $targetPath);
        }

        $create = null;
        if ($mime === 'image/png' || $ext === 'png') {
            $create = 'imagecreatefrompng';
        } elseif ($mime === 'image/gif' || $ext === 'gif') {
            $create = 'imagecreatefromgif';
        } elseif (($mime === 'image/webp' || $ext === 'webp') && function_exists('imagecreatefromwebp')) {
            $create = 'imagecreatefromwebp';
        } else {
            return false;
        }

        $src = @$create($srcPath);
        if (!$src) return false;

        $w = imagesx($src);
        $h = imagesy($src);
        $dst = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $w, $h, $white);
        imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
        $ok = imagejpeg($dst, $targetPath, 90);
        imagedestroy($src);
        imagedestroy($dst);

        return $ok;
    }

    private function getNextImportRunDir(): string
    {
        $doneBase = $this->importDir . 'done/';
        if (!is_dir($doneBase)) {
            mkdir($doneBase, 0777, true);
        }

        $max = 0;
        foreach (scandir($doneBase) as $item) {
            if ($item === '.' || $item === '..') continue;
            if (is_dir($doneBase . $item) && preg_match('/^\d{5}$/', $item)) {
                $val = (int)$item;
                if ($val > $max) $max = $val;
            }
        }

        $next = $max + 1;
        $dirName = str_pad((string)$next, 5, '0', STR_PAD_LEFT);
        $fullDir = $doneBase . $dirName;
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0777, true);
        }

        return $dirName;
    }

    private function moveImportedFile(string $srcPath, string $runDir)
    {
        $destDir = $this->importDir . 'done/' . $runDir . '/';
        if (!is_dir($destDir) && !mkdir($destDir, 0777, true)) {
            return false;
        }

        $filename = basename($srcPath);
        $destPath = $destDir . $filename;

        if (is_file($destPath)) {
            $timestamp = date('Ymd_His');
            $destPath = $destDir . pathinfo($filename, PATHINFO_FILENAME) . "_$timestamp." . pathinfo($filename, PATHINFO_EXTENSION);
        }

        if (@rename($srcPath, $destPath)) {
            return $destPath;
        }

        if (@copy($srcPath, $destPath) && @unlink($srcPath)) {
            return $destPath;
        }

        return false;
    }

    private function ok(string $msg): string
    {
        return '<p style="color: #1a7f37; font-weight: 600;">' . $msg . '</p>';
    }

    private function err(string $msg): string
    {
        return '<p style="color: #b42318; font-weight: 600;">' . $msg . '</p>';
    }
}
