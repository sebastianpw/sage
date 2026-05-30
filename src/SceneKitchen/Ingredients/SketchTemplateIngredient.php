<?php

namespace App\SceneKitchen\Ingredients;

use App\SceneKitchen\AbstractIngredient;

class SketchTemplateIngredient extends AbstractIngredient
{
    public static function getType(): string { return 'sketch_template'; }
    
    public function getLabel(): string { return $this->data['name'] ?? 'Unknown Template'; }
    
    public function getIcon(): string { return '🎬'; }

    public function getPromptSegment(): string
    {
        $prompt = $this->data['example_prompt'] ?? '';
        $coreIdea = $this->data['core_idea'] ?? '';
        
        if (!empty($coreIdea)) {
            // Include Core Idea explicitly as requested
            $prompt .= " (Core Concept: " . $coreIdea . ")";
        }
        return $prompt;
    }

    public static function fetchAvailable(\PDO $pdo, array $filters = []): array
    {
        $sql = "SELECT * FROM sketch_templates WHERE active = 1 AND entity_type = 'sketches'";
        $params = [];

        // Filter by Category (Directorial Group)
        if (!empty($filters['category_id'])) {
            $sql .= " AND category_id = ?";
            $params[] = (int)$filters['category_id'];
        }

        $sql .= " ORDER BY name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = new self((int)$row['id'], $row);
        }
        return $results;
    }
}
