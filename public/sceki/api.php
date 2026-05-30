<?php
// public/sceki/api.php
// Scene Kitchen v2 API — PDO-only, no Doctrine.
// All actions return JSON.
// ─────────────────────────────────────────────────────
declare(strict_types=1);

// Prevent raw PHP errors from breaking the JSON response
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../env_locals.php';
require_once __DIR__ . '/KitchenChef.php';

// ── Allowed generic entity tables (whitelist) ──────────────────────────────
// MUST be defined at the root level (outside try/catch) to prevent fatal parse errors.
const ENTITY_TABLES = [
    'characters', 'locations', 'vehicles', 'artifacts',
    'anivoc_expressions', 'anivoc_lighting', 'anivoc_color_coding',
    'anivoc_motion_impact', 'anivoc_transitions', 'anivoc_backgrounds',
    'anivoc_scale_perspective', 'anivoc_symbolic_objects',
    'anivoc_text_graphics', 'anivoc_panel_frame',
];

try {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        exit(json_encode(['ok' => false, 'error' => 'Not authenticated']));
    }

    // ── Input ──────────────────────────────────────────────────────────────────
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw ?: '{}', true) ?? [];
    foreach ($_POST as $k => $v) { 
        if (!isset($input[$k])) $input[$k] = $v; 
    }

    $action = trim((string)($input['action'] ?? ''));

    // Entity icons (fallback if entity_icons.php is missing)
    $entityIcons = [];
    $iconsFile = __DIR__ . '/../entity_icons.php';
    if (file_exists($iconsFile)) {
        include $iconsFile;
    }
    
    $entityIcons = array_merge([
        'characters'          => '🦸',
        'locations'           => '🗺️',
        'vehicles'            => '🛸',
        'artifacts'           => '🏺',
        'anivoc_expressions'  => '😊',
        'anivoc_lighting'     => '💡',
        'anivoc_color_coding' => '🎨',
        'anivoc_motion_impact'=> '💥',
        'anivoc_transitions'  => '🎞️',
        'anivoc_backgrounds'  => '🏙️',
        'anivoc_scale_perspective' => '📐',
        'anivoc_symbolic_objects'  => '🗿',
        'anivoc_text_graphics'     => '🗯️',
        'anivoc_panel_frame'       => '🖼️',
        'sketch_template'    => '🎬',
        'interaction'        => '🤝',
        'style_profile'      => '🎨',
    ], is_array($entityIcons ?? null) ? $entityIcons : []);

    // Chef helper (encapsulates save logic safely inside the try-catch block)
    $chef = new KitchenChefV2($pdo);

    // ══════════════════════════════════════════════════════
    // ROUTES
    // ══════════════════════════════════════════════════════

    // ── FETCH INGREDIENTS LIST ──────────────────────────────────────────────
    if ($action === 'fetch_ingredients') {
        $type    = inputStr($input, 'type');
        $filters = $input['filters'] ?? [];
        $data    = [];

        switch ($type) {
            case 'templates':
                $sql    = "SELECT id, name, example_prompt, core_idea FROM sketch_templates WHERE active=1 AND entity_type='sketches' AND is_ingredient=1";
                $params = [];
                if (!empty($filters['category_id'])) {
                    $sql .= " AND category_id = :cat";
                    $params[':cat'] = (int)$filters['category_id'];
                }
                $sql .= " ORDER BY name ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $label   = $r['name'];
                    $preview = $r['example_prompt'] ?? '';
                    $data[]  = [
                        'id'   => (int)$r['id'],
                        'type' => 'sketch_template',
                        'label'=> $label,
                        'icon' => '🎬',
                        'data' => ['core_idea' => $r['core_idea'], 'example_prompt' => $preview],
                    ];
                }
                break;

            case 'interactions':
                $sql    = "SELECT id, name, description, example_prompt, interaction_group FROM interactions WHERE active=1 AND is_ingredient=1";
                $params = [];
                if (!empty($filters['group'])) {
                    $sql .= " AND interaction_group = :grp";
                    $params[':grp'] = $filters['group'];
                }
                $sql .= " ORDER BY interaction_group, name ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $data[] = [
                        'id'   => (int)$r['id'],
                        'type' => 'interaction',
                        'label'=> $r['name'],
                        'icon' => '🤝',
                        'data' => ['description' => $r['description']],
                    ];
                }
                break;

            case 'style_profiles':
                $stmt = $pdo->query("SELECT id, name, convert_result FROM style_profiles WHERE convert_result IS NOT NULL AND convert_result != '' AND is_ingredient=1 ORDER BY name ASC");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $data[] = [
                        'id'   => (int)$r['id'],
                        'type' => 'style_profile',
                        'label'=> $r['name'],
                        'icon' => '🎨',
                        'data' => [],
                    ];
                }
                break;

            default:
                // Generic entity tables
                if (!in_array($type, ENTITY_TABLES, true)) fail("Unknown ingredient type: $type");
                $icon   = $entityIcons[$type] ?? '📦';
                $stmt   = $pdo->query("SELECT id, name, description FROM `$type` WHERE is_ingredient=1 ORDER BY id DESC LIMIT 200");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $data[] = [
                        'id'   => (int)$r['id'],
                        'type' => $type,
                        'label'=> $r['name'],
                        'icon' => $icon,
                        'data' => [],
                    ];
                }
        }

        ok(['data' => $data]);
    }

    // ── KG NODE SEARCH ─────────────────────────────────────────────────────
    if ($action === 'kg_search') {
        $q      = '%' . inputStr($input, 'q') . '%';
        $catId  = inputInt($input, 'category_id');

        $sql    = "SELECT id, name, node_type FROM kg_nodes WHERE status='active' AND (name LIKE :q OR keywords LIKE :q)";
        $params = [':q' => $q];
        if ($catId > 0) {
            $sql .= " AND category_id = :cat";
            $params[':cat'] = $catId;
        }
        $sql .= " LIMIT 30";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        ok(['results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // ── KG SUBPOT PREVIEW ─────────────────────────────────────────────────
    if ($action === 'kg_subpot_preview') {
        $nodeIds      = array_map('intval', (array)($input['node_ids'] ?? []));
        $includeEdges = !empty($input['include_edges']);

        if (empty($nodeIds)) ok(['preview' => '']);

        $ph    = implode(',', array_fill(0, count($nodeIds), '?'));
        $stmt  = $pdo->prepare("SELECT id, name, node_type, description FROM kg_nodes WHERE id IN ($ph) AND status='active'");
        $stmt->execute($nodeIds);
        $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $lines = ['[Knowledge Graph Subplot]'];
        foreach ($nodes as $n) {
            $desc   = trim(strip_tags($n['description'] ?? ''));
            $line   = "• [{$n['node_type']}] {$n['name']}";
            if ($desc) $line .= ": {$desc}";
            $lines[] = $line;
        }

        if ($includeEdges && count($nodeIds) > 1) {
            $ph2  = implode(',', array_fill(0, count($nodeIds), '?'));
            $stmt = $pdo->prepare("
                SELECT kni.relationship, kni.item_label, kn_src.name AS src_name
                FROM kg_node_items kni
                JOIN kg_nodes kn_src ON kn_src.id = kni.node_id
                WHERE kni.item_type = 'kg_node'
                  AND kni.node_id IN ($ph2)
                  AND kni.item_id IN ($ph2)
                  AND kni.item_id IS NOT NULL
                LIMIT 20
            ");
            $stmt->execute(array_merge($nodeIds, $nodeIds));
            $edges = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($edges) {
                $lines[] = '';
                $lines[] = '[Relationships]';
                foreach ($edges as $e) {
                    $rel   = $e['relationship'] ?: 'relates to';
                    $lines[] = "→ {$e['src_name']} {$rel} {$e['item_label']}";
                }
            }
        }

        ok(['preview' => implode("\n", $lines)]);
    }

    // ── FETCH MINI GRAPH (SUBGRAPH / NEIGHBOURHOOD) ─────────────────────────
    if ($action === 'fetch_mini_graph') {
        $nodeId = inputInt($input, 'node_id');
        $hops   = max(1, min(4, inputInt($input, 'hops', 1)));

        if ($nodeId <= 0) fail('Invalid node_id');

        $visited = [$nodeId => true];
        $frontier = [$nodeId];

        for ($h = 0; $h < $hops; $h++) {
            if (empty($frontier)) break;
            $ph = implode(',', array_fill(0, count($frontier), '?'));

            // Outgoing edges from frontier
            $stmt = $pdo->prepare("SELECT DISTINCT item_id FROM kg_node_items WHERE item_type = 'kg_node' AND item_id IS NOT NULL AND node_id IN ($ph)");
            $stmt->execute($frontier);
            $out = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Incoming edges to frontier
            $stmt = $pdo->prepare("SELECT DISTINCT node_id FROM kg_node_items WHERE item_type = 'kg_node' AND item_id IN ($ph)");
            $stmt->execute($frontier);
            $in = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $newFrontier = [];
            foreach (array_merge($out, $in) as $nid) {
                $nid = (int)$nid;
                if ($nid && !isset($visited[$nid])) {
                    $visited[$nid] = true;
                    $newFrontier[] = $nid;
                }
            }
            $frontier = $newFrontier;
        }

        $ids = array_keys($visited);
        $nodes = [];
        $edges = [];

        if (!empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            
            // Nodes
            $stmt = $pdo->prepare("SELECT id, name, node_type FROM kg_nodes WHERE id IN ($ph) AND status = 'active'");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
                $nodes[] = [
                    'id' => (int)$n['id'],
                    'name' => $n['name'],
                    'node_type' => $n['node_type'],
                ];
            }

            // Edges where BOTH source and target are inside the gathered subgraph
            $stmt = $pdo->prepare("
                SELECT id, node_id AS source, item_id AS target, relationship, item_label 
                FROM kg_node_items 
                WHERE item_type = 'kg_node' 
                  AND item_id IS NOT NULL 
                  AND node_id IN ($ph) 
                  AND item_id IN ($ph)
            ");
            $stmt->execute(array_merge($ids, $ids));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
                $edges[] = [
                    'id' => (int)$e['id'],
                    'source' => (int)$e['source'],
                    'target' => (int)$e['target'],
                    'relationship' => $e['relationship'] ?? '',
                    'item_label' => $e['item_label'] ?? '',
                ];
            }
        }

        ok(['nodes' => $nodes, 'edges' => $edges]);
    }

    // ── COOK ───────────────────────────────────────────────────────────────
    if ($action === 'cook') {
        $rawIngredients    = $input['ingredients']        ?? [];
        $customInstruction = inputStr($input, 'custom_instruction');
        $descGenId         = inputInt($input, 'desc_gen_id');
        $nameGenId         = inputInt($input, 'name_gen_id');

        if (empty($rawIngredients) && !$customInstruction) {
            fail('The pot is empty and no instructions provided');
        }
        if (!$descGenId) fail('Missing desc_gen_id');

        // Hydrate ingredients & build prompt
        $hydratedParts = [];
        $kgSubpotParts = [];

        foreach ($rawIngredients as $raw) {
            $type = (string)($raw['type'] ?? '');
            $rawId = $raw['id'] ?? null;
            if (!$type || !$rawId) continue;

            if ($type === '_kg_subpot') {
                $kgSubpotParts[] = buildKgSubpotText($pdo, $raw);
                continue;
            }

            $id = (int)$rawId;
            if (!$id) continue;
            
            $segment = hydrateIngredient($pdo, $type, $id);
            if ($segment) $hydratedParts[] = $segment;
        }

        $promptParts = [];
        if (!empty($hydratedParts)) {
            $promptParts[] = "Scene Ingredients:\n" . implode("\n\n", $hydratedParts);
        }
        if (!empty($kgSubpotParts)) {
            $promptParts[] = implode("\n\n", $kgSubpotParts);
        }
        if ($customInstruction) {
            $promptParts[] = "### CHEF'S NOTES / ADDITIONAL INSTRUCTIONS:\n" . $customInstruction;
        }

        $finalPrompt = implode("\n\n---\n\n", $promptParts);

        $genStmt = $pdo->prepare("SELECT * FROM generator_config WHERE id = :id AND active = 1 LIMIT 1");
        $genStmt->execute([':id' => $descGenId]);
        $genRow = $genStmt->fetch(PDO::FETCH_ASSOC);
        if (!$genRow) fail("Description generator #$descGenId not found or inactive");

        $systemRole   = $genRow['system_role'] ?? '';
        $instructions = json_decode($genRow['instructions'] ?? '[]', true) ?: [];
        $aiParams     = json_decode($genRow['parameters']   ?? '{}', true) ?: [];
        $oracleConfig = json_decode($genRow['oracle_config']?? 'null', true);

        $oracleHint = '';
        if ($oracleConfig && !empty($oracleConfig['dictionary_ids'])) {
            try {
                $bloom = new \App\Oracle\Bloom();
                $hint  = $bloom->generateHint(
                    $oracleConfig['dictionary_ids'],
                    $oracleConfig['num_words']   ?? 200,
                    $oracleConfig['error_rate']  ?? 0.01
                );
                if (!empty($hint['meta']['sampled_lemmas'])) {
                    $words      = implode(', ', $hint['meta']['sampled_lemmas']);
                    $oracleHint = "\nINSPIRATIONAL HINT: Draw inspiration from: [$words]";
                }
            } catch (\Throwable $e) { /* non-fatal */ }
        }

        $sysMsg = trim(implode("\n\n", array_filter([$systemRole, implode("\n", $instructions), $oracleHint])));
        $messages = [
            ['role' => 'system', 'content' => $sysMsg],
            ['role' => 'user',   'content' => json_encode(['entity_name' => $finalPrompt], JSON_UNESCAPED_UNICODE)],
        ];

        $aiProvider  = $spw->getAIProvider();
        $rawResponse = $aiProvider->sendMessage($genRow['model'], $messages, $aiParams);
        $description = parseAiDescription($rawResponse);

        $sketchName = 'Sketch ' . date('Y-m-d H:i');
        if ($nameGenId && $nameGenId !== $descGenId) {
            $nStmt = $pdo->prepare("SELECT * FROM generator_config WHERE id = :id AND active=1 LIMIT 1");
            $nStmt->execute([':id' => $nameGenId]);
            $nRow = $nStmt->fetch(PDO::FETCH_ASSOC);
            if ($nRow) {
                $nSys  = trim(($nRow['system_role'] ?? '') . "\n" . implode("\n", json_decode($nRow['instructions'] ?? '[]', true) ?: []));
                $nMsgs = [
                    ['role' => 'system', 'content' => $nSys],
                    ['role' => 'user',   'content' => json_encode(['entity_name' => $description, 'entity_type' => 'sketch'], JSON_UNESCAPED_UNICODE)],
                ];
                $nRaw      = $aiProvider->sendMessage($nRow['model'], $nMsgs, json_decode($nRow['parameters'] ?? '{}', true) ?: []);
                $nData     = @json_decode($nRaw, true);
                $parsedName= $nData['name'] ?? $nData['text'] ?? null;
                if ($parsedName) $sketchName = trim($parsedName, '"\'');
            }
        }

        $sketchId = $chef->saveSketch(
            name:        $sketchName,
            description: $description,
            ingredients: $rawIngredients,
            descGenId:   $descGenId,
            nameGenId:   $nameGenId
        );

        ok([
            'sketch_id'   => $sketchId,
            'sketch_name' => $sketchName,
            'description' => $description,
        ]);
    }

    // ── RUN CONTINUITY ──────────────────────────────────────────────────────
    if ($action === 'run_continuity') {
        $charIds   = array_map('intval', (array)($input['character_ids'] ?? []));
        $genId     = inputInt($input, 'generator_id');
        $desc      = inputStr($input, 'description');
        $sketchId  = inputInt($input, 'sketch_id');

        if (empty($charIds)) fail('No characters selected');
        if (!$desc)           fail('No scene description provided');

        $ph = implode(',', array_fill(0, count($charIds), '?'));
        $cStmt = $pdo->prepare("SELECT id, name, description FROM characters WHERE id IN ($ph) ORDER BY FIELD(id, $ph)");
        $cStmt->execute(array_merge($charIds, $charIds));
        $chars = $cStmt->fetchAll(PDO::FETCH_ASSOC);

        $charBlocks = [];
        foreach ($chars as $c) {
            $charDesc     = trim(strip_tags($c['description'] ?? ''));
            $charBlocks[] = "CHARACTER: {$c['name']}\n{$charDesc}";
        }
        $charContext = implode("\n\n", $charBlocks);
        
       $continuityPrompt =
            "You are a cinematic scene compiler. Your task is to rewrite a scene description so that the specified characters appear with their exact appearance as described, while preserving the full cinematic dynamism and action of the original scene.\n\n"
            . "CRITICAL RULES:\n"
            . "- Keep the original scene energy, action, and visual drama INTACT\n"
            . "- Do NOT reduce the scene to a static or posed composition\n"
            . "- Characters must match their exact physical descriptions below\n"
            . "- Preserve all environmental details, lighting, scale, and atmosphere from the original\n"
            . "- Place characters naturally within the scene's action, not posed for a portrait\n"
            . "- The scene prompt goes LAST in your response for maximum AI impact\n\n"
            . "CHARACTER REFERENCE (Use these EXACT descriptions):\n"
            . $charContext
            . "\n\n---\n\n"
            . "ORIGINAL SCENE TO REWRITE:\n"
            . $desc
            . "\n\n---\n\n"
            . "Rewrite the scene with the characters above integrated naturally into the action. Return ONLY the final scene description as JSON: {\"scene_prompt\": \"...\"}";

        $genStmt = $pdo->prepare("SELECT * FROM generator_config WHERE id = :id AND active=1 LIMIT 1");
        $genStmt->execute([':id' => $genId]);
        $genRow = $genStmt->fetch(PDO::FETCH_ASSOC);
        if (!$genRow) fail("Continuity generator #$genId not found");

        $sysMsg = trim(($genRow['system_role'] ?? '') . "\n" . implode("\n", json_decode($genRow['instructions'] ?? '[]', true) ?: []));
        $msgs   = [
            ['role' => 'system', 'content' => $sysMsg],
            ['role' => 'user',   'content' => $continuityPrompt],
        ];

        $aiProvider  = $spw->getAIProvider();
        $rawResponse = $aiProvider->sendMessage(
            $genRow['model'], $msgs,
            json_decode($genRow['parameters'] ?? '{}', true) ?: []
        );

        $parsed = @json_decode($rawResponse, true);
        if (!$parsed) {
            $cleaned = preg_replace('/^```(?:json)?\s*\n?(.*?)\n?```$/s', '$1', trim($rawResponse));
            $parsed  = @json_decode($cleaned, true);
        }

        $newDescription = $parsed['scene_prompt'] ?? $parsed['description'] ?? null;

        if (!$newDescription) {
            $newDescription = trim(preg_replace('/\{.*?\}/s', '', $rawResponse));
            if (strlen($newDescription) < 50) fail('AI did not return a valid scene description');
        }

        $newDescription = str_replace(["\u{2014}", "—"], "", $newDescription);

        if ($sketchId > 0) {
            $pdo->prepare("UPDATE sketches SET description = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$newDescription, $sketchId]);
        }

        ok(['new_description' => $newDescription]);
    }

    // ── SAVE CONTINUITY RESULT ─────────────────────────────────────────────
    if ($action === 'save_continuity_result') {
        $sketchId = inputInt($input, 'sketch_id');
        $desc     = inputStr($input, 'description');
        if (!$sketchId || !$desc) fail('Missing sketch_id or description');
        $pdo->prepare("UPDATE sketches SET description = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$desc, $sketchId]);
        ok(null, 'Saved');
    }

    // ── GET SKETCH DESCRIPTION ─────────────────────────────────────────────
    if ($action === 'get_sketch_desc') {
        $id   = inputInt($input, 'id');
        $stmt = $pdo->prepare("SELECT description FROM sketches WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) fail('Sketch not found', 404);
        ok(['description' => $row['description'] ?? '']);
    }

    // ── RECENT SKETCHES ────────────────────────────────────────────────────
    if ($action === 'recent_sketches') {
        $stmt = $pdo->query("SELECT id, name, description FROM sketches ORDER BY created_at DESC LIMIT 8");
        ok(['sketches' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // ── RANDOM RECIPE ──────────────────────────────────────────────────────
    if ($action === 'random_recipe') {
        $maxChars   = max(1, inputInt($input, 'max_chars', 3));
        $ingredients = [];

        if (rand(1,100) <= 85) {
            $r = $pdo->query("SELECT id, name FROM sketch_templates WHERE active=1 AND is_ingredient=1 ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($r) $ingredients[] = ['type'=>'sketch_template','id'=>(int)$r['id'],'label'=>$r['name'],'icon'=>'🎬'];
        }
        if (rand(1,100) <= 60) {
            $r = $pdo->query("SELECT id, name FROM interactions WHERE active=1 AND is_ingredient=1 ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($r) $ingredients[] = ['type'=>'interaction','id'=>(int)$r['id'],'label'=>$r['name'],'icon'=>'🤝'];
        }
        if (rand(1,100) <= 50) {
            $r = $pdo->query("SELECT id, name FROM style_profiles WHERE convert_result IS NOT NULL AND convert_result != '' AND is_ingredient=1 ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($r) $ingredients[] = ['type'=>'style_profile','id'=>(int)$r['id'],'label'=>$r['name'],'icon'=>'🎨'];
        }
        if (rand(1,100) <= 70) {
            $cat = $pdo->query("SELECT table_name, name FROM anivoc_categories ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($cat) {
                $tbl  = $cat['table_name'];
                $icon = [
                    'anivoc_expressions'=>'😊','anivoc_lighting'=>'💡','anivoc_color_coding'=>'🎨',
                    'anivoc_motion_impact'=>'💥','anivoc_transitions'=>'🎞️','anivoc_backgrounds'=>'🏙️',
                ][$tbl] ?? '📘';
                $r = $pdo->query("SELECT id, name FROM `$tbl` WHERE is_ingredient=1 ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($r) $ingredients[] = ['type'=>$tbl,'id'=>(int)$r['id'],'label'=>$r['name'],'icon'=>$icon];
            }
        }
        if (rand(1,100) <= 40) {
            $num   = rand(1, $maxChars);
            $rows  = $pdo->query("SELECT id, name FROM characters WHERE is_ingredient=1 ORDER BY RAND() LIMIT $num")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $ingredients[] = ['type'=>'characters','id'=>(int)$r['id'],'label'=>$r['name'],'icon'=>'🦸'];
            }
        }

        ok(['ingredients' => $ingredients]);
    }

    // ── SAVE RECIPE ────────────────────────────────────────────────────────
    if ($action === 'save_recipe') {
        $name  = inputStr($input, 'name') ?: 'Untitled';
        $ings  = $input['ingredients'] ?? [];
        $notes = inputStr($input, 'notes');
        if (empty($ings) && !$notes) fail('Nothing to save');
        $pdo->prepare("INSERT INTO scene_kitchen_pots (name, ingredients_json, notes) VALUES (?, ?, ?)")
            ->execute([$name, json_encode($ings, JSON_UNESCAPED_UNICODE), $notes]);
        ok(['id' => $pdo->lastInsertId()]);
    }

    // ── LIST RECIPES ───────────────────────────────────────────────────────
    if ($action === 'list_recipes') {
        $page  = max(1, inputInt($input, 'page', 1));
        $limit = 10;
        $off   = ($page - 1) * $limit;
        $total = $pdo->query("SELECT COUNT(*) FROM scene_kitchen_pots")->fetchColumn();
        $stmt  = $pdo->prepare("SELECT id, name, notes, created_at FROM scene_kitchen_pots ORDER BY created_at DESC LIMIT $limit OFFSET $off");
        $stmt->execute();
        ok(['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => (int)$total, 'page' => $page, 'pages' => (int)ceil($total/$limit)]);
    }

    // ── LOAD RECIPE ────────────────────────────────────────────────────────
    if ($action === 'load_recipe') {
        $id   = inputInt($input, 'id');
        $stmt = $pdo->prepare("SELECT ingredients_json, notes FROM scene_kitchen_pots WHERE id = ?");
        $stmt->execute([$id]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) fail('Recipe not found', 404);
        $ings = json_decode($row['ingredients_json'] ?? '[]', true) ?: [];
        ok(['ingredients' => hydrateListForUI($pdo, $ings), 'notes' => $row['notes'] ?? '']);
    }

    // ── DELETE RECIPE ──────────────────────────────────────────────────────
    if ($action === 'delete_recipe') {
        $id = inputInt($input, 'id');
        $pdo->prepare("DELETE FROM scene_kitchen_pots WHERE id = ?")->execute([$id]);
        ok(null, 'Deleted');
    }

    fail("Unknown action: $action");

} catch (\Throwable $e) {
    http_response_code(500);
    exit(json_encode([
        'ok' => false, 
        'error' => $e->getMessage(), 
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE));
}

// ══════════════════════════════════════════════════════
// HELPER FUNCTIONS (Outside try/catch to prevent scope issues)
// ══════════════════════════════════════════════════════

function ok(mixed $data = null, string $message = ''): never {
    $r = ['ok' => true];
    if ($data    !== null) $r = array_merge($r, is_array($data) ? $data : ['data' => $data]);
    if ($message !== '')   $r['message'] = $message;
    exit(json_encode($r, JSON_UNESCAPED_UNICODE));
}

function fail(string $error, int $code = 400): never {
    http_response_code($code);
    exit(json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE));
}

function inputInt(array $input, string $key, int $default = 0): int {
    return isset($input[$key]) ? (int)$input[$key] : $default;
}

function inputStr(array $input, string $key, string $default = ''): string {
    return isset($input[$key]) ? trim((string)$input[$key]) : $default;
}

function hydrateIngredient(PDO $pdo, string $type, int $id): string
{
    $row = null;

    switch ($type) {
        case 'sketch_template':
            $s = $pdo->prepare("SELECT name, example_prompt, core_idea FROM sketch_templates WHERE id = ?");
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if (!$row) return '';
            $seg = $row['example_prompt'] ?? '';
            if (!empty($row['core_idea'])) $seg .= " (Core Concept: {$row['core_idea']})";
            return $seg;

        case 'interaction':
            $s = $pdo->prepare("SELECT name, example_prompt, description FROM interactions WHERE id = ?");
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if (!$row) return '';
            return ($row['example_prompt'] ?? '') . ' (' . ($row['description'] ?? '') . ')';

        case 'style_profile':
            $s = $pdo->prepare("SELECT name, convert_result FROM style_profiles WHERE id = ?");
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if (!$row) return '';
            $raw  = $row['convert_result'] ?? '';
            $json = @json_decode($raw, true);
            $text = ($json && !empty($json['textualStylePrompt'])) ? $json['textualStylePrompt'] : $raw;
            return "Visual Style: $text";

        default:
            if (!in_array($type, ENTITY_TABLES, true)) return '';
            $s = $pdo->prepare("SELECT name, description FROM `$type` WHERE id = ?");
            $s->execute([$id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if (!$row) return '';
            $label = ucwords(str_replace(['anivoc_', '_'], ['', ' '], $type));
            if (substr($label, -1) === 's') $label = substr($label, 0, -1);
            $desc = trim(strip_tags($row['description'] ?? ''));
            return "$label: {$row['name']}" . ($desc ? " — $desc" : '');
    }
}

function buildKgSubpotText(PDO $pdo, array $raw): string
{
    // Fix: array_values strips the keys preserved by array_filter so PDO positional params work.
    $nodeIds = array_values(array_map('intval', array_filter(explode('_', (string)$raw['id']), 'is_numeric')));
    if (empty($nodeIds)) return '';

    $ph    = implode(',', array_fill(0, count($nodeIds), '?'));
    $stmt  = $pdo->prepare("SELECT id, name, node_type, description FROM kg_nodes WHERE id IN ($ph) AND status='active'");
    $stmt->execute($nodeIds);
    $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $lines = ['[Knowledge Graph Subplot]'];
    foreach ($nodes as $n) {
        $desc   = trim(strip_tags($n['description'] ?? ''));
        $line   = "• [{$n['node_type']}] {$n['name']}";
        if ($desc) $line .= ": $desc";
        $lines[] = $line;
    }

    if (!empty($raw['includeEdges']) && count($nodeIds) > 1) {
        $ph2  = implode(',', array_fill(0, count($nodeIds), '?'));
        $stmt = $pdo->prepare("
            SELECT kni.relationship, kni.item_label, kn.name AS src_name
            FROM kg_node_items kni
            JOIN kg_nodes kn ON kn.id = kni.node_id
            WHERE kni.item_type = 'kg_node'
              AND kni.node_id IN ($ph2)
              AND kni.item_id IN ($ph2)
            LIMIT 20
        ");
        $stmt->execute(array_merge($nodeIds, $nodeIds));
        $edges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($edges) {
            $lines[] = '';
            $lines[] = '[Relationships]';
            foreach ($edges as $e) {
                $rel     = $e['relationship'] ?: 'relates to';
                $lines[] = "→ {$e['src_name']} $rel {$e['item_label']}";
            }
        }
    }

    return implode("\n", $lines);
}

function hydrateListForUI(PDO $pdo, array $rawList): array
{
    global $entityIcons;
    $result = [];

    foreach ($rawList as $raw) {
        $type  = (string)($raw['type'] ?? '');
        $id    = (string)($raw['id']   ?? '');
        $label = (string)($raw['label'] ?? '');
        $icon  = (string)($raw['icon']  ?? '📦');

        if ($type === '_kg_subpot') {
            $result[] = $raw;
            continue;
        }

        if ($label) {
            $result[] = compact('type', 'id', 'label', 'icon');
            continue;
        }

        $iid = (int)$id;
        $name = null;
        try {
            switch ($type) {
                case 'sketch_template':
                    $s = $pdo->prepare("SELECT name FROM sketch_templates WHERE id=?"); $s->execute([$iid]); $name = $s->fetchColumn(); break;
                case 'interaction':
                    $s = $pdo->prepare("SELECT name FROM interactions WHERE id=?"); $s->execute([$iid]); $name = $s->fetchColumn(); break;
                case 'style_profile':
                    $s = $pdo->prepare("SELECT name FROM style_profiles WHERE id=?"); $s->execute([$iid]); $name = $s->fetchColumn(); break;
                default:
                    if (in_array($type, ENTITY_TABLES, true)) {
                        $s = $pdo->prepare("SELECT name FROM `$type` WHERE id=?"); $s->execute([$iid]); $name = $s->fetchColumn();
                    }
            }
        } catch (\Throwable $e) {}

        if ($name) {
            $icon = $entityIcons[$type] ?? $icon;
            $result[] = ['type' => $type, 'id' => $id, 'label' => $name, 'icon' => $icon];
        }
    }

    return $result;
}

function parseAiDescription(string $raw): string
{
    $text    = trim($raw);
    $decoded = @json_decode($text, true);
    if (!$decoded) {
        $cleaned = preg_replace('/^```(?:json)?\s*\n?(.*?)\n?```$/s', '$1', $text);
        $decoded = @json_decode(trim($cleaned), true);
    }
    if (!$decoded) {
        if (preg_match('/(\{.*\})/s', $text, $m)) {
            $decoded = @json_decode($m[1], true);
        }
    }
    if (is_array($decoded)) {
        foreach (['description', 'scene_prompt', 'text', 'content', 'result'] as $k) {
            if (!empty($decoded[$k]) && is_string($decoded[$k])) return $decoded[$k];
        }
        $first = reset($decoded);
        if (is_string($first) && strlen($first) > 30) return $first;
    }
    $fallback = preg_replace('/^\{.*\}$/s', '', $text);
    return trim($fallback) ?: $text;
}
