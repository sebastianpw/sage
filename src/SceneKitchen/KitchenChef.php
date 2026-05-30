<?php

namespace App\SceneKitchen;

use App\SceneKitchen\Ingredients\SketchTemplateIngredient;
use App\SceneKitchen\Ingredients\InteractionIngredient;
use App\SceneKitchen\Ingredients\StyleProfileIngredient;
use App\SceneKitchen\Ingredients\GenericEntityIngredient;

class KitchenChef
{
    private \PDO $pdo;

    // Icons mapping for hydration logic
    private array $iconsMap = [
        'characters' => '🦸', 'character_poses' => '🤸', 'animas' => '🐾',
        'locations' => '🗺️', 'backgrounds' => '🏞️', 'artifacts' => '🏺',
        'vehicles' => '🛸', 'scene_parts' => '🎬', 'controlnet_maps' => '☠️',
        'spawns' => '🌱', 'generatives' => '⚡', 'sketches' => '🪄',
        'prompt_matrix_blueprints' => '🌌', 'composites' => '🧩',
        // Anime Vocab
        'anivoc_expressions' => '😊', 'anivoc_backgrounds' => '🏙️',
        'anivoc_motion_impact' => '💥', 'anivoc_lighting' => '💡',
        'anivoc_transitions' => '🎞️', 'anivoc_color_coding' => '🎨',
        'anivoc_scale_perspective' => '📐', 'anivoc_symbolic_objects' => '🗿',
        'anivoc_text_graphics' => '🗯️', 'anivoc_panel_frame' => '🖼️'
    ];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Convert raw {type, id} arrays into populated Ingredient objects
     */
    public function hydrateIngredients(array $rawIngredients): array
    {
        $cooked = [];
        
        foreach ($rawIngredients as $raw) {
            $type = $raw['type'] ?? '';
            $id = (int)($raw['id'] ?? 0);
            if (!$type || !$id) continue;

            $obj = null;

            if ($type === 'sketch_template') {
                $row = $this->fetchRow('sketch_templates', $id);
                if ($row) $obj = new SketchTemplateIngredient($id, $row);
            } 
            elseif ($type === 'interaction') {
                $row = $this->fetchRow('interactions', $id);
                if ($row) $obj = new InteractionIngredient($id, $row);
            }
            elseif ($type === 'style_profile') {
                $row = $this->fetchRow('style_profiles', $id);
                if ($row) $obj = new StyleProfileIngredient($id, $row);
            }
            else {
                // Try Generic Entity
                if (array_key_exists($type, $this->iconsMap) || str_starts_with($type, 'anivoc_')) {
                    $row = $this->fetchRow($type, $id);
                    if ($row) {
                        $icon = $this->iconsMap[$type] ?? '📦';
                        $obj = new GenericEntityIngredient($type, $id, $row, $icon);
                    }
                }
            }

            if ($obj) {
                $cooked[] = $obj;
            }
        }

        return $cooked;
    }

