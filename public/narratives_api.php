<?php
// public/narratives_api.php
// API for Narrative Sequencer
// Update V5: Enhanced Error Logging for AI Enrichment
// Update V6: Injects 'keywords' column from documentations into AI context
// Update V7: Full Logic Restoration & Single-Item Enrichment Fix
// Update V8: Refactor — shared query helpers extracted to narratives_query_helpers.php
// ----------------------------------------------------

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;
use App\Core\VectorContextEngine;
use App\Service\LoreAccessService;

// ==============================================================================
// DEBUG MODE
// ==============================================================================
define('DEBUG_MODE', true);

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// Include Core Classes
require_once __DIR__ . '/../src/Core/AbstractContextEngine.php';
require_once __DIR__ . '/../src/Core/VectorContextEngine.php';
require_once __DIR__ . '/SketchLibrary.php';
require_once __DIR__ . '/SequenceManager.php';
require_once __DIR__ . '/../src/Core/PyApiVectorService.php';
require_once __DIR__ . '/../src/Service/LoreAccessService.php';

// Shared query building helpers (flatten_array_values, serializeField,
// enrichFilterQuery, buildRichQuery) — extracted to avoid duplication
// with taggeranger_api.php.
require_once __DIR__ . '/narratives_query_helpers.php';

// Initialize Core Engines
$engine = new VectorContextEngine($pdo);
$library = new SketchLibrary($pdo);
$seqManager = new SequenceManager($pdo);

// ==============================================================================
// QUERY ENRICHER INITIALIZATION
// ==============================================================================
$queryEnricherConfig  = null;
$queryEnricherService = null;
$queryEnricherSeriesBible = '';
$queryEnricherKeywords = ''; // Holds the keywords list
$enricherInitError = null;

try {
    global $spw;
    if (!isset($spw)) {
        throw new Exception("Showrunner Core (SPW) not initialized");
    }

    $em = $spw->getEntityManager();
    $repo = $em->getRepository(GeneratorConfig::class);
    $queryEnricherConfig = $repo->findOneBy(['configId' => 'filter_query_enricher_v1']);

    if ($queryEnricherConfig) {
        $aiProvider = $spw->getAIProvider();

        if (!class_exists('App\Service\GeneratorService')) {
            throw new Exception("GeneratorService class missing");
        }

        $queryEnricherService = new GeneratorService(
            $aiProvider,
            new SchemaValidator(),
            new ResponseNormalizer(),
            $spw->getFileLogger()
        );
    } else {
        $enricherInitError = "Config 'filter_query_enricher_v1' not found in DB";
    }
} catch (Exception $e) {
    $enricherInitError = $e->getMessage();
    $queryEnricherConfig  = null;
    $queryEnricherService = null;
}

// Ensure no PHP warnings leak into the JSON response
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 1);

header('Content-Type: application/json');


