<?php
// public/locahub_api.php
// LocaHub API Handler
// ----------------------------------------------------
header('Content-Type: application/json');

if (!isset($pdo)) {
    require_once __DIR__ . '/bootstrap.php';
}

$action = $_REQUEST['api_action'] ?? '';

try {
    
    // Helper to strip Markdown & HTML into clean plain text for AI prompts
    function markdownToPlainText($text) {
        if (empty($text)) return '';
        
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Remove Images: ![alt](url) -> alt
        $text = preg_replace('/!\[([^\]]*)\]\([^\)]*\)/', '$1', $text);
        // Remove Links: [text](url) -> text
        $text = preg_replace('/\[([^\]]*)\]\([^\)]*\)/', '$1', $text);
        // Remove Code Fences completely but keep the text inside
        $text = preg_replace('/```[a-zA-Z]*\n(.*?)\n```/s', '$1', $text);
        // Remove Inline Code backticks
        $text = preg_replace('/`([^`]*)`/', '$1', $text);
        // Remove Headers
        $text = preg_replace('/^#+\s+/m', '', $text);
        // Remove Blockquotes
        $text = preg_replace('/^\s*>\s+/m', '', $text);
        // Remove Unordered list markers
        $text = preg_replace('/^\s*[-*+]\s+/m', '', $text);
        // Remove Ordered list markers
        $text = preg_replace('/^\s*\d+\.\s+/m', '', $text);
        // Remove Bold/Italic markers
        $text = preg_replace('/([*_]{1,3})(.*?)\1/', '$2', $text);
        // Remove Strikethrough
        $text = preg_replace('/~~(.*?)~~/', '$1', $text);
        
        // Normalize excessive newlines to a single paragraph break
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        
        return trim($text);
    }

    // ═══════════════════════════════════════════════════════
    // GET ITEMS LIST
    // ═══════════════════════════════════════════════════════
    if ($action === 'get_items') {
        $source = $_GET['source'] ?? 'locations';
        $limit  = (int)($_GET['limit'] ?? 6);
        $offset = (int)($_GET['offset'] ?? 0);
        $search = trim($_GET['search'] ?? '');
        
        $data = [];
        $total = 0;
        
        if ($source === 'locations') {
            $where = "1=1"; $params = [];
            if ($search) { $where .= " AND name LIKE ?"; $params[] = "%$search%"; }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM locations WHERE $where");
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT id, name, type as type_hint, description, COALESCE(description, '') as meta FROM locations WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } 
        elseif ($source === 'fuzz') {
            $where = "status = 'promoted' AND concept_type = 'location'"; $params = [];
            if ($search) { $where .= " AND label LIKE ?"; $params[] = "%$search%"; }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM fuzz_candidates WHERE $where");
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT id, label as name, 'location' as type_hint, notes as description, COALESCE(notes, '') as meta FROM fuzz_candidates WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        elseif ($source === 'kg') {
            $where = "node_type = 'location'"; $params = [];
            if ($search) { $where .= " AND name LIKE ?"; $params[] = "%$search%"; }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kg_nodes WHERE $where");
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            // Prioritize AI-generated sketch description via sketch_lore_history, fallback to node content
            $sql = "
                SELECT id, name, 'location' as type_hint, 
                COALESCE(
                    (SELECT s.description 
                     FROM sketch_lore_history slh 
                     JOIN sketches s ON slh.sketch_id = s.id 
                     WHERE slh.entity_name = kg_nodes.name 
                     ORDER BY slh.id DESC LIMIT 1), 
                    content
                ) as raw_content, 
                COALESCE(description, '') as meta 
                FROM kg_nodes 
                WHERE $where 
                ORDER BY id DESC LIMIT $limit OFFSET $offset
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($data as &$row) {
                $row['description'] = markdownToPlainText($row['raw_content']);
                unset($row['raw_content']);
            }
        }
        elseif ($source === 'ag') {
            $where = "node_type = 'location'"; $params = [];
            if ($search) { $where .= " AND name LIKE ?"; $params[] = "%$search%"; }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ag_nodes WHERE $where");
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            // Prioritize AI-generated sketch description via sketch_lore_history, fallback to node content
            $sql = "
                SELECT id, name, 'location' as type_hint, 
                COALESCE(
                    (SELECT s.description 
                     FROM sketch_lore_history slh 
                     JOIN sketches s ON slh.sketch_id = s.id 
                     WHERE slh.entity_name = ag_nodes.name 
                     ORDER BY slh.id DESC LIMIT 1), 
                    content
                ) as raw_content, 
                COALESCE(description, '') as meta 
                FROM ag_nodes 
                WHERE $where 
                ORDER BY id DESC LIMIT $limit OFFSET $offset
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($data as &$row) {
                $row['description'] = markdownToPlainText($row['raw_content']);
                unset($row['raw_content']);
            }
        }
        elseif ($source === 'sketches') {
            $where = "1=1"; $params = [];
            if ($search) { $where .= " AND label LIKE ?"; $params[] = "%$search%"; }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sketch_location_ranges WHERE $where");
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT id, label as name, 'location' as type_hint, notes as description, CONCAT('Sketches ', sketch_id_from, ' to ', sketch_id_to) as meta FROM sketch_location_ranges WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['status' => 'success', 'data' => $data, 'total' => $total]);
        exit;
    }
    
    // ═══════════════════════════════════════════════════════
    // GET FRAMES FOR SELECTED ITEM
    // ═══════════════════════════════════════════════════════
    if ($action === 'get_frames') {
        $source = $_GET['source'] ?? 'locations';
        $itemId = (int)($_GET['item_id'] ?? 0);
        $frames = [];
        
        if ($source === 'locations') {
            $stmt = $pdo->prepare("SELECT id as frame_id, filename FROM frames WHERE entity_type = 'locations' AND entity_id = ? ORDER BY id DESC");
            $stmt->execute([$itemId]);
            $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        elseif ($source === 'fuzz') {
            $stmt = $pdo->prepare("
                SELECT f.id as frame_id, f.filename 
                FROM frames f 
                JOIN fuzz_mentions m ON f.entity_type = m.source_table AND f.entity_id = m.source_row_id 
                WHERE m.candidate_id = ? 
                ORDER BY f.id DESC
            ");
            $stmt->execute([$itemId]);
            $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        elseif ($source === 'kg') {
            $sql = "
                SELECT f.id as frame_id, f.filename 
                FROM frames f 
                JOIN sketch_lore_history slh ON f.entity_id = slh.sketch_id AND f.entity_type = 'sketches'
                JOIN kg_nodes kn ON slh.entity_name = kn.name
                WHERE kn.id = ?
                UNION
                SELECT f.id as frame_id, f.filename
                FROM frames f
                JOIN kg_node_items kni ON f.entity_id = kni.item_id AND f.entity_type = 'sketches' AND kni.item_type = 'sketch'
                WHERE kni.node_id = ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$itemId, $itemId]);
            $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Re-sort descending programmatically since UNION disrupts pure ordering
            usort($frames, function($a, $b) {
                return $b['frame_id'] <=> $a['frame_id'];
            });
        }
        elseif ($source === 'ag') {
            $sql = "
                SELECT f.id as frame_id, f.filename 
                FROM frames f 
                JOIN sketch_lore_history slh ON f.entity_id = slh.sketch_id AND f.entity_type = 'sketches'
                JOIN ag_nodes an ON slh.entity_name = an.name
                WHERE an.id = ?
                ORDER BY f.id DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$itemId]);
            $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        elseif ($source === 'sketches') {
            $stmtRange = $pdo->prepare("SELECT sketch_id_from, sketch_id_to FROM sketch_location_ranges WHERE id = ?");
            $stmtRange->execute([$itemId]);
            $range = $stmtRange->fetch(PDO::FETCH_ASSOC);
            if ($range) {
                $stmt = $pdo->prepare("SELECT id as frame_id, filename FROM frames WHERE entity_type = 'sketches' AND entity_id BETWEEN ? AND ? ORDER BY id DESC");
                $stmt->execute([$range['sketch_id_from'], $range['sketch_id_to']]);
                $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
        echo json_encode(['status' => 'success', 'data' => $frames]);
        exit;
    }
    
    
    
    
    
    
    
    
    
    
   // ═══════════════════════════════════════════════════════
    // HELPERS: location import upsert for KG / AG batch flows
    // ═══════════════════════════════════════════════════════
    $persistImportedLocation = function(array $item, string $source) use ($pdo): array {
        $name = trim((string)($item['name'] ?? ''));
        if ($name === '') {
            return ['action' => 'skipped', 'message' => 'Skipped item with empty name'];
        }

        $originSource = trim((string)($item['source'] ?? $source));
        $originId     = (int)($item['source_id'] ?? 0);
        $description  = trim((string)($item['description'] ?? ''));
        $type         = trim((string)($item['type'] ?? ($item['type_hint'] ?? '')));

        if ($originId <= 0) {
            return ['action' => 'skipped', 'message' => "Skipped {$originSource} item '{$name}' because source_id is missing."];
        }

        // Exact origin already exists -> do nothing.
        $stmt = $pdo->prepare("
            SELECT id
            FROM locations
            WHERE origin_source = ? AND origin_id = ?
            LIMIT 1
        ");
        $stmt->execute([$originSource, $originId]);
        $existingByOrigin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingByOrigin) {
            return [
                'action'  => 'skipped',
                'message' => "Skipped {$originSource} #{$originId}: already imported as location #{$existingByOrigin['id']}."
            ];
        }

        // Same name, but a row with no origin yet -> claim that row instead of failing.
        $stmt = $pdo->prepare("
            SELECT id, origin_id, origin_source
            FROM locations
            WHERE name = ?
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute([$name]);
        $existingByName = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingByName && empty($existingByName['origin_id'])) {
            $upd = $pdo->prepare("
                UPDATE locations
                SET description = ?, type = ?, origin_source = ?, origin_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([
                $description,
                $type,
                $originSource,
                $originId,
                (int)$existingByName['id']
            ]);

            return [
                'action'  => 'updated',
                'message' => "Updated existing location #{$existingByName['id']} from {$originSource} #{$originId}: {$name}"
            ];
        }

        // Try direct insert first.
        $ins = $pdo->prepare("
            INSERT INTO locations
                (name, description, type, origin_source, origin_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");

        try {
            $ins->execute([$name, $description, $type, $originSource, $originId]);

            return [
                'action'  => 'inserted',
                'message' => "Imported {$originSource} #{$originId}: {$name}"
            ];
        } catch (PDOException $ex) {
            // Duplicate name collision: retry with a deterministic alias.
            if ((int)($ex->errorInfo[1] ?? 0) !== 1062) {
                throw $ex;
            }

            $aliasName = "{$name} ({$originSource} #{$originId})";
            $ins->execute([$aliasName, $description, $type, $originSource, $originId]);

            return [
                'action'  => 'inserted',
                'message' => "Imported {$originSource} #{$originId}: {$aliasName}"
            ];
        }
    };

    // ═══════════════════════════════════════════════════════
    // STANDARD BATCH MIGRATE
    // ═══════════════════════════════════════════════════════
    if ($action === 'migrate_to_locations') {
        $input = json_decode(file_get_contents('php://input'), true);
        $items = $input['items'] ?? [];

        if (empty($items)) {
            throw new Exception("No items to migrate");
        }

        $pdo->beginTransaction();

        $count = 0;
        foreach ($items as $item) {
            $result = $persistImportedLocation($item, (string)($item['source'] ?? ''));

            if (($result['action'] ?? 'skipped') !== 'skipped') {
                $count++;
            }
        }

        $pdo->commit();

        echo json_encode(['status' => 'success', 'count' => $count]);
        exit;
    }

    // ═══════════════════════════════════════════════════════
    // AUTO-BATCH: GET INFO
    // ═══════════════════════════════════════════════════════
    if ($action === 'get_batch_info') {
        $source = $_GET['source'] ?? '';
        if (!in_array($source, ['kg', 'ag'])) throw new Exception("Invalid auto-batch source");

        $table = $source . '_nodes';

        // Count all nodes of type 'location' that haven't been migrated yet.
        // Use NOT EXISTS so NULL origin_id rows in locations do not break the result set.
        $sql = "
            SELECT COUNT(*)
            FROM $table n
            WHERE n.node_type = 'location'
              AND NOT EXISTS (
                  SELECT 1
                  FROM locations l
                  WHERE l.origin_source = ?
                    AND l.origin_id = n.id
              )
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$source]);
        $count = $stmt->fetchColumn();

        echo json_encode(['status' => 'success', 'unmigrated_count' => (int)$count]);
        exit;
    }

    // ═══════════════════════════════════════════════════════
    // AUTO-BATCH: PROCESS CHUNK
    // ═══════════════════════════════════════════════════════
    if ($action === 'process_auto_batch_chunk') {
        $input = json_decode(file_get_contents('php://input'), true);
        $source = $input['source'] ?? '';
        $chunkSize = (int)($input['chunk_size'] ?? 20);

        if (!in_array($source, ['kg', 'ag'])) throw new Exception("Invalid auto-batch source");

        $table = $source . '_nodes';

        // Fetch up to $chunkSize unmigrated nodes.
        // Use NOT EXISTS so NULL origin_id rows in locations do not interfere.
        $sql = "
            SELECT
                n.id,
                n.name,
                'location' as type_hint,
                COALESCE(
                    (
                        SELECT s.description
                        FROM sketch_lore_history slh
                        JOIN sketches s ON slh.sketch_id = s.id
                        WHERE slh.entity_name = n.name
                        ORDER BY slh.id DESC
                        LIMIT 1
                    ),
                    n.content
                ) as raw_content
            FROM $table n
            WHERE n.node_type = 'location'
              AND NOT EXISTS (
                  SELECT 1
                  FROM locations l
                  WHERE l.origin_source = ?
                    AND l.origin_id = n.id
              )
            ORDER BY n.id ASC
            LIMIT $chunkSize
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$source]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            echo json_encode(['status' => 'success', 'processed' => 0, 'logs' => []]);
            exit;
        }

        $pdo->beginTransaction();

        $logs = [];
        $processed = 0;

        foreach ($items as $item) {
            $desc = markdownToPlainText($item['raw_content']);
            $payload = [
                'source'      => $source,
                'source_id'   => (int)$item['id'],
                'name'        => (string)$item['name'],
                'description' => $desc,
                'type'        => (string)($item['type_hint'] ?? 'location'),
            ];

            $result = $persistImportedLocation($payload, $source);

            if (($result['action'] ?? 'skipped') !== 'skipped') {
                $processed++;
            }

            if (!empty($result['message'])) {
                $logs[] = $result['message'];
            }
        }

        $pdo->commit();

        echo json_encode(['status' => 'success', 'processed' => $processed, 'logs' => $logs]);
        exit;
    }
    
    
    
    
    
    
    
    
    
    /*
    
    // ═══════════════════════════════════════════════════════
    // STANDARD BATCH MIGRATE
    // ═══════════════════════════════════════════════════════
    if ($action === 'migrate_to_locations') {
        $input = json_decode(file_get_contents('php://input'), true);
        $items = $input['items'] ?? [];
        
        if (empty($items)) {
            throw new Exception("No items to migrate");
        }
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO locations 
            (name, description, type, origin_source, origin_id, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $count = 0;
        foreach ($items as $item) {
            $name = trim($item['name'] ?? '');
            if (!$name) continue;
            
            $stmt->execute([
                $name, 
                trim($item['description'] ?? ''), 
                trim($item['type'] ?? ''), 
                trim($item['source'] ?? ''), 
                (int)($item['source_id'] ?? 0)
            ]);
            $count++;
        }
        
        $pdo->commit();
        
        echo json_encode(['status' => 'success', 'count' => $count]);
        exit;
    }

    // ═══════════════════════════════════════════════════════
    // AUTO-BATCH: GET INFO
    // ═══════════════════════════════════════════════════════
    if ($action === 'get_batch_info') {
        $source = $_GET['source'] ?? '';
        if (!in_array($source, ['kg', 'ag'])) throw new Exception("Invalid auto-batch source");
        
        $table = $source . '_nodes';
        
        // Count all nodes of type 'location' that haven't been migrated yet
        $sql = "
            SELECT COUNT(*) 
            FROM $table 
            WHERE node_type = 'location'
            AND id NOT IN (SELECT origin_id FROM locations WHERE origin_source = ?)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$source]);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['status' => 'success', 'unmigrated_count' => (int)$count]);
        exit;
    }

    // ═══════════════════════════════════════════════════════
    // AUTO-BATCH: PROCESS CHUNK
    // ═══════════════════════════════════════════════════════
    if ($action === 'process_auto_batch_chunk') {
        $input = json_decode(file_get_contents('php://input'), true);
        $source = $input['source'] ?? '';
        $chunkSize = (int)($input['chunk_size'] ?? 20);
        
        if (!in_array($source, ['kg', 'ag'])) throw new Exception("Invalid auto-batch source");

        $table = $source . '_nodes';
        
        // Fetch up to $chunkSize unmigrated nodes
        $sql = "
            SELECT id, name, 'location' as type_hint, 
            COALESCE(
                (SELECT s.description 
                 FROM sketch_lore_history slh 
                 JOIN sketches s ON slh.sketch_id = s.id 
                 WHERE slh.entity_name = $table.name 
                 ORDER BY slh.id DESC LIMIT 1), 
                content
            ) as raw_content
            FROM $table 
            WHERE node_type = 'location'
            AND id NOT IN (SELECT origin_id FROM locations WHERE origin_source = ?)
            ORDER BY id ASC LIMIT $chunkSize
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$source]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            echo json_encode(['status' => 'success', 'processed' => 0, 'logs' => []]);
            exit;
        }

        $pdo->beginTransaction();
        $insertStmt = $pdo->prepare("
            INSERT INTO locations 
            (name, description, type, origin_source, origin_id, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $logs = [];
        $processed = 0;
        
        foreach ($items as $item) {
            $desc = markdownToPlainText($item['raw_content']);
            $insertStmt->execute([
                $item['name'],
                $desc,
                $item['type_hint'],
                $source,
                $item['id']
            ]);
            $logs[] = "Migrated $source #" . $item['id'] . ": " . $item['name'];
            $processed++;
        }
        
        $pdo->commit();

        echo json_encode(['status' => 'success', 'processed' => $processed, 'logs' => $logs]);
        exit;
    }
    
    */
    
    
    
    
    
    
    
    throw new Exception("Unknown API action");
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}