<?php
// public/beap_api.php
// Beat Extraction & Artboard Pipeline — AJAX API
// Self-contained: does NOT depend on narseq_api.php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Helper: normalise a frame filename to a public URL path ──────────────────
function beapResolveThumb(string $candidate): string {
    if ($candidate === '') return '';
    if (strpos($candidate, 'http') === 0) return $candidate;
    if (strpos($candidate, 'view_frame.php') !== false) return $candidate;
    $parts = array_map('rawurlencode', explode('/', ltrim($candidate, '/')));
    return '/' . implode('/', $parts);
}

// ── Helper: fetch all frames for a sketch (same logic as narseq) ─────────────
function beapGetFramesForSketch(PDO $pdo, int $sketchId): array {
    $stmt = $pdo->prepare("
        SELECT id, filename FROM frames WHERE entity_type='sketches' AND entity_id = ?
        UNION
        SELECT f.id, f.filename FROM frames f
        JOIN frames_2_sketches f2s ON f2s.from_id = f.id WHERE f2s.to_id = ?
        ORDER BY id DESC
    ");
    $stmt->execute([$sketchId, $sketchId]);
    $frames = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fr) {
        $frames[] = [
            'id'       => (int)$fr['id'],
            'filename' => beapResolveThumb($fr['filename']),
        ];
    }
    return $frames;
}

