<?php
// src/UI/Modules/AudioGearMenuModule.php
namespace App\UI\Modules;

/**
 * AudioGearMenuModule - Dedicated gear menu for Audio Player interfaces.
 */
class AudioGearMenuModule
{
    private array $config = [];
    private array $menuItems = [];
    private string $cssId = 'audio-gear-menu-styles';
    private string $jsId = 'audio-gear-menu-script';

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'entity_types' => [], 
            'icon' => '&#9881;', 
            'icon_size' => '0.5em',
            'position' => 'center-right',
            'menu_actions' => [], 
            'global_actions' => true,
        ], $config);
    }

    public function addAction(string $entity, array $action): self
    {
        if (!isset($this->menuItems[$entity])) {
            $this->menuItems[$entity] = [];
        }
        $this->menuItems[$entity][] = [
            'label' => $action['label'] ?? 'Action',
            'icon' => $action['icon'] ?? '',
            'callback' => $action['callback'] ?? 'function(){}',
            'condition' => $action['condition'] ?? null,
        ];
        return $this;
    }

    public function renderCSS(): string
    {
        $iconSize = $this->config['icon_size'];
        
        return <<<CSS
<style id="{$this->cssId}">
/* Audio Gear Menu Styles */
.audio-gear-wrapper {
    position: relative;
    display: inline-block;
    vertical-align: middle;
    margin-left: 10px;
}

.audio-gear-icon {
    font-size: {$iconSize};
    cursor: pointer;
    color: var(--text-muted);
    transition: all 0.2s ease;
    user-select: none;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px; 
    height: 30px;
    border-radius: 50%;
}

.audio-gear-icon:hover {
    color: var(--accent);
    background: rgba(var(--muted-border-rgb), 0.3);
    transform: rotate(45deg);
}

.audio-gear-menu {
    display: block !important;
    visibility: hidden;
    opacity: 0;
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 5px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    z-index: 1000;
    min-width: 160px;
    transition: opacity 0.15s ease, visibility 0.15s ease;
    pointer-events: none;
}

.audio-gear-menu.active {
    visibility: visible;
    opacity: 1;
    pointer-events: auto;
}

.audio-gear-menu button {
    display: block;
    width: 100%;
    padding: 10px 14px;
    background: transparent;
    border: none;
    color: var(--text);
    text-align: left;
    cursor: pointer;
    font-size: 13px;
    transition: background 0.1s;
    border-bottom: 1px solid rgba(var(--muted-border-rgb), 0.1);
}

.audio-gear-menu button:last-child {
    border-bottom: none;
}

.audio-gear-menu button:hover {
    background: rgba(var(--muted-border-rgb), 0.15);
    color: var(--accent);
}

.audio-gear-menu-separator {
    height: 1px;
    background: var(--border);
    margin: 4px 0;
}
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
    
    const AUDIO_GEAR_CONFIG = %%MENU_CONFIG%%;
    
    window.AudioGearMenu = {
        attach: function(context) {
            const $root = context instanceof jQuery ? context : $(context || document);
            $(document).off('click.audiogear').on('click.audiogear', function(e) {
                if (!$(e.target).closest('.audio-gear-menu, .audio-gear-icon').length) {
                    $('.audio-gear-menu').removeClass('active');
                }
            });
        },

        open: function(element, event) {
            if (event) event.stopPropagation();
            
            const $gear = $(element);
            const entity = $gear.data('entity') || 'audio_generic';
            const audioId = $gear.data('audio-id');
            const entityId = $gear.data('entity-id'); // Capture entity ID
            const url = $gear.data('url');
            
            // Close others
            $('.audio-gear-menu').not($gear.next('.audio-gear-menu')).removeClass('active');
            
            // Build or Toggle
            let $menu = $gear.next('.audio-gear-menu');
            
            if ($menu.length === 0) {
                $menu = buildMenu($gear, entity, audioId, url, entityId);
                $gear.after($menu);
            }
            
            // Toggle
            if ($menu.hasClass('active')) {
                $menu.removeClass('active');
            } else {
                $menu.addClass('active');
            }
        }
    };
    
    function buildMenu($gear, entity, audioId, url, entityId) {
        const items = AUDIO_GEAR_CONFIG[entity] || AUDIO_GEAR_CONFIG['_default'] || [];
        const $menu = $('<div class="audio-gear-menu"></div>');
        
        if (items.length === 0) {
            $menu.append('<div style="padding:10px;font-size:12px;color:var(--text-muted)">No actions</div>');
            return $menu;
        }

        items.forEach(item => {
            if (item.separator) {
                $menu.append('<div class="audio-gear-menu-separator"></div>');
                return;
            }
            
            const $btn = $('<button></button>');
            $btn.html((item.icon ? item.icon + ' ' : '') + item.label);
            
            $btn.on('click', function(e) {
                e.stopPropagation();
                $menu.removeClass('active');
                
                try {
                    // Pass entityId to the callback
                    const handler = new Function('url', 'audioId', 'entity', 'entityId', item.callback);
                    handler.call(this, url, audioId, entity, entityId);
                } catch(err) {
                    console.error('AudioGearMenu action error:', err);
                    if (typeof Toast !== 'undefined') Toast.show('Action error', 'error');
                }
            });
            
            $menu.append($btn);
        });
        
        return $menu;
    }
    
    $(document).ready(function() {
        window.AudioGearMenu.attach(document);
    });
})();
</script>
JS;

        return str_replace(
            ['%%JSID%%', '%%MENU_CONFIG%%'],
            [$this->jsId, $menuConfigJson],
            $jsNowdoc
        );
    }

    private function buildMenuConfig(): array
    {
        $config = [];
        if ($this->config['global_actions']) {
            $config['_default'] = $this->getDefaultActions();
        }
        foreach ($this->menuItems as $entity => $items) {
            $config[$entity] = $items;
        }
        foreach ($this->config['menu_actions'] as $entity => $actions) {
            $config[$entity] = array_merge($config[$entity] ?? [], $actions);
        }
        return $config;
    }

    private function getDefaultActions(): array
    {
        return [
            [
                'label' => 'Download',
                'icon' => '⬇️',
                'callback' => '
                    const a = document.createElement("a");
                    a.href = url;
                    a.download = url.split("/").pop();
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                '
            ],
            [
                'separator' => true
            ],
            [
                'label' => 'Edit / Cut',
                'icon' => '✂️',
                'callback' => '
                    if(window.AudioEditorModal) { 
                        window.AudioEditorModal.open({
                            url: url, 
                            entityType: entity, 
                            entityId: entityId, 
                            audioId: audioId
                        }); 
                    } else { 
                        alert("Audio Editor not loaded"); 
                    }
                '
            ],
            [
                'label' => 'Assign to Composite',
                'icon' => '🔗',
                'callback' => '
                    if(window.showImportAudioToCompositeModal) {
                        window.showImportAudioToCompositeModal({
                            source: entity,
                            source_entity_id: entityId,
                            audio_id: audioId
                        });
                    } else {
                        alert("Importer Modal not loaded. Please check modal_audio_details.php inclusion.");
                    }
                '
            ]
        ];
    }
}
