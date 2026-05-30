<?php
// public/taggerang_api.php
// API for Taggerang 🪃 — Hyper-Tagging Interface
//
// !! DB MIGRATION REQUIRED — run once:
//    ALTER TABLE tags ADD COLUMN visible tinyint(1) NOT NULL DEFAULT 1;
//    UPDATE tags SET visible = 1;
//
// Shares filter/lore intelligence with narratives_api.php
// New actions:
//   GET  get_tags          — list tags WHERE show_in_ui=1
//   POST save_tag_defs     — upsert (create sets show_in_ui=1; re-adding hidden tag flips it back)
//   POST save_frame_tags   — assign tag_id → [frame_ids] in tags_2_frames
//   GET  get_frame_tags    — get tags already assigned to a list of frame IDs
// ──────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// Core Classes (identical to narratives_api.php)
require_once __DIR__ . '/../src/Core/AbstractContextEngine.php';
require_once __DIR__ . '/../src/Core/VectorContextEngine.php';
require_once __DIR__ . '/SketchLibrary.php';
require_once __DIR__ . '/../src/Core/PyApiVectorService.php';
require_once __DIR__ . '/../src/Service/LoreAccessService.php';

use App\Core\VectorContextEngine;
use App\Service\LoreAccessService;

$engine  = new VectorContextEngine($pdo);
$library = new SketchLibrary($pdo);

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 1);
header('Content-Type: application/json');


// ============================================================
// HELPERS (identical to narratives_api.php — kept here for
// self-contained independence so the two files can diverge)
// ============================================================
function flatten_array_values($array) {
    $flat = [];
    array_walk_recursive($array, function($a) use (&$flat) {
        if (is_scalar($a)) $flat[] = trim((string)$a);
    });
    return implode(", ", array_filter($flat));
}

function serializeField(string $key, $value): string {
    if (is_string($value)) {
        $value = trim($value);
        if (strlen($value) <= 3 || strlen($value) > 800) return '';
        return ucfirst($key) . ": " . $value . ". ";
    }
    if (is_numeric($value)) return ucfirst($key) . ": " . $value . ". ";
    if (is_array($value) && !empty($value)) {
        $stringItems = array_values(array_filter($value, 'is_string'));
        if (count($stringItems) === count($value)) {
            if (count($stringItems) >= 2) {
                $avgLen = array_sum(array_map('strlen', $stringItems)) / count($stringItems);
                if ($avgLen > 40) return "Beats (" . $key . "): " . implode(" | ", $stringItems) . ". ";
                else              return ucfirst($key) . ": " . implode(", ", $stringItems) . ". ";
            }
            return ucfirst($key) . ": " . implode(", ", $stringItems) . ". ";
        }
        $flat = flatten_array_values($value);
        if ($flat) return ucfirst($key) . ": " . $flat . ". ";
    }
    return '';
}


