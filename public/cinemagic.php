<?php
// public/cinematic.php
// Cinematic story presentation — dynamic rendering.
// Incorporates Fuki absolute positioning text overlay integration.

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$langCode = strtolower($_GET['lang'] ?? 'en');

function firstExistingValue(array $row, array $keys, $default = '') {
    foreach ($keys as $key) {
        if (isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null) {
            return $row[$key];
        }
    }
    return $default;
}

function resolveFrameThumb(array $row, int $frameId = 0): string {
    $candidate = '';
    foreach (['thumb', 'thumbnail', 'image', 'image_url', 'image_path', 'file_path', 'path', 'src', 'url', 'filename', 'file_name'] as $key) {
        if (!empty($row[$key]) && is_string($row[$key])) {
            $candidate = $row[$key];
            break;
        }
    }
    if ($candidate !== '') {
        if (strpos($candidate, 'http') !== 0 && strpos($candidate, 'view_frame.php') === false) {
            $parts = array_map('rawurlencode', explode('/', ltrim($candidate, '/')));
            return '/' . implode('/', $parts);
        }
        return $candidate;
    }
    return $frameId > 0 ? 'view_frame.php?frame_id=' . $frameId : '';
}

// 1. Fetch the sequence
$seqId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$table = 'narrative_sequences';

$seq = null;
if ($seqId) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$seqId]);
    $seq = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $seq = $pdo->query("SELECT * FROM $table ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($seq) $seqId = (int)$seq['id'];
}

if (!$seq) {
    die("<div style='background:#000; color:#fff; font-family:sans-serif; padding:50px; text-align:center;'>Sequence not found.</div>");
}

// 2. Check if this sequence belongs to any Cinemagic (for episode nav)
$cinemagicEpisodes = [];   // episodes in the same cinemagic
$cinemagicInfo     = null; // the parent cinemagic
$currentEpisodeIdx = 0;
$availableLangs    = ['en'];

try {
    $stmtCM = $pdo->prepare(
        "SELECT c.id, c.name, s.supported_languages
         FROM cinemagics c
         JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = c.id
         LEFT JOIN cinemagic_series_2_cinemagics sc ON sc.cinemagic_id = c.id
         LEFT JOIN cinemagic_series s ON s.id = sc.series_id
         WHERE cs.sequence_id = ?
         ORDER BY c.id ASC
         LIMIT 1"
    );
    $stmtCM->execute([$seqId]);
    $cinemagicInfo = $stmtCM->fetch(PDO::FETCH_ASSOC);

    if ($cinemagicInfo) {
        $langsRaw = $cinemagicInfo['supported_languages'] ?? 'en';
        $availableLangs = array_filter(array_map('trim', explode(',', $langsRaw)));
        if (!in_array('en', $availableLangs)) array_unshift($availableLangs, 'en');

        $stmtEp = $pdo->prepare(
            "SELECT ns.id, ns.name, cs.sort_order, cs.chapter_label
             FROM narrative_sequences ns
             JOIN cinemagics_2_sequences cs ON cs.sequence_id = ns.id
             WHERE cs.cinemagic_id = ?
             ORDER BY cs.sort_order ASC, ns.id ASC"
        );
        $stmtEp->execute([(int)$cinemagicInfo['id']]);
        $cinemagicEpisodes = $stmtEp->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cinemagicEpisodes as $i => $ep) {
            if ((int)$ep['id'] === $seqId) { $currentEpisodeIdx = $i; break; }
        }
    }
} catch (PDOException $e) {} 

$showEpisodeNav = count($cinemagicEpisodes) > 1;

// 3. Build frames from sequence_data
$itemIds = json_decode($seq['sequence_data'] ?? '[]', true);
if (!is_array($itemIds)) $itemIds = [];

$pureSketchIds    = [];
$selectedFrameIds = [];

foreach ($itemIds as $idx => $item) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    if ($sid > 0) $pureSketchIds[] = $sid;
    $selectedFrameIds[$idx] = (is_array($item) && !empty($item['frame_id'])) ? (int)$item['frame_id'] : null;
}
$pureSketchIds = array_values(array_unique($pureSketchIds));

