<?php
// public/kg_import_api.php
// KG Import Bridge API
// -----------------------------------------------------------------------
// Actions:
//   GET  get_filter_items    — list entity names for a doc + category
//   GET  get_entity_preview  — full entity data for the Peek modal
//   POST promote_entity      — AI-author MD content, create kg_nodes row
//                              + kg_node_items back-link to source doc
//
// Reuses:
//   LoreAccessService        — entity resolution (same as auto_narratives_api)
//   md_filter_entity_enricher_v1 — GeneratorConfig for AI MD authoring
//   GeneratorService         — AI invocation layer
//
// DB writes (promote_entity):
//   kg_nodes       — one row per entity promoted
//   kg_node_items  — back-link row: item_type='md_doc', item_id=doc_id
//                    note field carries JSON deep-link metadata:
//                    { focus_type, focus_entity, doc_id }
//                    so kg_view.php can build a precise view_curated_docs.php URL
// -----------------------------------------------------------------------

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require_once __DIR__ . '/../src/Service/LoreAccessService.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;
use App\Service\LoreAccessService;

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// -----------------------------------------------------------------------
// HELPERS
// -----------------------------------------------------------------------

/**
 * Flatten any value to a plain string (for building AI prompts).
 */
function kgi_flatten($value): string {
    if ($value === null || $value === false || $value === '') return '';
    if (is_bool($value))    return $value ? 'true' : 'false';
    if (is_numeric($value)) return (string)$value;
    if (is_string($value))  return trim($value);
    if (is_array($value)) {
        $parts = [];
        array_walk_recursive($value, function ($v) use (&$parts) {
            if (is_scalar($v) && trim((string)$v) !== '') {
                $parts[] = trim((string)$v);
            }
        });
        return implode(', ', $parts);
    }
    return '';
}

/**
 * Build a rich text block from a LoreAccessService entity for AI prompt input.
 */
function kgi_entity_to_text(array $entity, string $cat): string {
    $parts = [];

    $name = $entity['name'] ?? 'Unknown';
    $parts[] = ucfirst($cat) . ': ' . $name;

    if (!empty($entity['roles'])) {
        $parts[] = 'Roles: ' . implode(', ', (array)$entity['roles']);
    }

    if (!empty($entity['attributes']) && is_array($entity['attributes'])) {
        $longFields = [
            'description', 'summary', 'backstory', 'purpose', 'function',
            'personality', 'motivation', 'visual', 'appearance', 'significance',
            'production_notes', 'logline', 'act_structure',
        ];
        foreach ($entity['attributes'] as $k => $v) {
            $flat = kgi_flatten($v);
            if ($flat === '') continue;
            if (in_array($k, $longFields, true)) {
                $parts[] = ucfirst(str_replace('_', ' ', $k)) . ': ' . $flat;
            } else {
                $parts[] = ucfirst(str_replace('_', ' ', $k)) . ': ' . $flat;
            }
        }
    }

    if (!empty($entity['relationships'])) {
        $relParts = [];
        foreach (array_slice($entity['relationships'], 0, 5) as $r) {
            $target = $r['target'] ?? '';
            $type   = $r['type']   ?? '';
            if ($target) $relParts[] = $target . ($type ? " ($type)" : '');
        }
        if ($relParts) $parts[] = 'Relationships: ' . implode(', ', $relParts);
    }

    if (!empty($entity['timeline'])) {
        $tlParts = [];
        foreach (array_slice($entity['timeline'], 0, 3) as $t) {
            if (!empty($t['text'])) $tlParts[] = $t['text'];
        }
        if ($tlParts) $parts[] = 'History: ' . implode('. ', $tlParts);
    }

    if (!empty($entity['aliases'])) {
        $parts[] = 'Also known as: ' . implode(', ', (array)$entity['aliases']);
    }

    return implode("\n", array_filter($parts));
}

/**
 * Build entity text for episodes / scene_hooks (story-engine items).
 */
