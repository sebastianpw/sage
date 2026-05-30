<?php
// src/Service/GeneratorRepository.php
// ─────────────────────────────────────────────────────────────────────────────
// Pure-PDO repository for generator_config.
// Works directly against the same table used by the Doctrine entity.
// NO Doctrine, NO ORM, NO magic — just clean SQL + PHP classes.
// Backwards-compatible: reads/writes the exact same columns.
// ─────────────────────────────────────────────────────────────────────────────

declare(strict_types=1);

namespace App\Service;

use PDO;
use PDOException;
use InvalidArgumentException;
use RuntimeException;

// ─────────────────────────────────────────────────────────────────────────────
// Value object representing one generator row
// ─────────────────────────────────────────────────────────────────────────────
class GeneratorRecord
{
    public function __construct(
        public readonly int     $id,
        public readonly string  $configId,
        public readonly int     $userId,
        public readonly string  $title,
        public readonly string  $model,
        public readonly string  $systemRole,
        public readonly array   $instructions,
        public readonly array   $parameters,
        public readonly array   $outputSchema,
        public readonly ?array  $examples,
        public readonly ?array  $oracleConfig,
        public readonly string  $createdAt,
        public readonly string  $updatedAt,
        public readonly bool    $active,
        public readonly bool    $isPublic,
        public readonly int     $listOrder,
    ) {}

