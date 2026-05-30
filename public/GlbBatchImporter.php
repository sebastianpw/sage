<?php
// public/GlbBatchImporter.php
class GlbBatchImporter
{
    private $pdo;
    private $entityType;
    private $importDir;
    private $meshDir;
    private $meshDirRel;
    private $projectRoot;

    public function __construct(PDO $pdo, string $entityType = 'dimensionals')
    {
        $this->pdo = $pdo;
        $this->entityType = $entityType;
        
        $this->projectRoot = defined('PROJECT_ROOT') ? PROJECT_ROOT : __DIR__ . '/..';
        
        // Paths
        $this->importDir = $this->projectRoot . '/public/import/meshes/';
        $this->meshDirRel = 'meshes/'; // Relative to public
        $this->meshDir = $this->projectRoot . '/public/meshes/';
        
        if (!is_dir($this->meshDir)) {
            mkdir($this->meshDir, 0775, true);
        }
    }

    public function runImport(array $files): array
    {
        $results = [];
        $runDir = $this->prepareDoneDir();

        foreach ($files as $filePath) {
            $filename = basename($filePath);
            
            if (!file_exists($filePath)) {
                $results[] = "[Error] File not found: $filename";
                continue;
            }

            try {
                $this->pdo->beginTransaction();

                // 1. Naming & Storage
                // Using timestamp + uniqid for uniqueness (no mesh_counter table yet)
                $safeBase = "mesh_" . date('Ymd_His') . "_" . uniqid();
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                
                $newFileName = $safeBase . '.' . $ext;
                $destPath = $this->meshDir . $newFileName;
                $relPath = $this->meshDirRel . $newFileName;

                // 2. Copy File
                if (!copy($filePath, $destPath)) {
                    throw new Exception("Failed to copy file to storage.");
                }

                // 3. Create Entity (Dimensionals)
                $entityName = pathinfo($filename, PATHINFO_FILENAME);
                
                $stmtEnt = $this->pdo->prepare("INSERT INTO `{$this->entityType}` (name, description, created_at) VALUES (?, 'Batch Import', NOW())");
                $stmtEnt->execute([$entityName]);
                $entityId = $this->pdo->lastInsertId();

                // 4. Create Map Run
                $stmtMR = $this->pdo->prepare("INSERT INTO map_runs (entity_type, note, created_at) VALUES (?, 'Batch Import', NOW())");
                $stmtMR->execute([$this->entityType]);
                $mapRunId = $this->pdo->lastInsertId();

                // 5. Update Entity
                $this->pdo->prepare("UPDATE `{$this->entityType}` SET active_map_run_id = ? WHERE id = ?")
                          ->execute([$mapRunId, $entityId]);

                // 6. Insert Asset Entry (meshes)
                // Note: Ensure your 'meshes' table schema has 'map_run_id' etc. based on previous SQL
                $stmtAsset = $this->pdo->prepare("INSERT INTO meshes (map_run_id, name, filename, entity_type, entity_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmtAsset->execute([
                    $mapRunId, 
                    $newFileName, 
                    $relPath,
                    $this->entityType,
                    $entityId
                ]);
                $assetId = $this->pdo->lastInsertId();

                // 7. Link (dimensionals_2_meshes)
                $linkTable = $this->entityType . "_2_meshes"; // e.g. dimensionals_2_meshes
                
                // Check if table exists to be safe
                $stmtLink = $this->pdo->prepare("INSERT INTO `$linkTable` (from_id, to_id) VALUES (?, ?)");
                $stmtLink->execute([$entityId, $assetId]);

                $this->pdo->commit();
                
                // Move original file to 'done'
                rename($filePath, $this->importDir . 'done/' . $runDir . '/' . $filename);
                
                $results[] = "[Success] Imported '$filename' as $newFileName (#$assetId)";

            } catch (Exception $e) {
                $this->pdo->rollBack();
                // Cleanup partial file if needed
                if (file_exists($destPath)) @unlink($destPath);
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
