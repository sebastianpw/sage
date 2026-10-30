<?php
// public/mermaids_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

header('Content-Type: application/json');

function merm_json($ok, $data = [], $error = null) {
    $out = ['ok' => $ok];
    if ($error) $out['error'] = $error;
    foreach ($data as $k => $v) $out[$k] = $v;
    echo json_encode($out);
    exit;
}

$input = [];
$raw   = file_get_contents('php://input');
if ($raw) {
    $dec = json_decode($raw, true);
    if (is_array($dec)) $input = $dec;
}
$input  = array_merge($input, $_POST);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {

// -------------------------------------------------------
// STATS (for Filter and Export modals)
// -------------------------------------------------------
if ($action === 'get_stats') {
    $filters = $input['filters'] ?? [];
    $where = ["t.status = 'active'"];
    $params = [];

    foreach (['series', 'season', 'episode'] as $type) {
        $key = $type . '_key';
        if (!empty($filters[$type]) && is_array($filters[$type])) {
            $ph = implode(',', array_fill(0, count($filters[$type]), '?'));
            $where[] = "t.{$key} IN ($ph)";
            $params = array_merge($params, array_values($filters[$type]));
        }
    }

    $whereSql = implode(' AND ', $where);

    $tStmt = $pdo->prepare("SELECT COUNT(*) FROM merm_threads t WHERE $whereSql");
    $tStmt->execute($params);
    $threadCount = (int)$tStmt->fetchColumn();

    $bStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM merm_beats b 
        JOIN merm_threads t ON b.thread_id = t.id 
        WHERE $whereSql AND b.status != 'archived'
    ");
    $bStmt->execute($params);
    $beatCount = (int)$bStmt->fetchColumn();

    merm_json(true, ['stats' => ['threads' => $threadCount, 'beats' => $beatCount]]);
}

// -------------------------------------------------------
// UNIFIED BUNDLE (Replaces N+1 HTTP calls with 1 instant fetch)
// -------------------------------------------------------
if ($action === 'fetch_bundle') {
    $threads = $pdo->query("
        SELECT t.id, t.name, t.series_key, t.season_key, t.episode_key, t.description, t.color, t.sort_order, t.status,
               t.created_at, t.updated_at
        FROM merm_threads t
        WHERE t.status = 'active'
        ORDER BY t.sort_order ASC, t.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $beats = $pdo->query("
        SELECT b.id, b.thread_id, b.title, b.summary, b.beat_type, b.story_time_dt, b.seq_order, b.status,
               b.created_at, b.updated_at
        FROM merm_beats b
        JOIN merm_threads t ON b.thread_id = t.id
        WHERE b.status != 'archived' AND t.status = 'active'
        ORDER BY b.story_time_dt ASC, b.seq_order ASC, b.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $participants = $pdo->query("
        SELECT bp.beat_id, bp.kg_node_id AS char_id, kg.name AS char_name, bp.role
        FROM merm_beat_participants bp
        JOIN kg_nodes kg ON kg.id = bp.kg_node_id
        JOIN merm_beats b ON b.id = bp.beat_id
        JOIN merm_threads t ON t.id = b.thread_id
        WHERE b.status != 'archived' AND t.status = 'active' AND kg.status = 'active'
        ORDER BY bp.sort_order ASC, bp.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    merm_json(true, [
        'threads' => $threads,
        'beats' => $beats,
        'participants' => $participants
    ]);
}

// -------------------------------------------------------
// THREADS
// -------------------------------------------------------
if ($action === 'fetch_threads') {
    $rows = $pdo->query("
        SELECT t.id, t.name, t.series_key, t.season_key, t.episode_key, t.description, t.color, t.sort_order, t.status,
               t.created_at, t.updated_at,
               (SELECT COUNT(*) FROM merm_beats b WHERE b.thread_id = t.id AND b.status != 'archived') AS beat_count
        FROM merm_threads t
        WHERE t.status = 'active'
        ORDER BY t.sort_order ASC, t.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    merm_json(true, ['threads' => $rows]);
}

if ($action === 'create_thread') {
    $name = trim($input['name'] ?? '');
    if (!$name) merm_json(false, [], 'Name required');
    $stmt = $pdo->prepare("INSERT INTO merm_threads (name, series_key, season_key, episode_key, description, color, sort_order) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([
        $name,
        trim($input['series_key'] ?? '') ?: null,
        trim($input['season_key'] ?? '') ?: null,
        trim($input['episode_key'] ?? '') ?: null,
        trim($input['description'] ?? '') ?: null,
        trim($input['color'] ?? '') ?: null,
        (int)($input['sort_order'] ?? 0),
    ]);
    merm_json(true, ['id' => $pdo->lastInsertId()]);
}

if ($action === 'update_thread') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) merm_json(false, [], 'Missing id');
    $stmt = $pdo->prepare("
        UPDATE merm_threads
        SET name=?, series_key=?, season_key=?, episode_key=?, description=?, color=?, sort_order=?, updated_at=NOW()
        WHERE id=?
    ");
    $stmt->execute([
        trim($input['name'] ?? ''),
        trim($input['series_key'] ?? '') ?: null,
        trim($input['season_key'] ?? '') ?: null,
        trim($input['episode_key'] ?? '') ?: null,
        trim($input['description'] ?? '') ?: null,
        trim($input['color'] ?? '') ?: null,
        (int)($input['sort_order'] ?? 0),
        $id,
    ]);
    merm_json(true);
}

if ($action === 'delete_thread') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) merm_json(false, [], 'Missing id');
    $pdo->prepare("UPDATE merm_threads SET status='archived' WHERE id=?")->execute([$id]);
    $pdo->prepare("UPDATE merm_beats SET status='archived' WHERE thread_id=?")->execute([$id]);
    merm_json(true);
}

// -------------------------------------------------------
// EXPORT TIMELINES (JSON, SQL, MD)
// -------------------------------------------------------
if ($action === 'export_data') {
    $filters = $input['filters'] ?? [];
    $format  = $input['format'] ?? 'json';
    
    $where = ["t.status = 'active'"];
    $params = [];

    foreach (['series', 'season', 'episode'] as $type) {
        $key = $type . '_key';
        if (!empty($filters[$type]) && is_array($filters[$type])) {
            $ph = implode(',', array_fill(0, count($filters[$type]), '?'));
            $where[] = "t.{$key} IN ($ph)";
            $params = array_merge($params, array_values($filters[$type]));
        }
    }

    $whereSql = implode(' AND ', $where);
    
    $tStmt = $pdo->prepare("SELECT * FROM merm_threads t WHERE $whereSql ORDER BY t.sort_order ASC, t.name ASC");
    $tStmt->execute($params);
    $threads = $tStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$threads) {
        if ($format === 'json') merm_json(true, ['export_data' => ['threads' => [], 'beats' => []]]);
        else merm_json(true, ['export_text' => '']);
    }

    $threadIds = array_map(fn($t) => (int)$t['id'], $threads);
    $ph = implode(',', array_fill(0, count($threadIds), '?'));

    $bStmt = $pdo->prepare("SELECT * FROM merm_beats WHERE thread_id IN ($ph) AND status != 'archived' ORDER BY story_time_dt ASC, seq_order ASC");
    $bStmt->execute($threadIds);
    $beats = $bStmt->fetchAll(PDO::FETCH_ASSOC);

    $beatIds = array_map(fn($b) => (int)$b['id'], $beats);
    $participants = [];
    $links = [];

    if ($beatIds) {
        $bph = implode(',', array_fill(0, count($beatIds), '?'));
        
        $pStmt = $pdo->prepare("SELECT * FROM merm_beat_participants WHERE beat_id IN ($bph) ORDER BY sort_order ASC");
        $pStmt->execute($beatIds);
        $participants = $pStmt->fetchAll(PDO::FETCH_ASSOC);

        $lStmt = $pdo->prepare("SELECT * FROM merm_beat_links WHERE from_beat_id IN ($bph) AND to_beat_id IN ($bph) ORDER BY sort_order ASC");
        $lStmt->execute(array_merge($beatIds, $beatIds));
        $links = $lStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // JSON Format
    if ($format === 'json') {
        merm_json(true, [
            'export_data' => [
                'meta' => [
                    'generated_at' => date('c'), 
                    'type' => 'swimlane_timeline_export',
                    'schema' => ['merm_threads', 'merm_beats', 'merm_beat_participants', 'merm_beat_links']
                ],
                'threads' => $threads,
                'beats' => $beats,
                'participants' => $participants,
                'links' => $links
            ]
        ]);
    }
    
    // SQL Format
    if ($format === 'sql') {
        $sqlStr = "-- Timeline Export Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $tables = [
            'merm_threads' => $threads,
            'merm_beats' => $beats,
            'merm_beat_participants' => $participants,
            'merm_beat_links' => $links
        ];
        
        foreach ($tables as $tableName => $rows) {
            if (empty($rows)) continue;
            $sqlStr .= "-- Table: $tableName\n";
            foreach ($rows as $row) {
                $cols = array_keys($row);
                $vals = array_map(function($v) use ($pdo) {
                    return $v === null ? "NULL" : $pdo->quote((string)$v);
                }, array_values($row));
                $sqlStr .= "INSERT INTO `$tableName` (`" . implode("`,`", $cols) . "`) VALUES (" . implode(",", $vals) . ");\n";
            }
            $sqlStr .= "\n";
        }
        
        merm_json(true, ['export_text' => $sqlStr]);
    }
    
    // Markdown Format
    if ($format === 'md') {
        $md = "";
        
        // Create lookup for threads
        $threadsById = [];
        foreach ($threads as $t) { $threadsById[$t['id']] = $t; }
        
        // Sort beats by episode then chronologically
        usort($beats, function($a, $b) use ($threadsById) {
            $ta = $threadsById[$a['thread_id']];
            $tb = $threadsById[$b['thread_id']];
            
            $cmpSeries = strcmp((string)$ta['series_key'], (string)$tb['series_key']);
            if ($cmpSeries !== 0) return $cmpSeries;
            
            $cmpSeason = strcmp((string)$ta['season_key'], (string)$tb['season_key']);
            if ($cmpSeason !== 0) return $cmpSeason;
            
            $cmpEp = strcmp((string)$ta['episode_key'], (string)$tb['episode_key']);
            if ($cmpEp !== 0) return $cmpEp;
            
            $timeA = $a['story_time_dt'] ?: '9999-12-31';
            $timeB = $b['story_time_dt'] ?: '9999-12-31';
            $cmpTime = strcmp($timeA, $timeB);
            if ($cmpTime !== 0) return $cmpTime;
            
            return $a['seq_order'] <=> $b['seq_order'];
        });

        $currentEp = null;
        foreach ($beats as $b) {
            $thread = $threadsById[$b['thread_id']];
            $ep = $thread['episode_key'] ?: 'Unassigned Episode';
            
            if ($ep !== $currentEp) {
                $md .= "# Episode: " . $ep . "\n\n";
                $currentEp = $ep;
            }
            
            $content = trim($b['content']);
            $summary = trim($b['summary']);
            $out = !empty($content) ? $content : $summary;
            
            if (!empty($out)) {
                $md .= "## " . $b['title'] . " (#" . $b['id'] . ")\n\n" . $out . "\n\n";
            }
        }
        
        merm_json(true, ['export_text' => trim($md) . "\n"]);
    }
}

