<?php

namespace App\SceneKitchen\Ingredients;

use App\SceneKitchen\AbstractIngredient;

class InteractionIngredient extends AbstractIngredient
{
    public static function getType(): string { return 'interaction'; }
    
    public function getLabel(): string { return $this->data['name'] ?? 'Unknown Interaction'; }
    
    public function getIcon(): string { return '🤝'; }

    public function getPromptSegment(): string
    {
        return ($this->data['example_prompt'] ?? '') . " (" . ($this->data['description'] ?? '') . ")";
    }

    public static function fetchAvailable(\PDO $pdo, array $filters = []): array
    {
        $sql = "SELECT * FROM interactions WHERE active = 1";
        $params = [];
        
        if (!empty($filters['group'])) {
            $sql .= " AND interaction_group = ?";
            $params[] = $filters['group'];
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND category = ?";
            $params[] = $filters['category'];
        }

        $sql .= " ORDER BY interaction_group, category, name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = new self((int)$row['id'], $row);
        }
        return $results;
    }
}
