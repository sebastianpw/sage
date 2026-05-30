<?php
// public/view_showrunner_creator.php — v1.3
// Creates new md_doc_analysis rows via a web UI, no CLI needed.
// Compatible with view_curated_docs.php, view_showrunner_editor.php, api_lore.php
//
// MODE A: Pick an existing documentations row that has no analysis yet
// MODE B: Create a brand-new documentations row + analysis row together
//
// API endpoints (same file, ?api_action=...):
//   get_categories   GET  → category list
//   get_orphan_docs  GET  → docs without md_doc_analysis rows
//   preview_skeleton GET  → blank JSON skeleton for a given preset
//   create_analysis  POST → main create handler

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ─────────────────────────────────────────
// BLANK SKELETON PRESETS
// Match what cli_md_curator_aggregate.php produces so view_curated_docs.php renders immediately
// ─────────────────────────────────────────
function blank_showrunner_skeleton(string $preset = 'full'): array {
    $base = [
        'visual_keywords'            => [],
        'scene_hooks'                => [],
        'production_notes'           => '',
        'readiness_score'            => 0,
        'narrative_engine'           => [
            'core_conflict'          => '',
            'central_metaphor'       => '',
            'philosophical_stakes'   => '',
            'readiness_score'        => 0,
        ],
        'episode_concepts'           => [],
        'narrative_engines_all'      => [],
        'episode_concepts_all'       => [],
        'scene_hooks_all'            => [],
        'production_notes_by_chunk'  => [],
        'lore_raw_by_chunk'          => [],
        'show_raw_by_chunk'          => [],
        'series_bible'               => '',
        'cast_brief'                 => [],
        'extracted_entities'         => [
            'characters' => [], 'locations'  => [],
            'factions'   => [], 'artifacts'  => [],
            'objects'    => [], 'roles'      => [],
        ],
    ];

    if ($preset === 'episode_focused') {
        $base['episode_concepts'] = [[
            'episode_number'     => 1,
            'title'              => 'Episode 1',
            'high_concept'       => '',
            'logline'            => '',
            'narrative_beats'    => [
                'Cold open: ',
                'Act 1: ',
                'Act 2: ',
                'Act 3: ',
                'Closing beat: ',
            ],
            'thematic_focus'     => '',
            'narrative_function' => [],
            'layer'              => '',
            'energy'             => '',
            'conflict'           => '',
            'key_scene'          => '',
            'notes'              => '',
        ]];
    }

    if ($preset === 'world_focused') {
        $base['extracted_entities']['characters'] = [
            ['name' => '', 'aliases' => [], 'roles' => [], 'description' => '', 'sources' => [], 'raw' => []]
        ];
        $base['extracted_entities']['locations'] = [['name' => '', 'description' => '']];
    }

    if ($preset === 'full_expanded') {
        // Every array gets one maximally complete stub — use as clipboard reference in the Editor.
        // Delete or clear stubs before saving real content.
        $base['episode_concepts'] = [[
            'episode_number'     => 1,
            'title'              => '',
            'high_concept'       => '',
            'logline'            => '',
            'narrative_beats'    => [
                'Cold open: ',
                'Act 1: ',
                'Act 2: ',
                'Act 3: ',
                'Closing beat: ',
            ],
            'thematic_focus'     => '',
            'narrative_function' => [],
            'layer'              => '',
            'energy'             => '',
            'conflict'           => '',
            'key_scene'          => '',
            'notes'              => '',
        ]];
        $base['scene_hooks'] = [[
            'title'       => '',
            'description' => '',
        ]];
        $base['visual_keywords'] = [''];
        $base['narrative_engine'] = [
            'core_conflict'        => '',
            'central_metaphor'     => '',
            'philosophical_stakes' => '',
            'readiness_score'      => 0,
        ];
        $base['extracted_entities'] = [
            'characters' => [[
                'name'          => '',
                'aliases'       => [],
                'roles'         => [],
                'description'   => '',
                'attributes'    => [
                    'age'           => '',
                    'appearance'    => '',
                    'motivation'    => '',
                    'arc'           => '',
                ],
                'relationships' => [[
                    'target'  => '',
                    'type'    => '',
                    'nature'  => '',
                    'description' => '',
                ]],
                'actions'       => [],
                'history'       => [],
                'sources'       => [],
                'raw'           => [],
            ]],
            'locations' => [[
                'name'        => '',
                'description' => '',
                'attributes'  => [
                    'geography'   => '',
                    'atmosphere'  => '',
                    'significance'=> '',
                ],
                'factions_present' => [],
                'history'     => [],
            ]],
            'factions' => [[
                'name'        => '',
                'description' => '',
                'goals'       => '',
                'methods'     => '',
                'members'     => [],
                'allies'      => [],
                'enemies'     => [],
            ]],
            'artifacts' => [[
                'name'        => '',
                'description' => '',
                'properties'  => '',
                'origin'      => '',
                'current_holder' => '',
            ]],
            'objects' => [[
                'name'        => '',
                'description' => '',
                'significance'=> '',
            ]],
            'roles' => [[
                'name'        => '',
                'description' => '',
                'held_by'     => [],
            ]],
        ];
        $base['cast_brief'] = [''];
        $base['series_bible']      = '';
        $base['production_notes']  = '';
    }

    return $base;
}

