<?php
// SpawnUpload.php
// Left in document root for now; resolves namespaced galleries when available.

class SpawnUpload
{
    private \mysqli $mysqli;
    private string $framesDir;
    private string $framesDirRel;
    private ?\App\Core\SpwBase $spw = null;
    private ?array $spawnType = null;

    /**
     * @param \mysqli $mysqli
     * @param array|null $spawnType Optional spawn type config (from spawn_types table)
     */
    public function __construct(\mysqli $mysqli, ?array $spawnType = null)
    {
        $this->mysqli = $mysqli;
        $this->spawnType = $spawnType;

        // Load root paths (keep this here until you centralize bootstrap)
        require __DIR__ . '/load_root.php'; // must define PROJECT_ROOT and FRAMES_ROOT

        // Directories
        $this->framesDir = rtrim(FRAMES_ROOT, '/') . '/'; // absolute, safe
        $this->framesDirRel = str_replace(PROJECT_ROOT . '/public/', '', FRAMES_ROOT); // dynamic relative

        $this->spw = \App\Core\SpwBase::getInstance();

        // Ensure folder exists
        if (!is_dir($this->framesDir)) {
            mkdir($this->framesDir, 0777, true);
        }
    }

    public function render(): string
    {
        $message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $message = $this->handleUpload();
        }

        // Render gallery above the upload form
        $galleryHtml = $this->renderGallery();

        // Get spawn type selector HTML
        $spawnTypeSelector = $this->renderSpawnTypeSelector();

        $spawnTypeLabel = $this->spawnType ? htmlspecialchars($this->spawnType['label']) : 'Spawn';

        $content = <<<HTML
        <div class="spawns-upload-container">
            {$galleryHtml}

            <div style="margin: 10px;">
                <hr>
                <h2>Upload {$spawnTypeLabel}</h2>
                <form id="uploader" method="post" enctype="multipart/form-data">
                    <label for="spawn_name">Name:</label><br>
                    <input type="text" name="spawn_name" id="spawn_name" required><br><br>

                    {$spawnTypeSelector}

                    <label for="spawn_description">Description / Prompt (optional):</label><br>
                    <textarea name="spawn_description" id="spawn_description" rows="4" cols="40"></textarea><br><br>

                    <label for="spawn_file">Upload Image (will be converted to JPG):</label><br>
                    <input type="file" name="spawn_file" id="spawn_file" accept="image/*" required><br><br>

