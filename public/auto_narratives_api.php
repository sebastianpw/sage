<?php
// public/auto_narratives_api.php
// Showrunner V10 - Auto-Narrative Laboratory API
// ----------------------------------------------------------------
// VERSION: V10.1 (Enriched Query Integration)
//
// ARCHITECTURE CHANGE: Stage 0 (Chroma lore collection query) eliminated.
//
// The rich entity context is now sourced DIRECTLY from MariaDB via
// LoreAccessService / getStoryEngine() — the same data that was already
// being used in buildRichQueryFromFilter(). This eliminates the lossy
// Chroma round-trip that was stripping episode loglines, faction details,
// and character attributes down to thin stubs.
//
// UPDATE V10.1: Added AI Query Enrichment (filter_query_enricher_v1)
// to transform raw MariaDB entity dumps into dense narrative prose using
// the 'keywords' column and 'desc_short' context.
//
// NEW TWO-STAGE PIPELINE:
//
// Stage 1 — SKETCH POOL (per shot)
//   Query sage_sketches_nu using the ENRICHED BASE QUERY (built from full
//   MariaDB entity data + AI Prose Transformation). Returns ~200 unique 
//   sketch IDs. Nothing outside this pool can be selected.
//
// Stage 2 — NARRATIVE RE-RANKING (within pool only)
//   Three queryJson calls (Primary, Narrative, Style), each constrained
//   to the Stage 1 pool via where: {sketch_id: {$in: [...]}}
//   Score, weight, apply novelty penalty, structural constraints.
//
// Query sources by mode:
//   Precision mode: Enriched RichQuery (AI-rewritten entity lore)
//   Wide mode:      loreBaseContext + worldVisualDna from md_doc_analysis
//
// PRESERVED from V9/V10:
//   - Full 3-vector scoring (Primary/Narrative/Style/Novelty)
//   - AI reasoning for shot intent (askAiForNextShot)
//   - Structural constraints (position, standalone, energy)
//   - Beat labels (ACT 1/2/3 modifiers)
//   - Debug mode with full logging
//   - Promote / Delete / List actions
//   - SketchLibrary hydration
//   - SequenceManager promotion
//   - LoreAccessService integration (now primary lore source)
//   - decodeField() helper
//   - buildRichQueryFromFilter() (now feeds Stage 1 directly)
//   - Keyword injection for precision entity names
//   - Two-pass selection (strict + relaxed constraints)
//   - Unrestricted fallback
// V10.2: Added get_entity_preview GET action for Peek modal
// ----------------------------------------------------------------

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;

require_once __DIR__ . '/../src/Core/VectorContextEngine.php';
require_once __DIR__ . '/../src/Core/PyApiVectorService.php';
require_once __DIR__ . '/../src/Service/LoreAccessService.php';
require_once __DIR__ . '/SketchLibrary.php';
require_once __DIR__ . '/SequenceManager.php';

use App\Core\PyApiVectorService;
use App\Service\LoreAccessService;


// ============================================================
// HELPER: Flatten nested arrays to a string
// ============================================================
function flatten_array_values_auto($array) {
    $flat = [];
    array_walk_recursive($array, function($a) use (&$flat) {
        if (is_scalar($a)) {
            $flat[] = trim((string)$a);
        }
    });
    return implode(", ", array_filter($flat));
}


// ============================================================
// AUTO DIRECTOR CLASS - V10 (Two-Stage Pipeline)
// ============================================================

class AutoDirector {

    private $pdo;
    private $vectorService;
    private $generatorService = null;
    
    // AI Configurations
    private $directorConfig = null;      // For Next Shot Logic (auto_director_v1)
    private $enricherConfig = null;      // For Query Enrichment (filter_query_enricher_v1)
    
    public  $logs = [];

    private const SKETCH_COLLECTION = 'sage_sketches_nu';

    // Lore context strings (built once on load from md_doc_analysis)
    private $loreBaseContext = "";
    private $worldVisualDna  = "";
    private $contextAnchor   = "";
    
    // New V10.1: Context for Enricher
    private $seriesBible     = "";
    private $worldKeywords   = "";

    // Precision mode (entities selected in filter)
    private $precisionMode        = false;
    private $precisionEntityNames = [];

    // The rich query string — built once from MariaDB, reused + enriched per shot
    private $richBaseQuery = "";

    // Raw filter data preserved for per-shot re-enrichment
    private $filterItems     = [];
    private $freeText        = "";
    private $docId           = null;
    private $docName         = "";

    // Per-item rich queries — one string per filter item for union pool building
    // Wide mode: single entry. Precision mode: one entry per selected entity.
    private $perItemQueries  = [];

    // LoreAccessService instance — loaded once, reused per shot
    private $loreService = null;

    private $useAi     = false;
    private $debugMode = false;

    // Scoring weights
    const W_PRIMARY   = 0.40;
    const W_NARRATIVE = 0.35;
    const W_STYLE     = 0.15;
    const W_NOVELTY   = 0.10;

    // Seed temperature default (0.0 = fully deterministic, 1.0 = fully random)
    // Overridden per-request via filter_payload 'temperature' field
    const SEED_TEMPERATURE_DEFAULT = 0.35;

    // Step temperature default — controls variety of each subsequent shot
    // Overridden per-request via filter_payload 'step_temperature' field
    const STEP_TEMPERATURE_DEFAULT = 0.25;

    // Pool size targets
    const POOL_STAGE1_TARGET  = 200;   // Sketch IDs from Stage 1
    const POOL_STAGE1_QUERY_N = 250;   // n_results for Stage 1 sketch query (pre-dedup)

    private $lastPicks        = [];
    private $seedTemperature  = self::SEED_TEMPERATURE_DEFAULT;
    private $stepTemperature  = self::STEP_TEMPERATURE_DEFAULT;

    private $debugData = [
        'seed_query'     => '',
        'ai_calls'       => [],
        'vector_queries' => [],
        'enrichment_log' => [] // New debug section
    ];

    public function __construct($pdo, $useAi = false, $debugMode = false) {
        $this->pdo           = $pdo;
        $this->vectorService = new PyApiVectorService();
        
        // This flag now ONLY controls the "Next Shot Reasoning" (Director)
        $this->useAi         = $useAi; 
        $this->debugMode     = $debugMode;

        // Always attempt to init services (for the Enricher),
        // regardless of whether the Director AI is enabled.
        $this->initGeneratorService();
    }

    // --------------------------------------------------------
    // DECODE FIELD HELPER
    // --------------------------------------------------------
    private function decodeField($val): array {
        if (is_array($val)) return $val;
        if (is_string($val) && $val !== '') return json_decode($val, true) ?: [];
        return [];
    }

    // --------------------------------------------------------
    // INIT GENERATOR SERVICE
    // --------------------------------------------------------
    // --------------------------------------------------------
    // INIT GENERATOR SERVICE
    // --------------------------------------------------------
    private function initGeneratorService() {
        try {
            global $spw;
            $em   = $spw->getEntityManager();
            $repo = $em->getRepository(GeneratorConfig::class);
            
            // 1. Load Director Config (Next Shot Logic)
            $this->directorConfig = $repo->findOneBy(['configId' => 'auto_director_v1']);
            
            // 2. Load Enricher Config (Query Optimization)
            $this->enricherConfig = $repo->findOneBy(['configId' => 'filter_query_enricher_v1']);

            // If NEITHER exists, we can't do anything
            if (!$this->directorConfig && !$this->enricherConfig) {
                $this->useAi = false; // Force Director off
                return;
            }

            // If Director config is missing but we asked for it, disable it
            if ($this->useAi && !$this->directorConfig) {
                $this->log("Warning: 'auto_director_v1' not found. Shot Reasoning disabled.");
                $this->useAi = false;
            }

            $aiProvider = $spw->getAIProvider();
            $this->generatorService = new GeneratorService(
                $aiProvider,
                new SchemaValidator(),
                new ResponseNormalizer(),
                $spw->getFileLogger()
            );
            
            $status = [];
            if ($this->useAi) {
                $status[] = "Director: ON";
            } else {
                $status[] = "Director: OFF";
            }
            
            if ($this->enricherConfig) {
                $status[] = "Enricher: ON";
            } else {
                $status[] = "Enricher: OFF";
            }
            
            $this->log("AI Services: " . implode(" | ", $status));

        } catch (Exception $e) {
            $this->log("AI init error: " . $e->getMessage());
            $this->useAi = false;
            $this->enricherConfig = null; // Disable enricher on error
        }
    }