    private function fetchRow(string $table, int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function saveSketch(string $name, string $finalPrompt, array $ingredients, int $descGenId, int $nameGenId): int
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Insert Sketch
            $stmt = $this->pdo->prepare("INSERT INTO sketches (name, description, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $stmt->execute([$name, $finalPrompt]);
            $sketchId = $this->pdo->lastInsertId();

            // 2. Insert Ingredients (Flexible System)
            $order = 0;
            $iStmt = $this->pdo->prepare("INSERT INTO sketch_ingredients (sketch_id, ingredient_type, source_id, prompt_fragment, snapshot_data, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
            
            // Add Generator configs as "ingredients" for the flexible system
            $iStmt->execute([$sketchId, 'generator_config_desc', $descGenId, 'Used to generate description', null, $order++]);
            $iStmt->execute([$sketchId, 'generator_config_name', $nameGenId, 'Used to generate name', null, $order++]);

            // Variables for Meta Sketches (Redundant but required for UI/Autopilot compatibility)
            $metaTemplateId = null;
            $metaInteractionId = null;

            foreach ($ingredients as $ing) {
                /** @var AbstractIngredient $ing */
                $type = ($ing instanceof GenericEntityIngredient) ? $ing->getSpecificType() : $ing::getType();

                // Capture IDs for the rigid meta table
                if ($type === 'sketch_template') {
                    $metaTemplateId = $ing->getId();
                }
                if ($type === 'interaction') {
                    $metaInteractionId = $ing->getId();
                }

                $iStmt->execute([
                    $sketchId,
                    $type,
                    $ing->getId(),
                    $ing->getPromptSegment(),
                    json_encode($ing->getSnapshotData()),
                    $order++
                ]);
            }

            // 3. Insert Meta Sketches (Rigid System)
            // This ensures compatibility with public/view_map_runs_sketches.php
            
            // First, get/create generator revisions
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
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Ensures we have a history revision for the generator config to link in meta_sketches
     */
    private function ensureGeneratorRevision(int $configId): ?array
    {
        // Fetch current config
        $stmt = $this->pdo->prepare("SELECT * FROM generator_config WHERE id = ?");
        $stmt->execute([$configId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$row) return null;

        // Build snapshot (Normalize JSON fields to arrays to match structure used in cli_autopilot)
        // Note: In DB they are strings, but we want the hash to represent structure
        $snapshot = [
            'system_role'   => $row['system_role'],
            'instructions'  => json_decode($row['instructions'] ?? '[]', true),
            'parameters'    => json_decode($row['parameters'] ?? '[]', true),
            'output_schema' => json_decode($row['output_schema'] ?? '[]', true),
            'oracle_config' => json_decode($row['oracle_config'] ?? 'null', true),
            'model'         => $row['model']
        ];

        $jsonSnapshot = json_encode($snapshot);
        $hash = md5($jsonSnapshot);

        // Check for existing revision
        $hStmt = $this->pdo->prepare("SELECT id FROM generator_config_history WHERE generator_config_id = ? AND config_hash = ?");
        $hStmt->execute([$configId, $hash]);
        $historyId = $hStmt->fetchColumn();

        if ($historyId) {
            return ['db_id' => $configId, 'history_id' => $historyId];
        }

        // Create new revision
        $iStmt = $this->pdo->prepare("INSERT INTO generator_config_history (generator_config_id, config_hash, snapshot_data, created_at) VALUES (?, ?, ?, NOW())");
        $iStmt->execute([$configId, $hash, $jsonSnapshot]);
        
        return ['db_id' => $configId, 'history_id' => $this->pdo->lastInsertId()];
    }

    public function generateRandomRecipe(): array
    {
        $ingredients = [];

        if (rand(1, 100) <= 80) {
            $templates = SketchTemplateIngredient::fetchAvailable($this->pdo);
            if (!empty($templates)) $ingredients[] = $templates[array_rand($templates)];
        }

        if (rand(1, 100) <= 60) {
            $interactions = InteractionIngredient::fetchAvailable($this->pdo);
            if (!empty($interactions)) $ingredients[] = $interactions[array_rand($interactions)];
        }

        if (rand(1, 100) <= 70) {
            $stmt = $this->pdo->query("SELECT table_name FROM anivoc_categories ORDER BY RAND() LIMIT 1");
            $cat = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($cat) {
                $tName = $cat['table_name'];
                $itemStmt = $this->pdo->query("SELECT * FROM `$tName` ORDER BY RAND() LIMIT 1");
                $row = $itemStmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $icon = $this->iconsMap[$tName] ?? '📘';
                    $ingredients[] = new GenericEntityIngredient($tName, (int)$row['id'], $row, $icon);
                }
            }
        }
        
        if (rand(1, 100) <= 40) {
            $itemStmt = $this->pdo->query("SELECT * FROM characters ORDER BY RAND() LIMIT 1");
            $row = $itemStmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) $ingredients[] = new GenericEntityIngredient('characters', (int)$row['id'], $row, '🦸');
        }

        return $ingredients;
    }
}
