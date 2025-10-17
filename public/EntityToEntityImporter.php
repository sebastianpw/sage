<?php

class EntityToEntityImporter
{
    private static ?\App\Core\SpwBase $spw = null;

    private function __construct() {}

    /**
     * Import entities into another entity table.
     *
     * @param string   $sourceEntity    Source table name
     * @param string   $targetEntity    Target table name
     * @param int      $startId         Starting ID for the import
     * @param int      $limit           Maximum number of rows to import
     * @param bool     $copyNameDesc    Copy name/description
     * @param int|null $frameId         Specific frame ID or null for latest
     * @param int|null $targetEntityId  Optional: update this target row instead of inserting
     * @return array   Array of messages
     */
    public static function import(
        string $sourceEntity,
        string $targetEntity,
        int $startId = 0,
        int $limit = 100,
        bool $copyNameDesc = true,
        ?int $frameId = null,
        ?int $targetEntityId = null
    ): array {

        if ($frameId !== null && $limit > 1) {
            throw new Exception("Cannot specify a frame ID when importing more than one entity (limit=$limit).");
        }

        if ($targetEntityId !== null && $limit > 1) {
            throw new Exception("Cannot specify a targetEntityId when importing more than one entity (limit=$limit).");
        }

        self::$spw = \App\Core\SpwBase::getInstance();
        $mysqli = self::$spw->getMysqli();
        $results = [];

        $entities = self::fetchEntities($sourceEntity, $startId, $limit, $mysqli);
        if (empty($entities)) {
            return ["No entities found in table '$sourceEntity' starting from ID $startId."];
        }

        $mysqli->begin_transaction();
        try {
            foreach ($entities as $entityRow) {
                $frameInfo = self::getRelatedFrame($sourceEntity, $entityRow['id'], $mysqli, $frameId);
                if (!$frameInfo) {
                    $results[] = "Entity ID {$entityRow['id']} has no frame. Skipped.";
                    continue;
                }

                $targetId = self::saveEntity(
                    $sourceEntity,
                    $targetEntity,
                    $entityRow,
                    (int)$frameInfo['id'],
                    $frameInfo['filename'],
                    $copyNameDesc,
                    $mysqli,
                    $targetEntityId
                );

                $action = $targetEntityId !== null ? "updated" : "imported";
                $results[] = "Entity ID {$entityRow['id']} $action into $targetEntity ID $targetId (frame: {$frameInfo['filename']})";
            }

            $mysqli->commit();
        } catch (\Exception $e) {
            $mysqli->rollback();
            self::$spw->getLogger()->debug([
                'action'    => 'EntityToEntityImporter::importError',
                'exception' => $e->getMessage()
            ]);
            $results[] = "Import failed: " . $e->getMessage();
        }

        return $results;
    }

    /**
     * Import entities into another entity table (ControlNet-specific).
     *
     * ControlNet rules:
     *  - Only a single source entity is processed (limit forced to 1).
     *  - startId (source_entity_id), frameId and targetEntityId are required.
     *  - Never copy name/description.
     *  - Always update the existing target row (targetEntityId).
     *  - Writes into cnmap / cnmap_frame_id / cnmap_frame_filename / cnmap_prompt columns.
     *
     * @param string   $sourceEntity
     * @param string   $targetEntity
     * @param int      $startId
     * @param int      $limit
     * @param bool     $copyNameDesc  ignored (forced false)
     * @param int|null $frameId
     * @param int|null $targetEntityId
     * @return array
     */
    public static function importControlNet(
        string $sourceEntity,
        string $targetEntity,
        int $startId = 0,
        int $limit = 100,
        bool $copyNameDesc = true,
        ?int $frameId = null,
        ?int $targetEntityId = null
    ): array {

        self::$spw = \App\Core\SpwBase::getInstance();
        $mysqli = self::$spw->getMysqli();
        $results = [];

        // Force single-entity behavior
        $limitToFetch = 1;

        // Validate required params
        $missing = [];
        if ($startId <= 0) {
            $missing[] = 'source_entity_id';
        }
        if ($frameId === null) {
            $missing[] = 'frame_id';
        }
        if ($targetEntityId === null) {
            $missing[] = 'target_entity_id';
        }

        if (!empty($missing)) {
            return ["Missing required ControlNet parameters: " . implode(', ', $missing) . "."];
        }

        // Fetch the single source entity
        $entities = self::fetchEntities($sourceEntity, $startId, $limitToFetch, $mysqli);
        if (empty($entities)) {
            return ["No entity found in '$sourceEntity' with ID $startId."];
        }

        $entityRow = $entities[0];

        // Get the frame info (must exist)
        $frameInfo = self::getRelatedFrame($sourceEntity, $entityRow['id'], $mysqli, $frameId);
        if (!$frameInfo) {
            return ["Frame ID $frameId not found for entity ID {$entityRow['id']}."];
        }

        // Pull prompt from frameInfo (frame prompt, not entity)
        $framePrompt = $frameInfo['prompt'] ?? '';

        // Begin transaction and perform the update on the target row
        $mysqli->begin_transaction();
        try {
            // Always update the existing target row. Never copy name/description.
            $savedId = self::saveControlNet(
                $sourceEntity,
                $targetEntity,
                $entityRow,
                (int)$frameInfo['id'],
                $frameInfo['filename'],
                $framePrompt,
                $mysqli,
                $targetEntityId
            );

            $results[] = "ControlNet entity ID {$entityRow['id']} assigned to $targetEntity ID $savedId (frame: {$frameInfo['filename']})";

            $mysqli->commit();
        } catch (\Exception $e) {
            $mysqli->rollback();
            self::$spw->getLogger()->debug([
                'action'    => 'EntityToEntityImporter::importControlNetError',
                'exception' => $e->getMessage()
            ]);
            $results[] = "ControlNet import failed: " . $e->getMessage();
        }

        return $results;
    }