// ============================================================
// GET ACTIONS
// ============================================================
if (isset($_GET['action'])) {
    try {
        switch ($_GET['action']) {

            // ── LIBRARY (shared with narratives) ──────────────────────
            case 'fetch_library':
                $page      = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $contextId = isset($_GET['context_id']) && $_GET['context_id'] !== '' ? (int)$_GET['context_id'] : null;

                $customQuery = null;
                $itemQueries = [];

                if (isset($_GET['filter_payload'])) {
                    $filterData = json_decode($_GET['filter_payload'], true);
                    if ($filterData) {
                        $result = buildRichQuery($pdo, $contextId, $filterData);
                        if (is_array($result)) {
                            $itemQueries = $result;
                            $customQuery = implode("\n\n", $result);
                        } else {
                            $customQuery = $result;
                        }
                    }
                } elseif (isset($_GET['custom_query'])) {
                    $customQuery = $_GET['custom_query'];
                }

                if (count($itemQueries) > 1) {
                    $rankedItems = $engine->getRankedItemsMulti($contextId, $itemQueries);
                } else {
                    $rankedItems = $engine->getRankedItems($contextId, $customQuery);
                }

                if (empty($rankedItems) && !$contextId && !$customQuery) {
                    $stmt = $pdo->query("SELECT sketch_id as id, 0 as score FROM sketch_analysis WHERE overall_quality > 0 ORDER BY analyzed_at DESC LIMIT 2000");
                    $rankedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }

                $result = $library->hydratePage($rankedItems, $page, 50);
                echo json_encode(['status' => 'success'] + $result);
                break;

            // ── HYDRATE SEQUENCE (used by load modal in narratives, kept for compat) ──
            case 'hydrate_sequence':
                $inputData = [];
                $inputJSON = file_get_contents('php://input');
                if (!empty($inputJSON)) {
                    $decoded = json_decode($inputJSON, true);
                    if (isset($decoded['items'])) $inputData = $decoded['items'];
                }
                if (empty($inputData) && isset($_GET['ids'])) {
                    $inputData = array_filter(array_map('intval', explode(',', $_GET['ids'])));
                }
                $data = $library->hydrateSpecificIds($inputData);
                echo json_encode(['status' => 'success', 'data' => $data]);
                break;

            // ── FILTER CATEGORIES ─────────────────────────────────────
            case 'get_filter_cats':
                $cats = ['episodes', 'scene_hooks', 'characters', 'locations', 'factions', 'artifacts'];
                echo json_encode(['status' => 'success', 'data' => $cats]);
                break;

            // ── FILTER ITEMS ──────────────────────────────────────────
            case 'get_filter_items':
                $docId = (int)$_GET['doc_id'];
                $cat   = $_GET['cat'] ?? 'characters';
                $lore  = new LoreAccessService($pdo);
                $lore->loadDoc($docId);

                $uiItems = [];
                if (in_array($cat, ['episodes', 'scene_hooks'])) {
                    $story   = $lore->getStoryEngine();
                    $rawList = $story[$cat] ?? [];
                    foreach ($rawList as $item) {
                        if (is_string($item)) {
                            $uiItems[] = $item;
                        } elseif (is_array($item)) {
                            $label = $item['title'] ?? ($item['name'] ?? ($item['event'] ?? 'Untitled'));
                            if ($cat === 'episodes' && isset($item['episode'])) {
                                $label = "Ep " . $item['episode'] . ": " . $label;
                            }
                            $uiItems[] = $label;
                        }
                    }
                } else {
                    $items = $lore->queryEntities($cat);
                    foreach ($items as $i) $uiItems[] = $i['name'] ?? 'Unknown';
                }
                $uiItems = array_values(array_unique(array_filter($uiItems)));
                echo json_encode(['status' => 'success', 'data' => $uiItems]);
                break;

            // ── TAGS: LIST VISIBLE ONLY ──────────────────────────────
            case 'get_tags':
                $tags = $pdo->query("SELECT id, name, description FROM tags WHERE show_in_ui = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $tags]);
                break;

            // ── FRAME TAGS: GET FOR FRAME IDS ────────────────────────
            // ?action=get_frame_tags&frame_ids=1,2,3,4
            case 'get_frame_tags':
                $rawIds = $_GET['frame_ids'] ?? '';
                $fids   = array_filter(array_map('intval', explode(',', $rawIds)));
                if (empty($fids)) { echo json_encode(['status' => 'success', 'data' => []]); break; }

                $placeholders = implode(',', array_fill(0, count($fids), '?'));
                $stmt = $pdo->prepare("
                    SELECT t2f.to_id AS frame_id, t.id AS tag_id, t.name AS tag_name
                    FROM tags_2_frames t2f
                    JOIN tags t ON t.id = t2f.from_id
                    WHERE t2f.to_id IN ($placeholders)
                    ORDER BY t.name ASC
                ");
                $stmt->execute($fids);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Group by frame_id
                $grouped = [];
                foreach ($rows as $row) {
                    $grouped[$row['frame_id']][] = ['id' => (int)$row['tag_id'], 'name' => $row['tag_name']];
                }
                echo json_encode(['status' => 'success', 'data' => $grouped]);
                break;
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}


// ============================================================
// POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Accept both JSON body and form POST
    $body = [];
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) $body = $decoded;
    }
    // Merge form POST if any
    $body = array_merge($_POST, $body);
    $action = $body['action'] ?? '';

    try {
        switch ($action) {

            // ── SAVE / UPSERT TAG DEFINITIONS ────────────────────────
            // Input: { action: 'save_tag_defs', tags: [{id?, name}] }
            // Creates new or updates existing; returns full list.
            case 'save_tag_defs':
                $tagsInput = $body['tags'] ?? [];
                if (!is_array($tagsInput)) {
                    echo json_encode(['status' => 'error', 'message' => 'tags must be array']); exit;
                }

                // INSERT new tag or flip visible=1 if name already exists (re-adding a hidden tag)
                $upsert = $pdo->prepare("
                    INSERT INTO tags (name, show_in_ui) VALUES (?, 1)
                    ON DUPLICATE KEY UPDATE show_in_ui = 1, updated_at = NOW()
                ");
                $update = $pdo->prepare("UPDATE tags SET name = ?, show_in_ui = 1, updated_at = NOW() WHERE id = ?");

                foreach ($tagsInput as $tag) {
                    $name = trim($tag['name'] ?? '');
                    if (!$name) continue;
                    $id = !empty($tag['id']) ? (int)$tag['id'] : null;
                    if ($id) {
                        $update->execute([$name, $id]);
                    } else {
                        $upsert->execute([$name]);
                    }
                }

                // Return only visible tags (what the UI should show)
                $tags = $pdo->query("SELECT id, name, description FROM tags WHERE show_in_ui = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $tags]);
                break;

            // ── SAVE FRAME TAGS ───────────────────────────────────────
            // Input: { action: 'save_frame_tags', tag_id: int, frame_ids: [int,...] }
            // Inserts rows into tags_2_frames (IGNORE duplicates via UNIQUE KEY).
            case 'save_frame_tags':
                $tagId   = (int)($body['tag_id'] ?? 0);
                $frameIds = array_filter(array_map('intval', (array)($body['frame_ids'] ?? [])));

                if (!$tagId)             { echo json_encode(['status' => 'error', 'message' => 'tag_id required']); exit; }
                if (empty($frameIds))    { echo json_encode(['status' => 'error', 'message' => 'frame_ids required']); exit; }

                // Validate tag exists
                $tagRow = $pdo->prepare("SELECT id, name FROM tags WHERE id = ?");
                $tagRow->execute([$tagId]);
                $tag = $tagRow->fetch(PDO::FETCH_ASSOC);
                if (!$tag) {
                    echo json_encode(['status' => 'error', 'message' => "Tag ID $tagId not found"]); exit;
                }

                $insert = $pdo->prepare("INSERT IGNORE INTO tags_2_frames (from_id, to_id) VALUES (?, ?)");
                $count  = 0;
                foreach ($frameIds as $fid) {
                    if ($fid <= 0) continue;
                    $insert->execute([$tagId, $fid]);
                    $count++;
                }

                echo json_encode([
                    'status'   => 'success',
                    'count'    => $count,
                    'tag_name' => $tag['name'],
                    'tag_id'   => $tagId
                ]);
                break;

            // ── REMOVE FRAME TAGS ─────────────────────────────────────
            // Input: { action: 'remove_frame_tags', tag_id: int, frame_ids: [int,...] }
            case 'remove_frame_tags':
                $tagId    = (int)($body['tag_id'] ?? 0);
                $frameIds = array_filter(array_map('intval', (array)($body['frame_ids'] ?? [])));

                if (!$tagId || empty($frameIds)) {
                    echo json_encode(['status' => 'error', 'message' => 'tag_id and frame_ids required']); exit;
                }

                $placeholders = implode(',', array_fill(0, count($frameIds), '?'));
                $params       = array_merge([$tagId], $frameIds);
                $stmt = $pdo->prepare("DELETE FROM tags_2_frames WHERE from_id = ? AND to_id IN ($placeholders)");
                $stmt->execute($params);

                echo json_encode(['status' => 'success', 'deleted' => $stmt->rowCount()]);
                break;

            // ── HIDE TAG FROM UI (sets visible=0, never deletes) ─────
            // Input: { action: 'hide_tag', tag_id: int }
            case 'hide_tag':
                $tagId = (int)($body['tag_id'] ?? 0);
                if (!$tagId) { echo json_encode(['status' => 'error', 'message' => 'tag_id required']); exit; }
                $pdo->prepare("UPDATE tags SET show_in_ui = 0, updated_at = NOW() WHERE id = ?")->execute([$tagId]);
                $tags = $pdo->query("SELECT id, name, description FROM tags WHERE show_in_ui = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $tags]);
                break;

            // ── HIDE ALL TAGS FROM UI (sets all visible=0) ───────────
            // Input: { action: 'hide_all_tags' }
            case 'hide_all_tags':
                $pdo->exec("UPDATE tags SET show_in_ui = 0, updated_at = NOW()");
                echo json_encode(['status' => 'success', 'data' => []]);
                break;

            // delete_tag intentionally not implemented — tags are NEVER deleted from DB via UI

            default:
                echo json_encode(['status' => 'error', 'message' => "Unknown action: $action"]);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}


// ============================================================
// BUILD RICH QUERY — identical to narratives_api.php
// (self-contained copy so files can diverge independently)
// ============================================================
function buildRichQuery($pdo, $docId, $filterData) {
    if (!$docId) return $filterData['text'] ?? '';

    $lore = new LoreAccessService($pdo);
    $lore->loadDoc($docId);

    $freeText   = trim($filterData['text'] ?? '');
    $items      = $filterData['items'] ?? [];
    $queryParts = [];
    $perItem    = [];

    if (!empty($freeText)) $queryParts[] = $freeText;

    if (!empty($items)) {
        $storyEngine = $lore->getStoryEngine();

        foreach ($items as $item) {
            $cat   = $item['cat']  ?? '';
            $name  = $item['name'] ?? '';
            $found = false;
            $desc  = '';

            // EPISODES
            if ($cat === 'episodes') {
                $eps = $storyEngine['episodes'] ?? [];
                foreach ($eps as $ep) {
                    if (!is_array($ep)) continue;
                    $titleKeys = ['title', 'name', 'episode_title', 'ep_title', 'label', 'heading'];
                    $title = '';
                    foreach ($titleKeys as $tk) {
                        if (!empty($ep[$tk]) && is_string($ep[$tk])) { $title = $ep[$tk]; break; }
                    }
                    if (!$title) continue;
                    $match = stripos($title, $name) !== false || stripos($name, $title) !== false;
                    if (!$match) continue;

                    $desc = "Episode: " . $title . ". ";
                    $explicit = [
                        'episode_number' => 'Episode Number', 'logline' => 'Logline',
                        'high_concept' => 'Concept', 'narrative_function' => 'Function',
                        'emotional_arc' => 'Emotional Arc', 'production_notes' => 'Visual/Production',
                        'production_focus' => 'Production Focus', 'description' => null,
                        'act_structure' => 'Structure', 'story_beats' => 'Story Beats', 'themes' => 'Themes',
                    ];
                    $handled = array_merge(array_keys($explicit), $titleKeys);
                    foreach ($explicit as $k => $label) {
                        if (empty($ep[$k])) continue;
                        $v = $ep[$k];
                        if ($label === null)       $desc .= (is_string($v) ? trim($v) : flatten_array_values($v)) . ". ";
                        elseif ($k === 'story_beats' && is_array($v)) $desc .= "Story Beats: " . implode(" | ", $v) . ". ";
                        elseif ($k === 'themes' && is_array($v))      $desc .= "Themes: " . implode(", ", $v) . ". ";
                        elseif ($k === 'act_structure')               $desc .= "Structure: " . (is_array($v) ? implode(", ", $v) : $v) . ". ";
                        elseif (is_string($v))     $desc .= $label . ": " . $v . ". ";
                        elseif (is_numeric($v))    $desc .= $label . ": " . $v . ". ";
                    }
                    foreach ($ep as $k => $v) {
                        if (in_array($k, $handled)) continue;
                        $s = serializeField($k, $v); if ($s) $desc .= $s;
                    }
                    $found = true; break;
                }
            }

            // SCENE HOOKS
            elseif ($cat === 'scene_hooks') {
                $hooks = $storyEngine['scene_hooks'] ?? [];
                foreach ($hooks as $h) {
                    $title = $h['title'] ?? ($h['name'] ?? '');
                    if (!is_array($h) || !$title || stripos($name, $title) === false) continue;
                    $desc = "Scene Hook: " . $title . ". ";
                    $explicit = [
                        'description' => null, 'visual_beat' => 'Visual Beat',
                        'visual_signature' => 'Visual Signature',
                        'narrative_function' => 'Function', 'emotional_tone' => 'Tone',
                    ];
                    $handled = array_merge(array_keys($explicit), ['title', 'name']);
                    foreach ($explicit as $k => $label) {
                        if (empty($h[$k])) continue; $v = $h[$k];
                        if ($label === null) $desc .= (is_string($v) ? trim($v) : flatten_array_values($v)) . ". ";
                        elseif (is_string($v)) $desc .= $label . ": " . $v . ". ";
                    }
                    foreach ($h as $k => $v) {
                        if (in_array($k, $handled)) continue;
                        $s = serializeField($k, $v); if ($s) $desc .= $s;
                    }
                    $found = true; break;
                }
            }

            // ALL OTHER ENTITIES
            else {
                $entity = $lore->getEntity($name);
                if ($entity) {
                    $desc = ucfirst($cat) . ": " . $entity['name'] . ". ";
                    if (!empty($entity['roles'])) $desc .= "Roles: " . implode(", ", (array)$entity['roles']) . ". ";
                    if (!empty($entity['attributes']) && is_array($entity['attributes'])) {
                        $attrExplicit = [
                            'description' => null, 'summary' => 'Summary', 'function' => 'Function',
                            'purpose' => 'Purpose', 'visual' => 'Visual', 'appearance' => 'Appearance',
                            'personality' => 'Personality', 'motivation' => 'Motivation',
                            'backstory' => 'Backstory', 'abilities' => 'Abilities', 'power' => 'Power',
                            'weakness' => 'Weakness', 'allegiance' => 'Allegiance',
                            'location' => 'Location', 'status' => 'Status',
                            'significance' => 'Significance', 'themes' => 'Themes',
                            'production_notes' => 'Production Notes',
                        ];
                        $attrHandled = array_merge(array_keys($attrExplicit), ['id', 'type']);
                        foreach ($attrExplicit as $k => $label) {
                            if (empty($entity['attributes'][$k])) continue;
                            $v = $entity['attributes'][$k];
                            if ($label === null) $desc .= (is_string($v) ? trim($v) : flatten_array_values($v)) . ". ";
                            else { $s = serializeField($label, $v); if ($s) $desc .= $s; }
                        }
                        foreach ($entity['attributes'] as $k => $v) {
                            if (in_array($k, $attrHandled)) continue;
                            $s = serializeField($k, $v); if ($s) $desc .= $s;
                        }
                    }
                    if (!empty($entity['relationships'])) {
                        $relParts = [];
                        foreach (array_slice($entity['relationships'], 0, 4) as $r) {
                            $rel = $r['target'] ?? ''; $typ = $r['type'] ?? '';
                            if ($rel) $relParts[] = $rel . ($typ ? " ($typ)" : "");
                        }
                        if ($relParts) $desc .= "Relations: " . implode(", ", $relParts) . ". ";
                    }
                    if (!empty($entity['timeline'])) {
                        $tlParts = [];
                        foreach (array_slice($entity['timeline'], 0, 3) as $t) {
                            if (!empty($t['text'])) $tlParts[] = $t['text'];
                        }
                        if ($tlParts) $desc .= "History: " . implode(". ", $tlParts) . ". ";
                    }
                    $entityHandled = ['name', 'roles', 'attributes', 'relationships', 'timeline', 'id', 'type', 'cat'];
                    foreach ($entity as $k => $v) {
                        if (in_array($k, $entityHandled)) continue;
                        $s = serializeField($k, $v); if ($s) $desc .= $s;
                    }
                    $found = true;
                }
            }

            if ($found && $desc) {
                $queryParts[] = $desc;
                $itemQuery = ($freeText ? $freeText . "\n\n" : '') . $desc;
                $perItem[] = $itemQuery;
            } elseif (!$found) {
                $queryParts[] = $name;
                $perItem[] = ($freeText ? $freeText . "\n\n" : '') . $name;
            }
        }
    }

    if (count($perItem) > 1) return $perItem;
    return implode("\n\n", array_filter($queryParts));
}
