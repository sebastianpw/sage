<?php
// public/sceki/KitchenChef.php
// Scene Kitchen v2 — KitchenChef implementation
// PDO-only, no Doctrine.
// ─────────────────────────────────────────────────────
declare(strict_types=1);

use PDO;
use PDOException;

class KitchenChefV2
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function saveSketch(string $name, string $description, array $ingredients, int $descGenId, int $nameGenId): int
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Insert Sketch (saving original to description_raw as well)
            $stmt = $this->pdo->prepare("INSERT INTO sketches (name, description, description_raw, `order`, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW())");
            $stmt->execute([$name, $description, $description]);
            $sketchId = (int)$this->pdo->lastInsertId();

            // 2. Insert Ingredients
            $order = 0;
            $iStmt = $this->pdo->prepare("INSERT INTO sketch_ingredients (sketch_id, ingredient_type, source_id, prompt_fragment, snapshot_data, sort_order) VALUES (?, ?, ?, ?, ?, ?)");

            // Add generator configs as ingredients for flexible system
            $iStmt->execute([$sketchId, 'generator_config_desc', $descGenId, 'Used to generate description', null, $order++]);
            $iStmt->execute([$sketchId, 'generator_config_name', $nameGenId, 'Used to generate name', null, $order++]);

            $metaTemplateId = null;
            $metaInteractionId = null;

            foreach ($ingredients as $ing) {
                $type = (string)($ing['type'] ?? '');
                $rawId = $ing['id'] ?? null;
                if (!$type || !$rawId) continue;

                $id = 0;
                if ($type === '_kg_subpot') {
                    // subpot id is a string ("kg_1_2_3"), so keep $id = 0 and pass null to DB
                } else {
                    $id = (int)$rawId;
                    if (!$id) continue;
                }

                // Capture IDs for the rigid meta table
                if ($type === 'sketch_template') {
                    $metaTemplateId = $id;
                }
                if ($type === 'interaction') {
                    $metaInteractionId = $id;
                }

                $promptFragment = $ing['label'] ?? '';
                $snapshot = json_encode($ing, JSON_UNESCAPED_UNICODE);

                $iStmt->execute([
                    $sketchId,
                    $type,
                    $id > 0 ? $id : null,
                    $promptFragment,
                    $snapshot,
                    $order++
                ]);
            }

            // 3. Insert Meta Sketches (Rigid System for backward compatibility)
            $descRev = $this->ensureGeneratorRevision($descGenId);
            $nameRev = $this->ensureGeneratorRevision($nameGenId);

            $metaStmt = $this->pdo->prepare("
                INSERT INTO meta_sketches 
                (sketch_id, desc_gen_config_id, desc_gen_history_id, name_gen_config_id, name_gen_history_id, sketch_template_id, interaction_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $metaStmt->execute([
                $sketchId,
                $descRev ? $descRev['db_id'] : $descGenId,
                $descRev ? $descRev['history_id'] : null,
                $nameRev ? $nameRev['db_id'] : $nameGenId,
                $nameRev ? $nameRev['history_id'] : null,
                $metaTemplateId,
                $metaInteractionId
            ]);

            $this->pdo->commit();
            return $sketchId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function ensureGeneratorRevision(int $configId): ?array
    {
        if ($configId <= 0) return null;

        $stmt = $this->pdo->prepare("SELECT * FROM generator_config WHERE id = ?");
        $stmt->execute([$configId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $snapshot = [
            'system_role'   => $row['system_role'],
            'instructions'  => json_decode($row['instructions'] ?? '[]', true),
            'parameters'    => json_decode($row['parameters'] ?? '[]', true),
            'output_schema' => json_decode($row['output_schema'] ?? '[]', true),
            'oracle_config' => json_decode($row['oracle_config'] ?? 'null', true),
            'model'         => $row['model']
        ];

        $jsonSnapshot = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
        $hash = md5($jsonSnapshot);

        $hStmt = $this->pdo->prepare("SELECT id FROM generator_config_history WHERE generator_config_id = ? AND config_hash = ?");
        $hStmt->execute([$configId, $hash]);
        $historyId = $hStmt->fetchColumn();

        if ($historyId) {
            return ['db_id' => $configId, 'history_id' => $historyId];
        }

        $iStmt = $this->pdo->prepare("INSERT INTO generator_config_history (generator_config_id, config_hash, snapshot_data, created_at) VALUES (?, ?, ?, NOW())");
        $iStmt->execute([$configId, $hash, $jsonSnapshot]);
        
        return ['db_id' => $configId, 'history_id' => $this->pdo->lastInsertId()];
    }
}