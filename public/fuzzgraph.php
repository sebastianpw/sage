<?php
// public/fuzzgraph.php
// ─────────────────────────────────────────────────────────────────────────────
// FUZZ GRAPH — Single-Candidate Implicit Graph Visualizer
// Renders the neighbourhood of ONE resolved/promoted fuzz candidate:
//   • Centre node  : the candidate itself
//   • Alias nodes  : fuzz_candidate_aliases
//   • KG node      : kg_nodes link (if resolved)
//   • Fuzz links   : fuzz_links to neighbouring candidates
//   • Source nodes : distinct source_table+entity combos from fuzz_mentions
//
// Entry points:
//   /fuzzgraph.php?id=123                 — load candidate #123
//   /fuzzgraph.php?entity=sketches&id=123 — load sketch #123
//   /fuzzgraph.php                        — shows a search/picker
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }

$candidateId = (int)($_GET['id'] ?? 0);
$entityType  = $_GET['entity'] ?? null;

$candidate = null;
$graphData  = ['nodes' => [], 'edges' => []];

if ($candidateId > 0) {
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

    if ($entityType === 'sketches') {
        $stmt = $pdo->prepare("SELECT id, name FROM sketches WHERE id = :id");
        $stmt->execute(['id' => $candidateId]);
        $sketch = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sketch) {
            $centreId = 'src_sketches_' . $sketch['id'];
            $label = $sketch['name'] ?: 'Sketch #' . $sketch['id'];
            $candidate = ['id' => $sketch['id'], 'label' => $label, 'is_entity' => true, 'table' => 'sketches'];
            
            $addNode($centreId, $label, 'source_entity', [
                'source_table' => 'sketches',
                'source_row_id' => $sketch['id'],
                'expanded' => true
            ]);

            $cStmt = $pdo->prepare("
                SELECT c.id, c.label, c.status, c.concept_type, c.confidence, COUNT(m.id) as cnt
                FROM fuzz_mentions m
                JOIN fuzz_candidates c ON c.id = m.candidate_id
                WHERE m.source_table IN ('sketches','sketch_analysis','sketch_lore_history','sketch_ingredients') 
                  AND m.source_row_id = :id
                GROUP BY c.id
                LIMIT 60
            ");
            $cStmt->execute(['id' => $candidateId]);
            foreach($cStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $cid = 'cand_' . $c['id'];
                $addNode($cid, $c['label'], 'linked_candidate', [
                    'db_id' => $c['id'], 'status' => $c['status'], 'concept_type' => $c['concept_type'], 'confidence' => $c['confidence']
                ]);
                $addEdge($centreId, $cid, 'mentions', min(1.0, $c['cnt']/10));
            }
            $graphData = ['nodes' => $nodes, 'edges' => $edges];
        }
    } elseif ($entityType === 'kg_nodes') {
        $stmt = $pdo->prepare("SELECT id, name, node_type FROM kg_nodes WHERE id = :id");
        $stmt->execute(['id' => $candidateId]);
        $kg = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($kg) {
            $centreId = 'kg_' . $kg['id'];
            $label = 'KG: ' . $kg['name'];
            $candidate = ['id' => $kg['id'], 'label' => $label, 'is_entity' => true, 'table' => 'kg_nodes'];
            
            $addNode($centreId, $kg['name'], 'kg_node', [
                'db_id' => $kg['id'], 'kg_node_type' => $kg['node_type'], 'expanded' => true
            ]);

            $cStmt = $pdo->prepare("SELECT id, label, status, concept_type, confidence FROM fuzz_candidates WHERE kg_node_id = :id LIMIT 60");
            $cStmt->execute(['id' => $candidateId]);
            foreach($cStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $cid = 'cand_' . $c['id'];
                $addNode($cid, $c['label'], 'linked_candidate', [
                    'db_id' => $c['id'], 'status' => $c['status'], 'concept_type' => $c['concept_type'], 'confidence' => $c['confidence']
                ]);
                $addEdge($centreId, $cid, 'resolved_to', 1.0);
            }
            $graphData = ['nodes' => $nodes, 'edges' => $edges];
        }
    } else {
        // Core candidate graph
        $stmt = $pdo->prepare("SELECT * FROM fuzz_candidates WHERE id = :id");
        $stmt->execute(['id' => $candidateId]);
        $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($candidate) {
            $centreId = 'cand_' . $candidate['id'];
            $addNode($centreId, $candidate['label'], 'centre', [
                'status'       => $candidate['status'],
                'concept_type' => $candidate['concept_type'] ?? '',
                'confidence'   => (int)($candidate['confidence'] ?? 50),
                'db_id'        => $candidate['id'],
                'expanded'     => true
            ]);

            $aStmt = $pdo->prepare("SELECT alias FROM fuzz_candidate_aliases WHERE candidate_id = :id LIMIT 30");
            $aStmt->execute(['id' => $candidateId]);
            foreach ($aStmt->fetchAll(PDO::FETCH_COLUMN) as $alias) {
                $aid = 'alias_' . md5($alias);
                $addNode($aid, $alias, 'alias');
                $addEdge($centreId, $aid, 'alias');
            }

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
            $graphData = ['nodes' => $nodes, 'edges' => $edges];
        }
    }
}

