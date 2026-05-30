<?php
// public/api/fuzz_graph_api.php
// ─────────────────────────────────────────────────────────────────────────────
// Dedicated API for dynamic graph traversal and hops expansion.
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../env_locals.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$req = json_decode($raw, true);
$action = $req['action'] ?? '';

// ── Search Entry Nodes ──
if ($action === 'search_entry') {
    $q = trim($req['q'] ?? '');
    $mode = $req['mode'] ?? 'general';
    
    if (strlen($q) < 1 && !is_numeric($q)) {
        echo json_encode(['ok' => true, 'data' => []]);
        exit;
    }

    $isNum = is_numeric($q);
    $likeQ = '%' . $q . '%';
    $numQ = $isNum ? (int)$q : 0;
    $results = [];

    global $pdo;

    if ($mode === 'sketches') {
        $stmt = $pdo->prepare("SELECT id, name as label FROM sketches WHERE name LIKE :q OR id = :qid LIMIT 30");
        $stmt->execute(['q' => $likeQ, 'qid' => $numQ]);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $results[] = [
                'id' => $r['id'],
                'label' => $r['label'] ?: 'Sketch #' . $r['id'],
                'meta' => 'Sketch',
                'type' => 'sketches'
            ];
        }
    } elseif ($mode === 'kg_nodes') {
        $stmt = $pdo->prepare("SELECT id, name as label, node_type FROM kg_nodes WHERE name LIKE :q OR id = :qid LIMIT 30");
        $stmt->execute(['q' => $likeQ, 'qid' => $numQ]);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $results[] = [
                'id' => $r['id'],
                'label' => $r['label'] ?: 'KG Node #' . $r['id'],
                'meta' => 'KG Node · ' . ($r['node_type'] ?? 'note'),
                'type' => 'kg_nodes'
            ];
        }
    } else {
        // general (fuzz candidates)
        $stmt = $pdo->prepare("
            SELECT c.id, c.label, c.concept_type, c.status,
                   (SELECT COUNT(*) FROM fuzz_mentions m WHERE m.candidate_id = c.id) as mention_count,
                   (SELECT COUNT(*) FROM fuzz_links fl WHERE fl.candidate_id = c.id OR fl.target_candidate_id = c.id) as link_count
            FROM fuzz_candidates c
            WHERE c.label LIKE :q OR c.id = :qid
            ORDER BY (mention_count + link_count) DESC, c.label ASC
            LIMIT 30
        ");
        $stmt->execute(['q' => $likeQ, 'qid' => $numQ]);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $conns = ((int)$r['mention_count']) + ((int)$r['link_count']);
            $results[] = [
                'id' => $r['id'],
                'label' => $r['label'],
                'status' => $r['status'],
                'concept_type' => $r['concept_type'],
                'meta' => 'Candidate · ' . $conns . ' Connection(s)',
                'type' => 'candidate'
            ];
        }
    }
    
    echo json_encode(['ok' => true, 'data' => $results]);
    exit;
}

// Shared scope for graph elements
$nodes = [];
$edges = [];
$nodeIndex = [];

$addNode = function(string $id, string $label, string $ntype, array $extra = []) use (&$nodes, &$nodeIndex) {
    if (!isset($nodeIndex[$id])) {
        $nodeIndex[$id] = true;
        $nodes[] = array_merge(['id' => $id, 'label' => $label, 'ntype' => $ntype], $extra);
    }
};
$addEdge = function(string $src, string $tgt, string $rel = '', float $w = 1.0) use (&$edges) {
    $edges[] = ['source' => $src, 'target' => $tgt, 'rel' => $rel, 'weight' => $w];
};

