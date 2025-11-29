<?php
// src/Gallery/PromptMatrixBlueprintsNuGallery.php
namespace App\Gallery;
require_once __DIR__ . '/AbstractNuGallery.php';
class PromptMatrixBlueprintsNuGallery extends AbstractNuGallery {
    protected function getFiltersFromRequest(): array {
        return [
            'blueprint_name'        => $_GET['blueprint_name'] ?? 'all',
            'blueprint_entity_type' => $_GET['blueprint_entity_type'] ?? 'all',
            'blueprint_entity_id'   => $_GET['blueprint_entity_id'] ?? 'all',
            'style'                 => $_GET['style'] ?? 'all',
        ];
    }
    protected function getFilterOptions(): array {
        $options = [];
        $options['blueprint_name'] = [
            'label'  => 'Names',
            'values' => $this->fetchDistinct('blueprint_name'),
            'left'   => 110
        ];
        $options['blueprint_entity_type'] = [
            'label'  => 'EntityTypes',
            'values' => $this->fetchDistinct('blueprint_entity_type'),
            'left'   => 0
        ];
        $options['blueprint_entity_id'] = [
            'label'  => 'EntityIds',
            'values' => $this->fetchDistinct('blueprint_entity_id'),
            'left'   => 55
        ];
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
                    'blueprint_entity_id'   => 'blueprint_entity_id',
                    'style'                 => 'style',
                };
                $clauses[] = "$col='" . $this->mysqli->real_escape_string($val) . "'";
            }
        }
        return $clauses ? "WHERE " . implode(" AND ", $clauses) : '';
    }
    protected function getBaseQuery(): string {
        return "v_gallery_prompt_matrix_blueprints";
    }
    protected function getCaptionFields(): array {
        return [
            'Entity ID'  => 'entity_id',
            'Frame ID'   => 'frame_id',
            'Blueprint'  => 'blueprint_name',
            'EntityType' => 'blueprint_entity_type',
            'EntityId'   => 'blueprint_entity_id',
            'Style'      => 'style',
        ];
    }
    protected function getGalleryEntity(): string {
        return "prompt_matrix_blueprints";
    }
    protected function getGalleryTitle(): string {
        return "Prompt Matrix Blueprints Gallery";
    }
    protected function getToggleButtonLeft(): int {
        return 225;
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
        this.entityTypeSelect.on('change', () => { this.updateFilters(); });
        this.entityIdSelect.on('change', () => { this.updateBlueprintNames(); });
        
        const initialEntityType = this.entityTypeSelect.val();
        const initialEntityId = this.entityIdSelect.val();
        
        if (initialEntityType && initialEntityType !== 'all') {
            this.updateFilters();
        } else if (initialEntityId && initialEntityId !== 'all') {
            this.updateBlueprintNames();
        }
    }
    
    async updateFilters() {
        const entityType = this.entityTypeSelect.val();
        if (!entityType || entityType === 'all') {
            await this.fetchAndPopulateFilters('all', 'all');
            return;
        }
        await this.fetchAndPopulateFilters(entityType, 'all');
    }
    
    async updateBlueprintNames() {
        const entityType = this.entityTypeSelect.val();
        const entityId = this.entityIdSelect.val();
        await this.fetchAndPopulateFilters(entityType || 'all', entityId || 'all');
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
            
            const currentEntityId = this.entityIdSelect.val();
            const currentBlueprintName = this.blueprintNameSelect.val();
            
            this.entityIdSelect.html('<option value="all">All EntityIds</option>');
            if (data.blueprint_entity_id && data.blueprint_entity_id.length > 0) {
                data.blueprint_entity_id.forEach(id => {
                    this.entityIdSelect.append($('<option>').val(id).text(id));
                });
                
                if (currentEntityId && data.blueprint_entity_id.includes(currentEntityId.toString())) {
                    this.entityIdSelect.val(currentEntityId);
                } else if (entityType !== 'all' && data.blueprint_entity_id.length === 1) {
                    this.entityIdSelect.val(data.blueprint_entity_id[0]);
                }
            }
            
            this.blueprintNameSelect.html('<option value="all">All Names</option>');
            if (data.blueprint_name && data.blueprint_name.length > 0) {
                data.blueprint_name.forEach(name => {
                    this.blueprintNameSelect.append($('<option>').val(name).text(name));
                });
                
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
    window.pmBlueprintsGallery = new PromptMatrixBlueprintsGallery();
});
</script>
JAVASCRIPT;
    }
}