$jsonGraph     = json_encode($graphData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$jsonCandidate = json_encode($candidate ?: null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=0.85, viewport-fit=cover">
<title>Fuzz Graph<?= $candidate ? ' — ' . htmlspecialchars($candidate['label']) : '' ?></title>
<script>
(function(){
    try {
        var t = localStorage.getItem('spw_theme');
        if (t === 'dark')  document.documentElement.setAttribute('data-theme', 'dark');
        else if (t === 'light') document.documentElement.setAttribute('data-theme', 'light');
    } catch(e) {}
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Bebas+Neue&family=Barlow+Condensed:wght@300;400;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* ═══════════════════════════════════════════════════════
   FUZZ GRAPH — Signal-Intelligence Dark
═══════════════════════════════════════════════════════ */
:root {
    --bg:           #04060c;
    --surface:      #080b13;
    --card:         #0b0f1c;
    --border:       #141c2e;
    --border-glow:  #1b2640;
    --text:         #a8bcd4;
    --text-dim:     #354a63;
    --text-bright:  #d8eeff;
    --cyan:         #00d4ff;
    --cyan-dim:     rgba(0,212,255,0.07);
    --cyan-mid:     rgba(0,212,255,0.18);
    --cyan-glow:    rgba(0,212,255,0.5);
    --orange:       #ff6b2b;
    --orange-dim:   rgba(255,107,43,0.09);
    --green:        #00e5a0;
    --green-dim:    rgba(0,229,160,0.08);
    --red:          #ff3d5a;
    --red-dim:      rgba(255,61,90,0.09);
    --purple:       #a855f7;
    --purple-dim:   rgba(168,85,247,0.09);
    --yellow:       #f5c400;
    --yellow-dim:   rgba(245,196,0,0.09);
    --mono: 'DM Mono', monospace;
    --head: 'Bebas Neue', 'Barlow Condensed', sans-serif;
    --sans: 'Barlow Condensed', system-ui, sans-serif;
    --radius: 5px;
    --header-bg: rgba(8,11,19,0.96);
    --panel-bg:  rgba(8,11,19,0.94);
}

/* ── LIGHT THEME ── */
:root[data-theme="light"], html[data-theme="light"] {
    --bg:           #f0f4f8;
    --surface:      #ffffff;
    --card:         #ffffff;
    --border:       #d0d8e4;
    --border-glow:  #aab8cc;
    --text:         #1a2533;
    --text-dim:     #7a8fa8;
    --text-bright:  #0d1824;
    --cyan:         #0094b3;
    --cyan-dim:     rgba(0,148,179,0.09);
    --cyan-mid:     rgba(0,148,179,0.2);
    --cyan-glow:    rgba(0,148,179,0.3);
    --orange:       #c94a00;
    --orange-dim:   rgba(201,74,0,0.08);
    --green:        #007a50;
    --green-dim:    rgba(0,122,80,0.09);
    --red:          #c0162f;
    --red-dim:      rgba(192,22,47,0.08);
    --purple:       #7c3aed;
    --purple-dim:   rgba(124,58,237,0.09);
    --yellow:       #a87800;
    --yellow-dim:   rgba(168,120,0,0.09);
    --header-bg:    rgba(240,244,248,0.97);
    --panel-bg:     rgba(255,255,255,0.97);
}

/* ── DARK THEME (explicit fallback) ── */
:root[data-theme="dark"], html[data-theme="dark"],
:root:not([data-theme="light"]) {
    --bg:           #04060c;
    --surface:      #080b13;
    --card:         #0b0f1c;
    --border:       #141c2e;
    --border-glow:  #1b2640;
    --text:         #a8bcd4;
    --text-dim:     #354a63;
    --text-bright:  #d8eeff;
    --cyan:         #00d4ff;
    --cyan-dim:     rgba(0,212,255,0.07);
    --cyan-mid:     rgba(0,212,255,0.18);
    --cyan-glow:    rgba(0,212,255,0.5);
    --orange:       #ff6b2b;
    --orange-dim:   rgba(255,107,43,0.09);
    --green:        #00e5a0;
    --green-dim:    rgba(0,229,160,0.08);
    --red:          #ff3d5a;
    --red-dim:      rgba(255,61,90,0.09);
    --purple:       #a855f7;
    --purple-dim:   rgba(168,85,247,0.09);
    --yellow:       #f5c400;
    --yellow-dim:   rgba(245,196,0,0.09);
    --header-bg:    rgba(8,11,19,0.96);
    --panel-bg:     rgba(8,11,19,0.94);
}

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html,body { height:100%; background:var(--bg); color:var(--text); font-family:var(--sans); overflow:hidden; -webkit-font-smoothing:antialiased; }

/* scanline — dark only */
html:not([data-theme="light"]) body::before {
    content:''; position:fixed; inset:0; pointer-events:none; z-index:1000;
    background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,212,255,0.01) 2px,rgba(0,212,255,0.01) 4px);
}

::-webkit-scrollbar { width:4px; height:4px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:var(--border-glow); border-radius:4px; }

/* ── LAYOUT ── */
.fg-layout { display:flex; flex-direction:column; height:100vh; }

/* ── HEADER ── */
.fg-header {
    height:52px; background:var(--header-bg); border-bottom:1px solid var(--border);
    display:flex; align-items:center; padding:0 16px; gap:10px; flex-shrink:0; z-index:200;
    backdrop-filter:blur(12px);
}
.fg-logo { display:flex; align-items:center; gap:9px; font-family:var(--head); font-size:1.35rem; letter-spacing:4px; color:var(--text-bright); text-decoration:none; }
.fg-logo-icon {
    width:30px; height:30px; background:var(--purple-dim); border:1px solid rgba(168,85,247,0.3);
    border-radius:var(--radius); display:flex; align-items:center; justify-content:center;
    font-size:14px; color:var(--purple); box-shadow:0 0 12px rgba(168,85,247,0.4);
}
.fg-candidate-label {
    font-family:var(--head); font-size:1.1rem; letter-spacing:2px; color:var(--text-dim);
    max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.fg-candidate-label span { color:var(--cyan); }
.fg-header-right { display:flex; align-items:center; gap:8px; margin-left:auto; }

/* ── GRAPH CANVAS ── */
.fg-main { flex:1; position:relative; overflow:hidden; }
#graph-container { width:100%; height:100%; outline:none; }

/* ── FLOATING PANELS ── */
.fg-panel {
    position:absolute; background:var(--panel-bg); backdrop-filter:blur(14px);
    border:1px solid var(--border-glow); border-radius:8px;
    box-shadow:0 8px 32px rgba(0,0,0,0.6); z-index:100;
    display:flex; flex-direction:column; overflow:hidden; min-width:200px;
}
.fg-panel-head {
    display:flex; align-items:center; justify-content:space-between; padding:10px 14px;
    border-bottom:1px solid var(--border); background:var(--surface); cursor:move;
    touch-action:none; user-select:none; gap:8px;
}
.fg-panel-head h3 { font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:2px; margin:0; pointer-events:none; flex:1; }
.fg-panel-body { padding:12px; display:flex; flex-direction:column; gap:8px; }

/* Controls panel */
#panel-controls { top:10px; left:10px; width:210px; }
/* Node detail panel */
#panel-node { top:10px; right:10px; width:280px; display:none; }

/* ── BUTTONS ── */
.btn-fg {
    padding:7px 12px; border-radius:var(--radius); border:1px solid var(--border-glow);
    background:transparent; color:var(--text-dim); font-family:var(--mono); font-size:0.68rem;
    cursor:pointer; text-transform:uppercase; letter-spacing:1px; transition:all 0.15s;
    display:flex; align-items:center; gap:6px; width:100%; justify-content:center;
    -webkit-tap-highlight-color:transparent; text-decoration:none;
}
.btn-fg:hover { border-color:var(--cyan); color:var(--cyan); background:var(--cyan-dim); box-shadow:0 0 8px var(--cyan-glow); }
.btn-fg.active { border-color:var(--cyan); color:var(--cyan); background:var(--cyan-dim); }
.btn-fg.purple { border-color:rgba(168,85,247,0.4); color:var(--purple); }
.btn-fg.purple:hover { border-color:var(--purple); background:var(--purple-dim); box-shadow:0 0 8px rgba(168,85,247,0.4); }
.btn-fg.green { border-color:rgba(0,229,160,0.35); color:var(--green); }
.btn-fg.green:hover { border-color:var(--green); background:var(--green-dim); box-shadow:0 0 8px rgba(0,229,160,0.3); }
.btn-icon { width:28px; height:28px; border:1px solid var(--border-glow); background:transparent; border-radius:var(--radius); color:var(--text-dim); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:13px; transition:all 0.15s; flex-shrink:0; -webkit-tap-highlight-color:transparent; text-decoration:none; }
.btn-icon:hover { border-color:var(--cyan); color:var(--cyan); background:var(--cyan-dim); }
.collapse-btn { background:none; border:none; color:var(--text-dim); cursor:pointer; font-size:12px; padding:2px; border-radius:3px; line-height:1; }
.collapse-btn:hover { color:var(--text); }

/* ── SEARCH ── */
.fg-search-wrap { position:relative; display:flex; gap:0; }
.fg-search-icon { position:absolute; right:9px; top:50%; transform:translateY(-50%); color:var(--purple); font-size:15px; pointer-events:none; }
.fg-search { width:100%; padding:7px 8px 7px 10px; background:var(--bg); border:1px solid var(--border-glow); color:var(--text-bright); font-family:var(--mono); font-size:0.75rem; }
.fg-search:focus { outline:none; border-color:var(--purple); box-shadow:0 0 8px rgba(168,85,247,0.25); z-index:2; }

/* ── STAT CHIPS ── */
.stat-row { display:flex; gap:6px; flex-wrap:wrap; }
.stat-chip { font-family:var(--mono); font-size:0.62rem; color:var(--text-dim); padding:3px 7px; background:var(--card); border:1px solid var(--border); border-radius:3px; text-transform:uppercase; letter-spacing:1px; }
.stat-chip span { color:var(--cyan); font-weight:500; }

/* ── NODE DETAIL PANEL ── */
.nd-name { font-family:var(--head); font-size:1.5rem; letter-spacing:2px; color:var(--text-bright); line-height:1.1; margin-bottom:6px; word-break:break-word; }
.nd-type-badge { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:3px; font-family:var(--mono); font-size:0.62rem; text-transform:uppercase; letter-spacing:1.5px; border:1px solid; margin-bottom:10px; }
.nd-meta { display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
.nd-meta-row { display:flex; align-items:center; justify-content:space-between; font-family:var(--mono); font-size:0.68rem; }
.nd-meta-label { color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; }
.nd-meta-val { color:var(--text-bright); text-align:right;}
.nd-divider { height:1px; background:var(--border); margin:8px 0; }
.nd-actions { display:flex; flex-direction:column; gap:6px; }

/* Conf bar */
.conf-bar-bg { flex:1; height:3px; background:var(--border); border-radius:2px; overflow:hidden; }
.conf-bar-fill { height:100%; border-radius:2px; transition:width 0.3s; box-shadow:0 0 6px var(--cyan-glow); }

/* ── LEGEND ── */
.legend { display:flex; flex-direction:column; gap:5px; }
.legend-item { display:flex; align-items:center; gap:7px; font-family:var(--mono); font-size:0.62rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; }
.legend-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }

/* ── PICKER OVERLAY (no-ID state) ── */
.fg-picker-overlay {
    position:fixed; inset:0; background:var(--bg);
    display:flex; align-items:center; justify-content:center; z-index:500;
    flex-direction:column; gap:24px; padding:20px;
}
.fg-picker-overlay.hidden { display:none; }
.fg-picker-box { width:100%; max-width:560px; background:var(--card); border:1px solid var(--border-glow); border-radius:10px; overflow:hidden; }
.fg-picker-head { padding:20px 24px 16px; border-bottom:1px solid var(--border); }
.fg-picker-title { font-family:var(--head); font-size:2rem; letter-spacing:4px; color:var(--text-bright); display:flex; align-items:center; gap:12px; }
.fg-picker-title i { color:var(--purple); font-size:1.6rem; }
.fg-picker-sub { font-family:var(--mono); font-size:0.72rem; color:var(--text-dim); margin-top:6px; text-transform:uppercase; letter-spacing:1px; }
.fg-picker-body { padding:20px 24px; }
.fg-picker-search-wrap { position:relative; margin-bottom:14px; display:flex; gap:0; }
.fg-picker-results { max-height:300px; overflow-y:auto; border:1px solid var(--border); border-radius:var(--radius); background:var(--bg); }
.fg-picker-item { padding:12px 14px; border-bottom:1px solid var(--border); cursor:pointer; display:flex; align-items:center; gap:10px; transition:background 0.12s; text-decoration:none; }
.fg-picker-item:last-child { border-bottom:none; }
.fg-picker-item:hover { background:var(--card); }
.fg-picker-item-name { font-family:var(--head); font-size:1.3rem; letter-spacing:1px; color:var(--text-bright); flex:1; line-height:1.1; }
.fg-picker-item-meta { font-family:var(--mono); font-size:0.62rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px; margin-top:4px;}
.fg-picker-empty { padding:30px; text-align:center; font-family:var(--mono); font-size:0.72rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:2px; }

/* ── TOAST ── */
.fg-toast { position:fixed; bottom:18px; right:18px; z-index:999; padding:10px 16px; background:var(--card); border:1px solid var(--border-glow); border-radius:var(--radius); font-family:var(--mono); font-size:0.72rem; color:var(--cyan); box-shadow:0 4px 20px rgba(0,0,0,0.5); display:none; text-transform:uppercase; letter-spacing:1px; }

/* ── NODE TYPE COLORS (for CSS vars) ── */
.badge-centre  { border-color:var(--purple); color:var(--purple); background:var(--purple-dim); }
.badge-alias   { border-color:var(--text-dim); color:var(--text-dim); background:transparent; }
.badge-kg      { border-color:var(--green); color:var(--green); background:var(--green-dim); }
.badge-linked  { border-color:var(--cyan); color:var(--cyan); background:var(--cyan-dim); }
.badge-source  { border-color:var(--orange); color:var(--orange); background:var(--orange-dim); }
</style>
</head>
<body>

<!-- ══════════════════════════════════════════ PICKER OVERLAY (no ID) -->
<div class="fg-picker-overlay <?= $candidateId ? 'hidden' : '' ?>" id="pickerOverlay">
    <div style="text-align:center;">
        <div style="font-family:var(--head); font-size:3rem; letter-spacing:8px; color:var(--text-bright); display:flex; align-items:center; justify-content:center; gap:14px;">
            <div style="width:44px; height:44px; background:var(--purple-dim); border:1px solid rgba(168,85,247,0.4); border-radius:6px; display:flex; align-items:center; justify-content:center; color:var(--purple); font-size:22px; box-shadow:0 0 18px rgba(168,85,247,0.4);">
                <i class="bi bi-diagram-3-fill"></i>
            </div>
            FUZZ GRAPH
        </div>
        <div style="font-family:var(--mono); font-size:0.72rem; color:var(--text-dim); margin-top:6px; text-transform:uppercase; letter-spacing:2px;">Implicit Graph Visualizer</div>
    </div>
    <div class="fg-picker-box">
        <div class="fg-picker-head">
            <div class="fg-picker-title"><i class="bi bi-search"></i> Select Entry Node</div>
            <div class="fg-picker-sub">Search for candidates, sketches, or kg nodes to visualize</div>
        </div>
        <div class="fg-picker-body">
            <div class="fg-picker-search-wrap">
                <select id="pickerSearchMode" class="fg-search" style="width:120px; border-right:none; border-radius:var(--radius) 0 0 var(--radius); font-weight:600; color:var(--purple); cursor:pointer;">
                    <option value="general">Candidates</option>
                    <option value="sketches">Sketches</option>
                    <option value="kg_nodes">KG Nodes</option>
                </select>
                <div style="position:relative; flex:1;">
                    <input type="text" class="fg-search" id="pickerSearch" placeholder="Search by ID or Name..." autocomplete="off" style="border-radius:0 var(--radius) var(--radius) 0; padding-right:30px;">
                    <i class="bi bi-search fg-search-icon"></i>
                </div>
            </div>
            <div class="fg-picker-results" id="pickerResults">
                <div class="fg-picker-empty">Type to search</div>
            </div>
        </div>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="/fuzz_forge.php" style="padding:8px 18px; border:1px solid var(--border-glow); border-radius:var(--radius); font-family:var(--mono); font-size:0.7rem; color:var(--text-dim); text-decoration:none; text-transform:uppercase; letter-spacing:1px; transition:all 0.15s;" onmouseover="this.style.borderColor='var(--cyan)'; this.style.color='var(--cyan)';" onmouseout="this.style.borderColor=''; this.style.color='';">
            <i class="bi bi-arrow-left"></i> Fuzz Forge
        </a>
    </div>
</div>

<!-- ══════════════════════════════════════════ MAIN UI -->
<div class="fg-layout">

    <!-- Header -->
    <header class="fg-header">
        <a href="/fuzzgraph.php" class="fg-logo">
            <div class="fg-logo-icon"><i class="bi bi-diagram-3-fill"></i></div>
            FUZZ GRAPH
        </a>
        <?php if ($candidate): ?>
        <div class="fg-candidate-label"><span>#<?= $candidate['id'] ?></span> <?= htmlspecialchars($candidate['label']) ?></div>
        <?php endif; ?>
        <div class="fg-header-right">
            <?php if ($candidate && empty($candidate['is_entity'])): ?>
            <button class="btn-icon" onclick="openLandingModal(<?= $candidate['id'] ?>, '<?= htmlspecialchars(addslashes($candidate['label'])) ?>')" title="View Landing Details"><i class="bi bi-file-earmark-text"></i></button>
            <a href="/fuzz_forge.php?candidate_id=<?= $candidate['id'] ?>" class="btn-icon" title="Open in Fuzz Forge"><i class="bi bi-collection"></i></a>
            <?php endif; ?>
            <a href="/fuzzgraph.php" class="btn-icon" title="New Search"><i class="bi bi-search"></i></a>
            <a href="/dashboard.php" class="btn-icon" title="Dashboard"><i class="bi bi-house"></i></a>
        </div>
    </header>

    <!-- Graph -->
    <div class="fg-main">
        <div id="graph-container"></div>

        <?php if ($candidate): ?>
        <!-- Controls Panel -->
        <div class="fg-panel" id="panel-controls">
            <div class="fg-panel-head" id="ctrl-drag-handle">
                <h3>Graph Controls</h3>
                <button class="collapse-btn" onclick="FG.togglePanel('panel-controls', this)"><i class="bi bi-dash"></i></button>
            </div>
            <div class="fg-panel-body" id="panel-controls-body">
                <button class="btn-fg" id="btn-layout" onclick="FG.toggleLayout()"><i class="bi bi-play-fill"></i> ForceAtlas2</button>
                <button class="btn-fg" onclick="FG.resetCamera()"><i class="bi bi-arrows-collapse"></i> Reset Camera</button>

                <div class="fg-search-wrap" style="position:relative;">
                    <input type="text" class="fg-search" id="graphSearch" placeholder="Search nodes…" autocomplete="off" style="border-radius:var(--radius); padding-right:30px;">
                    <i class="bi bi-search fg-search-icon"></i>
                </div>
                <div id="searchCount" style="font-family:var(--mono); font-size:0.65rem; color:var(--text-dim); min-height:14px;"></div>

                <div style="height:1px; background:var(--border);"></div>
                
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-family:var(--mono); font-size:0.68rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:1px;">Graph Hops</span>
                    <select id="graphHopsSelect" class="fg-search" style="width:auto; padding:2px 6px; border-radius:3px;" onchange="FG.changeHops(this.value)">
                        <option value="1">1 Hop</option>
                        <option value="2">2 Hops</option>
                    </select>
                </div>
                <div id="graphWarning" style="display:none; color:var(--red); font-family:var(--mono); font-size:0.65rem; margin-top:2px;"></div>

                <div class="stat-row">
                    <div class="stat-chip">Nodes <span id="statNodes">0</span></div>
                    <div class="stat-chip">Edges <span id="statEdges">0</span></div>
                </div>

                <div style="height:1px; background:var(--border);"></div>
                <div class="legend">
                    <div class="legend-item"><div class="legend-dot" style="background:#a855f7; box-shadow:0 0 6px rgba(168,85,247,0.6);"></div>Candidate (centre)</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#354a63;"></div>Alias</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#00e5a0; box-shadow:0 0 6px rgba(0,229,160,0.5);"></div>KG Node</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#00d4ff; box-shadow:0 0 6px rgba(0,212,255,0.4);"></div>Linked Candidate</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#ff6b2b;"></div>Source Entity</div>
                </div>
            </div>
        </div>

        <!-- Node Detail Panel -->
        <div class="fg-panel" id="panel-node">
            <div class="fg-panel-head" id="node-drag-handle">
                <h3>Node Detail</h3>
                <div style="display:flex; gap:4px;">
                    <button class="collapse-btn" onclick="FG.togglePanel('panel-node', this)"><i class="bi bi-dash"></i></button>
                    <button class="collapse-btn" onclick="FG.closeNodePanel()" title="Close"><i class="bi bi-x"></i></button>
                </div>
            </div>
            <div class="fg-panel-body" id="panel-node-body">
                <div class="nd-name" id="nd-name">—</div>
                <div class="nd-type-badge badge-centre" id="nd-type-badge">centre</div>
                <div class="nd-meta" id="nd-meta"></div>
                <div class="nd-divider"></div>
                <div class="nd-actions" id="nd-actions"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="fg-toast" id="fgToast"></div>

<!-- ── Connected Nodes List Modal ── -->
<div class="ef-overlay" id="fg-connections-modal" style="position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:8500;display:none;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(4px);">
    <div style="width:100%;max-width:520px;max-height:80dvh;background:var(--card);border:1px solid var(--border-glow);border-radius:8px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.8);">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:var(--surface);border-bottom:1px solid var(--border);">
            <span id="fg-conn-title" style="font-family:var(--head);font-size:1.2rem;color:var(--text-bright);letter-spacing:2px;text-transform:uppercase;">Connected Nodes</span>
            <button class="btn-icon" onclick="document.getElementById('fg-connections-modal').style.display='none';" style="width:28px;height:28px;font-size:16px;" title="Close"><i class="bi bi-x"></i></button>
        </div>
        <div id="fg-conn-list" style="overflow-y:auto; padding:0;" class="fg-picker-results">
            <!-- populated by JS -->
        </div>
    </div>
</div>

<!-- ── Landing Page Fullscreen Modal ── -->
<div class="ef-overlay" id="landingModalOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9000;display:none;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(4px);">
    <div style="width:100%;max-width:1200px;height:90dvh;background:var(--card);border:1px solid var(--border-glow);border-radius:8px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.8);">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:var(--surface);border-bottom:1px solid var(--border);">
            <span id="landingModalTitle" style="font-family:var(--head);font-size:1.2rem;color:var(--text-bright);letter-spacing:2px;text-transform:uppercase;">Landing Details</span>
            <div style="display:flex;gap:8px;">
                <a id="landingModalLink" href="#" target="_blank" class="btn-icon" title="Open in New Tab" style="width:28px;height:28px;font-size:12px;"><i class="bi bi-box-arrow-up-right"></i></a>
                <button class="btn-icon" onclick="document.getElementById('landingModalOverlay').style.display='none'; document.getElementById('landingModalIframe').src='about:blank';" style="width:28px;height:28px;font-size:16px;" title="Close"><i class="bi bi-x"></i></button>
            </div>
        </div>
        <iframe id="landingModalIframe" src="about:blank" style="flex:1;border:none;width:100%;"></iframe>
    </div>
</div>

<!-- ══════════════════════════════════════════ SCRIPTS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/graphology/0.25.4/graphology.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/graphology-library@0.7.1/dist/graphology-library.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sigma.js/2.4.0/sigma.min.js"></script>

<script>
// ── Picker search ──────────────────────────────────────────────
const pickerSearch  = document.getElementById('pickerSearch');
const pickerMode    = document.getElementById('pickerSearchMode');
const pickerResults = document.getElementById('pickerResults');
let _pickerTimer;

if (pickerSearch) {
    pickerSearch.addEventListener('input', () => {
        clearTimeout(_pickerTimer);
        _pickerTimer = setTimeout(runPickerSearch, 280);
    });
    pickerSearch.focus();
}
if (pickerMode) {
    pickerMode.addEventListener('change', () => {
        if (pickerSearch.value.trim().length > 0) runPickerSearch();
    });
}

async function runPickerSearch() {
    const q = (pickerSearch.value || '').trim();
    if (q.length < 1 && isNaN(q)) {
        pickerResults.innerHTML = '<div class="fg-picker-empty">Type to search</div>';
        return;
    }
    const mode = pickerMode.value;
    const res = await fetch('/api/fuzz_graph_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'search_entry', q, mode })
    });
    const data = await res.json();
    if (!data.ok || !data.data.length) {
        pickerResults.innerHTML = '<div class="fg-picker-empty">No results found</div>';
        return;
    }
    
    pickerResults.innerHTML = data.data.map(c => {
        let href = `/fuzzgraph.php?id=${c.id}`;
        let icon = '<i class="bi bi-diagram-3"></i>';
        if (c.type === 'sketches') {
            href = `/fuzzgraph.php?entity=sketches&id=${c.id}`;
            icon = '<i class="bi bi-palette"></i>';
        } else if (c.type === 'kg_nodes') {
            href = `/fuzzgraph.php?entity=kg_nodes&id=${c.id}`;
            icon = '<i class="bi bi-diagram-3-fill"></i>';
        }
        
        const stBadge = c.status ? `<span style="color:${statusBadgeColor(c.status)};">${escHtml(c.status)}</span> · ` : '';
        const tBadge = c.concept_type ? `${escHtml(c.concept_type)} · ` : '';

        return `<a href="${href}" class="fg-picker-item">
            <div style="font-size:18px; color:var(--text-dim); margin-right:4px;">${icon}</div>
            <div style="flex:1; min-width:0;">
                <div class="fg-picker-item-name">${escHtml(c.label)}</div>
                <div class="fg-picker-item-meta">
                    #${c.id} · ${tBadge}${stBadge}
                    <span style="color:var(--cyan);">${c.meta}</span>
                </div>
            </div>
            <i class="bi bi-arrow-right" style="color:var(--purple); font-size:14px;"></i>
        </a>`;
    }).join('');
}

function statusBadgeColor(s) {
    return { promoted:'#00d4ff', canonized:'#00e5a0', reviewed:'#f5c400', rejected:'#ff3d5a', deferred:'#354a63' }[s] || '#354a63';
}

// ── Graph bootstrap ───────────────────────────────────────────────────────────
const GRAPH_DATA = <?php echo $jsonGraph; ?>;
const CANDIDATE  = <?php echo $jsonCandidate; ?>;
const MAX_NODES  = 1000;

function escHtml(s) {
    if (!s && s !== 0) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

window.openLandingModal = function(id, label) {
    document.getElementById('landingModalTitle').textContent = "Landing: " + label;
    document.getElementById('landingModalLink').href = "/fuzz_forge_landing.php?id=" + id;
    document.getElementById('landingModalIframe').src = "/fuzz_forge_landing.php?id=" + id + "&embed=1";
    document.getElementById('landingModalOverlay').style.display = 'flex';
};

const NODE_COLORS = {
    centre:           '#a855f7',
    alias:            '#354a63',
    kg_node:          '#00e5a0',
    linked_candidate: '#00d4ff',
    source_entity:    '#ff6b2b',
};

const NODE_SIZES = {
    centre: 18, kg_node: 12, linked_candidate: 9, source_entity: 7, alias: 5,
};

const FG = (() => {
    'use strict';

    let graph, renderer;
    let isLayoutRunning = false;
    let fa2LoopId = null;
    let selectedNode = null;
    let hoveredNode  = null;
    let searchMatches = null;

    function init() {
        if (!GRAPH_DATA || !GRAPH_DATA.nodes || !GRAPH_DATA.nodes.length) return;

        graph = new graphology.UndirectedGraph();

        GRAPH_DATA.nodes.forEach(n => {
            graph.addNode(n.id, {
                label:    n.label,
                x:        (Math.random() - 0.5) * 10,
                y:        (Math.random() - 0.5) * 10,
                size:     NODE_SIZES[n.ntype] || 6,
                color:    NODE_COLORS[n.ntype] || '#888',
                ntype:    n.ntype,
                db_id:    n.db_id || null,
                status:   n.status || '',
                concept_type: n.concept_type || '',
                confidence:   n.confidence || 0,
                source_table: n.source_table || '',
                source_row_id: n.source_row_id || null,
                mention_count: n.mention_count || 0,
                kg_node_type: n.kg_node_type || '',
                expanded: n.expanded || false
            });
        });

        GRAPH_DATA.edges.forEach(e => {
            try {
                if (graph.hasNode(e.source) && graph.hasNode(e.target)) {
                    graph.addEdge(e.source, e.target, {
                        label: e.rel || '',
                        size:  1 + (e.weight || 0.5),
                        color: edgeColor(e.rel),
                    });
                }
            } catch(ex) {}
        });

        if (graph.order > 0) {
            graphologyLibrary.layoutForceAtlas2.assign(graph, {
                iterations: 150,
                settings: {
                    barnesHutOptimize: graph.order > 80,
                    gravity: 0.15,
                    scalingRatio: 10,
                    strongGravityMode: false
                }
            });
        }

        updateStats();

        renderer = new Sigma(graph, document.getElementById('graph-container'), {
            renderEdgeLabels: true,
            defaultEdgeType:  'line',
            allowInvalidContainer: true,
            labelColor:     { color: '#8ba0b8' },
            edgeLabelColor: { color: '#354a63' },
            edgeLabelSize:  8,
        });

        renderer.setSetting('nodeReducer', nodeReducer);
        renderer.setSetting('edgeReducer', edgeReducer);

        function getLabelColor() { return document.documentElement.getAttribute('data-theme') === 'light' ? '#1a2533' : '#8ba0b8'; }
        function getEdgeLabelColor() { return document.documentElement.getAttribute('data-theme') === 'light' ? '#7a8fa8' : '#354a63'; }
        renderer.setSetting('labelColor', { color: getLabelColor() });
        renderer.setSetting('edgeLabelColor', { color: getEdgeLabelColor() });

        new MutationObserver(() => {
            renderer.setSetting('labelColor', { color: getLabelColor() });
            renderer.setSetting('edgeLabelColor', { color: getEdgeLabelColor() });
            renderer.refresh();
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

        renderer.on('enterNode', ({node}) => { hoveredNode = node; renderer.refresh(); });
        renderer.on('leaveNode', () => { hoveredNode = null; renderer.refresh(); });

        let dragNode = null, dragStartX = 0, dragStartY = 0;
        const DRAG_THRESH = 6;

        renderer.on('downNode', e => {
            dragNode = e.node;
            const ev = (e.event && e.event.original) ? e.event.original : (e.event || {});
            dragStartX = (ev.touches && ev.touches.length > 0 ? ev.touches[0].clientX : ev.clientX) || 0;
            dragStartY = (ev.touches && ev.touches.length > 0 ? ev.touches[0].clientY : ev.clientY) || 0;
            renderer.getCamera().disable();
        });

        renderer.getMouseCaptor().on('mousemovebody', e => {
            if (!dragNode) return;
            const pos = renderer.viewportToGraph(e);
            graph.setNodeAttribute(dragNode, 'x', pos.x);
            graph.setNodeAttribute(dragNode, 'y', pos.y);
            e.preventSigmaDefault();
            e.original.preventDefault();
        });

        const endDrag = e => {
            if (!dragNode) return;
            const ev = (e.changedTouches && e.changedTouches.length > 0) ? e.changedTouches[0] : e;
            const cx = ev.clientX !== undefined ? ev.clientX : dragStartX;
            const cy = ev.clientY !== undefined ? ev.clientY : dragStartY;
            if (Math.hypot(cx - dragStartX, cy - dragStartY) < DRAG_THRESH) {
                openNodePanel(dragNode);
            }
            renderer.getCamera().enable();
            dragNode = null;
        };

        window.addEventListener('mouseup', endDrag);
        window.addEventListener('touchend', endDrag);

        renderer.on('clickStage', () => { selectedNode = null; closeNodePanel(); renderer.refresh(); });

        const searchEl = document.getElementById('graphSearch');
        if (searchEl) {
            searchEl.addEventListener('input', e => {
                const q = e.target.value.trim().toLowerCase();
                const countEl = document.getElementById('searchCount');
                if (!q) { searchMatches = null; countEl.textContent = ''; renderer.refresh(); return; }
                searchMatches = new Set();
                graph.forEachNode((node, attrs) => {
                    if (attrs.label && attrs.label.toLowerCase().includes(q)) searchMatches.add(node);
                });
                const n = searchMatches.size;
                countEl.textContent = n ? `${n} node${n > 1 ? 's' : ''} matched` : 'No matches';
                countEl.style.color = n ? 'var(--cyan)' : 'var(--red)';
                renderer.refresh();
            });
        }

        makeDraggable('panel-controls', 'ctrl-drag-handle');
        makeDraggable('panel-node',     'node-drag-handle');
    }

    function updateStats() {
        document.getElementById('statNodes').textContent = graph.order;
        document.getElementById('statEdges').textContent = graph.size;
        const warn = document.getElementById('graphWarning');
        if (graph.order >= MAX_NODES) {
            warn.style.display = 'block';
            warn.textContent = 'Hard limit reached (' + MAX_NODES + ' nodes).';
        } else {
            warn.style.display = 'none';
        }
    }

    function edgeColor(rel) {
        const isLight = document.documentElement.getAttribute('data-theme') === 'light';
        if (!rel) return isLight ? 'rgba(160,180,200,0.5)' : '#1b2640';
        if (rel === 'alias') return '#354a63';
        if (rel === 'resolved_to') return 'rgba(0,229,160,0.4)';
        if (rel === 'mentions') return 'rgba(255,107,43,0.3)';
        return 'rgba(0,212,255,0.2)';
    }

    function getMuted() {
        return document.documentElement.getAttribute('data-theme') === 'light' ? 'rgba(160,180,200,0.4)' : 'rgba(20,28,46,0.6)';
    }

    function nodeReducer(node, data) {
        const res = { ...data };
        const MUTED = getMuted();
        if (searchMatches !== null) {
            if (!searchMatches.has(node)) { res.color = MUTED; res.label = ''; res.zIndex = 0; }
            else { res.zIndex = 2; res.size = (data.size || 6) * 1.5; }
            return res;
        }
        const active = hoveredNode || selectedNode;
        if (active && active !== node) {
            const connected = graph.hasEdge(node, active) || graph.hasEdge(active, node);
            if (!connected) { res.color = MUTED; res.zIndex = 0; }
        }
        if (node === hoveredNode || node === selectedNode) { res.zIndex = 2; res.highlighted = true; }
        return res;
    }

    function edgeReducer(edge, data) {
        const res = { ...data };
        const src = graph.source(edge), tgt = graph.target(edge);
        if (searchMatches !== null) {
            if (!searchMatches.has(src) && !searchMatches.has(tgt)) res.hidden = true;
            return res;
        }
        const active = hoveredNode || selectedNode;
        if (active && src !== active && tgt !== active) {
            res.hidden = true;
        }
        return res;
    }

    function openNodePanel(nodeId) {
        selectedNode = nodeId;
        const attrs = graph.getNodeAttributes(nodeId);
        renderer.refresh();

        document.getElementById('panel-node').style.display = 'flex';
        document.querySelector('#panel-node .fg-panel-body').style.display = 'flex';

        document.getElementById('nd-name').textContent = attrs.label;

        const typeBadge = document.getElementById('nd-type-badge');
        const typeLabels = { centre:'root node', alias:'alias variant', kg_node:'kg node', linked_candidate:'candidate', source_entity:'source entity' };
        const typeCls = { centre:'badge-centre', alias:'badge-alias', kg_node:'badge-kg', linked_candidate:'badge-linked', source_entity:'badge-source' };
        typeBadge.textContent = typeLabels[attrs.ntype] || attrs.ntype;
        typeBadge.className = 'nd-type-badge ' + (typeCls[attrs.ntype] || 'badge-alias');

        const metaEl = document.getElementById('nd-meta');
        let metaHtml = '';
        if (attrs.concept_type) metaHtml += metaRow('Type', attrs.concept_type);
        if (attrs.status)       metaHtml += metaRow('Status', attrs.status);
        if (attrs.confidence)   metaHtml += metaRow('Confidence', confBar(attrs.confidence));
        if (attrs.source_table) metaHtml += metaRow('Source', attrs.source_table + ' #' + attrs.source_row_id);
        if (attrs.mention_count) metaHtml += metaRow('Mentions', attrs.mention_count + ' occurrences');
        if (attrs.kg_node_type)  metaHtml += metaRow('KG Type', attrs.kg_node_type);
        metaEl.innerHTML = metaHtml;

        const actEl = document.getElementById('nd-actions');
        let actions = '';
        
        const neighborCount = graph.degree(nodeId);
        if (neighborCount > 0) {
            actions += `<button class="btn-fg" onclick="FG.showConnectedNodes('${nodeId}')"><i class="bi bi-list-ul"></i> View Connected Nodes (${neighborCount})</button>`;
        }

        if (attrs.ntype === 'centre' && CANDIDATE && !CANDIDATE.is_entity) {
            actions += `<button class="btn-fg purple" onclick="openLandingModal(${CANDIDATE.id}, '${escHtml(CANDIDATE.label)}')"><i class="bi bi-file-earmark-text"></i> View Landing Details</button>`;
            actions += `<a href="/fuzz_forge.php?candidate_id=${CANDIDATE.id}" class="btn-fg"><i class="bi bi-collection"></i> Open in Fuzz Forge</a>`;
        } else if (attrs.ntype === 'linked_candidate' && attrs.db_id) {
            actions += `<button class="btn-fg purple" id="btn-expand-${attrs.db_id}" onclick="FG.expandCandidate(${attrs.db_id}, '${nodeId}')"><i class="bi bi-diagram-3"></i> Expand Connections</button>`;
            actions += `<a href="/fuzzgraph.php?id=${attrs.db_id}" class="btn-fg green"><i class="bi bi-bullseye"></i> Recenter Graph Here (Reload)</a>`;
            actions += `<button class="btn-fg" onclick="openLandingModal(${attrs.db_id}, '${escHtml(attrs.label)}')"><i class="bi bi-file-earmark-text"></i> View Landing Details</button>`;
        } else if (attrs.ntype === 'kg_node' && attrs.db_id) {
            actions += `<a href="/kg_graph.php" target="_blank" class="btn-fg green"><i class="bi bi-diagram-3-fill"></i> KG Graph</a>`;
        } else if (attrs.ntype === 'source_entity' && attrs.source_row_id) {
            actions += `<button class="btn-fg purple" id="btn-expand-ent-${attrs.source_table}-${attrs.source_row_id}" onclick="FG.expandEntity('${attrs.source_table}', ${attrs.source_row_id}, '${nodeId}')"><i class="bi bi-diagram-3"></i> Expand Linked Candidates</button>`;
            actions += `<a href="/fuzzgraph.php?entity=${escHtml(attrs.source_table)}&id=${attrs.source_row_id}" class="btn-fg green"><i class="bi bi-bullseye"></i> Recenter Graph Here (Reload)</a>`;
            actions += `<button class="btn-fg" onclick="openEntityIframe('${escHtml(attrs.source_table)}', ${attrs.source_row_id})"><i class="bi bi-box-arrow-up-right"></i> Open Entity Deep Dive</button>`;
        }
        actEl.innerHTML = actions;
    }

    function metaRow(label, val) {
        return `<div class="nd-meta-row"><span class="nd-meta-label">${escHtml(label)}</span><span class="nd-meta-val">${val}</span></div>`;
    }

    function confBar(pct) {
        const barColor = pct >= 70 ? 'var(--green)' : pct >= 40 ? 'var(--cyan)' : 'var(--orange)';
        return `<div style="display:flex; align-items:center; gap:6px; width:90px;">
            <div class="conf-bar-bg"><div class="conf-bar-fill" style="width:${pct}%; background:${barColor};"></div></div>
            <span style="font-family:var(--mono); font-size:0.65rem; color:var(--cyan);">${pct}%</span>
        </div>`;
    }

    function closeNodePanel() {
        document.getElementById('panel-node').style.display = 'none';
        selectedNode = null;
        if (renderer) renderer.refresh();
    }
    
    function showConnectedNodes(nodeId) {
        const neighbors = graph.neighbors(nodeId);
        const listEl = document.getElementById('fg-conn-list');
        const titleEl = document.getElementById('fg-conn-title');
        
        const nodeAttrs = graph.getNodeAttributes(nodeId);
        titleEl.textContent = `Connections: ${nodeAttrs.label}`;
        
        if (neighbors.length === 0) {
            listEl.innerHTML = '<div class="fg-picker-empty">No connections found.</div>';
        } else {
            const nData = neighbors.map(nid => ({ id: nid, ...graph.getNodeAttributes(nid) }));
            nData.sort((a,b) => a.label.localeCompare(b.label));
            
            listEl.innerHTML = nData.map(n => {
                const col = NODE_COLORS[n.ntype] || '#888';
                const typeFormat = n.ntype.replace('_', ' ');
                const metaParts = [];
                metaParts.push(typeFormat);
                if (n.db_id) metaParts.push('#' + n.db_id);
                if (n.source_row_id) metaParts.push('#' + n.source_row_id);
                
                return `<a href="javascript:void(0)" class="fg-picker-item" onclick="document.getElementById('fg-connections-modal').style.display='none'; FG.openNodePanel('${n.id}');">
                    <div style="width:12px; height:12px; border-radius:50%; background:${col}; flex-shrink:0;"></div>
                    <div style="flex:1; min-width:0;">
                        <div class="fg-picker-item-name" style="font-size:1.1rem;">${escHtml(n.label)}</div>
                        <div class="fg-picker-item-meta" style="margin-top:2px;">${escHtml(metaParts.join(' · '))}</div>
                    </div>
                    <i class="bi bi-chevron-right" style="color:var(--text-dim); font-size:12px;"></i>
                </a>`;
            }).join('');
        }
        
        document.getElementById('fg-connections-modal').style.display = 'flex';
    }

    function toggleLayout() {
        const btn = document.getElementById('btn-layout');
        if (isLayoutRunning) {
            cancelAnimationFrame(fa2LoopId);
            isLayoutRunning = false;
            btn.innerHTML = '<i class="bi bi-play-fill"></i> ForceAtlas2';
            btn.classList.remove('active');
        } else {
            isLayoutRunning = true;
            btn.innerHTML = '<i class="bi bi-stop-fill"></i> Stop Layout';
            btn.classList.add('active');
            const settings = {
                barnesHutOptimize: graph.order > 80,
                gravity: 0.1,
                scalingRatio: 12,
                slowDown: 8,
                strongGravityMode: false,
                adjustSizes: true,
            };
            const step = () => {
                graphologyLibrary.layoutForceAtlas2.assign(graph, { iterations: 1, settings });
                renderer.refresh();
                if (isLayoutRunning) fa2LoopId = requestAnimationFrame(step);
            };
            step();
        }
    }

    function resetCamera() {
        renderer.getCamera().animatedReset({ duration: 500 });
    }

    async function expandCandidate(dbId, nodeId) {
        if (graph.order >= MAX_NODES) return;

        try {
            const btn = document.getElementById('btn-expand-' + dbId);
            if (btn) btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Fetching...';

            const res = await fetch('/api/fuzz_graph_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'get_neighborhood', id: dbId})
            });
            const d = await res.json();
            if (!d.ok) {
                if(btn) btn.innerHTML = 'Error Loading';
                return;
            }

            let addedNodes = 0;
            const parentPos = graph.getNodeAttributes(nodeId);

            d.data.nodes.forEach(n => {
                if (graph.order >= MAX_NODES) return;
                if (!graph.hasNode(n.id)) {
                    graph.addNode(n.id, {
                        ...n,
                        x: parentPos.x + (Math.random() - 0.5) * 4,
                        y: parentPos.y + (Math.random() - 0.5) * 4,
                        size: NODE_SIZES[n.ntype] || 6,
                        color: NODE_COLORS[n.ntype] || '#888'
                    });
                    addedNodes++;
                }
            });

            d.data.edges.forEach(e => {
                if (graph.hasNode(e.source) && graph.hasNode(e.target) && !graph.hasEdge(e.source, e.target)) {
                    graph.addEdge(e.source, e.target, {
                        label: e.rel || '',
                        size: 1 + (e.weight || 0.5),
                        color: edgeColor(e.rel)
                    });
                }
            });

            try { graph.setNodeAttribute(nodeId, 'expanded', true); } catch(e){}

            if (addedNodes > 0) {
                graphologyLibrary.layoutForceAtlas2.assign(graph, {
                    iterations: 100,
                    settings: { barnesHutOptimize: graph.order > 80, gravity: 0.1, scalingRatio: 10 }
                });
                updateStats();
            }

            if (btn) btn.innerHTML = '<i class="bi bi-check2"></i> Expanded';
            renderer.refresh();
        } catch (err) {
            console.error("Expansion error:", err);
            const btn = document.getElementById('btn-expand-' + dbId);
            if(btn) btn.innerHTML = 'Error Loading';
        }
    }

    async function expandEntity(table, dbId, nodeId) {
        if (graph.order >= MAX_NODES) return;

        try {
            const btn = document.getElementById(`btn-expand-ent-${table}-${dbId}`);
            if (btn) btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Fetching...';

            const res = await fetch('/api/fuzz_graph_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'get_neighborhood_batch', entities: [{table: table, id: dbId}]})
            });
            const d = await res.json();
            if (!d.ok) {
                if(btn) btn.innerHTML = 'Error Loading';
                return;
            }

            let addedNodes = 0;
            const parentPos = graph.getNodeAttributes(nodeId);

            d.data.nodes.forEach(n => {
                if (graph.order >= MAX_NODES) return;
                if (!graph.hasNode(n.id)) {
                    graph.addNode(n.id, {
                        ...n,
                        x: parentPos.x + (Math.random() - 0.5) * 4,
                        y: parentPos.y + (Math.random() - 0.5) * 4,
                        size: NODE_SIZES[n.ntype] || 6,
                        color: NODE_COLORS[n.ntype] || '#888'
                    });
                    addedNodes++;
                }
            });

            d.data.edges.forEach(e => {
                if (graph.hasNode(e.source) && graph.hasNode(e.target) && !graph.hasEdge(e.source, e.target)) {
                    graph.addEdge(e.source, e.target, {
                        label: e.rel || '',
                        size: 1 + (e.weight || 0.5),
                        color: edgeColor(e.rel)
                    });
                }
            });

            try { graph.setNodeAttribute(nodeId, 'expanded', true); } catch(e){}

            if (addedNodes > 0) {
                graphologyLibrary.layoutForceAtlas2.assign(graph, {
                    iterations: 100,
                    settings: { barnesHutOptimize: graph.order > 80, gravity: 0.1, scalingRatio: 10 }
                });
                updateStats();
            }

            if (btn) btn.innerHTML = '<i class="bi bi-check2"></i> Expanded';
            renderer.refresh();
        } catch (err) {
            console.error("Expansion error:", err);
            const btn = document.getElementById(`btn-expand-ent-${table}-${dbId}`);
            if(btn) btn.innerHTML = 'Error Loading';
        }
    }

    async function changeHops(hopsVal) {
        if (hopsVal === '2') {
            const selectEl = document.getElementById('graphHopsSelect');
            selectEl.disabled = true;

            const toExpandCand = [];
            const toExpandEnt = [];
            graph.forEachNode((node, attrs) => {
                if (!attrs.expanded) {
                    if (attrs.ntype === 'linked_candidate' && attrs.db_id) {
                        toExpandCand.push(attrs.db_id);
                    } else if (attrs.ntype === 'source_entity' && attrs.source_table && attrs.source_row_id) {
                        toExpandEnt.push({table: attrs.source_table, id: attrs.source_row_id});
                    }
                }
            });

            if (toExpandCand.length === 0 && toExpandEnt.length === 0) {
                selectEl.disabled = false;
                return;
            }

            try {
                const chunkSize = 20;
                let totalAdded = 0;

                while ((toExpandCand.length > 0 || toExpandEnt.length > 0) && graph.order < MAX_NODES) {
                    const cChunk = toExpandCand.splice(0, chunkSize);
                    const eChunk = toExpandEnt.splice(0, chunkSize);

                    const res = await fetch('/api/fuzz_graph_api.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({action: 'get_neighborhood_batch', candidates: cChunk, entities: eChunk})
                    });
                    const d = await res.json();

                    if (d.ok) {
                        d.data.nodes.forEach(n => {
                            if (graph.order >= MAX_NODES) return;
                            if (!graph.hasNode(n.id)) {
                                graph.addNode(n.id, {
                                    ...n,
                                    x: (Math.random() - 0.5) * 20,
                                    y: (Math.random() - 0.5) * 20,
                                    size: NODE_SIZES[n.ntype] || 6,
                                    color: NODE_COLORS[n.ntype] || '#888'
                                });
                                totalAdded++;
                            }
                        });

                        d.data.edges.forEach(e => {
                            if (graph.hasNode(e.source) && graph.hasNode(e.target) && !graph.hasEdge(e.source, e.target)) {
                                graph.addEdge(e.source, e.target, {
                                    label: e.rel || '',
                                    size: 1 + (e.weight || 0.5),
                                    color: edgeColor(e.rel)
                                });
                            }
                        });

                        cChunk.forEach(dbId => {
                            try { graph.setNodeAttribute('cand_' + dbId, 'expanded', true); } catch(err){}
                        });
                        eChunk.forEach(ent => {
                            try { graph.setNodeAttribute('src_' + ent.table + '_' + ent.id, 'expanded', true); } catch(err){}
                        });
                    }
                }

                if (totalAdded > 0) {
                    graphologyLibrary.layoutForceAtlas2.assign(graph, {
                        iterations: 120,
                        settings: { barnesHutOptimize: graph.order > 80, gravity: 0.15, scalingRatio: 12 }
                    });
                    updateStats();
                    renderer.refresh();
                }

            } catch(e) {
                console.error("Batch Expansion error", e);
            }

            selectEl.disabled = false;
        }
    }

    function togglePanel(id, btn) {
        const body = document.getElementById(id + '-body') || document.querySelector(`#${id} .fg-panel-body`);
        if (!body) return;
        const hidden = body.style.display === 'none';
        body.style.display = hidden ? 'flex' : 'none';
        btn.innerHTML = hidden ? '<i class="bi bi-dash"></i>' : '<i class="bi bi-plus"></i>';
    }

    function makeDraggable(panelId, handleId) {
        const panel  = document.getElementById(panelId);
        const handle = document.getElementById(handleId);
        if (!panel || !handle) return;
        let dragging = false, ox = 0, oy = 0, px = 0, py = 0;
        function start(e) {
            if (e.target.closest('button') || e.target.closest('select') || e.target.closest('a')) return;
            dragging = true;
            const t = e.touches ? e.touches[0] : e;
            ox = t.clientX; oy = t.clientY;
            px = panel.offsetLeft; py = panel.offsetTop;
            window.addEventListener('mousemove', move);
            window.addEventListener('mouseup', end);
            window.addEventListener('touchmove', move, {passive:false});
            window.addEventListener('touchend', end);
        }
        function move(e) {
            if (!dragging) return;
            if (e.cancelable) e.preventDefault();
            const t = e.touches ? e.touches[0] : e;
            panel.style.left  = (px + t.clientX - ox) + 'px';
            panel.style.top   = (py + t.clientY - oy) + 'px';
            panel.style.right = 'auto';
        }
        function end() {
            dragging = false;
            window.removeEventListener('mousemove', move);
            window.removeEventListener('mouseup', end);
            window.removeEventListener('touchmove', move);
            window.removeEventListener('touchend', end);
        }
        handle.addEventListener('mousedown', start);
        handle.addEventListener('touchstart', start, {passive:false});
    }

    return { init, toggleLayout, resetCamera, closeNodePanel, openNodePanel, showConnectedNodes, togglePanel, expandCandidate, expandEntity, changeHops };
})();

// ── Entity iframe modal (lightweight) ────────────────────────────────────────
let _efOpen = false;
function openEntityIframe(entityType, entityId) {
    let existing = document.getElementById('fg-entity-modal');
    if (!existing) {
        existing = document.createElement('div');
        existing.id = 'fg-entity-modal';
        existing.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:8000;display:flex;align-items:flex-end;justify-content:center;backdrop-filter:blur(4px);';
        existing.innerHTML = `
            <div style="width:100%;max-width:900px;height:88dvh;background:var(--card);border:1px solid var(--border-glow);border-radius:12px 12px 0 0;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 -10px 40px rgba(0,0,0,0.8);">
                <div style="display:flex;align-items:center;padding:10px 16px;border-bottom:1px solid var(--border);background:var(--surface);gap:10px;">
                    <span id="fg-ef-title" style="font-family:var(--head);font-size:1.2rem;letter-spacing:2px;color:var(--text-bright);flex:1;text-transform:uppercase;">Entity</span>
                    <button onclick="document.getElementById('fg-entity-modal').style.display='none'; document.getElementById('fg-ef-iframe').src='about:blank';"
                        style="border:1px solid var(--border-glow);background:transparent;color:var(--text-dim);border-radius:4px;width:28px;height:28px;cursor:pointer;display:flex;align-items:center;justify-content:center;">✕</button>
                </div>
                <iframe id="fg-ef-iframe" src="about:blank" style="flex:1;border:none;width:100%;"></iframe>
            </div>`;
        document.body.appendChild(existing);
        existing.addEventListener('click', e => {
            if (e.target === existing) { existing.style.display='none'; document.getElementById('fg-ef-iframe').src='about:blank'; }
        });
    }
    document.getElementById('fg-ef-title').textContent = entityType + ' #' + entityId;
    document.getElementById('fg-ef-iframe').src = `/entity_form.php?entity_type=${encodeURIComponent(entityType)}&entity_id=${encodeURIComponent(entityId)}&view=modal`;
    existing.style.display = 'flex';
}

document.addEventListener('DOMContentLoaded', () => FG.init());
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        const efm = document.getElementById('fg-entity-modal');
        const lmm = document.getElementById('landingModalOverlay');
        const cmm = document.getElementById('fg-connections-modal');
        if (efm && efm.style.display !== 'none') { efm.style.display = 'none'; document.getElementById('fg-ef-iframe').src = 'about:blank'; }
        else if (lmm && lmm.style.display !== 'none') { lmm.style.display = 'none'; document.getElementById('landingModalIframe').src = 'about:blank'; }
        else if (cmm && cmm.style.display !== 'none') { cmm.style.display = 'none'; }
        else FG.closeNodePanel();
    }
});
</script>
</body>
</html>