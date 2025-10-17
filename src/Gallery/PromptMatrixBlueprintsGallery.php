<?php
namespace App\Gallery;

require_once "AbstractGallery.php";

class PromptMatrixBlueprintsGallery extends AbstractGallery {

    // ← include all filters here
    protected function getFiltersFromRequest(): array {
        return [
            'blueprint_name'       => $_GET['blueprint_name'] ?? 'all',
	    'blueprint_entity_type'=> $_GET['blueprint_entity_type'] ?? 'all',
	    'blueprint_entity_id'=> $_GET['blueprint_entity_id'] ?? 'all',
            'style'                => $_GET['style'] ?? 'all', // ← added style
        ];
    }

    protected function getFilterOptions(): array {
        $options = [];

        // Distinct blueprint names
        $options['blueprint_name'] = [
            'label'  => 'Names',
            'values' => $this->fetchDistinct('blueprint_name'),
            'left'   => 110
        ];

        // Distinct blueprint entity types
        $options['blueprint_entity_type'] = [
            'label'  => 'EntityTypes',
            'values' => $this->fetchDistinct('blueprint_entity_type'),
            'left'   => 0
	];


        // Distinct blueprint entity id
        $options['blueprint_entity_id'] = [
            'label'  => 'EntityIds',
            'values' => $this->fetchDistinct('blueprint_entity_id'),
            'left'   => 55
        ];

        // Distinct styles
        $options['style'] = [
            'label'  => 'Styles',
            'values' => $this->fetchDistinct('style'),
            'left'   => 165
        ];

        return $options;
    }

    protected function getWhereClause(): string {
        $clauses = [];
        foreach ($this->filters as $key => $val) {
            if ($val !== 'all') {
                $col = match($key) {
                    'blueprint_name'        => 'blueprint_name',
		    'blueprint_entity_type' => 'blueprint_entity_type',
		    'blueprint_entity_id' => 'blueprint_entity_id',
                    'style'                 => 'style',
                };
                $clauses[] = "$col='" . $this->mysqli->real_escape_string($val) . "'";
            }
        }
        return $clauses ? "WHERE " . implode(" AND ", $clauses) : '';
    }

    protected function getBaseQuery(): string {
        // use the blueprint-specific view (full name in the view)
        return "v_gallery_prompt_matrix_blueprints";
    }

    /*
    // Example renderItem override (optional)
    protected function renderItem(array $row): string {
        $filename = htmlspecialchars($row['filename']);
        $prompt   = htmlspecialchars($row['prompt']);
        $name     = htmlspecialchars($row['blueprint_name']);
        $type     = htmlspecialchars($row['blueprint_entity_type']);
        $style    = htmlspecialchars($row['style']); // optional display

        return <<<HTML
<div class="img-wrapper">
    <img src="{$filename}" alt="">
    <div class="caption">
        {$prompt}<br>
        <strong>Blueprint:</strong> {$name}<br>
        <strong>Type:</strong> {$type}<br>
        <strong>Style:</strong> {$style}
    </div>
</div>
HTML;
    }
    */

    protected function getCaptionFields(): array {
        return [
            'Entity ID'   => 'entity_id',
            'Frame ID'    => 'frame_id',
            'Blueprint'   => 'blueprint_name',
	    'EntityType'        => 'blueprint_entity_type',
            'EntityId'        => 'blueprint_entity_id',   
            'Style'       => 'style',
        ];
    }

    protected function getGalleryEntity(): string {
        // must match your full entity name
        return "prompt_matrix_blueprints";
    }

    protected function getGalleryTitle(): string {
        return "Prompt Matrix Blueprints Gallery";
    }