$sketchesData = [];
if (!empty($pureSketchIds)) {
    $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));
    $stmtS = $pdo->prepare("SELECT * FROM sketches WHERE id IN ($inClause)");
    $stmtS->execute($pureSketchIds);
    foreach ($stmtS->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sketchesData[(int)$row['id']] = $row;
    }
}

$selectedFrameMap = [];
$activeFrameIds   = array_values(array_unique(array_filter($selectedFrameIds)));
if (!empty($activeFrameIds)) {
    $inClauseFrames = implode(',', array_fill(0, count($activeFrameIds), '?'));
    $stmtF = $pdo->prepare("SELECT * FROM frames WHERE id IN ($inClauseFrames)");
    $stmtF->execute($activeFrameIds);
    foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $fid = (int)$row['id'];
        $selectedFrameMap[$fid] = ['row' => $row, 'thumb' => resolveFrameThumb($row, $fid)];
    }
}

$sketchIdsNeedingLatestFrame = [];
foreach ($itemIds as $idx => $item) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    if ($sid > 0 && empty($selectedFrameIds[$idx])) $sketchIdsNeedingLatestFrame[] = $sid;
}
$sketchIdsNeedingLatestFrame = array_values(array_unique($sketchIdsNeedingLatestFrame));

$latestFrameBySketch = [];
if (!empty($sketchIdsNeedingLatestFrame)) {
    $inClauseFb = implode(',', array_fill(0, count($sketchIdsNeedingLatestFrame), '?'));
    $stmtFb = $pdo->prepare(
        "SELECT f.*, f.entity_id AS _sketch_id
         FROM frames f
         INNER JOIN frames_2_sketches m ON m.from_id = f.id
         WHERE f.entity_id IN ($inClauseFb)
         ORDER BY f.id DESC"
    );
    $stmtFb->execute($sketchIdsNeedingLatestFrame);
    foreach ($stmtFb->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sketchId = (int)$row['_sketch_id'];
        if (!isset($latestFrameBySketch[$sketchId])) {
            $fid = (int)$row['id'];
            $latestFrameBySketch[$sketchId] = ['frame_id' => $fid, 'row' => $row, 'thumb' => resolveFrameThumb($row, $fid)];
        }
    }
}

// Load overlay texts
$overlayTexts = [];
$overlayTextsLang = [];
if (!empty($pureSketchIds)) {
    $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));
    try {
        $stmtO = $pdo->prepare("SELECT sketch_id, text_content, language_code, display_order FROM sketch_overlay_texts WHERE sketch_id IN ($inClause) AND language_code IN ('en', ?) ORDER BY display_order ASC, id ASC");
        $stmtO->execute([...$pureSketchIds, $langCode]);
        foreach ($stmtO->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['language_code'] === 'en') {
                $overlayTexts[(int)$row['sketch_id']][] = $row;
            } else {
                $overlayTextsLang[(int)$row['sketch_id']][$row['display_order']] = $row['text_content'];
            }
        }
    } catch (PDOException $e) {}
}

// Load Fuki Texts (Absolute positioning overlays)
$fukiTexts = [];
if (!empty($pureSketchIds)) {
    $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));
    try {
        $stmtFuki = $pdo->prepare("SELECT * FROM fuki_texts WHERE sketch_id IN ($inClause) AND language_code IN ('en', ?) ORDER BY id ASC");
        $stmtFuki->execute([...$pureSketchIds, $langCode]);
        $fukiRaw = $stmtFuki->fetchAll(PDO::FETCH_ASSOC);
        
        $fukiMap = [];
        foreach ($fukiRaw as $r) {
            if ($r['language_code'] === 'en') {
                $fukiMap[$r['sketch_id']][$r['element_uid']] = $r;
            }
        }
        if ($langCode !== 'en') {
            foreach ($fukiRaw as $r) {
                if ($r['language_code'] === $langCode) {
                    $fukiMap[$r['sketch_id']][$r['element_uid']] = array_merge(
                        $fukiMap[$r['sketch_id']][$r['element_uid']] ?? [], 
                        $r
                    );
                }
            }
        }
        foreach ($fukiMap as $skId => $uidMap) {
            $fukiTexts[$skId] = array_values($uidMap);
        }
    } catch (PDOException $e) {}
}

