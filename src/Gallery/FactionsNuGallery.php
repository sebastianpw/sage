<?php
// src/Gallery/FactionsNuGallery.php
namespace App\Gallery;

require_once __DIR__ . '/AbstractNuGallery.php';

/**
 * FactionsNuGallery - Factions gallery using the new modular system
 * 
 * Extends AbstractNuGallery (not AbstractGallery)
 * Maintains all original functionality
 */
class FactionsNuGallery extends AbstractNuGallery
{
    protected function getFiltersFromRequest(): array
    {
        return [
            'faction' => $_GET['faction'] ?? 'all',
            'style'     => $_GET['style'] ?? 'all',
            'map_run'   => $_GET['map_run'] ?? 'all',
        ];
    }

    protected function getFilterOptions(): array
    {
        return [
            'faction' => [
                'label'  => 'Factions',
                'values' => $this->fetchDistinct('faction_name'),
                'left'   => 0
            ],
            'style' => [
                'label'  => 'Styles',
                'values' => $this->fetchDistinct('style'),
                'left'   => 110
            ],
            'map_run' => [
                'label'  => 'Map Runs',
                'values' => [],  // populated dynamically via AJAX
                'left'   => 165
            ]
        ];
    }

    protected function getWhereClause(): string
    {
        $clauses = [];
        foreach ($this->filters as $key => $val) {
            if ($val !== 'all') {
                $col = match($key) {
                    'faction' => 'faction_name',
                    'style'     => 'style',
                    'map_run'   => 'map_run_id',
                };
                $clauses[] = "$col='" . $this->mysqli->real_escape_string($val) . "'";
            }
        }
        return $clauses ? "WHERE " . implode(" AND ", $clauses) : '';
    }

    protected function getBaseQuery(): string
    {
        return "v_gallery_factions";
    }

    protected function getCaptionFields(): array
    {
        return [
            'Entity ID'    => 'entity_id',
            'Frame ID'     => 'frame_id',
            'Faction'    => 'faction_name',
            'Type'         => 'faction_type',
            'Style'        => 'style',
            'Map Run'      => 'map_run_id',
        ];
    }

    protected function getGalleryEntity(): string
    {
        return "factions";
    }

    protected function getGalleryTitle(): string
    {
        return "Faction Gallery";
    }

    protected function getToggleButtonLeft(): int
    {
        return 225;
    }

    /**
     * Fetch map runs for a specific faction
     */
    public function fetchMapRunsForFaction(string $factionName): array
    {
        $stmt = $this->mysqli->prepare("
            SELECT DISTINCT mr.id, mr.is_active
            FROM v_map_runs_factions mr
            JOIN factions c ON mr.entity_id = c.id
            WHERE c.name = ?
            ORDER BY mr.id DESC
        ");
        $stmt->bind_param('s', $factionName);
        $stmt->execute();
        $result = $stmt->get_result();

        $mapRuns = [];
        while ($row = $result->fetch_assoc()) {
            $mapRuns[] = $row;
        }
        return $mapRuns;
    }

    /**
     * Add dynamic map run selector JavaScript
     */
    protected function renderSequel(): string
    {
        return "
        <script>
        $(document).ready(function() {
            var maprunSelect = $('select[name=map_run]');
            var factionSelect = $('select[name=faction]');

            // Initially hide
            maprunSelect.hide();

            function updateMapRuns(factionName) {
                // Clear options
                maprunSelect.html('<option value=\"all\">All Map Runs</option>');

                // Only proceed if a faction is selected
                if (!factionName || factionName === 'all') {
                    maprunSelect.hide();
                    return;
                }

                $.getJSON('mapruns_factions.php', { faction: factionName }, function(mapRuns) {
                    mapRuns.forEach(function(row) {
                        var optionText = 'Map Run ' + row.id;
                        if (row.is_active) optionText += ' (active)';
                        maprunSelect.append($('<option>').val(row.id).text(optionText));
                    });

                    // Keep selected map run from URL
                    var urlParams = new URLSearchParams(window.location.search);
                    var selectedMapRun = urlParams.get('map_run');
                    if (selectedMapRun) {
                        maprunSelect.val(selectedMapRun);
                    }

                    // Show select only if faction is selected
                    maprunSelect.show();
                }).fail(function(err) { console.error(err); });
            }

            // On page load: update map runs only if a faction is selected
            updateMapRuns(factionSelect.val());

            // When faction changes
            factionSelect.on('change', function() {
                updateMapRuns($(this).val());
            });

        });
        </script>";
    }
}
