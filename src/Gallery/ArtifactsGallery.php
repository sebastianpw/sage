<?php
namespace App\Gallery;

class ArtifactsGallery extends AbstractGallery {

    protected function getGalleryEntity(): string {
        return "artifacts";
    }

    protected function getGalleryTitle(): string {
        return "Artifacts Gallery";
    }

    protected function getToggleButtonLeft(): int {
        // Place after the last filter (type, status, style)
        return 225;
    }

    protected function getFiltersFromRequest(): array {
        return [
            'type'   => $_GET['type']   ?? 'all',
            'status' => $_GET['status'] ?? 'all',
            'style'  => $_GET['style']  ?? 'all',
        ];
    }

    protected function getFilterOptions(): array {
        return [
            'type' => [
                'label'  => 'Types',
                'values' => $this->fetchDistinct('artifact_type'),
                'left'   => 0
            ],
            'status' => [
                'label'  => 'Statuses',
                'values' => $this->fetchDistinct('artifact_status'),
                'left'   => 75
            ],
            'style' => [
                'label'  => 'Styles',
                'values' => $this->fetchDistinct('style'),
                'left'   => 150
            ]
        ];
    }

    protected function getWhereClause(): string {
        $clauses = [];
        if ($this->filters['type'] !== 'all') {
            $clauses[] = "artifact_type='" . $this->mysqli->real_escape_string($this->filters['type']) . "'";
        }
        if ($this->filters['status'] !== 'all') {
            $clauses[] = "artifact_status='" . $this->mysqli->real_escape_string($this->filters['status']) . "'";
        }
        if ($this->filters['style'] !== 'all') {
            $clauses[] = "style='" . $this->mysqli->real_escape_string($this->filters['style']) . "'";
        }
        return $clauses ? "WHERE " . implode(" AND ", $clauses) : "";
    }


    protected function getCaptionFields(): array {
    return [
        'Artifact' => 'artifact_name',
        'Type'     => 'artifact_type',
        'Status'   => 'artifact_status',
        'Style'    => 'style',
    ];
    }


    protected function getBaseQuery(): string {
        return "v_gallery_artifacts";
    }



    /*
    protected function renderItem(array $row): string {
        ob_start();
        ?>
        <div class="img-wrapper">
            <img src="<?= htmlspecialchars($row['filename']) ?>" alt="">
            <div class="caption">
                <?= htmlspecialchars($row['prompt']) ?><br>
                <strong>Type:</strong> <?= htmlspecialchars($row['artifact_type']) ?><br>
                <strong>Status:</strong> <?= htmlspecialchars($row['artifact_status']) ?><br>
                <strong>Style:</strong> <?= htmlspecialchars($row['style']) ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

     */


}