function blank_entities_skeleton(): array {
    return ['characters'=>[],'locations'=>[],'factions'=>[],'artifacts'=>[],'objects'=>[],'roles'=>[]];
}

function blank_lore_skeleton(): array {
    return [
        'timeline_events'=>[],'technology_magic'=>[],'lore_rules'=>[],
        'anima_types'=>[],'visual_details'=>[],'trade_goods'=>[],'population_data'=>[],
    ];
}

// ─────────────────────────────────────────
// API HANDLERS
// ─────────────────────────────────────────
if (isset($_REQUEST['api_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_REQUEST['api_action'];
    try {

        if ($action === 'get_categories') {
            $rows = $pdo->query("SELECT id, name FROM documentation_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status'=>'success','data'=>$rows]); exit;
        }

        if ($action === 'get_orphan_docs') {
            $search = trim($_GET['search'] ?? '');
            $catId  = (int)($_GET['category_id'] ?? 0);
            $where  = ["d.is_active = 1","da.id IS NULL"];
            $params = [];
            if ($search !== '') { $where[] = "d.name LIKE :s"; $params[':s'] = "%{$search}%"; }
            if ($catId > 0)     { $where[] = "d.category_id = :cat"; $params[':cat'] = $catId; }
            $stmt = $pdo->prepare("
                SELECT d.id, d.name, d.desc_short, c.name AS cat_name, d.category_id
                FROM documentations d
                LEFT JOIN md_doc_analysis da ON da.doc_id = d.id
                LEFT JOIN documentation_categories c ON c.id = d.category_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY d.name ASC LIMIT 100
            ");
            $stmt->execute($params);
            echo json_encode(['status'=>'success','data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
        }

        if ($action === 'preview_skeleton') {
            $preset = $_GET['preset'] ?? 'full';
            echo json_encode([
                'status'              => 'success',
                'showrunner_analysis' => blank_showrunner_skeleton($preset),
                'entities'            => blank_entities_skeleton(),
                'lore_points'         => blank_lore_skeleton(),
            ]); exit;
        }

        if ($action === 'create_analysis') {
            $body = json_decode(file_get_contents('php://input'), true);
            if (!$body) throw new Exception('Invalid JSON body');

            $mode            = $body['mode']              ?? 'existing';
            $preset          = $body['preset']            ?? 'full';
            $docName         = trim($body['doc_name']     ?? '');
            $docDesc         = trim($body['doc_desc']     ?? '');
            $docDescShort    = trim($body['doc_desc_short'] ?? '');
            $categoryId      = ($body['category_id'] ?? '') !== '' ? (int)$body['category_id'] : null;
            $existingDocId   = isset($body['doc_id']) ? (int)$body['doc_id'] : 0;
            $targetColl      = trim($body['target_collection'] ?? 'sage_lore_entities_draft');
            $seriesBible     = trim($body['series_bible'] ?? '');
            $productionNotes = trim($body['production_notes'] ?? '');
            $narrCore        = trim($body['narrative_core'] ?? '');
            $narrMeta        = trim($body['narrative_metaphor'] ?? '');
            $narrStakes      = trim($body['narrative_stakes'] ?? '');
            $themes          = array_values(array_filter(array_map('trim', explode(',', $body['themes'] ?? ''))));
            $mood            = trim($body['mood'] ?? '');
            $customShowrunner= $body['custom_showrunner'] ?? null;

            // Resolve docId
            $docId = 0;
            if ($mode === 'existing') {
                if ($existingDocId <= 0) throw new Exception('No doc_id provided');
                $chk = $pdo->prepare("SELECT id FROM documentations WHERE id = ? LIMIT 1");
                $chk->execute([$existingDocId]);
                if (!$chk->fetchColumn()) throw new Exception("documentations #{$existingDocId} not found");
                $chk2 = $pdo->prepare("SELECT id FROM md_doc_analysis WHERE doc_id = ? LIMIT 1");
                $chk2->execute([$existingDocId]);
                if ($chk2->fetchColumn()) throw new Exception(
                    "md_doc_analysis already exists for doc #{$existingDocId}. Use the editor instead."
                );
                $docId = $existingDocId;
            } else {
                if ($docName === '') throw new Exception('doc_name is required');
                $ins = $pdo->prepare("
                    INSERT INTO documentations
                        (name, description, desc_short, category_id, target_collection, is_active, created_at, updated_at)
                    VALUES (?,?,?,?,?,1,NOW(),NOW())
                ");
                $ins->execute([$docName, $docDesc ?: null, $docDescShort ?: null, $categoryId, $targetColl ?: null]);
                $docId = (int)$pdo->lastInsertId();
                if (!$docId) throw new Exception('Failed to insert documentations row');
            }

            // Build showrunner JSON
            $showrunnerArr = ($customShowrunner !== null && is_array($customShowrunner))
                           ? $customShowrunner
                           : blank_showrunner_skeleton($preset);

            if ($seriesBible !== '')     $showrunnerArr['series_bible']     = $seriesBible;
            if ($productionNotes !== '')  $showrunnerArr['production_notes'] = $productionNotes;
            if ($narrCore !== '' || $narrMeta !== '' || $narrStakes !== '') {
                $showrunnerArr['narrative_engine'] = [
                    'core_conflict'        => $narrCore,
                    'central_metaphor'     => $narrMeta,
                    'philosophical_stakes' => $narrStakes,
                    'readiness_score'      => 0,
                ];
            }

            $showrunnerJson = json_encode($showrunnerArr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $entitiesJson   = json_encode(blank_entities_skeleton(), JSON_UNESCAPED_UNICODE);
            $loreJson       = json_encode(blank_lore_skeleton(),     JSON_UNESCAPED_UNICODE);
            $thematicsJson  = json_encode(['themes'=>$themes,'mood'=>$mood], JSON_UNESCAPED_UNICODE);

            $ins2 = $pdo->prepare("
                INSERT INTO md_doc_analysis
                    (doc_id, summary, entities, lore_points, thematics,
                     narrative_utility, showrunner_analysis, series_bible, target_collection, analyzed_at)
                VALUES (?, '', ?, ?, ?, 0, ?, ?, ?, NOW())
            ");
            $ins2->execute([
                $docId,
                $entitiesJson, $loreJson, $thematicsJson,
                $showrunnerJson,
                $seriesBible ?: null,
                $targetColl  ?: null,
            ]);
            $newId = (int)$pdo->lastInsertId();

            echo json_encode([
                'status'      => 'success',
                'doc_id'      => $docId,
                'analysis_id' => $newId,
                'message'     => "Created md_doc_analysis #$newId for doc #$docId",
            ]);
            exit;
        }

        throw new Exception("Unknown action: {$action}");

    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        exit;
    }
}

// ─────────────────────────────────────────
// PAGE
// ─────────────────────────────────────────
$pageTitle = 'Showrunner Creator';
ob_start();
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:ital,wght@0,400;0,600;1,400&family=Syne:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/base.css">

<style>
:root{
    --ed-bg:#0e0f11;--ed-surface:#161719;--ed-border:#2a2c31;--ed-border-hi:#3f4148;
    --ed-text:#d4d6da;--ed-muted:#6b6f7a;--ed-accent:#e8c547;--ed-green:#4caf7d;
    --ed-red:#e05c5c;--ed-purple:#9b72cf;--ed-mono:'IBM Plex Mono',monospace;
    --ed-sans:'Syne',sans-serif;--r:6px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:var(--ed-bg);color:var(--ed-text);font-family:var(--ed-mono);font-size:13px;line-height:1.6;min-height:100vh}

/* SHELL */
#creator-shell{display:grid;grid-template-columns:320px 1fr;grid-template-rows:52px 1fr;height:100vh;overflow:hidden}

/* TOPBAR */
#topbar{grid-column:1/-1;background:var(--ed-surface);border-bottom:2px solid var(--ed-purple);display:flex;align-items:center;gap:18px;padding:0 20px}
.wordmark{font-family:var(--ed-sans);font-weight:800;font-size:15px;color:var(--ed-purple);letter-spacing:.08em;text-transform:uppercase;white-space:nowrap;flex-shrink:0}
.topbar-sep{width:1px;height:24px;background:var(--ed-border);flex-shrink:0}
.topbar-links{display:flex;gap:10px;flex:1}
.topbar-link{font-size:11px;color:var(--ed-muted);text-decoration:none;padding:4px 10px;border-radius:4px;border:1px solid var(--ed-border);transition:all .15s}
.topbar-link:hover{color:var(--ed-text);border-color:var(--ed-border-hi)}
#create-btn{background:var(--ed-purple);color:#fff;border:none;font-family:var(--ed-sans);font-weight:700;font-size:12px;letter-spacing:.06em;text-transform:uppercase;padding:7px 20px;border-radius:var(--r);cursor:pointer;transition:opacity .15s,transform .1s;flex-shrink:0}
#create-btn:hover{opacity:.88;transform:translateY(-1px)}
#create-btn:disabled{opacity:.35;pointer-events:none}

/* LEFT PANEL */
#left-panel{background:var(--ed-surface);border-right:1px solid var(--ed-border);overflow-y:auto}
.panel-section{padding:14px 16px;border-bottom:1px solid var(--ed-border)}
.panel-label{font-family:var(--ed-sans);font-size:9px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--ed-muted);margin-bottom:10px;display:flex;align-items:center;gap:6px}
.panel-label .badge{background:rgba(155,114,207,.15);color:var(--ed-purple);border:1px solid rgba(155,114,207,.25);border-radius:3px;padding:1px 6px;font-size:9px}
.mode-tabs{display:flex;gap:6px}
.mode-tab{flex:1;padding:8px 4px;text-align:center;font-family:var(--ed-sans);font-size:11px;font-weight:700;border:1px solid var(--ed-border);border-radius:var(--r);cursor:pointer;color:var(--ed-muted);background:transparent;transition:all .15s;user-select:none}
.mode-tab.active{background:rgba(155,114,207,.12);border-color:var(--ed-purple);color:var(--ed-purple)}
.mode-tab:hover:not(.active){border-color:var(--ed-border-hi);color:var(--ed-text)}
.field-row{margin-bottom:10px}
.field-label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--ed-muted);margin-bottom:4px}
.field-label .req{color:var(--ed-red);margin-left:2px}
.field-input,.field-select,.field-textarea{width:100%;background:var(--ed-bg);border:1px solid var(--ed-border);color:var(--ed-text);font-family:var(--ed-mono);font-size:12px;padding:7px 10px;border-radius:var(--r);outline:none;transition:border-color .15s}
.field-input:focus,.field-select:focus,.field-textarea:focus{border-color:var(--ed-purple)}
.field-textarea{resize:vertical;min-height:70px;line-height:1.6}
.field-hint{font-size:10px;color:var(--ed-muted);margin-top:3px;font-style:italic}
#orphan-search{width:100%;background:var(--ed-bg);border:1px solid var(--ed-border);color:var(--ed-text);font-family:var(--ed-mono);font-size:12px;padding:7px 10px;border-radius:var(--r);outline:none;margin-bottom:8px}
#orphan-search:focus{border-color:var(--ed-purple)}
#orphan-list{max-height:250px;overflow-y:auto;border:1px solid var(--ed-border);border-radius:var(--r);background:var(--ed-bg)}
.orphan-item{padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--ed-border);transition:background .1s}
.orphan-item:last-child{border-bottom:none}
.orphan-item:hover{background:rgba(155,114,207,.06)}
.orphan-item.selected{background:rgba(155,114,207,.1);border-left:3px solid var(--ed-purple);padding-left:9px}
.orphan-name{font-size:12px;color:var(--ed-text);font-weight:600}
.orphan-cat{font-size:10px;color:var(--ed-muted)}
.orphan-empty{padding:14px;color:var(--ed-muted);font-style:italic;font-size:11px;text-align:center}

/* RIGHT PANEL */
#right-panel{overflow-y:auto;background:var(--ed-bg)}
#right-scroll{padding:24px 32px 60px;display:flex;flex-direction:column;gap:20px}
.form-section{border:1px solid var(--ed-border);border-radius:8px;background:var(--ed-surface);overflow:hidden}
.form-section-head{padding:11px 18px;background:rgba(255,255,255,.02);border-bottom:1px solid var(--ed-border);display:flex;align-items:center;gap:10px}
.form-section-icon{font-size:16px}
.form-section-title{font-family:var(--ed-sans);font-weight:800;font-size:13px;color:var(--ed-text);flex:1}
.form-section-sub{font-size:10px;color:var(--ed-muted)}
.form-section-body{padding:18px;display:flex;flex-direction:column;gap:14px}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.preset-pills{display:flex;gap:8px;flex-wrap:wrap}
.preset-pill{padding:6px 14px;border-radius:20px;font-size:11px;font-weight:700;font-family:var(--ed-sans);border:1px solid var(--ed-border);background:transparent;color:var(--ed-muted);cursor:pointer;transition:all .15s;user-select:none}
.preset-pill.active{background:rgba(155,114,207,.15);border-color:var(--ed-purple);color:var(--ed-purple)}
.preset-pill:hover:not(.active){border-color:var(--ed-border-hi);color:var(--ed-text)}
.json-preview-wrap{border:1px solid var(--ed-border);border-radius:var(--r);background:#0a0b0d;overflow:hidden}
.json-preview-head{padding:6px 12px;background:rgba(255,255,255,.03);border-bottom:1px solid var(--ed-border);display:flex;justify-content:space-between;align-items:center;font-size:10px;color:var(--ed-muted)}
.json-preview-body{max-height:240px;overflow-y:auto;padding:12px;font-size:11px;line-height:1.7;color:#7ec8a0;white-space:pre-wrap;font-family:var(--ed-mono)}
.adv-toggle{background:none;border:1px dashed var(--ed-border);color:var(--ed-muted);font-family:var(--ed-mono);font-size:11px;padding:6px 12px;border-radius:var(--r);cursor:pointer;transition:all .15s;align-self:flex-start}
.adv-toggle:hover{border-color:var(--ed-border-hi);color:var(--ed-text)}
#adv-section{display:none}
#adv-section.visible{display:flex;flex-direction:column;gap:14px}
#success-banner{display:none;padding:18px 22px;background:rgba(76,175,125,.1);border:1px solid rgba(76,175,125,.3);border-radius:8px;color:var(--ed-green);font-size:13px;line-height:1.7}
#success-banner.visible{display:block}
#success-banner a{color:var(--ed-accent)}
#toast-bar{display:none;position:fixed;bottom:22px;right:26px;background:var(--ed-surface);border:1px solid var(--ed-border);color:var(--ed-text);font-size:12px;padding:10px 18px;border-radius:var(--r);box-shadow:0 6px 28px rgba(0,0,0,.55);z-index:9999;pointer-events:none}
#toast-bar.visible{display:block}
@keyframes spin{to{transform:rotate(360deg)}}
.spin{display:inline-block;width:10px;height:10px;border:2px solid rgba(255,255,255,.2);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:6px}
</style>

<div id="creator-shell">

  <div id="topbar">
    <div class="wordmark">✦ Creator</div>
    <div class="topbar-sep"></div>
    <div class="topbar-links">
      <a href="view_curated_docs.php"     class="topbar-link">📜 Story Bible</a>
      <a href="view_showrunner_editor.php" class="topbar-link">✏️ Editor</a>
      <a href="skeletjson.php"             class="topbar-link">🦴 Skeleton</a>
    </div>
    <button id="create-btn" onclick="submitCreate()" disabled>✦ Create Row</button>
  </div>

  <div id="left-panel">

    <div class="panel-section">
      <div class="panel-label">Mode <span class="badge">Step 1</span></div>
      <div class="mode-tabs">
        <div class="mode-tab active" id="tab-existing" onclick="setMode('existing')">Existing Doc</div>
        <div class="mode-tab"        id="tab-new"      onclick="setMode('new_doc')">New Doc</div>
      </div>
    </div>

    <div class="panel-section" id="mode-existing-panel">
      <div class="panel-label">Select doc without analysis <span class="badge">Step 2a</span></div>
      <div class="field-row">
        <select class="field-select" id="orphan-cat-filter" onchange="loadOrphans()" style="margin-bottom:8px">
          <option value="0">All categories</option>
        </select>
      </div>
      <input id="orphan-search" type="text" placeholder="Search docs…" oninput="debounceOrphan()">
      <div id="orphan-list"><div class="orphan-empty">Loading…</div></div>
      <div style="margin-top:8px;font-size:10px;color:var(--ed-muted)">Only docs with no existing analysis row are shown.</div>
    </div>

    <div class="panel-section" id="mode-new-panel" style="display:none">
      <div class="panel-label">New documentations row <span class="badge">Step 2b</span></div>
      <div class="field-row">
        <label class="field-label">Name <span class="req">*</span></label>
        <input class="field-input" id="new-doc-name" type="text" placeholder="e.g. The Iron Coast Chronicles" oninput="validateForm()">
      </div>
      <div class="field-row">
        <label class="field-label">Category</label>
        <select class="field-select" id="new-doc-cat"><option value="">— none —</option></select>
      </div>
      <div class="field-row">
        <label class="field-label">Short Description</label>
        <input class="field-input" id="new-doc-desc-short" type="text" placeholder="One-line teaser">
      </div>
      <div class="field-row">
        <label class="field-label">Full Description</label>
        <textarea class="field-textarea" id="new-doc-desc" rows="3" placeholder="Longer editorial description…"></textarea>
      </div>
      <div class="field-row">
        <label class="field-label">Target Collection</label>
        <input class="field-input" id="new-doc-collection" type="text" value="sage_lore_entities_draft">
      </div>
    </div>

    <div class="panel-section" id="selection-summary" style="display:none">
      <div class="panel-label">Selected</div>
      <div id="selection-info" style="font-size:12px;line-height:1.7"></div>
    </div>

  </div><!-- /left-panel -->

  <div id="right-panel">
    <div id="right-scroll">

      <div id="success-banner"></div>

      <!-- PRESET -->
      <div class="form-section">
        <div class="form-section-head">
          <div class="form-section-icon">🧱</div>
          <div class="form-section-title">JSON Structure Preset</div>
          <div class="form-section-sub">Shape of the blank skeleton written into showrunner_analysis</div>
        </div>
        <div class="form-section-body">
          <div class="preset-pills">
            <div class="preset-pill active" onclick="selectPreset('full',this)">Full (Default)</div>
            <div class="preset-pill" onclick="selectPreset('full_expanded',this)">Full (Expanded)</div>
            <div class="preset-pill" onclick="selectPreset('episode_focused',this)">Episode Focused</div>
            <div class="preset-pill" onclick="selectPreset('world_focused',this)">World Focused</div>
            <div class="preset-pill" onclick="selectPreset('blank',this)">Bare Minimum</div>
          </div>
          <div class="json-preview-wrap">
            <div class="json-preview-head">
              <span>showrunner_analysis skeleton preview</span>
              <div style="display:flex;align-items:center;gap:10px">
                <span id="skeleton-size" style="color:var(--ed-muted)">—</span>
                <button id="copy-skeleton-btn" onclick="copySkeleton()" style="background:transparent;border:1px solid var(--ed-border);color:var(--ed-muted);font-family:var(--ed-mono);font-size:10px;padding:2px 10px;border-radius:3px;cursor:pointer;transition:all .15s">⎘ Copy</button>
              </div>
            </div>
            <div class="json-preview-body" id="skeleton-preview">Loading…</div>
          </div>
        </div>
      </div>

      <!-- NARRATIVE ENGINE SEED -->
      <div class="form-section">
        <div class="form-section-head">
          <div class="form-section-icon">⚙️</div>
          <div class="form-section-title">Narrative Engine Seed</div>
          <div class="form-section-sub">Optional — written into narrative_engine inside showrunner_analysis</div>
        </div>
        <div class="form-section-body">
          <div class="two-col">
            <div class="field-row">
              <label class="field-label">Core Conflict</label>
              <textarea class="field-textarea" id="narr-core" rows="3" placeholder="The central dramatic tension…"></textarea>
            </div>
            <div class="field-row">
              <label class="field-label">Central Metaphor</label>
              <textarea class="field-textarea" id="narr-meta" rows="3" placeholder="The story is ultimately about…"></textarea>
            </div>
          </div>
          <div class="field-row">
            <label class="field-label">Philosophical Stakes</label>
            <textarea class="field-textarea" id="narr-stakes" rows="2" placeholder="What worldview or truth is being tested?"></textarea>
          </div>
        </div>
      </div>

      <!-- PRODUCTION NOTES -->
      <div class="form-section">
        <div class="form-section-head">
          <div class="form-section-icon">🎙️</div>
          <div class="form-section-title">Production Notes</div>
          <div class="form-section-sub">Saved as production_notes — renders in Curator's Insight view</div>
        </div>
        <div class="form-section-body">
          <textarea class="field-textarea" id="prod-notes" rows="4"
            placeholder="Visual register, tone, cinematic references, era, palette…"></textarea>
        </div>
      </div>

      <!-- SERIES BIBLE -->
      <div class="form-section">
        <div class="form-section-head">
          <div class="form-section-icon">📖</div>
          <div class="form-section-title">Series Bible</div>
          <div class="form-section-sub">Saved to showrunner_analysis.series_bible AND the series_bible column</div>
        </div>
        <div class="form-section-body">
          <textarea class="field-textarea" id="series-bible" rows="7"
            placeholder="=== Narrative Architecture ===&#10;&#10;Arc, world rules, motifs, tone guide…&#10;Renders in Curator's Insight."></textarea>
        </div>
      </div>

      <!-- THEMATICS -->
      <div class="form-section">
        <div class="form-section-head">
          <div class="form-section-icon">🌡️</div>
          <div class="form-section-title">Thematics</div>
          <div class="form-section-sub">Written into the thematics column (themes + mood)</div>
        </div>
        <div class="form-section-body">
          <div class="two-col">
            <div class="field-row">
              <label class="field-label">Themes <small style="color:var(--ed-muted);text-transform:none">(comma-separated)</small></label>
              <input class="field-input" id="themes" type="text" placeholder="power, loss, identity, empire…">
            </div>
            <div class="field-row">
              <label class="field-label">Mood / Tone</label>
              <input class="field-input" id="mood" type="text" placeholder="dark-hopeful, gothic noir…">
            </div>
          </div>
        </div>
      </div>

      <!-- ADVANCED -->
      <div class="form-section">
        <div class="form-section-head">
          <div class="form-section-icon">🔬</div>
          <div class="form-section-title">Advanced — Full JSON Override</div>
          <div class="form-section-sub">Paste a complete showrunner_analysis blob, e.g. migrated from skeletjson.php</div>
        </div>
        <div class="form-section-body">
          <button class="adv-toggle" onclick="toggleAdv()">▸ Show JSON override field</button>
          <div id="adv-section">
            <div class="field-row">
              <label class="field-label">Custom showrunner_analysis JSON</label>
              <div class="field-hint" style="margin-bottom:6px">
                If valid JSON is pasted here it replaces the preset skeleton entirely.
                Leave blank to use the preset above.
              </div>
              <textarea class="field-textarea" id="custom-json" rows="10"
                style="font-size:11px;min-height:180px"
                placeholder='{ "visual_keywords": [], "episode_concepts": [], ... }'
                oninput="validateCustomJson()"></textarea>
              <div id="custom-json-status" class="field-hint" style="margin-top:4px"></div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /right-scroll -->
  </div><!-- /right-panel -->

</div><!-- /creator-shell -->

<div id="toast-bar"></div>

<script>
'use strict';
let currentMode='existing', selectedDocId=0, selectedPreset='full', orphanTimer=null;

document.addEventListener('DOMContentLoaded', async () => {
    await loadCategories();
    loadOrphans();
    loadSkeletonPreview('full');
});

let _tt = null;
function toast(msg, dur=2800) {
    const el = document.getElementById('toast-bar');
    el.textContent = msg; el.classList.add('visible');
    clearTimeout(_tt); _tt = setTimeout(() => el.classList.remove('visible'), dur);
}
function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function loadCategories() {
    try {
        const j = await (await fetch('?api_action=get_categories')).json();
        if (j.status !== 'success') return;
        ['orphan-cat-filter','new-doc-cat'].forEach(id => {
            const sel = document.getElementById(id); if (!sel) return;
            j.data.forEach(c => { sel.innerHTML += `<option value="${esc(c.id)}">${esc(c.name)}</option>`; });
        });
    } catch(e) { console.error(e); }
}

function setMode(mode) {
    currentMode = mode;
    document.getElementById('tab-existing').classList.toggle('active', mode === 'existing');
    document.getElementById('tab-new').classList.toggle('active',      mode === 'new_doc');
    document.getElementById('mode-existing-panel').style.display = mode === 'existing' ? 'block' : 'none';
    document.getElementById('mode-new-panel').style.display      = mode === 'new_doc'  ? 'block' : 'none';
    document.getElementById('selection-summary').style.display   = (mode === 'existing' && selectedDocId) ? 'block' : 'none';
    validateForm();
}

function debounceOrphan() { clearTimeout(orphanTimer); orphanTimer = setTimeout(loadOrphans, 280); }

async function loadOrphans() {
    const search = document.getElementById('orphan-search').value.trim();
    const catId  = document.getElementById('orphan-cat-filter').value;
    const listEl = document.getElementById('orphan-list');
    listEl.innerHTML = '<div class="orphan-empty">Loading…</div>';
    try {
        const j = await (await fetch(`?api_action=get_orphan_docs&search=${encodeURIComponent(search)}&category_id=${catId}`)).json();
        if (!j || j.status !== 'success') {
            listEl.innerHTML = `<div class="orphan-empty">Error: ${esc(j?.message||'load failed')}</div>`; return;
        }
        const docs = j.data || [];
        if (!docs.length) { listEl.innerHTML = '<div class="orphan-empty">No docs found without an analysis row.</div>'; return; }
        listEl.innerHTML = '';
        docs.forEach(d => {
            const el = document.createElement('div');
            el.className = 'orphan-item'; el.dataset.id = d.id;
            el.innerHTML = `<div class="orphan-name">${esc(d.name)}</div><div class="orphan-cat">${esc(d.cat_name||'—')} · #${d.id}</div>`;
            el.onclick = () => selectOrphan(d, el);
            listEl.appendChild(el);
        });
    } catch(e) { listEl.innerHTML = '<div class="orphan-empty">Network error</div>'; }
}

function selectOrphan(doc, el) {
    document.querySelectorAll('.orphan-item').forEach(i => i.classList.remove('selected'));
    el.classList.add('selected');
    selectedDocId = parseInt(doc.id, 10);
    document.getElementById('selection-info').innerHTML = `
        <div style="color:var(--ed-purple);font-weight:700;margin-bottom:4px">${esc(doc.name)}</div>
        <div style="color:var(--ed-muted)">doc_id: <span style="color:var(--ed-text)">${doc.id}</span></div>
        <div style="color:var(--ed-muted)">category: <span style="color:var(--ed-text)">${esc(doc.cat_name||'—')}</span></div>
        ${doc.desc_short ? `<div style="color:var(--ed-muted);font-style:italic;margin-top:4px">${esc(doc.desc_short)}</div>` : ''}
    `;
    document.getElementById('selection-summary').style.display = 'block';
    validateForm();
}

async function copySkeleton() {
    const text = document.getElementById('skeleton-preview').textContent;
    if (!text || text === 'Loading…' || text === 'Error') { toast('Nothing to copy yet'); return; }
    const btn = document.getElementById('copy-skeleton-btn');
    try {
        await navigator.clipboard.writeText(text);
        btn.textContent = '✓ Copied';
        btn.style.color = 'var(--ed-green)';
        btn.style.borderColor = 'var(--ed-green)';
        toast('✓ Skeleton copied to clipboard', 2000);
    } catch(e) {
        // fallback for older browsers
        const ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select(); document.execCommand('copy');
        document.body.removeChild(ta);
        btn.textContent = '✓ Copied';
        btn.style.color = 'var(--ed-green)';
        toast('✓ Skeleton copied to clipboard', 2000);
    }
    setTimeout(() => {
        btn.textContent = '⎘ Copy';
        btn.style.color = 'var(--ed-muted)';
        btn.style.borderColor = 'var(--ed-border)';
    }, 2500);
}

function selectPreset(preset, el) {
    document.querySelectorAll('.preset-pill').forEach(p => p.classList.remove('active'));
    el.classList.add('active'); selectedPreset = preset; loadSkeletonPreview(preset);
}

async function loadSkeletonPreview(preset) {
    const previewEl = document.getElementById('skeleton-preview');
    const sizeEl    = document.getElementById('skeleton-size');
    previewEl.textContent = 'Loading…';
    try {
        const j = await (await fetch(`?api_action=preview_skeleton&preset=${preset}`)).json();
        if (!j || j.status !== 'success') { previewEl.textContent = 'Error'; return; }
        const pretty = JSON.stringify(j.showrunner_analysis, null, 2);
        previewEl.textContent = pretty;
        sizeEl.textContent = (pretty.length / 1024).toFixed(1) + ' KB';
    } catch(e) { previewEl.textContent = 'Error'; }
}

function toggleAdv() {
    const sec = document.getElementById('adv-section');
    const btn = document.querySelector('.adv-toggle');
    sec.classList.toggle('visible');
    btn.textContent = sec.classList.contains('visible') ? '▾ Hide JSON override field' : '▸ Show JSON override field';
}

function validateCustomJson() {
    const ta  = document.getElementById('custom-json');
    const sta = document.getElementById('custom-json-status');
    const val = ta.value.trim();
    if (!val) { sta.textContent = ''; return; }
    try {
        const p = JSON.parse(val);
        sta.textContent = `✓ Valid JSON — ${Object.keys(p).length} top-level key(s)`;
        sta.style.color = 'var(--ed-green)';
    } catch(e) {
        sta.textContent = '✗ Invalid JSON: ' + e.message;
        sta.style.color = 'var(--ed-red)';
    }
}

function validateForm() {
    const btn = document.getElementById('create-btn');
    btn.disabled = currentMode === 'existing'
        ? selectedDocId <= 0
        : document.getElementById('new-doc-name').value.trim() === '';
}
document.getElementById('new-doc-name').addEventListener('input', validateForm);

async function submitCreate() {
    const btn = document.getElementById('create-btn');
    const customRaw = document.getElementById('custom-json').value.trim();
    let customJson = null;
    if (customRaw) {
        try { customJson = JSON.parse(customRaw); }
        catch(e) { toast('❌ Fix the custom JSON first'); return; }
    }
    btn.disabled = true;
    btn.innerHTML = '<span class="spin"></span>Creating…';

    const body = {
        mode:               currentMode,
        preset:             selectedPreset,
        doc_id:             selectedDocId,
        doc_name:           document.getElementById('new-doc-name').value.trim(),
        doc_desc:           document.getElementById('new-doc-desc').value.trim(),
        doc_desc_short:     document.getElementById('new-doc-desc-short').value.trim(),
        category_id:        document.getElementById('new-doc-cat').value,
        target_collection:  document.getElementById('new-doc-collection')?.value?.trim() || 'sage_lore_entities_draft',
        series_bible:       document.getElementById('series-bible').value.trim(),
        production_notes:   document.getElementById('prod-notes').value.trim(),
        narrative_core:     document.getElementById('narr-core').value.trim(),
        narrative_metaphor: document.getElementById('narr-meta').value.trim(),
        narrative_stakes:   document.getElementById('narr-stakes').value.trim(),
        themes:             document.getElementById('themes').value.trim(),
        mood:               document.getElementById('mood').value.trim(),
        custom_showrunner:  customJson,
    };

    try {
        const data = await (await fetch('?api_action=create_analysis', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(body),
        })).json();
        if (data.status !== 'success') {
            toast('❌ ' + (data.message || 'Failed'));
        } else {
            showSuccess(data);
        }
    } catch(e) {
        toast('❌ Network error'); console.error(e);
    } finally {
        btn.disabled = false;
        btn.textContent = '✦ Create Row';
        validateForm();
    }
}

function showSuccess(data) {
    const banner = document.getElementById('success-banner');
    banner.classList.add('visible');
    banner.innerHTML = `
        <div style="font-size:15px;font-weight:700;margin-bottom:8px">✓ Created successfully</div>
        <div><strong>md_doc_analysis</strong> #${data.analysis_id} linked to <strong>documentations</strong> #${data.doc_id}</div>
        <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:14px">
            <a href="view_curated_docs.php?doc_id=${data.doc_id}" target="_blank">📜 Open in Story Bible</a>
            <a href="view_showrunner_editor.php?doc_id=${data.doc_id}" target="_blank">✏️ Open in Editor</a>
            <a href="api_lore.php?doc_id=${data.doc_id}&mode=story" target="_blank">🔌 Lore API</a>
        </div>
        <div style="margin-top:10px;font-size:11px;opacity:.7">
            The row is live. Use the Editor to fill in JSON, or run the curator pipeline when source content is ready.
        </div>
    `;
    banner.scrollIntoView({ behavior: 'smooth' });
    toast('✓ ' + data.message, 4000);
    if (currentMode === 'existing') {
        const el = document.querySelector(`.orphan-item[data-id="${data.doc_id}"]`);
        if (el) el.remove();
        selectedDocId = 0;
        document.getElementById('selection-summary').style.display = 'none';
    }
}
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout(
    $content,
    $pageTitle,
    $spw->getProjectPath() . '/templates/gallery.php'
);
