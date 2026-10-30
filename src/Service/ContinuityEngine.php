<?php
namespace App\Service;

use PDO;
use Exception;

class ContinuityEngine
{
    private PDO $pdo;

    public function __construct()
    {
        global $spw;
        $this->pdo = $spw->getPDO();
    }

    // Added $inheritFrames toggle, defaults to true to preserve all existing system behaviors
    public function ingestPayload(array $data, bool $inheritFrames = true): array
    {
        // 1. AUTO-INGEST NEW CONTINUITY TAGS IF PRESENT IN PAYLOAD
        $newTags = $data['continuity_tags_new'] ?? $data['continuity_tags'] ?? $data['new_tags'] ?? null;
        $ingestedTagCount = 0;
        $processedTags = [];
        
        if (is_array($newTags) && !empty($newTags)) {
            // NOTE: id = LAST_INSERT_ID(id) ensures we can reliably get the ID back on duplicates
            $stmtTag = $this->pdo->prepare("
                INSERT INTO continuity_entities (tag_name, canonical_name, entity_type, description, active, img2img_frame_id)
                VALUES (:tag, :cname, :etype, :desc, 1, :frame_id)
                ON DUPLICATE KEY UPDATE
                    description = VALUES(description),
                    canonical_name = COALESCE(VALUES(canonical_name), canonical_name),
                    img2img_frame_id = COALESCE(VALUES(img2img_frame_id), img2img_frame_id),
                    id = LAST_INSERT_ID(id)
            ");

            foreach ($newTags as $tagKey => $tagData) {
                $tagUpper = strtoupper(trim((string)$tagKey));
                if (empty($tagUpper)) continue;

                $desc = '';
                $frameId = null;

                // Support both flat string AND object notation for entity definitions
                if (is_array($tagData)) {
                    $desc = trim((string)($tagData['description'] ?? ''));
                    if (isset($tagData['img2img_frame_id']) && is_numeric($tagData['img2img_frame_id'])) {
                        $frameId = (int)$tagData['img2img_frame_id'];
                        if ($frameId <= 0) $frameId = null;
                    }
                } else {
                    $desc = trim((string)$tagData);
                }

                // Derive clean human display name (e.g. DP1_SKY_CARRIER -> Sky Carrier)
                $cleanName = ucwords(strtolower(str_replace(['_', '-'], ' ', preg_replace('/^[A-Z0-9]+_/', '', $tagUpper))));

                $stmtTag->execute([
                    'tag'      => $tagUpper,
                    'cname'    => $cleanName,
                    'etype'    => 'character',
                    'desc'     => $desc,
                    'frame_id' => $frameId
                ]);
                $ingestedTagCount++;
                $processedTags[] = $tagUpper;
            }
        }

        // 2. EXTRACT SKETCHES ARRAY
        $sketches = [];
        if (isset($data['sketches']) && is_array($data['sketches'])) {
            $sketches = $data['sketches'];
        } elseif (isset($data['name']) || isset($data['description'])) {
            $sketches = [$data];
        } else {
            $sketches = $data;
        }

        $insertedCount = 0;
        $sketchIds = [];

        if (!empty($sketches)) {
            // Fetch baseline DB entities map for smart append logic
            $baseEntities = [];
            $dbRows = $this->pdo->query("SELECT tag_name, description FROM continuity_entities WHERE active = 1")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($dbRows as $r) {
                $baseEntities[strtoupper($r['tag_name'])] = $r['description'];
            }

            global $GLOBALS;
            $pyapiUrl = ($GLOBALS['WORDNET_PYAPI_URL'] ?? 'http://127.0.0.1:8009') . '/continuity/process';

            // NOTE: Added img2img and img2img_frame_id to the insert and update clauses
            $stmt = $this->pdo->prepare("
                INSERT INTO `sketches` (`name`, `description_raw`, `description`, `order`, `img2img`, `img2img_frame_id`) 
                VALUES (:name, :desc_raw, :desc_expanded, 0, :img2img, :img2img_frame_id)
                ON DUPLICATE KEY UPDATE 
                    `description_raw`  = VALUES(`description_raw`),
                    `description`      = VALUES(`description`),
                    `img2img`          = VALUES(`img2img`),
                    `img2img_frame_id` = VALUES(`img2img_frame_id`),
                    `id`               = LAST_INSERT_ID(`id`)
            ");

            foreach ($sketches as $index => $item) {
                $rawDesc = $item['description'] ?? ($item['prompt'] ?? '');
                $rawOverrides = $item['overrides'] ?? null;
                $isHardOverride = !empty($item['hard_override']);

                // Parse img2img fields
                $img2imgFrameId = null;
                $img2imgFlag = 0;
                if (isset($item['img2img_frame_id']) && is_numeric($item['img2img_frame_id'])) {
                    $img2imgFrameId = (int)$item['img2img_frame_id'];
                    if ($img2imgFrameId > 0) {
                        $img2imgFlag = 1;
                    }
                }

                $processedOverrides = null;

                if (is_array($rawOverrides) && !empty($rawOverrides)) {
                    $processedOverrides = [];
                    foreach ($rawOverrides as $tag => $overrideVal) {
                        $tagUpper = strtoupper($tag);
                        if ($isHardOverride) {
                            $processedOverrides[$tagUpper] = $overrideVal;
                        } else {
                            $baseDesc = $baseEntities[$tagUpper] ?? '';
                            $processedOverrides[$tagUpper] = !empty($baseDesc) ? ($baseDesc . ', ' . $overrideVal) : $overrideVal;
                        }
                    }
                }

                $ch = curl_init($pyapiUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_POSTFIELDS => json_encode([
                        'payload' => $rawDesc,
                        'overrides' => $processedOverrides
                    ]),
                    CURLOPT_TIMEOUT => 30
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $expandedDesc = $rawDesc;
                if ($httpCode === 200 && $response) {
                    $pyResult = json_decode($response, true);
                    if (isset($pyResult['data'])) {
                        $expandedDesc = $pyResult['data'];
                    }
                }

                $name = trim($item['name'] ?? ('AI Sketch ' . date('Y-m-d H:i:s') . ' #' . ($index + 1)));

                try {
                    $stmt->execute([
                        'name'             => $name,
                        'desc_raw'         => $rawDesc,
                        'desc_expanded'    => $expandedDesc,
                        'img2img'          => $img2imgFlag,
                        'img2img_frame_id' => $img2imgFrameId
                    ]);
                    $sketchIds[] = (int)$this->pdo->lastInsertId();
                    $insertedCount++;
                } catch (\PDOException $e) {
                    if ($e->getCode() == 23000) { // Dupe workaround
                        $name .= ' (' . time() . '_' . $index . ')';
                        $stmt->execute([
                            'name'             => $name,
                            'desc_raw'         => $rawDesc,
                            'desc_expanded'    => $expandedDesc,
                            'img2img'          => $img2imgFlag,
                            'img2img_frame_id' => $img2imgFrameId
                        ]);
                        $sketchIds[] = (int)$this->pdo->lastInsertId();
                        $insertedCount++;
                    } else {
                        throw $e;
                    }
                }

                // ─── INHERIT MULTIPLE FRAMES FROM CONTINUITY TAGS ───
                $currentSketchId = end($sketchIds);
                if ($currentSketchId && $inheritFrames) { // <--- Added $inheritFrames check here
                    preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $rawDesc, $matches);
                    $usedTags = array_unique($matches[1]);
                    
                    if (!empty($usedTags)) {
                        $inClause = implode(',', array_fill(0, count($usedTags), '?'));
                        $stmtFrames = $this->pdo->prepare("
                            SELECT img2img_frame_id 
                            FROM continuity_entities 
                            WHERE tag_name IN ($inClause) 
                              AND img2img_frame_id IS NOT NULL 
                              AND img2img_frame_id > 0
                        ");
                        $stmtFrames->execute(array_values($usedTags));
                        $frameIds = $stmtFrames->fetchAll(PDO::FETCH_COLUMN);
                        
                        if (!empty($frameIds)) {
                            $stmtInsertFrame = $this->pdo->prepare("INSERT IGNORE INTO sketch_frames (sketch_id, frame_id) VALUES (?, ?)");
                            foreach ($frameIds as $fId) {
                                $stmtInsertFrame->execute([$currentSketchId, $fId]);
                            }
                        }
                    }
                }
                // ──────────────────────────────────────────────────────────
            }
        }

        return [
            'tags_inserted' => $ingestedTagCount,
            'sketches_inserted' => $insertedCount,
            'sketch_ids' => $sketchIds,
            'processed_tags' => $processedTags
        ];
    }
}