    private function log($msg) {
        $this->logs[] = $msg;
    }


    // ============================================================
    // UNIVERSAL FIELD SERIALIZER
    // ============================================================
    // Converts any field key+value to a meaningful string fragment.
    // Beat detection: pure string arrays with avg item length > 40
    // are treated as narrative beats regardless of key name.
    // ============================================================

    private function serializeEntityField(string $key, $value): string {
        if (is_string($value)) {
            $value = trim($value);
            if (strlen($value) <= 3 || strlen($value) > 800) return '';
            return ucfirst($key) . ": " . $value . ". ";
        }

        if (is_numeric($value)) {
            return ucfirst($key) . ": " . $value . ". ";
        }

        if (is_array($value) && !empty($value)) {
            $stringItems = array_values(array_filter($value, 'is_string'));

            if (count($stringItems) === count($value)) {
                // Pure string array — detect beats vs tags by average item length
                if (count($stringItems) >= 2) {
                    $avgLen = array_sum(array_map('strlen', $stringItems)) / count($stringItems);
                    if ($avgLen > 40) {
                        // Narrative beats — pipe-separated for clarity
                        return "Beats (" . $key . "): " . implode(" | ", $stringItems) . ". ";
                    } else {
                        // Short tags/keywords — comma-separated
                        return ucfirst($key) . ": " . implode(", ", $stringItems) . ". ";
                    }
                }
                return ucfirst($key) . ": " . implode(", ", $stringItems) . ". ";
            }

            // Mixed or nested array — flatten
            $flat = flatten_array_values_auto($value);
            if ($flat) return ucfirst($key) . ": " . $flat . ". ";
        }

        return '';
    }

    // ============================================================
    // AI ENRICHMENT HELPER (Ported from Manual API)
    // ============================================================
    // ============================================================
    // AI ENRICHMENT HELPER (Ported from Manual API)
    // ============================================================
    private function enrichFilterQuery(string $rawQuery, string $seriesBible, string $keywords): string {
        // CHANGED: We do NOT check $this->useAi here. 
        // We only check if the Service and Enricher Config are loaded.
        if (!$this->generatorService || !$this->enricherConfig) {
            return $rawQuery;
        }

        // Prepare Input
        $inputText = "=== FILTER ITEM ===\n" . $rawQuery;
        
        if (!empty($keywords)) {
            $inputText .= "\n\n=== MANDATORY WORLD VOCABULARY ===\n" . $keywords;
        }

        if (!empty($seriesBible)) {
            $inputText .= "\n\n=== WORLD CONTEXT (Flavor/Tone Only) ===\n" . $seriesBible;
        }

        try {
            $start = microtime(true);
            $res = $this->generatorService->generate(
                $this->enricherConfig,
                ['entity_name' => $inputText]
            );

            $rawOut = is_object($res) && method_exists($res, 'getRawResponse')
                ? $res->getRawResponse()
                : (string)$res;

            $stripped = trim(str_replace(['```json', '```'], '', $rawOut));
            $decoded  = json_decode($stripped, true);
            $duration = round((microtime(true) - $start) * 1000) . 'ms';

            if (!empty($decoded['enriched_query']) && is_string($decoded['enriched_query'])) {
                if ($this->debugMode) {
                    $this->debugData['enrichment_log'][] = [
                        'status'   => 'success',
                        'duration' => $duration,
                        'original' => substr($rawQuery, 0, 100) . '...',
                        'enriched' => substr($decoded['enriched_query'], 0, 100) . '...'
                    ];
                }
                return trim($decoded['enriched_query']);
            }

            throw new Exception("JSON missing 'enriched_query'");

        } catch (Exception $e) {
            if ($this->debugMode) {
                $this->debugData['enrichment_log'][] = [
                    'status' => 'failed',
                    'error'  => $e->getMessage()
                ];
            }
            return $rawQuery; // Fallback to raw on failure
        }
    }


    // ============================================================
    // RICH QUERY BUILDER — from MariaDB via LoreAccessService
    // ============================================================
    // Builds the richest possible text from selected filter items,
    // then passes it through the AI Enricher.
    // ============================================================

