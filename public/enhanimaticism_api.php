<?php
// public/enhanimaticism_api.php
// Enhanimatics API Handler — extracted from enhanimaticism.php
// ----------------------------------------------------
// Called via require_once from enhanimaticism.php when api_action is set.
// Expects: $pdo, $allowedEntities, $entityType already defined by the caller.
// Terminates with json output + exit on every branch.

header('Content-Type: application/json');
$action = $_REQUEST['api_action'];

// Accept entity param from request (validate)
$reqEntity = $_REQUEST['entity'] ?? $entityType;
if (!in_array($reqEntity, $allowedEntities, true)) {
    $reqEntity = $entityType;
}

try {
    // ═════════════════════════════════════════════════════════════════════
    // FORGE FILTER PROVIDERS (LISTING Fuzz, Lore, KG, Seq, Storyboards)
    // ═════════════════════════════════════════════════════════════════════
    if ($action === 'list_forge_items') {
        $mode = $_GET['mode'] ?? 'fuzz';
        $q = $_GET['q'] ?? '';
        $items = [];
        $params = [];

        if ($mode === 'fuzz') {
            $sql = "SELECT id, label, concept_type, status FROM fuzz_candidates ";
            if ($q) { $sql .= "WHERE label LIKE ? "; $params[] = "%$q%"; }
            $sql .= "ORDER BY updated_at DESC LIMIT 50";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $items = array_map(fn($r) => ['id' => $r['id'], 'label' => $r['label'], 'meta' => "Type: {$r['concept_type']} | Status: {$r['status']}"], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($mode === 'doc') {
            // Curated docs that have been analyzed (same source as rapid_lore_api_processor)
            $sql = "SELECT d.id, d.name, d.updated_at, dc.name as cat_name
                    FROM documentations d
                    JOIN md_doc_analysis da ON d.id = da.doc_id
                    LEFT JOIN documentation_categories dc ON d.category_id = dc.id
                    WHERE d.is_active = 1 ";
            if ($q) { $sql .= "AND d.name LIKE ? "; $params[] = "%$q%"; }
            $sql .= "ORDER BY d.updated_at DESC LIMIT 50";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $items = array_map(fn($r) => [
                'id'           => $r['id'],
                'label'        => $r['name'],
                'meta'         => ($r['cat_name'] ? $r['cat_name'] . ' · ' : '') . 'Updated: ' . substr($r['updated_at'], 0, 10),
                'has_episodes' => true
            ], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($mode === 'doc_entities') {
            // Sub-step: load entity lists from LoreAccessService for a given doc_id
            // Returns grouped sections: episodes, scene_hooks, characters, locations, factions, artifacts
            $docId = (int)($_GET['doc_id'] ?? 0);
            if ($docId) {
                require_once __DIR__ . '/../src/Service/LoreAccessService.php';
                $service = new \App\Service\LoreAccessService($pdo);
                try {
                    $service->loadDoc($docId);
                    $story = $service->getStoryEngine();
                    $world = $service->getWorldData(); // aggregated entities keyed by category

                    $sections = [];

                    // Story sections
                    $storySections = [
                        'episodes'         => 'Episodes',
                        'scene_hooks'      => 'Scene Hooks',
                        'narrative_engine' => 'Narrative Engine',
                    ];
                    foreach ($storySections as $key => $label) {
                        $list = $story[$key] ?? [];
                        if (!empty($list)) {
                            $names = [];
                            foreach ($list as $item) {
                                $name = is_array($item) ? ($item['name'] ?? ($item['title'] ?? ($item['event'] ?? null))) : (string)$item;
                                if ($name) $names[] = $name;
                            }
                            if (!empty($names)) $sections[] = ['section' => $label, 'items' => $names, 'type' => $key];
                        }
                    }

                    // World entity sections
                    $worldSections = ['characters', 'locations', 'factions', 'artifacts'];
                    foreach ($worldSections as $cat) {
                        $list = $world[$cat] ?? [];
                        if (!empty($list)) {
                            $names = [];
                            foreach ($list as $ent) {
                                $name = $ent['name'] ?? null;
                                if ($name && $name !== 'Unknown') $names[] = $name;
                            }
                            if (!empty($names)) $sections[] = ['section' => ucfirst($cat), 'items' => $names, 'type' => $cat];
                        }
                    }

                    echo json_encode(['status' => 'success', 'sections' => $sections]); exit;
                } catch (\Exception $e) {
                    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); exit;
                }
            }
            echo json_encode(['status' => 'success', 'sections' => []]); exit;
        } elseif ($mode === 'kg') {
            // Only show KG nodes that have sketches linked via sketch_lore_history
            // (entity_name in slh matches the node name) — prevents showing thousands of unused nodes
            $sql = "SELECT kn.id, kn.name, kn.node_type
                    FROM kg_nodes kn
                    WHERE kn.status = 'active'
                      AND EXISTS (
                          SELECT 1 FROM sketch_lore_history slh WHERE slh.entity_name = kn.name LIMIT 1
                      ) ";
            if ($q) { $sql .= "AND kn.name LIKE ? "; $params[] = "%$q%"; }
            $sql .= "ORDER BY kn.name ASC LIMIT 100";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $items = array_map(fn($r) => ['id' => $r['id'], 'label' => $r['name'], 'meta' => "Type: {$r['node_type']}"], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($mode === 'seq') {
            $sql = "SELECT id, name, description FROM narrative_sequences ";
            if ($q) { $sql .= "WHERE name LIKE ? "; $params[] = "%$q%"; }
            $sql .= "ORDER BY id DESC LIMIT 50";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $items = array_map(fn($r) => ['id' => $r['id'], 'label' => $r['name'], 'meta' => mb_substr($r['description'], 0, 50)], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($mode === 'storyboard') {
            $sql = "SELECT id, name, description FROM storyboards WHERE is_archived = 0 ";
            if ($q) { $sql .= "AND name LIKE ? "; $params[] = "%$q%"; }
            $sql .= "ORDER BY updated_at DESC LIMIT 50";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $items = array_map(fn($r) => ['id' => $r['id'], 'label' => $r['name'] ?: 'Untitled', 'meta' => mb_substr($r['description'], 0, 50)], $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        echo json_encode(['status' => 'success', 'data' => $items]); exit;
    }

    // ═════════════════════════════════════════════════════════════════════
    // Helper: Compute Intersection IDs based on Active Forge Filters
    // ═════════════════════════════════════════════════════════════════════
    function computeForgeIntersection($pdo, $reqEntity, $filtersJson) {
        $filters = json_decode($filtersJson, true);
        if (empty($filters)) return null; // No filters = no restriction

        $intersectIds = null;
        foreach ($filters as $f) {
            $type = $f['type'];
            $id = $f['id'] ?? null;
            $text = $f['text'] ?? null;
            $foundIds = [];

            if ($type === 'fuzz') {
                $stmt = $pdo->prepare("SELECT DISTINCT source_row_id FROM fuzz_mentions WHERE candidate_id = ? AND source_table = ? AND source_row_id IS NOT NULL");
                $stmt->execute([$id, $reqEntity]);
                $foundIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($type === 'doc') {
                $docId = $id;
                $entityName  = $f['entity_name']  ?? null;
                $entityNames = $f['entity_names']  ?? null; // whole-section: array of names
                if ($reqEntity === 'sketches') {
                    if ($entityNames && is_array($entityNames)) {
                        // Whole section — OR-match all entity names in section
                        $placeholders = implode(',', array_fill(0, count($entityNames), '?'));
                        $params = array_merge([$docId], $entityNames);
                        $stmt = $pdo->prepare("SELECT DISTINCT sketch_id FROM sketch_lore_history WHERE doc_id = ? AND entity_name IN ($placeholders)");
                        $stmt->execute($params);
                        $foundIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    } elseif ($entityName) {
                        // Specific entity — exact match first, LIKE fallback
                        $stmt = $pdo->prepare("SELECT DISTINCT sketch_id FROM sketch_lore_history WHERE doc_id = ? AND entity_name = ?");
                        $stmt->execute([$docId, $entityName]);
                        $foundIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        if (empty($foundIds)) {
                            $stmt2 = $pdo->prepare("SELECT DISTINCT sketch_id FROM sketch_lore_history WHERE doc_id = ? AND entity_name LIKE ?");
                            $stmt2->execute([$docId, '%' . $entityName . '%']);
                            $foundIds = $stmt2->fetchAll(PDO::FETCH_COLUMN);
                        }
                    } else {
                        // Whole document
                        $stmt = $pdo->prepare("SELECT DISTINCT sketch_id FROM sketch_lore_history WHERE doc_id = ?");
                        $stmt->execute([$docId]);
                        $foundIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    }
                }
                // non-sketch entities: no slh link, no restriction
            } elseif ($type === 'kg') {
                // The real KG → sketch link is through sketch_lore_history:
                // sketch_lore_history.entity_name matches kg_nodes.name
                // Also check kg_node_items for direct item_id links (secondary path)
                $singularMap = ['sketches'=>'sketch','characters'=>'character','locations'=>'location',
                                'factions'=>'faction','artifacts'=>'artifact','animatics'=>'animatic'];
                $singularType = $singularMap[$reqEntity] ?? rtrim($reqEntity, 's');

                // Primary: sketch_lore_history.entity_name = node name
                $nodeNameStmt = $pdo->prepare("SELECT name FROM kg_nodes WHERE id = ?");
                $nodeNameStmt->execute([$id]);
                $nodeName = $nodeNameStmt->fetchColumn();

                if ($nodeName && $reqEntity === 'sketches') {
                    $stmt = $pdo->prepare("SELECT DISTINCT sketch_id FROM sketch_lore_history WHERE entity_name = ?");
                    $stmt->execute([$nodeName]);
                    $foundIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                }

                // Secondary: direct kg_node_items links (e.g. manually linked sketches/entities)
                $stmt2 = $pdo->prepare("
                    SELECT DISTINCT item_id FROM kg_node_items
                    WHERE node_id = ? AND item_type = ? AND item_id IS NOT NULL
                ");
                $stmt2->execute([$id, $singularType]);
                $direct = $stmt2->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($direct)) {
                    $foundIds = array_unique(array_merge($foundIds, $direct));
                }

                // Tertiary: via fuzz_candidates linked to this kg_node
                $stmt3 = $pdo->prepare("
                    SELECT DISTINCT m.source_row_id FROM fuzz_mentions m
                    JOIN fuzz_candidates c ON m.candidate_id = c.id
                    WHERE c.kg_node_id = ? AND m.source_table = ? AND m.source_row_id IS NOT NULL
                ");
                $stmt3->execute([$id, $reqEntity]);
                $viaFuzz = $stmt3->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($viaFuzz)) {
                    $foundIds = array_unique(array_merge($foundIds, $viaFuzz));
                }
            } elseif ($type === 'seq' && $reqEntity === 'sketches') {
                $stmt = $pdo->prepare("
                    SELECT CASE WHEN JSON_TYPE(jt.val) = 'INTEGER' THEN JSON_VALUE(jt.val, '$') ELSE JSON_VALUE(jt.val, '$.sketch_id') END
                    FROM narrative_sequences ns, JSON_TABLE(ns.sequence_data, '$[*]' COLUMNS(val JSON PATH '$')) jt
                    WHERE ns.id = ?
                ");
                $stmt->execute([$id]);
                $foundIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($type === 'storyboard') {
                $stmt = $pdo->prepare("SELECT DISTINCT f.entity_id FROM frames f JOIN storyboard_frames sf ON f.id = sf.frame_id WHERE sf.storyboard_id = ? AND f.entity_type = ? AND f.entity_id IS NOT NULL");
                $stmt->execute([$id, $reqEntity]);
                $foundIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($type === 'vector_text' && $textQuery = $text) {
                $vectorService = new \App\Core\PyApiVectorService();
                $collection = ($reqEntity === 'sketches') ? 'sage_sketches_nu' : 'sage_lore_entities_draft';
                $chromaRes = $vectorService->query($textQuery, null, $collection, 'text', 30);
                if (!empty($chromaRes['result']['ids'][0])) {
                    $rawIds = $chromaRes['result']['ids'][0];
                    foreach ($rawIds as $rid) {
                        if (preg_match('/(?:sketch|entity)_(\d+)/', $rid, $m)) $foundIds[] = (int)$m[1];
                    }
                }
            }

            if ($intersectIds === null) {
                $intersectIds = $foundIds;
            } else {
                $intersectIds = array_intersect($intersectIds, $foundIds);
            }
        }
        return $intersectIds;
    }

    // 1. GET MAP RUNS (for specific entity)
    if ($action === 'get_map_runs') {
        $limit  = (int)($_GET['limit'] ?? 4);
        $offset = (int)($_GET['offset'] ?? 0);
        $search = $_GET['search'] ?? '';

        $where = "entity_type = " . $pdo->quote($reqEntity);
        if ($search) {
            $safeSearch = $pdo->quote("%$search%");
            $safeId     = intval($search);
            $where .= " AND (note LIKE $safeSearch OR id = $safeId)";
        }

        // Apply Forge Intersection
        $intersectIds = computeForgeIntersection($pdo, $reqEntity, $_GET['filters'] ?? '[]');
        if ($intersectIds !== null) {
            if (empty($intersectIds)) { $where .= " AND 1=0"; }
            else {
                $in = implode(',', array_map('intval', $intersectIds));
                $where .= " AND id IN (SELECT DISTINCT map_run_id FROM frames WHERE entity_type = '{$reqEntity}' AND entity_id IN ($in))";
            }
        }

        $total = $pdo->query("SELECT COUNT(*) FROM map_runs WHERE $where")->fetchColumn();
        $sql   = "SELECT *, (SELECT COUNT(*) FROM frames WHERE map_run_id = map_runs.id) as frame_count 
                  FROM map_runs WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset";
        $rows  = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status'=>'success', 'data'=>$rows, 'total'=>$total]);
        exit;
    }

    // 1a. GET ENTITIES (Entity mode)
    if ($action === 'get_entities') {
        $limit  = (int)($_GET['limit'] ?? 1);
        $offset = (int)($_GET['offset'] ?? 0);
        $search = $_GET['search'] ?? '';
        $sort   = $_GET['sort'] ?? 'id';

        $where = "1=1";
        if ($search) {
            $safeSearch = $pdo->quote("%$search%");
            $safeId     = intval($search);
            $where .= " AND (name LIKE $safeSearch OR id = $safeId)";
        }

        $table = "`" . str_replace('`', '', $reqEntity) . "`";
        $mapTable = "`frames_2_" . str_replace('`', '', $reqEntity) . "`";

        // Apply Forge Intersection
        $intersectIds = computeForgeIntersection($pdo, $reqEntity, $_GET['filters'] ?? '[]');
        if ($intersectIds !== null) {
            if (empty($intersectIds)) { $where .= " AND 1=0"; }
            else {
                $in = implode(',', array_map('intval', $intersectIds));
                $where .= " AND $table.id IN ($in)";
            }
        }

        $orderBy = "id DESC";
        if ($sort === 'latest_frame') {
            // Optimized: Sort using the integer index of the mapping table
            $orderBy = "(SELECT MAX(from_id) FROM $mapTable WHERE to_id = $table.id) DESC, id DESC";
        }

        $total = $pdo->query("SELECT COUNT(*) FROM $table WHERE $where")->fetchColumn();
        // Optimized: Count frames using the high-speed mapping table index instead of the heavy frames table
        $sql   = "SELECT *, 
                    (SELECT COUNT(from_id) FROM $mapTable WHERE to_id = $table.id) as frame_count 
                  FROM $table WHERE $where ORDER BY $orderBy LIMIT $limit OFFSET $offset";
        $rows  = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status'=>'success', 'data'=>$rows, 'total'=>$total]);
        exit;
    }

    // 1b-frames. GET FRAMES DIRECT (Frames mode — paginated flat frame list)
    if ($action === 'get_frames_direct') {
        $limit  = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        $search = $_GET['search'] ?? '';
        $sort   = $_GET['sort'] ?? 'id';

        $where = "f.entity_type = " . $pdo->quote($reqEntity);
        if ($search) {
            $safeSearch = $pdo->quote("%$search%");
            $safeId     = intval($search);
            $where .= " AND (f.name LIKE $safeSearch OR f.id = $safeId OR e.name LIKE $safeSearch)";
        }

        // Apply Forge Intersection (entity_id must be in intersect set)
        $intersectIds = computeForgeIntersection($pdo, $reqEntity, $_GET['filters'] ?? '[]');
        if ($intersectIds !== null) {
            if (empty($intersectIds)) { $where .= " AND 1=0"; }
            else {
                $in = implode(',', array_map('intval', $intersectIds));
                $where .= " AND f.entity_id IN ($in)";
            }
        }

        $table = "`" . str_replace('`', '', $reqEntity) . "`";
        $orderBy = $sort === 'entity_id' ? "f.entity_id DESC, f.id DESC" : "f.id DESC";

        $countSql = "SELECT COUNT(*) FROM frames f
                     LEFT JOIN $table e ON e.id = f.entity_id
                     WHERE $where";
        $total = $pdo->query($countSql)->fetchColumn();

        $sql = "SELECT f.id as frame_id, f.filename, f.name, f.prompt, f.entity_id,
                       COALESCE(e.name, '') as entity_name,
                       CASE WHEN EXISTS (SELECT 1 FROM animatics a WHERE a.img2img_frame_id = f.id) THEN 1 ELSE 0 END as is_imported,
                       CASE WHEN EXISTS (SELECT 1 FROM frame_enhancements fe WHERE fe.img2img_frame_id = f.id) THEN 1 ELSE 0 END as is_enhanced
                FROM frames f
                LEFT JOIN $table e ON e.id = f.entity_id
                WHERE $where
                ORDER BY $orderBy
                LIMIT $limit OFFSET $offset";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status'=>'success', 'data'=>$rows, 'total'=>$total]);
        exit;
    }

    // 1a.1 CREATE ENTITY
    if ($action === 'add_entity') {
        $table = "`" . $reqEntity . "`";
        $stmt = $pdo->query("SHOW COLUMNS FROM $table");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $hasOrder = in_array('order', $cols);

        $uniqueName = "New " . ucfirst($reqEntity) . " " . time();
        $insertCols = ['name'];
        $insertVals = ['?'];
        $params = [$uniqueName];

        if ($hasOrder) {
            $insertCols[] = '`order`';
            $insertVals[] = '0';
        }

        $sql = "INSERT INTO $table (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $insertVals) . ")";
        $pdo->prepare($sql)->execute($params);
        $newId = $pdo->lastInsertId();

        echo json_encode(['status' => 'success', 'id' => $newId]);
        exit;
    }

    // 1a.2 DELETE ENTITY
    if ($action === 'delete_entity') {
        $id = (int)($_POST['entity_id'] ?? 0);
        if ($id > 0) {
            $table = "`" . $reqEntity . "`";
            $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
        }
        echo json_encode(['status' => 'success']);
        exit;
    }

    // 1a.3 COPY ENTITY
    if ($action === 'copy_entity') {
        $id = (int)($_POST['entity_id'] ?? 0);
        if ($id > 0) {
            $table = "`" . $reqEntity . "`";
            $stmt = $pdo->query("SHOW COLUMNS FROM $table");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $colsList = implode(", ", array_map(fn($c) => "`$c`", array_filter($columns, fn($c)=> $c !== 'id')));
            $stmt = $pdo->prepare("SELECT $colsList FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                if(isset($row['name'])) $row['name'] .= ' (Copy)';
                $placeholders = implode(", ", array_fill(0, count($row), '?'));
                $insertStmt = $pdo->prepare("INSERT INTO $table ($colsList) VALUES ($placeholders)");
                $insertStmt->execute(array_values($row));
                $newId = $pdo->lastInsertId();
                echo json_encode(['status' => 'success', 'id' => $newId]);
                exit;
            }
        }
        echo json_encode(['status' => 'error', 'message' => 'Entity not found or invalid']);
        exit;
    }

    // 1a.4 TOGGLE REGENERATE
    if ($action === 'toggle_regenerate') {
        $id = (int)($_POST['entity_id'] ?? 0);
        $val = (int)($_POST['value'] ?? 0);
        $col = $_POST['column'] ?? '';
        $table = "`" . str_replace('`', '', $reqEntity) . "`";

        $stmt = $pdo->query("SHOW COLUMNS FROM $table");
        $validCols = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($id > 0 && in_array($col, $validCols)) {
            $pdo->prepare("UPDATE $table SET `$col` = ? WHERE id = ?")->execute([$val, $id]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
        }
        exit;
    }

    // 1b. REGENERATE MAP RUN
    if ($action === 'regenerate_run') {
        $runId = (int)($_POST['map_run_id'] ?? $_GET['map_run_id'] ?? 0);
        if (!$runId) throw new Exception('Invalid run ID');

        $stmt = $pdo->prepare("SELECT DISTINCT entity_id FROM frames WHERE map_run_id = :mid AND entity_type = 'sketches'");
        $stmt->execute(['mid' => $runId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($ids)) {
            $inQuery = implode(',', array_fill(0, count($ids), '?'));
            $upStmt = $pdo->prepare("UPDATE sketches SET regenerate_images = 1 WHERE id IN ($inQuery)");
            $upStmt->execute($ids);
        }

        echo json_encode(['status' => 'success', 'count' => count($ids)]);
        exit;
    }

    // 1c. GET STORYBOARDS (for run/frame storyboard import picker)
    if ($action === 'get_storyboards') {
        $sql = "
            SELECT s.id, s.name, s.custom_tag, s.category_id,
                   COUNT(sf.id) as frame_count,
                   cat.name as category_name, cat.code as category_code,
                   sc.name as scene_name,
                   ep.number as episode_number,
                   ep.id as episode_id,
                   sq.id as sequence_id
            FROM storyboards s
            LEFT JOIN storyboard_frames sf ON s.id = sf.storyboard_id
            LEFT JOIN storyboard_categories cat ON s.category_id = cat.id
            LEFT JOIN editorial_scenes sc ON s.editorial_scene_id = sc.id
            LEFT JOIN editorial_sequences sq ON sc.sequence_id = sq.id
            LEFT JOIN editorial_episodes ep ON sq.episode_id = ep.id
            WHERE s.is_archived = 0
            GROUP BY s.id
            ORDER BY s.updated_at DESC
        ";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $cats = $pdo->query("SELECT id, name, code FROM storyboard_categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
        $eps  = $pdo->query("SELECT id, name, number FROM editorial_episodes ORDER BY number ASC")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'boards' => $rows, 'cats' => $cats, 'eps' => $eps]);
        exit;
    }

    // 1d. IMPORT RUN TO STORYBOARD
    if ($action === 'import_run_to_storyboard') {
        $runId = (int)($_POST['map_run_id'] ?? 0);
        $sbId  = (int)($_POST['storyboard_id'] ?? 0);
        if (!$runId || !$sbId) throw new Exception('Invalid parameters');

        $sbStmt = $pdo->prepare("SELECT id, name FROM storyboards WHERE id = ?");
        $sbStmt->execute([$sbId]);
        $sb = $sbStmt->fetch(PDO::FETCH_ASSOC);
        if (!$sb) throw new Exception('Storyboard not found');

        $fStmt = $pdo->prepare("SELECT id, filename, name, prompt FROM frames WHERE map_run_id = ? ORDER BY id ASC");
        $fStmt->execute([$runId]);
        $frames = $fStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($frames)) throw new Exception('No frames found in this run');

        $maxOrder = (int)$pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM storyboard_frames WHERE storyboard_id = ?")
            ->execute([$sbId]) ? $pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM storyboard_frames WHERE storyboard_id = $sbId")->fetchColumn() : 0;

        $insertStmt = $pdo->prepare("
            INSERT INTO storyboard_frames (storyboard_id, frame_id, name, description, filename, sort_order, is_copied, original_filename)
            VALUES (?, ?, ?, ?, ?, ?, 0, ?)
        ");

        $count = 0;
        $pdo->beginTransaction();
        foreach ($frames as $i => $f) {
            $insertStmt->execute([
                $sbId, $f['id'],
                $f['name'] ?: ('Frame #' . $f['id']),
                $f['prompt'] ?: '',
                '',
                $maxOrder + $i + 1,
                ltrim($f['filename'], '/')
            ]);
            $count++;
        }
        $pdo->prepare("UPDATE storyboards SET updated_at = NOW() WHERE id = ?")->execute([$sbId]);
        $pdo->commit();

        echo json_encode(['status' => 'success', 'count' => $count, 'storyboard_name' => $sb['name']]);
        exit;
    }

    // 1e. IMPORT FRAMES TO STORYBOARD (STBA Button)
    if ($action === 'import_frames_to_storyboard') {
        $input    = json_decode(file_get_contents('php://input'), true);
        $frameIds = array_map('intval', $input['frame_ids'] ?? []);
        $sbId     = (int)($input['storyboard_id'] ?? 0);

        if (empty($frameIds) || !$sbId) throw new Exception('Invalid parameters');

        $sbStmt = $pdo->prepare("SELECT id, name FROM storyboards WHERE id = ?");
        $sbStmt->execute([$sbId]);
        $sb = $sbStmt->fetch(PDO::FETCH_ASSOC);
        if (!$sb) throw new Exception('Storyboard not found');

        $idsStr = implode(',', $frameIds);

        $fStmt = $pdo->query("SELECT id, filename, name, prompt FROM frames WHERE id IN ($idsStr) ORDER BY FIELD(id, $idsStr)");
        $frames = $fStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($frames)) throw new Exception('No frames found');

        $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM storyboard_frames WHERE storyboard_id = $sbId")->fetchColumn();

        $insertStmt = $pdo->prepare("
            INSERT INTO storyboard_frames (storyboard_id, frame_id, name, description, filename, sort_order, is_copied, original_filename)
            VALUES (?, ?, ?, ?, ?, ?, 0, ?)
        ");

        $count = 0;
        $pdo->beginTransaction();
        foreach ($frames as $i => $f) {
            $insertStmt->execute([
                $sbId,
                $f['id'],
                $f['name'] ?: ('Frame #' . $f['id']),
                $f['prompt'] ?: '',
                '',
                $maxOrder + $i + 1,
                ltrim($f['filename'], '/')
            ]);
            $count++;
        }
        $pdo->prepare("UPDATE storyboards SET updated_at = NOW() WHERE id = ?")->execute([$sbId]);
        $pdo->commit();

        echo json_encode(['status' => 'success', 'count' => $count, 'storyboard_name' => $sb['name']]);
        exit;
    }

    // 2. GET FRAMES
    if ($action === 'get_frames') {
        $runId = isset($_GET['map_run_id']) ? (int)$_GET['map_run_id'] : 0;
        $entId = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;

        if ($runId) {
            $cond = "f.map_run_id = $runId";
            $orderBy = "f.id ASC";
        } elseif ($entId) {
            $reqEntitySafe = $pdo->quote($reqEntity);
            $cond = "f.entity_id = $entId AND f.entity_type = $reqEntitySafe";
            $orderBy = "f.id DESC";
        } else {
            echo json_encode(['status'=>'success', 'data'=>[]]);
            exit;
        }

        $sql = "SELECT f.id as frame_id, f.filename, f.name, f.prompt,
                CASE WHEN EXISTS (SELECT 1 FROM animatics a WHERE a.img2img_frame_id = f.id) THEN 1 ELSE 0 END as is_imported,
                CASE WHEN EXISTS (SELECT 1 FROM frame_enhancements fe WHERE fe.img2img_frame_id = f.id) THEN 1 ELSE 0 END as is_enhanced
                FROM frames f 
                WHERE $cond 
                ORDER BY $orderBy";

        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status'=>'success', 'data'=>$rows]);
        exit;
    }

    // 2.5 GET SINGLE FRAME (For Add Frames Modal)
    if ($action === 'get_single_frame') {
        $fid = (int)$_GET['frame_id'];
        $stmt = $pdo->prepare("SELECT id, filename FROM frames WHERE id = ?");
        $stmt->execute([$fid]);
        $frame = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($frame) {
            echo json_encode(['status'=>'success', 'data'=>$frame]);
        } else {
            echo json_encode(['status'=>'error', 'message'=>'Frame not found']);
        }
        exit;
    }

    // ── BROWSE FRAMES FOR ADD-FRAMES MODAL ────────────────────────
    if ($action === 'af_search_characters') {
        $q      = trim($_GET['q'] ?? '');
        $limit  = 2000;
        $where  = '1=1';
        $params = [];
        if ($q !== '') { $where = '(name LIKE ? OR id = ?)'; $params = ["%$q%", intval($q)]; }
        $stmt = $pdo->prepare("SELECT id, name FROM characters WHERE $where ORDER BY id ASC LIMIT $limit");
        $stmt->execute($params);
        echo json_encode(['status'=>'success', 'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'af_search_variants') {
        $area  = $_GET['area'] ?? 'poses';
        $q     = trim($_GET['q'] ?? '');
        $tableMap = ['poses'=>'poses','expressions'=>'anivoc_expressions','anima_poses'=>'poses_anima'];
        $table = $tableMap[$area] ?? 'poses';
        $safeTable = '`' . str_replace('`', '', $table) . '`';
        $where = '1=1'; $params = [];
        if ($q !== '') { $where = '(name LIKE ? OR id = ?)'; $params = ["%$q%", intval($q)]; }
        $stmt = $pdo->prepare("SELECT id, name FROM $safeTable WHERE $where ORDER BY id ASC LIMIT 2000");
        $stmt->execute($params);
        echo json_encode(['status'=>'success', 'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'af_get_variant_frames') {
        $area       = $_GET['area']       ?? 'poses';
        $char_id    = (int)($_GET['char_id']    ?? 0);
        $variant_id = (int)($_GET['variant_id'] ?? 0);
        if (!$char_id) { echo json_encode(['status'=>'success','frames'=>[]]); exit; }

        $cfg = [
            'poses'       => ['entity_table'=>'character_poses',      'junction_table'=>'frames_2_character_poses',       'entity_fk'=>'pose_id'],
            'expressions' => ['entity_table'=>'character_expressions', 'junction_table'=>'frames_2_character_expressions', 'entity_fk'=>'expression_id'],
            'anima_poses' => ['entity_table'=>'character_anima_poses', 'junction_table'=>'frames_2_character_anima_poses', 'entity_fk'=>'pose_id'],
        ];
        $c  = $cfg[$area] ?? $cfg['poses'];
        $et = '`' . str_replace('`','', $c['entity_table'])   . '`';
        $jt = '`' . str_replace('`','', $c['junction_table']) . '`';
        $fk = '`' . str_replace('`','', $c['entity_fk'])      . '`';

        $where = "character_id = ?"; $params = [$char_id];
        if ($variant_id > 0) { $where .= " AND $fk = ?"; $params[] = $variant_id; }
        $entityIds = $pdo->prepare("SELECT id FROM $et WHERE $where ORDER BY id ASC");
        $entityIds->execute($params);
        $ids = $entityIds->fetchAll(PDO::FETCH_COLUMN);
        if (empty($ids)) { echo json_encode(['status'=>'success','frames'=>[]]); exit; }

        $inPh = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT f.id as frame_id, f.filename FROM $jt m JOIN frames f ON f.id=m.from_id WHERE m.to_id IN ($inPh) ORDER BY f.id DESC");
        $stmt->execute($ids);
        echo json_encode(['status'=>'success','frames'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // 3. ACTION: IMPORT TO ANIMATICS
    if ($action === 'submit_import') {
        $input = json_decode(file_get_contents('php://input'), true);
        $frameIds = $input['frame_ids'] ?? [];
        $reqEntity = $input['entity'] ?? $reqEntity;
        if (!in_array($reqEntity, $allowedEntities, true)) $reqEntity = $entityType;

        if (empty($frameIds)) throw new Exception("No frames selected.");

        $idsStr = implode(',', array_map('intval', $frameIds));

        $entityTable = "`" . $reqEntity . "`";
        $sql = "SELECT f.id as frame_id, f.filename,
                       COALESCE(s.name, '') as entity_name, COALESCE(s.description, '') as entity_desc,
                       f.name as frame_name, f.prompt as frame_prompt
                FROM frames f
                LEFT JOIN $entityTable s ON (f.entity_id = s.id AND f.entity_type = " . $pdo->quote($reqEntity) . ")
                WHERE f.id IN ($idsStr)";

        $framesData = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        if (empty($framesData)) throw new Exception("Selected frames not found.");

        $stmt = $pdo->prepare("
            INSERT INTO animatics 
            (name, description, img2img, img2img_frame_id, regenerate_videos, created_at, updated_at) 
            VALUES (?, ?, 1, ?, 1, NOW(), NOW())
        ");

        $count = 0;
        $pdo->beginTransaction();
        foreach ($framesData as $row) {
            $name = !empty($row['entity_name']) ? $row['entity_name'] : ($row['frame_name'] ?: $row['filename']);
            $description = !empty($row['entity_desc']) ? $row['entity_desc'] : $row['frame_prompt'];
            $stmt->execute([$name, $description, $row['frame_id']]);
            $count++;
        }
        $pdo->commit();

        echo json_encode(['status'=>'success', 'count'=>$count]);
        exit;
    }

    // 4. ACTION: ENHANCE FRAMES
    if ($action === 'submit_enhancement') {
        $input = json_decode(file_get_contents('php://input'), true);
        $frameIds    = $input['frame_ids'] ?? [];
        $extraFrames = $input['extra_frames'] ?? [];
        $description = trim($input['description'] ?? '');
        $reqEntity = $input['entity'] ?? $reqEntity;
        if (!in_array($reqEntity, $allowedEntities, true)) $reqEntity = $entityType;

        $useEntityPrompt = !empty($input['use_entity_prompt']);

        if (empty($frameIds)) throw new Exception("No frames selected.");
        if (empty($description) && !$useEntityPrompt) throw new Exception("Please enter an enhancement instruction.");
        $depth2img = !empty($input['depth2img']) ? 1 : 0;

        $stmt = $pdo->prepare("INSERT INTO frame_enhancements (entity_type, entity_id, description, img2img_frame_id, regenerate_images, depth2img) VALUES (?, ?, ?, ?, 1, ?)");
        $stmtExtra = $pdo->prepare("INSERT INTO frame_enhancement_frames (frame_enhancement_id, frame_id) VALUES (?, ?)");

        $idsStr = implode(',', array_map('intval', $frameIds));
        $metaData = $pdo->query("SELECT id, entity_id FROM frames WHERE id IN ($idsStr)")->fetchAll(PDO::FETCH_KEY_PAIR);

        $descMap = [];
        if ($useEntityPrompt) {
            $eIds = array_filter(array_unique(array_values($metaData)));
            if (!empty($eIds)) {
                $eIdsStr = implode(',', $eIds);
                $entTableSafe = "`" . str_replace('`', '', $reqEntity) . "`";
                $descMap = $pdo->query("SELECT id, description FROM $entTableSafe WHERE id IN ($eIdsStr)")->fetchAll(PDO::FETCH_KEY_PAIR);
            }
        }

        $count = 0;
        $pdo->beginTransaction();
        foreach ($frameIds as $fid) {
            $entityId = $metaData[$fid] ?? null;
            if ($entityId) {
                $finalDesc = $description;
                if ($useEntityPrompt && isset($descMap[$entityId])) {
                    $finalDesc = (string)$descMap[$entityId];
                }

                $stmt->execute([$reqEntity, $entityId, $finalDesc, $fid, $depth2img]);
                $enhId = $pdo->lastInsertId();
                if (!empty($extraFrames)) {
                    foreach ($extraFrames as $exFid) {
                        $stmtExtra->execute([$enhId, (int)$exFid]);
                    }
                }
                $count++;
            }
        }
        $pdo->commit();

        echo json_encode(['status'=>'success', 'count'=>$count]);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
    exit;
}
exit;