function kgi_story_item_to_text(array $item, string $cat): string {
    $parts = [];

    $titleKeys = ['title', 'name', 'episode_title', 'ep_title', 'label', 'heading'];
    $title = '';
    foreach ($titleKeys as $tk) {
        if (!empty($item[$tk]) && is_string($item[$tk])) {
            $title = $item[$tk];
            break;
        }
    }
    if ($title) $parts[] = ucfirst($cat) . ': ' . $title;

    $skipKeys = array_merge($titleKeys, ['raw']);
    foreach ($item as $k => $v) {
        if (in_array($k, $skipKeys, true)) continue;
        $flat = kgi_flatten($v);
        if ($flat === '') continue;
        $parts[] = ucfirst(str_replace('_', ' ', $k)) . ': ' . $flat;
    }

    return implode("\n", array_filter($parts));
}

/**
 * Send data to AI and get back a markdown-formatted node content string.
 * Uses md_filter_entity_enricher_v1. Falls back gracefully if AI fails.
 *
 * Updated: strict JSON parsing for a single key "enriched_entity".
 * Also accepts the case where enriched_entity is an object containing a 'content'
 * (or 'markdown' / 'md') property and will extract that as the MD string.
 */
function kgi_author_md_content(
    string $entityText,
    string $entityName,
    string $cat,
    string $docName,
    ?GeneratorService $generatorService,
    ?object $enricherConfig
): string {
    $fallback  = "# {$entityName}\n\n";
    $fallback .= "> **Source:** {$docName} · **Category:** {$cat}\n\n";
    $fallback .= "---\n\n";
    $fallback .= $entityText . "\n";

    if (!$generatorService || !$enricherConfig) {
        return $fallback;
    }

    $prompt  = "=== ENTITY TO DOCUMENT ===\n";
    $prompt .= "Name: {$entityName}\n";
    $prompt .= "Category: {$cat}\n";
    $prompt .= "Source Document: {$docName}\n\n";
    $prompt .= $entityText . "\n\n";

    try {
        $res    = $generatorService->generate($enricherConfig, ['entity_name' => $prompt]);
        $rawOut = is_object($res) && method_exists($res, 'getRawResponse')
            ? $res->getRawResponse()
            : (string)$res;

        // Try to extract a JSON object from the response
        $decoded = null;
        $jsonFound = false;
        $firstBrace = strpos($rawOut, '{');
        $lastBrace  = strrpos($rawOut, '}');

        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $possibleJson = substr($rawOut, $firstBrace, $lastBrace - $firstBrace + 1);
            $possibleJson = trim($possibleJson);
            $maybe = json_decode($possibleJson, true);
            if (is_array($maybe)) {
                $decoded = $maybe;
                $jsonFound = true;
            }
        }

        // If no JSON block found, try decoding the whole output
        if (!$jsonFound) {
            $maybe2 = json_decode(trim($rawOut), true);
            if (is_array($maybe2)) {
                $decoded = $maybe2;
                $jsonFound = true;
            }
        }

        // Helper to extract markdown from a variety of enriched_entity shapes
        $extract_md_from_enriched = function ($ee) use ($entityName) {
            // If it's a string that itself contains JSON, try decode it
            if (is_string($ee)) {
                $eeTrim = trim($ee);
                if ((str_starts_with($eeTrim, '{') || str_starts_with($eeTrim, '['))) {
                    $inner = json_decode($eeTrim, true);
                    if (is_array($inner)) {
                        $ee = $inner;
                    }
                } else {
                    return $eeTrim;
                }
            }

            // If it's an array/object, look for common content fields
            if (is_array($ee)) {
                $candidates = ['content', 'markdown', 'md', 'node_content', 'body'];
                foreach ($candidates as $k) {
                    if (isset($ee[$k]) && is_string($ee[$k]) && trim($ee[$k]) !== '') {
                        return trim($ee[$k]);
                    }
                }
                // As a fallback, if there's exactly one string-valued field, return it
                $stringFields = array_filter($ee, function($v){ return is_string($v) && trim($v) !== ''; });
                if (count($stringFields) === 1) {
                    return trim(array_values($stringFields)[0]);
                }
            }

            return null;
        };

        // Strict JSON handling when decoded JSON present
        if ($jsonFound && is_array($decoded)) {
            if (array_key_exists('enriched_entity', $decoded)) {
                $ee = $decoded['enriched_entity'];

                // If enriched_entity is string -> possibly direct MD or JSON string
                if (is_string($ee)) {
                    $maybeMd = $extract_md_from_enriched($ee);
                    if (is_string($maybeMd) && trim($maybeMd) !== '') {
                        return trim($maybeMd);
                    }
                }

                // If enriched_entity is an array/object -> look for content/markdown
                if (is_array($ee)) {
                    $md = $extract_md_from_enriched($ee);
                    if (is_string($md) && trim($md) !== '') {
                        return trim($md);
                    }
                }

                // If we reach here, enriched_entity exists but we couldn't find a usable MD
                error_log("[kg_import_api] 'enriched_entity' present but no usable markdown found for '{$entityName}'.");
                // continue to legacy fallbacks below
            }

            // Backwards compatibility for legacy key 'enriched_query'
            if (!empty($decoded['enriched_query']) && is_string($decoded['enriched_query']) && trim($decoded['enriched_query']) !== '') {
                error_log("[kg_import_api] Received JSON with legacy key 'enriched_query' for '{$entityName}'. Prefer single-key 'enriched_entity'.");
                return trim($decoded['enriched_query']);
            }

            // If JSON present but not the expected structure, log keys for debugging
            $keys = array_keys($decoded);
            error_log("[kg_import_api] AI returned JSON but did not match expected enriched_entity schema for '{$entityName}'. Keys: " . implode(', ', $keys));
        }

        // Fallback: accept raw markdown (strip fences) if substantial
        $stripped = preg_replace('/^```(?:markdown|md)?\s*/i', '', trim($rawOut));
        $stripped = preg_replace('/\s*```$/', '', $stripped);
        $stripped = trim($stripped);

        if (strlen($stripped) > 80 && str_contains($stripped, "\n")) {
            error_log("[kg_import_api] Using non-JSON markdown fallback for '{$entityName}'. Consider returning JSON {\"enriched_entity\": {\"content\":\"...\"}} or {\"enriched_entity\":\"...\"}.");
            return $stripped;
        }

        // Final attempt: if stripped looks like JSON with enriched_entity inside, decode and retry
        $tryDecoded = json_decode($stripped, true);
        if (is_array($tryDecoded) && !empty($tryDecoded['enriched_entity'])) {
            $mdTry = $extract_md_from_enriched($tryDecoded['enriched_entity']);
            if (is_string($mdTry) && trim($mdTry) !== '') {
                return trim($mdTry);
            }
        }

    } catch (Exception $e) {
        error_log("[kg_import_api] AI authoring failed for '{$entityName}': " . $e->getMessage());
    }

    return $fallback;
}

