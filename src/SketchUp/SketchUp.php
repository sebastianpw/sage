<?php
namespace App\SketchUp;

use PDO;

class SketchUp {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Generates a deeply nested export array from the provided config parameters.
     */
    public function generateExportData(array $config): array {
        $sketchIds = $config['sketch_ids'] ?? [];
        $ranges = $config['sketch_ranges'] ?? [];
        
        // 1. Gather all unique sketch IDs
        $finalSketchIds = [];
        foreach ($sketchIds as $id) {
            $finalSketchIds[(int)$id] = true;
        }
        foreach ($ranges as $range) {
            $start = (int)($range['start'] ?? 0);
            $end = (int)($range['end'] ?? 0);
            if ($start > 0 && $end >= $start) {
                $stmt = $this->pdo->prepare("SELECT id FROM sketches WHERE id BETWEEN ? AND ?");
                $stmt->execute([$start, $end]);
                while ($id = $stmt->fetchColumn()) {
                    $finalSketchIds[(int)$id] = true;
                }
            }
        }
        $finalSketchIds = array_keys($finalSketchIds);
        
        $export = [
            'sketches' => [],
            'related'  => [],
            'frames'   => [],
            'kg'       => []
        ];

        // 2. Fetch Sketches and Related Tables
        if (!empty($finalSketchIds)) {
            $export['sketches'] = $this->fetchTableInChunks('sketches', 'id', $finalSketchIds);

            $includeTables = $config['include_tables'] ?? [];
            $allowedRelated = ['sketch_analysis', 'sketch_sequence_analysis', 'sketch_overlay_texts', 'sketch_ingredients'];
            foreach ($allowedRelated as $table) {
                if (in_array($table, $includeTables, true)) {
                    $export['related'][$table] = $this->fetchTableInChunks($table, 'sketch_id', $finalSketchIds);
                }
            }

            // 3. Fetch Frames
            if (!empty($config['include_frames'])) {
                $f2s = $this->fetchTableInChunks('frames_2_sketches', 'to_id', $finalSketchIds);
                if (!empty($f2s)) {
                    $export['frames']['frames_2_sketches'] = $f2s;
                }
                
                $frameIds = array_unique(array_column($f2s, 'from_id'));
                
                // Also fetch frames that refer to entity_type='sketches' directly
                $directFramesStmt = $this->pdo->prepare("SELECT id FROM frames WHERE entity_type='sketches' AND entity_id = ?");
                foreach ($finalSketchIds as $sid) {
                    $directFramesStmt->execute([$sid]);
                    while ($fid = $directFramesStmt->fetchColumn()) {
                        $frameIds[] = $fid;
                    }
                }
                
                $frameIds = array_unique($frameIds);
                if (!empty($frameIds)) {
                    $export['frames']['frames'] = $this->fetchTableInChunks('frames', 'id', $frameIds);
                }
            }
        }

        // 4. Fetch Knowledge Graph Selection
        if (!empty($config['kg_nodes'])) {
            $nodeIds = array_map('intval', $config['kg_nodes']);
            $export['kg']['kg_nodes'] = $this->fetchTableInChunks('kg_nodes', 'id', $nodeIds);
            
            if (!empty($config['kg_include_edges']) && count($nodeIds) > 0) {
                $in = implode(',', array_fill(0, count($nodeIds), '?'));
                $stmt = $this->pdo->prepare("SELECT * FROM kg_node_items WHERE item_type='kg_node' AND node_id IN ($in) AND item_id IN ($in)");
                $stmt->execute(array_merge($nodeIds, $nodeIds));
                $export['kg']['kg_node_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        return $export;
    }

    /**
     * Converts the array payload into a raw SQL dump.
     */
    public function generateSql(array $exportData): string {
        $sql = "/* SketchUp Export SQL */\n\n";
        
        if (!empty($exportData['sketches'])) {
            $sql .= $this->buildInsertStatements('sketches', $exportData['sketches']);
        }
        if (!empty($exportData['related'])) {
            foreach ($exportData['related'] as $table => $rows) {
                $sql .= $this->buildInsertStatements($table, $rows);
            }
        }
        if (!empty($exportData['frames'])) {
            if (!empty($exportData['frames']['frames'])) {
                $sql .= $this->buildInsertStatements('frames', $exportData['frames']['frames']);
            }
            if (!empty($exportData['frames']['frames_2_sketches'])) {
                $sql .= $this->buildInsertStatements('frames_2_sketches', $exportData['frames']['frames_2_sketches']);
            }
        }
        if (!empty($exportData['kg'])) {
            if (!empty($exportData['kg']['kg_nodes'])) {
                $sql .= $this->buildInsertStatements('kg_nodes', $exportData['kg']['kg_nodes']);
            }
            if (!empty($exportData['kg']['kg_node_items'])) {
                $sql .= $this->buildInsertStatements('kg_node_items', $exportData['kg']['kg_node_items']);
            }
        }
        
        return $sql;
    }

    private function fetchTableInChunks(string $table, string $column, array $ids): array {
        if (empty($ids)) return [];
        $results = [];
        $chunks = array_chunk($ids, 500);
        foreach ($chunks as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $this->pdo->prepare("SELECT * FROM `$table` WHERE `$column` IN ($in)");
            $stmt->execute($chunk);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        return $results;
    }

    private function buildInsertStatements(string $table, array $rows): string {
        if (empty($rows)) return "";
        $sql = "-- Table: $table\n";
        
        foreach ($rows as $row) {
            $keys = array_keys($row);
            $vals = array_values($row);
            
            $escapedKeys = array_map(function($k) { return "`$k`"; }, $keys);
            $escapedVals = array_map(function($v) {
                if ($v === null) return "NULL";
                return $this->pdo->quote((string)$v);
            }, $vals);
            
            $sql .= "INSERT IGNORE INTO `$table` (" . implode(', ', $escapedKeys) . ") VALUES (" . implode(', ', $escapedVals) . ");\n";
        }
        $sql .= "\n";
        return $sql;
    }
}