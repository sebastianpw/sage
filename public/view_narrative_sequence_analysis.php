<?php
// public/view_narrative_sequence_analysis.php
//
// Deep-dive view for a single Narrative Sequence: episode document + per-beat
// analyst / composer data, frames carousel, sketch analysis cross-links.
//
// GET params:
//   ?id=96          — required: narrative_sequences.id
//
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require "entity_icons.php";

use App\UI\Modules\ModuleRegistry;

// ── UI Modules ────────────────────────────────────────────────────────────────
$registry = ModuleRegistry::getInstance();
$entities_with_menu = ['characters', 'sketches', 'frames'];
$gearMenu = $registry->create('gear_menu', [
    'position'          => 'top-right',
    'icon'              => '&#9881;',
    'icon_size'         => '1.5em',
    'show_for_entities' => $entities_with_menu,
]);
foreach ($entities_with_menu as $entity_name) {
    $gearMenu->addStandardActions($entity_name);
}
$imageEditor = $registry->create('image_editor');

ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();

// ── Params ────────────────────────────────────────────────────────────────────
$seqId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$seqId) {
    // Show a simple picker if no id
    $seqs = $pdo->query(
        "SELECT id, name, created_at FROM narrative_sequences ORDER BY id DESC LIMIT 100"
    )->fetchAll(PDO::FETCH_ASSOC);
    ob_start();
    ?>
    <link rel="stylesheet" href="/css/base.css">
    <div style="max-width:700px;margin:60px auto;padding:20px;">
        <h2 style="font-family:'Space Mono',monospace;color:var(--accent);">📽 Narrative Sequence Analysis</h2>
        <p style="color:var(--text-muted);">Select a sequence to view:</p>
        <div style="display:flex;flex-direction:column;gap:8px;margin-top:20px;">
        <?php foreach ($seqs as $s): ?>
            <a href="?id=<?= $s['id'] ?>"
               style="display:flex;justify-content:space-between;padding:10px 14px;background:var(--card);border:1px solid var(--border);border-radius:6px;text-decoration:none;color:var(--text);font-family:'Space Mono',monospace;font-size:.85rem;transition:border-color .2s;"
               onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
                <span>#<?= $s['id'] ?> — <?= htmlspecialchars($s['name']) ?></span>
                <span style="color:var(--text-muted);"><?= date('Y-m-d', strtotime($s['created_at'])) ?></span>
            </a>
        <?php endforeach; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    $spw->renderLayout($content, 'Narrative Sequence Analysis', $spw->getProjectPath() . '/templates/curation.php');
    exit;
}

// ── Fetch Sequence ────────────────────────────────────────────────────────────
$seqRow = $pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
$seqRow->execute([$seqId]);
$seq = $seqRow->fetch(PDO::FETCH_ASSOC);
if (!$seq) {
    echo "<div style='padding:40px;color:red;'>Sequence #$seqId not found.</div>";
    exit;
}
$seqBeats = json_decode($seq['sequence_data'] ?? '[]', true) ?: [];

// ── Fetch Episode-level Analysis ──────────────────────────────────────────────
$nsaStmt = $pdo->prepare("SELECT * FROM narrative_sequence_analysis WHERE sequence_id = ?");
$nsaStmt->execute([$seqId]);
$nsa = $nsaStmt->fetch(PDO::FETCH_ASSOC);
$episodeTitle    = $nsa['episode_title']    ?? $seq['name'];
$episodeSubtitle = $nsa['episode_subtitle'] ?? '';
$logline         = $nsa['logline']          ?? '';
$episodeThesis   = $nsa['episode_thesis']   ?? '';
$productionNotes = $nsa['production_notes'] ?? '';
$actStructure    = json_decode($nsa['act_structure']   ?? '[]', true) ?: [];
$recurringMotifs = json_decode($nsa['recurring_motifs'] ?? '[]', true) ?: [];
$openTensions    = json_decode($nsa['open_tensions']   ?? '[]', true) ?: [];
$synthRaw        = $nsa['synthesiser_raw']  ?? '';
$modelUsed       = $nsa['model_used']       ?? '';
$beatCount       = (int)($nsa['beat_count'] ?? count($seqBeats));

// Decode synthesiser_raw to get richer episode data
$synthDecoded = [];
if ($synthRaw) {
    $clean = trim($synthRaw);
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $clean, $m)) $clean = $m[1];
    $pos = strpos($clean, '{');
    if ($pos !== false) {
        $depth = 0; $inStr = false; $esc = false;
        for ($i = $pos; $i < strlen($clean); $i++) {
            $ch = $clean[$i];
            if ($inStr) { if ($esc) { $esc=false; continue; } if ($ch==='\\') { $esc=true; continue; } if ($ch==='"') $inStr=false; }
            else { if ($ch==='"') $inStr=true; elseif ($ch==='{') $depth++; elseif ($ch==='}') { $depth--; if ($depth===0) { $sub=substr($clean,$pos,$i-$pos+1); $sub=preg_replace('/,\s*(\]|\})/m','$1',$sub); $synthDecoded=json_decode($sub,true)??[]; break; } } }
        }
    }
}
// Prefer rich decoded data where available
if (!empty($synthDecoded['episode_title']))    $episodeTitle    = $synthDecoded['episode_title'];
if (!empty($synthDecoded['episode_subtitle'])) $episodeSubtitle = $synthDecoded['episode_subtitle'];
if (!empty($synthDecoded['logline']))          $logline         = $synthDecoded['logline'];
if (!empty($synthDecoded['episode_thesis']))   $episodeThesis   = $synthDecoded['episode_thesis'];
if (!empty($synthDecoded['production_notes'])) $productionNotes = $synthDecoded['production_notes'];
if (!empty($synthDecoded['act_structure']))    $actStructure    = $synthDecoded['act_structure'];
if (!empty($synthDecoded['recurring_motifs'])) $recurringMotifs = $synthDecoded['recurring_motifs'];
if (!empty($synthDecoded['open_tensions']))    $openTensions    = $synthDecoded['open_tensions'];

// ── Fetch Beat Rows ───────────────────────────────────────────────────────────
$beatStmt = $pdo->prepare(
    "SELECT * FROM narrative_beat_analysis WHERE sequence_id = ? ORDER BY position ASC"
);
$beatStmt->execute([$seqId]);
$beatRows = $beatStmt->fetchAll(PDO::FETCH_ASSOC);
$beatByPos = [];
foreach ($beatRows as $br) {
    $beatByPos[(int)$br['position']] = $br;
}

// ── Helper: decode raw JSON fields (same logic as cli) ────────────────────────
function nsa_decode_raw(string $raw): ?array {
    $raw = trim($raw);
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $raw, $m)) $raw = $m[1];
    $d = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) return $d;
    $pos = strpos($raw, '{');
    if ($pos !== false) {
        $depth=0;$inStr=false;$esc=false;
        for ($i=$pos;$i<strlen($raw);$i++) {
            $ch=$raw[$i];
            if ($inStr){if($esc){$esc=false;continue;}if($ch==='\\'){$esc=true;continue;}if($ch==='"')$inStr=false;}
            else{if($ch==='"')$inStr=true;elseif($ch==='{')$depth++;elseif($ch==='}'){$depth--;if($depth===0){$sub=substr($raw,$pos,$i-$pos+1);$sub=preg_replace('/,\s*(\]|\})/m','$1',$sub);$d=json_decode($sub,true);if(json_last_error()===JSON_ERROR_NONE)return $d;break;}}}
        }
    }
    return null;
}

