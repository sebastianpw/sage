<?php
namespace App\Continuity;

use PDO;

/**
 * ContinuityPrep
 *
 * Minimal, read-only helper for episode/sketch/character continuity
 * bookkeeping. Requires no new database tables — it only ever reads from
 * `sketches`, `characters`, `frames`, and `narrative_sequences`, plus
 * information_schema for AUTO_INCREMENT bookkeeping.
 */
class ContinuityPrep
{
    private PDO $pdo;

    private const STATUS_TABLES = ['sketches', 'characters', 'frames', 'narrative_sequences'];
    private const RANGE_TABLES = ['sketches', 'characters'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getTableStatus(string $table): array
    {
        if (!in_array($table, self::STATUS_TABLES, true)) {
            throw new \InvalidArgumentException("Table not allow-listed for status: $table");
        }

        $stmt = $this->pdo->prepare(
            "SELECT AUTO_INCREMENT, TABLE_ROWS
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$table]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'table'          => $table,
            'auto_increment' => isset($row['AUTO_INCREMENT']) ? (int)$row['AUTO_INCREMENT'] : null,
            'rows'           => isset($row['TABLE_ROWS']) ? (int)$row['TABLE_ROWS'] : null,
        ];
    }

    public function getAllTableStatuses(): array
    {
        $out = [];
        foreach (self::STATUS_TABLES as $table) {
            $out[] = $this->getTableStatus($table);
        }
        return $out;
    }

    public function analyzeRange(string $table, int $fromId, int $toId): array
    {
        if (!in_array($table, self::RANGE_TABLES, true)) {
            throw new \InvalidArgumentException("Table not allow-listed for range analysis: $table");
        }
        if ($fromId > $toId) {
            [$fromId, $toId] = [$toId, $fromId]; // tolerate reversed input
        }

        $stmt = $this->pdo->prepare("SELECT id FROM `$table` WHERE id BETWEEN ? AND ?");
        $stmt->execute([$fromId, $toId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $sourceLabel = ($fromId === $toId) ? "ID $fromId" : "IDs $fromId–$toId";

        return array_merge(
            $this->analyzeIdSet($table, $ids, $sourceLabel),
            ['sub_type' => 'range']
        );
    }

    public function analyzeMapRun(int $mapRunId): array
    {
        $stmt = $this->pdo->prepare("SELECT DISTINCT entity_id FROM frames WHERE map_run_id = ? AND entity_type = 'sketches' AND entity_id IS NOT NULL");
        $stmt->execute([$mapRunId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $this->pdo->prepare("SELECT note FROM map_runs WHERE id = ?");
        $stmt->execute([$mapRunId]);
        $note = $stmt->fetchColumn() ?: 'No Note';

        return array_merge(
            $this->analyzeIdSet('sketches', $ids, "Map Run #$mapRunId ($note)"),
            ['sub_type' => 'map_run']
        );
    }

    public function analyzeSequence(int $seqId): array
    {
        $stmt = $this->pdo->prepare("SELECT sequence_data, name FROM narrative_sequences WHERE id = ?");
        $stmt->execute([$seqId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return array_merge($this->analyzeIdSet('sketches', [], "Sequence #$seqId"), ['sub_type' => 'sequence']);

        $data = json_decode($row['sequence_data'] ?? '[]', true) ?: [];
        $ids = [];
        foreach ($data as $item) {
            $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
            if ($sid > 0) $ids[] = $sid;
        }

        $label = "Sequence #$seqId (" . ($row['name'] ?: 'Untitled') . ")";
        return array_merge(
            $this->analyzeIdSet('sketches', $ids, $label),
            ['sub_type' => 'sequence']
        );
    }

    /**
     * Resolves an arbitrary set of specific IDs and returns unified range statistics and complete description blocks.
     */
    private function analyzeIdSet(string $table, array $ids, string $sourceLabel): array
    {
        $ids = array_filter(array_unique(array_map('intval', $ids)));
        
        if (empty($ids)) {
            return [
                'table'         => $table,
                'source_label'  => $sourceLabel,
                'count'         => 0,
                'actual_min_id' => null,
                'actual_max_id' => null,
                'first_name'    => null,
                'last_name'     => null,
                'sample'        => [],
            ];
        }

        $count = count($ids);
        $minId = min($ids);
        $maxId = max($ids);

        $stmt = $this->pdo->prepare("SELECT name FROM `$table` WHERE id = ?");
        $stmt->execute([$minId]);
        $firstName = $stmt->fetchColumn() ?: null;

        $stmt->execute([$maxId]);
        $lastName = $stmt->fetchColumn() ?: null;

        $inClause = implode(',', $ids);
        
        $sample = [];
        
        if ($table === 'sketches') {
            $q = $this->pdo->query("SELECT id, name, description, mood, created_at FROM sketches WHERE id IN ($inClause) ORDER BY id ASC");
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $sample[] = [
                    'id'          => (int)$row['id'],
                    'name'        => (string)$row['name'],
                    'description' => (string)($row['description'] ?? ''),
                    'mood'        => $row['mood'] !== null ? (string)$row['mood'] : null,
                    'created_at'  => $row['created_at'] !== null ? (string)$row['created_at'] : null,
                ];
            }
        } elseif ($table === 'characters') {
            $q = $this->pdo->query("SELECT id, name, role, age_background, desc_abbr, description, motivations FROM characters WHERE id IN ($inClause) ORDER BY id ASC");
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $sample[] = [
                    'id'             => (int)$row['id'],
                    'name'           => (string)$row['name'],
                    'role'           => $row['role'] !== null ? (string)$row['role'] : null,
                    'age_background' => $row['age_background'] !== null ? (string)$row['age_background'] : null,
                    'desc_abbr'      => $row['desc_abbr'] !== null ? (string)$row['desc_abbr'] : null,
                    'description'    => (string)($row['description'] ?? ''),
                    'motivations'    => (string)($row['motivations'] ?? ''),
                ];
            }
        }

        return [
            'table'         => $table,
            'source_label'  => $sourceLabel,
            'count'         => $count,
            'actual_min_id' => $minId,
            'actual_max_id' => $maxId,
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'sample'        => $sample,
        ];
    }
}