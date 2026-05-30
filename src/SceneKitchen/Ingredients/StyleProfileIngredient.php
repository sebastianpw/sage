<?php

namespace App\SceneKitchen\Ingredients;

use App\SceneKitchen\AbstractIngredient;

class StyleProfileIngredient extends AbstractIngredient
{
    public static function getType(): string { return 'style_profile'; }

    public function getLabel(): string { return $this->data['name'] ?? 'Unknown Profile'; }

    public function getIcon(): string { return '🎨'; }

    public function getPromptSegment(): string
    {
        $raw = $this->data['convert_result'] ?? '';
        if (empty($raw)) return '';

        // Attempt to parse JSON as requested
        $json = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (!empty($json['textualStylePrompt'])) {
                return "Visual Style: " . $json['textualStylePrompt'];
            }
        }

        // Fallback: Use raw text if JSON parsing fails or key is missing,
        // preventing empty context if data exists but isn't structured as expected.
        return "Visual Style: " . $raw; 
    }

    public static function fetchAvailable(\PDO $pdo, array $filters = []): array
    {
        // Only fetch profiles that have a conversion result
        $sql = "SELECT * FROM style_profiles WHERE convert_result IS NOT NULL AND convert_result != '' ORDER BY name ASC";
        $stmt = $pdo->query($sql);
        
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = new self((int)$row['id'], $row);
        }
        return $results;
    }
}