function fetchNeighborhoodForCandidate(int $candidateId, $pdo, $addNode, $addEdge) {
    $stmt = $pdo->prepare("SELECT * FROM fuzz_candidates WHERE id = :id");
    $stmt->execute(['id' => $candidateId]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$candidate) return;

    $centreId = 'cand_' . $candidate['id'];
    
    $addNode($centreId, $candidate['label'], 'linked_candidate', [
        'status'       => $candidate['status'],
        'concept_type' => $candidate['concept_type'] ?? '',
        'confidence'   => (int)($candidate['confidence'] ?? 50),
        'db_id'        => $candidate['id'],
    ]);

    /* Aliases */
    $aStmt = $pdo->prepare("SELECT alias FROM fuzz_candidate_aliases WHERE candidate_id = :id LIMIT 30");
    $aStmt->execute(['id' => $candidateId]);
    foreach ($aStmt->fetchAll(PDO::FETCH_COLUMN) as $alias) {
        $aid = 'alias_' . md5($alias);
        $addNode($aid, $alias, 'alias');
        $addEdge($centreId, $aid, 'alias');
    }

    /* KG Node */
    if (!empty($candidate['kg_node_id'])) {
        $kgStmt = $pdo->prepare("SELECT id, name, node_type FROM kg_nodes WHERE id = :id");
        $kgStmt->execute(['id' => $candidate['kg_node_id']]);
        $kg = $kgStmt->fetch(PDO::FETCH_ASSOC);
        if ($kg) {
            $kgId = 'kg_' . $kg['id'];
            $addNode($kgId, $kg['name'], 'kg_node', ['db_id' => $kg['id'], 'kg_node_type' => $kg['node_type'] ?? 'note']);
            $addEdge($centreId, $kgId, 'resolved_to');
        }
    }

    /* Fuzz Links */
    $lStmt = $pdo->prepare("
        SELECT fl.*, fc.label AS target_label, fc.status AS target_status, fc.concept_type AS target_type
        FROM fuzz_links fl
        LEFT JOIN fuzz_candidates fc ON fc.id = fl.target_candidate_id
        WHERE fl.candidate_id = :id
        LIMIT 40
    ");
    $lStmt->execute(['id' => $candidateId]);
    foreach ($lStmt->fetchAll(PDO::FETCH_ASSOC) as $link) {
        $lid = 'cand_' . $link['target_candidate_id'];
        $addNode($lid, $link['target_label'] ?? 'Candidate #' . $link['target_candidate_id'], 'linked_candidate', [
            'db_id'        => (int)$link['target_candidate_id'],
            'status'       => $link['target_status'] ?? '',
            'concept_type' => $link['target_type'] ?? '',
        ]);
        $addEdge($centreId, $lid, str_replace('_', ' ', $link['relationship_type'] ?? 'linked'), (float)($link['confidence'] ?? 50) / 100);
    }

    /* Source Mentions */
    $mStmt = $pdo->prepare("
        SELECT source_table, source_row_id, mention_type, COUNT(*) as cnt
        FROM fuzz_mentions
        WHERE candidate_id = :id AND source_row_id IS NOT NULL
        GROUP BY source_table, source_row_id
        ORDER BY cnt DESC
        LIMIT 60
    ");
    $mStmt->execute(['id' => $candidateId]);
    $mentionGroups = $mStmt->fetchAll(PDO::FETCH_ASSOC);

    $byTable = [];
    foreach ($mentionGroups as $mg) {
        $byTable[$mg['source_table']][] = (int)$mg['source_row_id'];
    }
    $nameMap = [];
    $safeEntityTables = ['sketches','kg_nodes','characters','animas','locations','backgrounds','artifacts','vehicles'];
    foreach ($byTable as $tbl => $ids) {
        $lookupTbl = in_array($tbl, ['sketch_analysis','sketch_lore_history','sketch_ingredients'], true) ? 'sketches' : $tbl;
        if (!in_array($lookupTbl, $safeEntityTables, true)) continue;
        $in = implode(',', array_unique(array_map('intval', $ids)));
        try {
            $rows = $pdo->query("SELECT id, name FROM {$lookupTbl} WHERE id IN ({$in})")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) { $nameMap[$lookupTbl][(int)$r['id']] = $r['name']; }
        } catch (\Exception $e) {}
    }

    foreach ($mentionGroups as $mg) {
        $tbl = $mg['source_table'];
        $sid = (int)$mg['source_row_id'];
        $lookupTbl = in_array($tbl, ['sketch_analysis','sketch_lore_history','sketch_ingredients'], true) ? 'sketches' : $tbl;
        $name = $nameMap[$lookupTbl][$sid] ?? null;
        if (!$name) continue;

        $srcId = 'src_' . $lookupTbl . '_' . $sid;
        $addNode($srcId, $name, 'source_entity', [
            'source_table'  => $lookupTbl,
            'source_row_id' => $sid,
            'mention_count' => (int)$mg['cnt'],
        ]);
        $addEdge($srcId, $centreId, 'mentions', min(1.0, (int)$mg['cnt'] / 10));
    }
}

function fetchNeighborhoodForEntity(string $table, int $rowId, $pdo, $addNode, $addEdge) {
    $safeEntityTables = ['sketches','kg_nodes','characters','animas','locations','backgrounds','artifacts','vehicles'];
    if (!in_array($table, $safeEntityTables, true)) return;

    $srcId = 'src_' . $table . '_' . $rowId;

    if ($table === 'sketches') {
        $tableCond = "m.source_table IN ('sketches','sketch_analysis','sketch_lore_history','sketch_ingredients')";
    } else {
        $tableCond = "m.source_table = :tbl";
    }

    $sql = "
        SELECT m.candidate_id, COUNT(*) as cnt, c.label, c.status, c.concept_type, c.confidence
        FROM fuzz_mentions m
        JOIN fuzz_candidates c ON c.id = m.candidate_id
        WHERE {$tableCond} AND m.source_row_id = :rid
        GROUP BY m.candidate_id
        LIMIT 40
    ";

    $stmt = $pdo->prepare($sql);
    if ($table !== 'sketches') $stmt->bindValue(':tbl', $table);
    $stmt->bindValue(':rid', $rowId, PDO::PARAM_INT);
    $stmt->execute();
    
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cid = 'cand_' . $row['candidate_id'];
        $addNode($cid, $row['label'], 'linked_candidate', [
            'db_id'        => (int)$row['candidate_id'],
            'status'       => $row['status'] ?? '',
            'concept_type' => $row['concept_type'] ?? '',
            'confidence'   => (int)$row['confidence']
        ]);
        // Connect the entity out to the candidate
        $addEdge($srcId, $cid, 'mentions', min(1.0, (int)$row['cnt'] / 10));
    }
}