// Assemble frames
$frames = [];
foreach ($itemIds as $idx => $item) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    if ($sid <= 0 || !isset($sketchesData[$sid])) continue;

    $sketchRow    = $sketchesData[$sid];
    $activeFrameId = $selectedFrameIds[$idx] ?? null;
    $activeThumb   = '';

    if ($activeFrameId && isset($selectedFrameMap[$activeFrameId])) {
        $activeThumb = $selectedFrameMap[$activeFrameId]['thumb'];
    } elseif (!$activeFrameId && isset($latestFrameBySketch[$sid])) {
        $activeThumb   = $latestFrameBySketch[$sid]['thumb'];
        $activeFrameId = $latestFrameBySketch[$sid]['frame_id'];
    }

    $overrideTexts = [];
    if (isset($overlayTexts[$sid])) {
        foreach ($overlayTexts[$sid] as $enRow) {
            $dispOrder = $enRow['display_order'];
            if ($langCode !== 'en' && isset($overlayTextsLang[$sid][$dispOrder]) && trim($overlayTextsLang[$sid][$dispOrder]) !== '') {
                $overrideTexts[] = trim($overlayTextsLang[$sid][$dispOrder]);
            } else {
                $overrideTexts[] = trim($enRow['text_content']);
            }
        }
    } else {
        $desc = firstExistingValue($sketchRow, ['description', 'desc', 'prompt', 'text'], '');
        if (!empty(trim($desc))) $overrideTexts = [trim($desc)];
    }

    $frames[] = [
        'id'            => $sid, 
        'thumb'         => $activeThumb, 
        'overlay_texts' => $overrideTexts,
        'fuki_texts'    => $fukiTexts[$sid] ?? []
    ];
}