// Handle GET Actions
if (isset($_GET['action'])) {
    try {
        switch ($_GET['action']) {

            // --- MAIN LIBRARY FETCH ---
            case 'fetch_library':
                $page      = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $contextId = isset($_GET['context_id']) && $_GET['context_id'] !== '' ? (int)$_GET['context_id'] : null;

                $customQuery  = null;
                $itemQueries  = [];
                $debugLog     = [];

                // Load world context AND KEYWORDS
                global $queryEnricherSeriesBible, $queryEnricherKeywords;
                if ($contextId) {
                    $bibleStmt = $pdo->prepare("
                        SELECT
                            COALESCE(LEFT(d.desc_short, 800), LEFT(mda.series_bible, 800)) as bible,
                            d.keywords
                        FROM md_doc_analysis mda
                        JOIN documentations d ON d.id = mda.doc_id
                        WHERE mda.doc_id = ?
                    ");
                    $bibleStmt->execute([$contextId]);
                    $row = $bibleStmt->fetch(PDO::FETCH_ASSOC);

                    if ($row) {
                        $queryEnricherSeriesBible = $row['bible'] ?? '';
                        $queryEnricherKeywords    = $row['keywords'] ?? '';
                    }
                }

                $filterData = json_decode($_GET['filter_payload'] ?? 'null', true);

                if ($filterData) {
                    $result = buildRichQuery($pdo, $contextId, $filterData, $debugLog);
                    if (is_array($result)) {
                        $itemQueries = $result;
                        $customQuery = implode("\n\n", $result);
                    } else {
                        $customQuery = $result;
                    }
                } elseif (isset($_GET['custom_query'])) {
                    $customQuery = $_GET['custom_query'];
                }

                // Union mode: run one ranked query per item, merge by score
                if (count($itemQueries) > 1) {
                    $rankedItems = $engine->getRankedItemsMulti($contextId, $itemQueries);
                } else {
                    $rankedItems = $engine->getRankedItems($contextId, $customQuery);
                }

                if (empty($rankedItems) && !$contextId && !$customQuery) {
                    $stmt = $pdo->query("SELECT sa.sketch_id as id, 0 as score FROM sketch_analysis sa JOIN sketches s ON sa.sketch_id = s.id WHERE sa.overall_quality > 0 AND s.searchable = 1 ORDER BY sa.sketch_id DESC LIMIT 2000");
                    $rankedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }

                $result = $library->hydratePage($rankedItems, $page, 50);

                $response = ['status' => 'success'] + $result;

                // Attach debug log to response when DEBUG_MODE is active and filters were used
                if (DEBUG_MODE && !empty($debugLog)) {
                    $response['debug'] = $debugLog;
                }

                echo json_encode($response);
                break;

            // --- SEQUENCE LOADING (GET Fallback) ---
            case 'hydrate_sequence':
                $inputData = [];

                $inputJSON = file_get_contents('php://input');
                if (!empty($inputJSON)) {
                    $decoded = json_decode($inputJSON, true);
                    if (isset($decoded['items'])) {
                        $inputData = $decoded['items'];
                    }
                }

                if (empty($inputData) && isset($_GET['ids'])) {
                    $idsStr    = $_GET['ids'] ?? '';
                    $inputData = array_filter(array_map('intval', explode(',', $idsStr)));
                }

                $data = $library->hydrateSpecificIds($inputData);
                echo json_encode(['status' => 'success', 'data' => $data]);
                break;

            // --- FILTER MODAL: GET CATEGORIES ---
            case 'get_filter_cats':
                $cats = ['episodes', 'scene_hooks', 'characters', 'locations', 'factions', 'artifacts'];
                echo json_encode(['status' => 'success', 'data' => $cats]);
                break;

            // --- FILTER MODAL: GET ITEMS ---
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
                            $label = $item['title'] ?? ($item['name'] ?? ($item['event'] ?? 'Untitled Item'));
                            if ($cat === 'episodes' && isset($item['episode'])) {
                                $label = "Ep " . $item['episode'] . ": " . $label;
                            }
                            $uiItems[] = $label;
                        }
                    }
                } else {
                    $items = $lore->queryEntities($cat);
                    foreach ($items as $i) {
                        $uiItems[] = $i['name'] ?? 'Unknown';
                    }
                }

                $uiItems = array_values(array_unique(array_filter($uiItems)));
                echo json_encode(['status' => 'success', 'data' => $uiItems]);
                break;

            // --- ENTITY PREVIEW (Peek Modal) ---
            case 'get_entity_preview':
                $docId = isset($_GET['doc_id']) && $_GET['doc_id'] !== '' ? (int)$_GET['doc_id'] : null;
                $cat   = $_GET['cat']  ?? '';
                $name  = $_GET['name'] ?? '';

                if (!$docId || !$name) {
                    echo json_encode(['status' => 'error', 'message' => 'Missing doc_id or name']);
                    break;
                }

                $lore = new LoreAccessService($pdo);
                $lore->loadDoc($docId);

                $entityData = null;

                // Episodes / Scene Hooks — fetch from story engine
                if (in_array($cat, ['episodes', 'scene_hooks'])) {
                    $story   = $lore->getStoryEngine();
                    $rawList = $story[$cat] ?? [];
                    foreach ($rawList as $ep) {
                        if (!is_array($ep)) continue;
                        $titleKeys = ['title', 'name', 'episode_title', 'ep_title', 'label', 'heading'];
                        $title = '';
                        foreach ($titleKeys as $tk) {
                            if (!empty($ep[$tk]) && is_string($ep[$tk])) { $title = $ep[$tk]; break; }
                        }
                        if (!$title) continue;
                        $matchLabel = ($cat === 'episodes' && isset($ep['episode'])) ? "Ep " . $ep['episode'] . ": " . $title : $title;
                        if (stripos($name, $title) !== false || stripos($title, $name) !== false || stripos($name, $matchLabel) !== false) {
                            $attrs = [];
                            $skipKeys = ['title','name','episode_title','ep_title','label','heading','raw'];
                            foreach ($ep as $k => $v) {
                                if (!in_array($k, $skipKeys) && $v !== null && $v !== '') {
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
                    // Standard entity — use LoreAccessService index
                    $entity = $lore->getEntity($name);
                    if ($entity) {
                        $entityData = $entity;
                    }
                }

                if ($entityData) {
                    echo json_encode(['status' => 'success', 'data' => $entityData]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Entity not found in lore index', 'data' => null]);
                }
                break;
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}


// --- SAVE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_sequence') {
    try {
        $ids   = json_decode($_POST['sketch_ids'] ?? '[]', true);
        $docId = !empty($_POST['linked_doc_id']) ? $_POST['linked_doc_id'] : null;
        $seqId = !empty($_POST['sequence_id'])   ? $_POST['sequence_id']   : null;

        $newId = $seqManager->save($_POST['name'], $_POST['description'], $ids, $docId, $seqId);
        echo json_encode(['status' => 'success', 'id' => $newId]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}
?>