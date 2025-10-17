<?php
namespace App\Gallery;

abstract class AbstractArrayGallery extends AbstractGallery {

    protected array $items = [];

    public function __construct(?mysqli $mysqli = null) {
        // Explicit nullable mysqli
        parent::__construct($mysqli ?? new mysqli());
        $this->items = $this->loadItems();
    }

    // Load your items array here; child class can override
    abstract protected function loadItems(): array;

    protected function getFiltersFromRequest(): array {
        $filters = [];
        foreach ($this->filterOptions as $name => $opt) {
            $filters[$name] = $_GET[$name] ?? 'all';
        }
        return $filters;
    }

    protected function getFilterOptions(): array {
        // Example: override in child class
        return [];
    }

    protected function getWhereClause(): string {
        // Array-based galleries do filtering manually, not via SQL
        return '';
    }

    protected function getBaseQuery(): string {
        // Array galleries do not use SQL
        return '';
    }

    public function render(): string {
        // Filter items based on $this->filters
        $filtered = array_filter($this->items, function($item) {
            foreach ($this->filters as $key => $val) {
                if ($val === 'all') continue;
                if (!isset($item[$key]) || $item[$key] != $val) return false;
            }
            return true;
        });

        // Pagination
        $total_rows = count($filtered);
        $total_pages = ceil($total_rows / $this->limit);
        $paged_items = array_slice($filtered, $this->offset, $this->limit);

        ob_start();
        ?>
        <div class="album-container">
            <div style="display: flex; align-items: center; margin-bottom: 20px; gap: 10px;">
                <a href="dashboard.php" title="Show Gallery" style="text-decoration: none; font-size: 24px; display: inline-block;">
                    &#x1F5BC;
                </a>
                <h2 style="margin: 0;"><?= $this->getGalleryTitle() ?></h2>
            </div>

            <form style="position: relative; height: 40px;" id="galleryFilterForm" class="gallery-header" method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <input type="hidden" name="grid" value="<?= $this->gridOn ? '1' : '0' ?>">
                <?php $this->renderFilters(); ?>
                <button style="position: absolute; top: 0; left: <?= $this->getToggleButtonLeft() ?>px;" id="toggleView" type="button">⠿ Grid View</button>
            </form>

            <div class="<?= $this->albumClass ?>">
                <?php foreach ($paged_items as $item): ?>
                    <?= $this->renderItem($item) ?>
                <?php endforeach; ?>
            </div>

            <div class="gallery-footer">
                <?php
                for ($p = 1; $p <= $total_pages; $p++) {
                    $active = ($p == $this->page) ? 'active' : '';
                    $url = $this->getPageUrl($p);
                    echo "<button class=\"$active\" onclick=\"window.location='$url'\">$p</button>";
                }
                ?>
            </div>

            <?= $this->renderJsCss() ?>
        </div>
        <?php
        return ob_get_clean();
    }
}



