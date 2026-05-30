<?php

namespace App\SceneKitchen\Ingredients;

use App\SceneKitchen\AbstractIngredient;

class GenericEntityIngredient extends AbstractIngredient
{
    private string $entityType;
    private string $icon;

    public function __construct(string $entityType, ?int $id = null, array $data = [], string $icon = '📦')
    {
        parent::__construct($id, $data);
        $this->entityType = $entityType;
        $this->icon = $icon;
    }

    public static function getType(): string 
    { 
        return 'generic_entity'; 
    }

    public function getSpecificType(): string
    {
        return $this->entityType;
    }

    public function getLabel(): string 
    { 
        return $this->data['name'] ?? ucfirst($this->entityType) . ' #' . $this->id; 
    }
    
    public function getIcon(): string 
    { 
        return $this->icon; 
    }

    public function getPromptSegment(): string
    {
        $name = $this->data['name'] ?? 'Unknown';
        $desc = $this->data['description'] ?? '';
        
        $desc = trim(strip_tags($desc));
        
        // Pretty print the type name (remove anivoc_ prefix for prompt clarity)
        $label = str_replace('anivoc_', '', $this->entityType);
        $label = ucfirst(str_replace('_', ' ', $label));
        
        // Singularize roughly
        if(substr($label, -1) == 's') $label = substr($label, 0, -1);

        return "{$label}: {$name}" . ($desc ? " - {$desc}" : "");
    }

    /**
     * Static fetcher for a specific table
     */
    public static function fetchFromTable(\PDO $pdo, string $tableName, string $icon): array
    {
        // Whitelist allowed tables
        $allowed = [
            'characters', 'animas', 'locations', 'backgrounds', 'vehicles', 'artifacts',
            'anivoc_expressions', 'anivoc_backgrounds', 'anivoc_motion_impact', 
            'anivoc_lighting', 'anivoc_transitions', 'anivoc_color_coding', 
            'anivoc_scale_perspective', 'anivoc_symbolic_objects', 
            'anivoc_text_graphics', 'anivoc_panel_frame'
        ];

        if (!in_array($tableName, $allowed)) {
            return [];
        }

        $sql = "SELECT * FROM `$tableName` WHERE is_ingredient = 1 ORDER BY id DESC";
//        $sql = "SELECT * FROM `$tableName` ORDER BY id DESC LIMIT 100";
        $stmt = $pdo->query($sql);
        
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = new self($tableName, (int)$row['id'], $row, $icon);
        }
        return $results;
    }

    public static function fetchAvailable(\PDO $pdo, array $filters = []): array { return []; }
}