// -----------------------------------------------------------------------
// SERVICES
// -----------------------------------------------------------------------

$generatorService = null;
$enricherConfig   = null;

try {
    global $spw;
    $em             = $spw->getEntityManager();
    $repo           = $em->getRepository(GeneratorConfig::class);
    $enricherConfig = $repo->findOneBy(['configId' => 'md_filter_entity_enricher_v1']);

    if ($enricherConfig) {
        $aiProvider       = $spw->getAIProvider();
        $generatorService = new GeneratorService(
            $aiProvider,
            new SchemaValidator(),
            new ResponseNormalizer(),
            $spw->getFileLogger()
        );
    }
} catch (Exception $e) {
    error_log('[kg_import_api] Service init error: ' . $e->getMessage());
}

// -----------------------------------------------------------------------
// ROUTER
// -----------------------------------------------------------------------

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ============================
// GET: get_filter_items
// ============================
if ($method === 'GET' && $action === 'get_filter_items') {
    $docId = isset($_GET['doc_id']) && $_GET['doc_id'] !== '' ? (int)$_GET['doc_id'] : null;
    $cat   = $_GET['cat'] ?? '';

    if (!$docId || !$cat) {
        echo json_encode(['status' => 'error', 'message' => 'Missing doc_id or cat']);
        exit;
    }

    try {
        $lore = new LoreAccessService($pdo);
        $lore->loadDoc($docId);

        $uiItems = [];

        if (in_array($cat, ['episodes', 'scene_hooks'], true)) {
            $story   = $lore->getStoryEngine();
            $rawList = $story[$cat] ?? [];
            foreach ($rawList as $item) {
                if (is_array($item)) {
                    $titleKeys = ['title', 'name', 'episode_title', 'ep_title', 'label', 'heading'];
                    foreach ($titleKeys as $tk) {
                        if (!empty($item[$tk]) && is_string($item[$tk])) {
                            $uiItems[] = $item[$tk];
                            break;
                        }
                    }
                } elseif (is_string($item)) {
                    $uiItems[] = $item;
                }
            }
        } else {
            $entities = $lore->queryEntities($cat);
            foreach ($entities as $ent) {
                $name = $ent['name'] ?? '';
                if ($name) $uiItems[] = $name;
            }
        }

        $uiItems = array_values(array_unique(array_filter($uiItems)));
        sort($uiItems);

        echo json_encode(['status' => 'success', 'data' => $uiItems]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================
// GET: get_entity_preview
// ============================
if ($method === 'GET' && $action === 'get_entity_preview') {
    $docId = isset($_GET['doc_id']) && $_GET['doc_id'] !== '' ? (int)$_GET['doc_id'] : null;
    $cat   = $_GET['cat']  ?? '';
    $name  = $_GET['name'] ?? '';

    if (!$docId || !$name) {
        echo json_encode(['status' => 'error', 'message' => 'Missing doc_id or name']);
        exit;
    }

    try {
        $lore = new LoreAccessService($pdo);
        $lore->loadDoc($docId);

        $entityData = null;

        if (in_array($cat, ['episodes', 'scene_hooks'], true)) {
            $story   = $lore->getStoryEngine();
            $rawList = $story[$cat] ?? [];

            foreach ($rawList as $ep) {
                if (!is_array($ep)) continue;

                $titleKeys = ['title', 'name', 'episode_title', 'ep_title', 'label', 'heading'];
                $title = '';
                foreach ($titleKeys as $tk) {
                    if (!empty($ep[$tk]) && is_string($ep[$tk])) {
                        $title = $ep[$tk];
                        break;
                    }
                }
                if (!$title) continue;

                $matchLabel = ($cat === 'episodes' && isset($ep['episode']))
                    ? 'Ep ' . $ep['episode'] . ': ' . $title
                    : $title;

                if (
                    stripos($name, $title)     !== false ||
                    stripos($title, $name)      !== false ||
                    stripos($name, $matchLabel) !== false
                ) {
                    $skipKeys = ['title','name','episode_title','ep_title','label','heading','raw'];
                    $attrs    = [];
                    foreach ($ep as $k => $v) {
                        if (!in_array($k, $skipKeys, true) && $v !== null && $v !== '') {
                            $attrs[$k] = $v;
                        }
                    }
                    $entityData = [
                        'name'          => $matchLabel ?: $title,
                        'roles'         => [],
                        'aliases'       => [],
                        'attributes'    => $attrs,
                        'relationships' => [],
                        'timeline'      => [],
                    ];
                    break;
                }
            }
        } else {
            $entity = $lore->getEntity($name);
            if ($entity) {
                $entityData = $entity;
            }
        }

        if ($entityData) {
            echo json_encode(['status' => 'success', 'data' => $entityData]);
        } else {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Entity not found in lore index',
                'data'    => null,
            ]);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================
// POST: promote_entity
// ============================
if ($method === 'POST' && $action === 'promote_entity') {
    $docId      = !empty($_POST['doc_id'])      ? (int)$_POST['doc_id']  : null;
    $docName    = trim($_POST['doc_name']        ?? '');
    $entityName = trim($_POST['entity_name']     ?? '');
    $entityCat  = trim($_POST['entity_cat']      ?? '');
    $nodeType   = trim($_POST['node_type']       ?? 'note');
    $categoryId = !empty($_POST['category_id'])  ? (int)$_POST['category_id'] : null;

    if (!$docId || !$entityName || !$entityCat) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields: doc_id, entity_name, entity_cat']);
        exit;
    }

    // ------------------------------------------------------------------
    // 1. Resolve entity lore
    // ------------------------------------------------------------------
    try {
        $lore = new LoreAccessService($pdo);
        $lore->loadDoc($docId);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'LoreAccessService error: ' . $e->getMessage()]);
        exit;
    }

    $entityText = '';

    if (in_array($entityCat, ['episodes', 'scene_hooks'], true)) {
        $story   = $lore->getStoryEngine();
        $rawList = $story[$entityCat] ?? [];

        foreach ($rawList as $ep) {
            if (!is_array($ep)) continue;

            $titleKeys = ['title', 'name', 'episode_title', 'ep_title', 'label', 'heading'];
            $title = '';
            foreach ($titleKeys as $tk) {
                if (!empty($ep[$tk]) && is_string($ep[$tk])) {
                    $title = $ep[$tk];
                    break;
                }
            }
            if (!$title) continue;

            if (
                stripos($title, $entityName) !== false ||
                stripos($entityName, $title) !== false
            ) {
                $entityText = kgi_story_item_to_text($ep, $entityCat);
                break;
            }
        }

        if (!$entityText) {
            $entityText = ucfirst($entityCat) . ': ' . $entityName;
        }

    } else {
        $entity = $lore->getEntity($entityName);
        if ($entity) {
            $entityText = kgi_entity_to_text($entity, $entityCat);
        } else {
            $entityText = ucfirst($entityCat) . ': ' . $entityName;
        }
    }

    // ------------------------------------------------------------------
    // 2. AI-author Markdown content
    // ------------------------------------------------------------------
    $mdContent = kgi_author_md_content(
        $entityText,
        $entityName,
        $entityCat,
        $docName ?: 'Unknown Document',
        $generatorService,
        $enricherConfig
    );

    // ------------------------------------------------------------------
    // 3. Write to kg_nodes
    // ------------------------------------------------------------------
    try {
        $validNodeTypes = [
            'note','relationship','character','location','event','concept','arc','episode'
        ];
        if (!in_array($nodeType, $validNodeTypes, true)) {
            $nodeType = 'note';
        }

        $stmt = $pdo->prepare("
            INSERT INTO kg_nodes
                (name, node_type, content, description, keywords, category_id, status, sort_order)
            VALUES
                (:name, :node_type, :content, :description, :keywords, :category_id, 'active', 0)
        ");
        $stmt->execute([
            ':name'        => $entityName,
            ':node_type'   => $nodeType,
            ':content'     => $mdContent,
            ':description' => mb_substr($entityText, 0, 250),
            ':keywords'    => $entityCat . ', ' . ($docName ? $docName . ', ' : '') . 'imported',
            ':category_id' => $categoryId,
        ]);

        $nodeId = (int)$pdo->lastInsertId();

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'DB insert (kg_nodes) failed: ' . $e->getMessage()]);
        exit;
    }

    // ------------------------------------------------------------------
    // 4. Write back-link to kg_node_items with deep-link metadata
    //
    //    item_type  = 'md_doc'
    //    item_id    = doc_id
    //    item_label = entity name (for display in kg_view linked-items drawer)
    //    relationship = 'source_document'
    //    note       = JSON blob carrying deep-link params so kg_view.php can
    //                 build: view_curated_docs.php?doc_id=X&embed=1
    //                        &focus_type=Y&focus_entity=Z
    //                 Fields: { focus_type, focus_entity, doc_name }
    // ------------------------------------------------------------------
    $deepLinkMeta = json_encode([
        'focus_type'   => $entityCat,
        'focus_entity' => $entityName,
        'doc_name'     => $docName ?: '',
    ], JSON_UNESCAPED_UNICODE);

    try {
        $stmt2 = $pdo->prepare("
            INSERT INTO kg_node_items
                (node_id, item_type, item_id, item_label, relationship, note, sort_order)
            VALUES
                (:node_id, 'md_doc', :item_id, :item_label, 'source_document', :note, 0)
        ");
        $stmt2->execute([
            ':node_id'    => $nodeId,
            ':item_id'    => $docId,
            ':item_label' => $entityName,   // entity name — shown in linked-items drawer
            ':note'       => $deepLinkMeta, // JSON deep-link metadata
        ]);
    } catch (Exception $e) {
        // Non-fatal — node already created
        error_log('[kg_import_api] kg_node_items insert failed for node ' . $nodeId . ': ' . $e->getMessage());
    }

    // ------------------------------------------------------------------
    // 5. Return success
    // ------------------------------------------------------------------
    echo json_encode([
        'status'  => 'success',
        'node_id' => $nodeId,
        'message' => 'Entity promoted to KG node #' . $nodeId,
        'ai_used' => ($generatorService && $enricherConfig) ? true : false,
    ]);
    exit;
}

// -----------------------------------------------------------------------
// Unknown action
// -----------------------------------------------------------------------
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
exit;