$prevEp = ($currentEpisodeIdx > 0) ? $cinemagicEpisodes[$currentEpisodeIdx - 1] : null;
$nextEp = ($currentEpisodeIdx < count($cinemagicEpisodes) - 1) ? $cinemagicEpisodes[$currentEpisodeIdx + 1] : null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=0" />
    <title><?= htmlspecialchars($seq['name']) ?><?= $cinemagicInfo ? ' — ' . htmlspecialchars($cinemagicInfo['name']) : '' ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Permanent+Marker&family=Oswald:wght@600;700&family=Cinzel:wght@400;600&family=Space+Mono:wght@400;700&family=Lora:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-color:      #020202;
            --text-color:    #e5e0d8;
            --accent-color:  #cda434;
            --font-title:    'Cinzel', serif;
            --font-body:     'Lora', serif;
            --nav-bg:        rgba(8,6,4,0.82);
            --nav-border:    rgba(205,164,52,0.18);
            --nav-text:      #c8b88a;
            --nav-active-bg: rgba(205,164,52,0.14);
            --nav-active:    #cda434;
        }

        body, html {
            margin: 0; padding: 0;
            background-color: var(--bg-color);
            background-image: radial-gradient(circle at 50% 0%, #15151a 0%, #020202 60%);
            background-attachment: fixed;
            color: var(--text-color);
            font-family: var(--font-body);
            overscroll-behavior: none;
            -webkit-font-smoothing: antialiased;
        }

        .story-container { max-width: 800px; margin: 0 auto; padding: 0; display: flex; flex-direction: column; align-items: center; }
        .seq-header { text-align: center; padding: 100px 20px 80px; width: 100%; box-sizing: border-box; }
        .seq-title { font-family: var(--font-title); font-size: 2.5rem; font-weight: 400; margin: 0 0 20px 0; color: var(--accent-color); line-height: 1.2; letter-spacing: 2px; }
        .seq-desc { font-size: 1.1rem; opacity: 0.7; line-height: 1.6; max-width: 600px; margin: 0 auto; }
        .panel { width: 100%; display: flex; flex-direction: column; align-items: center; margin-bottom: 80px; }
        .panel-img { width: 100%; height: auto; display: block; margin: 0; box-shadow: 0 4px 40px rgba(0,0,0,0.8); border-radius: 2px; }
        .text-blocks { width: 100%; padding: 50px 20px 30px; box-sizing: border-box; display: flex; flex-direction: column; gap: 35px; align-items: center; }
        .story-text { font-size: 1.25rem; line-height: 1.8; font-weight: 400; text-align: center; max-width: 650px; color: var(--text-color); letter-spacing: 0.5px; text-shadow: 0 2px 6px rgba(0,0,0,0.9); }
        
        .observe-me { opacity: 0; transform: translateY(25px); transition: opacity 1s cubic-bezier(0.25, 1, 0.5, 1), transform 1s cubic-bezier(0.25, 1, 0.5, 1); }
        .observe-me.visible { opacity: 1; transform: translateY(0); }
        .end-mark { margin: 60px 0 120px; font-family: var(--font-title); color: var(--accent-color); font-size: 1.5rem; letter-spacing: 4px; opacity: 0.4; }

        /* ── Language Picker ─────────────────────────────────────────────── */
        .lang-picker { position: fixed; top: 20px; right: 20px; z-index: 999; }
        .lang-toggle { background: var(--nav-bg); border: 1px solid var(--nav-border); border-radius: 20px; padding: 6px 12px; color: var(--nav-text); font-family: var(--font-title); font-size: 0.6rem; cursor: pointer; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); display: flex; align-items: center; gap: 6px; transition: color 0.2s; }
        .lang-toggle:hover { color: var(--nav-active); }
        .lang-menu { position: absolute; top: 100%; right: 0; margin-top: 8px; background: var(--nav-bg); border: 1px solid var(--nav-border); border-radius: 8px; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); display: none; flex-direction: column; overflow: hidden; min-width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
        .lang-picker.open .lang-menu { display: flex; }
        .lang-menu a { padding: 10px 16px; color: var(--nav-text); text-decoration: none; font-family: var(--font-title); font-size: 0.65rem; text-align: center; transition: background 0.2s, color 0.2s; }
        .lang-menu a:hover { background: var(--nav-active-bg); color: var(--nav-active); }

        /* ── Episode Nav Bar ─────────────────────────────────────────────── */
        .ep-nav { position: fixed; bottom: 0; left: 0; right: 0; z-index: 200; display: flex; flex-direction: column; --ep-bar-h: 44px; }
        .ep-nav-toggle { align-self: center; margin-bottom: -1px; background: var(--nav-bg); border: 1px solid var(--nav-border); border-bottom: none; border-radius: 8px 8px 0 0; padding: 5px 18px 2px; font-family: var(--font-title); font-size: 0.6rem; letter-spacing: 2px; color: var(--nav-text); cursor: pointer; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); transition: color 0.2s; user-select: none; display: flex; align-items: center; gap: 8px; }
        .ep-nav-toggle:hover { color: var(--nav-active); }
        .ep-nav-toggle .ep-arrow { transition: transform 0.25s; }
        .ep-nav.open .ep-nav-toggle .ep-arrow { transform: rotate(180deg); }
        .ep-nav-bar { background: var(--nav-bg); border-top: 1px solid var(--nav-border); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); overflow: hidden; max-height: 0; transition: max-height 0.3s cubic-bezier(0.4,0,0.2,1); }
        .ep-nav.open .ep-nav-bar { max-height: 260px; }
        .ep-nav-inner { padding: 10px 12px env(safe-area-inset-bottom, 0px); display: flex; flex-direction: column; gap: 6px; }
        .ep-pn { display: flex; gap: 8px; justify-content: space-between; }
        .ep-pn-btn { flex: 1; text-align: center; padding: 7px 10px; background: transparent; border: 1px solid var(--nav-border); border-radius: 5px; color: var(--nav-text); font-family: var(--font-title); font-size: 0.6rem; letter-spacing: 1.5px; text-decoration: none; transition: border-color 0.2s, color 0.2s, background 0.2s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ep-pn-btn:hover { border-color: var(--nav-active); color: var(--nav-active); background: var(--nav-active-bg); }
        .ep-pn-btn.disabled { opacity: 0.25; pointer-events: none; }
        .ep-list { display: flex; gap: 6px; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; padding-bottom: 2px; }
        .ep-list::-webkit-scrollbar { display: none; }
        .ep-pill { flex-shrink: 0; padding: 5px 12px; border: 1px solid var(--nav-border); border-radius: 20px; font-family: var(--font-title); font-size: 0.55rem; letter-spacing: 1.5px; color: var(--nav-text); text-decoration: none; white-space: nowrap; transition: border-color 0.2s, color 0.2s, background 0.2s; }
        .ep-pill:hover { border-color: var(--nav-active); color: var(--nav-active); }
        .ep-pill.active { border-color: var(--nav-active); background: var(--nav-active-bg); color: var(--nav-active); }
        
        @media (max-width: 768px) { .seq-title { font-size: 2rem; } .story-text { font-size: 1.15rem; line-height: 1.7; padding: 0 10px; } .text-blocks { padding: 40px 10px 20px; gap: 25px; } .panel { margin-bottom: 60px; } }
        body.has-ep-nav .story-container { padding-bottom: 80px; }
    </style>
</head>
<body<?= $showEpisodeNav ? ' class="has-ep-nav"' : '' ?>>

<?php if (count($availableLangs) > 1): ?>
<div class="lang-picker" id="lang-picker">
    <button class="lang-toggle" id="lang-toggle" aria-expanded="false">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path><path d="M2 12h20"></path></svg>
        <span class="lang-label"><?= strtoupper($langCode) ?></span>
    </button>
    <div class="lang-menu">
        <?php foreach ($availableLangs as $l): ?>
            <a href="?id=<?= $seqId ?>&lang=<?= $l ?>"><?= strtoupper($l) ?></a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="story-container">
    <div class="seq-header observe-me visible">
        <?php if ($cinemagicInfo): ?>
            <div style="font-family:var(--font-title);font-size:0.65rem;letter-spacing:3px;color:var(--accent-color);opacity:0.6;margin-bottom:14px;text-transform:uppercase;">
                <?= htmlspecialchars($cinemagicInfo['name']) ?>
                <?php if ($showEpisodeNav): ?>
                    &nbsp;·&nbsp; <?= $currentEpisodeIdx + 1 ?> / <?= count($cinemagicEpisodes) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <h1 class="seq-title"><?= htmlspecialchars($seq['name']) ?></h1>
        <?php if (!empty($seq['description'])): ?>
            <p class="seq-desc"><?= nl2br(htmlspecialchars($seq['description'])) ?></p>
        <?php endif; ?>
    </div>

    <?php if (empty($frames)): ?>
        <div class="panel observe-me visible" style="margin-top:100px;">
            <div class="story-text">This sequence has no frames to display.</div>
        </div>
    <?php endif; ?>

    <?php foreach ($frames as $index => $f): ?>
        <div class="panel" id="panel-<?= $index ?>">
            <?php if (!empty($f['thumb'])): 
                $fukiJson = htmlspecialchars(json_encode($f['fuki_texts'] ?? []), ENT_QUOTES, 'UTF-8');
            ?>
                <div class="panel-img-wrapper" style="position:relative; width:100%; display:flex; justify-content:center;">
                    <div style="position:relative; width:100%; max-width:600px;">
                        <img src="<?= htmlspecialchars($f['thumb']) ?>" class="panel-img observe-me" loading="lazy" alt="Frame <?= $index + 1 ?>" data-fuki="<?= $fukiJson ?>" onload="if(typeof renderFuki === 'function') renderFuki(this);">
                        <div class="fuki-layer" style="position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none;"></div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($f['overlay_texts'])): ?>
                <div class="text-blocks">
                    <?php foreach ($f['overlay_texts'] as $txt): ?>
                        <div class="story-text observe-me"><?= nl2br(htmlspecialchars($txt)) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="end-mark observe-me">&#10086; FIN &#10086;</div>