try {
    switch ($action) {

        // ── Sketch search (for the live search input on the list view) ────────
        case 'search_sketches':
            $q = trim($_GET['q'] ?? '');
            $params = [];
            $where  = "searchable = 1";
            if ($q !== '') {
                // numeric: search by id OR name/description
                if (is_numeric($q)) {
                    $where .= " AND (id = ? OR name LIKE ? OR description LIKE ?)";
                    $params = [(int)$q, "%$q%", "%$q%"];
                } else {
                    $where .= " AND (name LIKE ? OR description LIKE ?)";
                    $params = ["%$q%", "%$q%"];
                }
            }
            $stmt = $pdo->prepare(
                "SELECT id, name, description FROM sketches WHERE $where ORDER BY id DESC LIMIT 20"
            );
            $stmt->execute($params);
            $res = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $res[] = [
                    'id'    => (int)$row['id'],
                    'label' => '#' . $row['id'] . ' — ' . $row['name'],
                    'desc'  => mb_substr($row['description'] ?? '', 0, 80),
                ];
            }
            echo json_encode(['status' => 'success', 'data' => $res]);
            break;

        // ── Paginated sketch list ─────────────────────────────────────────────
        case 'get_sketches_list':
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $perPage = 30;
            $search  = trim($_GET['search'] ?? '');

            $where  = "searchable = 1";
            $params = [];
            if ($search !== '') {
                if (is_numeric($search)) {
                    $where .= " AND (id = ? OR name LIKE ? OR description LIKE ?)";
                    $params = [(int)$search, "%$search%", "%$search%"];
                } else {
                    $where .= " AND (name LIKE ? OR description LIKE ?)";
                    $params = ["%$search%", "%$search%"];
                }
            }

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM sketches WHERE $where");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $offset = ($page - 1) * $perPage;
            $stmt   = $pdo->prepare(
                "SELECT id, name, description FROM sketches WHERE $where ORDER BY id DESC LIMIT $perPage OFFSET $offset"
            );
            $stmt->execute($params);

            $data = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $data[] = [
                    'id'   => (int)$row['id'],
                    'name' => $row['name'],
                    'desc' => mb_substr($row['description'] ?? '', 0, 120),
                ];
            }

            echo json_encode([
                'success' => true,
                'data'    => $data,
                'meta'    => [
                    'total'       => $total,
                    'page'        => $page,
                    'total_pages' => max(1, (int)ceil($total / $perPage)),
                ],
            ]);
            break;

        // ── Narrative sequences list (for picking output target) ──────────────
        case 'get_sequences_list':
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $perPage = 30;
            $search  = trim($_GET['search'] ?? '');
            $catId   = (int)($_GET['category_id'] ?? 0);

            $where  = "1=1";
            $params = [];
            if ($search !== '') {
                $where .= " AND (name LIKE ? OR id = ?)";
                $params[] = "%$search%";
                $params[] = (int)$search;
            }
            // Defensively check column exists before filtering
            if ($catId > 0) {
                try {
                    $pdo->query("SELECT category_id FROM narrative_sequences LIMIT 1");
                    $where   .= " AND category_id = ?";
                    $params[] = $catId;
                } catch (Exception $e) { /* column absent — skip filter */ }
            }

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM narrative_sequences WHERE $where");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $offset = ($page - 1) * $perPage;
            $stmt   = $pdo->prepare(
                "SELECT id, name, sequence_data, created_at FROM narrative_sequences WHERE $where ORDER BY id DESC LIMIT $perPage OFFSET $offset"
            );
            $stmt->execute($params);

            $data = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $sData = json_decode($row['sequence_data'] ?? '[]', true) ?: [];
                $data[] = [
                    'id'         => (int)$row['id'],
                    'name'       => $row['name'] ?: 'Untitled Sequence',
                    'skt_count'  => count($sData),
                    'created_at' => date('Y-m-d', strtotime($row['created_at'])),
                ];
            }

            echo json_encode([
                'success' => true,
                'data'    => $data,
                'meta'    => [
                    'total'       => $total,
                    'page'        => $page,
                    'total_pages' => max(1, (int)ceil($total / $perPage)),
                ],
            ]);
            break;

        // ── Sketches within a sequence ────────────────────────────────────────
        case 'get_sequence_sketches':
            $seqId = (int)($_GET['seq_id'] ?? 0);
            if (!$seqId) throw new Exception('seq_id required');

            $stmt = $pdo->prepare("SELECT sequence_data FROM narrative_sequences WHERE id = ?");
            $stmt->execute([$seqId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Sequence not found');

            $items = json_decode($row['sequence_data'] ?? '[]', true) ?: [];
            $sketchIds = [];
            foreach ($items as $item) {
                $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
                if ($sid > 0) $sketchIds[] = $sid;
            }
            $sketchIds = array_values(array_unique($sketchIds));

            $data = [];
            if (!empty($sketchIds)) {
                $in   = implode(',', array_fill(0, count($sketchIds), '?'));
                $stmt = $pdo->prepare("SELECT id, name, description FROM sketches WHERE id IN ($in)");
                $stmt->execute($sketchIds);
                $map = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $map[(int)$r['id']] = $r;
                }
                // Fetch latest frame per sketch in one query
                $thumbStmt = $pdo->prepare("
                    SELECT entity_id, id AS frame_id, filename
                    FROM frames
                    WHERE entity_type = 'sketches' AND entity_id IN ($in)
                    AND id = (
                        SELECT MAX(f2.id) FROM frames f2
                        WHERE f2.entity_type = 'sketches' AND f2.entity_id = frames.entity_id
                    )
                ");
                $thumbStmt->execute($sketchIds);
                $thumbMap = [];
                foreach ($thumbStmt->fetchAll(PDO::FETCH_ASSOC) as $tf) {
                    $thumbMap[(int)$tf['entity_id']] = [
                        'frame_id' => (int)$tf['frame_id'],
                        'filename' => beapResolveThumb($tf['filename']),
                    ];
                }
                // Also check frames_2_sketches for linked frames not in entity_id column
                $f2sStmt = $pdo->prepare("
                    SELECT f2s.to_id AS sketch_id, f.id AS frame_id, f.filename
                    FROM frames_2_sketches f2s
                    JOIN frames f ON f.id = f2s.from_id
                    WHERE f2s.to_id IN ($in)
                    ORDER BY f.id DESC
                ");
                $f2sStmt->execute($sketchIds);
                foreach ($f2sStmt->fetchAll(PDO::FETCH_ASSOC) as $tf) {
                    $sid = (int)$tf['sketch_id'];
                    if (!isset($thumbMap[$sid])) {
                        $thumbMap[$sid] = [
                            'frame_id' => (int)$tf['frame_id'],
                            'filename' => beapResolveThumb($tf['filename']),
                        ];
                    }
                }

                foreach ($sketchIds as $sid) {
                    if (isset($map[$sid])) {
                        $th = $thumbMap[$sid] ?? null;
                        $data[] = [
                            'id'       => $sid,
                            'name'     => $map[$sid]['name'],
                            'desc'     => mb_substr($map[$sid]['description'] ?? '', 0, 120),
                            'frame_id' => $th ? $th['frame_id'] : null,
                            'thumb'    => $th ? $th['filename'] : null,
                        ];
                    }
                }
            }

            echo json_encode(['success' => true, 'data' => $data]);
            break;

        // ── Get single sketch detail (for the beat extraction step) ───────────
        case 'get_sketch':
            $sketchId = (int)($_GET['sketch_id'] ?? $_POST['sketch_id'] ?? 0);
            if (!$sketchId) throw new Exception('sketch_id required');

            $stmt = $pdo->prepare("SELECT id, name, description, mood FROM sketches WHERE id = ?");
            $stmt->execute([$sketchId]);
            $sk = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sk) throw new Exception('Sketch not found');

            $frames = beapGetFramesForSketch($pdo, $sketchId);

            echo json_encode([
                'success' => true,
                'sketch'  => [
                    'id'          => (int)$sk['id'],
                    'name'        => $sk['name'],
                    'description' => $sk['description'],
                    'mood'        => $sk['mood'],
                    'frames'      => $frames,
                ],
            ]);
            break;

        // ── List existing BEAP sessions ───────────────────────────────────────
        case 'get_sessions_list':
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $perPage = 30;
            $search  = trim($_GET['search'] ?? '');

            $where  = "1=1";
            $params = [];
            if ($search !== '') {
                $where .= " AND (sketch_name LIKE ? OR id = ?)";
                $params = ["%$search%", (int)$search];
            }

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM beap_sessions WHERE $where");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $offset = ($page - 1) * $perPage;
            $stmt   = $pdo->prepare(
                "SELECT id, sketch_id, sketch_name, status, depth, narseq_id, created_at
                 FROM beap_sessions WHERE $where ORDER BY id DESC LIMIT $perPage OFFSET $offset"
            );
            $stmt->execute($params);

            $data = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                // Count beats
                $bStmt = $pdo->prepare("SELECT COUNT(*) FROM beap_beats WHERE session_id = ?");
                $bStmt->execute([(int)$row['id']]);
                $beatCount = (int)$bStmt->fetchColumn();

                $data[] = [
                    'id'          => (int)$row['id'],
                    'sketch_id'   => (int)$row['sketch_id'],
                    'sketch_name' => $row['sketch_name'],
                    'status'      => $row['status'],
                    'depth'       => $row['depth'],
                    'narseq_id'   => $row['narseq_id'] ? (int)$row['narseq_id'] : null,
                    'beat_count'  => $beatCount,
                    'created_at'  => date('Y-m-d', strtotime($row['created_at'])),
                ];
            }

            echo json_encode([
                'success' => true,
                'data'    => $data,
                'meta'    => [
                    'total'       => $total,
                    'page'        => $page,
                    'total_pages' => max(1, (int)ceil($total / $perPage)),
                ],
            ]);
            break;

        // ── Create a new BEAP session for a sketch ────────────────────────────
        case 'create_session':
            $sketchId = (int)($_POST['sketch_id'] ?? 0);
            $depth    = in_array($_POST['depth'] ?? '', ['short', 'normal', 'epic'])
                      ? $_POST['depth'] : 'normal';
            if (!$sketchId) throw new Exception('sketch_id required');

            $stmt = $pdo->prepare("SELECT id, name, description FROM sketches WHERE id = ?");
            $stmt->execute([$sketchId]);
            $sk = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sk) throw new Exception('Sketch not found');

            $ins = $pdo->prepare(
                "INSERT INTO beap_sessions (sketch_id, sketch_name, sketch_desc, depth, status)
                 VALUES (?, ?, ?, ?, 'beats_pending')"
            );
            $ins->execute([$sketchId, $sk['name'], $sk['description'], $depth]);
            $sessionId = (int)$pdo->lastInsertId();

            echo json_encode(['success' => true, 'session_id' => $sessionId]);
            break;

        // ── Load a session + its beats ────────────────────────────────────────
        case 'get_session':
            $sessionId = (int)($_GET['session_id'] ?? 0);
            if (!$sessionId) throw new Exception('session_id required');

            $stmt = $pdo->prepare("SELECT * FROM beap_sessions WHERE id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$session) throw new Exception('Session not found');

            $bStmt = $pdo->prepare(
                "SELECT id, beat_order, beat_text, shot_intent, panel_data, status
                 FROM beap_beats WHERE session_id = ? ORDER BY beat_order ASC, id ASC"
            );
            $bStmt->execute([$sessionId]);
            $beats = [];
            foreach ($bStmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
                $b['panel_data'] = $b['panel_data'] ? json_decode($b['panel_data'], true) : null;
                $beats[] = $b;
            }

            $frames = beapGetFramesForSketch($pdo, (int)$session['sketch_id']);

            echo json_encode([
                'success' => true,
                'session' => $session,
                'beats'   => $beats,
                'frames'  => $frames,
            ]);
            break;

        // ── AI Step 1: Extract beats + shot intents ───────────────────────────
        // Expects: session_id, generator_config_id
        case 'extract_beats':
            $sessionId    = (int)($_POST['session_id'] ?? 0);
            $genConfigId  = (int)($_POST['generator_config_id'] ?? 0);
            if (!$sessionId) throw new Exception('session_id required');

            $stmt = $pdo->prepare("SELECT * FROM beap_sessions WHERE id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$session) throw new Exception('Session not found');

            $sceneDesc = $session['sketch_desc'];
            $depth     = $session['depth'];

            // Build prompt context
            $promptText  = "DEPTH: $depth\n\n";
            $promptText .= "SCENE DESCRIPTION:\n$sceneDesc\n\n";
            $promptText .= "Extract narrative beats with shot intents as described. ";
            $promptText .= "Number of beats: " . match($depth) {
                'short' => '3-5',
                'epic'  => '9-15',
                default => '5-9',
            } . ".";

            // Resolve generator config
            if (!$genConfigId) {
                // Default: find by config_id slug
                $gcStmt = $pdo->prepare(
                    "SELECT id FROM generator_config WHERE config_id = 'beap_beat_extractor_v1' AND active = 1 LIMIT 1"
                );
                $gcStmt->execute();
                $genConfigId = (int)$gcStmt->fetchColumn();
            }
            if (!$genConfigId) throw new Exception('Beat extractor generator config not found. Run the DB migration.');

            $em        = $spw->getEntityManager();
            $genConfig = $em->getRepository(GeneratorConfig::class)->find($genConfigId);
            if (!$genConfig) throw new Exception('Generator config not found');

            $logger           = $spw->getFileLogger();
            $aiProvider       = $spw->getAIProvider();
            $generatorService = new GeneratorService(
                $aiProvider,
                new SchemaValidator(),
                new ResponseNormalizer(),
                $logger
            );

            $result = $generatorService->generate($genConfig, ['entity_name' => $promptText]);
            if (!$result->isSuccess()) {
                throw new Exception('AI generation failed: ' . ($result->getError() ?? 'Unknown error'));
            }

            $data  = $result->getData();
            $beats = is_array($data) ? ($data['beats'] ?? []) : [];
            if (empty($beats)) throw new Exception('AI returned no beats');

            // Delete old beats, insert new ones
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM beap_beats WHERE session_id = ?")->execute([$sessionId]);

            $ins = $pdo->prepare(
                "INSERT INTO beap_beats (session_id, beat_order, beat_text, shot_intent, status)
                 VALUES (?, ?, ?, ?, 'pending')"
            );
            foreach (array_values($beats) as $i => $beat) {
                $ins->execute([
                    $sessionId,
                    $i,
                    trim($beat['beat_text'] ?? ''),
                    trim($beat['shot_intent'] ?? ''),
                ]);
            }

            $pdo->prepare(
                "UPDATE beap_sessions SET status = 'beats_done', updated_at = NOW() WHERE id = ?"
            )->execute([$sessionId]);

            $pdo->commit();

            // Return fresh beat list
            $bStmt = $pdo->prepare(
                "SELECT id, beat_order, beat_text, shot_intent, panel_data, status
                 FROM beap_beats WHERE session_id = ? ORDER BY beat_order ASC"
            );
            $bStmt->execute([$sessionId]);
            $freshBeats = [];
            foreach ($bStmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
                $b['panel_data'] = $b['panel_data'] ? json_decode($b['panel_data'], true) : null;
                $freshBeats[] = $b;
            }

            echo json_encode(['success' => true, 'beats' => $freshBeats]);
            break;

        // ── Inline edit: save beat_text or shot_intent ────────────────────────
        case 'update_beat':
            $beatId     = (int)($_POST['beat_id'] ?? 0);
            $beatText   = trim($_POST['beat_text'] ?? '');
            $shotIntent = trim($_POST['shot_intent'] ?? '');
            if (!$beatId) throw new Exception('beat_id required');

            $pdo->prepare(
                "UPDATE beap_beats SET beat_text = ?, shot_intent = ?, updated_at = NOW() WHERE id = ?"
            )->execute([$beatText, $shotIntent, $beatId]);

            echo json_encode(['success' => true]);
            break;

        // ── AI Step 2: Panelise a single beat ────────────────────────────────
        // Sends rolling context (all beats) + focuses on one beat
        case 'panelise_beat':
            $beatId      = (int)($_POST['beat_id'] ?? 0);
            $genConfigId = (int)($_POST['generator_config_id'] ?? 0);
            if (!$beatId) throw new Exception('beat_id required');

            // Load target beat + session
            $bStmt = $pdo->prepare("SELECT * FROM beap_beats WHERE id = ?");
            $bStmt->execute([$beatId]);
            $beat = $bStmt->fetch(PDO::FETCH_ASSOC);
            if (!$beat) throw new Exception('Beat not found');

            $sessionId = (int)$beat['session_id'];

            $sess = $pdo->prepare("SELECT * FROM beap_sessions WHERE id = ?");
            $sess->execute([$sessionId]);
            $session = $sess->fetch(PDO::FETCH_ASSOC);

            // Load all beats for rolling context
            $allBeatsStmt = $pdo->prepare(
                "SELECT beat_order, beat_text, shot_intent FROM beap_beats
                 WHERE session_id = ? ORDER BY beat_order ASC"
            );
            $allBeatsStmt->execute([$sessionId]);
            $allBeats = $allBeatsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Build rolling context prompt
            $contextLines = [];
            foreach ($allBeats as $b) {
                $ord = (int)$b['beat_order'] + 1;
                $contextLines[] = "BEAT $ord: {$b['beat_text']}\nSHOT: {$b['shot_intent']}";
            }
            $rollingContext = implode("\n\n", $contextLines);

            $targetOrd   = (int)$beat['beat_order'] + 1;
            $promptText  = "FULL SCENE DESCRIPTION:\n{$session['sketch_desc']}\n\n";
            $promptText .= "ALL BEATS (FULL CONTEXT):\n$rollingContext\n\n";
            $promptText .= "TARGET BEAT TO PANELISE (Beat $targetOrd):\n";
            $promptText .= "Beat text: {$beat['beat_text']}\n";
            $promptText .= "Shot intent: {$beat['shot_intent']}\n\n";
            $promptText .= "Generate 1-3 panels for this beat only.";

            // Resolve generator config
            if (!$genConfigId) {
                $gcStmt = $pdo->prepare(
                    "SELECT id FROM generator_config WHERE config_id = 'beap_panel_composer_v1' AND active = 1 LIMIT 1"
                );
                $gcStmt->execute();
                $genConfigId = (int)$gcStmt->fetchColumn();
            }
            if (!$genConfigId) throw new Exception('Panel composer generator config not found. Run the DB migration.');

            $em        = $spw->getEntityManager();
            $genConfig = $em->getRepository(GeneratorConfig::class)->find($genConfigId);

            $generatorService = new GeneratorService(
                $spw->getAIProvider(),
                new SchemaValidator(),
                new ResponseNormalizer(),
                $spw->getFileLogger()
            );

            $result = $generatorService->generate($genConfig, ['entity_name' => $promptText]);
            if (!$result->isSuccess()) {
                throw new Exception('AI generation failed: ' . ($result->getError() ?? 'Unknown error'));
            }

            $data   = $result->getData();
            $panels = is_array($data) ? ($data['panels'] ?? []) : [];
            if (empty($panels)) throw new Exception('AI returned no panels');

            $pdo->prepare(
                "UPDATE beap_beats SET panel_data = ?, status = 'panelised', updated_at = NOW() WHERE id = ?"
            )->execute([json_encode($panels, JSON_UNESCAPED_UNICODE), $beatId]);

            echo json_encode(['success' => true, 'panels' => $panels]);
            break;

        // ── Export panelised beats to a new narrative_sequence + sketches ─────
        case 'export_to_sequence':
            $sessionId  = (int)($_POST['session_id'] ?? 0);
            $seqName    = trim($_POST['seq_name'] ?? '');
            if (!$sessionId) throw new Exception('session_id required');
            if (!$seqName)   $seqName = 'BEAP Export — Session #' . $sessionId;

            $sess = $pdo->prepare("SELECT * FROM beap_sessions WHERE id = ?");
            $sess->execute([$sessionId]);
            $session = $sess->fetch(PDO::FETCH_ASSOC);
            if (!$session) throw new Exception('Session not found');

            $bStmt = $pdo->prepare(
                "SELECT id, beat_order, beat_text, shot_intent, panel_data
                 FROM beap_beats WHERE session_id = ? AND status = 'panelised' ORDER BY beat_order ASC"
            );
            $bStmt->execute([$sessionId]);
            $beats = $bStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($beats)) throw new Exception('No panelised beats to export');

            $pdo->beginTransaction();

            // Create a new narrative_sequence
            $insSeq = $pdo->prepare(
                "INSERT INTO narrative_sequences (name, description, sequence_data) VALUES (?, ?, '[]')"
            );
            $seqDesc = "BEAP export from sketch #{$session['sketch_id']} — {$session['sketch_name']}";
            $insSeq->execute([$seqName, $seqDesc]);
            $newSeqId = (int)$pdo->lastInsertId();

            // For each panel in each beat: create a sketch row, then add to sequence
            $sketchIds = [];
            $insSketch = $pdo->prepare(
                "INSERT INTO sketches (name, description, searchable) VALUES (?, ?, 1)"
            );

            $panelCounter = 1;
            foreach ($beats as $beat) {
                $panels = json_decode($beat['panel_data'] ?? '[]', true) ?: [];
                foreach ($panels as $panel) {
                    $skName  = $seqName . ' — Panel ' . $panelCounter;
                    $skDesc  = trim(($panel['panel_prompt'] ?? '') . "\n\n" .
                                   '[Layout: ' . ($panel['layout_hint'] ?? '') . ']' . "\n" .
                                   '[Action: ' . ($panel['action_note'] ?? '') . ']');

                    // Ensure unique name
                    $checkName = $skName;
                    $suffix = 1;
                    while (true) {
                        $chk = $pdo->prepare("SELECT id FROM sketches WHERE name = ? LIMIT 1");
                        $chk->execute([$checkName]);
                        if (!$chk->fetchColumn()) break;
                        $checkName = $skName . ' (' . $suffix . ')';
                        $suffix++;
                    }

                    $insSketch->execute([$checkName, $skDesc]);
                    $sketchIds[] = (int)$pdo->lastInsertId();
                    $panelCounter++;
                }
            }

            // Build sequence_data JSON
            $seqData = array_map(fn($sid) => ['sketch_id' => $sid, 'frame_id' => 0], $sketchIds);
            $pdo->prepare("UPDATE narrative_sequences SET sequence_data = ? WHERE id = ?")
                ->execute([json_encode($seqData, JSON_UNESCAPED_UNICODE), $newSeqId]);

            // Mark session as exported
            $pdo->prepare(
                "UPDATE beap_sessions SET narseq_id = ?, status = 'exported', updated_at = NOW() WHERE id = ?"
            )->execute([$newSeqId, $sessionId]);

            $pdo->commit();

            echo json_encode([
                'success'      => true,
                'narseq_id'    => $newSeqId,
                'sketch_count' => count($sketchIds),
            ]);
            break;

        // ── Delete a session (cascades to beats via FK) ────────────────────────
        case 'delete_session':
            $sessionId = (int)($_POST['session_id'] ?? 0);
            if (!$sessionId) throw new Exception('session_id required');
            $pdo->prepare("DELETE FROM beap_sessions WHERE id = ?")->execute([$sessionId]);
            echo json_encode(['success' => true]);
            break;

        // ── Update depth on a session ────────────────────────────────────────
        case 'update_session_depth':
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $depth     = in_array($_POST['depth'] ?? '', ['short', 'normal', 'epic'])
                       ? $_POST['depth'] : 'normal';
            if (!$sessionId) throw new Exception('session_id required');
            $pdo->prepare("UPDATE beap_sessions SET depth = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$depth, $sessionId]);
            echo json_encode(['success' => true]);
            break;

        // ── Sequence categories (for the category filter select) ─────────────
        case 'get_sequence_categories':
            try {
                $stmt = $pdo->query(
                    "SELECT id, name FROM narrative_sequence_categories ORDER BY name ASC"
                );
                $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $cats]);
            } catch (Exception $e) {
                // Table may not exist in all installations — return empty gracefully
                echo json_encode(['success' => true, 'data' => []]);
            }
            break;

        // ── Get available generator configs for BEAP steps ───────────────────
        case 'get_generator_configs':
            $stmt = $pdo->prepare(
                "SELECT id, title, config_id FROM generator_config WHERE active = 1 ORDER BY list_order ASC, title ASC"
            );
            $stmt->execute();
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ── Delete a single beat ──────────────────────────────────────────────
        case 'delete_beat':
            $beatId = (int)($_POST['beat_id'] ?? 0);
            if (!$beatId) throw new Exception('beat_id required');
            // Reorder remaining beats after deletion
            $pdo->beginTransaction();
            $sessRow = $pdo->prepare("SELECT session_id FROM beap_beats WHERE id = ?");
            $sessRow->execute([$beatId]);
            $sessionId = (int)$sessRow->fetchColumn();
            if (!$sessionId) throw new Exception('Beat not found');
            $pdo->prepare("DELETE FROM beap_beats WHERE id = ?")->execute([$beatId]);
            // Re-sequence beat_order
            $remaining = $pdo->prepare(
                "SELECT id FROM beap_beats WHERE session_id = ? ORDER BY beat_order ASC, id ASC"
            );
            $remaining->execute([$sessionId]);
            $upd = $pdo->prepare("UPDATE beap_beats SET beat_order = ? WHERE id = ?");
            foreach ($remaining->fetchAll(PDO::FETCH_COLUMN) as $i => $rid) {
                $upd->execute([$i, $rid]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        // ── Get all characters (for character picker modal) ───────────────────
        case 'get_characters':
            $q      = trim($_GET['q'] ?? '');
            $where  = "1=1";
            $params = [];
            if ($q !== '') {
                $where  = "(name LIKE ? OR description LIKE ?)";
                $params = ["%$q%", "%$q%"];
            }
            $stmt = $pdo->prepare(
                "SELECT id, name, description FROM characters WHERE $where ORDER BY name ASC LIMIT 120"
            );
            $stmt->execute($params);
            $chars = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $chars[] = [
                    'id'   => (int)$c['id'],
                    'name' => $c['name'],
                    'desc' => mb_substr(strip_tags($c['description'] ?? ''), 0, 100),
                ];
            }
            echo json_encode(['success' => true, 'data' => $chars]);
            break;

        // ── Character continuity rewrite of a single beat's beat_text ─────────
        // Mirrors sketch_continuity.php logic but scoped to one beat.
        // Sends: scene context + all beats (rolling) + character details + target beat.
        // Returns: rewritten beat_text saved to DB.
        case 'continuity_beat':
            $beatId      = (int)($_POST['beat_id'] ?? 0);
            $charIds     = array_filter(array_map('intval', (array)($_POST['character_ids'] ?? [])));
            $genConfigId = (int)($_POST['generator_config_id'] ?? 0);
            if (!$beatId)         throw new Exception('beat_id required');
            if (empty($charIds))  throw new Exception('No characters selected');

            // Load beat + session
            $bStmt = $pdo->prepare("SELECT * FROM beap_beats WHERE id = ?");
            $bStmt->execute([$beatId]);
            $beat = $bStmt->fetch(PDO::FETCH_ASSOC);
            if (!$beat) throw new Exception('Beat not found');

            $sess = $pdo->prepare("SELECT * FROM beap_sessions WHERE id = ?");
            $sess->execute([(int)$beat['session_id']]);
            $session = $sess->fetch(PDO::FETCH_ASSOC);

            // Load all beats for rolling context
            $allBeatsStmt = $pdo->prepare(
                "SELECT beat_order, beat_text, shot_intent FROM beap_beats
                 WHERE session_id = ? ORDER BY beat_order ASC"
            );
            $allBeatsStmt->execute([(int)$beat['session_id']]);
            $allBeats = $allBeatsStmt->fetchAll(PDO::FETCH_ASSOC);

            $contextLines = [];
            foreach ($allBeats as $b) {
                $ord = (int)$b['beat_order'] + 1;
                $contextLines[] = "BEAT $ord: {$b['beat_text']}\nSHOT: {$b['shot_intent']}";
            }

            // Load character details
            $ph    = implode(',', array_fill(0, count($charIds), '?'));
            $cStmt = $pdo->prepare("SELECT name, description FROM characters WHERE id IN ($ph)");
            $cStmt->execute($charIds);
            $charContext = '';
            foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $desc = trim(strip_tags($c['description'] ?? ''));
                $charContext .= "CHARACTER: {$c['name']}\nDESCRIPTION: {$desc}\n\n";
            }

            $targetOrd  = (int)$beat['beat_order'] + 1;
            $promptText  = "FULL SCENE DESCRIPTION:\n{$session['sketch_desc']}\n\n";
            $promptText .= "ALL BEATS (FULL CONTEXT):\n" . implode("\n\n", $contextLines) . "\n\n";
            $promptText .= "TARGET BEAT (Beat $targetOrd):\n{$beat['beat_text']}\n";
            $promptText .= "Shot intent: {$beat['shot_intent']}\n\n";
            $promptText .= "REQUIRED CHARACTER CONTINUITY DETAILS:\n$charContext";
            $promptText .= "TASK: Rewrite the TARGET BEAT text incorporating the exact character details above. ";
            $promptText .= "Preserve the original shot intent, action, and mood. Return strict JSON with key scene_prompt only.";

            // Resolve generator config — prefer continuity config, then fallback to any active
            if (!$genConfigId) {
                // Try the same config slug that sketch_continuity uses (id 126 / e2a4d0f6...)
                $gcStmt = $pdo->prepare(
                    "SELECT id FROM generator_config WHERE config_id = 'e2a4d0f6c1b84f2c9a7d1e5b3f6a8c10' AND active = 1 LIMIT 1"
                );
                $gcStmt->execute();
                $genConfigId = (int)$gcStmt->fetchColumn();
            }
            if (!$genConfigId) {
                // Any active config as last resort
                $gcStmt = $pdo->prepare("SELECT id FROM generator_config WHERE active = 1 ORDER BY id ASC LIMIT 1");
                $gcStmt->execute();
                $genConfigId = (int)$gcStmt->fetchColumn();
            }
            if (!$genConfigId) throw new Exception('No generator config available');

            $em        = $spw->getEntityManager();
            $genConfig = $em->getRepository(GeneratorConfig::class)->find($genConfigId);

            $generatorService = new GeneratorService(
                $spw->getAIProvider(),
                new SchemaValidator(),
                new ResponseNormalizer(),
                $spw->getFileLogger()
            );

            $result = $generatorService->generate($genConfig, ['entity_name' => $promptText]);
            if (!$result->isSuccess()) {
                throw new Exception('AI generation failed: ' . ($result->getError() ?? 'Unknown error'));
            }

            $data    = $result->getData();
            $newText = '';
            if (is_array($data)) {
                $newText = $data['scene_prompt'] ?? $data['beat_text'] ?? $data['description'] ?? $data['text'] ?? json_encode($data);
            } else {
                $newText = (string)$data;
            }
            $newText = trim(str_replace(["\u{2014}", "—"], '', $newText));

            if (empty($newText)) throw new Exception('AI returned empty text');

            $pdo->prepare("UPDATE beap_beats SET beat_text = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$newText, $beatId]);

            echo json_encode(['success' => true, 'new_beat_text' => $newText]);
            break;

        // ── Get latest frame for a sketch (for seq sketch thumbnails) ─────────
        case 'get_sketch_thumb':
            $sketchId = (int)($_GET['sketch_id'] ?? 0);
            if (!$sketchId) { echo json_encode(['success' => false]); break; }
            $frames = beapGetFramesForSketch($pdo, $sketchId);
            echo json_encode([
                'success' => true,
                'frame'   => !empty($frames) ? $frames[0] : null,
            ]);
            break;

        default:
            throw new Exception('Invalid action: ' . htmlspecialchars($action));
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
