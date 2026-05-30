<?php
// public/api/fuzz_forge_api.php
// ─────────────────────────────────────────────────────────────────────────────
// FUZZ FORGE API
// Lore Concept Consolidation & Reference Aggregation System — backend handler.
//
// Tables used (WRITTEN TO):
//   fuzz_candidates  — staging concept dossiers
//   fuzz_candidate_aliases - candidate alias names
//   fuzz_mentions    — raw mention evidence
//   fuzz_links       — fuzzy provisional relationships between candidates
//   fuzz_reviews     — human review decisions
//   fuzz_resolutions — final resolution records (created on promote/canonize)
//
// Tables used (READ ONLY - NO AUTOMATED WRITES ALLOWED):
//   kg_nodes, kg_node_items, kg_categories
//   sketches, sketch_analysis, sketch_lore_history, sketch_ingredients
//   characters, animas, locations, backgrounds, artifacts, vehicles
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../env_locals.php';

use App\Core\PyApiVectorService;
use App\Core\PyApiFuzzService;
use App\Core\PyApiLocalIngestService;

header('Content-Type: application/json');

function jsonOk($data = null) {
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}
function jsonErr($msg, $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// ── Normalise a raw string for grouping ──────────────────────────────────────
function normalizeText(string $text): string {
    $t = mb_strtolower(trim($text));
    $t = preg_replace('/[^a-z0-9 ]/u', ' ', $t);
    $t = preg_replace('/\s+/', ' ', $t);
    return trim($t);
}

// ─────────────────────────────────────────────────────────────────────────────

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErr('Method Not Allowed', 405);

    $raw = file_get_contents('php://input');
    $req = json_decode($raw, true);
    if (!$req || !isset($req['action'])) jsonErr('Invalid JSON or missing action');

    $action = $req['action'];
    global $pdo;

    switch ($action) {

        // ── LIST CANDIDATES ────────────────────────────────────────────────
        case 'list_candidates': {
            $tab    = $req['tab']    ?? 'candidates';
            $filter = $req['filter'] ?? 'all';
            $search = trim($req['search'] ?? '');
            
            // Sorting & Pagination
            $sort   = $req['sort']   ?? null;
            $dir    = strtolower($req['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
            $page   = max(1, (int)($req['page'] ?? 1));
            $limit  = 400;
            $offset = ($page - 1) * $limit;

            $where  = [];
            $params = [];

            if ($tab === 'resolved') {
                $where[] = "c.status IN ('promoted','canonized')";
            } elseif ($tab === 'rejected') {
                $where[] = "c.status = 'rejected'";
            } else {
                $where[] = "c.status NOT IN ('rejected','promoted','canonized')";
            }

            if ($filter !== 'all') {
                $where[] = "c.concept_type = :ctype";
                $params['ctype'] = $filter;
            }
            if ($search !== '') {
                $where[] = "(c.label LIKE :search OR c.notes LIKE :search2)";
                $params['search']  = '%' . $search . '%';
                $params['search2'] = '%' . $search . '%';
            }

            $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $allowedSorts = [
                'id' => 'c.id',
                'label' => 'c.label',
                'concept_type' => 'c.concept_type',
                'status' => 'c.status',
                'mention_count' => 'mention_count',
                'confidence' => 'c.confidence',
                'kg_node_id' => 'c.kg_node_id'
            ];

            // Default fallback sort is heavily by mentions DESC to surface the most relevant concepts
            $orderBy = "ORDER BY mention_count DESC, c.updated_at DESC";
            if ($sort && isset($allowedSorts[$sort])) {
                $orderBy = "ORDER BY {$allowedSorts[$sort]} {$dir}, c.updated_at DESC";
            }

            // Get total count for THIS tab
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM fuzz_candidates c {$whereSQL}");
            $countStmt->execute($params);
            $tabCount = (int)$countStmt->fetchColumn();
            $totalPages = max(1, ceil($tabCount / $limit));

            $stmt = $pdo->prepare("
                SELECT c.id, c.label, c.concept_type, c.status, c.confidence, c.kg_node_id,
                       (SELECT COUNT(*) FROM fuzz_mentions m WHERE m.candidate_id = c.id) as mention_count
                FROM fuzz_candidates c
                {$whereSQL}
                {$orderBy}
                LIMIT {$limit} OFFSET {$offset}
            ");
            $stmt->execute($params);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Global Counts (for the top header)
            $totalStmt   = $pdo->query("SELECT COUNT(*) FROM fuzz_candidates");
            $pendingStmt = $pdo->query("SELECT COUNT(*) FROM fuzz_candidates WHERE status IN ('extracted','grouped')");

            jsonOk([
                'candidates'   => $candidates,
                'total_count'  => (int)$totalStmt->fetchColumn(),
                'pending_count'=> (int)$pendingStmt->fetchColumn(),
                'tab_count'    => $tabCount,
                'total_pages'  => $totalPages,
                'current_page' => $page
            ]);
        }

        // ── GET CANDIDATE ──────────────────────────────────────────────────
        case 'get_candidate': {
            $id = (int)($req['id'] ?? 0);
            if (!$id) jsonErr('Missing id');

            $stmt = $pdo->prepare("SELECT * FROM fuzz_candidates WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$candidate) jsonErr('Candidate not found');

            // Aliases
            $aStmt = $pdo->prepare("SELECT alias FROM fuzz_candidate_aliases WHERE candidate_id = :id ORDER BY id ASC");
            $aStmt->execute(['id' => $id]);
            $aliases = array_column($aStmt->fetchAll(PDO::FETCH_ASSOC), 'alias');

            // Mentions
            $mStmt = $pdo->prepare("SELECT * FROM fuzz_mentions WHERE candidate_id = :id ORDER BY id DESC LIMIT 200");
            $mStmt->execute(['id' => $id]);
            $mentions = $mStmt->fetchAll(PDO::FETCH_ASSOC);

            // Read-only enrichment for UI:
            // Resolve source entity names for mention rows so the landing page can display
            // proper entity names even when the mention came through a chain.
            $entityIds = [];

            foreach ($mentions as $m) {
                $sourceTable = $m['source_table'] ?? '';
                $sourceRowId = (int)($m['source_row_id'] ?? 0);
                if (!$sourceRowId) continue;

                if (in_array($sourceTable, ['sketches', 'sketch_analysis', 'sketch_lore_history', 'sketch_ingredients'], true)) {
                    $entityIds['sketches'][$sourceRowId] = true;
                } else {
                    $entityIds[$sourceTable][$sourceRowId] = true;
                }
            }

            $entityNameMap = [];
            foreach ($entityIds as $table => $ids) {
                // Ensure $table is safe (whitelist)
                $safeTables = ['sketches', 'kg_nodes', 'characters', 'animas', 'locations', 'backgrounds', 'artifacts', 'vehicles'];
                if (!in_array($table, $safeTables, true)) continue;
                
                if (!empty($ids)) {
                    $in = implode(',', array_map('intval', array_keys($ids)));
                    try {
                        $rows = $pdo->query("SELECT id, name FROM {$table} WHERE id IN ($in)")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($rows as $row) {
                            $entityNameMap[$table][(int)$row['id']] = $row['name'] ?? null;
                        }
                    } catch (Exception $e) { }
                }
            }

            foreach ($mentions as &$m) {
                $sourceTable = $m['source_table'] ?? '';
                $sourceRowId = (int)($m['source_row_id'] ?? 0);
                $resolvedName = null;

                $lookupTable = $sourceTable;
                if (in_array($sourceTable, ['sketch_analysis', 'sketch_lore_history', 'sketch_ingredients'], true)) {
                    $lookupTable = 'sketches';
                }

                $resolvedName = $entityNameMap[$lookupTable][$sourceRowId] ?? null;

                if ($resolvedName !== null && $resolvedName !== '') {
                    $m['source_entity_name'] = $resolvedName;
                }
            }
            unset($m);

            // Frames (derived from mapped entity mentions safely)
            $frames = [];
            $entityFramesQuery = [];
            $validEntities = ['characters', 'character_poses', 'character_anima_poses', 'character_expressions', 'animas', 'locations', 'backgrounds', 'artifacts', 'vehicles', 'scene_parts', 'controlnet_maps', 'spawns', 'generatives', 'sketches', 'prompt_matrix_blueprints', 'composites', 'animatics'];
            
            $kgNodeIdsToFetch = [];
            if (!empty($candidate['kg_node_id'])) {
                $kgNodeIdsToFetch[] = (int)$candidate['kg_node_id'];
            }

            foreach($mentions as $m) {
                $st = $m['source_table'];
                $sid = $m['source_row_id'];
                if ($sid) {
                    $mappedTable = $st;
                    if (in_array($st, ['sketch_analysis', 'sketch_ingredients', 'sketch_lore_history'])) {
                        $mappedTable = 'sketches';
                    }
                    if (in_array($mappedTable, $validEntities)) {
                        $mapTable = "frames_2_" . $mappedTable;
                        $entityFramesQuery[$mapTable][] = (int)$sid;
                    } elseif ($st === 'kg_nodes') {
                        $kgNodeIdsToFetch[] = (int)$sid;
                    }
                }
            }
            
            foreach ($entityFramesQuery as $mapTable => $sids) {
                $sids = array_unique($sids);
                if (empty($sids)) continue;
                $in = implode(',', $sids);
                try {
                    $fSql = "SELECT f.id, f.filename, f.entity_type, f.entity_id
                             FROM frames f 
                             JOIN {$mapTable} m ON f.id = m.from_id 
                             WHERE m.to_id IN ($in)";
                    $fRows = $pdo->query($fSql)->fetchAll(PDO::FETCH_ASSOC);
                    foreach($fRows as $fr) {
                        $frames[$fr['id'] . '_' . $fr['entity_type'] . '_' . $fr['entity_id']] = $fr; 
                    }
                } catch (Exception $e) { } // Ignore safely if mapping table structure varies
            }

            // NEW LOGIC: Fetch frames for kg_nodes via sketch_lore_history
            $kgNodeIdsToFetch = array_unique($kgNodeIdsToFetch);
            if (!empty($kgNodeIdsToFetch)) {
                $inKg = implode(',', $kgNodeIdsToFetch);
                try {
                    $kgNames = $pdo->query("SELECT id, name FROM kg_nodes WHERE id IN ($inKg)")->fetchAll(PDO::FETCH_KEY_PAIR);
                    foreach ($kgNames as $kId => $kName) {
                        if (!$kName) continue;
                        $stmtHist = $pdo->prepare("SELECT sketch_id FROM sketch_lore_history WHERE LOWER(entity_name) = LOWER(?)");
                        $stmtHist->execute([$kName]);
                        $sketchIds = array_unique($stmtHist->fetchAll(PDO::FETCH_COLUMN));
                        
                        if (!empty($sketchIds)) {
                            $inSketches = implode(',', $sketchIds);
                            $fSql = "SELECT f.id, f.filename, 'kg_nodes' as entity_type, ? as entity_id
                                     FROM frames f 
                                     WHERE (f.entity_type = 'sketches' AND f.entity_id IN ($inSketches))
                                        OR f.id IN (SELECT from_id FROM frames_2_sketches WHERE to_id IN ($inSketches))";
                            $fStmt = $pdo->prepare($fSql);
                            $fStmt->execute([$kId]);
                            $fRows = $fStmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach($fRows as $fr) {
                                $frames[$fr['id'] . '_kg_nodes_' . $kId] = $fr; 
                            }
                        }
                    }
                } catch (Exception $e) { }
            }

            $frames = array_values($frames);

            // Links (with target label)
            $lStmt = $pdo->prepare("
                SELECT fl.*, fc.label as target_label
                FROM fuzz_links fl
                LEFT JOIN fuzz_candidates fc ON fc.id = fl.target_candidate_id
                WHERE fl.candidate_id = :id
                ORDER BY fl.confidence DESC
            ");
            $lStmt->execute(['id' => $id]);
            $links = $lStmt->fetchAll(PDO::FETCH_ASSOC);

            // Reviews
            $rStmt = $pdo->prepare("SELECT * FROM fuzz_reviews WHERE candidate_id = :id ORDER BY created_at DESC LIMIT 50");
            $rStmt->execute(['id' => $id]);
            $reviews = $rStmt->fetchAll(PDO::FETCH_ASSOC);

            // KG node if linked
            $kgNode = null;
            if ($candidate['kg_node_id']) {
                $kgStmt = $pdo->prepare("SELECT id, name, node_type, description FROM kg_nodes WHERE id = :id");
                $kgStmt->execute(['id' => $candidate['kg_node_id']]);
                $kgNode = $kgStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            jsonOk([
                'candidate' => $candidate,
                'aliases'   => $aliases,
                'mentions'  => $mentions,
                'frames'    => $frames,
                'links'     => $links,
                'reviews'   => $reviews,
                'kg_node'   => $kgNode
            ]);
        }

        // ── SAVE CANDIDATE ─────────────────────────────────────────────────
        case 'save_candidate': {
            $id         = (int)($req['id'] ?? 0);
            $label      = trim($req['label'] ?? '');
            $type       = $req['concept_type'] ?? null;
            $status     = $req['status'] ?? 'extracted';
            $confidence = max(0, min(100, (int)($req['confidence'] ?? 50)));
            $notes      = $req['notes'] ?? null;
            $kgNodeId   = $req['kg_node_id'] ? (int)$req['kg_node_id'] : null;
            $aliases    = $req['aliases'] ?? [];

            if (!$label) jsonErr('Label is required');

            $allowedStatus = ['extracted','grouped','reviewed','promoted','canonized','rejected','deferred'];
            if (!in_array($status, $allowedStatus)) $status = 'extracted';

            $pdo->beginTransaction();
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE fuzz_candidates
                        SET label = :label, concept_type = :concept_type, status = :status,
                            confidence = :confidence, notes = :notes, kg_node_id = :kg_node_id,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'label'        => $label,
                        'concept_type' => $type ?: null,
                        'status'       => $status,
                        'confidence'   => $confidence,
                        'notes'        => $notes ?: null,
                        'kg_node_id'   => $kgNodeId,
                        'id'           => $id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO fuzz_candidates (label, concept_type, status, confidence, notes, kg_node_id)
                        VALUES (:label, :concept_type, :status, :confidence, :notes, :kg_node_id)
                    ");
                    $stmt->execute([
                        'label'        => $label,
                        'concept_type' => $type ?: null,
                        'status'       => $status,
                        'confidence'   => $confidence,
                        'notes'        => $notes ?: null,
                        'kg_node_id'   => $kgNodeId
                    ]);
                    $id = (int)$pdo->lastInsertId();
                }

                // Rebuild aliases
                $pdo->prepare("DELETE FROM fuzz_candidate_aliases WHERE candidate_id = :id")->execute(['id' => $id]);
                if ($aliases) {
                    $aliasStmt = $pdo->prepare("INSERT INTO fuzz_candidate_aliases (candidate_id, conditional) VALUES (:cid, :alias)");
                    foreach (array_unique(array_filter($aliases, fn($a) => trim($a) !== '')) as $alias) {
                        // wait, previous code has 'alias' column... oh wait. 
                        // It is indeed 'alias'.
                        $aliasStmt = $pdo->prepare("INSERT INTO fuzz_candidate_aliases (candidate_id, alias) VALUES (:cid, :alias)");
                        $aliasStmt->execute(['cid' => $id, 'alias' => trim($alias)]);
                    }
                }

                // If kg_node_id changed and status is promoted/canonized, record a resolution
                if ($kgNodeId && in_array($status, ['promoted', 'canonized'])) {
                    $existing = $pdo->prepare("SELECT id FROM fuzz_resolutions WHERE candidate_id = :cid AND kg_node_id = :kgid");
                    $existing->execute(['cid' => $id, 'kgid' => $kgNodeId]);
                    if (!$existing->fetch()) {
                        $pdo->prepare("
                            INSERT INTO fuzz_resolutions (candidate_id, kg_node_id, outcome)
                            VALUES (:cid, :kgid, :outcome)
                        ")->execute(['cid' => $id, 'kgid' => $kgNodeId, 'outcome' => $status]);
                    }
                }

                $pdo->commit();
                jsonOk(['id' => $id]);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        // ── DELETE CANDIDATE ───────────────────────────────────────────────
        case 'delete_candidate': {
            $id = (int)($req['id'] ?? 0);
            if (!$id) jsonErr('Missing id');
            $pdo->beginTransaction();
            try {
                $pdo->prepare("DELETE FROM fuzz_mentions WHERE candidate_id = :id")->execute(['id' => $id]);
                $pdo->prepare("DELETE FROM fuzz_links WHERE candidate_id = :id OR target_candidate_id = :id2")->execute(['id' => $id, 'id2' => $id]);
                $pdo->prepare("DELETE FROM fuzz_reviews WHERE candidate_id = :id")->execute(['id' => $id]);
                $pdo->prepare("DELETE FROM fuzz_resolutions WHERE candidate_id = :id")->execute(['id' => $id]);
                $pdo->prepare("DELETE FROM fuzz_candidate_aliases WHERE candidate_id = :id")->execute(['id' => $id]);
                $pdo->prepare("DELETE FROM fuzz_candidates WHERE id = :id")->execute(['id' => $id]);
                $pdo->commit();
                jsonOk();
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        // ── ADD MENTION ────────────────────────────────────────────────────
        case 'add_mention': {
            $candidateId = (int)($req['candidate_id'] ?? 0);
            $text        = trim($req['extracted_text'] ?? '');
            if (!$candidateId || !$text) jsonErr('Missing candidate_id or extracted_text');

            $stmt = $pdo->prepare("
                INSERT INTO fuzz_mentions
                    (candidate_id, source_table, source_row_id, source_field, mention_type, extracted_text, normalized_text, context_snippet)
                VALUES
                    (:cid, :src_table, :src_row, :src_field, :mtype, :text, :norm, :ctx)
            ");
            $stmt->execute([
                'cid'       => $candidateId,
                'src_table' => $req['source_table'] ?? 'manual',
                'src_row'   => $req['source_row_id'] ? (int)$req['source_row_id'] : null,
                'src_field' => $req['source_field'] ?? null,
                'mtype'     => $req['mention_type'] ?? 'manual',
                'text'      => $text,
                'norm'      => normalizeText($text),
                'ctx'       => $req['context_snippet'] ?? null
            ]);
            jsonOk(['id' => (int)$pdo->lastInsertId()]);
        }

        // ── DELETE MENTION ─────────────────────────────────────────────────
        case 'delete_mention': {
            $id = (int)($req['id'] ?? 0);
            if (!$id) jsonErr('Missing id');
            $pdo->prepare("DELETE FROM fuzz_mentions WHERE id = :id")->execute(['id' => $id]);
            jsonOk();
        }

        // ── ADD LINK ───────────────────────────────────────────────────────
        case 'add_link': {
            $cid    = (int)($req['candidate_id'] ?? 0);
            $target = (int)($req['target_candidate_id'] ?? 0);
            if (!$cid || !$target) jsonErr('Missing candidate_id or target_candidate_id');
            if ($cid === $target) jsonErr('Cannot link a candidate to itself');

            $stmt = $pdo->prepare("
                INSERT INTO fuzz_links (candidate_id, target_candidate_id, relationship_type, confidence, note)
                VALUES (:cid, :target, :rel, :conf, :note)
            ");
            $stmt->execute([
                'cid'    => $cid,
                'target' => $target,
                'rel'    => $req['relationship_type'] ?? 'may_refer_to',
                'conf'   => max(0, min(100, (int)($req['confidence'] ?? 50))),
                'note'   => $req['note'] ?? null
            ]);
            jsonOk(['id' => (int)$pdo->lastInsertId()]);
        }

        // ── DELETE LINK ────────────────────────────────────────────────────
        case 'delete_link': {
            $id = (int)($req['id'] ?? 0);
            if (!$id) jsonErr('Missing id');
            $pdo->prepare("DELETE FROM fuzz_links WHERE id = :id")->execute(['id' => $id]);
            jsonOk();
        }

        // ── ADD REVIEW ─────────────────────────────────────────────────────
        case 'add_review': {
            $cid      = (int)($req['candidate_id'] ?? 0);
            $decision = trim($req['decision'] ?? '');
            if (!$cid || !$decision) jsonErr('Missing candidate_id or decision');

            $allowed = ['confirmed','rejected','deferred','split','promoted','linked','unresolved'];
            if (!in_array($decision, $allowed)) jsonErr('Invalid decision');

            $stmt = $pdo->prepare("
                INSERT INTO fuzz_reviews (candidate_id, decision, note)
                VALUES (:cid, :dec, :note)
            ");
            $stmt->execute(['cid' => $cid, 'dec' => $decision, 'note' => $req['note'] ?? null]);

            // Also update candidate status to reflect the decision
            $statusMap = [
                'confirmed' => 'reviewed',
                'rejected'  => 'rejected',
                'deferred'  => 'deferred',
                'promoted'  => 'promoted',
                'linked'    => 'promoted',
            ];
            if (isset($statusMap[$decision])) {
                $pdo->prepare("UPDATE fuzz_candidates SET status = :s, updated_at = NOW() WHERE id = :id")
                    ->execute(['s' => $statusMap[$decision], 'id' => $cid]);
            }
            jsonOk(['review_id' => (int)$pdo->lastInsertId()]);
        }

        // ── SEARCH KG NODES ────────────────────────────────────────────────
        case 'search_kg_nodes': {
            $q = trim($req['q'] ?? '');
            if (strlen($q) < 2) jsonOk([]);

            $stmt = $pdo->prepare("
                SELECT id, name, node_type, description
                FROM kg_nodes
                WHERE status = 'active' AND (name LIKE :q OR description LIKE :q2)
                ORDER BY name ASC
                LIMIT 20
            ");
            $stmt->execute(['q' => '%' . $q . '%', 'q2' => '%' . $q . '%']);
            jsonOk($stmt->fetchAll(PDO::FETCH_ASSOC));
        }


        // ── SEARCH CANDIDATES ──────────────────────────────────────────────
        case 'search_candidates': {
            $q = trim($req['q'] ?? '');
            $sort = $req['sort'] ?? 'label'; // Default to original sort for backward compatibility

            if (strlen($q) < 2) jsonOk([]);

            if ($sort === 'connections') {
                $stmt = $pdo->prepare("
                    SELECT c.id, c.label, c.concept_type, c.status,
                           (SELECT COUNT(*) FROM fuzz_mentions m WHERE m.candidate_id = c.id) as mention_count,
                           (SELECT COUNT(*) FROM fuzz_links fl WHERE fl.candidate_id = c.id OR fl.target_candidate_id = c.id) as link_count
                    FROM fuzz_candidates c
                    WHERE c.label LIKE :q
                    ORDER BY (mention_count + link_count) DESC, c.label ASC
                    LIMIT 30
                ");
            } else {
                // Original backward-compatible query
                $stmt = $pdo->prepare("
                    SELECT id, label, concept_type, status
                    FROM fuzz_candidates
                    WHERE label LIKE :q
                    ORDER BY label ASC
                    LIMIT 20
                ");
            }

            $stmt->execute(['q' => '%' . $q . '%']);
            jsonOk($stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // ── SEMANTIC SEARCH via App\Core\PyApiVectorService ────────────────
        case 'semantic_search': {
            $candidateId = (int)($req['candidate_id'] ?? 0);
            if (!$candidateId) jsonErr('Missing candidate_id');

            // Load the candidate label + aliases as the query text
            $cStmt = $pdo->prepare("SELECT label FROM fuzz_candidates WHERE id = :id");
            $cStmt->execute(['id' => $candidateId]);
            $cand = $cStmt->fetch(PDO::FETCH_ASSOC);
            if (!$cand) jsonErr('Candidate not found');

            $aStmt = $pdo->prepare("SELECT alias FROM fuzz_candidate_aliases WHERE candidate_id = :id");
            $aStmt->execute(['id' => $candidateId]);
            $aliases = array_column($aStmt->fetchAll(PDO::FETCH_ASSOC), 'alias');

            $queryText = $cand['label'];
            if ($aliases) $queryText .= ' ' . implode(' ', $aliases);

            // Using PyApiVectorService (READ-ONLY search)
            $vectorService = new PyApiVectorService();
            
            // Querying your defined sage_sketches_nu collection
            $res = $vectorService->query($queryText, null, 'sage_sketches_nu', 'text', 20);

            if (isset($res['ok']) && $res['ok'] === false) {
                jsonErr('PyAPI error: ' . ($res['error'] ?? 'Unknown'));
            }

            if (!isset($res['result']['ids'][0]) || empty($res['result']['ids'][0])) {
                jsonOk(['matches' => [], 'query' => $queryText]);
            }

            // Normalize matches exactly as the frontend expects
            $matches = [];
            $ids = $res['result']['ids'][0];
            $distances = $res['result']['distances'][0] ?? [];
            $metadatas = $res['result']['metadatas'][0] ?? [];
            $documents = $res['result']['documents'][0] ?? [];

            foreach ($ids as $idx => $rawId) {
                $meta = $metadatas[$idx] ?? [];
                $dist = $distances[$idx] ?? 1.0;
                $score = max(0, 1 - $dist);

                // Prefer db_id or sketch_id from metadata, fallback to regexing it from the ID string
                $sourceId = $meta['sketch_id'] ?? $meta['db_id'] ?? null;
                if (!$sourceId && preg_match('/sketch_(\d+)/', $rawId, $m)) {
                    $sourceId = $m[1];
                }

                $matches[] = [
                    'id'           => $rawId,
                    'label'        => $meta['name'] ?? $meta['title'] ?? 'Sketch #'.$sourceId,
                    'score'        => $score,
                    'source_table' => 'sketches',
                    'source_id'    => $sourceId,
                    'snippet'      => $documents[$idx] ?? null
                ];
            }

            // Deduplicate chunks (multiple chunks for same sketch_id) keeping the best score
            $uniqueMatches = [];
            foreach ($matches as $m) {
                if (!$m['source_id']) continue;
                if (!isset($uniqueMatches[$m['source_id']]) || $m['score'] > $uniqueMatches[$m['source_id']]['score']) {
                    $uniqueMatches[$m['source_id']] = $m;
                }
            }
            $matches = array_values($uniqueMatches);
            
            // Sort by score desc
            usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

            jsonOk(['matches' => array_slice($matches, 0, 20), 'query' => $queryText]);
        }

        // ── RUN EXTRACTION (Phase 1: extract → fuzz_queue → submit PyAPI job) ──
        case 'run_extraction': {
            // Stream-based response: each line is a JSON object {type, msg}
            header('Content-Type: text/plain; charset=utf-8');
            header('X-Accel-Buffering: no');
            header('Cache-Control: no-cache');
            if (ob_get_level()) ob_end_clean();

            set_time_limit(0);

            $config = $req['config'] ?? [];

            $srcSketches    = !empty($config['src_sketches']);
            $srcAnalysis    = !empty($config['src_analysis']);
            $srcLore        = !empty($config['src_lore']);
            $srcKG          = !empty($config['src_kg']);
            $srcIngredients = !empty($config['src_ingredients']);
            $maxCandidates  = max(1, min(500000, (int)($config['max_candidates'] ?? 500000))); // Limitless
            $skipExisting   = !empty($config['skip_existing']);
            $threshold      = max(0.5, min(0.99, (float)($config['threshold'] ?? 0.82)));

            function streamLine(string $msg, string $type = 'info'): void {
                echo json_encode(['type' => $type, 'msg' => $msg]) . "\n";
                if (ob_get_level()) ob_flush();
                flush();
            }

            streamLine('Extraction started — ' . date('Y-m-d H:i:s'), 'info');

            // Collect raw items: [label, norm, source_table, source_row_id, source_field, mention_type, context]
            $items = [];

            if ($srcSketches) {
                streamLine('Extracting from sketches (Regex noun phrase targeting)…', 'info');
                try {
                    $rows = $pdo->query("SELECT id, name, description FROM sketches LIMIT 200000")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        if ($row['name']) {
                            $items[] = ['label' => $row['name'], 'norm' => normalizeText($row['name']),
                                'source_table' => 'sketches', 'source_row_id' => $row['id'],
                                'source_field' => 'name', 'mention_type' => 'sketch_name',
                                'context' => null];
                        }
                        if ($row['description']) {
                            $desc = strip_tags($row['description']);
                            
                            // Regex matching Proper Noun Phrases (2+ Capitalized words, e.g. "Crater City Mainframe" or "Dr. Chen")
                            if (preg_match_all('/\b([A-Z][a-z\.]+(?:\s+[A-Z][a-z\.]+)+)\b/u', $desc, $matches)) {
                                $extracted = array_unique($matches[1]);
                                foreach ($extracted as $entityName) {
                                    if (mb_strlen($entityName) < 4 || mb_strlen($entityName) > 60) continue;
                                    
                                    // Provide a short context snippet around the matched entity
                                    $pos = mb_strpos($desc, $entityName);
                                    $start = max(0, $pos - 30);
                                    $ctxSnippet = mb_substr($desc, $start, mb_strlen($entityName) + 60);
                                    if ($start > 0) $ctxSnippet = '... ' . $ctxSnippet;
                                    
                                    $items[] = ['label' => $entityName, 'norm' => normalizeText($entityName),
                                        'source_table' => 'sketches', 'source_row_id' => $row['id'],
                                        'source_field' => 'description', 'mention_type' => 'sketch_desc',
                                        'context' => trim($ctxSnippet)];
                                }
                            }
                        }
                    }
                    streamLine('  → Processed sketches successfully', 'ok');
                } catch (Exception $e) {
                    streamLine('  Sketches error: ' . $e->getMessage(), 'warn');
                }
            }

            // Extract from standard entities (Characters, Animas, Locations, Backgrounds, Artifacts, Vehicles)
            // No LIMIT to ensure we get ALL guaranteed core entities
            $additionalEntities = ['characters', 'animas', 'locations', 'backgrounds', 'artifacts', 'vehicles'];
            foreach ($additionalEntities as $tbl) {
                if (!empty($config["src_{$tbl}"])) {
                    streamLine("Extracting from {$tbl} (Regex noun phrase targeting)…", 'info');
                    try {
                        $rows = $pdo->query("SELECT id, name, description FROM {$tbl}")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($rows as $row) {
                            if ($row['name']) {
                                $items[] = ['label' => $row['name'], 'norm' => normalizeText($row['name']),
                                    'source_table' => $tbl, 'source_row_id' => $row['id'],
                                    'source_field' => 'name', 'mention_type' => $tbl . '_name',
                                    'context' => null];
                            }
                            if ($row['description']) {
                                $desc = strip_tags($row['description']);
                                
                                if (preg_match_all('/\b([A-Z][a-z\.]+(?:\s+[A-Z][a-z\.]+)+)\b/u', $desc, $matches)) {
                                    $extracted = array_unique($matches[1]);
                                    foreach ($extracted as $entityName) {
                                        if (mb_strlen($entityName) < 4 || mb_strlen($entityName) > 60) continue;
                                        
                                        $pos = mb_strpos($desc, $entityName);
                                        $start = max(0, $pos - 30);
                                        $ctxSnippet = mb_substr($desc, $start, mb_strlen($entityName) + 60);
                                        if ($start > 0) $ctxSnippet = '... ' . $ctxSnippet;
                                        
                                        $items[] = ['label' => $entityName, 'norm' => normalizeText($entityName),
                                            'source_table' => $tbl, 'source_row_id' => $row['id'],
                                            'source_field' => 'description', 'mention_type' => $tbl . '_desc',
                                            'context' => trim($ctxSnippet)];
                                    }
                                }
                            }
                        }
                        streamLine("  → Processed {$tbl} successfully", 'ok');
                    } catch (Exception $e) {
                        streamLine("  {$tbl} error: " . $e->getMessage(), 'warn');
                    }
                }
            }

            if ($srcAnalysis) {
                streamLine('Extracting from sketch_analysis…', 'info');
                try {
                    $rows = $pdo->query("
                        SELECT sa.id, sa.sketch_id, sa.entities, sa.thematics, s.name as sketch_name
                        FROM sketch_analysis sa
                        JOIN sketches s ON s.id = sa.sketch_id
                        WHERE sa.entities IS NOT NULL OR sa.thematics IS NOT NULL
                        LIMIT 200000
                    ")->fetchAll(PDO::FETCH_ASSOC);

                    $entCount = 0;
                    foreach ($rows as $row) {
                        if ($row['entities']) {
                            $decoded = json_decode($row['entities'], true);
                            if (is_array($decoded)) {
                                foreach (['characters', 'locations', 'artifacts', 'events', 'concepts'] as $cat) {
                                    if (!empty($decoded[$cat]) && is_array($decoded[$cat])) {
                                        foreach ($decoded[$cat] as $item) {
                                            $entName = is_string($item) ? $item : ($item['name'] ?? '');
                                            if (is_string($entName) && trim($entName)) {
                                                $items[] = ['label' => trim($entName), 'norm' => normalizeText($entName),
                                                    'source_table' => 'sketch_analysis', 'source_row_id' => $row['sketch_id'],
                                                    'source_field' => 'entities', 'mention_type' => 'analysis_entity',
                                                    'context' => 'Type [' . $cat . '] from sketch: ' . $row['sketch_name']];
                                                $entCount++;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if ($row['thematics']) {
                            $decoded = json_decode($row['thematics'], true);
                            if (is_array($decoded) && !empty($decoded['primary_themes']) && is_array($decoded['primary_themes'])) {
                                foreach ($decoded['primary_themes'] as $th) {
                                    $thName = is_string($th) ? $th : ($th['name'] ?? $th['theme'] ?? '');
                                    if (!is_string($thName) || !trim($thName)) continue;
                                    $items[] = ['label' => trim($thName), 'norm' => normalizeText($thName),
                                        'source_table' => 'sketch_analysis', 'source_row_id' => $row['sketch_id'],
                                        'source_field' => 'thematics', 'mention_type' => 'analysis_thematic',
                                        'context' => 'Theme from: ' . $row['sketch_name']];
                                }
                            }
                        }
                    }
                    streamLine('  → ' . $entCount . ' entities + thematics extracted from ' . count($rows) . ' analyses', 'ok');
                } catch (Exception $e) {
                    streamLine('  sketch_analysis error: ' . $e->getMessage(), 'warn');
                }
            }

            if ($srcLore) {
                streamLine('Extracting from sketch_lore_history…', 'info');
                try {
                    $rows = $pdo->query("
                        SELECT id, sketch_id, entity_name, entity_type, prompt_used
                        FROM sketch_lore_history
                        WHERE entity_name IS NOT NULL AND entity_name != ''
                        LIMIT 200000
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        $ctx = $row['entity_type'] ? '[' . $row['entity_type'] . '] Lore lineage for sketch #' . $row['sketch_id'] : null;
                        $items[] = ['label' => $row['entity_name'], 'norm' => normalizeText($row['entity_name']),
                            'source_table' => 'sketch_lore_history', 'source_row_id' => $row['sketch_id'],
                            'source_field' => 'entity_name', 'mention_type' => 'lore_history',
                            'context' => $ctx];
                    }
                    streamLine('  → ' . count($rows) . ' lore history entries processed', 'ok');
                } catch (Exception $e) {
                    streamLine('  sketch_lore_history error: ' . $e->getMessage(), 'warn');
                }
            }

            if ($srcKG) {
                streamLine('Extracting from kg_nodes…', 'info');
                try {
                    $rows = $pdo->query("
                        SELECT id, name, description, node_type
                        FROM kg_nodes
                        WHERE status = 'active' AND name IS NOT NULL AND name != ''
                        LIMIT 200000
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        $items[] = ['label' => $row['name'], 'norm' => normalizeText($row['name']),
                            'source_table' => 'kg_nodes', 'source_row_id' => $row['id'],
                            'source_field' => 'name', 'mention_type' => 'kg_node',
                            'context' => $row['description'] ? mb_substr($row['description'], 0, 150) : null];
                    }
                    streamLine('  → ' . count($rows) . ' KG nodes processed', 'ok');
                } catch (Exception $e) {
                    streamLine('  kg_nodes error: ' . $e->getMessage(), 'warn');
                }
            }

            if ($srcIngredients) {
                streamLine('Extracting from sketch_ingredients…', 'info');
                try {
                    $rows = $pdo->query("
                        SELECT id, sketch_id, ingredient_type, prompt_fragment
                        FROM sketch_ingredients
                        WHERE prompt_fragment IS NOT NULL AND prompt_fragment != ''
                        LIMIT 200000
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        $frag = mb_substr(trim($row['prompt_fragment']), 0, 200);
                        if (!$frag) continue;
                        $items[] = ['label' => $frag, 'norm' => normalizeText($frag),
                            'source_table' => 'sketch_ingredients', 'source_row_id' => $row['sketch_id'],
                            'source_field' => 'prompt_fragment', 'mention_type' => 'ingredient',
                            'context' => 'Type: ' . $row['ingredient_type']];
                    }
                    streamLine('  → ' . count($rows) . ' ingredients processed', 'ok');
                } catch (Exception $e) {
                    streamLine('  sketch_ingredients error: ' . $e->getMessage(), 'warn');
                }
            }

            streamLine('Total raw items collected: ' . count($items), 'info');

            if (empty($items)) {
                streamLine('No items to process. Enable at least one source.', 'warn');
                exit;
            }

            // Filter out already-grouped mentions if requested
            if ($skipExisting) {
                $existingNorms = [];
                $existStmt = $pdo->query("SELECT normalized_text FROM fuzz_mentions WHERE normalized_text IS NOT NULL");
                foreach ($existStmt->fetchAll(PDO::FETCH_COLUMN) as $n) {
                    $existingNorms[$n] = true;
                }
                $beforeCount = count($items);
                $items = array_filter($items, fn($i) => !isset($existingNorms[$i['norm']]));
                $items = array_values($items);
                streamLine('Skipped ' . ($beforeCount - count($items)) . ' already-grouped mentions. Remaining: ' . count($items), 'info');
            }

            // Trim to unique norms to avoid sending duplicates to PyAPI
            $uniqueByNorm = [];
            foreach ($items as $item) {
                $uniqueByNorm[$item['norm']] = true;
            }
            $uniqueCount = count($uniqueByNorm);
            streamLine('Unique normalized forms: ' . $uniqueCount, 'info');

            if ($uniqueCount === 0) {
                streamLine('No unique items to cluster. Exiting.', 'warn');
                exit;
            }

            // ── Write ALL items to fuzz_queue (staging table for this job) ──
            // CRITICAL FIX: We must insert ALL original items so no mention is lost
            // when reconstructing groups in apply_clusters.
            streamLine('Writing all items to fuzz_queue (Preserving Mentions)…', 'info');
            
            $pdo->beginTransaction();
            try {
                $pdo->exec("DELETE FROM fuzz_queue"); 
                
                $qCount = 0;
                $batchSize = 1000;
                $batchData = [];
                $placeholders = [];
                
                foreach ($items as $item) {
                    $batchData[] = $item['source_table'];
                    $batchData[] = $item['source_row_id'];
                    $batchData[] = $item['source_field'];
                    $batchData[] = $item['mention_type'];
                    $batchData[] = mb_substr($item['label'], 0, 512);
                    $batchData[] = $item['norm'];
                    $batchData[] = $item['context'] ? mb_substr($item['context'], 0, 500) : null;
                    
                    $placeholders[] = '(?, ?, ?, ?, ?, ?, ?)';
                    $qCount++;
                    
                    if ($qCount % $batchSize === 0) {
                        $sql = "INSERT INTO fuzz_queue 
                                    (source_table, source_row_id, source_field, mention_type, extracted_text, normalized_text, context_snippet) 
                                VALUES " . implode(', ', $placeholders);
                        $pdo->prepare($sql)->execute($batchData);
                        $batchData = [];
                        $placeholders = [];
                    }
                }
                
                // Flush remaining data
                if (!empty($placeholders)) {
                    $sql = "INSERT INTO fuzz_queue 
                                (source_table, source_row_id, source_field, mention_type, extracted_text, normalized_text, context_snippet) 
                            VALUES " . implode(', ', $placeholders);
                    $pdo->prepare($sql)->execute($batchData);
                }
                
                $pdo->commit();
                streamLine("  → {$qCount} individual mention rows staged in fuzz_queue", 'ok');
            } catch (Exception $e) {
                $pdo->rollBack();
                streamLine('  → Error staging items: ' . $e->getMessage(), 'err');
                exit;
            }

            // ── Submit async PyAPI clustering job ────────────────────────
            streamLine('Submitting async clustering job to PyAPI (TF-IDF Sparse, threshold=' . $threshold . ')…', 'stage');

            try {
                $fuzzService = new PyApiFuzzService();

                // Build payload: Only send the unique norms to python!
                $payloadItems = [];
                foreach (array_keys($uniqueByNorm) as $norm) {
                    $payloadItems[] = ['id' => $norm, 'text' => $norm];
                }

                $submitResult = $fuzzService->clusterAsync($payloadItems, $threshold);

                if (empty($submitResult['job_id'])) {
                    streamLine('PyAPI did not return a job_id. Response: ' . json_encode($submitResult), 'err');
                    exit;
                }

                $jobId = (string)$submitResult['job_id'];
                streamLine('JOB_ID:' . $jobId, 'job');
                streamLine("PyAPI job queued. Unique items sent: {$uniqueCount}, Max candidates: {$maxCandidates}", 'ok');
                streamLine('Close this dialog and return later using the resume URL — the tablet will keep clustering.', 'info');

            } catch (Exception $e) {
                streamLine('PyAPI submission failed: ' . $e->getMessage(), 'err');
            }

            exit;
        }

        // ── GET EXTRACTION JOB STATUS (proxy to PyAPI) ─────────────────────
        case 'get_extraction_job_status': {
            $jobId = trim($req['job_id'] ?? '');
            if (!$jobId) jsonErr('Missing job_id');
            try {
                $fuzzService = new PyApiFuzzService();
                $status = $fuzzService->getClusterJobStatus($jobId);
                jsonOk($status);
            } catch (Exception $e) {
                jsonErr('PyAPI status error: ' . $e->getMessage());
            }
        }

        // ── APPLY CLUSTERS (Phase 2: read PyAPI result → trigger Local PyAPI Ingest) ──
        case 'apply_clusters': {
            $jobId         = trim($req['job_id'] ?? '');
            $maxCandidates = max(1, min(500000, (int)($req['max_candidates'] ?? 500000)));

            if (!$jobId) jsonErr('Missing job_id');

            try {
                $fuzzService = new PyApiFuzzService();
                $statusData  = $fuzzService->getClusterJobStatus($jobId);
            } catch (Exception $e) {
                jsonErr('PyAPI error: ' . $e->getMessage());
            }

            $jobStatus = $statusData['status'] ?? '';
            if ($jobStatus !== 'success') {
                jsonErr("Job is not complete (status: {$jobStatus}). Cannot apply clusters yet.");
            }

            $clusters = $statusData['clusters'] ?? [];

            // Trigger the internal background ingestion
            try {
                $ingestService = new PyApiLocalIngestService();
                $ingestRes = $ingestService->startIngest($clusters, $maxCandidates);
                jsonOk(['ingest_job_id' => $ingestRes['job_id']]);
            } catch (Exception $e) {
                jsonErr('Failed to start Local PyAPI ingest job: ' . $e->getMessage());
            }
            exit;
        }

        // ── GET LOCAL INGEST JOB STATUS ────────────────────────────────────
        case 'get_ingest_job_status': {
            $jobId = trim($req['job_id'] ?? '');
            if (!$jobId) jsonErr('Missing job_id');

            try {
                $ingestService = new PyApiLocalIngestService();
                $status = $ingestService->getIngestStatus($jobId);
                jsonOk($status);
            } catch (Exception $e) {
                jsonErr('Local PyAPI ingest status error: ' . $e->getMessage());
            }
            exit;
        }

        // ── GET STATS ──────────────────────────────────────────────────────
        case 'get_stats': {
            $total    = (int)$pdo->query("SELECT COUNT(*) FROM fuzz_candidates")->fetchColumn();
            $pending  = (int)$pdo->query("SELECT COUNT(*) FROM fuzz_candidates WHERE status IN ('extracted','grouped')")->fetchColumn();
            $canon    = (int)$pdo->query("SELECT COUNT(*) FROM fuzz_candidates WHERE status = 'canonized'")->fetchColumn();
            $rejected = (int)$pdo->query("SELECT COUNT(*) FROM fuzz_candidates WHERE status = 'rejected'")->fetchColumn();
            $promoted = (int)$pdo->query("SELECT COUNT(*) FROM fuzz_candidates WHERE status = 'promoted'")->fetchColumn();
            $mentions = (int)$pdo->query("SELECT COUNT(*) FROM fuzz_mentions")->fetchColumn();
            $links    = (int)$pdo->query("SELECT COUNT(*) FROM fuzz_links")->fetchColumn();
            $kgLinked = (int)$pdo->query("SELECT COUNT(*) FROM fuzz_candidates WHERE kg_node_id IS NOT NULL")->fetchColumn();

            $byType   = $pdo->query("SELECT concept_type, COUNT(*) as count FROM fuzz_candidates GROUP BY concept_type ORDER BY count DESC")->fetchAll(PDO::FETCH_ASSOC);
            $byStatus = $pdo->query("SELECT status, COUNT(*) as count FROM fuzz_candidates GROUP BY status ORDER BY count DESC")->fetchAll(PDO::FETCH_ASSOC);

            jsonOk([
                'total_candidates' => $total,
                'pending'          => $pending,
                'canonized'        => $canon,
                'rejected'         => $rejected,
                'promoted'         => $promoted,
                'total_mentions'   => $mentions,
                'total_links'      => $links,
                'kg_linked'        => $kgLinked,
                'by_type'          => $byType,
                'by_status'        => $byStatus
            ]);
        }

        // ── DELETE MENTIONS BY ENTITY ──────────────────────────────────────
        case 'delete_mentions_by_entity': {
            $candidateId  = (int)($req['candidate_id'] ?? 0);
            $sourceTable  = trim($req['source_table']  ?? '');
            $sourceRowId  = (int)($req['source_row_id'] ?? 0);
            if (!$candidateId || !$sourceTable || !$sourceRowId) {
                jsonErr('Missing candidate_id, source_table, or source_row_id');
            }
            $stmt = $pdo->prepare("
                DELETE FROM fuzz_mentions
                WHERE candidate_id  = :cid
                  AND source_table  = :src_table
                  AND source_row_id = :src_row
            ");
            $stmt->execute([
                'cid'       => $candidateId,
                'src_table' => $sourceTable,
                'src_row'   => $sourceRowId
            ]);
            jsonOk(['deleted' => $stmt->rowCount()]);
        }

        default:
            jsonErr('Unknown action: ' . $action);
    }

} catch (Exception $e) {
    jsonErr($e->getMessage());
}
?>