// ── Entry Points ──
if ($action === 'get_neighborhood') {
    $candidateId = (int)($req['id'] ?? 0);
    if ($candidateId > 0) {
        fetchNeighborhoodForCandidate($candidateId, $pdo, $addNode, $addEdge);
    }
    echo json_encode(['ok' => true, 'data' => ['nodes' => $nodes, 'edges' => $edges]]);
    exit;
}

if ($action === 'get_neighborhood_batch') {
    $cands = $req['candidates'] ?? [];
    $ents = $req['entities'] ?? [];
    
    // Process candidate hops
    $cChunk = array_slice($cands, 0, 25);
    foreach ($cChunk as $cid) {
        if ((int)$cid > 0) fetchNeighborhoodForCandidate((int)$cid, $pdo, $addNode, $addEdge);
    }
    
    // Process entity hops (e.g. sketches that contain candidates)
    $eChunk = array_slice($ents, 0, 25);
    foreach ($eChunk as $ent) {
        if (!empty($ent['table']) && !empty($ent['id'])) {
            fetchNeighborhoodForEntity($ent['table'], (int)$ent['id'], $pdo, $addNode, $addEdge);
        }
    }
    
    echo json_encode(['ok' => true, 'data' => ['nodes' => $nodes, 'edges' => $edges]]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);