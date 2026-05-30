<?php
// src/UI/Modules/GearMenuModule.php
namespace App\UI\Modules;

/**
 * GearMenuModule - Fully standalone, configurable gear menu system
 */
class GearMenuModule
{
    private array $config = [];
    private array $menuItems = [];
    private string $cssId = 'gear-menu-styles';
    private string $jsId = 'gear-menu-script';

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'entity_types' => ['characters', 'character_poses', 'character_expressions', 'character_anima_poses', 'animas', 'locations', 'backgrounds', 'artifacts', 'vehicles', 'scene_parts', 'controlnet_maps', 'spawns', 'generatives', 'sketches', 'prompt_matrix_blueprints', 'composites'],
            'show_for_entities' => null,
            'exclude_entities' => [],
            'icon' => '&#9881;',
            'icon_size' => '2em',
            'position' => 'top-right',
            'menu_actions' => [],
            'global_actions' => true,
        ], $config);
    }

    public function addAction(string $entity, array $action): self
    {
        if (!isset($this->menuItems[$entity])) $this->menuItems[$entity] = [];
        $this->menuItems[$entity][] = [
            'label' => $action['label'] ?? 'Action',
            'icon' => $action['icon'] ?? '',
            'callback' => $action['callback'] ?? 'function(){}',
            'condition' => $action['condition'] ?? null,
            'separator' => $action['separator'] ?? false,
        ];
        return $this;
    }

    /**
     * CENTRALIZED ACTION DEFINITIONS
     * Adds the standard suite of actions to a specific entity.
     * 
     * @param string $entity The entity type (e.g., 'characters')
     * @param array $options Configuration options:
     *                       - 'exclude' => ['rate', 'delete'] (keys to exclude)
     *                       - 'overrides' => ['delete' => ['callback' => '...']] (custom properties)
     */
    public function addStandardActions(string $entity, array $options = []): self
    {
        $exclude = $options['exclude'] ?? [];
        $overrides = $options['overrides'] ?? [];

        // Define the standard library of actions
        $actions = [
            'view_frame' => [
                'label' => 'View Frame',
                'icon' => '👁️',
                'callback' => 'window.showFrameDetailsModal(frameId);',
                'condition' => 'frameId > 0'
            ],
            'rate' => [
                'label' => 'Rate Frame',
                'icon' => '⭐',
                'callback' => 'window.openRatingMenu(frameId, $(wrapper), $(this));'
            ],
            'generative' => [
                'label' => 'Import to Generative',
                'icon' => '⚡',
                'callback' => 'window.importGenerative(entity, entityId, frameId);'
            ],
            'animatic' => [
                'label' => 'Import to Animatics',
                'icon' => '🎥',
                'callback' => 'window.importAnimatic(entity, entityId, frameId);'
            ],
            'edit_entity' => [
                'label' => 'Edit Entity',
                'icon' => '✏️',
                'callback' => 'window.showEntityFormInModal(entity, entityId);'
            ],
            'edit_image' => [
                'label' => 'Edit Image',
                'icon' => '🖌️',
                'callback' => 'const $w = $(wrapper); if (typeof ImageEditorModal !== "undefined") { ImageEditorModal.open({ entity: entity, entityId: entityId, frameId: frameId, src: $w.find("img").attr("src") || $w.find("img").attr("data-src") }); }'
            ],
            'frame_chain' => [
                'label' => 'View Frame Chain',
                'icon' => '🔗',
                'callback' => 'window.showFrameChainInModal(frameId);'
            ],
            'storyboard' => [
                'label' => 'Add to Storyboard',
                'icon' => '🎬',
                'callback' => 'window.selectStoryboard(frameId, $(wrapper));'
            ],
            'composite' => [
                'label' => 'Assign to Composite',
                'icon' => '🧩',
                'callback' => 'window.showImportEntityModal({ source: entity, target: "composites", source_entity_id: entityId, frame_id: frameId, limit: 1, copy_name_desc: 0, composite: 1 });'
            ],
            'mouthshapes' => [
                 'label' => 'Import Mouthshapes',
                 'icon' => '👄',
                 'callback' => 'window.importMouthshapes(entity, entityId, frameId);',
                 'condition' => '["characters", "animas"].includes(entity)'
            ],
            'controlnet' => [
                'label' => 'Import to ControlNet Map',
                'icon' => '☠️',
                'callback' => 'window.importControlNetMap(entity, entityId, frameId);'
            ],
            'prompt_matrix' => [
                'label' => 'Use Prompt Matrix',
                'icon' => '🌌',
                'callback' => 'window.usePromptMatrix(entity, entityId, frameId);'
            ],
            'delete' => [
                'label' => 'Delete Frame',
                'icon' => '🗑️',
                'callback' => 'window.deleteFrame(entity, entityId, frameId);'
            ]
        ];

        foreach ($actions as $key => $actionData) {
            // Skip if excluded
            if (in_array($key, $exclude)) {
                continue;
            }

            // Apply overrides
            if (isset($overrides[$key])) {
                $actionData = array_merge($actionData, $overrides[$key]);
            }

            $this->addAction($entity, $actionData);
        }

        return $this;
    }

    public function setActionsForEntity(string $entity, array $actions): self
    {
        $this->menuItems[$entity] = [];
        foreach ($actions as $action) $this->addAction($entity, $action);
        return $this;
    }

    public function renderCSS(): string
    {
        $iconSize = $this->config['icon_size'];
        $position = $this->getPositionStyles();
        
        return <<<CSS
<style id="{$this->cssId}">
[data-entity] { position: relative !important; }

.gear-icon {
    position: absolute; {$position} font-size: {$iconSize}; cursor: pointer; z-index: 500;
    color: rgba(255,255,255,0.9); text-shadow: 0 2px 4px rgba(0,0,0,0.8);
    transition: all 0.2s ease; user-select: none; pointer-events: auto;
    background: rgba(0,0,0,0.3); width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center; border-radius: 4px;
}
.gear-icon:hover { color: rgba(0,255,255,1); background: rgba(0,0,0,0.6); transform: scale(1.1) rotate(45deg); }

.gear-menu {
    display: block !important; visibility: hidden; opacity: 0; position: absolute; top: 42px; right: 0;
    background: rgba(26, 26, 26, 0.98); border: 1px solid rgba(0, 255, 255, 0.3);
    border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.6), 0 0 20px rgba(0, 255, 255, 0.15);
    z-index: 10000000; min-width: 200px; backdrop-filter: blur(10px);
    transition: opacity 0.2s ease, visibility 0.2s ease; pointer-events: none;
}
.gear-menu.active { visibility: visible; opacity: 1; pointer-events: auto; }

