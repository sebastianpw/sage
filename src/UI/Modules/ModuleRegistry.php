<?php
// src/UI/Modules/ModuleRegistry.php
namespace App\UI\Modules;

/**
 * ModuleRegistry - Central registry for UI modules
 * Manages module loading, configuration, and rendering
 */
class ModuleRegistry
{
    private static ?self $instance = null;
    private array $modules = [];
    private array $config = [];

    private function __construct()
    {
        // Register core modules
        $this->registerCoreModules();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register core modules
     */
    private function registerCoreModules(): void
    {
        $this->register('gear_menu', function(array $config = []) {
            return new GearMenuModule($config);
        });

        $this->register('image_editor', function(array $config = []) {
            return new ImageEditorModule($config);
        });
    }

    /**
     * Register a module
     */
    public function register(string $name, callable $factory): self
    {
        $this->modules[$name] = $factory;
        return $this;
    }

    /**
     * Create a module instance
     */
    public function create(string $name, array $config = []): ?object
    {
        if (!isset($this->modules[$name])) {
            return null;
        }

        return ($this->modules[$name])($config);
    }

    /**
     * Get or create a module (singleton per name)
     */
    public function get(string $name, array $config = []): ?object
    {
        $key = $name . '_' . md5(json_encode($config));
        
        if (!isset($this->config[$key])) {
            $this->config[$key] = $this->create($name, $config);
        }

        return $this->config[$key];
    }

    /**
     * Check if a module is registered
     */
    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    /**
     * Get all registered module names
     */
    public function getRegistered(): array
    {
        return array_keys($this->modules);
    }
}
