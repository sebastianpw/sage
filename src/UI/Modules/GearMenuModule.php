<?php
// src/UI/Modules/GearMenuModule.php
namespace App\UI\Modules;

/**
 * GearMenuModule - Fully standalone, configurable gear menu system
 * Can be integrated into any view without dependencies on AbstractGallery
 */
class GearMenuModule
{
    private array $config = [];
    private array $menuItems = [];
    private bool $autoInit = true;
    private string $cssId = 'gear-menu-styles';
    private string $jsId = 'gear-menu-script';

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'entity_types' => ['characters', 'character_poses', 'animas', 'locations', 'backgrounds', 'artifacts', 'vehicles', 'scene_parts', 'controlnet_maps', 'spawns', 'generatives', 'sketches', 'prompt_matrix_blueprints', 'composites'],
            'show_for_entities' => null, // null = all, or array of specific entities
            'exclude_entities' => [],
            'icon' => '&#9881;', // gear symbol
            'icon_size' => '2em',
            'position' => 'top-right', // top-right, top-left, bottom-right, bottom-left
            'menu_actions' => [], // custom actions to add
            'global_actions' => true, // include default global actions
        ], $config);
    }

    /**
     * Add a custom menu action
     */
    public function addAction(string $entity, array $action): self
    {
        if (!isset($this->menuItems[$entity])) {
            $this->menuItems[$entity] = [];
        }
        
        $this->menuItems[$entity][] = [
            'label' => $action['label'] ?? 'Action',
            'icon' => $action['icon'] ?? '',
            'callback' => $action['callback'] ?? 'function(){}',
            'condition' => $action['condition'] ?? null, // JS condition string
        ];
        
        return $this;
    }

    /**
     * Set multiple actions for an entity
     */
    public function setActionsForEntity(string $entity, array $actions): self
    {
        $this->menuItems[$entity] = [];
        foreach ($actions as $action) {
            $this->addAction($entity, $action);
        }
        return $this;
    }

    /**
     * Generate the CSS for the gear menu
     */
    public function renderCSS(): string
    {
        $iconSize = $this->config['icon_size'];
        $position = $this->getPositionStyles();
        
        return <<<CSS
<style id="{$this->cssId}">
/* Ensure parent wrapper has position context */
[data-entity] {
    position: relative !important;
}

.gear-icon {
    position: absolute;
    {$position}
    font-size: {$iconSize};
    cursor: pointer;
    z-index: 500;
    color: rgba(255,255,255,0.9);
    text-shadow: 0 2px 4px rgba(0,0,0,0.8);
    transition: all 0.2s ease;
    user-select: none;
    pointer-events: auto;
    background: rgba(0,0,0,0.3);
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.gear-icon:hover {
    color: rgba(0,255,255,1);
    background: rgba(0,0,0,0.6);
    transform: scale(1.1) rotate(45deg);
}

.gear-menu {
    display: block !important;
    visibility: hidden;
    opacity: 0;
    position: absolute;
    top: 42px;
    right: 0;
    background: rgba(26, 26, 26, 0.98);
    border: 1px solid rgba(0, 255, 255, 0.3);
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.6),
                0 0 20px rgba(0, 255, 255, 0.15);
    z-index: 10000000;
    min-width: 200px;
    backdrop-filter: blur(10px);
    transition: opacity 0.2s ease, visibility 0.2s ease;
    pointer-events: none;
}

.gear-menu.active {
    visibility: visible;
    opacity: 1;
    pointer-events: auto;
}

.gear-menu button {
    display: block;
    width: 100%;
    padding: 12px 16px;
    background: transparent;
    border: none;
    color: rgba(255,255,255,0.9);
    text-align: left;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.15s ease;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.gear-menu button:last-child {
    border-bottom: none;
}

.gear-menu button:hover {
    background: rgba(0, 255, 255, 0.1);
    color: rgba(0, 255, 255, 1);
    padding-left: 20px;
}

.gear-menu button:active {
    background: rgba(0, 255, 255, 0.2);
}

.gear-menu-separator {
    height: 1px;
    background: rgba(255,255,255,0.1);
    margin: 4px 0;
}

/* Storyboard submenu styles */
.sb-menu {
    position: fixed;
    background: rgba(26, 26, 26, 0.98);
    border: 1px solid rgba(0, 255, 255, 0.3);
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.6),
                0 0 20px rgba(0, 255, 255, 0.15);
    z-index: 20000000 !important;
    min-width: 220px;
    max-width: 320px;
    backdrop-filter: blur(10px);
    padding: 4px 0;
}

.sb-menu-item {
    display: block;
    width: 100%;
    padding: 12px 16px;
    background: transparent;
    border: none;
    color: rgba(255,255,255,0.9);
    text-align: left;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.15s ease;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sb-menu-item:last-child {
    border-bottom: none;
}

.sb-menu-item:hover {
    background: rgba(0, 255, 255, 0.1);
    color: rgba(0, 255, 255, 1);
    padding-left: 20px;
}

.sb-menu-item:active {
    background: rgba(0, 255, 255, 0.2);
}

.sb-menu-sep {
    height: 1px;
    background: rgba(255,255,255,0.15);
    margin: 4px 8px;
}

.sb-menu-item span {
    opacity: 0.6;
    font-size: 12px;
}
</style>
CSS;
    }

    /**
     * Generate the JavaScript for the gear menu
     */
    public function renderJS(): string
    {
        $menuConfig = $this->buildMenuConfig();
        $menuConfigJson = json_encode($menuConfig, JSON_PRETTY_PRINT);
        
        return <<<JS
<script id="{$this->jsId}">
(function() {
    'use strict';
    
    // Configuration for each entity type
    const GEAR_MENU_CONFIG = {$menuConfigJson};
    
    /**
     * Attach gear menu to elements
     */
    window.GearMenu = {
        attach: function(context) {
            const \$root = context instanceof jQuery ? context : \$(context || document);
            
            \$root.find('[data-entity]').each(function() {
                const \$wrapper = \$(this);
                const entity = \$wrapper.data('entity');
                const entityId = \$wrapper.data('entity-id');
                const frameId = \$wrapper.data('frame-id');
                
                // Check if gear icon already exists
                if (\$wrapper.find('.gear-icon').length > 0) {
                    // Icon exists, just attach menu
                    const \$gear = \$wrapper.find('.gear-icon').first();
                    attachMenuToGear(\$gear, \$wrapper, entity, entityId, frameId);
                } else {
                    // Create and inject gear icon
                    const \$gear = \$('<span class="gear-icon">&#9881;</span>');
                    \$wrapper.append(\$gear);
                    attachMenuToGear(\$gear, \$wrapper, entity, entityId, frameId);
                }
            });
            
            // Close menus on outside click
            \$(document).off('click.gearmenu').on('click.gearmenu', function(e) {
                if (!\$(e.target).closest('.gear-menu, .gear-icon, .sb-menu').length) {
                    \$('.gear-menu').removeClass('active');
                    \$('.sb-menu').remove();
                }
            });
        },
        
        // Programmatically trigger menu for an element
        open: function(element) {
            \$(element).find('.gear-icon').trigger('click');
        },
        
        // Close all menus
        closeAll: function() {
            \$('.gear-menu').removeClass('active');
        },
        
        /**
         * Get the configured actions for a given element.
         * This is useful for custom UI integrations like PhotoSwipe
         * where the menu is rendered differently.
         */
        getActionsFor: function(element) {
            const \$wrapper = \$(element);
            const entity = \$wrapper.data('entity');
            const entityId = \$wrapper.data('entity-id');
            const frameId = \$wrapper.data('frame-id');

            const menuConfig = GEAR_MENU_CONFIG[entity] || GEAR_MENU_CONFIG['_default'] || [];
            
            return menuConfig.filter(item => {
                if (item.condition) {
                    try {
                        return new Function('entity', 'entityId', 'frameId', 
                            'return ' + item.condition)(entity, entityId, frameId);
                    } catch(e) {
                        console.error('GearMenu condition error:', e);
                        return false;
                    }
                }
                return true;
            });
        }
    };
    
    
    
    
    
    
    
    function attachMenuToGear(\$gear, \$wrapper, entity, entityId, frameId) {
        const menuItems = GEAR_MENU_CONFIG[entity] || GEAR_MENU_CONFIG['_default'] || [];
        
        if (menuItems.length === 0) return;
        
        // Remove existing menu to prevent duplicates
        \$gear.siblings('.gear-menu').remove();
        
        // Build menu
        const \$menu = \$('<div class="gear-menu"></div>');
        
        menuItems.forEach(item => {
            // Check condition if present
            if (item.condition) {
                try {
                    const conditionMet = new Function('entity', 'entityId', 'frameId', 
                        'return ' + item.condition)(entity, entityId, frameId);
                    if (!conditionMet) return;
                } catch(e) {
                    console.error('GearMenu condition error:', e);
                    return;
                }
            }
            
            if (item.separator) {
                \$menu.append('<div class="gear-menu-separator"></div>');
                return;
            }
            
            const \$btn = \$('<button></button>');
            \$btn.html((item.icon ? item.icon + ' ' : '') + item.label);
            
            \$btn.on('click', function(e) {
                e.stopPropagation();
                // Don't close menu here - let the action handler decide
                // \$menu.removeClass('active');
                
                try {
                    const handler = new Function('wrapper', 'entity', 'entityId', 'frameId', 
                        item.callback);
                    handler.call(this, \$wrapper[0], entity, entityId, frameId);
                } catch(err) {
                    console.error('GearMenu action error:', err);
                    if (typeof Toast !== 'undefined') {
                        Toast.show('Action failed: ' + err.message, 'error');
                    }
                }
            });
            
            \$menu.append(\$btn);
        });
        
        \$gear.after(\$menu);
        
        // Toggle menu on gear click
        \$gear.off('click.gearmenu').on('click.gearmenu', function(e) {
            e.stopPropagation();
            \$('.gear-menu').removeClass('active');
            \$menu.toggleClass('active');
        });
    }
    
    // Auto-attach on DOM ready if elements exist
    \$(document).ready(function() {
        if (\$('.gear-icon').length > 0) {
            window.GearMenu.attach(document);
        }
    });
    
})();
</script>
JS;
    }

    /**
     * Render gear icon HTML for an image wrapper
     */
    public function renderIcon(array $data = []): string
    {
        $entity = $data['entity'] ?? '';
        $entityId = $data['entity_id'] ?? 0;
        $frameId = $data['frame_id'] ?? 0;
        
        // Check if we should show for this entity
        if (!$this->shouldShowForEntity($entity)) {
            return '';
        }
        
        $icon = $this->config['icon'];
        
        // Return ONLY the icon - the JS will create the menu dynamically
        return <<<HTML
<span class="gear-icon">{$icon}</span>
HTML;
    }

    /**
     * Render complete module (CSS + JS)
     */
    public function render(): string
    {
        return $this->renderCSS() . "\n" . $this->renderJS();
    }

    /**
     * Get position styles for gear icon
     */
    private function getPositionStyles(): string
    {
        $pos = $this->config['position'];
        $map = [
            'top-right' => 'top: 8px; right: 8px;',
            'top-left' => 'top: 8px; left: 8px;',
            'bottom-right' => 'bottom: 8px; right: 8px;',
            'bottom-left' => 'bottom: 8px; left: 8px;',
        ];
        return $map[$pos] ?? $map['top-right'];
    }

    /**
     * Get menu position styles based on icon position
     */
    private function getMenuPositionStyles(): string
    {
        $pos = $this->config['position'];
        if (strpos($pos, 'right') !== false) {
            return 'right: 0; top: calc(100% + 4px);';
        }
        return 'left: 0; top: calc(100% + 4px);';
    }

    /**
     * Build menu configuration for JavaScript
     */
    private function buildMenuConfig(): array
    {
        $config = [];
        
        // Add default global actions if enabled
        if ($this->config['global_actions']) {
            $config['_default'] = $this->getDefaultActions();
        }
        
        // Add entity-specific actions
        foreach ($this->menuItems as $entity => $items) {
            $config[$entity] = $items;
        }
        
        // Add configured actions from constructor
        foreach ($this->config['menu_actions'] as $entity => $actions) {
            if (!isset($config[$entity])) {
                $config[$entity] = [];
            }
            $config[$entity] = array_merge($config[$entity], $actions);
        }
        
        return $config;
    }

    /**
     * Get default menu actions
     */
    private function getDefaultActions(): array
    {
        return [
            [
                'label' => 'Delete Frame',
                'icon' => '🗑️',
                'callback' => 'window.deleteFrame(entity, entityId, frameId);'
            ]
        ];
    }

    /**
     * Check if menu should show for entity
     */
    private function shouldShowForEntity(string $entity): bool
    {
        if (in_array($entity, $this->config['exclude_entities'])) {
            return false;
        }
        
        if ($this->config['show_for_entities'] === null) {
            return true;
        }
        
        return in_array($entity, $this->config['show_for_entities']);
    }
}