    private function buildRichQueryFromFilter($filterItems, $freeText, $docId) {
        $queryParts = [];

        if (!empty($freeText)) {
            $queryParts[] = "Director Instruction: " . $freeText;
        }

        if (empty($filterItems)) {
            // Wide mode — world context + visual DNA is the full query
            if (!empty($this->loreBaseContext)) {
                $queryParts[] = $this->loreBaseContext;
            }
            if (!empty($this->worldVisualDna)) {
                $queryParts[] = $this->worldVisualDna;
            }
            return implode("\n\n", array_filter($queryParts));
        }

        // Precision mode — entity lore IS the context.

        // Load LoreAccessService if not already loaded
        if (!$this->loreService && $docId) {
            try {
                $this->loreService = new LoreAccessService($this->pdo);
                $this->loreService->loadDoc((int)$docId);
                $this->log("LoreAccessService loaded for doc $docId");
            } catch (Exception $e) {
                $this->log("LoreAccessService error: " . $e->getMessage());
            }
        }

        $storyEngine = $this->loreService ? $this->loreService->getStoryEngine() : [];

        foreach ($filterItems as $item) {
            $cat  = $item['cat']  ?? '';
            $name = $item['name'] ?? '';
            if (!$name) continue;

            $this->precisionEntityNames[] = $name;
            $found = false;
            $desc  = '';

            // --------------------------------------------------------
            // EPISODES
            // --------------------------------------------------------
            if ($cat === 'episodes') {
                $eps = $storyEngine['episodes'] ?? [];
                foreach ($eps as $ep) {
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

                    $match = stripos($title, $name) !== false
                          || stripos($name, $title) !== false;
                    if (!$match) continue;

                    $desc = "Episode: " . $title . ". ";

                    $explicit = [
                        'episode_number'     => 'Episode Number',
                        'logline'            => 'Logline',
                        'high_concept'       => 'Concept',
                        'narrative_function' => 'Function',
                        'emotional_arc'      => 'Emotional Arc',
                        'production_notes'   => 'Visual/Production',
                        'production_focus'   => 'Production Focus',
                        'description'        => null,
                        'act_structure'      => 'Structure',
                        'story_beats'        => 'Story Beats',
                        'themes'             => 'Themes',
                    ];
                    $handled = array_merge(array_keys($explicit), $titleKeys);

                    foreach ($explicit as $k => $label) {
                        if (empty($ep[$k])) continue;
                        $v = $ep[$k];
                        if ($label === null) {
                            $desc .= (is_string($v) ? trim($v) : flatten_array_values_auto($v)) . ". ";
                        } elseif ($k === 'story_beats' && is_array($v)) {
                            $desc .= "Story Beats: " . implode(" | ", $v) . ". ";
                        } elseif ($k === 'themes' && is_array($v)) {
                            $desc .= "Themes: " . implode(", ", $v) . ". ";
                        } elseif ($k === 'act_structure') {
                            $desc .= "Structure: " . (is_array($v) ? implode(", ", $v) : $v) . ". ";
                        } elseif (is_string($v)) {
                            $desc .= $label . ": " . $v . ". ";
                        } elseif (is_numeric($v)) {
                            $desc .= $label . ": " . $v . ". ";
                        }
                    }

                    foreach ($ep as $k => $v) {
                        if (in_array($k, $handled)) continue;
                        $serialized = $this->serializeEntityField($k, $v);
                        if ($serialized) $desc .= $serialized;
                    }

                    $this->log("Episode lore loaded: '$title' (" . strlen($desc) . " chars)");
                    $found = true;
                    break;
                }
            }

            // --------------------------------------------------------
            // SCENE HOOKS
            // --------------------------------------------------------
            elseif ($cat === 'scene_hooks') {
                $hooks = $storyEngine['scene_hooks'] ?? [];
                foreach ($hooks as $h) {
                    $title = $h['title'] ?? ($h['name'] ?? '');
                    if (!is_array($h) || !$title || stripos($name, $title) === false) continue;

                    $desc = "Scene Hook: " . $title . ". ";

                    $explicit = [
                        'description'        => null,
                        'visual_beat'        => 'Visual Beat',
                        'visual_signature'   => 'Visual Signature',
                        'narrative_function' => 'Function',
                        'emotional_tone'     => 'Tone',
                    ];
                    $handled = array_merge(array_keys($explicit), ['title', 'name']);

                    foreach ($explicit as $k => $label) {
                        if (empty($h[$k])) continue;
                        $v = $h[$k];
                        if ($label === null) {
                            $desc .= (is_string($v) ? trim($v) : flatten_array_values_auto($v)) . ". ";
                        } elseif (is_string($v)) {
                            $desc .= $label . ": " . $v . ". ";
                        }
                    }

                    foreach ($h as $k => $v) {
                        if (in_array($k, $handled)) continue;
                        $serialized = $this->serializeEntityField($k, $v);
                        if ($serialized) $desc .= $serialized;
                    }

                    $this->log("Scene hook lore loaded: '$title' (" . strlen($desc) . " chars)");
                    $found = true;
                    break;
                }
            }

            // --------------------------------------------------------
            // ALL OTHER ENTITIES
            // --------------------------------------------------------
            else {
                $entity = $this->loreService ? $this->loreService->getEntity($name) : null;
                if ($entity) {
                    $desc = ucfirst($cat) . ": " . $entity['name'] . ". ";

                    if (!empty($entity['roles'])) {
                        $desc .= "Roles: " . implode(", ", (array)$entity['roles']) . ". ";
                    }

                    if (!empty($entity['attributes']) && is_array($entity['attributes'])) {
                        $attrExplicit = [
                            'description'      => null,
                            'summary'          => 'Summary',
                            'function'         => 'Function',
                            'purpose'          => 'Purpose',
                            'visual'           => 'Visual',
                            'appearance'       => 'Appearance',
                            'personality'      => 'Personality',
                            'motivation'       => 'Motivation',
                            'backstory'        => 'Backstory',
                            'abilities'        => 'Abilities',
                            'power'            => 'Power',
                            'weakness'         => 'Weakness',
                            'allegiance'       => 'Allegiance',
                            'location'         => 'Location',
                            'status'           => 'Status',
                            'significance'     => 'Significance',
                            'themes'           => 'Themes',
                            'production_notes' => 'Production Notes',
                        ];
                        $attrHandled = array_merge(array_keys($attrExplicit), ['id', 'type']);

                        foreach ($attrExplicit as $k => $label) {
                            if (empty($entity['attributes'][$k])) continue;
                            $v = $entity['attributes'][$k];
                            if ($label === null) {
                                $desc .= (is_string($v) ? trim($v) : flatten_array_values_auto($v)) . ". ";
                            } else {
                                $serialized = $this->serializeEntityField($label, $v);
                                if ($serialized) $desc .= $serialized;
                            }
                        }

                        foreach ($entity['attributes'] as $k => $v) {
                            if (in_array($k, $attrHandled)) continue;
                            $serialized = $this->serializeEntityField($k, $v);
                            if ($serialized) $desc .= $serialized;
                        }
                    }

                    if (!empty($entity['relationships'])) {
                        $relParts = [];
                        foreach (array_slice($entity['relationships'], 0, 4) as $r) {
                            $rel = $r['target'] ?? '';
                            $typ = $r['type']   ?? '';
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
                        $serialized = $this->serializeEntityField($k, $v);
                        if ($serialized) $desc .= $serialized;
                    }

                    $this->log("Entity lore loaded: '$name' (" . strlen($desc) . " chars)");
                    $found = true;
                }
            }

            if ($found && $desc) {
                // !!! AI ENRICHMENT POINT !!!
                // Build a temporary prompt with freeText prefix if needed
                $itemPrompt = ($freeText ? "Instruction: $freeText\n\n" : "") . $desc;
                
                // Call the enricher
                $enrichedDesc = $this->enrichFilterQuery($itemPrompt, $this->seriesBible, $this->worldKeywords);
                
                $queryParts[] = $enrichedDesc;

                // Store this item's contribution as its own per-item query
                $this->perItemQueries[] = $enrichedDesc;

            } elseif (!$found) {
                $this->log("Warning: No lore found for '$name' ($cat) — using name only.");
                $plain = ucfirst($cat) . ": " . $name;
                $queryParts[] = $plain;
                $this->perItemQueries[] = $plain;
            }
        }

        return implode("\n\n", array_filter($queryParts));
    }


    // ============================================================
    // STAGE 1: BUILD SKETCH POOL — Union of per-item queries
    // ============================================================
    // When multiple filter items are selected, each gets its own
    // focused Chroma query so no item drowns out the others.
    // Results are unioned and deduplicated up to POOL_STAGE1_TARGET.
    // Wide mode (no items): single query from richBaseQuery.
    // ============================================================

    private function buildLorePool($richQuery, $currentSketch = null, $stepIndex = 0) {
        if (empty(trim($richQuery))) {
            $this->log("Stage 1: Empty query — pool unrestricted.");
            return [];
        }

        // Per-shot signal for natural drift as story progresses
        $shotSignal = '';
        if ($currentSketch) {
            $shotSignal = $this->buildCurationSignal($currentSketch);
        }

        // Decide which queries to run:
        // - Precision mode with multiple items: one query per item (union)
        // - Single item or wide mode: one query from richQuery
        $queriesToRun = [];

        if ($this->precisionMode && count($this->perItemQueries) > 1) {
            // Multiple items — run each separately for true union
            foreach ($this->perItemQueries as $idx => $itemQuery) {
                $q = $itemQuery;
                if ($shotSignal) {
                    $q .= "\n\n--- CURRENT SHOT CONTEXT ---\n" . $shotSignal;
                }
                $queriesToRun[] = $q;
            }
            $this->log("Stage 1: Running " . count($queriesToRun) . " per-item queries (union mode).");
        } else {
            // Single query — wide mode or single item
            $q = $richQuery;
            if ($shotSignal) {
                $q .= "\n\n--- CURRENT SHOT CONTEXT ---\n" . $shotSignal;
            }
            $queriesToRun[] = $q;
        }

        // How many results to request per query
        // Spread the target across items so union reaches POOL_STAGE1_TARGET
        $nPerQuery = min(
            self::POOL_STAGE1_QUERY_N,
            (int)ceil(self::POOL_STAGE1_TARGET * 1.5 / count($queriesToRun))
        );

        $seen    = [];
        $poolIds = [];

        foreach ($queriesToRun as $idx => $baseQuery) {
            // Keyword injection per query
            $poolQuery = "";
            if ($this->precisionMode && !empty($this->precisionEntityNames)) {
                // For multi-item union, inject only this item's name if we can isolate it
                $keywords   = count($queriesToRun) > 1
                    ? ($this->precisionEntityNames[$idx] ?? implode(", ", $this->precisionEntityNames))
                    : implode(", ", $this->precisionEntityNames);
                $poolQuery .= "Focus Entities: " . $keywords . ". ";
                $poolQuery .= $keywords . ". ";
            }
            $poolQuery .= $baseQuery;
            $poolQuery  = $this->appendWorldDna($poolQuery);

            if ($this->debugMode) {
                $this->debugData['vector_queries'][] = [
                    'step'      => "stage1_pool_step{$stepIndex}_item{$idx}",
                    'raw_query' => substr($poolQuery, 0, 200),
                ];
            }

            $this->log("Stage 1 query[$idx] (first 200): " . substr($poolQuery, 0, 200));

            try {
                $res = $this->vectorService->queryJson(
                    $poolQuery,
                    self::SKETCH_COLLECTION,
                    $nPerQuery,
                    'text',
                    ['type' => 'primary']
                );

                $candidates = $this->vectorService->extractSketchIds($res);
                $addedThisQuery = 0;

                foreach ($candidates as $c) {
                    $id = (int)$c['sketch_id'];
                    if ($id > 0 && !isset($seen[$id])) {
                        $poolIds[]   = $id;
                        $seen[$id]   = true;
                        $addedThisQuery++;
                    }
                }

                $this->log("Stage 1 query[$idx]: added $addedThisQuery sketches.");

            } catch (Exception $e) {
                $this->log("Stage 1 pool error (query $idx): " . $e->getMessage());
            }
        }

        // Trim to target
        $poolIds = array_slice($poolIds, 0, self::POOL_STAGE1_TARGET);

        $this->log("Stage 1: Pool = " . count($poolIds) . " sketches (union of " . count($queriesToRun) . " queries).");
        return $poolIds;
    }


    // --------------------------------------------------------
    // LOAD WORLD CONTEXT from md_doc_analysis + keywords
    // --------------------------------------------------------
    private function loadLoreCollection($docId, $docName) {
        if (!$docId) return;

        // JOIN with documentations to get keywords and bible/desc_short
        $stmt = $this->pdo->prepare("
            SELECT 
                mda.summary, 
                mda.thematics, 
                mda.showrunner_analysis,
                d.keywords,
                COALESCE(LEFT(d.desc_short, 800), LEFT(mda.series_bible, 800)) as bible
            FROM md_doc_analysis mda
            JOIN documentations d ON d.id = mda.doc_id
            WHERE mda.doc_id = ?
        ");
        $stmt->execute([$docId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->log("Warning: No md_doc_analysis found for doc_id $docId");
            return;
        }

        // Store Bible and Keywords for Enricher
        $this->seriesBible   = $row['bible'] ?? '';
        $this->worldKeywords = $row['keywords'] ?? '';

        $baseParts = [];
        $dnaParts  = [];

        if (!empty($row['summary'])) {
            $baseParts[] = "World Summary: " . substr($row['summary'], 0, 400);
        }

        $them = json_decode($row['thematics'] ?? '{}', true);
        if (!empty($them['mood'])) {
            $baseParts[] = "Mood: " . $them['mood'];
        }
        if (!empty($them['themes']) && is_array($them['themes'])) {
            $baseParts[] = "Themes: " . implode(", ", array_slice($them['themes'], 0, 6));
        }

        $show = json_decode($row['showrunner_analysis'] ?? '{}', true);
        if (!empty($show['visual_keywords']) && is_array($show['visual_keywords'])) {
            $dna = implode(", ", array_slice($show['visual_keywords'], 0, 12));
            $baseParts[] = "Visual DNA: " . $dna;
            $dnaParts[]  = "World Visual DNA: " . $dna;
        }
        if (!empty($show['production_notes'])) {
            $notes = is_array($show['production_notes'])
                ? implode(". ", array_slice($show['production_notes'], 0, 3))
                : substr($show['production_notes'], 0, 300);
            $baseParts[] = "Production Notes: " . $notes;
            $dnaParts[]  = "Production Notes: " . $notes;
        }

        $this->loreBaseContext = implode("\n", $baseParts);
        $this->worldVisualDna  = implode("\n", $dnaParts);
        $this->log("World grounded: " . ($docName ?: "doc $docId") . " (Keywords loaded)");
    }


    // --------------------------------------------------------
    // HELPER: APPEND WORLD DNA
    // --------------------------------------------------------
    private function appendWorldDna($query) {
        if ($this->contextAnchor) {
            $query = $this->contextAnchor . ". " . $query;
        }
        if (!empty($this->worldVisualDna)) {
            $query .= "\n\n--- WORLD ANCHOR ---\n" . $this->worldVisualDna;
        }
        return $query;
    }


    // --------------------------------------------------------
    // CURATION SIGNAL (from current sketch — per-shot pool drift)
    // --------------------------------------------------------
    private function buildCurationSignal($sketch) {
        $parts = [];
        $cls  = $this->decodeField($sketch['classification'] ?? []);
        $them = $this->decodeField($sketch['thematics'] ?? []);
        $ent  = $this->decodeField($sketch['entities'] ?? []);

        if (!empty($cls['narrative_function'])) $parts[] = $cls['narrative_function'];
        if (!empty($cls['emotional_tone']))     $parts[] = $cls['emotional_tone'];
        if (!empty($cls['visual_style']))       $parts[] = $cls['visual_style'];
        if (!empty($them['primary_themes']))    $parts[] = implode(", ", array_slice($them['primary_themes'], 0, 3));
        if (!empty($ent['characters']))         $parts[] = "Characters: " . implode(", ", $ent['characters']);
        if (!empty($ent['locations']))          $parts[] = "Locations: "  . implode(", ", $ent['locations']);

        if (!empty($sketch['description'])) {
            $parts[] = substr($sketch['description'], 0, 250);
        }
        return implode(". ", array_filter($parts));
    }


    // --------------------------------------------------------
    // ASK AI FOR NEXT SHOT (preserved from V9)
    // --------------------------------------------------------
    private function askAiForNextShot($current, $richQuery, $mode, $progress, $seedQuery) {
        if (!$this->useAi || !$this->generatorService || !$this->directorConfig) {
            return null;
        }

        $cls  = $this->decodeField($current['classification'] ?? []);
        $them = $this->decodeField($current['thematics'] ?? []);
        $ent  = $this->decodeField($current['entities'] ?? []);
        $func = $this->decodeField($current['narrative_function'] ?? []);

        $beatLabel = $this->getBeatLabel($progress);

        $inputText = "=== CURRENT SHOT ===\n";
        $inputText .= "Title: "              . ($current['name'] ?? '') . "\n";
        $inputText .= "Narrative Function: " . implode(", ", (array)$func) . "\n";
        $inputText .= "Energy: "             . ($current['energy'] ?? 'neutral') . " | Pos: " . ($current['position'] ?? '') . "\n";
        $inputText .= "Editorial Use: "      . ($current['connective_hint'] ?? '') . "\n";
        $inputText .= "Emotional Tone: "     . ($cls['emotional_tone']  ?? 'Unknown') . "\n";
        $inputText .= "Visual Style: "       . ($cls['visual_style']    ?? 'Unknown') . "\n";
        $inputText .= "Themes: "             . implode(", ", (array)($them['primary_themes'] ?? [])) . "\n";
        $inputText .= "Characters: "         . implode(", ", (array)($ent['characters'] ?? [])) . "\n";
        $inputText .= "Scene: "              . substr($current['description'] ?? '', 0, 350) . "\n";

        $inputText .= "\n=== NARRATIVE POSITION ===\n";
        $inputText .= "Director Logic: "  . strtoupper($mode) . "\n";
        $inputText .= "Sequence Beat: "   . $beatLabel . " (progress: " . round($progress * 100) . "%)\n";

        if (!empty($richQuery)) {
            $inputText .= "\n=== WORLD + ENTITY CONTEXT ===\n" . substr($richQuery, 0, 600) . "\n";
        }

        if (!empty($seedQuery)) {
            $inputText .= "\n=== ORIGINAL SEQUENCE GOAL ===\n" . substr($seedQuery, 0, 200) . "\n";
        }

        if ($this->precisionMode && !empty($this->precisionEntityNames)) {
            $inputText .= "\n=== FOCUS ENTITIES (keep in frame) ===\n";
            $inputText .= implode(", ", $this->precisionEntityNames) . "\n";
        }

        try {
            $res    = $this->generatorService->generate(
                $this->directorConfig,
                ['entity_name' => $inputText]
            );
            $rawOut = is_object($res) && method_exists($res, 'getRawResponse')
                ? $res->getRawResponse()
                : (string)$res;

            $stripped = str_replace(['```json', '```'], '', $rawOut);
            $stripped = trim($stripped);

            if (!empty($stripped)) {
                $hint = '';
                if (preg_match('/"(?:next_shot_intent|next_shot_title|narrative_function)"\s*:\s*"([^"]{10,80})"/i', $rawOut, $m)) {
                    $hint = $m[1];
                }
                $this->log("AI → " . ($hint ?: substr($stripped, 0, 60)));

                if ($this->debugMode) {
                    $this->debugData['ai_calls'][] = [
                        'input'              => $inputText,
                        'raw_output'         => $rawOut,
                        'vector_query_built' => $stripped,
                    ];
                }

                return $stripped;
            }

        } catch (Exception $e) {
            $this->log("AI shot reasoning error: " . $e->getMessage());
        }

        return null;
    }


    // ============================================================
    // MAIN GENERATION LOOP
    // ============================================================

    public function generate($docId, $docName, $mode, $targetLength, $filterPayload) {

        $sequenceIds = [];
        $this->lastPicks      = [];
        $this->perItemQueries = [];
        $this->precisionEntityNames = [];

        $this->docId   = $docId;
        $this->docName = $docName;

        $this->log("Director Mode: " . strtoupper($mode));
        if ($this->useAi)     $this->log("AI Reasoning: ON");
        if ($this->debugMode) $this->log("[DEBUG] Debug mode active. Stage 1→2 pipeline (V10).");

        // Load world context from md_doc_analysis
        $this->loadLoreCollection($docId, $docName);
        if ($docName) {
            $this->contextAnchor = "World: " . $docName;
        }

        $filter            = json_decode($filterPayload, true) ?: [];
        $this->filterItems = $filter['items'] ?? [];
        $this->freeText    = trim($filter['text'] ?? '');

        // Temperature: from filter payload, clamped to 0.0–1.0
        if (isset($filter['temperature'])) {
            $t = (float)$filter['temperature'];
            $this->seedTemperature = max(0.0, min(1.0, $t));
        }
        if (isset($filter['step_temperature'])) {
            $t = (float)$filter['step_temperature'];
            $this->stepTemperature = max(0.0, min(1.0, $t));
        }
        $this->log("Temperatures — seed: " . $this->seedTemperature . " step: " . $this->stepTemperature);

        if (!empty($this->filterItems)) {
            $this->precisionMode = true;
            $this->log("Query Mode: PRECISION (" . count($this->filterItems) . " entities selected)");
        } else {
            $this->precisionMode = false;
            $this->log("Query Mode: WIDE (world context" . ($this->freeText ? " + free text" : "") . ")");
        }

        // Build rich query directly from MariaDB — this IS the lore context now
        $this->richBaseQuery = $this->buildRichQueryFromFilter(
            $this->filterItems,
            $this->freeText,
            $docId
        );

        if (!empty($this->precisionEntityNames)) {
            $this->log("Entities: " . implode(", ", $this->precisionEntityNames));
        }

        $this->log("Rich query built: " . strlen($this->richBaseQuery) . " chars.");

        if ($this->debugMode) {
            $this->debugData['seed_query'] = substr($this->richBaseQuery, 0, 500);
        }

        // Pick seed via Stage 1 pool
        $seed = $this->pickSeed();
        if (!$seed) {
            $this->log("Staged seed failed. Fallback to random.");
            $seed = $this->pickRandomSeed($docName);
        }
        if (!$seed) throw new Exception("No valid seed found.");

        $sequenceIds[]      = $seed['id'];
        $this->lastPicks[]  = $seed['id'];

        $func = $this->decodeField($seed['narrative_function'] ?? []);
        $this->log(
            "SEED: #" . $seed['id'] .
            " (" . substr($seed['name'], 0, 20) . "...)" .
            (!empty($func) ? " [" . implode(',', $func) . "]" : "")
        );

        $currentSketch = $seed;

        for ($i = 0; $i < $targetLength - 1; $i++) {
            $progress = ($i + 1) / ($targetLength - 1);
            $next     = $this->pickNext($currentSketch, $sequenceIds, $mode, $progress, $i + 2);

            if ($next) {
                $sequenceIds[]     = $next['id'];
                $this->lastPicks[] = $next['id'];
                if (count($this->lastPicks) > 3) array_shift($this->lastPicks);

                $this->log("SHOT " . ($i + 2) . ": #" . $next['id'] . " (" . $next['name'] . ")");
                $currentSketch = $next;
            } else {
                $this->log("Chain broken at shot " . ($i + 2) . ": No suitable matches in pool.");
                break;
            }
        }

        $modeLabel = $this->precisionMode
            ? "Precision: " . implode(", ", array_slice($this->precisionEntityNames, 0, 3))
            : "Wide";

        $title = "AutoSeq: " . $seed['name'];
        if ($docName) $title .= " (" . strtoupper(str_replace(' ', '_', $docName)) . ")";

        $desc = "Logic: $mode. Anchor: " . ($docName ?: 'Global') .
                ". Mode: $modeLabel" .
                ($this->useAi ? ". AI: on" : "") .
                ". Length: " . count($sequenceIds);

        return [
            'ids'         => $sequenceIds,
            'name'        => $title,
            'description' => $desc,
            'logs'        => $this->logs,
            'debug_data'  => $this->debugMode ? $this->debugData : null,
        ];
    }


    // ============================================================
    // WEIGHTED RANDOM PICK
    // ============================================================
    // Converts a scores array (id => float) into a probability
    // distribution and samples from it. Temperature controls the
    // balance between quality and variety:
    //   0.0 = fully deterministic (always pick top score)
    //   0.35 = default — strong quality preference, real variety
    //   1.0 = fully random (scores ignored)
    //
    // Uses softmax-style weighting: each score is exponentiated
    // by 1/temperature so higher scores are exponentially more
    // likely but lower scores always retain some chance.
    // ============================================================

    private function weightedRandomPick(array $scores, float $temperature): ?int {
        if (empty($scores)) return null;

        // At temperature ~0 just return the top scorer
        if ($temperature < 0.01) {
            return (int)array_key_first($scores);
        }

        // Shift scores to be non-negative (min becomes 0)
        $minScore = min($scores);
        $shifted  = [];
        foreach ($scores as $id => $s) {
            $shifted[$id] = $s - $minScore;
        }

        // Exponentiate by 1/temperature — amplifies differences
        $exponent = 1.0 / $temperature;
        $weights  = [];
        $total    = 0.0;
        foreach ($shifted as $id => $s) {
            $w          = pow($s + 1e-9, $exponent); // +epsilon avoids pow(0,x)=0
            $weights[$id] = $w;
            $total       += $w;
        }

        if ($total <= 0) return (int)array_key_first($scores);

        // Sample
        $rand = (mt_rand() / mt_getrandmax()) * $total;
        $cumulative = 0.0;
        foreach ($weights as $id => $w) {
            $cumulative += $w;
            if ($rand <= $cumulative) return (int)$id;
        }

        // Fallback to last key
        return (int)array_key_last($weights);
    }


    // ============================================================
    // PICK SEED — Stage 1 pool → scored best opener
    // ============================================================
    // Uses the same three-vector scoring as pickNext() so the seed
    // is the highest-relevance opener in the pool, not a random one.
    // Position weighting: opener > any > mid (closer excluded).
    // ============================================================

    private function pickSeed() {
        $this->log("Seeding via Stage 1 pool (scored)...");

        $poolIds = $this->buildLorePool($this->richBaseQuery, null, 0);

        if (empty($poolIds)) {
            $this->log("Seed pool empty — fallback to direct sketch query.");
            return $this->pickSeedDirect();
        }

        // Build seed queries — rich base as primary, ACT 1 signal as narrative
        $qPrimary   = $this->appendWorldDna($this->richBaseQuery);
        $qNarrative = "Establishing shot, opening beat, introduce world, ACT 1";
        $qStyle     = $this->worldVisualDna ?: $this->loreBaseContext;

        // Constrain all three vectors to the pool
        $cleanPoolIds = array_map('intval', array_values($poolIds));
        $poolFilter   = ['sketch_id' => ['$in' => $cleanPoolIds]];
        $n            = min(count($cleanPoolIds), 100);

        try {
            $resP = $this->vectorService->queryJson($qPrimary,   self::SKETCH_COLLECTION, $n, 'text', $poolFilter);
            $resN = $this->vectorService->queryJson($qNarrative, self::SKETCH_COLLECTION, $n, 'text', $poolFilter);
            $resS = $this->vectorService->queryJson($qStyle,     self::SKETCH_COLLECTION, $n, 'text', $poolFilter);
        } catch (Exception $e) {
            $this->log("Seed scoring error: " . $e->getMessage() . " — falling back to random.");
            return $this->getFullSketchData($poolIds[array_rand(array_slice($poolIds, 0, 10))]);
        }

        // Accumulate scores
        $scores = [];
        $this->accumulateScores($scores, $resP, self::W_PRIMARY,   'primary');
        $this->accumulateScores($scores, $resN, self::W_NARRATIVE, 'narrative');
        $this->accumulateScores($scores, $resS, self::W_STYLE,     'style');
        arsort($scores);

        // Fetch positions for top-scoring candidates (efficiency — top 40 only)
        $topIds = array_keys(array_slice($scores, 0, 40, true));
        $posMap = [];
        if (!empty($topIds)) {
            $placeholders = str_repeat('?,', count($topIds) - 1) . '?';
            $stmt = $this->pdo->prepare("
                SELECT s.id, ssa.position
                FROM sketches s
                JOIN sketch_sequence_analysis ssa ON s.id = ssa.sketch_id
                WHERE s.id IN ($placeholders) AND s.searchable = 1
            ");
            $stmt->execute(array_values($topIds));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $pr) {
                $posMap[(int)$pr['id']] = $pr['position'];
            }
        }

        // Apply position bonus/penalty
        foreach ($scores as $id => $score) {
            $pos = $posMap[$id] ?? 'any';
            if ($pos === 'opener')      $scores[$id] += 0.15;
            elseif ($pos === 'any')     $scores[$id] += 0.05;
            elseif ($pos === 'closer')  $scores[$id] -= 0.30;
        }
        arsort($scores);

        // Remove closers from the candidate pool entirely
        foreach ($scores as $id => $score) {
            $pos = $posMap[$id] ?? 'any';
            if ($pos === 'closer') unset($scores[$id]);
        }

        if (empty($scores)) {
            $this->log("Seed scoring yielded no valid candidate — using pool fallback.");
            return $this->getFullSketchData($poolIds[array_rand(array_slice($poolIds, 0, 10))]);
        }

        // Weighted random pick using temperature
        // Higher temperature = more variety, lower = more deterministic
        $pickedId = $this->weightedRandomPick($scores, $this->seedTemperature);

        if ($pickedId) {
            $pos   = $posMap[$pickedId] ?? 'any';
            $score = $scores[$pickedId] ?? 0;
            $this->log("Seed picked: #$pickedId pos=$pos score=" . round($score, 3) . " temp=" . $this->seedTemperature);
            $cand = $this->getFullSketchData($pickedId);
            if ($cand) return $cand;
        }

        // Last resort
        $this->log("Seed weighted pick failed — using pool fallback.");
        return $this->getFullSketchData($poolIds[array_rand(array_slice($poolIds, 0, 10))]);
    }


    // --------------------------------------------------------
    // PICK SEED DIRECT (fallback when pool is empty)
    // --------------------------------------------------------
    private function pickSeedDirect() {
        $query = $this->appendWorldDna($this->richBaseQuery ?: $this->loreBaseContext);
        if (empty(trim($query))) return null;

        try {
            $res = $this->vectorService->queryJson(
                $query, self::SKETCH_COLLECTION, 40, 'text', ['type' => 'primary']
            );
            $candidates = $this->vectorService->extractSketchIds($res);

            if (empty($candidates)) return null;

            $pool = [];
            foreach ($candidates as $c) {
                $sk = $this->getFullSketchData($c['sketch_id']);
                if ($sk) {
                    $pos = $sk['position'] ?? 'any';
                    if (in_array($pos, ['opener', 'any', 'mid'])) $pool[] = $sk;
                }
            }

            if (empty($pool)) return $this->getFullSketchData($candidates[0]['sketch_id']);
            return $pool[array_rand(array_slice($pool, 0, 5))];

        } catch (Exception $e) {
            $this->log("Seed Direct Error: " . $e->getMessage());
        }
        return null;
    }


    // ============================================================
    // PICK NEXT — Two-Stage Pipeline (per shot)
    // Stage 1: fresh pool from rich query + current sketch signal
    // Stage 2: three-vector scoring within pool
    // ============================================================

    private function pickNext($current, $excludeIds, $mode, $progress, $stepIndex) {

        // ---- STAGE 1: Build pool — rich query enriched with current sketch signal ----
        $poolIds = $this->buildLorePool($this->richBaseQuery, $current, $stepIndex);

        // Remove already-used sketches
        if (!empty($poolIds) && !empty($excludeIds)) {
            $poolIds = array_values(array_diff($poolIds, $excludeIds));
        }

        $usePool = !empty($poolIds);

        if (!$usePool) {
            $this->log("Shot {$stepIndex}: Pool empty — Stage 2 unrestricted.");
        }

        // ---- Build Stage 2 queries ----
        $qPrimary = "";
        if ($this->useAi) {
            $aiPrompt = $this->askAiForNextShot($current, $this->richBaseQuery, $mode, $progress, $this->richBaseQuery);
            if ($aiPrompt) {
                $qPrimary = $this->appendWorldDna($aiPrompt);
            }
        }

        if (empty($qPrimary)) {
            $fallback = $this->buildCompositeSketchQuery($current, $this->richBaseQuery, $mode, $progress, $this->richBaseQuery);
            $qPrimary = $this->appendWorldDna($fallback);
        }

        $qNarrative = $this->buildNextNarrativeQuery($current, $mode, $progress);
        $qStyle     = $this->buildNextStyleQuery($current, $mode);

        if ($this->debugMode) {
            $this->debugData['vector_queries'][] = [
                'step'      => $stepIndex,
                'pool_size' => count($poolIds),
                'primary'   => substr($qPrimary, 0, 150),
            ];
        }

        // ---- STAGE 2: Three-vector scoring within pool ----
        $cleanPoolIds    = [];
        $filterPrimary   = ['type' => 'primary'];
        $filterNarrative = ['type' => 'narrative'];
        $filterStyle     = ['type' => 'style'];

        if ($usePool) {
            $cleanPoolIds    = array_map('intval', array_values($poolIds));
            $poolFilter      = ['sketch_id' => ['$in' => $cleanPoolIds]];
            $filterPrimary   = $poolFilter;
            $filterNarrative = $poolFilter;
            $filterStyle     = $poolFilter;
        }

        $n = $usePool ? min(count($cleanPoolIds), 100) : 50;

        $resP = $this->vectorService->queryJson($qPrimary,   self::SKETCH_COLLECTION, $n, 'text', $filterPrimary);
        $resN = $this->vectorService->queryJson($qNarrative, self::SKETCH_COLLECTION, $n, 'text', $filterNarrative);
        $resS = $this->vectorService->queryJson($qStyle,     self::SKETCH_COLLECTION, $n, 'text', $filterStyle);

        // ---- Score Accumulation ----
        $scores = [];
        $this->accumulateScores($scores, $resP, self::W_PRIMARY,   'primary');
        $this->accumulateScores($scores, $resN, self::W_NARRATIVE, 'narrative');
        $this->accumulateScores($scores, $resS, self::W_STYLE,     'style');

        foreach ($scores as $id => $score) {
            if (in_array($id, $this->lastPicks)) $scores[$id] -= self::W_NOVELTY;
        }
        arsort($scores);

        // ---- Pass 1: Weighted random pick with structural constraints ----
        // Build a constraint-filtered candidate pool, then sample by temperature
        $pass1Pool = [];
        foreach ($scores as $id => $score) {
            if (in_array($id, $excludeIds)) continue;
            $cand = $this->getFullSketchData($id);
            if (!$cand) continue;
            if ($this->checkStructuralConstraints($current, $cand, $progress)) {
                $pass1Pool[$id] = $score;
            }
        }

        if (!empty($pass1Pool)) {
            $pickedId = $this->weightedRandomPick($pass1Pool, $this->stepTemperature);
            if ($pickedId) return $this->getFullSketchData($pickedId);
        }

        // ---- Pass 2: Relaxed Constraints ----
        if ($usePool && count($scores) > 0) {
            $this->log("Shot {$stepIndex}: Strict constraints exhausted pool. Relaxing rules.");
            foreach ($scores as $id => $score) {
                if (in_array($id, $excludeIds)) continue;
                $cand = $this->getFullSketchData($id);
                if (!$cand) continue;
                $nextType = $cand['standalone'] ?? 'yes';
                $prevType = $current['standalone'] ?? 'yes';
                if ($nextType === 'needs-context' && $prevType === 'needs-context') continue;
                return $cand;
            }
        }

        // ---- Pass 3: Unrestricted Fallback ----
        if ($usePool) {
            $this->log("Shot {$stepIndex}: Pool exhausted — trying unrestricted fallback.");
            return $this->pickNextUnrestricted($current, $excludeIds, $mode, $progress, $stepIndex, $qPrimary, $qNarrative, $qStyle);
        }

        return null;
    }


    // --------------------------------------------------------
    // UNRESTRICTED FALLBACK
    // --------------------------------------------------------
    private function pickNextUnrestricted($current, $excludeIds, $mode, $progress, $stepIndex, $qPrimary, $qNarrative, $qStyle) {
        $resP = $this->vectorService->queryJson($qPrimary,   self::SKETCH_COLLECTION, 50, 'text', ['type' => 'primary']);
        $resN = $this->vectorService->queryJson($qNarrative, self::SKETCH_COLLECTION, 50, 'text', ['type' => 'narrative']);
        $resS = $this->vectorService->queryJson($qStyle,     self::SKETCH_COLLECTION, 50, 'text', ['type' => 'style']);

        $scores = [];
        $this->accumulateScores($scores, $resP, self::W_PRIMARY,   'primary');
        $this->accumulateScores($scores, $resN, self::W_NARRATIVE, 'narrative');
        $this->accumulateScores($scores, $resS, self::W_STYLE,     'style');

        foreach ($scores as $id => $score) {
            if (in_array($id, $this->lastPicks)) $scores[$id] -= self::W_NOVELTY;
        }
        arsort($scores);

        foreach ($scores as $id => $score) {
            if (in_array($id, $excludeIds)) continue;
            $cand = $this->getFullSketchData($id);
            if (!$cand) continue;
            if (!$this->checkStructuralConstraints($current, $cand, $progress)) continue;
            return $cand;
        }

        return null;
    }


    // --------------------------------------------------------
    // SCORING HELPERS
    // --------------------------------------------------------

    private function accumulateScores(&$scores, $chromaResult, $weight, $type) {
        $extracted = $this->vectorService->extractSketchIds($chromaResult);
        foreach ($extracted as $item) {
            $id  = $item['sketch_id'];
            $sim = 1.0 - ($item['distance'] * 0.5);
            if ($sim < 0) $sim = 0;
            if (!isset($scores[$id])) $scores[$id] = 0;
            $scores[$id] += $sim * $weight;
        }
    }

    // --------------------------------------------------------
    // BUILD COMPOSITE SKETCH QUERY (Stage 2 primary fallback)
    // --------------------------------------------------------

    private function buildCompositeSketchQuery($sketch, $richQuery, $mode, $progress, $seedQuery) {
        $parts = [];

        if ($this->precisionMode && !empty($this->precisionEntityNames)) {
            $parts[] = "Required Elements: " . implode(", ", $this->precisionEntityNames);
        }

        if (!empty($richQuery)) {
            $label   = $this->precisionMode ? "Entity Context" : "World Context";
            $parts[] = "$label:\n" . substr($richQuery, 0, 400);
        }

        $cls  = $this->decodeField($sketch['classification'] ?? []);
        $them = $this->decodeField($sketch['thematics'] ?? []);

        if (!empty($cls['narrative_function'])) $parts[] = "Current Function: " . $cls['narrative_function'];
        if (!empty($cls['emotional_tone']))     $parts[] = "Tone: " . $cls['emotional_tone'];
        if (!empty($them['primary_themes']))    $parts[] = "Themes: " . implode(", ", array_slice($them['primary_themes'], 0, 4));

        $modifier = $this->getBeatModifier($mode, $progress);
        if ($modifier) $parts[] = "Director Beat: " . $modifier;

        return implode("\n", $parts);
    }

    private function buildNextNarrativeQuery($current, $mode, $progress) {
        $parts = [];
        $currEnergy = $current['energy'] ?? 'neutral';
        if ($mode === 'contrast') {
            $parts[] = "Reversal, Twist, Surprise";
        } elseif ($progress > 0.8) {
            $parts[] = "Resolution, Aftermath, Closer";
        } else {
            if ($currEnergy == 'advance')  $parts[] = "Escalation, Complication";
            elseif ($currEnergy == 'turn') $parts[] = "Reaction, Decision";
            else                           $parts[] = "Advance, Progression";
        }
        if (!empty($current['connective_hint'])) $parts[] = $current['connective_hint'];
        return implode(", ", $parts);
    }

    private function buildNextStyleQuery($current, $mode) {
        $cls   = $this->decodeField($current['classification'] ?? []);
        $style = $cls['visual_style']   ?? '';
        $tone  = $cls['emotional_tone'] ?? '';
        if ($mode === 'contrast') return "Visually opposite, conflict";
        return trim("$style. $tone", ". ");
    }

    private function checkStructuralConstraints($prev, $next, $progress) {
        $nextType = $next['standalone'] ?? 'yes';
        $prevType = $prev['standalone'] ?? 'yes';
        if ($nextType === 'needs-context' && $prevType === 'needs-context') return false;

        $pos = $next['position'] ?? 'any';
        if ($pos === 'opener' && $progress > 0.2) return false;
        if ($pos === 'closer' && $progress < 0.8) return false;
        return true;
    }

    private function getBeatLabel($progress) {
        if ($progress < 0.33) return "ACT 1 — Establish";
        if ($progress < 0.66) return "ACT 2 — Conflict";
        return "ACT 3 — Resolution";
    }

    private function getBeatModifier($mode, $progress) {
        if ($mode === 'contrast') return "visually contrasting, conflict";
        if ($progress < 0.33)    return "establishing shot, wide angle";
        if ($progress < 0.66)    return "action shot, dynamic";
        return "reaction shot, close-up";
    }


    // --------------------------------------------------------
    // DATA FETCHING
    // --------------------------------------------------------

    private function getFullSketchData($id) {
        $stmt = $this->pdo->prepare("
            SELECT
                s.id, s.name, s.description,
                sa.entities, sa.classification, sa.scoring, sa.thematics, sa.recommendations,
                ssa.narrative_function, ssa.layer, ssa.energy, ssa.position, ssa.intensity,
                ssa.standalone, ssa.connective_hint, ssa.narrative_function_mask, ssa.layer_mask,
                ssa.confidence, ssa.structure_type, ssa.shot_scale, ssa.edit_relationship
            FROM sketches s
            LEFT JOIN sketch_analysis sa ON s.id = sa.sketch_id
            LEFT JOIN sketch_sequence_analysis ssa ON s.id = ssa.sketch_id
            WHERE s.id = ? AND s.searchable = 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $row['classification'] = $this->decodeField($row['classification'] ?? []);
            $row['entities']       = $this->decodeField($row['entities']       ?? []);
            $row['thematics']      = $this->decodeField($row['thematics']      ?? []);
        }
        return $row ?: null;
    }

    private function pickRandomSeed($keyword = null) {
        $sql = "SELECT s.id FROM sketches s
                JOIN sketch_sequence_analysis ssa ON s.id = ssa.sketch_id
                WHERE ssa.position IN ('opener', 'any') AND s.searchable = 1";
        if ($keyword) {
            $clean = preg_replace('/[^a-zA-Z0-9 ]/', '', $keyword);
            $sql  .= " AND (s.description LIKE '%$clean%' OR s.name LIKE '%$clean%')";
        }
        $sql .= " ORDER BY RAND() LIMIT 1";
        $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        if ($row) return $this->getFullSketchData($row['id']);
        return null;
    }
}


// ============================================================
// API CONTROLLER (preserved from V9)
// ============================================================

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_sequence') {
        try {
            $docId         = !empty($_POST['doc_id'])    ? $_POST['doc_id']    : null;
            $docName       = $_POST['doc_name']           ?? '';
            $mode          = $_POST['mode']               ?? 'associative';
            $length        = (int)($_POST['length']       ?? 6);
            $filterPayload = $_POST['filter_payload']     ?? '';
            $useAi         = !empty($_POST['use_ai'])     && $_POST['use_ai'] !== '0';
            $debugMode     = !empty($_POST['debug_mode']) && $_POST['debug_mode'] !== '0';

            $director = new AutoDirector($pdo, $useAi, $debugMode);
            $result   = $director->generate($docId, $docName, $mode, $length, $filterPayload);

            $stmt = $pdo->prepare("
                INSERT INTO narrative_sequences_auto
                    (name, description, sequence_data, generation_log, linked_doc_id, status, score)
                VALUES (?, ?, ?, ?, ?, 'pending', ?)
            ");
            $stmt->execute([
                $result['name'],
                $result['description'],
                json_encode($result['ids']),
                json_encode($result['logs']),
                $docId,
                count($result['ids'])
            ]);

            $lib   = new SketchLibrary($pdo);
            $items = $lib->hydrateSpecificIds($result['ids']);

            $response = [
                'status'        => 'success',
                'sequence_name' => $result['name'],
                'logs'          => $result['logs'],
                'items'         => $items,
            ];

            if ($debugMode && !empty($result['debug_data'])) {
                $response['debug_data'] = $result['debug_data'];
            }

            echo json_encode($response);

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'promote') {
        try {
            $id   = (int)$_POST['id'];
            $stmt = $pdo->prepare("SELECT * FROM narrative_sequences_auto WHERE id = ?");
            $stmt->execute([$id]);
            $auto = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($auto) {
                $man = new SequenceManager($pdo);
                $ids = json_decode($auto['sequence_data'], true);
                if (is_array($ids) && count($ids) > 0) {
                    $man->save(
                        $auto['name'],
                        $auto['description'] . " [Auto-Promoted]",
                        $ids,
                        $auto['linked_doc_id']
                    );
                    $pdo->prepare("UPDATE narrative_sequences_auto SET status = 'promoted' WHERE id = ?")
                        ->execute([$id]);
                    echo json_encode(['status' => 'success']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Empty sequence data']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE narrative_sequences_auto SET status = 'archived' WHERE id = ?")
            ->execute([$id]);
        echo json_encode(['status' => 'success']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (($_GET['action'] ?? '') === 'list_results') {
        $stmt = $pdo->query("
            SELECT id, name, description, status, score, sequence_data, created_at
            FROM narrative_sequences_auto
            WHERE status != 'archived'
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $ids = json_decode($r['sequence_data'], true);
            $r['item_count'] = is_array($ids) ? count($ids) : 0;
        }
        echo json_encode(['status' => 'success', 'data' => $rows]);
        exit;
    }

    if (($_GET['action'] ?? '') === 'get_filter_cats') {
        echo json_encode(['status' => 'success', 'data' => [
            'episodes', 'scene_hooks', 'characters', 'locations', 'factions', 'artifacts'
        ]]);
        exit;
    }

    if (($_GET['action'] ?? '') === 'get_filter_items') {
        $docId = (int)$_GET['doc_id'];
        $cat   = $_GET['cat'] ?? '';
        $lore  = new LoreAccessService($pdo);
        $lore->loadDoc($docId);

        $uiItems = [];
        if (in_array($cat, ['episodes', 'scene_hooks'])) {
            $story   = $lore->getStoryEngine();
            $rawList = $story[$cat] ?? [];
            foreach ($rawList as $item) {
                if (is_array($item))      $uiItems[] = $item['title'] ?? ($item['name'] ?? 'Untitled');
                elseif (is_string($item)) $uiItems[] = $item;
            }
        } else {
            $items = $lore->queryEntities($cat);
            foreach ($items as $i) $uiItems[] = $i['name'];
        }

        echo json_encode([
            'status' => 'success',
            'data'   => array_values(array_unique($uiItems))
        ]);
        exit;
    }

    // --------------------------------------------------------
    // ENTITY PREVIEW (Peek Modal) — added V10.2
    // Identical logic to narratives_api.php get_entity_preview.
    // Routes through LoreAccessService, handles both episodes/
    // scene_hooks (story engine) and standard entities (index).
    // --------------------------------------------------------
    if (($_GET['action'] ?? '') === 'get_entity_preview') {
        $docId = isset($_GET['doc_id']) && $_GET['doc_id'] !== '' ? (int)$_GET['doc_id'] : null;
        $cat   = $_GET['cat']  ?? '';
        $name  = $_GET['name'] ?? '';

        if (!$docId || !$name) {
            echo json_encode(['status' => 'error', 'message' => 'Missing doc_id or name']);
            exit;
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
        exit;
    }
}
?>