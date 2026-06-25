<?php
namespace App\KgNarrative;

use PDO;

/**
 * KgNarrative
 *
 * Curation + export/import service for the KG Narrative Export / Import module.
 *
 * Responsibilities:
 *  - Hop expansion (manual / 1 hop / 2 hop) over kg_node_items edges
 *  - Canonical sketch resolution (kg_nodes.name <-> sketches.name, exact match)
 *  - Latest-frame resolution per sketch via frames_2_sketches
 *  - AI-ready export bundle construction
 *  - Import + validation of AI-produced narrative sequence JSON
 *  - Persistence into narrative_sequences
 *
 * Canonical source of truth: kg_nodes. Sketches/frames are derived artifacts.
 */
class KgNarrative
{
    private PDO $pdo;

    /** Hard cap on hop expansion. The UI must never request more than this. */
    public const MAX_HOPS = 2;

    /** Soft warning thresholds (informational only, surfaced in export preview) */
    public const WARN_NODES   = 25;
    public const WARN_SKETCHES = 15;
    public const WARN_FRAMES   = 40;

    /** Allowed reading lenses for export instruction generation. */
    public const READING_MODES = ['atlas', 'tour', 'story'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Hop expansion
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Expand a focal node by N hops (1 or 2) across kg_node_items edges
     * (item_type = 'kg_node'), following both outgoing and incoming directions.
     *
     * @return int[] node IDs including the focal node
     */
    public function expandHops(int $focalNodeId, int $hops): array
    {
        $hops = max(0, min(self::MAX_HOPS, $hops));
        $visited = [$focalNodeId => true];
        $frontier = [$focalNodeId];

        for ($h = 0; $h < $hops; $h++) {
            if (empty($frontier)) {
                break;
            }
            $ph = implode(',', array_fill(0, count($frontier), '?'));

            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT item_id FROM kg_node_items
                 WHERE item_type = 'kg_node' AND item_id IS NOT NULL AND node_id IN ($ph)"
            );
            $stmt->execute($frontier);
            $out = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT node_id FROM kg_node_items
                 WHERE item_type = 'kg_node' AND item_id IN ($ph)"
            );
            $stmt->execute($frontier);
            $in = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $newFrontier = [];
            foreach (array_merge($out, $in) as $nid) {
                $nid = (int)$nid;
                if ($nid && !isset($visited[$nid])) {
                    $visited[$nid] = true;
                    $newFrontier[] = $nid;
                }
            }
            $frontier = $newFrontier;
        }