    /**
     * Assign a frame to a composite via composite_frames table.
     *
     * Composite assignment rules:
     *  - Only a single source entity is processed (limit forced to 1).
     *  - startId (source_entity_id), frameId and targetEntityId are required.
     *  - Never copy name/description.
     *  - Creates an entry in composite_frames table.
     *
     * @param string   $sourceEntity
     * @param string   $targetEntity    Must be 'composites'
     * @param int      $startId
     * @param int      $limit
     * @param bool     $copyNameDesc    ignored (forced false)
     * @param int|null $frameId
     * @param int|null $targetEntityId  The composite ID
     * @return array
     */
    public static function importComposite(
        string $sourceEntity,
        string $targetEntity,
        int $startId = 0,
        int $limit = 100,
        bool $copyNameDesc = true,
        ?int $frameId = null,
        ?int $targetEntityId = null
    ): array {

        self::$spw = \App\Core\SpwBase::getInstance();
        $mysqli = self::$spw->getMysqli();
        $results = [];

        // Validate target is composites
        if ($targetEntity !== 'composites') {
            return ["Composite assignment only works with target='composites'."];
        }

        // Force single-entity behavior
        $limitToFetch = 1;

        // Validate required params
        $missing = [];
        if ($startId <= 0) {
            $missing[] = 'source_entity_id';
        }
        if ($frameId === null) {
            $missing[] = 'frame_id';
        }
        if ($targetEntityId === null) {
            $missing[] = 'target_entity_id';
        }

        if (!empty($missing)) {
            return ["Missing required Composite parameters: " . implode(', ', $missing) . "."];
        }

        // Fetch the single source entity
        $entities = self::fetchEntities($sourceEntity, $startId, $limitToFetch, $mysqli);
        if (empty($entities)) {
            return ["No entity found in '$sourceEntity' with ID $startId."];
        }

        $entityRow = $entities[0];

        // Get the frame info (must exist)
        $frameInfo = self::getRelatedFrame($sourceEntity, $entityRow['id'], $mysqli, $frameId);
        if (!$frameInfo) {
            return ["Frame ID $frameId not found for entity ID {$entityRow['id']}."];
        }

        // Verify composite exists
        $checkStmt = $mysqli->prepare("SELECT id FROM composites WHERE id = ?");
        $checkStmt->bind_param('i', $targetEntityId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows === 0) {
            $checkStmt->close();
            return ["Composite ID $targetEntityId does not exist."];
        }
        $checkStmt->close();

        // Begin transaction and insert into composite_frames
        $mysqli->begin_transaction();
        try {
            $savedId = self::saveComposite(
                $sourceEntity,
                $entityRow,
                (int)$frameInfo['id'],
                $frameInfo['filename'],
                $mysqli,
                $targetEntityId
            );

            $results[] = "Frame {$frameInfo['id']} from {$sourceEntity} entity ID {$entityRow['id']} assigned to composite ID $savedId (frame: {$frameInfo['filename']})";

            $mysqli->commit();
        } catch (\Exception $e) {
            $mysqli->rollback();
            self::$spw->getLogger()->debug([
                'action'    => 'EntityToEntityImporter::importCompositeError',
                'exception' => $e->getMessage()
            ]);
            $results[] = "Composite assignment failed: " . $e->getMessage();
        }

        return $results;
    }