                    <button type="submit">Upload</button>
                </form>
                <div class="message">{$message}</div>
            </div>
        </div>
HTML;
        return $content;
    }

    /**
     * Render spawn type selector dropdown if multiple types are available
     */
    private function renderSpawnTypeSelector(): string
    {
        $sql = "SELECT id, code, label FROM spawn_types WHERE active = 1 AND upload_enabled = 1 ORDER BY sort_order, label";
        $result = $this->mysqli->query($sql);

        $types = [];
        while ($row = $result->fetch_assoc()) {
            $types[] = $row;
        }

        // If only one type or type is preset, return hidden field
        if (count($types) <= 1 || $this->spawnType) {
            $typeId = $this->spawnType['id'] ?? ($types[0]['id'] ?? '');
            return sprintf(
                '<input type="hidden" name="spawn_type_id" value="%d">',
                (int)$typeId
            );
        }

        // Multiple types available - show dropdown
        $html = '<label for="spawn_type_id">Spawn Type:</label><br>';
        $html .= '<select name="spawn_type_id" id="spawn_type_id" required>';

        foreach ($types as $type) {
            $selected = ($this->spawnType && $type['id'] == $this->spawnType['id']) ? 'selected' : '';
            $html .= sprintf(
                '<option value="%d" %s>%s</option>',
                (int)$type['id'],
                $selected,
                htmlspecialchars($type['label'])
            );
        }

        $html .= '</select><br><br>';

        return $html;
    }

    /**
     * Render the appropriate gallery for current spawn type
     * This tries namespaced App\Gallery\ classes first, then falls back to legacy global classes.
     * It also tries several constructor signatures so it works during incremental migration.
     */
    private function renderGallery(): string
    {
        $defaultClassName = 'SpawnsGalleryDefault';

        // If spawn type is specified, try custom class name
        $requestedClass = $defaultClassName;
        if ($this->spawnType) {
            $requestedClass = 'SpawnsGallery' . ucfirst($this->spawnType['code']);
        }

        // Candidate class names to try (namespaced first)
        $candidates = [
            'App\\Gallery\\' . $requestedClass,
            $requestedClass,
            'App\\Gallery\\SpawnsGallery',
            'SpawnsGallery',
            'App\\Gallery\\' . $defaultClassName,
            $defaultClassName
        ];

        foreach ($candidates as $candidate) {
            if (!class_exists($candidate)) {
                continue;
            }

            // Try multiple constructor signatures to be tolerant during migration
            $instance = null;

            // 1) (mysqli, SpwBase, spawnType)
            try {
                $instance = new $candidate($this->mysqli, $this->spw, $this->spawnType);
            } catch (\ArgumentCountError|\TypeError|\Throwable $e) {
                // ignore and try next signature
            }

            // 2) (mysqli, SpwBase)
            if ($instance === null) {
                try {
                    $instance = new $candidate($this->mysqli, $this->spw);
                } catch (\ArgumentCountError|\TypeError|\Throwable $e) {
                    // ignore
                }
            }

            // 3) (spawnType) - legacy constructor many upload-related galleries used
            if ($instance === null) {
                try {
                    $instance = new $candidate($this->spawnType);
                } catch (\ArgumentCountError|\TypeError|\Throwable $e) {
                    // ignore
                }
            }

            // 4) no-arg
            if ($instance === null) {
                try {
                    $instance = new $candidate();
                } catch (\ArgumentCountError|\TypeError|\Throwable $e) {
                    // ignore
                }
            }

            if ($instance !== null) {
                // If the migrated gallery expects DB/SpwBase but didn't get it, try setter injection if available
                if (method_exists($instance, 'setMysqli') && !isset($instance->mysqli)) {
                    try { $instance->setMysqli($this->mysqli); } catch (\Throwable $e) {}
                }
                if (method_exists($instance, 'setSpw') && !isset($instance->spw)) {
                    try { $instance->setSpw($this->spw); } catch (\Throwable $e) {}
                }

                try {
                    return $instance->render();
                } catch (\Throwable $e) {
                    return '<p style="color:#b42318">Error rendering gallery: ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
            }
        }

        // No gallery class found or instantiation failed
        return '';
    }


    private function handleUpload(): string
    {
        // Basic checks
        if (!isset($_FILES['spawn_file'])) {
            return $this->err('No file uploaded.');
        }
        $file = $_FILES['spawn_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->err('Upload error: ' . $file['error']);
        }

        $spawnName = trim($_POST['spawn_name'] ?? '');
        $spawnDesc = trim($_POST['spawn_description'] ?? '');
        $spawnTypeId = isset($_POST['spawn_type_id']) ? (int)$_POST['spawn_type_id'] : null;

        // If spawn type not provided, try to get from current context
        if (!$spawnTypeId && $this->spawnType) {
            $spawnTypeId = $this->spawnType['id'];
        }

        // Validate spawn type exists
        if ($spawnTypeId) {
            $stmt = $this->mysqli->prepare("SELECT id FROM spawn_types WHERE id = ? AND active = 1");
            $stmt->bind_param('i', $spawnTypeId);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                return $this->err('Invalid spawn type selected.');
            }
            $stmt->close();
        }

        // Decide next frame basename: frame0000001 etc. (DB-driven to avoid FS race)
        $frameBase = $this->nextFrameBasenameFromDB();
        $relativeFilename = $this->framesDirRel . '/' . $frameBase . '.jpg';
        $absolutePath = $this->framesDir . $frameBase . '.jpg';

        // Convert upload to JPG at the target path
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!$this->convertToJpg($file['tmp_name'], $ext, $absolutePath)) {
            return $this->err('Failed to convert image to JPG.');
        }

        // DB writes in a transaction; remove file on rollback
        $this->mysqli->begin_transaction();
        try {
            // 1) spawns - now includes spawn_type_id
            $stmt = $this->mysqli->prepare(
                "INSERT INTO spawns (name, description, spawn_type_id) VALUES (?, ?, ?)"
            );
            if (!$stmt) {
                throw new \Exception('Prepare (spawns) failed: ' . $this->mysqli->error);
            }
            $stmt->bind_param('ssi', $spawnName, $spawnDesc, $spawnTypeId);
            if (!$stmt->execute()) {
                if ($stmt->errno === 1062) {
                    throw new \Exception('Spawn name already exists. Choose a different name.');
                }
                throw new \Exception('Insert (spawns) failed: ' . $stmt->error);
            }
            $spawnId = $stmt->insert_id;
            $stmt->close();

            // 2) frames
            $frameName = $frameBase;
            $prompt = $spawnDesc;
            $entityType = 'spawns';

            $stmt = $this->mysqli->prepare(
                "INSERT INTO frames (name, filename, prompt, entity_type)
                 VALUES (?, ?, ?, ?)"
            );
            if (!$stmt) {
                throw new \Exception('Prepare (frames) failed: ' . $this->mysqli->error);
            }
            $stmt->bind_param('ssss', $frameName, $relativeFilename, $prompt, $entityType);
            if (!$stmt->execute()) {
                throw new \Exception('Insert (frames) failed: ' . $stmt->error);
            }
            $frameId = $stmt->insert_id;
            $stmt->close();

            // 3) mapping frames_2_spawns
            $stmt = $this->mysqli->prepare(
                "INSERT INTO frames_2_spawns (from_id, to_id) VALUES (?, ?)"
            );
            if (!$stmt) {
                throw new \Exception('Prepare (frames_2_spawns) failed: ' . $this->mysqli->error);
            }
            $stmt->bind_param('ii', $frameId, $spawnId);
            if (!$stmt->execute()) {
                throw new \Exception('Insert (frames_2_spawns) failed: ' . $stmt->error);
            }
            $stmt->close();

            $this->mysqli->commit();
            return $this->ok('Spawn "' . htmlspecialchars($spawnName) . '" uploaded as ' . htmlspecialchars($relativeFilename) . '.');

        } catch (\Exception $e) {
            $this->mysqli->rollback();
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
            return $this->err($e->getMessage());
        }
    }

    private function nextFrameBasenameFromDB(): string
    {
        $sql = "UPDATE frame_counter 
                SET next_frame = LAST_INSERT_ID(next_frame + 1)";
        $this->mysqli->query($sql);

        $res = $this->mysqli->query("SELECT LAST_INSERT_ID() AS frame_num");
        $row = $res->fetch_assoc();
        $num = (int)$row['frame_num'];

        return 'frame' . str_pad((string)$num, 7, '0', STR_PAD_LEFT);
    }

    private function convertToJpg(string $srcPath, string $ext, string $targetPath): bool
    {
        $ext = strtolower($ext);
        $info = @getimagesize($srcPath);
        $mime = $info['mime'] ?? null;

        $create = null;
        if ($mime === 'image/jpeg' || $ext === 'jpg' || $ext === 'jpeg') {
            return move_uploaded_file($srcPath, $targetPath);
        } elseif ($mime === 'image/png' || $ext === 'png') {
            $create = 'imagecreatefrompng';
        } elseif ($mime === 'image/gif' || $ext === 'gif') {
            $create = 'imagecreatefromgif';
        } elseif (($mime === 'image/webp' || $ext === 'webp') && function_exists('imagecreatefromwebp')) {
            $create = 'imagecreatefromwebp';
        } else {
            return false;
        }

        $src = @$create($srcPath);
        if (!$src) {
            return false;
        }

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

    private function ok(string $msg): string
    {
        return '<p style="color: #1a7f37; font-weight: 600;">' . $msg . '</p>';
    }

    private function err(string $msg): string
    {
        return '<p style="color: #b42318; font-weight: 600;">' . $msg . '</p>';
    }
}