// -------------------------------------------------------
// SERIES ANCHOR DATE SHIFTING
// -------------------------------------------------------
if ($action === 'get_series_start') {
    $series = trim($input['series_key'] ?? '');
    if (!$series) merm_json(false, [], 'Missing series_key');
    
    $stmt = $pdo->prepare("
        SELECT MIN(b.story_time_dt) as min_dt 
        FROM merm_beats b
        JOIN merm_threads t ON b.thread_id = t.id
        WHERE t.series_key = ? AND b.story_time_dt IS NOT NULL AND b.status != 'archived'
    ");
    $stmt->execute([$series]);
    $minDt = $stmt->fetchColumn();
    
    merm_json(true, ['min_dt' => $minDt]);
}

if ($action === 'shift_series_start') {
    $series = trim($input['series_key'] ?? '');
    $newStart = $input['new_start_dt'] ?? '';
    
    if (!$series || !$newStart) merm_json(false, [], 'Missing parameters');
    
    $stmt = $pdo->prepare("
        SELECT MIN(b.story_time_dt) as min_dt 
        FROM merm_beats b
        JOIN merm_threads t ON b.thread_id = t.id
        WHERE t.series_key = ? AND b.story_time_dt IS NOT NULL AND b.status != 'archived'
    ");
    $stmt->execute([$series]);
    $minDt = $stmt->fetchColumn();
    
    if (!$minDt) merm_json(false, [], 'No beats with dates found in this series.');
    
    $tsOld = strtotime($minDt);
    $tsNew = strtotime($newStart);
    $diffSeconds = $tsNew - $tsOld;
    
    if ($diffSeconds == 0) merm_json(true, ['msg' => 'No change needed']);
    
    $update = $pdo->prepare("
        UPDATE merm_beats b
        JOIN merm_threads t ON b.thread_id = t.id
        SET b.story_time_dt = DATE_ADD(b.story_time_dt, INTERVAL ? SECOND)
        WHERE t.series_key = ? AND b.story_time_dt IS NOT NULL AND b.status != 'archived'
    ");
    $update->execute([$diffSeconds, $series]);
    
    merm_json(true, ['shifted' => $diffSeconds]);
}

// -------------------------------------------------------
// GRID & TIMELINE
// -------------------------------------------------------
if ($action === 'save_grid') {
    $moves = $input['moves'] ?? [];
    if (is_string($moves)) {
        $moves = json_decode($moves, true) ?: [];
    }
    if (!is_array($moves) || !$moves) merm_json(false, [], 'moves array required');
    
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE merm_beats SET story_time_dt=?, thread_id=?, seq_order=? WHERE id=?");
    foreach ($moves as $m) {
        $dt = !empty($m['story_time_dt']) ? date('Y-m-d H:i:s', strtotime($m['story_time_dt'])) : null;
        $stmt->execute([
            $dt,
            (int)($m['thread_id'] ?? 0),
            (int)($m['seq_order'] ?? 0),
            (int)($m['beat_id'] ?? 0),
        ]);
    }
    $pdo->commit();
    merm_json(true);
}

if ($action === 'shift_timeline') {
    $fromDt = $input['from_dt'] ?? '';
    $seriesKey = trim($input['series_key'] ?? '');
    
    if ($fromDt) {
        $fromDt = date('Y-m-d H:i:s', strtotime($fromDt));
        
        if ($seriesKey) {
            $stmt = $pdo->prepare("
                UPDATE merm_beats b
                JOIN merm_threads t ON b.thread_id = t.id
                SET b.story_time_dt = DATE_ADD(b.story_time_dt, INTERVAL 1 DAY) 
                WHERE b.story_time_dt >= ? AND b.status != 'archived' AND t.series_key = ?
            ");
            $stmt->execute([$fromDt, $seriesKey]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE merm_beats 
                SET story_time_dt = DATE_ADD(story_time_dt, INTERVAL 1 DAY) 
                WHERE story_time_dt >= ? AND status != 'archived'
            ");
            $stmt->execute([$fromDt]);
        }
    }
    merm_json(true);
}

// -------------------------------------------------------
// BEATS
// -------------------------------------------------------
if ($action === 'fetch_beats') {
    $threadId = (int)($_GET['thread_id'] ?? $input['thread_id'] ?? 0);
    if (!$threadId) merm_json(false, [], 'Missing thread_id');
    $stmt = $pdo->prepare("
        SELECT id, thread_id, title, summary, beat_type, story_time_dt, seq_order, status, created_at, updated_at
        FROM merm_beats
        WHERE thread_id = ? AND status != 'archived'
        ORDER BY story_time_dt ASC, seq_order ASC, id ASC
    ");
    $stmt->execute([$threadId]);
    merm_json(true, ['beats' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'get_beat') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) merm_json(false, [], 'Missing id');
    $stmt = $pdo->prepare("SELECT * FROM merm_beats WHERE id=?");
    $stmt->execute([$id]);
    $beat = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$beat) merm_json(false, [], 'Beat not found');

    $pStmt = $pdo->prepare("SELECT id, kg_node_id, role, sort_order FROM merm_beat_participants WHERE beat_id=? ORDER BY sort_order ASC, id ASC");
    $pStmt->execute([$id]);
    $participants = $pStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($participants) {
        $kgIds = array_values(array_unique(array_map(fn($p) => (int)$p['kg_node_id'], $participants)));
        $ph    = implode(',', array_fill(0, count($kgIds), '?'));
        $kgStmt = $pdo->prepare("SELECT id, name, node_type FROM kg_nodes WHERE id IN ($ph) AND status='active'");
        $kgStmt->execute($kgIds);
        $kgById = [];
        foreach ($kgStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $kgById[(int)$r['id']] = $r;
        foreach ($participants as &$p) {
            $kg = $kgById[(int)$p['kg_node_id']] ?? null;
            $p['kg_node_name']      = $kg['name'] ?? '(deleted node)';
            $p['kg_node_type']      = $kg['node_type'] ?? null;
        }
        unset($p);
    }
    $beat['participants'] = $participants;

    $lOut = $pdo->prepare("SELECT id, from_beat_id, to_beat_id, relationship, label, 'outgoing' AS direction FROM merm_beat_links WHERE from_beat_id=?");
    $lOut->execute([$id]);
    $lIn = $pdo->prepare("SELECT id, from_beat_id, to_beat_id, relationship, label, 'incoming' AS direction FROM merm_beat_links WHERE to_beat_id=?");
    $lIn->execute([$id]);
    $links = array_merge($lOut->fetchAll(PDO::FETCH_ASSOC), $lIn->fetchAll(PDO::FETCH_ASSOC));

    if ($links) {
        $beatIds = [];
        foreach ($links as $l) { $beatIds[(int)$l['from_beat_id']] = true; $beatIds[(int)$l['to_beat_id']] = true; }
        unset($beatIds[$id]);
        if ($beatIds) {
            $ph = implode(',', array_fill(0, count($beatIds), '?'));
            $bStmt = $pdo->prepare("SELECT id, title, thread_id FROM merm_beats WHERE id IN ($ph)");
            $bStmt->execute(array_keys($beatIds));
            $bById = [];
            foreach ($bStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $bById[(int)$r['id']] = $r;
            foreach ($links as &$l) {
                $otherId = $l['direction'] === 'outgoing' ? (int)$l['to_beat_id'] : (int)$l['from_beat_id'];
                $l['other_beat_title']     = $bById[$otherId]['title'] ?? '(deleted beat)';
                $l['other_beat_thread_id'] = $bById[$otherId]['thread_id'] ?? null;
            }
            unset($l);
        }
    }
    $beat['links'] = $links;

    merm_json(true, ['beat' => $beat]);
}

if ($action === 'create_beat') {
    $threadId = (int)($input['thread_id'] ?? 0);
    $title    = trim($input['title'] ?? '');
    if (!$threadId || !$title) merm_json(false, [], 'thread_id and title required');

    $max = $pdo->prepare("SELECT COALESCE(MAX(seq_order),0)+1 FROM merm_beats WHERE thread_id=?");
    $max->execute([$threadId]);
    $nextSeq = $max->fetchColumn();

    $storyTimeDt = !empty($input['story_time_dt']) ? date('Y-m-d H:i:s', strtotime($input['story_time_dt'])) : null;

    $stmt = $pdo->prepare("
        INSERT INTO merm_beats (thread_id, title, summary, content, beat_type, story_time_dt, seq_order, status)
        VALUES (?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $threadId,
        $title,
        trim($input['summary'] ?? '') ?: null,
        $input['content'] ?? '',
        trim($input['beat_type'] ?? 'event'),
        $storyTimeDt,
        !empty($input['seq_order']) ? (int)$input['seq_order'] : $nextSeq,
        trim($input['status'] ?? 'draft'),
    ]);
    merm_json(true, ['id' => $pdo->lastInsertId()]);
}

if ($action === 'update_beat') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) merm_json(false, [], 'Missing id');
    
    $storyTimeDt = !empty($input['story_time_dt']) ? date('Y-m-d H:i:s', strtotime($input['story_time_dt'])) : null;

    $stmt = $pdo->prepare("
        UPDATE merm_beats
        SET thread_id=?, title=?, summary=?, content=?, beat_type=?, story_time_dt=?, seq_order=?, status=?, updated_at=NOW()
        WHERE id=?
    ");
    $stmt->execute([
        (int)($input['thread_id'] ?? 0),
        trim($input['title'] ?? ''),
        trim($input['summary'] ?? '') ?: null,
        $input['content'] ?? '',
        trim($input['beat_type'] ?? 'event'),
        $storyTimeDt,
        (int)($input['seq_order'] ?? 0),
        trim($input['status'] ?? 'draft'),
        $id,
    ]);
    merm_json(true);
}

if ($action === 'delete_beat') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) merm_json(false, [], 'Missing id');
    $pdo->prepare("UPDATE merm_beats SET status='archived' WHERE id=?")->execute([$id]);
    merm_json(true);
}

// -------------------------------------------------------
// PARTICIPANTS
// -------------------------------------------------------
if ($action === 'add_participant') {
    $beatId   = (int)($input['beat_id'] ?? 0);
    $kgNodeId = (int)($input['kg_node_id'] ?? 0);
    if (!$beatId || !$kgNodeId) merm_json(false, [], 'beat_id and kg_node_id required');

    $chk = $pdo->prepare("SELECT id FROM kg_nodes WHERE id=? AND status='active' LIMIT 1");
    $chk->execute([$kgNodeId]);
    if (!$chk->fetch()) merm_json(false, [], 'kg_node not found or inactive');

    $max = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM merm_beat_participants WHERE beat_id=?");
    $max->execute([$beatId]);
    $nextOrder = $max->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO merm_beat_participants (beat_id, kg_node_id, role, sort_order) VALUES (?,?,?,?)");
    $stmt->execute([$beatId, $kgNodeId, trim($input['role'] ?? '') ?: null, $nextOrder]);
    merm_json(true, ['id' => $pdo->lastInsertId()]);
}

if ($action === 'remove_participant') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) merm_json(false, [], 'Missing id');
    $pdo->prepare("DELETE FROM merm_beat_participants WHERE id=?")->execute([$id]);
    merm_json(true);
}

// -------------------------------------------------------
// BEAT LINKS
// -------------------------------------------------------
if ($action === 'add_link') {
    $fromId = (int)($input['from_beat_id'] ?? 0);
    $toId   = (int)($input['to_beat_id'] ?? 0);
    if (!$fromId || !$toId) merm_json(false, [], 'from_beat_id and to_beat_id required');
    if ($fromId === $toId) merm_json(false, [], 'Cannot link a beat to itself');

    $stmt = $pdo->prepare("
        INSERT INTO merm_beat_links (from_beat_id, to_beat_id, relationship, label, sort_order)
        VALUES (?,?,?,?,?)
    ");
    $stmt->execute([
        $fromId, $toId,
        trim($input['relationship'] ?? 'leads_to'),
        trim($input['label'] ?? '') ?: null,
        (int)($input['sort_order'] ?? 0),
    ]);
    merm_json(true, ['id' => $pdo->lastInsertId()]);
}

if ($action === 'remove_link') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) merm_json(false, [], 'Missing id');
    $pdo->prepare("DELETE FROM merm_beat_links WHERE id=?")->execute([$id]);
    merm_json(true);
}

// -------------------------------------------------------
// KG NODE SEARCH
// -------------------------------------------------------
if ($action === 'search_kg_nodes') {
    $q = '%' . trim($_GET['q'] ?? $input['q'] ?? '') . '%';
    $stmt = $pdo->prepare("
        SELECT id, name, node_type
        FROM kg_nodes
        WHERE status='active' AND name LIKE ?
        ORDER BY name ASC
        LIMIT 30
    ");
    $stmt->execute([$q]);
    merm_json(true, ['results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

merm_json(false, [], 'Unknown action: ' . $action);

} catch (Exception $e) {
    http_response_code(500);
    merm_json(false, [], $e->getMessage());
}
