<?php
namespace App\Gallery;

class ScenePartsGallery extends AbstractGallery {

    protected function getFiltersFromRequest(): array {
        return [
            'character' => $_GET['character'] ?? 'all',
            'anima'     => $_GET['anima'] ?? 'all',
            'style'     => $_GET['style'] ?? 'all',
        ];
    }

    protected function getFilterOptions(): array {
        return [
            'character' => [
                'label'  => 'Characters',
                'values' => $this->fetchDistinct('characters'),
                'left'   => 0
            ],
            'anima' => [
                'label'  => 'Animas',
                'values' => $this->fetchDistinct('animas'),
                'left'   => 75
            ],
            'style' => [
                'label'  => 'Styles',
                'values' => $this->fetchDistinct('style'),
                'left'   => 150
            ],
        ];
    }

    protected function getWhereClause(): string {
        $clauses = [];
        if ($this->filters['character'] !== 'all') {
            $clauses[] = "characters LIKE '%" . $this->mysqli->real_escape_string($this->filters['character']) . "%'";
        }
        if ($this->filters['anima'] !== 'all') {
            $clauses[] = "animas LIKE '%" . $this->mysqli->real_escape_string($this->filters['anima']) . "%'";
        }
        if ($this->filters['style'] !== 'all') {
            $clauses[] = "style LIKE '%" . $this->mysqli->real_escape_string($this->filters['style']) . "%'";
        }
        return $clauses ? "WHERE " . implode(" AND ", $clauses) : '';
    }

    protected function getBaseQuery(): string {
        return "v_gallery_scene_parts";
    }


    /*
    protected function renderItem(array $row): string {
        ob_start(); ?>
        <div class="img-wrapper">
            <img src="<?= htmlspecialchars($row['filename']) ?>" alt="">
            <div class="caption">
                <strong>Scene:</strong> <?= htmlspecialchars($row['scene_part_title']) ?><br>
                <?= htmlspecialchars($row['prompt']) ?><br>
                <strong>Characters:</strong> <?= htmlspecialchars($row['characters']) ?><br>
                <strong>Animas:</strong> <?= htmlspecialchars($row['animas']) ?><br>
                <strong>Artifacts:</strong> <?= htmlspecialchars($row['artifacts']) ?><br>
                <strong>Backgrounds:</strong> <?= htmlspecialchars($row['backgrounds']) ?><br>
                <strong>Style:</strong> <?= htmlspecialchars($row['style']) ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
     */





protected function getCaptionFields(): array {
    return [
        'Scene'       => 'scene_part_title',
        'Characters'  => 'characters',
        'Animas'      => 'animas',
        'Artifacts'   => 'artifacts',
        'Backgrounds' => 'backgrounds',
        'Style'       => 'style',
    ];
}

    protected function getGalleryEntity(): string {
        return "scene_parts";
    }

    protected function getGalleryTitle(): string {
        return "Scene Parts Gallery";
    }

    protected function getToggleButtonLeft(): int {
        return 225; // adjust UI position
    }
}


