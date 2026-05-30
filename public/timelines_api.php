<?php
// public/timelines_api.php
// AJAX API for the SAGE AI Timelines view.
// Serves TimelineJS-compatible JSON and handles entity-tag CRUD.
// PHP reads/writes rows only — no DDL here.

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── helpers ──────────────────────────────────────────────────────────────────
function jsonErr(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

function tljsDate(int $sort): array {
    return ['year' => $sort ?: 1, 'month' => 1, 'day' => 1];
}

function getFramesForSketches(PDO $pdo, array $sketchIds): array {
    if (empty($sketchIds)) return [];
    $in = implode(',', array_fill(0, count($sketchIds), '?'));
    $params = array_merge($sketchIds, $sketchIds);
    $sql = "
        SELECT id, filename, entity_id as sketch_id 
        FROM frames 
        WHERE entity_type = 'sketches' AND entity_id IN ($in)
        UNION
        SELECT f.id, f.filename, fs.to_id as sketch_id
        FROM frames f
        JOIN frames_2_sketches fs ON fs.from_id = f.id
        WHERE fs.to_id IN ($in)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $framesBySketch = [];
    foreach ($rows as $r) {
        $sid = (int)$r['sketch_id'];
        $framesBySketch[$sid][] = $r;
    }
    // Sort descending by id
    foreach ($framesBySketch as &$frames) {
        usort($frames, fn($a, $b) => $b['id'] <=> $a['id']);
    }
    return $framesBySketch;
}


function buildGalleryHtml(array $frames): string {
    if (empty($frames)) return '';
    $html = '<div class="visual-container sage-sketch-swiper pswp-gallery swiper">';
    $html .= '<div class="swiper-wrapper">';
    foreach ($frames as $f) {
        $url = htmlspecialchars($f['filename']);
        $html .= '<div class="swiper-slide">';
        $html .= '<a href="' . $url . '" data-pswp-width="1024" data-pswp-height="1024" target="_blank">';
        $html .= '<img src="' . $url . '" loading="lazy" onload="if(this.naturalWidth) { this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight; }">';
        $html .= '</a>';
        // Removed inline event attributes to survive TimelineJS DOMPurify. Added data-frame-id instead.
        $html .= '<div class="f-view-btn swiper-no-swiping" data-frame-id="' . $f['id'] . '"><i class="bi bi-arrows-fullscreen"></i></div>';
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '<div class="swiper-button-next" style="color:rgba(255,255,255,0.7); text-shadow:0 0 5px #000;"></div>';
    $html .= '<div class="swiper-button-prev" style="color:rgba(255,255,255,0.7); text-shadow:0 0 5px #000;"></div>';
    $html .= '</div>';
    return $html;
}


// ── router ───────────────────────────────────────────────────────────────────
try {

    switch ($action) {

        // ── PLUSH TIMELINE JSON ──────────────────────────────────────────────
        case 'plush_timeline':
            $storyId      = (int)($_GET['story_id'] ?? 0);
            $collectionId = (int)($_GET['collection_id'] ?? 0);

            if (!$storyId && !$collectionId) jsonErr('story_id or collection_id required');

            if ($collectionId) {
                $stmt = $pdo->prepare(
                    "SELECT ps.*, psd.era_label, psd.start_label, psd.end_label,
                            psd.sort_start, psd.sort_end,
                            pcs.arc_label, pcs.sort_order AS col_sort
                     FROM plush_stories ps
                     JOIN plush_collections_2_stories pcs ON pcs.story_id = ps.id
                     LEFT JOIN plush_story_dates psd ON psd.story_id = ps.id
                     WHERE pcs.collection_id = ?
                     ORDER BY pcs.sort_order ASC, ps.id ASC"
                );
                $stmt->execute([$collectionId]);
                $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT ps.*, psd.era_label, psd.start_label, psd.end_label,
                            psd.sort_start, psd.sort_end
                     FROM plush_stories ps
                     LEFT JOIN plush_story_dates psd ON psd.story_id = ps.id
                     WHERE ps.id = ?"
                );
                $stmt->execute([$storyId]);
                $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            if (empty($stories)) jsonErr('No stories found');

            $storyIds = array_column($stories, 'id');
            $inS = implode(',', array_fill(0, count($storyIds), '?'));

            $stmtSc = $pdo->prepare(
                "SELECT sc.*, scd.start_label AS sc_start_label, scd.end_label AS sc_end_label,
                        scd.sort_start AS sc_sort_start, scd.sort_end AS sc_sort_end
                 FROM plush_scenes sc
                 LEFT JOIN plush_scene_dates scd ON scd.scene_id = sc.id
                 WHERE sc.story_id IN ($inS)
                 ORDER BY sc.story_id ASC, sc.scene_order ASC, sc.id ASC"
            );
            $stmtSc->execute($storyIds);
            $allScenes = $stmtSc->fetchAll(PDO::FETCH_ASSOC);

            $sceneIds = array_column($allScenes, 'id');
            $scenesByStory = [];
            foreach ($allScenes as $sc) {
                $scenesByStory[(int)$sc['story_id']][] = $sc;
            }

            $highlightsByScene = [];
            $entityTagsByBlock = [];
            if (!empty($sceneIds)) {
                $inSc = implode(',', array_fill(0, count($sceneIds), '?'));
                $stmtH = $pdo->prepare(
                    "SELECT * FROM plush_highlight_blocks
                     WHERE scene_id IN ($inSc) AND language_code = 'en'
                     ORDER BY group_id ASC, display_order ASC"
                );
                $stmtH->execute($sceneIds);
                foreach ($stmtH->fetchAll(PDO::FETCH_ASSOC) as $b) {
                    $highlightsByScene[(int)$b['scene_id']][] = $b;
                }

                $blockIds = [];
                foreach ($highlightsByScene as $bArr) {
                    foreach ($bArr as $b) $blockIds[] = (int)$b['id'];
                }
                
                if (!empty($blockIds)) {
                    $inB = implode(',', array_fill(0, count($blockIds), '?'));
                    $stmtE = $pdo->prepare(
                        "SELECT * FROM plush_highlight_block_entities WHERE block_id IN ($inB) ORDER BY entity_type ASC"
                    );
                    $stmtE->execute($blockIds);
                    foreach ($stmtE->fetchAll(PDO::FETCH_ASSOC) as $tag) {
                        $entityTagsByBlock[(int)$tag['block_id']][] = $tag;
                    }
                }
                foreach ($highlightsByScene as $scId => &$bArr) {
                    foreach ($bArr as &$b) {
                        $b['entity_tags'] = $entityTagsByBlock[(int)$b['id']] ?? [];
                    }
                    unset($b);
                }
                unset($bArr);
            }

            // Extract referenced sketch IDs to fetch frames for the interactive gallery
            $sketchIds = [];
            foreach ($entityTagsByBlock as $tags) {
                foreach ($tags as $t) {
                    if ($t['entity_type'] === 'sketches') {
                        $sketchIds[] = (int)$t['entity_id'];
                    }
                }
            }
            $sketchIds = array_values(array_unique($sketchIds));
            $framesBySketch = getFramesForSketches($pdo, $sketchIds);

            $titleSlide = null;
            if ($collectionId) {
                $colStmt = $pdo->prepare("SELECT * FROM plush_collections WHERE id = ?");
                $colStmt->execute([$collectionId]);
                $col = $colStmt->fetch(PDO::FETCH_ASSOC);
                $titleSlide = [
                    'text' => [
                        'headline' => htmlspecialchars($col['title'] ?? 'Timeline'),
                        'text'     => htmlspecialchars($col['description'] ?? ''),
                    ]
                ];
            } else {
                $titleSlide = [
                    'text' => [
                        'headline' => htmlspecialchars($stories[0]['title'] ?? 'Story Timeline'),
                        'text'     => htmlspecialchars($stories[0]['description'] ?? ''),
                    ]
                ];
            }

            $events   = [];
            $eras     = [];
            $sortBase = 1;

            foreach ($stories as $story) {
                $sid    = (int)$story['id'];
                $scenes = $scenesByStory[$sid] ?? [];

                $totalBlocks = 0;
                foreach ($scenes as $sc) {
                    $cnt = count($highlightsByScene[(int)$sc['id']] ?? []);
                    $totalBlocks += $cnt ?: 1;
                }

                $storyStart = isset($story['sort_start']) && $story['sort_start'] !== null
                    ? (int)$story['sort_start']
                    : $sortBase;
                $storyEnd   = isset($story['sort_end']) && $story['sort_end'] !== null
                    ? (int)$story['sort_end']
                    : ($storyStart + max(0, $totalBlocks - 1));

                $eras[] = [
                    'start_date' => tljsDate($storyStart),
                    'end_date'   => tljsDate($storyEnd),
                    'text'       => [
                        'headline' => htmlspecialchars($story['arc_label'] ?? $story['title']),
                    ],
                ];

                $sceneSort = $storyStart;

                foreach ($scenes as $scene) {
                    $sceneId = (int)$scene['id'];
                    $blocks  = $highlightsByScene[$sceneId] ?? [];

                    if (empty($blocks)) {
                        $events[] = [
                            'start_date' => tljsDate($sceneSort),
                            'text'       => [
                                'headline' => htmlspecialchars($scene['title']),
                                'text'     => '<p style="opacity:.5;font-style:italic;">No highlight blocks yet.</p>',
                            ],
                            'group' => htmlspecialchars($scene['title']),
                            '_sage' => [
                                'type'     => 'plush_scene',
                                'scene_id' => $sceneId,
                                'story_id' => $sid,
                                'block_id' => null,
                            ],
                        ];
                        $sceneSort++;
                        continue;
                    }

                    foreach ($blocks as $b) {
                        $tagPills = '';
                        $sageEntities = [];
                        $galleriesHtml = '';
                        $includedSketchIds = [];

                        if (!empty($b['entity_tags'])) {
                            foreach ($b['entity_tags'] as $t) {
                                $sageEntities[] = $t['entity_type'] . ':' . $t['entity_id'];
                                $tagPills .= ' <span class="tl-entity-tag tl-entity-' . htmlspecialchars($t['entity_type']) . '" '
                                     . 'data-entity-type="' . htmlspecialchars($t['entity_type']) . '" '
                                     . 'data-entity-id="' . (int)$t['entity_id'] . '">'
                                     . htmlspecialchars($t['entity_label'] ?? '') . '</span>';
                                     
                                if ($t['entity_type'] === 'sketches') {
                                    $sketchId = (int)$t['entity_id'];
                                    if (!in_array($sketchId, $includedSketchIds)) {
                                        $includedSketchIds[] = $sketchId;
                                        if (!empty($framesBySketch[$sketchId])) {
                                            $galleriesHtml .= buildGalleryHtml($framesBySketch[$sketchId]);
                                        }
                                    }
                                }
                            }
                        }

                        $flagLabel = mb_substr($b['text_content'], 0, 50);
                        if (mb_strlen($b['text_content']) > 50) $flagLabel .= '…';
                        $events[] = [
                            'start_date' => tljsDate($sceneSort),
                            'text'       => [
                                'headline' => htmlspecialchars($flagLabel),
                                'text'     => '<p>' . htmlspecialchars($b['text_content']) . $tagPills . '</p>' . $galleriesHtml,
                            ],
                            'group'      => htmlspecialchars($scene['title']),
                            '_sage'      => [
                                'type'     => 'plush_block',
                                'block_id' => (int)$b['id'],
                                'scene_id' => $sceneId,
                                'story_id' => $sid,
                                'bg_color' => $b['bg_color'] ?? '',
                                'entities' => $sageEntities,
                            ],
                        ];
                        $sceneSort++;
                    }
                }

                $sortBase = $storyEnd + 2;
            }

            $tlData = [
                'title'  => $titleSlide,
                'events' => $events,
                'eras'   => $eras,
            ];

            echo json_encode(['success' => true, 'timeline' => $tlData]);
            break;


        // ── NARRATIVE SEQUENCES / CINEMAGICS TIMELINE JSON ──────────────────
        case 'cinemagic_timeline':
            $cinemagicId = (int)($_GET['cinemagic_id'] ?? 0);
            $sequenceId  = (int)($_GET['sequence_id']  ?? 0);

            if (!$cinemagicId && !$sequenceId) jsonErr('cinemagic_id or sequence_id required');

            if ($cinemagicId) {
                $stmt = $pdo->prepare(
                    "SELECT ns.*, c2s.sort_order AS c_sort, c2s.chapter_label,
                            c.name AS cinemagic_name
                     FROM narrative_sequences ns
                     JOIN cinemagics_2_sequences c2s ON c2s.sequence_id = ns.id
                     JOIN cinemagics c ON c.id = c2s.cinemagic_id
                     WHERE c2s.cinemagic_id = ?
                     ORDER BY c2s.sort_order ASC, ns.id ASC"
                );
                $stmt->execute([$cinemagicId]);
                $sequences = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $titleRow = null;
                $cmStmt = $pdo->prepare("SELECT * FROM cinemagics WHERE id = ?");
                $cmStmt->execute([$cinemagicId]);
                $titleRow = $cmStmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
                $stmt->execute([$sequenceId]);
                $sequences = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $titleRow = $sequences[0] ?? null;
            }

            if (empty($sequences)) jsonErr('No sequences found');

            $events = [];
            $eras   = [];
            $sortBase = 1;
            
            // Gather all sketch references upfront to preload frames efficiently
            $allSketchIds = [];
            foreach ($sequences as $seq) {
                $itemIds = json_decode($seq['sequence_data'] ?? '[]', true) ?: [];
                foreach ($itemIds as $item) {
                    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
                    if ($sid > 0) $allSketchIds[] = $sid;
                }
            }
            $allSketchIds = array_values(array_unique($allSketchIds));
            $framesBySketch = getFramesForSketches($pdo, $allSketchIds);

            foreach ($sequences as $seqIdx => $seq) {
                $itemIds = json_decode($seq['sequence_data'] ?? '[]', true) ?: [];
                $seqStart = $sortBase;
                $seqEnd   = $sortBase + max(0, count($itemIds) - 1);

                $eras[] = [
                    'start_date' => tljsDate($seqStart),
                    'end_date'   => tljsDate($seqEnd),
                    'text'       => [
                        'headline' => htmlspecialchars($seq['chapter_label'] ?? $seq['name']),
                    ],
                ];

                $sketchIds = [];
                foreach ($itemIds as $item) {
                    $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
                    if ($sid > 0) $sketchIds[] = $sid;
                }
                $sketchIds = array_values(array_unique($sketchIds));

                $sketchesData = [];
                $overlaysBySketch = [];
                if (!empty($sketchIds)) {
                    $inSk = implode(',', array_fill(0, count($sketchIds), '?'));
                    $stmtSk = $pdo->prepare("SELECT id, name, description FROM sketches WHERE id IN ($inSk)");
                    $stmtSk->execute($sketchIds);
                    foreach ($stmtSk->fetchAll(PDO::FETCH_ASSOC) as $sk) {
                        $sketchesData[(int)$sk['id']] = $sk;
                    }

                    $stmtOv = $pdo->prepare(
                        "SELECT sketch_id, text_content, display_order
                         FROM sketch_overlay_texts
                         WHERE sketch_id IN ($inSk) AND language_code = 'en'
                         ORDER BY display_order ASC"
                    );
                    $stmtOv->execute($sketchIds);
                    foreach ($stmtOv->fetchAll(PDO::FETCH_ASSOC) as $ov) {
                        $overlaysBySketch[(int)$ov['sketch_id']][] = $ov['text_content'];
                    }
                }

                foreach ($itemIds as $itemIdx => $item) {
                    $sketchId  = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
                    $frameId   = is_array($item) ? (int)($item['frame_id']   ?? 0) : 0;
                    if (!$sketchId || !isset($sketchesData[$sketchId])) continue;

                    $sk  = $sketchesData[$sketchId];
                    $ovs = $overlaysBySketch[$sketchId] ?? [];

                    if (!empty($ovs)) {
                        $bodyHtml = implode('', array_map(fn($t) => '<p>' . htmlspecialchars($t) . '</p>', $ovs));
                    } else {
                        $bodyHtml = '<p>' . htmlspecialchars($sk['description'] ?? '') . '</p>';
                    }

                    // ── Sketch chip — opens entity detail modal via bindEntityTagClicks() ──
                    $bodyHtml .= ' <span class="tl-entity-tag tl-entity-sketches"'
                        . ' data-entity-type="sketches"'
                        . ' data-entity-id="' . $sketchId . '">'
                        . htmlspecialchars($sk['name'])
                        . '</span>';
                        
                    if (!empty($framesBySketch[$sketchId])) {
                        $bodyHtml .= buildGalleryHtml($framesBySketch[$sketchId]);
                    }

                    $thumbUrl = '';
                    if ($frameId) {
                        $thumbUrl = 'view_frame.php?frame_id=' . $frameId;
                    }

                    $event = [
                        'start_date' => tljsDate($sortBase + $itemIdx),
                        'text'       => [
                            'headline' => htmlspecialchars($sk['name']),
                            'text'     => $bodyHtml,
                        ],
                        'group'  => htmlspecialchars($seq['chapter_label'] ?? $seq['name']),
                        '_sage'  => [
                            'type'       => 'sequence_sketch',
                            'sketch_id'  => $sketchId,
                            'sequence_id'=> (int)$seq['id'],
                            'frame_id'   => $frameId ?: null,
                            'thumb_url'  => $thumbUrl,
                        ],
                    ];
                    $events[] = $event;
                }

                $sortBase = $seqEnd + 2;
            }

            $titleSlide = [
                'text' => [
                    'headline' => htmlspecialchars($titleRow['cinemagic_name'] ?? $titleRow['name'] ?? 'Sequence Timeline'),
                    'text'     => htmlspecialchars($titleRow['description'] ?? ''),
                ],
            ];

            echo json_encode([
                'success'  => true,
                'timeline' => ['title' => $titleSlide, 'events' => $events, 'eras' => $eras],
            ]);
            break;


        // ── List endpoints ──────────────────────────────────────────────────
        case 'list_stories':
            $rows = $pdo->query("SELECT id, title FROM plush_stories ORDER BY id DESC LIMIT 300")
                        ->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'stories' => $rows]);
            break;

        case 'list_collections':
            $rows = $pdo->query("SELECT id, title FROM plush_collections ORDER BY title ASC LIMIT 200")
                        ->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'collections' => $rows]);
            break;

        case 'list_cinemagics':
            $rows = $pdo->query("SELECT id, name FROM cinemagics ORDER BY name ASC LIMIT 200")
                        ->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'cinemagics' => $rows]);
            break;

        case 'list_sequences':
            $rows = $pdo->query("SELECT id, name FROM narrative_sequences ORDER BY id DESC LIMIT 300")
                        ->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'sequences' => $rows]);
            break;


        // ── Scene date CRUD ──────────────────────────────────────────────────
        case 'save_scene_date':
            $sceneId    = (int)($_POST['scene_id']    ?? 0);
            $sortStart  = (int)($_POST['sort_start']  ?? 0);
            $sortEnd    = isset($_POST['sort_end']) && $_POST['sort_end'] !== '' ? (int)$_POST['sort_end'] : null;
            $startLabel = trim($_POST['start_label']  ?? '');
            $endLabel   = trim($_POST['end_label']    ?? '');
            if (!$sceneId) jsonErr('scene_id required');

            $stmt = $pdo->prepare(
                "INSERT INTO plush_scene_dates (scene_id, start_label, end_label, sort_start, sort_end)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   start_label = VALUES(start_label),
                   end_label   = VALUES(end_label),
                   sort_start  = VALUES(sort_start),
                   sort_end    = VALUES(sort_end)"
            );
            $stmt->execute([$sceneId, $startLabel ?: null, $endLabel ?: null, $sortStart, $sortEnd]);
            echo json_encode(['success' => true]);
            break;

        case 'save_story_date':
            $storyId    = (int)($_POST['story_id']    ?? 0);
            $sortStart  = (int)($_POST['sort_start']  ?? 0);
            $sortEnd    = isset($_POST['sort_end']) && $_POST['sort_end'] !== '' ? (int)$_POST['sort_end'] : null;
            $startLabel = trim($_POST['start_label']  ?? '');
            $endLabel   = trim($_POST['end_label']    ?? '');
            $eraLabel   = trim($_POST['era_label']    ?? '');
            if (!$storyId) jsonErr('story_id required');

            $stmt = $pdo->prepare(
                "INSERT INTO plush_story_dates (story_id, era_label, start_label, end_label, sort_start, sort_end)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   era_label   = VALUES(era_label),
                   start_label = VALUES(start_label),
                   end_label   = VALUES(end_label),
                   sort_start  = VALUES(sort_start),
                   sort_end    = VALUES(sort_end)"
            );
            $stmt->execute([$storyId, $eraLabel ?: null, $startLabel ?: null, $endLabel ?: null, $sortStart, $sortEnd]);
            echo json_encode(['success' => true]);
            break;


        // ── Entity tag CRUD on plush blocks ──────────────────────────────────
        case 'add_block_entity':
            $blockId    = (int)($_POST['block_id']    ?? 0);
            $entityType = trim($_POST['entity_type']  ?? '');
            $entityId   = (int)($_POST['entity_id']   ?? 0);
            if (!$blockId || !$entityType || !$entityId) throw new Exception('block_id, entity_type, entity_id required');

            $allowed = ['characters', 'factions', 'locations', 'animas', 'sketches'];
            if (!in_array($entityType, $allowed)) jsonErr('Invalid entity_type');

            $lStmt = $pdo->prepare("SELECT name AS lbl FROM `$entityType` WHERE id = ?");
            $lStmt->execute([$entityId]);
            $lRow = $lStmt->fetch(PDO::FETCH_ASSOC);
            $label = $lRow['lbl'] ?? '';

            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO plush_highlight_block_entities (block_id, entity_type, entity_id, entity_label)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$blockId, $entityType, $entityId, $label]);
            echo json_encode(['success' => true, 'entity_label' => $label]);
            break;

        case 'remove_block_entity':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) jsonErr('id required');
            $stmt = $pdo->prepare("DELETE FROM plush_highlight_block_entities WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'get_block_entities':
            $blockId = (int)($_GET['block_id'] ?? 0);
            if (!$blockId) jsonErr('block_id required');
            $stmt = $pdo->prepare("SELECT * FROM plush_highlight_block_entities WHERE block_id = ? ORDER BY entity_type, entity_label ASC");
            $stmt->execute([$blockId]);
            echo json_encode(['success' => true, 'entities' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;


        // ── Entity search ────────────────────────────────────────────────────
        case 'search_entities':
            $type  = trim($_GET['entity_type'] ?? '');
            $q     = trim($_GET['q'] ?? '');
            $limit = min((int)($_GET['limit'] ?? 15), 50);

            $allowed = ['characters', 'factions', 'locations', 'animas', 'sketches'];
            if (!in_array($type, $allowed)) throw new Exception('Invalid entity_type');

            if (strlen($q) < 1) {
                $stmt = $pdo->query("SELECT id, name FROM `$type` ORDER BY name ASC LIMIT $limit");
            } elseif (is_numeric($q)) {
                $stmt = $pdo->prepare("SELECT id, name FROM `$type` WHERE id = ? OR name LIKE ? ORDER BY name ASC LIMIT $limit");
                $stmt->execute([(int)$q, '%' . $q . '%']);
            } else {
                $stmt = $pdo->prepare("SELECT id, name FROM `$type` WHERE name LIKE ? ORDER BY name ASC LIMIT $limit");
                $stmt->execute(['%' . $q . '%']);
            }
            echo json_encode(['success' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;


        // ── Scene search ─────────────────────────────────────────────────────
        case 'search_scenes':
            $source = $_GET['source'] ?? '';
            $q      = trim($_GET['q'] ?? '');
            
            $parts = explode(':', $source);
            $kind = $parts[0] ?? '';
            $id   = (int)($parts[1] ?? 0);
            
            if (!$id || !in_array($kind, ['story', 'collection'])) {
                echo json_encode(['success' => true, 'results' => []]);
                break;
            }
            
            $sql = "SELECT id, title FROM plush_scenes ";
            $params = [];
            
            if ($kind === 'collection') {
                $sql .= "WHERE story_id IN (SELECT story_id FROM plush_collections_2_stories WHERE collection_id = ?) ";
            } else {
                $sql .= "WHERE story_id = ? ";
            }
            $params[] = $id;
            
            if ($q !== '') {
                $sql .= "AND (title LIKE ? OR id = ?) ";
                $params[] = "%$q%";
                $params[] = (int)$q;
            }
            
            $sql .= "ORDER BY scene_order ASC, id ASC LIMIT 50";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;


        // ── Get scene blocks with entity tags ────────────────────────────────
        case 'get_scene_blocks':
            $sceneId = (int)($_GET['scene_id'] ?? 0);
            if (!$sceneId) jsonErr('scene_id required');

            $stmt = $pdo->prepare(
                "SELECT b.*, GROUP_CONCAT(
                    CONCAT(e.id,'|',e.entity_type,'|',e.entity_id,'|',COALESCE(e.entity_label,''))
                    ORDER BY e.entity_type, e.entity_label SEPARATOR ';;'
                 ) AS entity_tags_raw
                 FROM plush_highlight_blocks b
                 LEFT JOIN plush_highlight_block_entities e ON e.block_id = b.id
                 WHERE b.scene_id = ? AND b.language_code = 'en'
                 GROUP BY b.id
                 ORDER BY b.group_id ASC, b.display_order ASC"
            );
            $stmt->execute([$sceneId]);
            $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($blocks as &$b) {
                $b['entity_tags'] = [];
                if (!empty($b['entity_tags_raw'])) {
                    foreach (explode(';;', $b['entity_tags_raw']) as $tagRaw) {
                        [$tid, $ttype, $teid, $tlabel] = explode('|', $tagRaw, 4);
                        $b['entity_tags'][] = [
                            'id'           => (int)$tid,
                            'entity_type'  => $ttype,
                            'entity_id'    => (int)$teid,
                            'entity_label' => $tlabel,
                        ];
                    }
                }
                unset($b['entity_tags_raw']);
            }
            unset($b);

            echo json_encode(['success' => true, 'blocks' => $blocks]);
            break;


        default:
            jsonErr('Invalid action', 400);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[timelines_api] PDO: ' . $e->getMessage());
    jsonErr('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonErr($e->getMessage(), 400);
}