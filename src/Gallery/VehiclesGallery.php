<?php
namespace App\Gallery;

class VehiclesGallery extends AbstractGallery {

    protected function getFiltersFromRequest(): array {
        return [
            'type'  => $_GET['type'] ?? 'all',
            'status'=> $_GET['status'] ?? 'all',
            'style' => $_GET['style'] ?? 'all',
        ];
    }

    protected function getFilterOptions(): array {
        return [
            'type' => [
                'label'  => 'Types',
                'values' => $this->fetchDistinct('vehicle_type'),
                'left'   => 0
            ],
            'status' => [
                'label'  => 'Statuses',
                'values' => $this->fetchDistinct('vehicle_status'),
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
        if ($this->filters['type'] !== 'all') {
            $clauses[] = "vehicle_type='" . $this->mysqli->real_escape_string($this->filters['type']) . "'";
        }
        if ($this->filters['status'] !== 'all') {
            $clauses[] = "vehicle_status='" . $this->mysqli->real_escape_string($this->filters['status']) . "'";
        }
        if ($this->filters['style'] !== 'all') {
            $clauses[] = "style='" . $this->mysqli->real_escape_string($this->filters['style']) . "'";
        }
        return $clauses ? "WHERE " . implode(" AND ", $clauses) : '';
    }

    protected function getBaseQuery(): string {
        return "v_gallery_vehicles";
    }


    /*
    protected function renderItem(array $row): string {
        ob_start(); ?>
        <div class="img-wrapper">
            <img src="<?= htmlspecialchars($row['filename']) ?>" alt="">
            <div class="caption">
                <?= htmlspecialchars($row['prompt']) ?><br>
                <strong>Vehicle:</strong> <?= htmlspecialchars($row['vehicle_name']) ?><br>
                <strong>Type:</strong> <?= htmlspecialchars($row['vehicle_type']) ?><br>
                <strong>Status:</strong> <?= htmlspecialchars($row['vehicle_status']) ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
     */





protected function getCaptionFields(): array {
    return [
        'Vehicle'    => 'vehicle_name',
        'Type'       => 'vehicle_type',
        'Status'     => 'vehicle_status',
        'Style'      => 'style',
    ];
}

    protected function getGalleryEntity(): string {
        return "vehicles";
    }

    protected function getGalleryTitle(): string {
        return "Vehicles Gallery";
    }

    protected function getToggleButtonLeft(): int {
        return 225; // adjust position of the "Grid View" toggle button
    }
}