.gear-menu button {
    display: block; width: 100%; padding: 12px 16px; background: transparent; border: none;
    color: rgba(255,255,255,0.9); text-align: left; cursor: pointer; font-size: 14px;
    transition: all 0.15s ease; border-bottom: 1px solid rgba(255,255,255,0.05);
}
.gear-menu button:last-child { border-bottom: none; }
.gear-menu button:hover { background: rgba(0, 255, 255, 0.1); color: rgba(0, 255, 255, 1); padding-left: 20px; }
.gear-menu button:active { background: rgba(0, 255, 255, 0.2); }
.gear-menu-separator { height: 1px; background: rgba(255,255,255,0.1); margin: 4px 0; }

/* === STORYBOARD & RATING MENUS === */
.sb-menu {
    position: fixed; background: rgba(26, 26, 26, 0.98); border: 1px solid rgba(0, 255, 255, 0.3);
    border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.7);
    z-index: 20000000 !important; min-width: 250px; max-width: 320px;
    padding: 0; overflow: hidden; backdrop-filter: blur(10px);
}
.sb-filters {
    padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.1);
    background: rgba(0,0,0,0.2); display: flex; flex-direction: column; gap: 8px;
}
.sb-editorial-group { display: flex; flex-direction: column; gap: 8px; }
.sb-select {
    width: 100%; box-sizing: border-box; padding: 6px 8px;
    background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.2);
    border-radius: 4px; color: #fff; font-size: 13px; outline: none;
}
.sb-select:focus { border-color: rgba(0,255,255,0.5); background: rgba(0,0,0,0.6); }
.sb-select:disabled { opacity: 0.5; cursor: not-allowed; }
.sb-list-container { max-height: 300px; overflow-y: auto; }
.sb-menu-item {
    display: block; width: 100%; padding: 10px 16px; background: transparent; border: none;
    color: rgba(255,255,255,0.9); text-align: left; cursor: pointer; font-size: 13px;
    transition: all 0.1s ease; border-bottom: 1px solid rgba(255,255,255,0.03);
}
.sb-menu-item:hover { background: rgba(0, 255, 255, 0.1); color: rgba(0, 255, 255, 1); padding-left: 20px; }
.sb-footer { border-top: 1px solid rgba(255,255,255,0.1); font-weight: 500; color: rgba(0,255,255,0.8); }