// ── Helper: frames for a sketch ───────────────────────────────────────────────
function nsa_frames_for_sketch(PDO $pdo, int $sketchId): array {
    $stmt = $pdo->prepare("
        SELECT f.*, ie.tool as edit_tool
        FROM frames f
        LEFT JOIN image_edits ie ON f.id = ie.derived_frame_id
        WHERE f.entity_type = 'sketches' AND f.entity_id = :sid
        ORDER BY f.id DESC
        LIMIT 20
    ");
    $stmt->execute([':sid' => $sketchId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Helper: sketch analysis ────────────────────────────────────────────────────
function nsa_sketch_analysis(PDO $pdo, int $sketchId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM sketch_analysis WHERE sketch_id = ?");
    $stmt->execute([$sketchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    foreach (['entities','classification','scoring','thematics','recommendations'] as $f) {
        if (!empty($row[$f])) $row[$f] = json_decode($row[$f], true);
    }
    return $row;
}

// ── Helper: sketch_sequence_analysis ─────────────────────────────────────────
function nsa_seq_analysis(PDO $pdo, int $sketchId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM sketch_sequence_analysis WHERE sketch_id = ?");
    $stmt->execute([$sketchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    foreach (['narrative_function','layer','tags'] as $f) {
        if (!empty($row[$f])) $row[$f] = json_decode($row[$f], true);
    }
    return $row;
}

// ── Normalize beat entry (same as CLI) ────────────────────────────────────────
function nsa_normalize_beat(mixed $entry): array {
    if (is_int($entry) || (is_string($entry) && ctype_digit((string)$entry))) {
        return ['sketch_id' => (int)$entry, 'frame_id' => 0];
    }
    if (!is_array($entry)) return ['sketch_id' => 0, 'frame_id' => 0];
    if (isset($entry[0]) && is_numeric($entry[0])) {
        return ['sketch_id' => (int)$entry[0], 'frame_id' => isset($entry[1]) ? (int)$entry[1] : 0];
    }
    return [
        'sketch_id' => (int)($entry['sketch_id'] ?? $entry['sketchId'] ?? $entry['entity_id'] ?? $entry['id'] ?? 0),
        'frame_id'  => (int)($entry['frame_id']  ?? $entry['frameId']  ?? 0),
    ];
}

// ── Resolve act label for each beat ──────────────────────────────────────────
// Build a lookup: position → act label from actStructure
$posActLabel = [];
if (!empty($actStructure)) {
    foreach ($actStructure as $act) {
        $label = $act['label'] ?? $act['act_label'] ?? '';
        $scenes = $act['scene_titles'] ?? $act['scenes'] ?? [];
        // map by position index is tricky — we'll rely on beat row's act_label instead
        // Store act_structure acts for display
    }
}

// ── Build beat data array for rendering ──────────────────────────────────────
$pageTitle = "📽 " . $episodeTitle;
$allBeatData = [];
foreach ($seqBeats as $pos => $rawEntry) {
    $nb = nsa_normalize_beat($rawEntry);
    $sketchId = $nb['sketch_id'];
    $frameIdHint = $nb['frame_id'];

    $beatRow = $beatByPos[$pos] ?? null;

    // Decode beat analyst JSON
    $beatAnalyst = null;
    if (!empty($beatRow['beat_raw'])) {
        $beatAnalyst = nsa_decode_raw($beatRow['beat_raw']);
    }

    // Decode composer JSON
    $composeData = null;
    if (!empty($beatRow['compose_raw'])) {
        $composeData = nsa_decode_raw($beatRow['compose_raw']);
    }

    // Rolling context at this beat
    $rollingCtx = null;
    if (!empty($beatRow['rolling_context'])) {
        $rollingCtx = json_decode($beatRow['rolling_context'], true);
    }

    // Sketch row
    $sketchStmt = $pdo->prepare("SELECT * FROM sketches WHERE id = ?");
    $sketchStmt->execute([$sketchId]);
    $sketchRow = $sketchStmt->fetch(PDO::FETCH_ASSOC);

    // Frames
    $frames = $sketchId ? nsa_frames_for_sketch($pdo, $sketchId) : [];

    // Highlighted frame (from beat row or hint)
    $pinnedFrameId = ($beatRow['frame_id'] ?? 0) ?: $frameIdHint;

    // Sketch analysis
    $sa = $sketchId ? nsa_sketch_analysis($pdo, $sketchId) : null;

    // Sketch sequence analysis
    $ssa = $sketchId ? nsa_seq_analysis($pdo, $sketchId) : null;

    $allBeatData[] = [
        'position'      => $pos,
        'sketch_id'     => $sketchId,
        'frame_id_hint' => $pinnedFrameId,
        'beat_row'      => $beatRow,
        'beat_analyst'  => $beatAnalyst,
        'compose_data'  => $composeData,
        'rolling_ctx'   => $rollingCtx,
        'sketch'        => $sketchRow,
        'frames'        => $frames,
        'sa'            => $sa,
        'ssa'           => $ssa,
    ];
}

// ── Build export overlay payload for JS ──────────────────────────────────────
// Collect sketch_id → beat_summary pairs for all beats that have a beat_summary
$exportOverlayBeats = [];
foreach ($allBeatData as $bd) {
    $sketchId = $bd['sketch_id'];
    if (!$sketchId) continue;
    $cd = $bd['compose_data'];
    $beatSummary = '';
    if ($cd) {
        $beatSummary = $cd['beat_summary'] ?? $cd['BEAT_SUMMARY'] ?? '';
    }
    $exportOverlayBeats[] = [
        'sketch_id'    => $sketchId,
        'beat_summary' => $beatSummary,
        'position'     => $bd['position'],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// OUTPUT
// ─────────────────────────────────────────────────────────────────────────────
ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<!-- Swiper & PhotoSwipe -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<script type="module">
    import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
    window.initLightbox = () => {
        const lightbox = new PhotoSwipeLightbox({
            gallery: '.nsa-pswp-gallery',
            children: '.nsa-pswp-item',
            pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        });
        lightbox.init();
    };
</script>

<style>
/* ── Design tokens ──────────────────────────────────────────────── */
:root {
    --nsa-amber:    #f5a623;
    --nsa-amber-dim:#b5761a;
    --nsa-crimson:  #e05252;
    --nsa-teal:     #4ecdc4;
    --nsa-violet:   #9b6dff;
    --nsa-green:    #52e07a;
    --nsa-bg:       var(--bg, #0d0d0d);
    --nsa-card:     var(--card, #161616);
    --nsa-border:   var(--border, #2a2a2a);
    --nsa-text:     var(--text, #e8e8e8);
    --nsa-muted:    var(--text-muted, #777);
    --mono:         'Space Mono', 'Courier New', monospace;
    --sans:         'Syne', system-ui, sans-serif;
}

/* ── Episode Header ─────────────────────────────────────────────── */
.nsa-header {
    background: linear-gradient(180deg, rgba(245,166,35,.07) 0%, transparent 100%);
    border-bottom: 2px solid var(--nsa-amber-dim);
    padding: 28px 24px 20px;
    position: relative;
    overflow: hidden;
}
.nsa-header::before {
    content: '';
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 28px,
        rgba(245,166,35,.025) 28px,
        rgba(245,166,35,.025) 29px
    );
    pointer-events: none;
}
.nsa-ep-eyebrow {
    font-family: var(--mono);
    font-size: .65rem;
    letter-spacing: .15em;
    color: var(--nsa-amber);
    text-transform: uppercase;
    margin-bottom: 8px;
}
.nsa-ep-title {
    font-family: var(--sans);
    font-size: clamp(1.4rem, 3vw, 2.1rem);
    font-weight: 800;
    color: var(--nsa-text);
    line-height: 1.15;
    margin: 0 0 4px;
}
.nsa-ep-subtitle {
    font-family: var(--mono);
    font-size: .85rem;
    color: var(--nsa-amber);
    font-style: italic;
    margin-bottom: 14px;
}
.nsa-ep-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
}
.nsa-meta-chip {
    font-family: var(--mono);
    font-size: .7rem;
    padding: 3px 9px;
    border: 1px solid var(--nsa-border);
    border-radius: 4px;
    color: var(--nsa-muted);
    background: rgba(255,255,255,.03);
}
.nsa-meta-chip strong { color: var(--nsa-text); }

/* Logline / Thesis */
.nsa-logline {
    font-family: var(--sans);
    font-size: 1rem;
    font-style: italic;
    color: var(--nsa-text);
    border-left: 3px solid var(--nsa-amber);
    padding: 10px 16px;
    background: rgba(245,166,35,.04);
    border-radius: 0 6px 6px 0;
    margin-bottom: 14px;
    line-height: 1.55;
}
.nsa-thesis {
    font-family: var(--sans);
    font-size: .88rem;
    color: var(--nsa-muted);
    line-height: 1.6;
}

/* Expandable episode-doc sections */
.nsa-doc-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 20px 0 0;
}
.nsa-doc-tab {
    font-family: var(--mono);
    font-size: .7rem;
    letter-spacing: .07em;
    padding: 5px 12px;
    border: 1px solid var(--nsa-border);
    border-radius: 3px;
    cursor: pointer;
    background: rgba(255,255,255,.02);
    color: var(--nsa-muted);
    text-transform: uppercase;
    transition: all .15s;
    user-select: none;
}
.nsa-doc-tab:hover { border-color: var(--nsa-amber); color: var(--nsa-amber); }
.nsa-doc-tab.active { border-color: var(--nsa-amber); color: #111; background: var(--nsa-amber); }

.nsa-doc-panel {
    display: none;
    margin-top: 16px;
    padding: 16px;
    background: rgba(0,0,0,.3);
    border: 1px solid var(--nsa-border);
    border-radius: 6px;
    animation: nsaFadeIn .2s ease;
}
.nsa-doc-panel.visible { display: block; }

@keyframes nsaFadeIn { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:none; } }

/* Act structure chips */
.nsa-act-block {
    margin-bottom: 16px;
    padding: 12px 14px;
    border: 1px solid rgba(245,166,35,.2);
    border-radius: 6px;
    background: rgba(245,166,35,.03);
}
.nsa-act-label {
    font-family: var(--mono);
    font-size: .7rem;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--nsa-amber);
    margin-bottom: 6px;
}
.nsa-act-desc {
    font-family: var(--sans);
    font-size: .85rem;
    color: var(--nsa-text);
    line-height: 1.5;
    margin-bottom: 8px;
}
.nsa-scene-pill {
    display: inline-block;
    font-family: var(--mono);
    font-size: .65rem;
    padding: 2px 7px;
    border: 1px solid var(--nsa-border);
    border-radius: 3px;
    color: var(--nsa-muted);
    margin: 2px 3px 2px 0;
}

/* Motif / Tension lists */
.nsa-motif-list, .nsa-tension-list { list-style: none; padding: 0; margin: 0; }
.nsa-motif-list li {
    font-family: var(--sans);
    font-size: .85rem;
    color: var(--nsa-text);
    padding: 7px 10px 7px 22px;
    position: relative;
    border-bottom: 1px solid rgba(255,255,255,.04);
    line-height: 1.45;
}
.nsa-motif-list li:before { content: '◈'; position:absolute; left:4px; color: var(--nsa-violet); font-size:.65rem; top:10px; }
.nsa-tension-list li {
    font-family: var(--sans);
    font-size: .85rem;
    color: var(--nsa-text);
    padding: 7px 10px 7px 22px;
    position: relative;
    border-bottom: 1px solid rgba(255,255,255,.04);
    line-height: 1.45;
}
.nsa-tension-list li:before { content: '⚡'; position:absolute; left:4px; font-size:.65rem; top:8px; }

/* Production notes pre */
.nsa-prod-notes {
    font-family: var(--sans);
    font-size: .85rem;
    color: var(--nsa-text);
    line-height: 1.65;
    white-space: pre-wrap;
}

/* ── Beat Timeline ──────────────────────────────────────────────── */
.nsa-timeline-wrap {
    padding: 24px 16px 40px;
}
.nsa-timeline-header {
    font-family: var(--mono);
    font-size: .65rem;
    letter-spacing: .15em;
    text-transform: uppercase;
    color: var(--nsa-amber);
    padding: 0 0 12px;
    border-bottom: 1px solid var(--nsa-border);
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Beat card */
.nsa-beat-card {
    margin-bottom: 12px;
    border: 1px solid var(--nsa-border);
    border-radius: 8px;
    overflow: hidden;
    background: var(--nsa-card);
    transition: border-color .2s;
}
.nsa-beat-card:hover { border-color: rgba(245,166,35,.35); }
.nsa-beat-card.open { border-color: var(--nsa-amber-dim); }

/* Beat header / toggle row */
.nsa-beat-toggle {
    display: grid;
    grid-template-columns: 40px 1fr auto;
    gap: 0;
    align-items: stretch;
    cursor: pointer;
    min-height: 64px;
}
.nsa-beat-num {
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--mono);
    font-size: .75rem;
    font-weight: 700;
    color: var(--nsa-amber);
    background: rgba(245,166,35,.06);
    border-right: 1px solid var(--nsa-border);
    padding: 10px 0;
    line-height: 1;
}
.nsa-beat-summary-row {
    padding: 10px 14px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 3px;
}
.nsa-beat-scene-title {
    font-family: var(--sans);
    font-size: .95rem;
    font-weight: 700;
    color: var(--nsa-text);
    line-height: 1.2;
}
.nsa-beat-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
}
.nsa-act-chip {
    font-family: var(--mono);
    font-size: .6rem;
    letter-spacing: .07em;
    text-transform: uppercase;
    padding: 2px 7px;
    background: rgba(245,166,35,.12);
    color: var(--nsa-amber);
    border-radius: 3px;
    border: 1px solid rgba(245,166,35,.3);
}
.nsa-emotion-chip {
    font-family: var(--mono);
    font-size: .6rem;
    letter-spacing: .04em;
    padding: 2px 7px;
    background: rgba(155,109,255,.1);
    color: var(--nsa-violet);
    border-radius: 3px;
    border: 1px solid rgba(155,109,255,.25);
}
.nsa-sketch-chip {
    font-family: var(--mono);
    font-size: .6rem;
    color: var(--nsa-muted);
    padding: 2px 5px;
    border-radius: 3px;
    border: 1px dashed var(--nsa-border);
}
.nsa-beat-thumb {
    width: 56px;
    min-height: 56px;
    flex-shrink: 0;
    background: #111;
    overflow: hidden;
    border-left: 1px solid var(--nsa-border);
    display: flex;
    align-items: center;
    justify-content: center;
}
.nsa-beat-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.nsa-beat-thumb .no-thumb {
    font-size: 1.2rem;
    opacity: .3;
}
.nsa-beat-chevron {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    color: var(--nsa-muted);
    font-size: .75rem;
    flex-shrink: 0;
    transition: transform .2s;
}
.nsa-beat-card.open .nsa-beat-chevron { transform: rotate(90deg); color: var(--nsa-amber); }

/* Beat body */
.nsa-beat-body {
    display: none;
    border-top: 1px solid var(--nsa-border);
    animation: nsaFadeIn .2s ease;
}
.nsa-beat-card.open .nsa-beat-body { display: block; }

/* Beat body inner tabs */
.nsa-beat-tabs {
    display: flex;
    gap: 0;
    border-bottom: 1px solid var(--nsa-border);
    overflow-x: auto;
    background: rgba(0,0,0,.25);
}
.nsa-beat-tab {
    font-family: var(--mono);
    font-size: .62rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    padding: 9px 14px;
    cursor: pointer;
    color: var(--nsa-muted);
    border-bottom: 2px solid transparent;
    white-space: nowrap;
    flex-shrink: 0;
    transition: color .15s, border-color .15s;
    user-select: none;
}
.nsa-beat-tab:hover { color: var(--nsa-text); }
.nsa-beat-tab.active { color: var(--nsa-amber); border-bottom-color: var(--nsa-amber); }

.nsa-beat-panel {
    display: none;
    padding: 16px;
    animation: nsaFadeIn .2s ease;
}
.nsa-beat-panel.visible { display: block; }

/* Frames carousel inside beat */
.nsa-frames-swiper { width: 100%; margin-top: 8px; }
.nsa-frames-swiper .swiper-slide { width: 160px; }
.nsa-frame-mini {
    background: #111;
    border: 1px solid var(--nsa-border);
    border-radius: 6px;
    overflow: visible;
    position: relative;
}
.nsa-frame-mini-img {
    position: relative;
    width: 100%;
    padding-top: 100%;
    overflow: hidden;
    border-radius: 5px 5px 0 0;
    background: #0a0a0a;
}
.nsa-frame-mini-img img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.nsa-frame-mini-info {
    padding: 6px 8px;
    font-family: var(--mono);
    font-size: .6rem;
    color: var(--nsa-muted);
    display: flex;
    justify-content: space-between;
}
.nsa-frame-pinned {
    position: absolute;
    top: 4px;
    left: 4px;
    background: var(--nsa-amber);
    color: #111;
    font-family: var(--mono);
    font-size: .55rem;
    padding: 1px 5px;
    border-radius: 2px;
    font-weight: 700;
    z-index: 2;
}
.nsa-frame-rating {
    position: absolute;
    top: 4px;
    right: 4px;
    background: rgba(0,0,0,.7);
    color: #f59e0b;
    font-family: var(--mono);
    font-size: .6rem;
    padding: 1px 5px;
    border-radius: 2px;
    z-index: 2;
}

/* Analyst / Composer fields */
.nsa-field-group { margin-bottom: 16px; }
.nsa-field-label {
    font-family: var(--mono);
    font-size: .6rem;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--nsa-amber);
    margin-bottom: 5px;
    display: block;
}
.nsa-field-value {
    font-family: var(--sans);
    font-size: .875rem;
    color: var(--nsa-text);
    line-height: 1.55;
}
.nsa-field-value.mono {
    font-family: var(--mono);
    font-size: .78rem;
}
.nsa-pill-group { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 4px; }
.nsa-pill {
    display: inline-block;
    font-family: var(--mono);
    font-size: .65rem;
    padding: 2px 8px;
    border-radius: 3px;
    border: 1px solid var(--nsa-border);
    color: var(--nsa-muted);
    background: rgba(255,255,255,.03);
}
.nsa-pill.amber { border-color: rgba(245,166,35,.4); color: var(--nsa-amber); background: rgba(245,166,35,.07); }
.nsa-pill.violet { border-color: rgba(155,109,255,.3); color: var(--nsa-violet); background: rgba(155,109,255,.07); }
.nsa-pill.teal { border-color: rgba(78,205,196,.3); color: var(--nsa-teal); background: rgba(78,205,196,.07); }
.nsa-pill.red { border-color: rgba(224,82,82,.3); color: var(--nsa-crimson); background: rgba(224,82,82,.07); }
.nsa-pill.green { border-color: rgba(82,224,122,.3); color: var(--nsa-green); background: rgba(82,224,122,.07); }

/* Char state rows */
.nsa-char-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
.nsa-char-table th {
    font-family: var(--mono);
    font-size: .58rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--nsa-muted);
    text-align: left;
    padding: 4px 8px;
    border-bottom: 1px solid var(--nsa-border);
}
.nsa-char-table td {
    font-family: var(--sans);
    font-size: .8rem;
    color: var(--nsa-text);
    padding: 6px 8px;
    border-bottom: 1px solid rgba(255,255,255,.04);
    vertical-align: top;
}
.nsa-char-table td:first-child { color: var(--nsa-teal); font-weight: 600; }

/* Scene prose */
.nsa-prose-wrap {
    font-family: var(--mono);
    font-size: .78rem;
    line-height: 1.8;
    color: #ccc;
    white-space: pre-wrap;
    background: rgba(0,0,0,.35);
    border: 1px solid var(--nsa-border);
    border-radius: 6px;
    padding: 16px;
    max-height: 480px;
    overflow-y: auto;
}
.nsa-prose-wrap::-webkit-scrollbar { width: 5px; }
.nsa-prose-wrap::-webkit-scrollbar-track { background: transparent; }
.nsa-prose-wrap::-webkit-scrollbar-thumb { background: var(--nsa-border); border-radius: 3px; }

/* Rolling context */
.nsa-ctx-block {
    font-family: var(--mono);
    font-size: .72rem;
    color: var(--nsa-muted);
    background: rgba(0,0,0,.3);
    border: 1px solid var(--nsa-border);
    border-radius: 6px;
    padding: 12px 14px;
    margin-top: 4px;
}
.nsa-ctx-section { margin-bottom: 10px; }
.nsa-ctx-label {
    color: var(--nsa-amber-dim);
    font-size: .6rem;
    letter-spacing: .1em;
    text-transform: uppercase;
    margin-bottom: 3px;
}
.nsa-ctx-item {
    padding: 2px 0 2px 10px;
    position: relative;
    color: var(--nsa-text);
    font-size: .72rem;
    line-height: 1.45;
    border-left: 2px solid rgba(245,166,35,.2);
    margin-bottom: 3px;
}

/* Sketch analysis cross-ref panel */
.nsa-sa-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
@media (max-width: 600px) { .nsa-sa-grid { grid-template-columns: 1fr; } }
.nsa-sa-box {
    background: rgba(0,0,0,.25);
    border: 1px solid var(--nsa-border);
    border-radius: 6px;
    padding: 10px 12px;
}
.nsa-sa-box-title {
    font-family: var(--mono);
    font-size: .58rem;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--nsa-muted);
    margin-bottom: 6px;
}
.nsa-score-badge {
    display: inline-block;
    font-family: var(--mono);
    font-size: .8rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
    color: #fff;
}
.nsa-score-high { background: #10b981; }
.nsa-score-mid  { background: #f59e0b; }
.nsa-score-low  { background: #ef4444; }
.nsa-score-zero { background: #444; }

/* Sketch cross-links bar */
.nsa-sketch-links {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 12px;
}
.nsa-sketch-link {
    font-family: var(--mono);
    font-size: .65rem;
    padding: 4px 10px;
    border-radius: 4px;
    border: 1px solid var(--nsa-border);
    color: var(--nsa-muted);
    text-decoration: none;
    transition: all .15s;
    background: rgba(255,255,255,.02);
}
.nsa-sketch-link:hover { border-color: var(--nsa-amber); color: var(--nsa-amber); }

/* Top nav bar */
.nsa-nav {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    background: rgba(0,0,0,.4);
    border-bottom: 1px solid var(--nsa-border);
    position: sticky;
    top: 0;
    z-index: 200;
    backdrop-filter: blur(6px);
}
.nsa-nav-title {
    font-family: var(--mono);
    font-size: .7rem;
    color: var(--nsa-muted);
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.nsa-nav-link {
    font-family: var(--mono);
    font-size: .65rem;
    padding: 4px 10px;
    border: 1px solid var(--nsa-border);
    border-radius: 3px;
    color: var(--nsa-muted);
    text-decoration: none;
    white-space: nowrap;
    transition: all .15s;
    background: rgba(255,255,255,.02);
}
.nsa-nav-link:hover { color: var(--nsa-amber); border-color: var(--nsa-amber); }

/* Expand-all toggle */
.nsa-expand-all {
    font-family: var(--mono);
    font-size: .65rem;
    padding: 4px 10px;
    border: 1px solid var(--nsa-border);
    border-radius: 3px;
    color: var(--nsa-muted);
    cursor: pointer;
    background: rgba(255,255,255,.02);
    transition: all .15s;
}
.nsa-expand-all:hover { color: var(--nsa-amber); border-color: var(--nsa-amber); }

/* Section divider label */
.nsa-section-label {
    font-family: var(--mono);
    font-size: .58rem;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--nsa-muted);
    padding: 8px 0 4px;
    border-top: 1px solid rgba(255,255,255,.06);
    margin: 12px 0 6px;
}

/* ── SSA (sketch sequence analysis) fields ──────────────────────── */
.nsa-ssa-row {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 4px;
}
.nsa-ssa-chip {
    font-family: var(--mono);
    font-size: .6rem;
    padding: 2px 7px;
    border-radius: 3px;
}

/* Resolved tension pill — green */
.nsa-resolved-item {
    font-family: var(--sans);
    font-size: .8rem;
    color: var(--nsa-green);
    padding: 5px 10px 5px 22px;
    position: relative;
    border-bottom: 1px solid rgba(82,224,122,.08);
    line-height: 1.4;
}
.nsa-resolved-item:before { content: '✓'; position:absolute; left:5px; font-size:.7rem; top:7px; }

/* New tensions / motifs from compose */
.nsa-new-tension-item {
    font-family: var(--sans);
    font-size: .8rem;
    color: var(--nsa-crimson);
    padding: 5px 10px 5px 18px;
    position: relative;
    border-bottom: 1px solid rgba(224,82,82,.08);
    line-height: 1.4;
}
.nsa-new-tension-item:before { content: '↗'; position:absolute; left:4px; font-size:.7rem; top:6px; }
.nsa-new-motif-item {
    font-family: var(--sans);
    font-size: .8rem;
    color: var(--nsa-violet);
    padding: 5px 10px 5px 18px;
    position: relative;
    border-bottom: 1px solid rgba(155,109,255,.08);
    line-height: 1.4;
}
.nsa-new-motif-item:before { content: '◉'; position:absolute; left:4px; font-size:.6rem; top:8px; }

/* Description pre */
.nsa-description {
    font-family: var(--sans);
    font-size: .83rem;
    color: var(--nsa-muted);
    line-height: 1.65;
    white-space: pre-wrap;
    padding: 10px 12px;
    background: rgba(0,0,0,.25);
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 6px;
    max-height: 160px;
    overflow-y: auto;
}

/* ── Export Overlay Modal ────────────────────────────────────────── */
.nsa-export-btn {
    font-family: var(--mono);
    font-size: .65rem;
    padding: 5px 11px;
    border: 1px solid rgba(78,205,196,.4);
    border-radius: 3px;
    color: var(--nsa-teal);
    cursor: pointer;
    background: rgba(78,205,196,.06);
    transition: all .15s;
    white-space: nowrap;
    position: relative;
    z-index: 1;
}
.nsa-export-btn:hover { border-color: var(--nsa-teal); background: rgba(78,205,196,.12); }

.nsa-export-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.75);
    z-index: 500000;
    display: none;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(3px);
    padding: 16px;
}
.nsa-export-modal-backdrop.active { display: flex; }
.nsa-export-modal {
    width: 100%;
    max-width: 480px;
    background: var(--nsa-card);
    border: 1px solid var(--nsa-border);
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 12px 48px rgba(0,0,0,.6);
    max-height: 90vh;
    overflow-y: auto;
}
.nsa-export-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}
.nsa-export-modal-title {
    font-family: var(--mono);
    font-size: .8rem;
    color: var(--nsa-teal);
    text-transform: uppercase;
    letter-spacing: .1em;
}
.nsa-export-modal-close {
    background: transparent;
    border: none;
    color: var(--nsa-muted);
    cursor: pointer;
    font-size: 1.1rem;
    line-height: 1;
    padding: 2px 6px;
}
.nsa-export-modal-close:hover { color: var(--nsa-text); }
.nsa-export-modal-desc {
    font-family: var(--sans);
    font-size: .82rem;
    color: var(--nsa-muted);
    line-height: 1.55;
    margin-bottom: 16px;
}
.nsa-export-preview {
    background: rgba(0,0,0,.3);
    border: 1px solid var(--nsa-border);
    border-radius: 6px;
    padding: 12px 14px;
    margin-bottom: 16px;
    max-height: 220px;
    overflow-y: auto;
    font-family: var(--mono);
    font-size: .68rem;
    color: var(--nsa-muted);
    line-height: 1.6;
}
.nsa-export-preview-item {
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255,255,255,.05);
}
.nsa-export-preview-item:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
.nsa-export-preview-sketch {
    color: var(--nsa-teal);
    margin-bottom: 3px;
    font-size: .65rem;
}
.nsa-export-preview-text {
    color: var(--nsa-text);
    font-family: var(--sans);
    font-size: .78rem;
    line-height: 1.45;
}
.nsa-export-preview-missing {
    color: var(--nsa-amber-dim);
    font-style: italic;
}
.nsa-export-option {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 12px;
    padding: 10px 12px;
    border: 1px solid var(--nsa-border);
    border-radius: 6px;
    background: rgba(255,255,255,.02);
    cursor: pointer;
}
.nsa-export-option input[type="checkbox"] {
    margin-top: 2px;
    accent-color: var(--nsa-teal);
    flex-shrink: 0;
}
.nsa-export-option-label {
    font-family: var(--mono);
    font-size: .72rem;
    color: var(--nsa-text);
    line-height: 1.4;
}
.nsa-export-option-hint {
    font-family: var(--sans);
    font-size: .73rem;
    color: var(--nsa-muted);
    margin-top: 2px;
    line-height: 1.4;
}
.nsa-export-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 16px;
}
.nsa-export-cancel {
    font-family: var(--mono);
    font-size: .72rem;
    padding: 7px 14px;
    border: 1px solid var(--nsa-border);
    border-radius: 4px;
    background: transparent;
    color: var(--nsa-muted);
    cursor: pointer;
    transition: all .15s;
}
.nsa-export-cancel:hover { color: var(--nsa-text); border-color: var(--nsa-text); }
.nsa-export-confirm {
    font-family: var(--mono);
    font-size: .72rem;
    font-weight: 700;
    padding: 7px 16px;
    border: 1px solid var(--nsa-teal);
    border-radius: 4px;
    background: var(--nsa-teal);
    color: #0d0d0d;
    cursor: pointer;
    transition: all .15s;
}
.nsa-export-confirm:hover { filter: brightness(1.1); }
.nsa-export-confirm:disabled { opacity: .5; cursor: not-allowed; }
.nsa-export-progress {
    font-family: var(--mono);
    font-size: .68rem;
    color: var(--nsa-teal);
    text-align: center;
    padding: 8px 0 0;
    display: none;
}

/* Mobile responsive */
@media (max-width: 480px) {
    .nsa-header { padding: 18px 14px 14px; }
    .nsa-beat-tabs { flex-wrap: nowrap; }
    .nsa-beat-toggle { grid-template-columns: 32px 1fr auto; }
    .nsa-beat-thumb { width: 44px; }
}
</style>

<!-- ── Top nav ──────────────────────────────────────────────────────────────── -->
<div class="nsa-nav">
    <a href="view_narrative_sequence_analysis.php" class="nsa-nav-link">◀ All Sequences</a>
    <span class="nsa-nav-title">#<?= $seqId ?> — <?= htmlspecialchars($episodeTitle) ?></span>
    <a href="view_curated_sketches_analysis.php" class="nsa-nav-link">Sketch Analysis</a>
    <a href="view_curated_sketches.php" class="nsa-nav-link">Sketch Curation</a>
</div>

<!-- ── Episode Header ──────────────────────────────────────────────────────── -->
<div class="nsa-header">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;">
        <div class="nsa-ep-eyebrow">📽 Episode Document — Sequence #<?= $seqId ?></div>
        <?php if ($nsa): ?>
        <button class="nsa-export-btn" id="nsa-export-overlay-btn" title="Export overlay texts from beat summaries">
            ⬇ Export Overlay Texts
        </button>
        <?php endif; ?>
    </div>
    <h1 class="nsa-ep-title"><?= htmlspecialchars($episodeTitle) ?></h1>
    <?php if ($episodeSubtitle): ?>
        <div class="nsa-ep-subtitle"><?= htmlspecialchars($episodeSubtitle) ?></div>
    <?php endif; ?>

    <div class="nsa-ep-meta">
        <span class="nsa-meta-chip"><strong><?= $beatCount ?></strong> beats</span>
        <?php if ($modelUsed): ?><span class="nsa-meta-chip">model: <strong><?= htmlspecialchars($modelUsed) ?></strong></span><?php endif; ?>
        <?php if ($nsa && $nsa['updated_at']): ?><span class="nsa-meta-chip">synthesised: <strong><?= date('Y-m-d H:i', strtotime($nsa['updated_at'])) ?></strong></span><?php endif; ?>
        <span class="nsa-meta-chip">seq created: <strong><?= date('Y-m-d', strtotime($seq['created_at'])) ?></strong></span>
    </div>

    <?php if ($logline): ?>
        <div class="nsa-logline"><?= htmlspecialchars($logline) ?></div>
    <?php endif; ?>
    <?php if ($episodeThesis): ?>
        <div class="nsa-thesis"><?= htmlspecialchars($episodeThesis) ?></div>
    <?php endif; ?>

    <!-- Episode doc tabs -->
    <div class="nsa-doc-tabs" id="ep-doc-tabs">
        <?php if (!empty($actStructure)): ?><button class="nsa-doc-tab" data-panel="ep-acts">Act Structure</button><?php endif; ?>
        <?php if (!empty($recurringMotifs)): ?><button class="nsa-doc-tab" data-panel="ep-motifs">Recurring Motifs</button><?php endif; ?>
        <?php if (!empty($openTensions)): ?><button class="nsa-doc-tab" data-panel="ep-tensions">Open Tensions</button><?php endif; ?>
        <?php if (!empty($productionNotes)): ?><button class="nsa-doc-tab" data-panel="ep-prod">Production Notes</button><?php endif; ?>
        <?php if ($seq['description']): ?><button class="nsa-doc-tab" data-panel="ep-seq-desc">Sequence Info</button><?php endif; ?>
    </div>

    <!-- Act structure panel -->
    <?php if (!empty($actStructure)): ?>
    <div class="nsa-doc-panel" id="ep-acts">
        <?php foreach ($actStructure as $act):
            $aLabel = $act['label'] ?? $act['act_label'] ?? '—';
            $aDesc  = $act['description'] ?? '';
            $aScenes= $act['scene_titles'] ?? $act['scenes'] ?? [];
        ?>
            <div class="nsa-act-block">
                <div class="nsa-act-label"><?= htmlspecialchars($aLabel) ?></div>
                <?php if ($aDesc): ?><div class="nsa-act-desc"><?= htmlspecialchars($aDesc) ?></div><?php endif; ?>
                <?php foreach ($aScenes as $st): ?>
                    <span class="nsa-scene-pill"><?= htmlspecialchars($st) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Motifs panel -->
    <?php if (!empty($recurringMotifs)): ?>
    <div class="nsa-doc-panel" id="ep-motifs">
        <ul class="nsa-motif-list">
            <?php foreach ($recurringMotifs as $m): ?>
                <li><?= htmlspecialchars(is_array($m) ? json_encode($m) : (string)$m) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Open tensions panel -->
    <?php if (!empty($openTensions)): ?>
    <div class="nsa-doc-panel" id="ep-tensions">
        <ul class="nsa-tension-list">
            <?php foreach ($openTensions as $t): ?>
                <li><?= htmlspecialchars(is_array($t) ? json_encode($t) : (string)$t) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Production notes panel -->
    <?php if (!empty($productionNotes)): ?>
    <div class="nsa-doc-panel" id="ep-prod">
        <?php if (is_array($productionNotes)): ?>
            <ul class="nsa-motif-list">
                <?php foreach ($productionNotes as $note): ?>
                    <li><?= htmlspecialchars(is_array($note) ? json_encode($note) : (string)$note) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="nsa-prod-notes"><?= htmlspecialchars($productionNotes) ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Sequence info panel -->
    <?php if ($seq['description']): ?>
    <div class="nsa-doc-panel" id="ep-seq-desc">
        <div class="nsa-description"><?= htmlspecialchars($seq['description']) ?></div>
        <?php if ($seq['linked_doc_id']): ?>
            <div style="margin-top:8px; font-family:var(--mono); font-size:.65rem; color:var(--nsa-muted);">
                linked doc: <a href="view_kg_docs.php?doc_id=<?= (int)$seq['linked_doc_id'] ?>" style="color:var(--nsa-amber);">#<?= (int)$seq['linked_doc_id'] ?></a>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($nsa): ?>
<!-- ── Export Overlay Texts Modal ──────────────────────────────────────────── -->
<div class="nsa-export-modal-backdrop" id="nsa-export-modal-backdrop">
    <div class="nsa-export-modal">
        <div class="nsa-export-modal-header">
            <div class="nsa-export-modal-title">⬇ Export Overlay Texts</div>
            <button class="nsa-export-modal-close" id="nsa-export-modal-close">✕</button>
        </div>
        <div class="nsa-export-modal-desc">
            Populate English overlay texts for each sketch in this sequence using the beat summary from the narrative analysis. One overlay text block per sketch.
        </div>

        <!-- Preview list -->
        <div class="nsa-export-preview" id="nsa-export-preview">
            <?php foreach ($exportOverlayBeats as $eb): ?>
            <div class="nsa-export-preview-item">
                <div class="nsa-export-preview-sketch">Sketch #<?= $eb['sketch_id'] ?> &mdash; Beat <?= $eb['position'] + 1 ?></div>
                <?php if (!empty($eb['beat_summary'])): ?>
                    <div class="nsa-export-preview-text"><?= htmlspecialchars(mb_substr($eb['beat_summary'], 0, 120)) ?><?= mb_strlen($eb['beat_summary']) > 120 ? '…' : '' ?></div>
                <?php else: ?>
                    <div class="nsa-export-preview-missing">No beat summary — will be skipped</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Options -->
        <label class="nsa-export-option" for="nsa-export-overwrite">
            <input type="checkbox" id="nsa-export-overwrite">
            <div>
                <div class="nsa-export-option-label">Replace existing overlay texts</div>
                <div class="nsa-export-option-hint">If unchecked, sketches that already have English overlay texts will be skipped.</div>
            </div>
        </label>

        <div class="nsa-export-progress" id="nsa-export-progress">Exporting…</div>

        <div class="nsa-export-modal-footer">
            <button class="nsa-export-cancel" id="nsa-export-cancel">Cancel</button>
            <button class="nsa-export-confirm" id="nsa-export-confirm">Export</button>
        </div>
    </div>
</div>

<script>
// Beat data for export — PHP-injected
window._nsaExportBeats = <?= json_encode($exportOverlayBeats, JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php endif; ?>

<!-- ── Beat Timeline ────────────────────────────────────────────────────────── -->
<div class="nsa-timeline-wrap">
    <div class="nsa-timeline-header">
        <span>Beat Timeline — <?= count($allBeatData) ?> beats</span>
        <button class="nsa-expand-all" id="nsa-expand-all-btn">Expand All</button>
    </div>

    <?php foreach ($allBeatData as $bd):
        $pos        = $bd['position'];
        $sketchId   = $bd['sketch_id'];
        $sketchRow  = $bd['sketch'];
        $beatRow    = $bd['beat_row'];
        $ba         = $bd['beat_analyst'];
        $cd         = $bd['compose_data'];
        $rc         = $bd['rolling_ctx'];
        $frames     = $bd['frames'];
        $sa         = $bd['sa'];
        $ssa        = $bd['ssa'];
        $pinnedFid  = $bd['frame_id_hint'];

        // Resolve display fields
        $sceneTitle = '';
        if ($beatRow && $beatRow['scene_title']) $sceneTitle = $beatRow['scene_title'];
        elseif ($cd && !empty($cd['SCENE_TITLE'])) $sceneTitle = $cd['SCENE_TITLE'];
        elseif ($cd && !empty($cd['scene_title'])) $sceneTitle = $cd['scene_title'];
        elseif ($ba && !empty($ba['beat_name'])) $sceneTitle = $ba['beat_name'];
        elseif ($sketchRow) $sceneTitle = $sketchRow['name'];
        else $sceneTitle = "Beat $pos";

        $actLabel = '';
        if ($beatRow && $beatRow['act_label']) $actLabel = $beatRow['act_label'];
        elseif ($cd && !empty($cd['ACT_LABEL'])) $actLabel = $cd['ACT_LABEL'];
        elseif ($cd && !empty($cd['act_label'])) $actLabel = $cd['act_label'];
        elseif ($ba && !empty($ba['suggested_act_label'])) $actLabel = $ba['suggested_act_label'];

        $emotionalReg = '';
        if ($beatRow && $beatRow['emotional_register']) $emotionalReg = $beatRow['emotional_register'];
        elseif ($cd && !empty($cd['EMOTIONAL_REGISTER'])) $emotionalReg = $cd['EMOTIONAL_REGISTER'];
        elseif ($cd && !empty($cd['emotional_register'])) $emotionalReg = $cd['emotional_register'];
        elseif ($ba && !empty($ba['emotional_register'])) $emotionalReg = is_string($ba['emotional_register']) ? $ba['emotional_register'] : '';

        // Pick thumbnail: pinned frame first, else first frame
        $thumbFrame = null;
        if ($pinnedFid) {
            foreach ($frames as $f) { if ((int)$f['id'] === $pinnedFid) { $thumbFrame = $f; break; } }
        }
        if (!$thumbFrame && !empty($frames)) $thumbFrame = $frames[0];

        // Beat analyst fields
        $tensionType       = '';
        $narrativeFunc     = '';
        $beatPurpose       = '';
        $visualAnchors     = [];
        $characterStates   = [];
        if ($ba) {
            $tensionType     = is_string($ba['tension_type'] ?? '') ? ($ba['tension_type'] ?? '') : '';
            $narrativeFunc   = is_string($ba['narrative_function'] ?? '') ? ($ba['narrative_function'] ?? '') : '';
            $beatPurpose     = is_string($ba['beat_purpose'] ?? '') ? ($ba['beat_purpose'] ?? '') : '';
            $visualAnchors   = is_array($ba['visual_anchors'] ?? null) ? $ba['visual_anchors'] : [];
            $characterStates = is_array($ba['character_states'] ?? null) ? $ba['character_states'] : [];
        }

        // Composer fields
        $sceneProse     = '';
        $beatSummary    = '';
        $newTensions    = [];
        $newMotifs      = [];
        $resolvedTens   = [];
        if ($cd) {
            $sceneProse   = $cd['scene_prose']        ?? $cd['SCENE_PROSE']     ?? '';
            $beatSummary  = $cd['beat_summary']       ?? $cd['BEAT_SUMMARY']    ?? '';
            $newTensions  = (array)($cd['new_tensions']    ?? $cd['NEW_TENSIONS']   ?? []);
            $newMotifs    = (array)($cd['new_motifs']      ?? $cd['NEW_MOTIFS']     ?? []);
            $resolvedTens = (array)($cd['resolved_tensions']?? $cd['RESOLVED_TENSIONS'] ?? []);
        }

        $cardId = "beat-card-$pos";
    ?>
    <div class="nsa-beat-card" id="<?= $cardId ?>" data-pos="<?= $pos ?>">

        <!-- Toggle row -->
        <div class="nsa-beat-toggle" role="button" aria-expanded="false"
             onclick="nsaToggleBeat('<?= $cardId ?>')">

            <div class="nsa-beat-num"><?= $pos + 1 ?></div>

            <div class="nsa-beat-summary-row">
                <div class="nsa-beat-scene-title"><?= htmlspecialchars($sceneTitle) ?></div>
                <div class="nsa-beat-meta-row">
                    <?php if ($actLabel): ?>
                        <span class="nsa-act-chip"><?= htmlspecialchars($actLabel) ?></span>
                    <?php endif; ?>
                    <?php if ($emotionalReg): ?>
                        <span class="nsa-emotion-chip"><?= htmlspecialchars(mb_substr($emotionalReg, 0, 40)) ?></span>
                    <?php endif; ?>
                    <?php if ($sketchId): ?>
                        <span class="nsa-sketch-chip">sketch #<?= $sketchId ?></span>
                    <?php endif; ?>
                    <?php if ($tensionType): ?>
                        <span class="nsa-pill violet" style="font-size:.55rem;"><?= htmlspecialchars(mb_substr($tensionType,0,30)) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="nsa-beat-thumb">
                <?php if ($thumbFrame): ?>
                    <img src="<?= htmlspecialchars(ltrim($thumbFrame['filename'], '/')) ?>" loading="lazy" alt="">
                <?php else: ?>
                    <span class="no-thumb">🎞</span>
                <?php endif; ?>
            </div>

            <div class="nsa-beat-chevron">▶</div>
        </div>

        <!-- Body -->
        <div class="nsa-beat-body">

            <!-- Inner tab bar -->
            <div class="nsa-beat-tabs" id="tabs-<?= $cardId ?>">
                <?php if (!empty($frames)): ?>
                    <div class="nsa-beat-tab active" data-card="<?= $cardId ?>" data-panel="frames-<?= $cardId ?>">Frames</div>
                <?php endif; ?>
                <div class="nsa-beat-tab <?= empty($frames) ? 'active' : '' ?>" data-card="<?= $cardId ?>" data-panel="sketch-<?= $cardId ?>">Sketch</div>
                <?php if ($ba): ?>
                    <div class="nsa-beat-tab" data-card="<?= $cardId ?>" data-panel="analyst-<?= $cardId ?>">Beat Analyst</div>
                <?php endif; ?>
                <?php if ($cd): ?>
                    <div class="nsa-beat-tab" data-card="<?= $cardId ?>" data-panel="composer-<?= $cardId ?>">Scene Prose</div>
                    <?php if ($newTensions || $resolvedTens || $newMotifs): ?>
                        <div class="nsa-beat-tab" data-card="<?= $cardId ?>" data-panel="threads-<?= $cardId ?>">Threads</div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($rc): ?>
                    <div class="nsa-beat-tab" data-card="<?= $cardId ?>" data-panel="ctx-<?= $cardId ?>">Rolling Context</div>
                <?php endif; ?>
                <?php if ($sa || $ssa): ?>
                    <div class="nsa-beat-tab" data-card="<?= $cardId ?>" data-panel="xref-<?= $cardId ?>">Analysis X-Ref</div>
                <?php endif; ?>
            </div>

            <!-- ── FRAMES panel ────────────────────────────────────────── -->
            <?php if (!empty($frames)): ?>
            <div class="nsa-beat-panel visible" id="frames-<?= $cardId ?>">
                <div class="nsa-pswp-gallery swiper nsa-frames-swiper" id="swiper-<?= $cardId ?>">
                    <div class="swiper-wrapper">
                        <?php foreach ($frames as $frame):
                            $img = ltrim($frame['filename'], '/');
                            $fid = (int)$frame['id'];
                            $isPinned = ($fid === $pinnedFid);
                            $gearAttr = 'data-gear-menu data-entity="frames" data-entity-id="'.$fid.'" data-frame-id="'.$fid.'" data-img-url="'.htmlspecialchars($img).'"';
                        ?>
                        <div class="swiper-slide">
                            <div class="nsa-frame-mini" <?= $gearAttr ?>>
                                <div class="nsa-frame-mini-img">
                                    <?php if ($isPinned): ?><span class="nsa-frame-pinned">★</span><?php endif; ?>
                                    <?php if ($frame['rating'] > 0): ?><span class="nsa-frame-rating"><?= $frame['rating'] ?>★</span><?php endif; ?>
                                    <a href="<?= htmlspecialchars($img) ?>"
                                       class="nsa-pswp-item"
                                       data-pswp-width="1024" data-pswp-height="1024"
                                       target="_blank">
                                        <img src="<?= htmlspecialchars($img) ?>" loading="lazy" alt="" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                                    </a>
                                </div>
                                <div class="nsa-frame-mini-info">
                                    <span>#<?= $fid ?></span>
                                    <span class="frame-detail-link" data-frame-id="<?= $fid ?>" style="cursor:pointer;color:var(--nsa-amber);text-decoration:none;" title="Frame detail">⋯</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-scrollbar"></div>
                </div>
                <?php if ($beatSummary): ?>
                    <div style="margin-top:10px;">
                        <span class="nsa-field-label">Beat Summary</span>
                        <div class="nsa-field-value" style="font-style:italic;color:var(--nsa-muted);"><?= htmlspecialchars($beatSummary) ?></div>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ── SKETCH panel ────────────────────────────────────────── -->
            <div class="nsa-beat-panel <?= empty($frames) ? 'visible' : '' ?>" id="sketch-<?= $cardId ?>">
                <?php if ($sketchRow): ?>
                    <div class="nsa-field-group">
                        <span class="nsa-field-label">Name</span>
                        <div class="nsa-field-value" style="font-weight:700;"><?= htmlspecialchars($sketchRow['name']) ?></div>
                    </div>
                    <?php if ($sketchRow['description']): ?>
                    <div class="nsa-field-group">
                        <span class="nsa-field-label">Description / Prompt</span>
                        <div class="nsa-description"><?= htmlspecialchars($sketchRow['description']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($sketchRow['mood']): ?>
                    <div class="nsa-field-group">
                        <span class="nsa-field-label">Mood</span>
                        <div class="nsa-field-value nsa-muted"><?= htmlspecialchars($sketchRow['mood']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="nsa-sketch-links">
                        <a href="entity.php?type=sketches&id=<?= $sketchId ?>" class="nsa-sketch-link">🖼 Entity Page</a>
                        <a href="view_curated_sketches_analysis.php?char=<?= urlencode($sketchRow['name']) ?>" class="nsa-sketch-link">📜 Sketch Analysis View</a>
                        <a href="view_curated_sketches.php" class="nsa-sketch-link">🕵️ Sketch Curation</a>
                        <?php if (!empty($frames)): ?>
                            <a href="view_video_review.php?entity_type=sketches&entity_id=<?= $sketchId ?>" class="nsa-sketch-link">🎞 Frame Review</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="color:var(--nsa-muted);font-family:var(--mono);font-size:.75rem;">Sketch #<?= $sketchId ?> not found.</div>
                <?php endif; ?>
            </div>

            <!-- ── BEAT ANALYST panel ──────────────────────────────────── -->
            <?php if ($ba): ?>
            <div class="nsa-beat-panel" id="analyst-<?= $cardId ?>">
                <?php
                // Helper: render a string or array value
                $renderVal = function($v): string {
                    if (is_string($v)) return htmlspecialchars($v);
                    if (is_array($v))  return htmlspecialchars(implode(' | ', array_map(fn($x) => is_array($x) ? implode(': ',$x) : (string)$x, $v)));
                    return htmlspecialchars((string)$v);
                };
                ?>

                <?php if (!empty($ba['emotional_register'])): ?>
                <div class="nsa-field-group">
                    <span class="nsa-field-label">Emotional Register</span>
                    <div class="nsa-field-value"><?= $renderVal($ba['emotional_register']) ?></div>
                    <?php if (!empty($ba['emotional_register_definition'])): ?>
                        <div style="color:var(--nsa-muted);font-size:.78rem;margin-top:3px;line-height:1.45;"><?= htmlspecialchars($ba['emotional_register_definition']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($ba['tension_type'])): ?>
                <div class="nsa-field-group">
                    <span class="nsa-field-label">Tension Type</span>
                    <div class="nsa-pill-group">
                        <?php foreach ((array)$ba['tension_type'] as $tt): ?>
                            <span class="nsa-pill violet"><?= htmlspecialchars(is_array($tt) ? json_encode($tt) : (string)$tt) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($ba['tension_type_definition']) || !empty($ba['tension_detail'])): ?>
                        <div style="color:var(--nsa-muted);font-size:.78rem;margin-top:5px;"><?= htmlspecialchars($ba['tension_type_definition'] ?? $ba['tension_detail'] ?? '') ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($ba['narrative_function'])): ?>
                <div class="nsa-field-group">
                    <span class="nsa-field-label">Narrative Function</span>
                    <div class="nsa-field-value"><?= $renderVal($ba['narrative_function']) ?></div>
                    <?php if (!empty($ba['narrative_function_definition']) || !empty($ba['narrative_function_detail'])): ?>
                        <div style="color:var(--nsa-muted);font-size:.78rem;margin-top:3px;"><?= htmlspecialchars($ba['narrative_function_definition'] ?? $ba['narrative_function_detail'] ?? '') ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($ba['beat_purpose'])): ?>
                <div class="nsa-field-group">
                    <span class="nsa-field-label">Beat Purpose</span>
                    <div class="nsa-field-value" style="font-style:italic;"><?= htmlspecialchars($ba['beat_purpose']) ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($visualAnchors)): ?>
                <div class="nsa-field-group">
                    <span class="nsa-field-label">Visual Anchors</span>
                    <div class="nsa-pill-group">
                        <?php foreach ($visualAnchors as $va): ?>
                            <span class="nsa-pill teal"><?= htmlspecialchars(is_array($va) ? json_encode($va) : (string)$va) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($characterStates)): ?>
                <div class="nsa-field-group">
                    <span class="nsa-field-label">Character States</span>
                    <table class="nsa-char-table">
                        <thead>
                            <tr>
                                <th>Character</th>
                                <th>State</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($characterStates as $cs):
                                if (is_array($cs)) {
                                    $cName  = $cs['character'] ?? $cs['name'] ?? (array_key_first($cs) ?: '?');
                                    $cState = $cs['state'] ?? $cs['internal_state'] ?? $cs['condition'] ?? (array_values($cs)[1] ?? '');
                                    if (is_array($cState)) $cState = implode('; ', $cState);
                                    $cCtx   = $cs['context'] ?? $cs['internal_condition'] ?? '';
                                } else {
                                    $cName = (string)$cs; $cState = ''; $cCtx = '';
                                }
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($cName) ?></td>
                                    <td>
                                        <?= htmlspecialchars($cState) ?>
                                        <?php if ($cCtx): ?>
                                            <div style="color:var(--nsa-muted);font-size:.72rem;margin-top:2px;"><?= htmlspecialchars(is_array($cCtx) ? json_encode($cCtx) : $cCtx) ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php
                // Extra analyst fields not handled above
                $skipKeys = ['beat_id','beat_name','emotional_register','emotional_register_definition','tension_type','tension_type_definition','tension_detail','narrative_function','narrative_function_definition','narrative_function_detail','beat_purpose','visual_anchors','character_states','suggested_act_label'];
                foreach ($ba as $bak => $bav):
                    if (in_array($bak, $skipKeys)) continue;
                    if (empty($bav) && $bav !== 0) continue;
                    // Skip deep structural nodes — show as json pill
                    $dispVal = is_array($bav) ? json_encode($bav, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) : (string)$bav;
                ?>
                <div class="nsa-field-group">
                    <span class="nsa-field-label"><?= htmlspecialchars(ucwords(str_replace('_',' ',$bak))) ?></span>
                    <?php if (is_array($bav)): ?>
                        <div class="nsa-field-value mono" style="white-space:pre-wrap;font-size:.7rem;color:var(--nsa-muted);max-height:160px;overflow-y:auto;"><?= htmlspecialchars($dispVal) ?></div>
                    <?php else: ?>
                        <div class="nsa-field-value"><?= htmlspecialchars($dispVal) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ── SCENE PROSE panel ───────────────────────────────────── -->
            <?php if ($cd && $sceneProse): ?>
            <div class="nsa-beat-panel" id="composer-<?= $cardId ?>">
                <div class="nsa-prose-wrap"><?= htmlspecialchars($sceneProse) ?></div>
            </div>
            <?php elseif ($cd): ?>
            <div class="nsa-beat-panel" id="composer-<?= $cardId ?>">
                <?php
                $skipComp = ['scene_title','SCENE_TITLE','act_label','ACT_LABEL','scene_prose','SCENE_PROSE','beat_summary','BEAT_SUMMARY','emotional_register','EMOTIONAL_REGISTER','new_tensions','NEW_TENSIONS','new_motifs','NEW_MOTIFS','resolved_tensions','RESOLVED_TENSIONS'];
                foreach ($cd as $ck => $cv):
                    if (in_array($ck, $skipComp)) continue;
                    if (empty($cv)) continue;
                ?>
                <div class="nsa-field-group">
                    <span class="nsa-field-label"><?= htmlspecialchars(ucwords(str_replace('_',' ', strtolower($ck)))) ?></span>
                    <div class="nsa-field-value"><?= htmlspecialchars(is_array($cv) ? json_encode($cv, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) : (string)$cv) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ── THREADS panel (new/resolved tensions & motifs) ─────── -->
            <?php if ($cd && ($newTensions || $resolvedTens || $newMotifs)): ?>
            <div class="nsa-beat-panel" id="threads-<?= $cardId ?>">
                <?php if (!empty($resolvedTens)): ?>
                    <div class="nsa-section-label">Resolved Tensions</div>
                    <?php foreach ($resolvedTens as $rt): ?>
                        <div class="nsa-resolved-item"><?= htmlspecialchars(is_array($rt) ? json_encode($rt) : (string)$rt) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($newTensions)): ?>
                    <div class="nsa-section-label">New Tensions Introduced</div>
                    <?php foreach ($newTensions as $nt): ?>
                        <div class="nsa-new-tension-item"><?= htmlspecialchars(is_array($nt) ? json_encode($nt) : (string)$nt) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($newMotifs)): ?>
                    <div class="nsa-section-label">New Motifs Established</div>
                    <?php foreach ($newMotifs as $nm): ?>
                        <div class="nsa-new-motif-item"><?= htmlspecialchars(is_array($nm) ? json_encode($nm) : (string)$nm) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ── ROLLING CONTEXT panel ──────────────────────────────── -->
            <?php if ($rc): ?>
            <div class="nsa-beat-panel" id="ctx-<?= $cardId ?>">
                <div class="nsa-ctx-block">
                    <?php if (!empty($rc['emotional_temperature'])): ?>
                    <div class="nsa-ctx-section">
                        <div class="nsa-ctx-label">Emotional Temperature</div>
                        <div class="nsa-ctx-item"><?= htmlspecialchars($rc['emotional_temperature']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($rc['last_beat_summary'])): ?>
                    <div class="nsa-ctx-section">
                        <div class="nsa-ctx-label">Last Beat Summary</div>
                        <div class="nsa-ctx-item"><?= htmlspecialchars($rc['last_beat_summary']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($rc['open_tensions'])): ?>
                    <div class="nsa-ctx-section">
                        <div class="nsa-ctx-label">Open Tensions at this Point</div>
                        <?php foreach ($rc['open_tensions'] as $ot): ?>
                            <div class="nsa-ctx-item"><?= htmlspecialchars(is_array($ot) ? json_encode($ot) : (string)$ot) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($rc['established_motifs'])): ?>
                    <div class="nsa-ctx-section">
                        <div class="nsa-ctx-label">Established Motifs at this Point</div>
                        <?php foreach ($rc['established_motifs'] as $em): ?>
                            <div class="nsa-ctx-item"><?= htmlspecialchars(is_array($em) ? json_encode($em) : (string)$em) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($rc['acts_so_far'])): ?>
                    <div class="nsa-ctx-section">
                        <div class="nsa-ctx-label">Acts Written So Far</div>
                        <?php foreach ($rc['acts_so_far'] as $act): ?>
                            <div class="nsa-ctx-item" style="margin-bottom:5px;"><?= htmlspecialchars(is_array($act) ? json_encode($act) : (string)$act) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── ANALYSIS X-REF panel ───────────────────────────────── -->
            <?php if ($sa || $ssa): ?>
            <div class="nsa-beat-panel" id="xref-<?= $cardId ?>">
                <div class="nsa-sa-grid">

                    <!-- Sketch Analysis box -->
                    <?php if ($sa): ?>
                    <div class="nsa-sa-box" style="grid-column:1/-1;">
                        <div class="nsa-sa-box-title">Sketch Analysis (sketch_analysis)</div>
                        <?php
                        $saScore = (float)($sa['overall_quality'] ?? 0);
                        $saClass = $saScore >= 8 ? 'nsa-score-high' : ($saScore >= 5 ? 'nsa-score-mid' : ($saScore > 0 ? 'nsa-score-low' : 'nsa-score-zero'));
                        $saClassif = is_array($sa['classification'] ?? null) ? $sa['classification'] : [];
                        $saThemes  = is_array($sa['thematics']     ?? null) ? $sa['thematics']     : [];
                        $saEntities= is_array($sa['entities']      ?? null) ? $sa['entities']      : [];
                        $saRecs    = is_array($sa['recommendations']?? null) ? $sa['recommendations']: [];
                        ?>
                        <div style="display:flex;gap:10px;align-items:center;margin-bottom:10px;flex-wrap:wrap;">
                            <span class="nsa-score-badge <?= $saClass ?>"><?= $saScore ?></span>
                            <?php if (!empty($saClassif['narrative_function'])): ?>
                                <span class="nsa-pill amber"><?= htmlspecialchars(is_string($saClassif['narrative_function']) ? $saClassif['narrative_function'] : ($saClassif['narrative_function']['name'] ?? json_encode($saClassif['narrative_function']))) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($saClassif['emotional_tone'])): ?>
                                <span class="nsa-pill"><?= htmlspecialchars(is_string($saClassif['emotional_tone']) ? $saClassif['emotional_tone'] : ($saClassif['emotional_tone']['name'] ?? '')) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($saThemes['primary_themes'])): ?>
                            <div class="nsa-field-group">
                                <span class="nsa-field-label">Themes</span>
                                <div class="nsa-pill-group">
                                    <?php foreach ((array)$saThemes['primary_themes'] as $t): ?>
                                        <span class="nsa-pill"><?= htmlspecialchars(is_array($t) ? ($t['name']??$t['theme']??json_encode($t)) : (string)$t) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($saEntities['characters'])): ?>
                            <div class="nsa-field-group">
                                <span class="nsa-field-label">Characters</span>
                                <div class="nsa-pill-group">
                                    <?php foreach ((array)$saEntities['characters'] as $c): ?>
                                        <span class="nsa-pill teal">👤 <?= htmlspecialchars(is_array($c) ? ($c['name']??$c['character']??json_encode($c)) : (string)$c) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($saEntities['locations'])): ?>
                            <div class="nsa-field-group">
                                <span class="nsa-field-label">Locations</span>
                                <div class="nsa-pill-group">
                                    <?php foreach ((array)$saEntities['locations'] as $l): ?>
                                        <span class="nsa-pill"><?= htmlspecialchars(is_array($l) ? ($l['name']??json_encode($l)) : (string)$l) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php
                        $saScoring = is_array($sa['scoring'] ?? null) ? $sa['scoring'] : [];
                        if (!empty($saScoring)):
                        ?>
                            <div class="nsa-field-group">
                                <span class="nsa-field-label">Scoring</span>
                                <div class="nsa-pill-group">
                                    <?php foreach ($saScoring as $sk => $sv): ?>
                                        <span class="nsa-pill amber"><?= htmlspecialchars($sk) ?>: <strong><?= htmlspecialchars(is_array($sv) ? json_encode($sv) : (string)$sv) ?></strong></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($saRecs['potential_use'])): ?>
                            <div class="nsa-field-group">
                                <span class="nsa-field-label">Potential Use</span>
                                <div class="nsa-field-value" style="font-style:italic;color:var(--nsa-muted);"><?= htmlspecialchars(is_string($saRecs['potential_use']) ? $saRecs['potential_use'] : ($saRecs['potential_use']['description'] ?? json_encode($saRecs['potential_use']))) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Sketch Sequence Analysis box -->
                    <?php if ($ssa): ?>
                    <div class="nsa-sa-box" style="grid-column:1/-1;">
                        <div class="nsa-sa-box-title">Sketch Sequence Analysis (sketch_sequence_analysis)</div>
                        <div class="nsa-ssa-row">
                            <?php
                            $ssaEnum = [
                                'energy'              => 'teal',
                                'position'            => '',
                                'intensity'           => 'amber',
                                'shot_scale'          => '',
                                'edit_relationship'   => '',
                                'structure_type'      => 'violet',
                                'fabula_position'     => '',
                                'syuzhet_position'    => '',
                                'character_presence'  => '',
                                'world_specificity'   => '',
                                'standalone'          => '',
                            ];
                            foreach ($ssaEnum as $sf => $color):
                                if (empty($ssa[$sf])) continue;
                            ?>
                                <span class="nsa-ssa-chip nsa-pill <?= $color ? $color : '' ?>"><?= htmlspecialchars($sf) ?>: <strong><?= htmlspecialchars((string)$ssa[$sf]) ?></strong></span>
                            <?php endforeach; ?>
                            <?php if (isset($ssa['confidence']) && $ssa['confidence'] > 0): ?>
                                <span class="nsa-ssa-chip nsa-pill">conf: <?= round((float)$ssa['confidence'], 2) ?></span>
                            <?php endif; ?>
                            <?php if (isset($ssa['novelty']) && $ssa['novelty'] !== null): ?>
                                <span class="nsa-ssa-chip nsa-pill">novelty: <?= round((float)$ssa['novelty'], 2) ?></span>
                            <?php endif; ?>
                            <?php if (isset($ssa['thematic_relevance']) && $ssa['thematic_relevance'] !== null): ?>
                                <span class="nsa-ssa-chip nsa-pill">thematic_rel: <?= round((float)$ssa['thematic_relevance'], 2) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($ssa['short_logline'])): ?>
                            <div class="nsa-field-group" style="margin-top:10px;">
                                <span class="nsa-field-label">Short Logline</span>
                                <div class="nsa-field-value" style="font-style:italic;"><?= htmlspecialchars($ssa['short_logline']) ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($ssa['connective_hint'])): ?>
                            <div class="nsa-field-group">
                                <span class="nsa-field-label">Connective Hint</span>
                                <div class="nsa-field-value"><?= htmlspecialchars($ssa['connective_hint']) ?></div>
                            </div>
                        <?php endif; ?>
                        <?php
                        $ssaNF = is_array($ssa['narrative_function'] ?? null) ? $ssa['narrative_function'] : [];
                        if (!empty($ssaNF)):
                        ?>
                            <div class="nsa-field-group">
                                <span class="nsa-field-label">Narrative Function(s)</span>
                                <div class="nsa-pill-group">
                                    <?php foreach ((array)$ssaNF as $nf): ?>
                                        <span class="nsa-pill violet"><?= htmlspecialchars(is_array($nf) ? json_encode($nf) : (string)$nf) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php
                        $ssaLayer = is_array($ssa['layer'] ?? null) ? $ssa['layer'] : [];
                        if (!empty($ssaLayer)):
                        ?>
                            <div class="nsa-field-group">
                                <span class="nsa-field-label">Layer</span>
                                <div class="nsa-pill-group">
                                    <?php foreach ((array)$ssaLayer as $l): ?>
                                        <span class="nsa-pill"><?= htmlspecialchars(is_array($l) ? json_encode($l) : (string)$l) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php
                        $ssaTags = is_array($ssa['tags'] ?? null) ? $ssa['tags'] : [];
                        if (!empty($ssaTags)):
                        ?>
                            <div class="nsa-field-group">
                                <span class="nsa-field-label">Tags</span>
                                <div class="nsa-pill-group">
                                    <?php foreach ((array)$ssaTags as $tg): ?>
                                        <span class="nsa-pill"><?= htmlspecialchars(is_array($tg) ? json_encode($tg) : (string)$tg) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </div><!-- /nsa-sa-grid -->
            </div>
            <?php endif; ?>

        </div><!-- /nsa-beat-body -->
    </div><!-- /nsa-beat-card -->
    <?php endforeach; ?>