</div>

<?php if ($showEpisodeNav): ?>
<nav class="ep-nav" id="ep-nav" aria-label="Episode Navigation">
    <button class="ep-nav-toggle" id="ep-nav-toggle" aria-expanded="false" aria-controls="ep-nav-bar">
        <span>Episodes</span>
        <span class="ep-arrow">&#9650;</span>
    </button>
    <div class="ep-nav-bar" id="ep-nav-bar" role="region">
        <div class="ep-nav-inner">
            <div class="ep-pn">
                <?php if ($prevEp): ?>
                    <a class="ep-pn-btn" href="cinematic.php?id=<?= $prevEp['id'] ?>&lang=<?= $langCode ?>">&#9664; <?= htmlspecialchars($prevEp['chapter_label'] ?: $prevEp['name']) ?></a>
                <?php else: ?>
                    <span class="ep-pn-btn disabled">&#9664; Previous</span>
                <?php endif; ?>

                <?php if ($nextEp): ?>
                    <a class="ep-pn-btn" href="cinematic.php?id=<?= $nextEp['id'] ?>&lang=<?= $langCode ?>"><?= htmlspecialchars($nextEp['chapter_label'] ?: $nextEp['name']) ?> &#9654;</a>
                <?php else: ?>
                    <span class="ep-pn-btn disabled">Next &#9654;</span>
                <?php endif; ?>
            </div>
            <div class="ep-list">
                <?php foreach ($cinemagicEpisodes as $i => $ep): ?>
                    <a class="ep-pill<?= $ep['id'] === $seqId ? ' active' : '' ?>" href="cinematic.php?id=<?= $ep['id'] ?>&lang=<?= $langCode ?>" aria-current="<?= $ep['id'] === $seqId ? 'page' : 'false' ?>">
                        <?= htmlspecialchars($ep['chapter_label'] ?: ($ep['name'])) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) { entry.target.classList.add('visible'); obs.unobserve(entry.target); }
        });
    }, { root: null, rootMargin: '0px 0px -15% 0px', threshold: 0 });
    document.querySelectorAll('.observe-me').forEach(el => observer.observe(el));

    const langToggle = document.getElementById('lang-toggle');
    if (langToggle) {
        langToggle.addEventListener('click', function(e) {
            e.stopPropagation(); document.getElementById('lang-picker').classList.toggle('open');
        });
        document.addEventListener('click', function(e) {
            const p = document.getElementById('lang-picker');
            if (p && !p.contains(e.target)) p.classList.remove('open');
        });
    }

    <?php if ($showEpisodeNav): ?>
    const nav    = document.getElementById('ep-nav');
    const toggle = document.getElementById('ep-nav-toggle');
    toggle.addEventListener('click', () => {
        const isOpen = nav.classList.toggle('open');
        toggle.setAttribute('aria-expanded', isOpen);
    });
    nav.addEventListener('transitionend', () => {
        if (nav.classList.contains('open')) {
            const active = nav.querySelector('.ep-pill.active');
            if (active) active.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
        }
    });
    <?php endif; ?>
});