        return array_keys($visited);
    }

    /**
     * Fetch the focal node plus its immediate neighbors + relationship edges,
     * for rendering the mini graph modal (always 1 hop visually, regardless
     * of the export hop mode — the modal is a refinement tool).
     */
    public function fetchMiniGraph(int $nodeId, int $hops = 1): array
    {
        $ids = $this->expandHops($nodeId, $hops);

        $nodes = [];
        $edges = [];

        if (!empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT id, name, node_type FROM kg_nodes WHERE id IN ($ph) AND status = 'active'"
            );
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
                $nodes[] = ['id' => (int)$n['id'], 'name' => $n['name'], 'node_type' => $n['node_type']];
            }

            $stmt = $this->pdo->prepare(
                "SELECT id, node_id AS source, item_id AS target, relationship, item_label
                 FROM kg_node_items
                 WHERE item_type = 'kg_node' AND item_id IS NOT NULL AND node_id IN ($ph) AND item_id IN ($ph)"
            );
            $stmt->execute(array_merge($ids, $ids));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
                $edges[] = [
                    'id'           => (int)$e['id'],
                    'source'       => (int)$e['source'],
                    'target'       => (int)$e['target'],
                    'relationship' => $e['relationship'] ?? '',
                    'item_label'   => $e['item_label'] ?? '',
                ];
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Canonical sketch / frame resolution
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Resolve canonical sketches for a set of KG node IDs.
     *
     * Canonical rule: kg_nodes.name matches sketches.name exactly.
     * kg_nodes is the source of truth; a sketch is only "canonical" for a
     * node if its name matches that node's name 1:1. This avoids pulling in
     * unrelated sketches that merely share a substring.
     *
     * @return array<int, array{kg_node_id:int, sketch_id:int, sketch_name:string}>
     */
    public function resolveCanonicalSketches(array $nodeIds): array
    {
        if (empty($nodeIds)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($nodeIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT n.id AS kg_node_id, s.id AS sketch_id, s.name AS sketch_name
             FROM kg_nodes n
             JOIN sketches s ON s.name = n.name
             WHERE n.id IN ($ph)"
        );
        $stmt->execute($nodeIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resolve the latest frame ID for each given sketch ID.
     *
     * Preferred source of truth: frames_2_sketches (from_id = frames.id, to_id = sketches.id).
     * Legacy fallback: frames.entity_type = 'sketches' AND frames.entity_id = sketches.id.
     * "Latest" = highest frames.id (auto-increment is chronological in this schema).
     *
     * @return array<int,int> map of sketch_id => latest frame_id
     */
    public function resolveLatestFrames(array $sketchIds): array
    {
        if (empty($sketchIds)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($sketchIds), '?'));

        $latest = [];

        // Preferred: frames_2_sketches join
        $stmt = $this->pdo->prepare(
            "SELECT f2s.to_id AS sketch_id, MAX(f2s.from_id) AS frame_id
             FROM frames_2_sketches f2s
             JOIN frames f ON f.id = f2s.from_id
             WHERE f2s.to_id IN ($ph)
             GROUP BY f2s.to_id"
        );
        $stmt->execute($sketchIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $latest[(int)$row['sketch_id']] = (int)$row['frame_id'];
        }

        // Legacy fallback for any sketch not yet resolved
        $missing = array_values(array_diff($sketchIds, array_keys($latest)));
        if (!empty($missing)) {
            $ph2 = implode(',', array_fill(0, count($missing), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT entity_id AS sketch_id, MAX(id) AS frame_id
                 FROM frames
                 WHERE entity_type = 'sketches' AND entity_id IN ($ph2)
                 GROUP BY entity_id"
            );
            $stmt->execute($missing);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $latest[(int)$row['sketch_id']] = (int)$row['frame_id'];
            }
        }

        return $latest;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Export bundle construction
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Build the full AI-ready export bundle.
     *
     * @param array $config {
     *   focal_kg_node_id: int,
     *   hop_mode: 'manual'|'1hop'|'2hop',
     *   reading_mode: 'atlas'|'tour'|'story',
     *   manual_node_ids: int[],
     *   include_edges: bool
     * }
     */
    public function generateExportData(array $config): array
    {
        $focalId  = (int)($config['focal_kg_node_id'] ?? 0);
        $hopMode  = $config['hop_mode'] ?? 'manual';
        $readingMode = $this->normalizeReadingMode($config['reading_mode'] ?? 'atlas');
        $manualIds = array_values(array_unique(array_map('intval', $config['manual_node_ids'] ?? [])));
        $includeEdges = !empty($config['include_edges']);

        // Resolve final selected node ID set based on mode
        $selectedIds = [];
        if ($hopMode === '1hop' && $focalId > 0) {
            $selectedIds = $this->expandHops($focalId, 1);
        } elseif ($hopMode === '2hop' && $focalId > 0) {
            $selectedIds = $this->expandHops($focalId, 2);
        } else {
            // manual mode: focal node (if set) + manually chosen nodes
            $selectedIds = $manualIds;
            if ($focalId > 0 && !in_array($focalId, $selectedIds, true)) {
                array_unshift($selectedIds, $focalId);
            }
        }
        $selectedIds = array_values(array_unique(array_map('intval', $selectedIds)));

        // 1. KG nodes
        $kgNodes = [];
        if (!empty($selectedIds)) {
            $ph = implode(',', array_fill(0, count($selectedIds), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT id, category_id, name, node_type, description, content, keywords, status, sort_order
                 FROM kg_nodes WHERE id IN ($ph)"
            );
            $stmt->execute($selectedIds);
            $kgNodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 2. Edges among selected nodes only
        $kgNodeItems = [];
        if ($includeEdges && count($selectedIds) > 1) {
            $ph = implode(',', array_fill(0, count($selectedIds), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT id, node_id, item_type, item_id, item_label, relationship, note, sort_order
                 FROM kg_node_items
                 WHERE item_type = 'kg_node' AND node_id IN ($ph) AND item_id IN ($ph)"
            );
            $stmt->execute(array_merge($selectedIds, $selectedIds));
            $kgNodeItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 3. Canonical sketches
        $canonical = $this->resolveCanonicalSketches($selectedIds);
        $sketchIds = array_values(array_unique(array_map(fn($r) => (int)$r['sketch_id'], $canonical)));

        $sketches = [];
        if (!empty($sketchIds)) {
            $ph = implode(',', array_fill(0, count($sketchIds), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT id, name, description, mood FROM sketches WHERE id IN ($ph)"
            );
            $stmt->execute($sketchIds);
            $sketches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 4. Latest frame per sketch
        $latestFrameMap = $this->resolveLatestFrames($sketchIds);
        $frameIds = array_values(array_unique(array_values($latestFrameMap)));

        $frames = [];
        if (!empty($frameIds)) {
            $ph = implode(',', array_fill(0, count($frameIds), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT id, name, filename, entity_type, entity_id, created_at FROM frames WHERE id IN ($ph)"
            );
            $stmt->execute($frameIds);
            $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // 5. Mappings
        $kgToSketch = [];
        foreach ($canonical as $row) {
            $kgToSketch[] = [
                'kg_node_id' => (int)$row['kg_node_id'],
                'sketch_id'  => (int)$row['sketch_id'],
            ];
        }
        $sketchToLatestFrame = [];
        foreach ($latestFrameMap as $sid => $fid) {
            $sketchToLatestFrame[] = ['sketch_id' => $sid, 'frame_id' => $fid];
        }

        // Nodes with no resolvable sketch (for warnings in preview)
        $nodesWithSketch = array_unique(array_map(fn($r) => (int)$r['kg_node_id'], $canonical));
        $nodesWithoutSketch = array_values(array_diff($selectedIds, $nodesWithSketch));

        // Sketches with no resolvable frame
        $sketchesWithoutFrame = array_values(array_diff($sketchIds, array_keys($latestFrameMap)));

        return [
            'export_meta' => [
                'export_type'      => 'kg_narrative_bundle',
                'generated_at'     => date('c'),
                'source_module'    => 'KgNarrativeExporter',
                'version'          => '1.0',
                'hop_mode'         => $hopMode,
                'reading_mode'     => $readingMode,
                'focal_kg_node_id' => $focalId ?: null,
            ],
            'selection' => [
                'manual_node_ids'   => $manualIds,
                'selected_node_ids' => $selectedIds,
            ],
            'kg' => [
                'kg_nodes'      => $kgNodes,
                'kg_node_items' => $kgNodeItems,
            ],
            'sketches' => $sketches,
            'frames'   => $frames,
            'mappings' => [
                'kg_to_sketch'            => $kgToSketch,
                'sketch_to_latest_frame'  => $sketchToLatestFrame,
            ],
            'warnings' => [
                'nodes_without_sketch'   => $nodesWithoutSketch,
                'sketches_without_frame' => $sketchesWithoutFrame,
                'over_node_threshold'    => count($selectedIds) > self::WARN_NODES,
                'over_sketch_threshold'   => count($sketchIds) > self::WARN_SKETCHES,
                'over_frame_threshold'    => count($frameIds) > self::WARN_FRAMES,
            ],
            'ai_instructions' => $this->buildAiInstructions($readingMode),
        ];
    }

    private function normalizeReadingMode(?string $readingMode): string
    {
        $mode = strtolower(trim((string)$readingMode));
        return in_array($mode, self::READING_MODES, true) ? $mode : 'atlas';
    }

    private function buildOutputFormat(string $readingMode): array
    {
        $rules = [
            'items must be an array',
            'sequence_name must be a string',
            'sequence_description must be a string or null',
            'use only IDs present in the export bundle',
            'do not invent nodes, sketches, or frames',
            'preserve the selected order unless a clearer reading order is needed',
            'include only valid JSON and no surrounding commentary',
        ];

        if ($readingMode === 'tour' || $readingMode === 'atlas') {
            $rules[] = 'role should be one of orientation, region, systems, culture, '
                . 'history, factions, characters, conflict, consequence, or a close equivalent';
        }

        return [
            'must_return' => 'valid_json_only',
            'no_markdown' => true,
            'no_explanation_text' => true,
            'required_keys' => [
                'sequence_name',
                'sequence_description',
                'items',
            ],
            'item_keys' => [
                'kg_node_id',
                'sketch_id',
                'frame_id',
                'role',
                'reason',
            ],
            'rules' => $rules,
        ];
    }

    private function buildAiInstructions(string $readingMode): array
    {
        $readingMode = $this->normalizeReadingMode($readingMode);
        $outputFormat = $this->buildOutputFormat($readingMode);

        if ($readingMode === 'story') {
            return [
                'goal' => 'Create a compact reading sequence with strong narrative flow.',
                'preferred_ordering' => [
                    'thesis', 'anchor', 'surface', 'depth', 'conflict', 'consequence',
                ],
                'constraints' => [
                    'Do not exceed 120 sequence items unless explicitly allowed.',
                    'Prefer canonical KG-backed sketches only.',
                    'Use latest frame per sketch.',
                    'Use only provided KG nodes. Do not invent new nodes.',
                ],
                'output_format' => $outputFormat,
                'mode' => 'story',
            ];
        }

        if ($readingMode === 'tour') {
            return [
                'goal' => 'Create a guided informational tour through the fictional world that helps the reader move through it step by step.',
                'preferred_ordering' => [
                    'orientation',
                    'region',
                    'notable_places',
                    'systems',
                    'culture',
                    'history',
                    'factions',
                    'characters',
                    'conflicts',
                    'consequences',
                ],
                'constraints' => [
                    'Prioritize clarity, context, and comprehension over dramatic narrative flow.',
                    'Use only provided KG nodes. Do not invent new nodes.',
                    'Prefer canonical KG-backed sketches only.',
                    'Use latest frame per sketch.',
                    'Group related nodes thematically when that improves understanding.',
                    'Do not compress distinct worldbuilding topics into a single sequence item if that harms learning.',
                    'Allow longer sequences when needed for completeness.',
                ],
                'output_format' => $outputFormat,
                'mode' => 'tour',
            ];
        }

        return [
            'goal' => 'Create an atlas-style reading sequence that orients the reader to the fictional world with broad, structured understanding first.',
            'preferred_ordering' => [
                'orientation',
                'region',
                'notable_places',
                'systems',
                'culture',
                'history',
                'factions',
                'characters',
                'conflicts',
                'consequences',
            ],
            'constraints' => [
                'Prioritize reference clarity and world comprehension over dramatic narrative flow.',
                'Lead with high-level geography, institutions, and systems before deeper details.',
                'Use only provided KG nodes. Do not invent new nodes.',
                'Prefer canonical KG-backed sketches only.',
                'Use latest frame per sketch.',
                'Group related nodes thematically when that improves understanding.',
                'Allow longer sequences when needed for completeness.',
            ],
            'output_format' => $outputFormat,
            'mode' => 'atlas',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Import
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Validate an AI-produced narrative sequence JSON structure against the DB.
     *
     * @return array{ok:bool, errors:string[], warnings:string[], items:array}
     */
    public function validateImport(array $payload): array
    {
        $errors = [];
        $warnings = [];

        if (empty($payload['items']) || !is_array($payload['items'])) {
            $errors[] = 'Missing or empty "items" array.';
            return ['ok' => false, 'errors' => $errors, 'warnings' => $warnings, 'items' => []];
        }

        $items = $payload['items'];
        if (count($items) > 40) {
            $warnings[] = 'Sequence has more than 40 items — unusually large for a reading sequence.';
        }

        // Collect all referenced IDs for batch lookups
        $kgIds = [];
        $sketchIds = [];
        $frameIds = [];
        foreach ($items as $it) {
            if (!empty($it['kg_node_id'])) $kgIds[] = (int)$it['kg_node_id'];
            if (!empty($it['sketch_id']))  $sketchIds[] = (int)$it['sketch_id'];
            if (!empty($it['frame_id']))   $frameIds[] = (int)$it['frame_id'];
        }
        $kgIds = array_unique($kgIds);
        $sketchIds = array_unique($sketchIds);
        $frameIds = array_unique($frameIds);

        $existingKg = $kgIds ? $this->fetchExistingIds('kg_nodes', $kgIds) : [];
        $existingSketches = $sketchIds ? $this->fetchExistingIds('sketches', $sketchIds) : [];
        $existingFrames = $frameIds ? $this->fetchExistingIds('frames', $frameIds) : [];

        // Frame -> sketch linkage (for "frame belongs to sketch" check)
        $frameSketchMap = [];
        if (!empty($frameIds)) {
            $ph = implode(',', array_fill(0, count($frameIds), '?'));
            $stmt = $this->pdo->prepare("SELECT from_id, to_id FROM frames_2_sketches WHERE from_id IN ($ph)");
            $stmt->execute($frameIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $frameSketchMap[(int)$row['from_id']][] = (int)$row['to_id'];
            }
        }

        $cleanItems = [];
        foreach ($items as $i => $it) {
            $idx = $i + 1;
            $kgNodeId = isset($it['kg_node_id']) ? (int)$it['kg_node_id'] : null;
            $sketchId = isset($it['sketch_id']) ? (int)$it['sketch_id'] : null;
            $frameId  = isset($it['frame_id']) ? (int)$it['frame_id'] : null;
            $role     = isset($it['role']) ? (string)$it['role'] : null;
            $reason   = isset($it['reason']) ? (string)$it['reason'] : null;

            if ($kgNodeId && !in_array($kgNodeId, $existingKg, true)) {
                $errors[] = "Item $idx: kg_node_id $kgNodeId does not exist.";
            }
            if ($sketchId && !in_array($sketchId, $existingSketches, true)) {
                $errors[] = "Item $idx: sketch_id $sketchId does not exist.";
            }
            if ($frameId && !in_array($frameId, $existingFrames, true)) {
                $errors[] = "Item $idx: frame_id $frameId does not exist.";
            }
            if ($frameId && $sketchId && isset($frameSketchMap[$frameId])
                && !in_array($sketchId, $frameSketchMap[$frameId], true)) {
                $warnings[] = "Item $idx: frame_id $frameId is not linked to sketch_id $sketchId via frames_2_sketches.";
            }
            if ($kgNodeId && $sketchId) {
                $canon = $this->resolveCanonicalSketches([$kgNodeId]);
                $canonSketchIds = array_map(fn($r) => (int)$r['sketch_id'], $canon);
                if (!in_array($sketchId, $canonSketchIds, true)) {
                    $warnings[] = "Item $idx: sketch_id $sketchId is not the canonical sketch for kg_node_id $kgNodeId.";
                }
            }

            $cleanItems[] = [
                'kg_node_id' => $kgNodeId,
                'sketch_id'  => $sketchId,
                'frame_id'   => $frameId,
                'role'       => $role,
                'reason'     => $reason,
            ];
        }

        return [
            'ok'       => empty($errors),
            'errors'   => $errors,
            'warnings' => $warnings,
            'items'    => $cleanItems,
        ];
    }

    /**
     * Save a validated sequence into narrative_sequences.
     */
    public function saveSequence(string $name, ?string $description, array $items, ?int $linkedDocId = null): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO narrative_sequences (name, description, sequence_data, linked_doc_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([
            $name,
            $description,
            json_encode($items, JSON_UNESCAPED_UNICODE),
            $linkedDocId,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    private function fetchExistingIds(string $table, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM `$table` WHERE id IN ($ph)");
        $stmt->execute($ids);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}