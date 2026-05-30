<?php
// public/api_kg_lore.php
// Knowledge Graph Lore API — Clone of api_lore.php
// Provides Agent Context, Entity Queries, and Full Category extraction.
// Powered by kg_categories, kg_nodes, and kg_node_items.

header('Content-Type: application/json');
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$catId = (int)($_GET['category_id'] ?? $_GET['doc_id'] ?? 0); // Top-level KG category ID (0 = all)
$mode  = $_GET['mode'] ?? 'full'; // full, entity, category, context, story
$query = $_GET['query'] ?? null;

try {
    $response =[];

    // Helper: fetch family of category IDs (category + all descendants)
    $getCatFamily = function($pdo, $topCatId) {
        if ($topCatId <= 0) return[]; // 0 means return all globally
        $allCats = $pdo->query("SELECT id, parent_id FROM kg_categories")->fetchAll(PDO::FETCH_ASSOC);
        $childrenMap = [];
        foreach ($allCats as $c) {
            $childrenMap[$c['parent_id'] ?? 0][] = $c['id'];
        }
        $family =[(int)$topCatId];
        $queue = [(int)$topCatId];
        while (!empty($queue)) {
            $curr = array_shift($queue);
            if (isset($childrenMap[$curr])) {
                foreach ($childrenMap[$curr] as $childId) {
                    $family[] = $childId;
                    $queue[] = $childId;
                }
            }
        }
        return $family;
    };

    switch ($mode) {

        // ----------------------------------------------------------------------------------
        // MODE: ENTITY (Get exact node by name)
        // ----------------------------------------------------------------------------------
        case 'entity':
            if (!$query) throw new Exception("Missing query param for entity mode");
            
            $q = '%' . $query . '%';
            $stmt = $pdo->prepare("
                SELECT n.*, c.name AS category_name
                FROM kg_nodes n
                LEFT JOIN kg_categories c ON n.category_id = c.id
                WHERE n.status = 'active' AND (n.name LIKE ? OR n.keywords LIKE ?)
                ORDER BY CASE WHEN n.name = ? THEN 1 ELSE 2 END, n.name ASC
                LIMIT 1
            ");
            $stmt->execute([$q, $q, $query]);
            $node = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($node) {
                $istmt = $pdo->prepare("SELECT * FROM kg_node_items WHERE node_id = ? ORDER BY sort_order ASC");
                $istmt->execute([$node['id']]);
                $node['relationships'] = $istmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            $response = $node;
            break;

        // ----------------------------------------------------------------------------------
        // MODE: CONTEXT (Build optimized agent context window injection)
        // ----------------------------------------------------------------------------------
        case 'context':
            if (!$query) throw new Exception("Missing query param for context mode");
            
            $q = '%' . $query . '%';
            $stmt = $pdo->prepare("
                SELECT n.*, c.name AS category_name
                FROM kg_nodes n
                LEFT JOIN kg_categories c ON n.category_id = c.id
                WHERE n.status = 'active' AND (n.name LIKE ? OR n.keywords LIKE ?)
                ORDER BY CASE WHEN n.name = ? THEN 1 ELSE 2 END, n.name ASC
                LIMIT 1
            ");
            $stmt->execute([$q, $q, $query]);
            $node = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$node) {
                $response = null;
                break;
            }

            $istmt = $pdo->prepare("SELECT * FROM kg_node_items WHERE node_id = ? ORDER BY sort_order ASC");
            $istmt->execute([$node['id']]);
            $items = $istmt->fetchAll(PDO::FETCH_ASSOC);

            $network =[];
            foreach ($items as $item) {
                $target = $item['item_label'] ?? ('ID:' . $item['item_id']);
                $rel = $item['relationship'] ?? '';
                $type = $item['item_type'] ?? '';
                $desc = $item['note'] ?? '';
                $str = "$target ($type)";
                if ($rel) $str .= " - $rel";
                if ($desc) $str .= ": $desc";
                $network[] = $str;
            }

            $response =[
                'identity' => [
                    'name' => $node['name'],
                    'roles' => [$node['node_type']],
                    'core_attributes' => [
                        'type' => $node['node_type'],
                        'category' => $node['category_name'],
                        'description' => $node['description'] ?? '',
                        'keywords' => $node['keywords'] ?? ''
                    ]
                ],
                'network' => $network,
                'history' => [], // History injected through markdown content directly in KG
                'content' => $node['content'] ?? ''
            ];
            break;

        // ----------------------------------------------------------------------------------
        // MODE: CATEGORY (Query a specific bucket/folder of entities e.g., "Characters")
        // ----------------------------------------------------------------------------------
        case 'category':
            if (!$query) throw new Exception("Missing query param (category name)");
            $roleFilter = $_GET['role'] ?? null;
            
            $stmt = $pdo->prepare("SELECT id FROM kg_categories WHERE name LIKE ? LIMIT 1");
            $stmt->execute(['%' . $query . '%']);
            $cat = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cat) {
                $response =[];
                break;
            }

            $family = $getCatFamily($pdo, $cat['id']);
            $ph = implode(',', array_fill(0, count($family), '?'));
            
            $sql = "
                SELECT n.id, n.name, n.node_type, n.description, n.keywords, c.name as category_name
                FROM kg_nodes n
                LEFT JOIN kg_categories c ON n.category_id = c.id
                WHERE n.category_id IN ($ph) AND n.status = 'active'
            ";
            $params = $family;

            // In KG, 'role' maps beautifully to 'node_type'
            if ($roleFilter) {
                $sql .= " AND n.node_type = ?";
                $params[] = $roleFilter;
            }
            $sql .= " ORDER BY n.name ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format to match the old list schema output
            foreach ($nodes as &$n) {
                $n['aliases'] = !empty($n['keywords']) ? array_map('trim', explode(',', $n['keywords'])) : [];
                $n['roles'] = [$n['node_type']];
                $n['attributes'] = [
                    'type' => $n['node_type'],
                    'category' => $n['category_name']
                ];
            }

            $response = $nodes;
            break;

        // ----------------------------------------------------------------------------------
        // MODE: STORY (Extract Narrative Engines & Episodes)
        // ----------------------------------------------------------------------------------
        case 'story':
            $where = "WHERE n.status = 'active' AND n.node_type IN ('arc', 'episode')";
            $params =[];
            
            if ($catId > 0) {
                $family = $getCatFamily($pdo, $catId);
                $ph = implode(',', array_fill(0, count($family), '?'));
                $where .= " AND n.category_id IN ($ph)";
                $params = $family;
            }
            
            $sql = "SELECT n.id, n.name, n.node_type, n.description, n.content FROM kg_nodes n $where ORDER BY n.sort_order ASC, n.name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $episodes = [];
            $arcs =[];
            foreach ($nodes as $n) {
                $item = [
                    'title' => $n['name'],
                    'description' => $n['description'],
                    'content' => $n['content']
                ];
                if ($n['node_type'] === 'episode') $episodes[] = $item;
                if ($n['node_type'] === 'arc') $arcs[] = $item;
            }
            
            $response =[
                'episodes' => $episodes,
                'narrative_engine' => $arcs,
                'visual_keywords' => [],
                'scene_hooks' =>[]
            ];
            break;

        // ----------------------------------------------------------------------------------
        // MODE: FULL (Extract entire KG category graph as Lore Payload)
        // ----------------------------------------------------------------------------------
        case 'full':
        default:
            $where = "WHERE n.status = 'active'";
            $params =[];
            
            if ($catId > 0) {
                $family = $getCatFamily($pdo, $catId);
                $ph = implode(',', array_fill(0, count($family), '?'));
                $where .= " AND n.category_id IN ($ph)";
                $params = $family;
            }

            $sql = "
                SELECT n.*, c.name AS category_name
                FROM kg_nodes n
                LEFT JOIN kg_categories c ON n.category_id = c.id
                $where
                ORDER BY c.sort_order ASC, n.sort_order ASC, n.name ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch relations
            $nodeIds = array_column($nodes, 'id');
            $itemsByNode =[];
            if (!empty($nodeIds)) {
                $iph = implode(',', array_fill(0, count($nodeIds), '?'));
                $istmt = $pdo->prepare("SELECT * FROM kg_node_items WHERE node_id IN ($iph) ORDER BY sort_order ASC");
                $istmt->execute($nodeIds);
                foreach ($istmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
                    $itemsByNode[$item['node_id']][] = $item;
                }
            }

            $world = [];
            $story = ['episodes' => [], 'narrative_engine' =>[]];
            
            foreach ($nodes as $node) {
                $subCatName = $node['category_name'] ?? 'General';
                $type = $node['node_type'];
                
                $relationships = [];
                foreach ($itemsByNode[$node['id']] ??[] as $item) {
                    $relationships[] = [
                        'target' => $item['item_label'] ?? ('ID:' . $item['item_id']),
                        'type' => $item['item_type'],
                        'nature' => $item['relationship'] ?? '',
                        'desc' => $item['note'] ?? ''
                    ];
                }

                $keywords = !empty($node['keywords']) ? array_map('trim', explode(',', $node['keywords'])) :[];

                $entity = [
                    'id' => $node['id'],
                    'name' => $node['name'],
                    'node_type' => $type,
                    'description' => $node['description'] ?? '',
                    'content' => $node['content'] ?? '',
                    'keywords' => $keywords,
                    'aliases' => $keywords,
                    'roles' => [$type],
                    'relationships' => $relationships,
                    'attributes' =>[
                        'type' => $type,
                        'category' => $subCatName
                    ]
                ];

                if (in_array($type,['episode', 'arc'])) {
                    if ($type === 'episode') $story['episodes'][] = $entity;
                    else $story['narrative_engine'][] = $entity;
                } else {
                    $world[$subCatName][] = $entity;
                }
            }

            // Provide list of active categories used for reference
            $cstmt = $pdo->query("SELECT DISTINCT c.name FROM kg_nodes n JOIN kg_categories c ON n.category_id = c.id WHERE n.status = 'active'");
            $allUsedCats = $cstmt->fetchAll(PDO::FETCH_COLUMN);

            $response =[
                'world' => $world,
                'story' => $story,
                'categories' => $allUsedCats,
            ];
            break;
    }

    // JSON_UNESCAPED_UNICODE helps keep AI-friendly string representations cleanly formatted
    echo json_encode(['status' => 'success', 'data' => $response], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}