    protected static function fetchEntities(string $entity, int $startId, int $limit, mysqli $mysqli): array
    {
        $stmt = $mysqli->prepare("SELECT * FROM `$entity` WHERE id >= ? ORDER BY id ASC LIMIT ?");
        if ($stmt === false) {
            throw new \Exception("fetchEntities prepare failed: " . $mysqli->error);
        }
        $stmt->bind_param('ii', $startId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $entities = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        self::$spw->getLogger()->debug([
            'action'  => 'fetchEntities',
            'table'   => $entity,
            'startId' => $startId,
            'limit'   => $limit,
            'fetched' => count($entities)
        ]);

        return $entities;
    }

    /**
     * Returns ['id' => frameId, 'filename' => filename, 'prompt' => prompt] or null if none found.
     *
     * This function is backward-compatible: if the frames table does not have a `prompt` column,
     * it will fall back to selecting only id and filename and return prompt as an empty string.
     */
    protected static function getRelatedFrame(
        string $entity,
        int $entityId,
        mysqli $mysqli,
        ?int $frameId = null
    ): ?array {
        $linkTable = "frames_2_$entity";

        // Preferred SQL (includes prompt)
        if ($frameId !== null) {
            $sqlWithPrompt = "SELECT f.id, f.filename, f.prompt
                              FROM frames f
                              JOIN `$linkTable` l ON l.from_id = f.id
                              WHERE l.to_id = ? AND f.id = ?
                              LIMIT 1";
            $sqlNoPrompt = "SELECT f.id, f.filename
                            FROM frames f
                            JOIN `$linkTable` l ON l.from_id = f.id
                            WHERE l.to_id = ? AND f.id = ?
                            LIMIT 1";
        } else {
            $sqlWithPrompt = "SELECT f.id, f.filename, f.prompt
                              FROM frames f
                              JOIN `$linkTable` l ON l.from_id = f.id
                              WHERE l.to_id = ?
                              ORDER BY f.id DESC
                              LIMIT 1";
            $sqlNoPrompt = "SELECT f.id, f.filename
                            FROM frames f
                            JOIN `$linkTable` l ON l.from_id = f.id
                            WHERE l.to_id = ?
                            ORDER BY f.id DESC
                            LIMIT 1";
        }

        // Try query that includes prompt first
        $stmt = $mysqli->prepare($sqlWithPrompt);
        if ($stmt !== false) {
            if ($frameId !== null) {
                $stmt->bind_param('ii', $entityId, $frameId);
            } else {
                $stmt->bind_param('i', $entityId);
            }
        } else {
            // Fallback to query without prompt (schema might not have prompt column)
            $stmt = $mysqli->prepare($sqlNoPrompt);
            if ($stmt === false) {
                // fatal: both queries failed
                throw new \Exception("getRelatedFrame prepare failed: " . $mysqli->error);
            }
            if ($frameId !== null) {
                $stmt->bind_param('ii', $entityId, $frameId);
            } else {
                $stmt->bind_param('i', $entityId);
            }
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $frame = $res->fetch_assoc();
        $stmt->close();

        if (!$frame) {
            return null;
        }

        return [
            'id' => (int)$frame['id'],
            'filename' => $frame['filename'],
            'prompt' => isset($frame['prompt']) ? $frame['prompt'] : ''
        ];
    }

    /**
     * Update an existing target row with ControlNet columns.
     *
     * Writes:
     *  - cnmap = 1
     *  - cnmap_frame_id = $frameId
     *  - cnmap_frame_filename = $frameFilename
     *  - cnmap_prompt = $prompt (empty string when null)
     *
     * Returns the $targetEntityId (int).
     */
    protected static function saveControlNet(
        string $sourceEntity,
        string $targetEntity,
        array $entityData,
        int $frameId,
        string $frameFilename,
        ?string $prompt,
        mysqli $mysqli,
        int $targetEntityId
    ): int {
        // ControlNet assignment always updates an existing target row; no inserts.
        $promptVal = $prompt ?? '';

        $stmt = $mysqli->prepare(
            "UPDATE `$targetEntity`
             SET cnmap = 1,
                 cnmap_frame_id = ?,
                 cnmap_frame_filename = ?,
                 cnmap_prompt = ?
             WHERE id = ?"
        );

        if ($stmt === false) {
            throw new \Exception("Failed to prepare statement for saveControlNet: " . $mysqli->error);
        }

        // bind types: frameId (i), filename (s), prompt (s), targetId (i)
        $stmt->bind_param('issi', $frameId, $frameFilename, $promptVal, $targetEntityId);
        $stmt->execute();

        if ($stmt->errno) {
            $err = $stmt->error;
            $stmt->close();
            throw new \Exception("saveControlNet failed: " . $err);
        }

        $stmt->close();

        self::$spw->getLogger()->debug([
            'action'        => 'updateControlNet',
            'sourceEntity'  => $sourceEntity,
            'entityId'      => $entityData['id'] ?? null,
            'frameFilename' => $frameFilename,
            'targetEntity'  => $targetEntity,
            'targetId'      => $targetEntityId
        ]);

        return $targetEntityId;
    }

    /**
     * Insert entry into composite_frames table.
     *
     * Creates an assignment between a composite and a frame.
     * Uses INSERT IGNORE to avoid duplicates.
     *
     * Returns the compositeId (int).
     */
    protected static function saveComposite(
        string $sourceEntity,
        array $entityData,
        int $frameId,
        string $frameFilename,
        mysqli $mysqli,
        int $compositeId
    ): int {
        $stmt = $mysqli->prepare(
            "INSERT IGNORE INTO composite_frames (composite_id, frame_id) VALUES (?, ?)"
        );

        if ($stmt === false) {
            throw new \Exception("Failed to prepare statement for saveComposite: " . $mysqli->error);
        }

        $stmt->bind_param('ii', $compositeId, $frameId);
        $stmt->execute();

        if ($stmt->errno) {
            $err = $stmt->error;
            $stmt->close();
            throw new \Exception("saveComposite failed: " . $err);
        }

        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        self::$spw->getLogger()->debug([
            'action'        => 'insertCompositeFrame',
            'sourceEntity'  => $sourceEntity,
            'entityId'      => $entityData['id'] ?? null,
            'frameId'       => $frameId,
            'frameFilename' => $frameFilename,
            'compositeId'   => $compositeId,
            'inserted'      => $affectedRows > 0
        ]);

        return $compositeId;
    }

    /**
     * Insert new row or update existing target row if $targetEntityId is provided
     *
     * Now uses the new img2img schema:
     *  - img2img (tinyint) flag set to 1
     *  - img2img_frame_id references the frame id
     */
    protected static function saveEntity(
        string $sourceEntity,
        string $targetEntity,
        array $entityData,
        int $frameId,
        string $frameFilename,
        bool $copyNameDesc,
        mysqli $mysqli,
        ?int $targetEntityId = null
    ): int {
        $name        = $copyNameDesc && isset($entityData['name']) ? $entityData['name'] : null;
        $description = $copyNameDesc && isset($entityData['description']) ? $entityData['description'] : null;

        if ($targetEntityId !== null) {
            // --- UPDATE existing row: only update img2img info (preserve other fields) ---
            $stmt = $mysqli->prepare(
                "UPDATE `$targetEntity` 
                 SET img2img = 1, img2img_frame_id = ?
                 WHERE id = ?"
            );
            $stmt->bind_param('ii', $frameId, $targetEntityId);
            $stmt->execute();
            $stmt->close();

            self::$spw->getLogger()->debug([
                'action'        => 'updateEntity',
                'sourceEntity'  => $sourceEntity,
                'entityId'      => $entityData['id'],
                'frameFilename' => $frameFilename,
                'targetEntity'  => $targetEntity,
                'targetId'      => $targetEntityId
            ]);

            return $targetEntityId;
        } else {
            // --- INSERT new row ---
            // ensure name/description are strings for bind_param (use empty string if null)
            $nameVal = $name ?? '';
            $descVal = $description ?? '';

            $stmt = $mysqli->prepare(
                "INSERT INTO `$targetEntity` 
                 (name, description, img2img, img2img_frame_id) 
                 VALUES (?, ?, 1, ?)"
            );
            // bind params: name (s), description (s), frameId (i)
            $stmt->bind_param('ssi', $nameVal, $descVal, $frameId);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();

            self::$spw->getLogger()->debug([
                'action'        => 'insertEntity',
                'sourceEntity'  => $sourceEntity,
                'entityId'      => $entityData['id'],
                'frameFilename' => $frameFilename,
                'targetEntity'  => $targetEntity,
                'targetId'      => $newId
            ]);

            return $newId;
        }
    }
}