</div><!-- /nsa-timeline-wrap -->


<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/gear_menu_globals.js"></script>
<?= $gearMenu->render() ?>
<?= $imageEditor->render() ?>
<?= $frameDetailsModal ?>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

<script>
$(function() {

    // ── Gear menu attach ──────────────────────────────────────────
    function attachGearMenu() {
        if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
            window.GearMenu.attach(document.body);
        } else { setTimeout(attachGearMenu, 200); }
    }
    attachGearMenu();

    // ── PhotoSwipe ────────────────────────────────────────────────
    if (window.initLightbox) window.initLightbox();

    // ── Swipers (init on beat open to avoid layout issues) ────────
    const initializedSwipers = new Set();
    function initSwiper(cardId) {
        if (initializedSwipers.has(cardId)) return;
        const el = document.getElementById('swiper-' + cardId);
        if (!el) return;
        new Swiper(el, {
            slidesPerView: 'auto',
            spaceBetween: 10,
            freeMode: true,
            scrollbar: { el: el.querySelector('.swiper-scrollbar'), hide: true },
            slidesOffsetBefore: 12,
            slidesOffsetAfter: 12,
        });
        initializedSwipers.add(cardId);
    }

    // ── Beat card toggle ──────────────────────────────────────────
    window.nsaToggleBeat = function(cardId) {
        const card = document.getElementById(cardId);
        if (!card) return;
        const isOpen = card.classList.contains('open');
        card.classList.toggle('open', !isOpen);
        if (!isOpen) initSwiper(cardId);
    };

    // ── Expand/collapse all ───────────────────────────────────────
    let allExpanded = false;
    $('#nsa-expand-all-btn').on('click', function() {
        allExpanded = !allExpanded;
        $('.nsa-beat-card').each(function() {
            const id = this.id;
            this.classList.toggle('open', allExpanded);
            if (allExpanded) initSwiper(id);
        });
        $(this).text(allExpanded ? 'Collapse All' : 'Expand All');
    });

    // ── Beat inner tab switching ──────────────────────────────────
    $(document).on('click', '.nsa-beat-tab', function() {
        const cardId = $(this).data('card');
        const panelId = $(this).data('panel');
        // deactivate tabs in this card
        $('#tabs-' + cardId + ' .nsa-beat-tab').removeClass('active');
        $(this).addClass('active');
        // hide all panels in this card's body
        $(this).closest('.nsa-beat-body').find('.nsa-beat-panel').removeClass('visible');
        $('#' + panelId).addClass('visible');
    });

    // ── Episode doc tabs ──────────────────────────────────────────
    $(document).on('click', '#ep-doc-tabs .nsa-doc-tab', function() {
        const panelId = $(this).data('panel');
        const isActive = $(this).hasClass('active');
        // toggle: click active tab → close panel
        if (isActive) {
            $(this).removeClass('active');
            $('#' + panelId).removeClass('visible');
            return;
        }
        // switch to new panel
        $('#ep-doc-tabs .nsa-doc-tab').removeClass('active');
        $('.nsa-doc-panel').removeClass('visible');
        $(this).addClass('active');
        $('#' + panelId).addClass('visible');
    });

    // ── Frame detail modal trigger ────────────────────────────────
    $(document).on('click', '.frame-detail-link', function(e) {
        e.stopPropagation();
        const fid = $(this).data('frame-id');
        if (window.openFrameDetailsModal) {
            window.openFrameDetailsModal(fid);
        }
    });

    // ── Export Overlay Texts modal ────────────────────────────────
    const exportBtn      = document.getElementById('nsa-export-overlay-btn');
    const exportBackdrop = document.getElementById('nsa-export-modal-backdrop');
    const exportClose    = document.getElementById('nsa-export-modal-close');
    const exportCancel   = document.getElementById('nsa-export-cancel');
    const exportConfirm  = document.getElementById('nsa-export-confirm');
    const exportProgress = document.getElementById('nsa-export-progress');

    if (exportBtn && exportBackdrop) {
        exportBtn.addEventListener('click', function() {
            exportBackdrop.classList.add('active');
        });

        exportClose.addEventListener('click', closeExportModal);
        exportCancel.addEventListener('click', closeExportModal);

        exportBackdrop.addEventListener('mousedown', function(e) {
            if (e.target === exportBackdrop) closeExportModal();
        });

        exportConfirm.addEventListener('click', function() {
            const beats = window._nsaExportBeats || [];
            const overwrite = document.getElementById('nsa-export-overwrite').checked;

            // Filter to only beats that have a beat_summary
            const toExport = beats.filter(function(b) {
                return b.beat_summary && b.beat_summary.trim() !== '';
            });

            if (!toExport.length) {
                Toast.show('No beat summaries available to export.', 'warn');
                return;
            }

            exportConfirm.disabled = true;
            exportProgress.style.display = 'block';
            exportProgress.textContent = 'Exporting 0 / ' + toExport.length + '…';

            const fd = new URLSearchParams();
            fd.append('action', 'export_overlay_texts');
            fd.append('overwrite', overwrite ? '1' : '0');
            fd.append('beats', JSON.stringify(toExport));

            fetch('cinemagic_editor_api.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    exportConfirm.disabled = false;
                    exportProgress.style.display = 'none';
                    if (res.success) {
                        var msg = 'Exported ' + res.inserted + ' overlay text(s).';
                        if (res.skipped > 0) msg += ' ' + res.skipped + ' skipped (already exist).';
                        Toast.show(msg, 'success');
                        closeExportModal();
                    } else {
                        Toast.show(res.message || 'Export failed.', 'error');
                    }
                })
                .catch(function() {
                    exportConfirm.disabled = false;
                    exportProgress.style.display = 'none';
                    Toast.show('Network error during export.', 'error');
                });
        });
    }

    function closeExportModal() {
        if (exportBackdrop) exportBackdrop.classList.remove('active');
    }

});
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>