    protected function getToggleButtonLeft(): int {
        return 225; // sits just after the filter select
    }






protected function renderSequel(): string {
    return <<<'JAVASCRIPT'
<script>
class PromptMatrixBlueprintsGallery {
    constructor() {
        this.entityTypeSelect = $('select[name="blueprint_entity_type"]');
        this.entityIdSelect = $('select[name="blueprint_entity_id"]');
        this.blueprintNameSelect = $('select[name="blueprint_name"]');
        
        this.init();
    }
    
    init() {
        // When entity_type changes, update entity_id and blueprint_name
        this.entityTypeSelect.on('change', () => {
            this.updateFilters();
        });
        
        // When entity_id changes, update blueprint_name
        this.entityIdSelect.on('change', () => {
            this.updateBlueprintNames();
        });
        
        // Initial load: if filters are already selected, update dependent filters
        const initialEntityType = this.entityTypeSelect.val();
        const initialEntityId = this.entityIdSelect.val();
        
        if (initialEntityType && initialEntityType !== 'all') {
            this.updateFilters();
        } else if (initialEntityId && initialEntityId !== 'all') {
            // Edge case: entity_id selected but not entity_type
            this.updateBlueprintNames();
        }
    }
    
    async updateFilters() {
        const entityType = this.entityTypeSelect.val();
        
        if (!entityType || entityType === 'all') {
            // Reset to show all options
            await this.fetchAndPopulateFilters('all', 'all');
            return;
        }
        
        // Fetch filtered options based on entity_type
        await this.fetchAndPopulateFilters(entityType, 'all');
    }
    
    async updateBlueprintNames() {
        const entityType = this.entityTypeSelect.val();
        const entityId = this.entityIdSelect.val();
        
        // Fetch blueprint names filtered by both entity_type and entity_id
        await this.fetchAndPopulateFilters(
            entityType || 'all', 
            entityId || 'all'
        );
    }
    
    async fetchAndPopulateFilters(entityType, entityId) {
        try {
            const url = `/ajax_gallery_blueprints_filter_options.php?entity=prompt_matrix_blueprints&entity_type=${encodeURIComponent(entityType)}&entity_id=${encodeURIComponent(entityId)}`;
            
            const response = await fetch(url, { credentials: 'same-origin' });
            const data = await response.json();
            
            if (data.error) {
                console.error('Filter error:', data.error);
                Toast.show('Failed to update filters', 'error');
                return;
            }
            
            // Store current selections
            const currentEntityId = this.entityIdSelect.val();
            const currentBlueprintName = this.blueprintNameSelect.val();
            
            // Update entity_id select
            this.entityIdSelect.html('<option value="all">All EntityIds</option>');
            if (data.blueprint_entity_id && data.blueprint_entity_id.length > 0) {
                data.blueprint_entity_id.forEach(id => {
                    this.entityIdSelect.append($('<option>').val(id).text(id));
                });
                
                // Restore selection if still valid
                if (currentEntityId && data.blueprint_entity_id.includes(currentEntityId.toString())) {
                    this.entityIdSelect.val(currentEntityId);
                } else if (entityType !== 'all' && data.blueprint_entity_id.length === 1) {
                    // Auto-select if only one option available
                    this.entityIdSelect.val(data.blueprint_entity_id[0]);
                }
            }
            
            // Update blueprint_name select
            this.blueprintNameSelect.html('<option value="all">All Names</option>');
            if (data.blueprint_name && data.blueprint_name.length > 0) {
                data.blueprint_name.forEach(name => {
                    this.blueprintNameSelect.append($('<option>').val(name).text(name));
                });
                
                // Restore selection if still valid
                if (currentBlueprintName && data.blueprint_name.includes(currentBlueprintName)) {
                    this.blueprintNameSelect.val(currentBlueprintName);
                }
            }
            
        } catch (err) {
            console.error('Failed to fetch filter options:', err);
            Toast.show('Failed to update filters', 'error');
        }
    }
}

$(document).ready(function() {
    // Initialize the gallery filter manager
    window.pmBlueprintsGallery = new PromptMatrixBlueprintsGallery();
});
</script>
JAVASCRIPT;
}




}
