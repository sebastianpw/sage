<?php
// SpawnGalleryManager.php
// Keeps living in document root but resolves namespaced galleries when present.

class SpawnGalleryManager
{
    private \mysqli $mysqli;
    private array $spawnTypes = [];
    private ?string $activeTypeCode = null;

    public function __construct(\mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->loadSpawnTypes();
    }

    private function loadSpawnTypes(): void
    {
        $sql = "SELECT * FROM spawn_types WHERE active = 1 ORDER BY sort_order, label";
        $result = $this->mysqli->query($sql);

        while ($row = $result->fetch_assoc()) {
            $this->spawnTypes[$row['code']] = $row;
        }

        if (!empty($this->spawnTypes)) {
            $this->activeTypeCode = array_key_first($this->spawnTypes);
        }
    }

    public function setActiveType(string $typeCode): bool
    {
        if (isset($this->spawnTypes[$typeCode])) {
            $this->activeTypeCode = $typeCode;
            return true;
        }
        return false;
    }

    public function getSpawnTypes(): array
    {
        return $this->spawnTypes;
    }

    public function getActiveType(): ?array
    {
        return $this->activeTypeCode ? $this->spawnTypes[$this->activeTypeCode] : null;
    }

    public function renderTypeTabs(): string
    {
        if (count($this->spawnTypes) <= 1) {
            return '';
        }

        $html = '<div class="spawn-type-tabs" style="margin: 20px 0; border-bottom: 2px solid #ddd;">';

        foreach ($this->spawnTypes as $code => $type) {
            $active = ($code === $this->activeTypeCode) ? 'active' : '';
            $activeStyle = ($code === $this->activeTypeCode)
                ? 'background: #007bff; color: white; border-bottom: 2px solid #007bff;'
                : 'background: #f8f9fa; color: #333;';

            $html .= sprintf(
                '<a href="?spawn_type=%s" class="spawn-tab %s" style="display: inline-block; padding: 10px 20px; margin-right: 5px; text-decoration: none; border: 1px solid #ddd; border-bottom: none; border-radius: 5px 5px 0 0; %s">%s</a>',
                htmlspecialchars($code),
                $active,
                $activeStyle,
                htmlspecialchars($type['label'])
            );
        }

        $html .= '</div>';

        return $html;
    }

    public function renderGallery(): string
    {
        $activeType = $this->getActiveType();
        if (!$activeType) {
            return '<p>No spawn types configured.</p>';
        }

        // The gallery_view field might contain a view name, but we map type code to a gallery class
        $galleryClassBase = $this->getGalleryClass($activeType['code']);

        // Candidates: namespaced first, then legacy
        $candidates = [
            'App\\Gallery\\' . $galleryClassBase,
            $galleryClassBase
        ];

        foreach ($candidates as $candidate) {
            if (!class_exists($candidate)) {
                continue;
            }

            // Try instantiation patterns like in SpawnUpload
            $instance = null;

            try {
                $instance = new $candidate($this->mysqli, $activeType);
            } catch (\ArgumentCountError|\TypeError|\Throwable $e) {}

            if ($instance === null) {
                try {
                    $instance = new $candidate($activeType);
                } catch (\ArgumentCountError|\TypeError|\Throwable $e) {}
            }

            if ($instance === null) {
                try {
                    $instance = new $candidate($this->mysqli);
                } catch (\ArgumentCountError|\TypeError|\Throwable $e) {}
            }

            if ($instance === null) {
                try {
                    $instance = new $candidate();
                } catch (\ArgumentCountError|\TypeError|\Throwable $e) {}
            }

            if ($instance !== null) {
                try {
                    return $instance->render();
                } catch (\Throwable $e) {
                    return sprintf(
                        '<p style="color: #b42318;">Error rendering gallery: %s</p>',
                        htmlspecialchars($e->getMessage())
                    );
                }
            }
        }

        return sprintf(
            '<p>Gallery class "%s" not found for spawn type "%s".</p>',
            htmlspecialchars($galleryClassBase),
            htmlspecialchars($activeType['label'])
        );
    }

    protected function getGalleryClass(string $typeCode): string
    {
        $classMap = [
            'default'   => 'SpawnsGallery',
            'reference' => 'SpawnsGalleryReference',
            'texture'   => 'SpawnsGalleryTexture',
        ];

        return $classMap[$typeCode] ?? 'SpawnsGallery';
    }

    public function isUploadEnabled(): bool
    {
        $activeType = $this->getActiveType();
        return $activeType && $activeType['upload_enabled'];
    }

    public function isBatchImportEnabled(): bool
    {
        $activeType = $this->getActiveType();
        return $activeType && $activeType['batch_import_enabled'];
    }

    public function getSpawnTypeId(string $typeCode): ?int
    {
        return $this->spawnTypes[$typeCode]['id'] ?? null;
    }

    public function renderUploadForm(): string
    {
        if (!$this->isUploadEnabled()) {
            return '<p>Upload is not enabled for this spawn type.</p>';
        }

        // Keep using the legacy SpawnUpload in document root for now
        require_once __DIR__ . '/SpawnUpload.php';
        $uploader = new SpawnUpload($this->mysqli, $this->getActiveType());
        return $uploader->render();
    }

    public function getSpawnTypeOptions(): array
    {
        $options = [];
        foreach ($this->spawnTypes as $code => $type) {
            if ($type['upload_enabled']) {
                $options[$code] = $type['label'];
            }
        }
        return $options;
    }
}
