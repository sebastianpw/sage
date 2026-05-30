<?php

class AudioToCompositeImporter
{
    private static ?\App\Core\SpwBase $spw = null;

    // Hardcoded list of supported audio source entities
    private const ALLOWED_ENTITIES = [
        "audio_ambiences",
        "audio_cues",
        "audio_dialogue_lines",
        "audio_foleys",
        "audio_fxsounds",
        "audio_themes"
    ];

    private function __construct() {}

    /**
     * Import logic: Links an audio file (via its source entity) to a Composite.
     * 
     * @param string $sourceEntity      One of the ALLOWED_ENTITIES
     * @param int    $sourceEntityId    ID of the row in the source table
     * @param int    $compositeId       ID of the composite
     * @param int|null $audioId         Optional specific audio ID (otherwise finds latest)
     * @return array                    List of result messages
     */
    public static function import(
        string $sourceEntity,
        int $sourceEntityId,
        int $compositeId,
        ?int $audioId = null
    ): array {
        
        self::$spw = \App\Core\SpwBase::getInstance();
        $mysqli = self::$spw->getMysqli();
        $results = [];

        // 1. Validate Source Entity is in our hardcoded allowed list
        if (!in_array($sourceEntity, self::ALLOWED_ENTITIES)) {
            return ["Error: '$sourceEntity' is not a supported audio entity."];
        }

        // 2. Validate Composite Exists
        $compStmt = $mysqli->prepare("SELECT id, name FROM composites WHERE id = ?");
        $compStmt->bind_param('i', $compositeId);
        $compStmt->execute();
        $compRes = $compStmt->get_result();
        if ($compRes->num_rows === 0) {
            $compStmt->close();
            return ["Error: Target Composite ID $compositeId does not exist."];
        }
        $compositeRow = $compRes->fetch_assoc();
        $compStmt->close();

        // 3. Validate Source Entity Row Exists
        // We use the variable table name safely because we validated it against the const list
        $srcStmt = $mysqli->prepare("SELECT id FROM `$sourceEntity` WHERE id = ?");
        if (!$srcStmt) {
            return ["Error: Could not prepare query for table '$sourceEntity'. Table might be missing."];
        }
        $srcStmt->bind_param('i', $sourceEntityId);
        $srcStmt->execute();
        if ($srcStmt->get_result()->num_rows === 0) {
            $srcStmt->close();
            return ["Error: Entity ID $sourceEntityId not found in '$sourceEntity'."];
        }
        $srcStmt->close();

        // 4. Find the Audio
        $audioInfo = self::getRelatedAudio($sourceEntity, $sourceEntityId, $mysqli, $audioId);
        
        if (!$audioInfo) {
            if ($audioId) {
                return ["Error: Audio ID $audioId is not linked to $sourceEntity ID $sourceEntityId."];
            } else {
                return ["Error: No audio files found for $sourceEntity ID $sourceEntityId."];
            }
        }

        // 5. Assign to Composite
        $mysqli->begin_transaction();
        try {
            $inserted = self::saveCompositeAudio(
                $compositeId, 
                (int)$audioInfo['id'], 
                $mysqli
            );

            if ($inserted) {
                $results[] = "Success: Audio #{$audioInfo['id']} ('{$audioInfo['filename']}') assigned to Composite #{$compositeId} ('{$compositeRow['name']}').";
            } else {
                $results[] = "Info: Audio #{$audioInfo['id']} was already assigned to Composite #{$compositeId}.";
            }

            $mysqli->commit();
        } catch (\Exception $e) {
            $mysqli->rollback();
            $results[] = "Error: " . $e->getMessage();
        }

        return $results;
    }

    public static function getAllowedEntities(): array
    {
        return self::ALLOWED_ENTITIES;
    }

    /**
     * Fetch related audio from db:audios via the mapping table.
     * Mapping convention: audios_2_{entity} where from_id=audio_id and to_id=entity_id
     */
    private static function getRelatedAudio(
        string $entity,
        int $entityId,
        mysqli $mysqli,
        ?int $audioId = null
    ): ?array {
        $linkTable = "audios_2_$entity";

        // Query: join audios with the link table
        // l.from_id = audios.id
        // l.to_id   = entity.id
        $sql = "SELECT a.id, a.filename 
                FROM audios a
                JOIN `$linkTable` l ON l.from_id = a.id
                WHERE l.to_id = ?";

        if ($audioId !== null) {
            $sql .= " AND a.id = ?";
        } else {
            // Default to latest audio if none specified
            $sql .= " ORDER BY a.id DESC LIMIT 1";
        }

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            // This might happen if the specific link table doesn't exist
            return null;
        }

        if ($audioId !== null) {
            $stmt->bind_param('ii', $entityId, $audioId);
        } else {
            $stmt->bind_param('i', $entityId);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res->fetch_assoc();
        $stmt->close();

        return $data ?: null;
    }

    private static function saveCompositeAudio(int $compositeId, int $audioId, mysqli $mysqli): bool
    {
        $stmt = $mysqli->prepare("INSERT IGNORE INTO composite_audios (composite_id, audio_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $compositeId, $audioId);
        $stmt->execute();
        
        $affected = $stmt->affected_rows;
        $stmt->close();

        // Return true if inserted, false if already existed (IGNORE)
        return $affected > 0;
    }
}
