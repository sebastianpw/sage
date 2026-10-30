<?php
/**
 * SAGE Cinemagic Hub — 3D Coverflow Isolated Iframe View
 * public/cinemagic_hub/demo.php
 * 
 * Target a specific Season/Cinemagic: ?series_id=12&cinemagic_id=5
 */

require_once __DIR__ . '/../bootstrap.php';
global $pdo;

$seriesId    = (int)($_GET['series_id'] ?? 0);
$cinemagicId = (int)($_GET['cinemagic_id'] ?? 0);

// Fallbacks for easy testing
if (!$seriesId) {
    $lastSeries = $pdo->query("SELECT id FROM cinemagic_series WHERE status = 'published' ORDER BY updated_at DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    if ($lastSeries) $seriesId = (int)$lastSeries['id'];
    else die("<div style='color:white; padding:40px; font-family:sans-serif;'>No published series found.</div>");
}

if (!$cinemagicId) {
    $firstCm = $pdo->prepare("SELECT cinemagic_id FROM cinemagic_series_2_cinemagics WHERE series_id = ? ORDER BY sort_order ASC LIMIT 1");
    $firstCm->execute([$seriesId]);
    $cmRow = $firstCm->fetch(\PDO::FETCH_ASSOC);
    if ($cmRow) $cinemagicId = (int)$cmRow['cinemagic_id'];
    else die("<div style='color:white; padding:40px; font-family:sans-serif;'>No cinemagics attached to this series.</div>");
}

// 1. Fetch Series Info (to determine Square vs Upright format)
$stmtS = $pdo->prepare("SELECT title, description, cover_image_url, pdf_page_size FROM cinemagic_series WHERE id = ?");
$stmtS->execute([$seriesId]);
$series = $stmtS->fetch(\PDO::FETCH_ASSOC);
if (!$series) die("<div style='color:white; padding:40px; font-family:sans-serif;'>Series not found.</div>");

$isSquare = (strtolower($series['pdf_page_size'] ?? '') === 'square');

// 2. Fetch Isolated Episodes for THIS Cinemagic only
$epStmt = $pdo->prepare("
    SELECT ns.id, ns.name, c.name as season_name,
           cs.cover_image_url, ns.sequence_data, cs.chapter_label,
           css.title AS super_season_title
    FROM cinemagic_series_2_cinemagics sc
    LEFT JOIN cinemagic_series_seasons css ON css.id = sc.season_id
    JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = sc.cinemagic_id
    JOIN narrative_sequences ns ON ns.id = cs.sequence_id
    JOIN cinemagics c ON c.id = sc.cinemagic_id
    WHERE sc.series_id = ? AND sc.cinemagic_id = ?
    ORDER BY cs.sort_order ASC, ns.id ASC
");
$epStmt->execute([$seriesId, $cinemagicId]);
$episodes = $epStmt->fetchAll(\PDO::FETCH_ASSOC);

if (empty($episodes)) die("<div style='color:white; padding:40px; font-family:sans-serif;'>No episodes found in this season.</div>");

$superTitle = $episodes[0]['super_season_title'] ?: '';
$cmName     = $episodes[0]['season_name'] ?: 'Extras';

// 3. Resolve Covers
foreach ($episodes as &$ep) {
    $cover = $ep['cover_image_url'] ?: '';
    if (!$cover) {
        $items = json_decode($ep['sequence_data'] ?? '[]', true) ?: [];
        if (!empty($items)) {
            $first = $items[0];
            $sid = is_array($first) ? (int)($first['sketch_id']??0) : (int)$first;
            $fid = is_array($first) ? (int)($first['frame_id']??0) : 0;
            if ($fid) {
                $fStmt = $pdo->prepare("SELECT filename FROM frames WHERE id = ?");
                $fStmt->execute([$fid]);
                $cover = $fStmt->fetchColumn();
            } else if ($sid) {
                $fStmt = $pdo->prepare("SELECT f.filename FROM frames f INNER JOIN frames_2_sketches m ON m.from_id = f.id WHERE f.entity_id = ? ORDER BY f.id DESC LIMIT 1");
                $fStmt->execute([$sid]);
                $cover = $fStmt->fetchColumn();
            }
        }
    }
    if ($cover && !preg_match('/^https?:\/\//i', $cover)) {
        $cover = str_starts_with($cover, '/') ? $cover : '/' . $cover;
    }
    $ep['resolved_cover'] = $cover;
}
unset($ep);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Coverflow — <?= htmlspecialchars($cmName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Lora:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #020202;
            --text-color: #e5e0d8;
            --accent-color: #cda434;
            --font-title: 'Cinzel', serif;
            --font-body: 'Lora', serif;
        }

        body, html {
            margin: 0; padding: 0;
            background-color: transparent; /* Allows modal background to bleed through if needed */
            color: var(--text-color);
            font-family: var(--font-body);
            overflow: hidden; /* Trap scrolling inside iframe */
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            text-align: center;
            padding: 30px 20px 10px;
            flex-shrink: 0;
        }
        
        .cf-super-title {
            font-family: var(--font-title);
            font-size: 0.75rem;
            letter-spacing: 4px;
            color: var(--accent-color);
            opacity: 0.8;
            text-transform: uppercase;
        }
        
        .cf-cm-title {
            font-family: var(--font-title);
            font-size: 1.8rem;
            color: var(--text-color);
            margin-top: 4px;
            letter-spacing: 2px;
        }

        /* The 3D Perspective Stage */
        .cf-stage {
            position: relative;
            width: 100%;
            flex: 1; /* Take remaining height */
            perspective: 1200px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            touch-action: pan-y;
            user-select: none;
            -webkit-user-select: none;
        }

        .cf-track {
            position: absolute;
            left: 50%;
            top: 45%; /* Slightly elevated to leave room for text below */
            transform-style: preserve-3d;
        }

        /* Individual Cover Card - Dimensions React to Square vs Upright */
        .cf-item {
            position: absolute;
            width: <?= $isSquare ? '260px' : '220px' ?>;
            aspect-ratio: <?= $isSquare ? '1/1' : '3/4' ?>;
            transform-origin: center center;
            transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1), opacity 0.4s ease, filter 0.4s ease;
            cursor: pointer;
            border-radius: 4px;
            background: #111;
            /* Apple-style reflection */
            -webkit-box-reflect: below 2px linear-gradient(transparent 75%, rgba(255,255,255,0.3));
        }

        @media (max-width: 600px) {
            .cf-item { 
                width: <?= $isSquare ? '220px' : '170px' ?>; 
            }
        }

        .cf-item:not(.active) {
            filter: brightness(0.4) saturate(0.5);
        }
        
        .cf-item.active {
            box-shadow: 0 10px 40px rgba(0,0,0,0.8), 0 0 0 1px rgba(205,164,52,0.4);
            filter: brightness(1.1);
        }

        .cf-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
            display: block;
            pointer-events: none;
        }

        .cf-info {
            position: absolute;
            bottom: <?= $isSquare ? '-80px' : '-65px' ?>; /* Push below reflection */
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
            width: 320px;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        
        .cf-item.active .cf-info {
            opacity: 1;
            transition-delay: 0.15s;
        }

        .cf-label {
            font-family: var(--font-title);
            font-size: 0.65rem;
            letter-spacing: 2px;
            color: var(--accent-color);
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .cf-title {
            font-family: var(--font-body);
            font-size: 1.1rem;
            color: var(--text-color);
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-shadow: 0 2px 8px rgba(0,0,0,0.8);
        }

        .cf-arrow {
            position: absolute;
            top: 45%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1000;
            transition: background 0.2s, border-color 0.2s, color 0.2s;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        .cf-arrow:hover {
            background: rgba(205,164,52,0.2);
            border-color: var(--accent-color);
            color: var(--accent-color);
        }
        .cf-arrow.prev { left: 20px; }
        .cf-arrow.next { right: 20px; }
        .cf-arrow svg { width: 24px; height: 24px; stroke: currentColor; fill: none; stroke-width: 2; }
        
        @media (max-width: 768px) {
            .cf-arrow { display: none; }
        }
    </style>
</head>
<body>

    <div class="header">
        <?php if ($superTitle && $superTitle !== 'Season'): ?>
            <div class="cf-super-title"><?= htmlspecialchars($superTitle) ?></div>
        <?php endif; ?>
        <div class="cf-cm-title"><?= htmlspecialchars($cmName) ?></div>
    </div>

    <!-- Coverflow Stage -->
    <div class="cf-stage" id="stage-main">
        <button class="cf-arrow prev"><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></button>
        <button class="cf-arrow next"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>
        
        <div class="cf-track">
            <?php foreach ($episodes as $idx => $ep): 
                // target="_top" ensures the parent window navigates away from the grid/landing page
                $href = "api.php?action=preview_episode&series_id={$seriesId}&seq_id={$ep['id']}";
                $cover = $ep['resolved_cover'] ?: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="220" height="300"><rect width="100%" height="100%" fill="%23222"/></svg>';
                $epLabel = $ep['chapter_label'] ?: 'Episode ' . ($idx + 1);
            ?>
                <a class="cf-item" href="<?= $href ?>" target="_top" data-index="<?= $idx ?>">
                    <img class="cf-img" src="<?= htmlspecialchars($cover) ?>" alt="Cover">
                    <div class="cf-info">
                        <div class="cf-label"><?= htmlspecialchars($epLabel) ?></div>
                        <div class="cf-title"><?= htmlspecialchars($ep['name']) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    class Coverflow {
        constructor(stageElement) {
            this.stage = stageElement;
            this.track = stageElement.querySelector('.cf-track');
            this.items = Array.from(stageElement.querySelectorAll('.cf-item'));
            this.btnPrev = stageElement.querySelector('.cf-arrow.prev');
            this.btnNext = stageElement.querySelector('.cf-arrow.next');
            
            this.currentIndex = 0;
            this.total = this.items.length;

            if (this.total === 0) return;

            this.init();
        }

        init() {
            this.update();

            if (this.btnPrev) this.btnPrev.addEventListener('click', () => this.goPrev());
            if (this.btnNext) this.btnNext.addEventListener('click', () => this.goNext());

            this.items.forEach((item, i) => {
                item.addEventListener('click', (e) => {
                    if (this.currentIndex !== i) {
                        e.preventDefault();
                        this.goTo(i);
                    }
                    // Else, let the <a> tag handle the target="_top" navigation
                });
            });

            // Swipe detection
            let startX = 0;
            let currentX = 0;
            let isSwiping = false;

            this.stage.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
                isSwiping = true;
            }, { passive: true });

            this.stage.addEventListener('touchmove', (e) => {
                if (!isSwiping) return;
                currentX = e.touches[0].clientX;
            }, { passive: true });

            this.stage.addEventListener('touchend', (e) => {
                if (!isSwiping) return;
                isSwiping = false;
                
                const deltaX = currentX - startX;
                if (Math.abs(deltaX) > 40) {
                    if (deltaX < 0) this.goNext(); 
                    else this.goPrev();
                }
            });
            
            // Allow keyboard arrows
            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowRight') this.goNext();
                if (e.key === 'ArrowLeft')  this.goPrev();
            });
        }

        goTo(index) {
            if (index < 0) index = 0;
            if (index >= this.total) index = this.total - 1;
            if (this.currentIndex !== index) {
                this.currentIndex = index;
                this.update();
            }
        }

        goNext() { this.goTo(this.currentIndex + 1); }
        goPrev() { this.goTo(this.currentIndex - 1); }

        update() {
            const isMobile = window.innerWidth <= 600;
            const isSquare = <?= $isSquare ? 'true' : 'false' ?>;
            
            // Tweak overlapping math slightly depending on format
            const baseShift = isMobile ? (isSquare ? 75 : 65) : (isSquare ? 85 : 75); 
            const stepShift = isMobile ? 18 : 22; 
            const zPush = -120; 
            const zStep = -40;  
            const rotation = 55;

            this.items.forEach((item, i) => {
                const offset = i - this.currentIndex;
                const absOffset = Math.abs(offset);
                const sign = Math.sign(offset);
                const isActive = absOffset === 0;

                let x = 0, z = 0, rotY = 0, opacity = 1;

                if (!isActive) {
                    x = sign * (baseShift + (absOffset - 1) * stepShift);
                    z = zPush + (absOffset - 1) * zStep;
                    rotY = -sign * rotation;
                    
                    if (absOffset > 3) {
                        opacity = 0;
                    } else if (absOffset === 3) {
                        opacity = 0.3;
                    }
                }

                item.style.transform = `translate(-50%, -50%) translateX(${x}%) translateZ(${z}px) rotateY(${rotY}deg)`;
                item.style.zIndex = 100 - absOffset;
                item.style.opacity = opacity;
                item.style.pointerEvents = opacity > 0 ? 'auto' : 'none';

                if (isActive) item.classList.add('active');
                else item.classList.remove('active');
            });
        }
    }

    new Coverflow(document.getElementById('stage-main'));
    </script>
</body>
</html>