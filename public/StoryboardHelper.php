<?php
// src/Helper/StoryboardHelper.php
namespace App\Helper;

use PDO;

class StoryboardHelper
{
    private PDO $pdo;
    private string $docRoot;

    public function __construct(PDO $pdo, string $docRoot = '')
    {
        $this->pdo = $pdo;
        $this->docRoot = $docRoot ?: rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    }

    /**
     * Copy frames from the frames table to a storyboard
     * Called by your existing import functionality
     */
    public function importFramesToStoryboard(int $storyboardId, array $frameIds): array
    {
        $imported = [];
        $errors = [];

        foreach ($frameIds as $frameId) {
            try {
                // Check if frame already exists in this storyboard
                $stmt = $this->pdo->prepare("
                    SELECT id FROM storyboard_frames 
                    WHERE storyboard_id = ? AND frame_id = ?
                ");
                $stmt->execute([$storyboardId, $frameId]);
                
                if ($stmt->fetch()) {
                    $errors[] = "Frame $frameId already in storyboard";
                    continue;
                }

                // Get frame info
                $stmt = $this->pdo->prepare("SELECT * FROM frames WHERE id = ?");
                $stmt->execute([$frameId]);
                $frame = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$frame) {
                    $errors[] = "Frame $frameId not found";
                    continue;
                }

                // Get max sort_order
                $stmt = $this->pdo->prepare("
                    SELECT COALESCE(MAX(sort_order), -1) as max_order 
                    FROM storyboard_frames 
                    WHERE storyboard_id = ?
                ");
                $stmt->execute([$storyboardId]);
                $maxOrder = (int)$stmt->fetch(PDO::FETCH_ASSOC)['max_order'];

                // Insert storyboard_frame record (not copied yet)
                $stmt = $this->pdo->prepare("
                    INSERT INTO storyboard_frames 
                    (storyboard_id, frame_id, name, filename, sort_order, is_copied, original_filename)
                    VALUES (?, ?, ?, ?, ?, 0, ?)
                ");
                $stmt->execute([
                    $storyboardId,
                    $frameId,
                    $frame['name'],
                    $frame['filename'], // Will be updated when physically copied
                    $maxOrder + 1,
                    $frame['filename']
                ]);

                $imported[] = $frameId;

            } catch (\Exception $e) {
                $errors[] = "Frame $frameId: " . $e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors
        ];
    }

    /**
     * Physically copy frames that haven't been copied yet
     * Called automatically when viewing a storyboard
     */
    public function copyPendingFrames(int $storyboardId): array
    {
        $copied = [];
        $errors = [];

        // Get storyboard directory
        $stmt = $this->pdo->prepare("SELECT directory FROM storyboards WHERE id = ?");
        $stmt->execute([$storyboardId]);
        $storyboard = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$storyboard) {
            return ['copied' => [], 'errors' => ['Storyboard not found']];
        }

        $storyboardDir = $this->docRoot . $storyboard['directory'];

        if (!is_dir($storyboardDir)) {
            if (!mkdir($storyboardDir, 0777, true)) {
                return ['copied' => [], 'errors' => ['Cannot create directory']];
            }
        }

        // Get frames that need copying
        $stmt = $this->pdo->prepare("
            SELECT sf.*, f.filename as source_filename 
            FROM storyboard_frames sf
            LEFT JOIN frames f ON sf.frame_id = f.id
            WHERE sf.storyboard_id = ? AND sf.is_copied = 0 AND sf.frame_id IS NOT NULL
        ");
        $stmt->execute([$storyboardId]);
        $framesToCopy = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($framesToCopy as $frame) {
            try {
                $sourceFile = $this->docRoot . '/' . ltrim($frame['source_filename'], '/');

                if (!file_exists($sourceFile)) {
                    $errors[] = "Source file not found: " . $frame['source_filename'];
                    continue;
                }

                $ext = pathinfo($sourceFile, PATHINFO_EXTENSION);
                $newFilename = 'frame' . str_pad($frame['id'], 7, '0', STR_PAD_LEFT) . '.' . $ext;
                $destFile = $storyboardDir . '/' . $newFilename;
                $destRelPath = $storyboard['directory'] . '/' . $newFilename;

                if (copy($sourceFile, $destFile)) {
                    // Update database
                    $updateStmt = $this->pdo->prepare("
                        UPDATE storyboard_frames 
                        SET filename = ?, is_copied = 1
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$destRelPath, $frame['id']]);
                    $copied[] = $frame['id'];
                } else {
                    $errors[] = "Copy failed for frame " . $frame['id'];
                }

            } catch (\Exception $e) {
                $errors[] = "Frame " . $frame['id'] . ": " . $e->getMessage();
            }
        }

        return [
            'copied' => $copied,
            'errors' => $errors
        ];
    }

    /**
     * Get storyboard statistics
     */
    public function getStoryboardStats(int $storyboardId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_frames,
                SUM(CASE WHEN is_copied = 1 THEN 1 ELSE 0 END) as copied_frames,
                SUM(CASE WHEN is_copied = 0 THEN 1 ELSE 0 END) as pending_frames
            FROM storyboard_frames
            WHERE storyboard_id = ?
        ");
        $stmt->execute([$storyboardId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Validate storyboard directory structure
     */
    public function validateStoryboard(int $storyboardId): array
    {
        $issues = [];

        // Check storyboard exists
        $stmt = $this->pdo->prepare("SELECT * FROM storyboards WHERE id = ?");
        $stmt->execute([$storyboardId]);
        $storyboard = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$storyboard) {
            return ['Storyboard not found'];
        }

        // Check directory exists
        $storyboardDir = $this->docRoot . $storyboard['directory'];
        if (!is_dir($storyboardDir)) {
            $issues[] = "Directory does not exist: " . $storyboard['directory'];
        } elseif (!is_writable($storyboardDir)) {
            $issues[] = "Directory not writable: " . $storyboard['directory'];
        }

        // Check for orphaned files
        $stmt = $this->pdo->prepare("
            SELECT filename FROM storyboard_frames 
            WHERE storyboard_id = ? AND is_copied = 1
        ");
        $stmt->execute([$storyboardId]);
        $dbFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($dbFiles as $file) {
            $fullPath = $this->docRoot . $file;
            if (!file_exists($fullPath)) {
                $issues[] = "Missing file: " . $file;
            }
        }

        return $issues;
    }

    /**
     * Clean up storyboard - remove files not in database
     */
    public function cleanupStoryboard(int $storyboardId): array
    {
        $removed = [];

        $stmt = $this->pdo->prepare("SELECT directory FROM storyboards WHERE id = ?");
        $stmt->execute([$storyboardId]);
        $storyboard = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$storyboard) {
            return ['removed' => [], 'error' => 'Storyboard not found'];
        }

        $storyboardDir = $this->docRoot . $storyboard['directory'];
        if (!is_dir($storyboardDir)) {
            return ['removed' => [], 'error' => 'Directory not found'];
        }

        // Get files in database
        $stmt = $this->pdo->prepare("
            SELECT filename FROM storyboard_frames 
            WHERE storyboard_id = ? AND is_copied = 1
        ");
        $stmt->execute([$storyboardId]);
        $dbFiles = array_map(function($f) {
            return basename($f);
        }, $stmt->fetchAll(PDO::FETCH_COLUMN));

        // Scan directory
        $files = scandir($storyboardDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $fullPath = $storyboardDir . '/' . $file;
            if (is_file($fullPath) && !in_array($file, $dbFiles)) {
                if (@unlink($fullPath)) {
                    $removed[] = $file;
                }
            }
        }

        return ['removed' => $removed];
    }

    /**
     * Generate a unique storyboard directory name
     */
    public function generateDirectoryName(string $baseName = 'storyboard'): string
    {
        $counter = 1;
        do {
            $dirName = $baseName . str_pad($counter, 3, '0', STR_PAD_LEFT);
            $directory = '/storyboards/' . $dirName;
            
            $stmt = $this->pdo->prepare("SELECT id FROM storyboards WHERE directory = ?");
            $stmt->execute([$directory]);
            
            if (!$stmt->fetch()) {
                return $directory;
            }
            $counter++;
        } while ($counter < 10000);

        throw new \Exception('Cannot generate unique directory name');
    }
}