function renderFuki(imgEl) {
    try {
        const fukiData = JSON.parse(imgEl.getAttribute('data-fuki') || '[]');
        if (!fukiData.length) return;
        const layer = imgEl.nextElementSibling;
        if (!layer || !layer.classList.contains('fuki-layer')) return;
        const natW = imgEl.naturalWidth || imgEl.getAttribute('width');
        if (!natW) return;
        const scale = imgEl.clientWidth / natW;
        layer.innerHTML = '';
        fukiData.forEach(ft => {
            const div = document.createElement('div');
            div.style.position = 'absolute';
            div.style.left = (ft.x * scale) + 'px';
            div.style.top = (ft.y * scale) + 'px';
            div.style.width = (ft.width * scale) + 'px';
            div.style.transform = `rotate(${ft.rotation}deg)`;
            div.style.transformOrigin = 'top left';
            div.style.color = ft.fill_color;
            div.style.textAlign = ft.text_align;
            div.style.fontFamily = `"${ft.font_family}", sans-serif`;
            div.style.fontSize = (ft.font_size * scale) + 'px';
            div.style.fontWeight = ft.is_bold == 1 ? 'bold' : 'normal';
            div.style.fontStyle = ft.is_italic == 1 ? 'italic' : 'normal';
            div.style.textDecoration = ft.is_underline == 1 ? 'underline' : 'none';
            div.style.lineHeight = '1.2';
            div.style.whiteSpace = 'pre-wrap';
            div.style.wordWrap = 'break-word';
            
            // --- FIX: Re-enable pointer events and text selection for the text itself ---
            div.style.pointerEvents = 'auto';
            div.style.userSelect = 'text';
            div.style.webkitUserSelect = 'text';
            
            div.innerText = ft.text_content;
            layer.appendChild(div);
        });
    } catch(e) { console.error('Fuki render error', e); }
}
window.addEventListener('resize', () => {
    document.querySelectorAll('.panel-img[data-fuki]').forEach(img => {
        if (img.complete && img.naturalWidth > 0) renderFuki(img);
    });
});
</script>

</body>
</html>





