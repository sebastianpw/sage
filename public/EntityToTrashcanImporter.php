<?php

class EntityToTrashcanImporter
{
    private static ?\App\Core\SpwBase $spw = null;

    private function __construct() {}

    /**
     * Delete a single frame by frame ID.
     *
     * @param int $frameId
     * @return array Messages describing the operation
     */
    public static function deleteFrameById(int $frameId): array
    {
        self::$spw = \App\Core\SpwBase::getInstance();
        $mysqli = self::$spw->getMysqli();
        $results = [];

        // Fetch frame
        $stmt = $mysqli->prepare("SELECT * FROM frames WHERE id = ?");
        $stmt->bind_param('i', $frameId);
        $stmt->execute();
        $res = $stmt->get_result();
        $frame = $res->fetch_assoc();
        $stmt->close();

        if (!$frame) {
            return ["Frame ID $frameId not found."];
        }

        $mysqli->begin_transaction();
        try {
            self::moveFrameToTrashcan($frame, $mysqli);
            $results[] = "Frame ID $frameId moved to frames_trashcan.";
            $mysqli->commit();
        } catch (\Exception $e) {
            $mysqli->rollback();
            self::$spw->getLogger()->debug([
                'action' => 'EntityToTrashcanImporter::deleteFrameByIdError',
                'exception' => $e->getMessage()
            ]);
            $results[] = "Operation failed: " . $e->getMessage();
        }

        return $results;
    }

    /**
     * Delete all frames for a single entity.
     *
     * @param string $entity
     * @param int $entityId
     * @return array Messages describing the operation
     */
    public static function deleteAllFramesForEntity(string $entity, int $entityId): array
    {
        self::$spw = \App\Core\SpwBase::getInstance();
        $mysqli = self::$spw->getMysqli();
        $results = [];

        $frames = self::fetchFramesForEntity($entity, $entityId, $mysqli);
        if (empty($frames)) {
            return ["No frames found for entity '$entity' with ID $entityId."];
        }

        $mysqli->begin_transaction();
        try {
            foreach ($frames as $frame) {
                self::moveFrameToTrashcan($frame, $mysqli);
                $results[] = "Frame ID {$frame['id']} moved to frames_trashcan.";
            }
            $mysqli->commit();
        } catch (\Exception $e) {
            $mysqli->rollback();
            self::$spw->getLogger()->debug([
                'action' => 'EntityToTrashcanImporter::deleteAllFramesForEntityError',
                'exception' => $e->getMessage()
            ]);
            $results[] = "Operation failed: " . $e->getMessage();
        }

        return $results;
    }

    /**
     * Delete frames for multiple entities (paged by start ID and limit).
     *
     * @param string $entity
     * @param int $startId
     * @param int $limit
     * @return array Messages describing the operation
     */
    public static function deleteFramesForEntities(string $entity, int $startId, int $limit): array
    {
        if ($limit < 1) {
            throw new Exception("Limit must be greater than 0.");
        }

        self::$spw = \App\Core\SpwBase::getInstance();
        $mysqli = self::$spw->getMysqli();
        $results = [];

        // Fetch the next $limit entities starting at $startId
        $stmt = $mysqli->prepare("SELECT id FROM `$entity` WHERE id >= ? ORDER BY id ASC LIMIT ?");
        $stmt->bind_param('ii', $startId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $entities = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($entities)) {
            return ["No entities found in table '$entity' starting from ID $startId."];
        }

        $mysqli->begin_transaction();
        try {
            foreach ($entities as $entityRow) {
                $entityId = $entityRow['id'];
                $frames = self::fetchFramesForEntity($entity, $entityId, $mysqli);
                foreach ($frames as $frame) {
                    self::moveFrameToTrashcan($frame, $mysqli);
                    $results[] = "Frame ID {$frame['id']} from entity ID $entityId moved to frames_trashcan.";
                }
            }
            $mysqli->commit();
        } catch (\Exception $e) {
            $mysqli->rollback();
            self::$spw->getLogger()->debug([
                'action' => 'EntityToTrashcanImporter::deleteFramesForEntitiesError',
                'exception' => $e->getMessage()
            ]);
            $results[] = "Operation failed: " . $e->getMessage();
        }

        return $results;
    }

    // --- Protected helper methods ---

    protected static function fetchFramesForEntity(string $entity, int $entityId, mysqli $mysqli): array
    {
        $linkTable = "frames_2_$entity";
        $stmt = $mysqli->prepare("
            SELECT f.*
            FROM frames f
            JOIN `$linkTable` l ON l.from_id = f.id
            WHERE l.to_id = ?
            ORDER BY f.id ASC
        ");
        $stmt->bind_param('i', $entityId);
        $stmt->execute();
        $res = $stmt->get_result();
        $frames = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $frames;
    }

    protected static function moveFrameToTrashcan(array $frame, mysqli $mysqli): void
    {
        // Insert into frames_trashcan with correct bind_param types
        $stmt = $mysqli->prepare("
            INSERT INTO frames_trashcan 
            (original_frame_id, name, filename, prompt, entity_type, entity_id, style, style_id, created_at, img2img_entity, img2img_id, img2img_filename, deleted_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param(
            'issssisssiii',
            $frame['id'],             // original_frame_id (int)
            $frame['name'],           // name (varchar)
            $frame['filename'],       // filename (varchar)
            $frame['prompt'],         // prompt (text)
            $frame['entity_type'],    // entity_type (varchar)
            $frame['entity_id'],      // entity_id (int)
            $frame['style'],          // style (text)
            $frame['style_id'],       // style_id (int)
            $frame['created_at'],     // created_at (timestamp/string)
            $frame['img2img_entity'], // img2img_entity (varchar)
            $frame['img2img_id'],     // img2img_id (int)
            $frame['img2img_filename'] // img2img_filename (varchar)
        );
        $stmt->execute();
        $stmt->close();

        // Delete original frame
        $stmt = $mysqli->prepare("DELETE FROM frames WHERE id = ?");
        $stmt->bind_param('i', $frame['id']);
        $stmt->execute();
        $stmt->close();

        // Delete all mappings for this frame in the entity mapping table
        $linkTable = "frames_2_" . ($frame['entity_type'] ?? 'unknown');
        $stmt = $mysqli->prepare("DELETE FROM `$linkTable` WHERE from_id = ?");
        $stmt->bind_param('i', $frame['id']);
        $stmt->execute();
        $stmt->close();

        self::$spw->getLogger()->debug([
            'action' => 'moveFrameToTrashcan',
            'frameId' => $frame['id'],
            'entityType' => $frame['entity_type'],
            'entityId' => $frame['entity_id']
        ]);
    }
}
