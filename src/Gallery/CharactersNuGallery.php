<?php
// src/Gallery/CharactersNuGallery.php
namespace App\Gallery;

require_once __DIR__ . '/AbstractNuGallery.php';

/**
 * CharactersNuGallery - Characters gallery using the new modular system
 * 
 * Extends AbstractNuGallery (not AbstractGallery)
 * Maintains all original functionality
 */
class CharactersNuGallery extends AbstractNuGallery
{
    protected function getFiltersFromRequest(): array
    {
        return [
            'character' => $_GET['character'] ?? 'all',
            'role'      => $_GET['role'] ?? 'all',
            'style'     => $_GET['style'] ?? 'all',
            'map_run'   => $_GET['map_run'] ?? 'all',
        ];
    }

    protected function getFilterOptions(): array
    {
        return [
            'character' => [
                'label'  => 'Characters',
                'values' => $this->fetchDistinct('character_name'),
                'left'   => 0
            ],
            'role' => [
                'label'  => 'Roles',
                'values' => $this->fetchDistinct('character_role'),
                'left'   => 55
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
                    'character' => 'character_name',
                    'role'      => 'character_role',
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
        return "v_gallery_characters";
    }

    protected function getCaptionFields(): array
    {
        return [
            'Entity ID'    => 'entity_id',
            'Frame ID'     => 'frame_id',
            'Character'    => 'character_name',
            'Type'         => 'character_type',
            'Style'        => 'style',
            'Map Run'      => 'map_run_id',
        ];
    }

    protected function getGalleryEntity(): string
    {
        return "characters";
    }

    protected function getGalleryTitle(): string
    {
        return "Character Gallery";
    }

    protected function getToggleButtonLeft(): int
    {
        return 225;
    }

    /**
     * Fetch map runs for a specific character
     */
    public function fetchMapRunsForCharacter(string $characterName): array
    {
        $stmt = $this->mysqli->prepare("
            SELECT DISTINCT mr.id, mr.is_active
            FROM v_map_runs_characters mr
            JOIN characters c ON mr.entity_id = c.id
            WHERE c.name = ?
            ORDER BY mr.id DESC
        ");
        $stmt->bind_param('s', $characterName);
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
            var characterSelect = $('select[name=character]');

            // Initially hide
            maprunSelect.hide();

            function updateMapRuns(characterName) {
                // Clear options
                maprunSelect.html('<option value=\"all\">All Map Runs</option>');

                // Only proceed if a character is selected
                if (!characterName || characterName === 'all') {
                    maprunSelect.hide();
                    return;
                }

                $.getJSON('mapruns_characters.php', { character: characterName }, function(mapRuns) {
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

                    // Show select only if character is selected
                    maprunSelect.show();
                }).fail(function(err) { console.error(err); });
            }

            // On page load: update map runs only if a character is selected
            updateMapRuns(characterSelect.val());

            // When character changes
            characterSelect.on('change', function() {
                updateMapRuns($(this).val());
            });

            // When role or style changes, do not show map run if no character
            $('select[name=role], select[name=style]').on('change', function() {
                if (!characterSelect.val() || characterSelect.val() === 'all') {
                    maprunSelect.hide();
                }
            });
        });
        </script>";
    }
}
