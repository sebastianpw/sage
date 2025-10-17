<?php
namespace App\Gallery;

require_once 'AbstractArrayGallery.php';

class InfinityFreeGallery extends AbstractArrayGallery
{
    protected array $galleryContent = [];
    protected array $flatImages = []; // all images flattened for filters/pagination


protected function renderItem(array $row): string
{
    // Default keys if missing
    $filename = htmlspecialchars($row['file'] ?? '');
    $prompt   = htmlspecialchars($row['prompt'] ?? '');

    // Build caption text
    $captionParts = [];
    if (!empty($prompt)) {
        $captionParts[] = $prompt;
    }
    foreach ($this->getCaptionFields() as $label => $field) {
        if (!empty($row[$field])) {
            $captionParts[] = '<strong>' . htmlspecialchars($label) . ':</strong> ' . htmlspecialchars($row[$field]);
        }
    }
    $captionHtml = implode('<br>', $captionParts);

    ob_start(); ?>
    <div class="img-wrapper" style="position: relative;">
        <?php if ($filename !== ''): ?>
            <a href="<?= $filename ?>" class="venobox" data-gall="gallery" title="<?= htmlspecialchars(strip_tags($captionHtml)) ?>">
                <img src="<?= $filename ?>" alt="">
            </a>
        <?php endif; ?>
        <div class="caption">
            <?= $captionHtml ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


protected function getLimit(): int
{
    return 100;
}


    public function __construct(?mysqli $mysqli = null)
    {
	parent::__construct($mysqli);


    // Load gallery content from separate file
    require __DIR__ . '/gallery_infinity_free_items.php';
    $this->galleryContent = $items;


        // Flatten all images for filtering/pagination
        $this->flatImages = [];
        foreach ($this->galleryContent as $block) {
            if (isset($block['images'])) {
                foreach ($block['images'] as $img) {
                    $this->flatImages[] = $img;
                }
            }
        }
    }

    // ----------------------
    // Abstract method implementations
    // ----------------------

    protected function loadItems(): array
    {
        return $this->galleryContent;
    }

    protected function getFilterOptions(): array
    {
        return [
            'type' => [
                'label' => 'Type',
                'values' => array_values(array_unique(array_map(fn($i) => $i['type'], $this->flatImages))),
                'left' => 0
            ],
            'style' => [
                'label' => 'Style',
                'values' => array_values(array_unique(array_map(fn($i) => $i['style'], $this->flatImages))),
                'left' => 80
            ]
        ];
    }

    protected function getGalleryEntity(): string
    {
        return 'infinity_free';
    }

    protected function getGalleryTitle(): string
    {
        return 'Infinity Free Gallery';
    }

    protected function getToggleButtonLeft(): int
    {
        return 160;
    }

    protected function getCaptionFields(): array
    {
        return ['type', 'style'];
    }

    // ----------------------
    // Override render() to handle chunks/text blocks
    // ----------------------
    public function render(): string
    {
        $filteredImages = array_filter($this->flatImages, function($item){
            foreach ($this->filters as $name => $val) {
                if ($val !== 'all' && (!isset($item[$name]) || $item[$name] !== $val)) {
                    return false;
                }
            }
            return true;
        });

        $total_rows = count($filteredImages);
        $total_pages = ceil($total_rows / $this->limit);
        $currentPageItems = array_slice($filteredImages, $this->offset, $this->limit);

        // Replace $this->items with current page items for AbstractGallery rendering
        $this->items = $currentPageItems;

        // Now render using parent
        return parent::render();
    }
}


