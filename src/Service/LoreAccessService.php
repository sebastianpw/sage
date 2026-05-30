<?php
namespace App\Service;

use PDO;

class LoreAccessService {
    private $pdo;
    private $docId;
    private $raw;
    private $index = [];
    private $aggregated = [];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Load a document and perform the V8 "Omni-Capture" Aggregation
     */
    public function loadDoc(int $docId) {
        $this->docId = $docId;
        // Reset state for reuse across multiple loadDoc() calls
        $this->raw        = [];
        $this->index      = [];
        $this->aggregated = [];

        $stmt = $this->pdo->prepare("
            SELECT entities, showrunner_analysis, lore_points, thematics, series_bible, summary,
                   narrative_utility, target_collection
            FROM md_doc_analysis 
            WHERE doc_id = ?
        ");
        $stmt->execute([$docId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new \Exception("Document analysis not found");

        // 1. Decode Raw Columns
        $entities   = json_decode($row['entities']            ?? '{}', true) ?? [];
        $showrunner = json_decode($row['showrunner_analysis'] ?? '{}', true) ?? [];
        $lorePoints = json_decode($row['lore_points']         ?? '{}', true) ?? [];
        $thematics  = json_decode($row['thematics']           ?? '{}', true) ?? [];

        if (isset($entities['entities'])) $entities = $entities['entities'];

        // 2. Merge Lore Points into World (Hidden Gems Logic)
        foreach ($this->safeIterable($lorePoints) as $key => $val) {
            if (!empty($val)) {
                $catName = ($key === 'timeline_events') ? 'timeline' : (($key === 'technology_magic') ? 'technology' : $key);
                $list = [];
                if (is_array($val)) {
                    foreach ($val as $v) {
                        if (is_string($v)) $list[] = ['description' => $v, 'name' => 'Entry'];
                        else $list[] = $v;
                    }
                }
                if (!empty($list)) {
                    if (isset($entities[$catName])) {
                        $entities[$catName] = array_merge((array)$entities[$catName], $list);
                    } else {
                        $entities[$catName] = $list;
                    }
                }
            }
        }

        // 3. Structure the Master Object
        $this->raw = [
            'world' => $entities,
            'story' => [
                'episodes'         => $showrunner['episode_concepts'] ?? [],
                'narrative_engine' => $showrunner['narrative_engine'] ? [$showrunner['narrative_engine']] : [],
                'visual_keywords'  => $showrunner['visual_keywords']  ?? [],
                'scene_hooks'      => $showrunner['scene_hooks']      ?? [],
            ],
            'curator' => [
                'bible'              => $row['series_bible']              ?? '',
                'production_notes'   => $showrunner['production_notes']   ?? [],
                'themes'             => $thematics['themes']              ?? [],
                'mood'               => $thematics['mood']                ?? '',
                'summary'            => $row['summary']                   ?? '',
                'narrative_utility'  => (float)($row['narrative_utility'] ?? 0),
                'target_collection'  => $row['target_collection']         ?? '',
            ],
        ];

        // 4. Pre-Aggregate Entities (The V8 Logic)
        $this->processEntities();
    }

    /**
     * Replicates the JS "Omni-Capture" logic to merge raw chunks and flatten attributes
     */
    private function processEntities() {
        if (empty($this->raw['world']) || !is_array($this->raw['world'])) return;

        foreach ($this->safeIterable($this->raw['world']) as $category => $items) {
            if (!is_array($items)) continue;
            foreach ($this->safeIterable($items) as $item) {
                $processed = $this->aggregateSingleEntity($item);
                $processed['category'] = $category;

                if (!isset($this->aggregated[$category]) || !is_array($this->aggregated[$category])) {
                    $this->aggregated[$category] = [];
                }

                $this->aggregated[$category][] = $processed;

                $nameVal = is_string($processed['name'] ?? null) ? $processed['name'] : (string)($processed['title'] ?? 'unknown');
                $key = strtolower(trim($nameVal));
                if ($key === '') $key = uniqid('ent_', true);

                $this->index[$key] = $processed;

                foreach ((array)$processed['aliases'] as $alias) {
                    $a = trim((string)$alias);
                    if ($a === '') continue;
                    $this->index[strtolower($a)] = &$this->index[$key];
                }
            }
        }
    }

    private function aggregateSingleEntity($entity) {
        $final = [
            'name'          => $entity['name'] ?? ($entity['title'] ?? ($entity['event'] ?? 'Unknown')),
            'aliases'       => is_array($entity['aliases'] ?? null) ? $entity['aliases'] : (isset($entity['aliases']) ? (array)$entity['aliases'] : []),
            'roles'         => is_array($entity['roles']   ?? null) ? $entity['roles']   : (isset($entity['roles'])   ? (array)$entity['roles']   : []),
            'attributes'    => is_array($entity['attributes'] ?? null) ? $entity['attributes'] : (isset($entity['attributes']) ? (array)$entity['attributes'] : []),
            'relationships' => [],
            'timeline'      => [],
            'raw_source'    => $entity['raw'] ?? [],
        ];

        $addTime = function($txt, $type, $date = null) use (&$final) {
            if ($txt) $final['timeline'][] = ['text' => $txt, 'type' => $type, 'date' => $date];
        };
        $addRel = function($r) use (&$final) {
            if (!is_array($r)) return;
            if (!isset($r['target']) && !isset($r['entity_2']) && !isset($r['object'])) return;
            $final['relationships'][] = [
                'target' => $r['target'] ?? ($r['entity_2'] ?? $r['object']),
                'type'   => $r['type']   ?? ($r['role']     ?? ''),
                'nature' => $r['nature'] ?? ($r['context']  ?? ''),
                'desc'   => $r['description'] ?? ($r['action'] ?? $r['details'] ?? ''),
            ];
        };

        foreach ($this->safeIterable($entity['actions']       ?? []) as $a) { $addTime($a, 'action'); }
        foreach ($this->safeIterable($entity['events']        ?? []) as $e) {
            $eventText = is_array($e) ? ($e['event'] ?? $e['name'] ?? '') : (string)$e;
            $eventDate = is_array($e) ? ($e['time']  ?? $e['chapter'] ?? '') : null;
            $addTime($eventText, 'event', $eventDate);
        }
        foreach ($this->safeIterable($entity['history']       ?? []) as $h) {
            $histText = is_array($h) ? ($h['event'] ?? '') : (string)$h;
            $histDate = is_array($h) ? ($h['time']  ?? '') : null;
            $addTime($histText, 'history', $histDate);
        }
        foreach ($this->safeIterable($entity['relationships'] ?? []) as $r) { $addRel($r); }

        $exclude = ['name','title','id','type','raw','entities','relationships','actions','events','history','aliases','roles'];
        foreach (is_array($entity) ? $entity : [] as $k => $v) {
            if (!in_array($k, $exclude, true)) {
                $final['attributes'][$k] = $v;
            }
        }

        foreach ($this->safeIterable($entity['raw'] ?? []) as $chunk) {
            if (!is_array($chunk)) continue;

            if (isset($chunk['aliases'])) {
                $final['aliases'] = array_merge($final['aliases'], (array)$chunk['aliases']);
            }
            if (isset($chunk['roles'])) {
                $final['roles'] = array_merge($final['roles'], (array)$chunk['roles']);
            }
            if (isset($chunk['attributes']) && is_array($chunk['attributes'])) {
                foreach ($chunk['attributes'] as $k => $v) {
                    if (isset($final['attributes'][$k])) {
                        if (!is_array($final['attributes'][$k])) $final['attributes'][$k] = [$final['attributes'][$k]];
                        $final['attributes'][$k][] = $v;
                    } else {
                        $final['attributes'][$k] = $v;
                    }
                }
            }
            foreach ($this->safeIterable($chunk['relationships'] ?? []) as $r) { $addRel($r); }
            foreach ($this->safeIterable($chunk['actions']       ?? []) as $a) { $addTime($a, 'action'); }
            foreach ($chunk as $k => $v) {
                if (!in_array($k, $exclude, true) && $k !== 'attributes') {
                    $final['attributes'][$k] = $v;
                }
            }
        }

        $final['aliases'] = array_values(array_unique((array)$final['aliases']));
        $final['roles']   = array_values(array_unique((array)$final['roles']));

        return $final;
    }

    // --- PUBLIC ACCESS METHODS ---

    /**
     * Get filtered entities (e.g., all 'characters' with role 'Protagonist')
     */
    public function queryEntities($category, $roleFilter = null) {
        if (!isset($this->aggregated[$category]) || !is_array($this->aggregated[$category])) return [];

        $results = $this->aggregated[$category];
        if ($roleFilter) {
            return array_values(array_filter($results, function($ent) use ($roleFilter) {
                foreach ((array)($ent['roles'] ?? []) as $r) {
                    if (stripos($r, $roleFilter) !== false) return true;
                }
                if (isset($ent['attributes']['type']) && stripos((string)$ent['attributes']['type'], $roleFilter) !== false) return true;
                return false;
            }));
        }
        return $results;
    }

    /**
     * Get a specific entity by name (fuzzy/alias search)
     */
    public function getEntity($name) {
        $key = strtolower(trim((string)$name));
        return $this->index[$key] ?? null;
    }

    /**
     * Build a context object for an AI Agent about a specific entity
     */
    public function buildAgentContext($entityName) {
        $ent = $this->getEntity($entityName);
        if (!$ent) return null;

        return [
            'identity' => [
                'name'             => $ent['name'],
                'roles'            => array_values((array)$ent['roles']),
                'core_attributes'  => $ent['attributes'],
            ],
            'network' => array_map(function($r) {
                $r = is_array($r) ? $r : [];
                return ($r['target'] ?? 'unknown') . ' (' . ($r['type'] ?? '') . '): ' . ($r['desc'] ?? '');
            }, (array)($ent['relationships'] ?? [])),
            'history' => array_map(function($t) {
                $t = is_array($t) ? $t : ['text' => (string)$t, 'date' => ''];
                return (!empty($t['date']) ? "[{$t['date']}] " : "") . ($t['text'] ?? '');
            }, (array)($ent['timeline'] ?? [])),
        ];
    }

    /**
     * Get high-level story data (episodes, narrative engine, visual keywords, scene hooks)
     */
    public function getStoryEngine(): array {
        return $this->raw['story'] ?? ['episodes' => [], 'narrative_engine' => [], 'visual_keywords' => [], 'scene_hooks' => []];
    }

    /**
     * Get curator data: summary, bible, themes, mood, production_notes, narrative_utility
     * Used by lore_focused_export to build the per-document export object.
     */
    public function getCuratorData(): array {
        return $this->raw['curator'] ?? [];
    }

    /**
     * Get the fully aggregated world entities keyed by category.
     * This is the V8 omni-capture result: characters, locations, etc. fully merged.
     * Used by lore_focused_export to include structured lore per document.
     */
    public function getWorldData(): array {
        return $this->aggregated;
    }

    /**
     * Ensure the supplied value is iterable for use in foreach.
     */
    protected function safeIterable($v) {
        if ($v instanceof \Traversable) return $v;
        if (is_array($v)) return $v;
        if (is_string($v)) {
            $maybe = json_decode($v, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($maybe)) {
                return $maybe;
            }
            return [];
        }
        return [];
    }
}
