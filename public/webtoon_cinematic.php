<?php
// public/webtoon_cinematic.php
// A pure cinematic, Webtoon-style storybook presentation clone

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

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
        // Sanitize path for web if it's a direct file path to avoid space/character breaking on mobile
        if (strpos($candidate, 'http') !== 0 && strpos($candidate, 'view_frame.php') === false) {
            $parts = array_map('rawurlencode', explode('/', ltrim($candidate, '/')));
            return '/' . implode('/', $parts);
        }
        return $candidate;
    }

    if ($frameId > 0) {
        return 'view_frame.php?frame_id=' . $frameId;
    }

    return '';
}

// 1. Fetch the sequence
$seqId = $_GET['id'] ?? null;
$table = 'narrative_sequences';

$seq = null;
if ($seqId) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$seqId]);
    $seq = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $seq = $pdo->query("SELECT * FROM $table ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

if (!$seq) {
    die("<div style='background:#000; color:#fff; font-family:sans-serif; padding:50px; text-align:center;'>Sequence not found.</div>");
}

// 2. Build frames directly from the saved sequence_data
$itemIds = json_decode($seq['sequence_data'] ?? '[]', true);
if (!is_array($itemIds)) {
    $itemIds = [];
}

$pureSketchIds = [];
$selectedFrameIds = [];

foreach ($itemIds as $idx => $item) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    if ($sid > 0) {
        $pureSketchIds[] = $sid;
    }
    $selectedFrameIds[$idx] = (is_array($item) && !empty($item['frame_id'])) ? (int)$item['frame_id'] : null;
}

$pureSketchIds = array_values(array_unique($pureSketchIds));

// Load sketches
$sketchesData = [];
if (!empty($pureSketchIds)) {
    $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));
    $stmtS = $pdo->prepare("SELECT * FROM sketches WHERE id IN ($inClause)");
    $stmtS->execute($pureSketchIds);
    foreach ($stmtS->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sketchesData[(int)$row['id']] = $row;
    }
}

// Load selected frames explicitly pinned to the sequence
$selectedFrameMap = [];
$activeFrameIds = array_values(array_unique(array_filter($selectedFrameIds)));

if (!empty($activeFrameIds)) {
    $inClauseFrames = implode(',', array_fill(0, count($activeFrameIds), '?'));
    $stmtF = $pdo->prepare("SELECT * FROM frames WHERE id IN ($inClauseFrames)");
    $stmtF->execute($activeFrameIds);
    foreach ($stmtF->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $fid = (int)$row['id'];
        $selectedFrameMap[$fid] = [
            'row' => $row,
            'thumb' => resolveFrameThumb($row, $fid),
        ];
    }
}

// Fallback logic for unpinned frames
$sketchIdsNeedingLatestFrame = [];
foreach ($itemIds as $idx => $item) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    if ($sid > 0 && empty($selectedFrameIds[$idx])) {
        $sketchIdsNeedingLatestFrame[] = $sid;
    }
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
            $latestFrameBySketch[$sketchId] = [
                'frame_id' => $fid,
                'row' => $row,
                'thumb' => resolveFrameThumb($row, $fid),
            ];
        }
    }
}

// Load override overlay texts (1:N translation capability without language locks)
$overlayTexts = [];
if (!empty($pureSketchIds)) {
    $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));
    try {
        $stmtO = $pdo->prepare("SELECT sketch_id, text_content FROM sketch_overlay_texts WHERE sketch_id IN ($inClause) ORDER BY display_order ASC, id ASC");
        $stmtO->execute($pureSketchIds);
        foreach ($stmtO->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $overlayTexts[(int)$row['sketch_id']][] = $row['text_content'];
        }
    } catch (PDOException $e) {
        // Table missing fallback: do nothing, it will utilize default sketch description.
    }
}

