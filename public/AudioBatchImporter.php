<?php

class AudioBatchImporter
{
    private $pdo;
    private $entityType;
    private $mode; // 1 = Source, 0 = Result
    private $importDir;
    private $audioDir;
    private $audioDirRel;

    public function __construct(PDO $pdo, string $entityType, int $mode)
    {
        $this->pdo = $pdo;
        $this->entityType = $entityType;
        $this->mode = $mode;
        
        // Paths
        $this->importDir = PROJECT_ROOT . '/public/import/audios/';
        $this->audioDirRel = '/audios/';
        $this->audioDir = PROJECT_ROOT . '/public/audios/';
        
        if (!is_dir($this->audioDir)) {
            mkdir($this->audioDir, 0777, true);
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

                // 1. Generate unique internal filename (audio000XXXX.wav)
                $this->pdo->exec("UPDATE audio_counter SET next_audio = LAST_INSERT_ID(next_audio + 1)");
                $countId = $this->pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();
                
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $newBaseName = "audio" . str_pad($countId, 7, '0', STR_PAD_LEFT);
                $newFileName = $newBaseName . '.' . $ext;
                $destPath = $this->audioDir . $newFileName;
                $relPath = $this->audioDirRel . $newFileName;

                // 2. Copy File to /public/audios/
                if (!copy($filePath, $destPath)) {
                    throw new Exception("Failed to copy file to storage.");
                }

                // 3. Create NEW Entity Row
                // Use filename (without ext) as Name
                $entityName = pathinfo($filename, PATHINFO_FILENAME);
                
                // Ensure unique name? For now just insert.
                $stmtEnt = $this->pdo->prepare("INSERT INTO `{$this->entityType}` (name, description, created_at) VALUES (:name, 'Imported Batch', NOW())");
                $stmtEnt->execute(['name' => $entityName]);
                $entityId = $this->pdo->lastInsertId();

                $audioId = 0;

                if ($this->mode === 1) {
                    // --- MODE: WAV2WAV=1 (Source) ---
                    // Insert into audios (no map run)
                    $stmtAudio = $this->pdo->prepare("INSERT INTO audios (name, filename, entity_type, entity_id, created_at) VALUES (:name, :file, :type, :eid, NOW())");
                    $stmtAudio->execute([
                        'name' => $newBaseName . " (Import Src)",
                        'file' => $relPath,
                        'type' => $this->entityType,
                        'eid'  => $entityId
                    ]);
                    $audioId = $this->pdo->lastInsertId();

                    // Update Entity
                    $sqlUpd = "UPDATE `{$this->entityType}` SET wav2wav = 1, wav2wav_audio_id = :aid, wav2wav_audio_filename = :file WHERE id = :eid";
                    $this->pdo->prepare($sqlUpd)->execute(['aid' => $audioId, 'file' => $relPath, 'eid' => $entityId]);

                } else {
                    // --- MODE: WAV2WAV=0 (Result) ---
                    // Create Map Run
                    $stmtMR = $this->pdo->prepare("INSERT INTO map_runs (entity_type, note, created_at) VALUES (:type, 'Batch Import', NOW())");
                    $stmtMR->execute(['type' => $this->entityType]);
                    $mapRunId = $this->pdo->lastInsertId();

                    // Insert Audio
                    $stmtAudio = $this->pdo->prepare("INSERT INTO audios (name, filename, entity_type, entity_id, map_run_id, created_at) VALUES (:name, :file, :type, :eid, :mrid, NOW())");
                    $stmtAudio->execute([
                        'name' => $newBaseName,
                        'file' => $relPath,
                        'type' => $this->entityType,
                        'eid'  => $entityId,
                        'mrid' => $mapRunId
                    ]);
                    $audioId = $this->pdo->lastInsertId();

                    // Link Table
                    $linkTable = "audios_2_" . $this->entityType;
                    $stmtLink = $this->pdo->prepare("INSERT INTO `$linkTable` (from_id, to_id) VALUES (:aid, :eid)");
                    $stmtLink->execute(['aid' => $audioId, 'eid' => $entityId]);
                    
                    // Set Active Map Run
                    $this->pdo->prepare("UPDATE `{$this->entityType}` SET active_map_run_id = ? WHERE id = ?")->execute([$mapRunId, $entityId]);
                }

                $this->pdo->commit();
                
                // Move original file to 'done' folder
                rename($filePath, $this->importDir . 'done/' . $runDir . '/' . $filename);
                
                $results[] = "[Success] Imported '$filename' as #$entityId";

            } catch (Exception $e) {
                $this->pdo->rollBack();
                if (file_exists($destPath)) @unlink($destPath); // Cleanup bad copy
                $results[] = "[Failed] '$filename': " . $e->getMessage();
            }
        }

        return $results;
    }

    private function prepareDoneDir()
    {
        $base = $this->importDir . 'done/';
        if (!is_dir($base)) mkdir($base, 0777, true);
        
        // Find next run number
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