    /**
     * Reconstitute the config_json blob exactly as the admin UI expects it.
     * Shape: { system: { role, instructions[] }, parameters: {}, output: {}, examples: [] }
     */
    public function toConfigJson(): string
    {
        return json_encode([
            'system'     => [
                'role'         => $this->systemRole,
                'instructions' => $this->instructions,
            ],
            'parameters' => $this->parameters,
            'output'     => $this->outputSchema,
            'examples'   => $this->examples ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Decoded config as array (same shape as toConfigJson).
     */
    public function toConfigArray(): array
    {
        return [
            'system'     => [
                'role'         => $this->systemRole,
                'instructions' => $this->instructions,
            ],
            'parameters' => $this->parameters,
            'output'     => $this->outputSchema,
            'examples'   => $this->examples ?? [],
        ];
    }

    /**
     * Full record as array (for API responses).
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'config_id'    => $this->configId,
            'user_id'      => $this->userId,
            'title'        => $this->title,
            'model'        => $this->model,
            'system_role'  => $this->systemRole,
            'instructions' => $this->instructions,
            'parameters'   => $this->parameters,
            'output_schema'=> $this->outputSchema,
            'examples'     => $this->examples,
            'oracle_config'=> $this->oracleConfig,
            'created_at'   => $this->createdAt,
            'updated_at'   => $this->updatedAt,
            'active'       => $this->active,
            'is_public'    => $this->isPublic,
            'list_order'   => $this->listOrder,
            'config_json'  => $this->toConfigJson(),
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Repository
// ─────────────────────────────────────────────────────────────────────────────
class GeneratorRepository
{
    private const TABLE = 'generator_config';

    public function __construct(private PDO $pdo) {}

    // ── Finders ──────────────────────────────────────────────────────────────

    public function findById(int $id): ?GeneratorRecord
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByConfigId(string $configId): ?GeneratorRecord
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE config_id = :cid LIMIT 1'
        );
        $stmt->execute([':cid' => $configId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Find active record by config_id accessible to this user
     * (owner OR public generator).
     */
    public function findActiveForUser(string $configId, int $userId): ?GeneratorRecord
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ' . self::TABLE .
            ' WHERE config_id = :cid AND active = 1
              AND (user_id = :uid OR is_public = 1)
              LIMIT 1'
        );
        $stmt->execute([':cid' => $configId, ':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * List generators visible to this user (owned + public),
     * optionally filtered by display-area key, with search.
     *
     * Returns plain arrays (not hydrated) for speed in list views.
     * Each row also includes display_area_keys[] joined from the pivot table.
     */
    public function listForUser(
        int    $userId,
        string $filterArea  = '',
        string $search      = '',
        int    $limit       = 200,
        int    $offset      = 0
    ): array {
        $where  = ['(gc.user_id = :uid OR gc.is_public = 1)'];
        $params = [':uid' => $userId];

        if ($filterArea !== '') {
            $where[]              = 'EXISTS (
                SELECT 1 FROM generator_config_to_display_area gda
                INNER JOIN generator_config_display_area da ON da.id = gda.display_area_id
                WHERE gda.generator_config_id = gc.id AND da.area_key = :area
            )';
            $params[':area'] = $filterArea;
        }

        if ($search !== '') {
            $where[]           = '(gc.title LIKE :search OR gc.system_role LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $whereSQL = implode(' AND ', $where);

        $sql = "SELECT gc.* FROM " . self::TABLE . " gc
                WHERE {$whereSQL}
                ORDER BY gc.list_order ASC, gc.id DESC
                LIMIT :lim OFFSET :off";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':off',  $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [];
        }

        // Enrich with display_area_keys
        $ids     = array_column($rows, 'id');
        $areaMap = $this->fetchDisplayAreaKeys($ids);

        $result = [];
        foreach ($rows as $row) {
            $rec           = $this->hydrate($row);
            $data          = $rec->toArray();
            $data['display_area_keys']  = $areaMap[$row['id']] ?? [];
            $data['is_owner']           = ((int)$row['user_id'] === $userId);
            $result[] = $data;
        }
        return $result;
    }

    /**
     * List only active generators for a specific display area (floatool usage).
     */
    public function listActiveByArea(int $userId, string $areaKey): array
    {
        $sql = "SELECT gc.id, gc.config_id, gc.title, gc.model
                FROM " . self::TABLE . " gc
                INNER JOIN generator_config_to_display_area gda ON gda.generator_config_id = gc.id
                INNER JOIN generator_config_display_area da ON da.id = gda.display_area_id
                WHERE gc.active = 1
                  AND (gc.user_id = :uid OR gc.is_public = 1)
                  AND da.area_key = :area
                ORDER BY gc.list_order ASC, gc.title ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':uid' => $userId, ':area' => $areaKey]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Mutations ─────────────────────────────────────────────────────────────

    /**
     * Create from a config_json blob (same format as admin UI sends).
     * Returns the new record ID.
     */
    public function createFromConfigJson(
        int    $userId,
        string $title,
        string $model,
        string $configJson,
        bool   $isPublic    = false,
        array  $oracleConfig = [],
        array  $displayAreaIds = []
    ): int {
        $parsed = $this->parseConfigJson($configJson);

        $configId = bin2hex(random_bytes(16));
        $now      = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . "
                (config_id, user_id, title, model, system_role, instructions,
                 parameters, output_schema, examples, oracle_config,
                 created_at, updated_at, active, is_public, list_order)
            VALUES
                (:cid, :uid, :title, :model, :role, :instr,
                 :params, :output, :examples, :oracle,
                 :now, :now2, 1, :pub, 0)
        ");

        $stmt->execute([
            ':cid'     => $configId,
            ':uid'     => $userId,
            ':title'   => $title,
            ':model'   => $model,
            ':role'    => $parsed['system_role'],
            ':instr'   => json_encode($parsed['instructions'], JSON_UNESCAPED_UNICODE),
            ':params'  => json_encode($parsed['parameters'],   JSON_UNESCAPED_UNICODE),
            ':output'  => json_encode($parsed['output_schema'],JSON_UNESCAPED_UNICODE),
            ':examples'=> json_encode($parsed['examples'],     JSON_UNESCAPED_UNICODE),
            ':oracle'  => empty($oracleConfig)  ? null : json_encode($oracleConfig, JSON_UNESCAPED_UNICODE),
            ':now'     => $now,
            ':now2'    => $now,
            ':pub'     => (int)$isPublic,
        ]);

        $newId = (int)$this->pdo->lastInsertId();

        if (!empty($displayAreaIds)) {
            $this->syncDisplayAreas($newId, $displayAreaIds);
        }

        return $newId;
    }

    /**
     * Update from config_json blob.
     * Only updates fields that are provided; owner/admin check must happen in caller.
     */
    public function updateFromConfigJson(
        int    $id,
        string $title,
        string $model,
        string $configJson,
        bool   $isPublic,
        ?array $oracleConfig,
        array  $displayAreaIds,
        bool   $isActive = true,
        int    $listOrder = 0
    ): bool {
        $parsed = $this->parseConfigJson($configJson);
        $now    = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            UPDATE " . self::TABLE . " SET
                title        = :title,
                model        = :model,
                system_role  = :role,
                instructions = :instr,
                parameters   = :params,
                output_schema= :output,
                examples     = :examples,
                oracle_config= :oracle,
                is_public    = :pub,
                active       = :active,
                list_order   = :list_order,
                updated_at   = :now
            WHERE id = :id
        ");

        $ok = $stmt->execute([
            ':title'      => $title,
            ':model'      => $model,
            ':role'       => $parsed['system_role'],
            ':instr'      => json_encode($parsed['instructions'],  JSON_UNESCAPED_UNICODE),
            ':params'     => json_encode($parsed['parameters'],    JSON_UNESCAPED_UNICODE),
            ':output'     => json_encode($parsed['output_schema'], JSON_UNESCAPED_UNICODE),
            ':examples'   => json_encode($parsed['examples'],      JSON_UNESCAPED_UNICODE),
            ':oracle'     => $oracleConfig === null ? null : json_encode($oracleConfig, JSON_UNESCAPED_UNICODE),
            ':pub'        => (int)$isPublic,
            ':active'     => (int)$isActive,
            ':list_order' => $listOrder,
            ':now'        => $now,
            ':id'         => $id,
        ]);

        $this->syncDisplayAreas($id, $displayAreaIds);

        return $ok;
    }

    public function toggleActive(int $id): ?bool
    {
        $rec = $this->findById($id);
        if (!$rec) {
            return null;
        }
        $newState = !$rec->active;
        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE . ' SET active = :a, updated_at = :now WHERE id = :id'
        );
        $stmt->execute([
            ':a'   => (int)$newState,
            ':now' => date('Y-m-d H:i:s'),
            ':id'  => $id,
        ]);
        return $newState;
    }

    public function delete(int $id): bool
    {
        // Pivot rows handled by FK cascade, or manual cleanup if no cascade
        $this->pdo->prepare(
            'DELETE FROM generator_config_to_display_area WHERE generator_config_id = :id'
        )->execute([':id' => $id]);

        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Update list_order for a set of IDs (drag-and-drop reorder).
     * $orderedIds = [id, id, id, …] in desired order.
     */
    public function updateOrder(array $orderedIds): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE . ' SET list_order = :ord WHERE id = :id'
        );
        foreach ($orderedIds as $position => $id) {
            $stmt->execute([':ord' => $position, ':id' => (int)$id]);
        }
    }

    /**
     * Duplicate a record for a given user. Returns new ID.
     */
    public function duplicate(int $sourceId, int $newUserId): int
    {
        $rec = $this->findById($sourceId);
        if (!$rec) {
            throw new RuntimeException("Source generator #{$sourceId} not found");
        }
        return $this->createFromConfigJson(
            $newUserId,
            '[Copy of] ' . $rec->title,
            $rec->model,
            $rec->toConfigJson(),
            false,
            $rec->oracleConfig ?? [],
        );
    }

    // ── Display Area helpers ──────────────────────────────────────────────────

    public function getAllDisplayAreas(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT id, area_key, label FROM generator_config_display_area ORDER BY label ASC'
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    private function syncDisplayAreas(int $generatorId, array $areaIds): void
    {
        $this->pdo->prepare(
            'DELETE FROM generator_config_to_display_area WHERE generator_config_id = :id'
        )->execute([':id' => $generatorId]);

        if (empty($areaIds)) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO generator_config_to_display_area
                (generator_config_id, display_area_id) VALUES (:gid, :did)'
        );
        foreach ($areaIds as $did) {
            $stmt->execute([':gid' => $generatorId, ':did' => (int)$did]);
        }
    }

    /**
     * Returns [ generatorId => [area_key, …], … ] for a list of generator IDs.
     */
    private function fetchDisplayAreaKeys(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT gda.generator_config_id AS gid, da.area_key, da.label, da.id
             FROM generator_config_to_display_area gda
             INNER JOIN generator_config_display_area da ON da.id = gda.display_area_id
             WHERE gda.generator_config_id IN ({$ph})"
        );
        $stmt->execute($ids);
        $rows   = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $r) {
            $result[(int)$r['gid']][] = [
                'id'    => (int)$r['id'],
                'key'   => $r['area_key'],
                'label' => $r['label'],
            ];
        }
        return $result;
    }

    // ── Config JSON parser ────────────────────────────────────────────────────

    /**
     * Parse config_json blob into flat DB columns.
     *
     * Accepts two flavours:
     *   (A) Full shape: { system: { role, instructions[] }, parameters: {}, output: {}, examples: [] }
     *   (B) Flat shape: { system_role, instructions[], parameters: {}, output_schema: {}, examples: [] }
     *
     * Both shapes are valid — conventions are honoured but the parser is forgiving.
     */
    private function parseConfigJson(string $json): array
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new InvalidArgumentException('Invalid config JSON: ' . json_last_error_msg());
        }

        // Shape A (standard nested)
        if (isset($data['system']) && is_array($data['system'])) {
            return [
                'system_role'  => (string)($data['system']['role'] ?? ''),
                'instructions' => (array) ($data['system']['instructions'] ?? []),
                'parameters'   => (array) ($data['parameters']  ?? []),
                'output_schema'=> (array) ($data['output']       ?? $data['output_schema'] ?? []),
                'examples'     => (array) ($data['examples']     ?? []),
            ];
        }

        // Shape B (flat)
        return [
            'system_role'  => (string)($data['system_role']  ?? ''),
            'instructions' => (array) ($data['instructions'] ?? []),
            'parameters'   => (array) ($data['parameters']   ?? []),
            'output_schema'=> (array) ($data['output_schema']?? $data['output'] ?? []),
            'examples'     => (array) ($data['examples']     ?? []),
        ];
    }

    // ── Hydration ─────────────────────────────────────────────────────────────

    private function hydrate(array $row): GeneratorRecord
    {
        return new GeneratorRecord(
            id:           (int)  $row['id'],
            configId:            $row['config_id'],
            userId:       (int)  $row['user_id'],
            title:               $row['title'],
            model:               $row['model'],
            systemRole:          $row['system_role'],
            instructions: (array)(json_decode($row['instructions'] ?? '[]', true) ?? []),
            parameters:   (array)(json_decode($row['parameters']   ?? '[]', true) ?? []),
            outputSchema: (array)(json_decode($row['output_schema']?? '[]', true) ?? []),
            examples:            json_decode($row['examples']      ?? 'null', true),
            oracleConfig:        json_decode($row['oracle_config'] ?? 'null', true),
            createdAt:           $row['created_at'],
            updatedAt:           $row['updated_at'],
            active:       (bool) $row['active'],
            isPublic:     (bool) $row['is_public'],
            listOrder:    (int)  $row['list_order'],
        );
    }
}