.sb-cat-header {
    padding: 8px 12px; background: rgba(255,255,255,0.1); font-size: 11px;
    text-transform: uppercase; color: rgba(0,255,255,0.8); font-weight: 600;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.sb-rating-wrap {
    padding: 10px 16px; display: flex; justify-content: center; gap: 8px; cursor: default;
}
.sb-star {
    font-size: 24px; color: #ffd700; cursor: pointer; line-height: 1; transition: transform 0.1s;
    text-shadow: 0 2px 5px rgba(0,0,0,0.5);
}
.sb-star:hover { transform: scale(1.3); }

.gear-pagination-shift { transform: translateY(14px); pointer-events: none !important; opacity: 0 !important; visibility: hidden !important; transition: transform 160ms ease, opacity 160ms ease; }
</style>
CSS;
    }

    public function renderJS(): string
    {
        $menuConfig = $this->buildMenuConfig();
        $menuConfigJson = json_encode($menuConfig, JSON_PRETTY_PRINT);

        $jsNowdoc = <<<'JS'
<script id="%%JSID%%">
(function() {
    'use strict';
    const GEAR_MENU_CONFIG = %%MENU_CONFIG%%;
    
    window.GearMenu = {
        attach: function(context) {
            const $root = context instanceof jQuery ? context : $(context || document);
            $root.find('[data-entity]').each(function() {
                const $wrapper = $(this);
                const entity = $wrapper.data('entity');
                const entityId = $wrapper.data('entity-id');
                const frameId = $wrapper.data('frame-id');
                if ($wrapper.find('.gear-icon').length > 0) {
                    attachMenuToGear($wrapper.find('.gear-icon').first(), $wrapper, entity, entityId, frameId);
                } else {
                    const $gear = $('<span class="gear-icon">&#9881;</span>');
                    $wrapper.append($gear);
                    attachMenuToGear($gear, $wrapper, entity, entityId, frameId);
                }
            });
            $(document).off('click.gearmenu').on('click.gearmenu', function(e) {
                if (!$(e.target).closest('.gear-menu, .gear-icon, .sb-menu').length) {
                    window.GearMenu.closeAll();
                }
            });
        },
        open: function(element) { $(element).find('.gear-icon').trigger('click'); },
        closeAll: function() {
            $('.gear-menu').removeClass('active');
            $('.sb-menu').remove();
            $('.swiper-pagination, .gallery-pagination, .pagination').removeClass('gear-pagination-shift');
        },
        getActionsFor: function(element) {
            const $w = $(element);
            const menuConfig = GEAR_MENU_CONFIG[$w.data('entity')] || GEAR_MENU_CONFIG['_default'] || [];
            return menuConfig.filter(item => {
                if (item.condition) {
                    try { return new Function('entity', 'entityId', 'frameId', 'return ' + item.condition)($w.data('entity'), $w.data('entity-id'), $w.data('frame-id')); } 
                    catch(e) { return false; }
                }
                return true;
            });
        }
    };
    
    function attachMenuToGear($gear, $wrapper, entity, entityId, frameId) {
        const menuItems = GEAR_MENU_CONFIG[entity] || GEAR_MENU_CONFIG['_default'] || [];
        if (menuItems.length === 0) return;
        
        $gear.siblings('.gear-menu').remove();
        const $menu = $('<div class="gear-menu"></div>');
        
        menuItems.forEach(item => {
            if (item.condition) {
                try {
                    const met = new Function('entity', 'entityId', 'frameId', 'return ' + item.condition)(entity, entityId, frameId);
                    if (!met) return;
                } catch(e) { return; }
            }
            if (item.separator) { $menu.append('<div class="gear-menu-separator"></div>'); return; }
            
            const $btn = $('<button></button>').html((item.icon ? item.icon + ' ' : '') + item.label);
            $btn.on('click', function(e) {
                e.stopPropagation();
                try {
                    const h = new Function('wrapper', 'entity', 'entityId', 'frameId', item.callback);
                    h.call(this, $wrapper[0], entity, entityId, frameId);
                } catch(err) { if (typeof Toast !== 'undefined') Toast.show(err.message, 'error'); }
            });
            $menu.append($btn);
        });
        
        $gear.after($menu);
        $gear.off('click.gearmenu').on('click.gearmenu', function(e) {
            e.stopPropagation();
            $('.gear-menu').removeClass('active');
            $menu.toggleClass('active');
            $('.swiper-pagination, .gallery-pagination, .pagination').toggleClass('gear-pagination-shift', $menu.hasClass('active'));
        });
    }
    
    $(document).ready(function() {
        if ($('.gear-icon').length > 0) window.GearMenu.attach(document);
    });
})();
</script>
JS;
        return str_replace(['%%JSID%%', '%%MENU_CONFIG%%'], [$this->jsId, $menuConfigJson], $jsNowdoc);
    }

    public function renderIcon(array $data = []): string
    {
        $entity = $data['entity'] ?? '';
        if (!$this->shouldShowForEntity($entity)) return '';
        return '<span class="gear-icon">' . $this->config['icon'] . '</span>';
    }

    public function render(): string { return $this->renderCSS() . "\n" . $this->renderJS(); }
    private function getPositionStyles(): string {
        $map = [
            'top-right' => 'top: 8px; right: 8px;', 'top-left' => 'top: 8px; left: 8px;',
            'bottom-right' => 'bottom: 8px; right: 8px;', 'bottom-left' => 'bottom: 8px; left: 8px;'
        ];
        return $map[$this->config['position']] ?? $map['top-right'];
    }
    private function buildMenuConfig(): array {
        $config = [];
        if ($this->config['global_actions']) $config['_default'] = $this->getDefaultActions();
        foreach ($this->menuItems as $entity => $items) $config[$entity] = $items;
        foreach ($this->config['menu_actions'] as $entity => $actions) {
            $config[$entity] = array_merge($config[$entity] ?? [], $actions);
        }
        return $config;
    }
    private function getDefaultActions(): array {
        return [
            ['label' => 'Rate Frame', 'icon' => '⭐', 'callback' => 'window.openRatingMenu(frameId, $(wrapper), $(this));'],
            ['label' => 'Delete Frame', 'icon' => '🗑️', 'callback' => 'window.deleteFrame(entity, entityId, frameId);']
        ];
    }
    private function shouldShowForEntity(string $entity): bool {
        if (in_array($entity, $this->config['exclude_entities'])) return false;
        return $this->config['show_for_entities'] === null || in_array($entity, $this->config['show_for_entities']);
    }
}