// Assemble sequence items
$frames = [];
foreach ($itemIds as $idx => $item) {
    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
    if ($sid <= 0 || !isset($sketchesData[$sid])) {
        continue;
    }

    $sketchRow = $sketchesData[$sid];
    $activeFrameId = $selectedFrameIds[$idx] ?? null;
    $activeThumb = '';

    if ($activeFrameId && isset($selectedFrameMap[$activeFrameId])) {
        $activeThumb = $selectedFrameMap[$activeFrameId]['thumb'];
    } elseif (!$activeFrameId && isset($latestFrameBySketch[$sid])) {
        $activeThumb = $latestFrameBySketch[$sid]['thumb'];
        $activeFrameId = $latestFrameBySketch[$sid]['frame_id'];
    }

    // Attempt to pull override DB texts, otherwise fallback cleanly to DB original description
    $overrideTexts = $overlayTexts[$sid] ?? [];
    if (empty($overrideTexts)) {
        $desc = firstExistingValue($sketchRow, ['description', 'desc', 'prompt', 'text'], '');
        if (!empty(trim($desc))) {
            $overrideTexts = [trim($desc)];
        }
    }

    $frames[] = [
        'id' => $sid,
        'thumb' => $activeThumb,
        'overlay_texts' => $overrideTexts,
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=0" />
    <title><?= htmlspecialchars($seq['name']) ?> - Cinematic Story</title>

    <!-- Elegant Noble Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Lora:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-color: #020202;
            --text-color: #e5e0d8;
            --accent-color: #cda434; /* Soft Gold */
            --font-title: 'Cinzel', serif;
            --font-body: 'Lora', serif;
        }

        body, html {
            margin: 0; 
            padding: 0;
            background-color: var(--bg-color);
            background-image: radial-gradient(circle at 50% 0%, #15151a 0%, #020202 60%);
            background-attachment: fixed;
            color: var(--text-color);
            font-family: var(--font-body);
            overscroll-behavior: none;
            -webkit-font-smoothing: antialiased;
        }

        .story-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .seq-header {
            text-align: center;
            padding: 100px 20px 80px;
            width: 100%;
            box-sizing: border-box;
        }

        .seq-title {
            font-family: var(--font-title);
            font-size: 2.5rem;
            font-weight: 400;
            margin: 0 0 20px 0;
            color: var(--accent-color);
            line-height: 1.2;
            letter-spacing: 2px;
        }

        .seq-desc {
            font-size: 1.1rem;
            opacity: 0.7;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto;
        }

        .panel {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 80px; 
        }

        .panel-img {
            width: 100%;
            height: auto;
            display: block;
            margin: 0;
            box-shadow: 0 4px 40px rgba(0,0,0,0.8);
            border-radius: 2px;
        }

        .text-blocks {
            width: 100%;
            padding: 50px 20px 30px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 35px; 
            align-items: center;
        }

        .story-text {
            font-size: 1.25rem;
            line-height: 1.8;
            font-weight: 400;
            text-align: center;
            max-width: 650px;
            color: var(--text-color);
            letter-spacing: 0.5px;
            text-shadow: 0 2px 6px rgba(0,0,0,0.9);
        }

        /* Animation Classes */
        .observe-me {
            opacity: 0;
            transform: translateY(25px);
            transition: opacity 1s cubic-bezier(0.25, 1, 0.5, 1), transform 1s cubic-bezier(0.25, 1, 0.5, 1);
        }

        .observe-me.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .end-mark {
            margin: 60px 0 120px;
            font-family: var(--font-title);
            color: var(--accent-color);
            font-size: 1.5rem;
            letter-spacing: 4px;
            opacity: 0.4;
        }

        /* Mobile First Scaling Adjustments */
        @media (max-width: 768px) {
            .seq-title {
                font-size: 2rem;
            }
            .story-text {
                font-size: 1.15rem;
                line-height: 1.7;
                padding: 0 10px;
            }
            .text-blocks {
                padding: 40px 10px 20px;
                gap: 25px;
            }
            .panel {
                margin-bottom: 60px;
            }
        }
    </style>
</head>
<body>

<div class="story-container">
    <div class="seq-header observe-me visible">
        <h1 class="seq-title"><?= htmlspecialchars($seq['name']) ?></h1>
        <?php if (!empty($seq['description'])): ?>
            <p class="seq-desc"><?= nl2br(htmlspecialchars($seq['description'])) ?></p>
        <?php endif; ?>
    </div>

    <?php if (empty($frames)): ?>
        <div class="panel observe-me visible" style="margin-top: 100px;">
            <div class="story-text">This sequence has no frames to display.</div>
        </div>
    <?php endif; ?>

    <?php foreach ($frames as $index => $f): ?>
        <div class="panel" id="panel-<?= $index ?>">
            <?php if (!empty($f['thumb'])): ?>
                <img src="<?= htmlspecialchars($f['thumb']) ?>" class="panel-img observe-me" loading="lazy" alt="Frame <?= $index + 1 ?>">
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

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const observerOptions = {
            root: null,
            // Triggers elements slightly before they enter the viewport
            rootMargin: '0px 0px -15% 0px',
            threshold: 0
        };

        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    // Stop observing once animated in
                    obs.unobserve(entry.target);
                }
            });
        }, observerOptions);

        document.querySelectorAll('.observe-me').forEach(el => {
            observer.observe(el);
        });
    });
</script>

</body>
</html>