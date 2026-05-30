<?php
/**
 * SAGE Cinemagic Hub — Manager
 * src/CinemagicHub/CinemagicHubManager.php
 *
 * Magazine Series Publishing interface for the Cinemagic narrative system.
 * Manages Series -> Cinemagics (Seasons) -> Narrative Sequences (Episodes).
 */

namespace App\CinemagicHub;

class CinemagicHubManager
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `cinemagic_series` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `title` varchar(255) NOT NULL,
              `description` text DEFAULT NULL,
              `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
              `asset_url_prefix` varchar(512) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `cinemagic_series_2_cinemagics` (
              `series_id` int(11) NOT NULL,
              `cinemagic_id` int(11) NOT NULL,
              `sort_order` int(11) NOT NULL DEFAULT 0,
              PRIMARY KEY (`series_id`,`cinemagic_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `sequence_overlay_texts` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `sequence_id` int(11) NOT NULL,
              `language_code` varchar(2) NOT NULL DEFAULT 'en',
              `name_overlay` varchar(255) DEFAULT NULL,
              `description_overlay` text DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_seq_lang` (`sequence_id`, `language_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    // ── Global System Languages ───────────────────────────────────────────────────

    public function getSystemLanguages(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM system_languages ORDER BY is_main DESC, code ASC");
        return ['success' => true, 'languages' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    public function saveSystemLanguage(string $code, string $name): array
    {
        $code = strtolower(trim($code));
        if (strlen($code) !== 2) return ['success' => false, 'error' => 'Code must be exactly 2 letters'];
        $stmt = $this->pdo->prepare("INSERT INTO system_languages (code, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = ?");
        $stmt->execute([$code, $name, $name]);
        return ['success' => true];
    }

    public function deleteSystemLanguage(string $code): array
    {
        if ($code === 'en') return ['success' => false, 'error' => 'Cannot delete main system language'];
        $stmt = $this->pdo->prepare("DELETE FROM system_languages WHERE code = ?");
        $stmt->execute([$code]);
        return ['success' => true];
    }

    // ── CRUD and Dashboard ────────────────────────────────────────────────────────

    public function getDashboardStats(): array
    {
        $seriesCount = (int)$this->pdo->query("SELECT COUNT(*) FROM cinemagic_series")->fetchColumn();
        $seasonCount = (int)$this->pdo->query("SELECT COUNT(*) FROM cinemagics")->fetchColumn();
        $epCount     = (int)$this->pdo->query("SELECT COUNT(*) FROM narrative_sequences")->fetchColumn();
        $pubCount    = (int)$this->pdo->query("SELECT COUNT(*) FROM cinemagic_series WHERE status = 'published'")->fetchColumn();

        return [
            'success' => true,
            'stats' => [
                'total_series'   => $seriesCount,
                'published'      => $pubCount,
                'total_seasons'  => $seasonCount,
                'total_episodes' => $epCount,
            ]
        ];
    }

    public function getPublishedMagazines(): array
    {
        $stmt = $this->pdo->query("SELECT id, title, cover_image_url, asset_url_prefix FROM cinemagic_series WHERE status = 'published' ORDER BY id DESC");
        $series = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($series as &$s) {
            $cover = $s['cover_image_url'] ?? '';
            if ($cover) {
                $prefix = $s['asset_url_prefix'] ?? '';
                if ($prefix !== '') {
                    $cover = rtrim($prefix, '/') . '/' . ltrim($cover, '/');
                } else {
                    $cover = str_starts_with($cover, '/') ? $cover : '/' . $cover;
                }
            }
            $s['resolved_cover'] = $cover;
        }

        return ['success' => true, 'magazines' => $series];
    }
    
    
    
    
       public function getPublishedMagazinesForLocal(): array
    {
        $stmt = $this->pdo->query("SELECT id, title, cover_image_url FROM cinemagic_series WHERE status = 'published' ORDER BY id DESC");
        $series = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($series as &$s) {
            $cover = $s['cover_image_url'] ?? '';
            if ($cover) {
                
                    $cover = str_starts_with($cover, '/') ? $cover : '/' . $cover;
                
            }
            $s['resolved_cover'] = $cover;
        }

        return ['success' => true, 'magazines' => $series];
    }

    public function getSeriesList(): array
    {
        $stmt = $this->pdo->query("SELECT id, title, status FROM cinemagic_series ORDER BY id DESC");
        return ['success' => true, 'series' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    public function getSeriesDetails(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM cinemagic_series WHERE id = ?");
        $stmt->execute([$id]);
        $series = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$series) return ['success' => false, 'error' => 'Series not found'];

        $sStmt = $this->pdo->prepare("
            SELECT c.id, c.name, sc.sort_order
            FROM cinemagic_series_2_cinemagics sc
            JOIN cinemagics c ON c.id = sc.cinemagic_id
            WHERE sc.series_id = ?
            ORDER BY sc.sort_order ASC
        ");
        $sStmt->execute([$id]);
        $seasons = $sStmt->fetchAll(\PDO::FETCH_ASSOC);

        return ['success' => true, 'series' => $series, 'seasons' => $seasons];
    }

    public function getUnassignedSeasons(int $seriesId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.name
            FROM cinemagics c
            WHERE c.id NOT IN (SELECT cinemagic_id FROM cinemagic_series_2_cinemagics WHERE series_id = ?)
            ORDER BY c.name ASC
        ");
        $stmt->execute([$seriesId]);
        return ['success' => true, 'seasons' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    public function saveSeries(array $data): array
    {
        $id     = !empty($data['id']) ? (int)$data['id'] : null;
        $title  = $data['title'] ?? 'Untitled Series';
        $desc   = $data['description'] ?? '';
        $status = $data['status'] ?? 'draft';
        $prefix = $data['asset_url_prefix'] ?? '';
        $cover  = $data['cover_image_url'] ?? null;
        $tmpl   = $data['template'] ?? 'default';
        $langs  = $data['supported_languages'] ?? 'en';

        if ($id) {
            $stmt = $this->pdo->prepare("UPDATE cinemagic_series SET title=?, description=?, status=?, asset_url_prefix=?, cover_image_url=?, template=?, supported_languages=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$title, $desc, $status, $prefix, $cover, $tmpl, $langs, $id]);
            return ['success' => true, 'id' => $id];
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO cinemagic_series (title, description, status, asset_url_prefix, cover_image_url, template, supported_languages, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$title, $desc, $status, $prefix, $cover, $tmpl, $langs]);
            return ['success' => true, 'id' => (int)$this->pdo->lastInsertId()];
        }
    }

    public function deleteSeries(int $id): array
    {
        $stmt = $this->pdo->prepare("DELETE FROM cinemagic_series WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true];
    }

    public function assignSeason(int $seriesId, int $cinemagicId): array
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM cinemagic_series_2_cinemagics WHERE series_id = ?");
        $stmt->execute([$seriesId]);
        $order = (int)$stmt->fetchColumn();

        $iStmt = $this->pdo->prepare("INSERT IGNORE INTO cinemagic_series_2_cinemagics (series_id, cinemagic_id, sort_order) VALUES (?, ?, ?)");
        $iStmt->execute([$seriesId, $cinemagicId, $order]);
        return ['success' => true];
    }

    public function removeSeason(int $seriesId, int $cinemagicId): array
    {
        $stmt = $this->pdo->prepare("DELETE FROM cinemagic_series_2_cinemagics WHERE series_id = ? AND cinemagic_id = ?");
        $stmt->execute([$seriesId, $cinemagicId]);
        return ['success' => true];
    }

    // ── Asset / Frame Picker ──────────────────────────────────────────────────────

    public function searchSequences(string $q, int $page, int $limit = 12): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $where  = '1=1';
        $params = [];
        if ($q !== '') {
            $where = '(name LIKE ? OR id = ?)';
            $params[] = "%$q%";
            $params[] = (int)$q;
        }

        $stmtC = $this->pdo->prepare("SELECT COUNT(*) FROM narrative_sequences WHERE $where");
        $stmtC->execute($params);
        $total = (int)$stmtC->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT id, name, JSON_LENGTH(sequence_data) AS frame_count FROM narrative_sequences WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return ['success' => true, 'items' => $items, 'pages' => max(1, (int)ceil($total / $limit))];
    }

    public function searchCinemagics(string $q, int $page, int $limit = 12): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $where  = '1=1';
        $params = [];
        if ($q !== '') {
            $where = '(c.name LIKE ? OR c.id = ?)';
            $params[] = "%$q%";
            $params[] = (int)$q;
        }

        $stmtC = $this->pdo->prepare("SELECT COUNT(*) FROM cinemagics c WHERE $where");
        $stmtC->execute($params);
        $total = (int)$stmtC->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT c.id, c.name, COUNT(cs.sequence_id) AS seq_count FROM cinemagics c LEFT JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = c.id WHERE $where GROUP BY c.id ORDER BY c.id DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($items as &$item) $item['meta'] = 'Cinemagic';

        return ['success' => true, 'items' => $items, 'pages' => max(1, (int)ceil($total / $limit))];
    }

    public function getSequenceFrames(int $sequenceId): array
    {
        $stmt = $this->pdo->prepare("SELECT sequence_data FROM narrative_sequences WHERE id = ?");
        $stmt->execute([$sequenceId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return [];

        $items = json_decode($row['sequence_data'] ?? '[]', true) ?: [];
        $assets = [];

        foreach ($items as $item) {
            $sid    = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
            $pinned = is_array($item) ? (int)($item['frame_id']  ?? 0) : 0;
            if ($sid <= 0) continue;

            $frameId = 0;
            $filename = '';
            if ($pinned > 0) {
                $fStmt = $this->pdo->prepare("SELECT id, name, filename FROM frames WHERE id = ?");
                $fStmt->execute([$pinned]);
                if ($fRow = $fStmt->fetch(\PDO::FETCH_ASSOC)) {
                    $frameId = (int)$fRow['id'];
                    $filename = $fRow['filename'];
                }
            }
            if (!$frameId) {
                $fStmt = $this->pdo->prepare("SELECT f.id, f.name, f.filename FROM frames f INNER JOIN frames_2_sketches m ON m.from_id = f.id WHERE f.entity_id = ? ORDER BY f.id DESC LIMIT 1");
                $fStmt->execute([$sid]);
                if ($fRow = $fStmt->fetch(\PDO::FETCH_ASSOC)) {
                    $frameId = (int)$fRow['id'];
                    $filename = $fRow['filename'];
                }
            }
            if ($filename) {
                $url = str_starts_with($filename, '/') ? $filename : '/' . $filename;
                $assets[] = ['id' => $frameId, 'name' => 'Frame #' . $frameId, 'url' => $url];
            }
        }
        return $assets;
    }

    // ── Slug / Repo Path Helpers ──────────────────────────────────────────────────

    private function seriesSlug(int $seriesId, string $title): string
    {
        return preg_replace('/[^a-z0-9]+/', '-', strtolower($title)) ?: 'series_' . $seriesId;
    }

    private function repoAssetsPath(int $seriesId, string $title): string
    {
        return 'cinemagic_hub/' . $this->seriesSlug($seriesId, $title) . '/assets';
    }

    private function resolveImageUrl(
        string $filename,
        string $urlPrefix = '',
        bool $makeLocalRelative = false,
        string $repoAssetPath = ''
    ): string {
        if ($filename === '') return '';

        if ($repoAssetPath !== '' && $urlPrefix !== '') {
            return rtrim($urlPrefix, '/') . '/' . trim($repoAssetPath, '/') . '/' . basename($filename);
        }

        if ($urlPrefix !== '') return rtrim($urlPrefix, '/') . '/' . ltrim($filename, '/');

        if ($makeLocalRelative) return 'assets/' . basename($filename);

        return str_starts_with($filename, '/') ? $filename : '/' . $filename;
    }

    // ── Data Resolution for Exports & Previews ────────────────────────────────────

    public function getEpisodeData(
        int    $seqId,
        string $urlPrefix         = '',
        bool   $makeLocalRelative = false,
        string $linkFormat        = 'ep_%d.html',
        string $repoAssetPath     = '',
        string $langCode          = 'en'
    ): array {
        $stmt = $this->pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
        $stmt->execute([$seqId]);
        $seq = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$seq) return [];

        $publicPathAbs = \App\Core\SpwBase::getInstance()->getPublicPath();

        // Determine series_id
        $stmtSer = $this->pdo->prepare("SELECT sc.series_id FROM cinemagics_2_sequences cs JOIN cinemagic_series_2_cinemagics sc ON sc.cinemagic_id = cs.cinemagic_id WHERE cs.sequence_id = ? LIMIT 1");
        $stmtSer->execute([$seqId]);
        $seriesId = $stmtSer->fetchColumn();

        // Check if PDF exists locally
        $pdfUrl = null;
        if ($seriesId) {
            $pdfFilename = "media/magazines/series_{$seriesId}/magazine_seq{$seqId}_{$langCode}.pdf";
            if (file_exists(rtrim($publicPathAbs, '/') . '/' . $pdfFilename)) {
                $pdfUrl = $this->resolveImageUrl($pdfFilename, $urlPrefix, $makeLocalRelative, $repoAssetPath);
            }
        }

        // Fetch Narrative Sequence Overlays (Title & Description)
        $stmtO = $this->pdo->prepare("SELECT name_overlay, description_overlay, language_code FROM sequence_overlay_texts WHERE sequence_id = ? AND language_code IN ('en', ?)");
        $stmtO->execute([$seqId, $langCode]);
        $seqOverlays = [];
        foreach ($stmtO->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $seqOverlays[$row['language_code']] = $row;
        }

        $finalSeqName = $seq['name'];
        $finalSeqDesc = $seq['description'];

        if (!empty($seqOverlays['en']['name_overlay'])) $finalSeqName = $seqOverlays['en']['name_overlay'];
        if (!empty($seqOverlays['en']['description_overlay'])) $finalSeqDesc = $seqOverlays['en']['description_overlay'];

        if ($langCode !== 'en' && isset($seqOverlays[$langCode])) {
            if (!empty($seqOverlays[$langCode]['name_overlay'])) $finalSeqName = $seqOverlays[$langCode]['name_overlay'];
            if (!empty($seqOverlays[$langCode]['description_overlay'])) $finalSeqDesc = $seqOverlays[$langCode]['description_overlay'];
        }

        $itemIds          = json_decode($seq['sequence_data'] ?? '[]', true) ?: [];
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
            $stmtS    = $this->pdo->prepare("SELECT * FROM sketches WHERE id IN ($inClause)");
            $stmtS->execute($pureSketchIds);
            foreach ($stmtS->fetchAll(\PDO::FETCH_ASSOC) as $row) $sketchesData[(int)$row['id']] = $row;
        }

        $selectedFrameMap = [];
        $activeFrameIds   = array_values(array_unique(array_filter($selectedFrameIds)));
        if (!empty($activeFrameIds)) {
            $inClauseFrames = implode(',', array_fill(0, count($activeFrameIds), '?'));
            $stmtF          = $this->pdo->prepare("SELECT * FROM frames WHERE id IN ($inClauseFrames)");
            $stmtF->execute($activeFrameIds);
            foreach ($stmtF->fetchAll(\PDO::FETCH_ASSOC) as $row) $selectedFrameMap[(int)$row['id']] = $row;
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
            $stmtFb     = $this->pdo->prepare("SELECT f.*, f.entity_id AS _sketch_id FROM frames f INNER JOIN frames_2_sketches m ON m.from_id = f.id WHERE f.entity_id IN ($inClauseFb) ORDER BY f.id DESC");
            $stmtFb->execute($sketchIdsNeedingLatestFrame);
            foreach ($stmtFb->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $sketchId = (int)$row['_sketch_id'];
                if (!isset($latestFrameBySketch[$sketchId])) $latestFrameBySketch[$sketchId] = $row;
            }
        }

        // Overlay Texts - Support Fallback System for Sketches
        $overlayTexts = [];
        $overlayTextsLang = [];
        if (!empty($pureSketchIds)) {
            $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));
            try {
                $stmtO = $this->pdo->prepare("SELECT sketch_id, text_content, language_code, display_order FROM sketch_overlay_texts WHERE sketch_id IN ($inClause) AND language_code IN ('en', ?) ORDER BY display_order ASC, id ASC");
                $stmtO->execute([...$pureSketchIds, $langCode]);
                foreach ($stmtO->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    if ($row['language_code'] === 'en') {
                        $overlayTexts[(int)$row['sketch_id']][] = $row;
                    } else {
                        $overlayTextsLang[(int)$row['sketch_id']][$row['display_order']] = $row['text_content'];
                    }
                }
            } catch (\Exception $e) {}
        }

        $stmtCM = $this->pdo->prepare("SELECT c.id, c.name FROM cinemagics c JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = c.id WHERE cs.sequence_id = ? ORDER BY c.id ASC LIMIT 1");
        $stmtCM->execute([$seqId]);
        $cinemagicInfo = $stmtCM->fetch(\PDO::FETCH_ASSOC);

        $episodesNav = [];
        if ($cinemagicInfo) {
            $stmtEp = $this->pdo->prepare("
                SELECT ns.id, ns.name, cs.sort_order, cs.chapter_label,
                       so_en.name_overlay AS en_name, so_lang.name_overlay AS lang_name
                FROM narrative_sequences ns 
                JOIN cinemagics_2_sequences cs ON cs.sequence_id = ns.id 
                LEFT JOIN sequence_overlay_texts so_en ON so_en.sequence_id = ns.id AND so_en.language_code = 'en'
                LEFT JOIN sequence_overlay_texts so_lang ON so_lang.sequence_id = ns.id AND so_lang.language_code = ?
                WHERE cs.cinemagic_id = ? 
                ORDER BY cs.sort_order ASC, ns.id ASC
            ");
            $stmtEp->execute([$langCode, (int)$cinemagicInfo['id']]);
            foreach ($stmtEp->fetchAll(\PDO::FETCH_ASSOC) as $ep) {
                $epName = $ep['name'];
                if (!empty($ep['en_name'])) $epName = $ep['en_name'];
                if ($langCode !== 'en' && !empty($ep['lang_name'])) $epName = $ep['lang_name'];

                $episodesNav[] = [
                    'id'            => $ep['id'],
                    'name'          => $epName,
                    'chapter_label' => $ep['chapter_label'],
                    'url'           => sprintf($linkFormat, $ep['id'])
                ];
            }
        }

        $frames = [];
        foreach ($itemIds as $idx => $item) {
            $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
            if ($sid <= 0 || !isset($sketchesData[$sid])) continue;

            $sketchRow     = $sketchesData[$sid];
            $activeFrameId = $selectedFrameIds[$idx] ?? null;
            $frameRow      = null;

            if ($activeFrameId && isset($selectedFrameMap[$activeFrameId])) {
                $frameRow = $selectedFrameMap[$activeFrameId];
            } elseif (!$activeFrameId && isset($latestFrameBySketch[$sid])) {
                $frameRow = $latestFrameBySketch[$sid];
            }

            $filename = $frameRow['filename'] ?? '';
            $imageUrl = $this->resolveImageUrl($filename, $urlPrefix, $makeLocalRelative, $repoAssetPath);

            $overrideTexts = [];
            if (isset($overlayTexts[$sid])) {
                foreach ($overlayTexts[$sid] as $enRow) {
                    $dispOrder = $enRow['display_order'];
                    if ($langCode !== 'en' && isset($overlayTextsLang[$sid][$dispOrder]) && trim($overlayTextsLang[$sid][$dispOrder]) !== '') {
                        $overrideTexts[] = $overlayTextsLang[$sid][$dispOrder];
                    } else {
                        $overrideTexts[] = $enRow['text_content'];
                    }
                }
            } else {
                $desc = $sketchRow['description'] ?? $sketchRow['desc'] ?? $sketchRow['prompt'] ?? '';
                if (!empty(trim($desc))) $overrideTexts = [trim($desc)];
            }

            $frames[] = [
                'id'                => $sid,
                'thumb'             => $imageUrl,
                'image_url'         => $imageUrl,
                'url'               => $imageUrl,
                'original_filename' => $filename,
                'filename'          => $filename,
                'overlay_texts'     => array_map('htmlspecialchars', $overrideTexts)
            ];
        }

        return [
            'id'           => $seqId,
            'name'         => $finalSeqName,
            'description'  => $finalSeqDesc,
            'cinemagic'    => $cinemagicInfo,
            'episodes_nav' => $episodesNav,
            'pdf_url'      => $pdfUrl,
            'frames'       => $frames
        ];
    }

    public function renderEpisodeHtml(array $epData): string
    {
        $title       = htmlspecialchars($epData['name']);
        $cmName      = $epData['cinemagic'] ? htmlspecialchars($epData['cinemagic']['name']) : '';
        $titleSuffix = $cmName ? ' — ' . $cmName : '';
        $desc        = !empty($epData['description']) ? nl2br(htmlspecialchars($epData['description'])) : '';
        $showNav     = count($epData['episodes_nav']) > 1;

        $currentIdx = 0;
        foreach ($epData['episodes_nav'] as $i => $ep) {
            if ((int)$ep['id'] === (int)$epData['id']) {
                $currentIdx = $i;
                break;
            }
        }
        $prevEp = ($currentIdx > 0) ? $epData['episodes_nav'][$currentIdx - 1] : null;
        $nextEp = ($currentIdx < count($epData['episodes_nav']) - 1) ? $epData['episodes_nav'][$currentIdx + 1] : null;

        $panelsHtml = '';
        foreach ($epData['frames'] as $index => $f) {
            $imgHtml = '';
            if (!empty($f['thumb'])) {
                $imgHtml = '<img src="' . htmlspecialchars($f['thumb']) . '" class="panel-img observe-me" loading="lazy" alt="Frame ' . ($index + 1) . '">';
            }
            $txtHtml = '';
            if (!empty($f['overlay_texts'])) {
                $txtHtml = '<div class="text-blocks">';
                foreach ($f['overlay_texts'] as $txt) {
                    $txtHtml .= '<div class="story-text observe-me">' . nl2br($txt) . '</div>';
                }
                $txtHtml .= '</div>';
            }
            $panelsHtml .= '<div class="panel" id="panel-' . $index . '">' . $imgHtml . $txtHtml . '</div>';
        }
        if (empty($epData['frames'])) {
            $panelsHtml = '<div class="panel observe-me visible" style="margin-top:100px;"><div class="story-text">This sequence has no frames to display.</div></div>';
        }

        $navHtml = '';
        if ($showNav) {
            $prevLink  = $prevEp ? '<a class="ep-pn-btn" href="' . $prevEp['url'] . '">&#9664; ' . htmlspecialchars($prevEp['chapter_label'] ?: $prevEp['name']) . '</a>' : '<span class="ep-pn-btn disabled">&#9664; Previous</span>';
            $nextLink  = $nextEp ? '<a class="ep-pn-btn" href="' . $nextEp['url'] . '">' . htmlspecialchars($nextEp['chapter_label'] ?: $nextEp['name']) . ' &#9654;</a>' : '<span class="ep-pn-btn disabled">Next &#9654;</span>';
            $pillsHtml = '';
            foreach ($epData['episodes_nav'] as $ep) {
                $activeClass = (int)$ep['id'] === (int)$epData['id'] ? ' active' : '';
                $aria        = (int)$ep['id'] === (int)$epData['id'] ? ' aria-current="page"' : '';
                $label       = htmlspecialchars($ep['chapter_label'] ?: $ep['name']);
                $pillsHtml  .= '<a class="ep-pill' . $activeClass . '" href="' . $ep['url'] . '"' . $aria . '>' . $label . '</a>';
            }
            $navHtml = <<<HTML
<nav class="ep-nav" id="ep-nav" aria-label="Episode Navigation">
    <button class="ep-nav-toggle" id="ep-nav-toggle" aria-expanded="false" aria-controls="ep-nav-bar">
        <span>Episodes</span>
        <span class="ep-arrow">&#9650;</span>
    </button>
    <div class="ep-nav-bar" id="ep-nav-bar" role="region">
        <div class="ep-nav-inner">
            <div class="ep-pn">$prevLink $nextLink</div>
            <div class="ep-list">$pillsHtml</div>
        </div>
    </div>
</nav>
HTML;
        }

        $cmLabelHtml = '';
        if ($cmName) {
            $cmLabelText = $cmName . ($showNav ? '&nbsp;·&nbsp; ' . ($currentIdx + 1) . ' / ' . count($epData['episodes_nav']) : '');
            $cmLabelHtml = '<div style="font-family:var(--font-title);font-size:0.65rem;letter-spacing:3px;color:var(--accent-color);opacity:0.6;margin-bottom:14px;text-transform:uppercase;">' . $cmLabelText . '</div>';
        }

        $pdfBtnHtml = '';
        if (!empty($epData['pdf_url'])) {
            $pdfBtnHtml = <<<HTML
<a href="{$epData['pdf_url']}" class="lang-toggle" style="text-decoration:none;" download>
    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
    <span class="lang-label">PDF</span>
</a>
HTML;
        }

        $langHtml = '';
        if (!empty($epData['available_langs']) && count($epData['available_langs']) > 1) {
            $langOptions = '';
            foreach ($epData['available_langs'] as $l) {
                if (!empty($epData['is_preview'])) {
                    $url = "api.php?action=preview_episode&series_id={$epData['series_id']}&seq_id={$epData['id']}&lang={$l}";
                } else {
                    $suffix = $l === 'en' ? '' : '_' . $l;
                    $url = "ep_{$epData['id']}{$suffix}.html";
                }
                $langOptions .= '<a href="' . $url . '">' . strtoupper($l) . '</a>';
            }
            $currL = strtoupper($epData['current_lang'] ?? 'EN');
            $langHtml = <<<HTML
<div class="lang-picker" id="lang-picker">
    <button class="lang-toggle" id="lang-toggle" aria-expanded="false">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path><path d="M2 12h20"></path></svg>
        <span class="lang-label">{$currL}</span>
    </button>
    <div class="lang-menu">{$langOptions}</div>
</div>
<script>
document.getElementById('lang-toggle')?.addEventListener('click', function(e) {
    e.stopPropagation(); document.getElementById('lang-picker').classList.toggle('open');
});
document.addEventListener('click', function(e) {
    const p = document.getElementById('lang-picker');
    if (p && !p.contains(e.target)) p.classList.remove('open');
});
</script>
HTML;
        }

        $topRightHtml = '';
        if ($langHtml || $pdfBtnHtml) {
            $topRightHtml = '<div class="top-actions">' . $pdfBtnHtml . $langHtml . '</div>';
        }

        $descHtml  = $desc ? '<p class="seq-desc">' . $desc . '</p>' : '';
        $bodyClass = $showNav ? ' class="has-ep-nav"' : '';

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=0" />
    <title>{$title}{$titleSuffix}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Lora:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
    <style>
        :root { --bg-color: #020202; --text-color: #e5e0d8; --accent-color: #cda434; --font-title: 'Cinzel', serif; --font-body: 'Lora', serif; --nav-bg: rgba(8,6,4,0.82); --nav-border: rgba(205,164,52,0.18); --nav-text: #c8b88a; --nav-active-bg: rgba(205,164,52,0.14); --nav-active: #cda434; }
        body, html { margin: 0; padding: 0; background-color: var(--bg-color); background-image: radial-gradient(circle at 50% 0%, #15151a 0%, #020202 60%); background-attachment: fixed; color: var(--text-color); font-family: var(--font-body); overscroll-behavior: none; -webkit-font-smoothing: antialiased; }
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
        
        .top-actions { position: fixed; top: 20px; right: 20px; z-index: 999; display: flex; gap: 8px; align-items: flex-start; }
        .lang-picker { position: relative; }
        .lang-toggle { background: var(--nav-bg); border: 1px solid var(--nav-border); border-radius: 20px; padding: 6px 12px; color: var(--nav-text); font-family: var(--font-title); font-size: 0.6rem; cursor: pointer; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); display: flex; align-items: center; gap: 6px; transition: color 0.2s; }
        .lang-toggle:hover { color: var(--nav-active); }
        .lang-menu { position: absolute; top: 100%; right: 0; margin-top: 8px; background: var(--nav-bg); border: 1px solid var(--nav-border); border-radius: 8px; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); display: none; flex-direction: column; overflow: hidden; min-width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
        .lang-picker.open .lang-menu { display: flex; }
        .lang-menu a { padding: 10px 16px; color: var(--nav-text); text-decoration: none; font-family: var(--font-title); font-size: 0.65rem; text-align: center; transition: background 0.2s, color 0.2s; }
        .lang-menu a:hover { background: var(--nav-active-bg); color: var(--nav-active); }
        
        .ep-nav { position: fixed; bottom: 0; left: 0; right: 0; z-index: 200; display: flex; flex-direction: column; --ep-bar-h: 44px; }
        .ep-nav-toggle { align-self: center; margin-bottom: -1px; background: var(--nav-bg); border: 1px solid var(--nav-border); border-bottom: none; border-radius: 8px 8px 0 0; padding: 5px 18px 2px; font-family: var(--font-title); font-size: 0.6rem; letter-spacing: 2px; color: var(--nav-text); cursor: pointer; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); transition: color 0.2s; user-select: none; display: flex; align-items: center; gap: 8px; }
        .ep-nav-toggle:hover { color: var(--nav-active); }
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
<body{$bodyClass}>
{$topRightHtml}
<div class="story-container">
    <div class="seq-header observe-me visible">
        {$cmLabelHtml}
        <h1 class="seq-title">{$title}</h1>
        {$descHtml}
    </div>
    {$panelsHtml}
    <div class="end-mark observe-me">&#10086; FIN &#10086;</div>
</div>
{$navHtml}
<script>
document.addEventListener('DOMContentLoaded', function() {
    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) { entry.target.classList.add('visible'); obs.unobserve(entry.target); }
        });
    }, { root: null, rootMargin: '0px 0px -15% 0px', threshold: 0 });
    document.querySelectorAll('.observe-me').forEach(el => observer.observe(el));
    const nav    = document.getElementById('ep-nav');
    const toggle = document.getElementById('ep-nav-toggle');
    if (toggle && nav) {
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
    }
});
</script>
</body>
</html>
HTML;
    }

    public function renderSeriesIndexHtml(
        int    $seriesId,
        bool   $isPreview         = false,
        string $urlPrefix         = '',
        bool   $makeLocalRelative = false,
        string $repoAssetPath     = '',
        string $langCode          = 'en',
        array  $availableLangs    = ['en']
    ): string {
        $seriesStmt = $this->pdo->prepare("SELECT * FROM cinemagic_series WHERE id = ?");
        $seriesStmt->execute([$seriesId]);
        $series = $seriesStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$series) return 'Series not found.';

        $epStmt = $this->pdo->prepare("
            SELECT ns.id, ns.name, c.name as season_name,
                   so_en.name_overlay AS en_name, so_lang.name_overlay AS lang_name
            FROM cinemagic_series_2_cinemagics sc
            JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = sc.cinemagic_id
            JOIN narrative_sequences ns ON ns.id = cs.sequence_id
            JOIN cinemagics c ON c.id = sc.cinemagic_id
            LEFT JOIN sequence_overlay_texts so_en ON so_en.sequence_id = ns.id AND so_en.language_code = 'en'
            LEFT JOIN sequence_overlay_texts so_lang ON so_lang.sequence_id = ns.id AND so_lang.language_code = ?
            WHERE sc.series_id = ?
            ORDER BY sc.sort_order ASC, cs.sort_order ASC
        ");
        $epStmt->execute([$langCode, $seriesId]);
        $episodes = $epStmt->fetchAll(\PDO::FETCH_ASSOC);

        $firstEpId   = !empty($episodes) ? (int)$episodes[0]['id'] : 0;
        $firstEpHref = '';
        if ($firstEpId) {
            $suffix = $langCode === 'en' ? '' : '_' . $langCode;
            $firstEpHref = $isPreview
                ? "api.php?action=preview_episode&series_id={$seriesId}&seq_id={$firstEpId}&lang={$langCode}"
                : "ep_{$firstEpId}{$suffix}.html";
        }

        $cover = $series['cover_image_url'] ?? '';
        if ($cover) {
            if ($repoAssetPath !== '') {
                $cover = rtrim($urlPrefix, '/') . '/' . trim($repoAssetPath, '/') . '/' . basename($cover);
            } elseif ($makeLocalRelative) {
                $cover = 'assets/' . basename($cover);
            } elseif ($urlPrefix) {
                $cover = rtrim($urlPrefix, '/') . '/' . ltrim($cover, '/');
            } else {
                $cover = str_starts_with($cover, '/') ? $cover : '/' . $cover;
            }
        }

        $title    = htmlspecialchars($series['title']);
        $desc     = $series['description'] ? nl2br(htmlspecialchars($series['description'])) : '';
        $template = $series['template'] ?? 'default';

        $previewBadge = $isPreview
            ? "<div style='position:absolute;top:20px;left:50%;transform:translateX(-50%);background:var(--accent-color);color:#000;padding:6px 16px;border-radius:4px;font-family:var(--font-title);font-size:0.7rem;font-weight:bold;letter-spacing:2px;z-index:999;text-transform:uppercase;'>Preview Mode</div>"
            : "";

        $tocHtml       = '';
        $currentSeason = '';
        foreach ($episodes as $ep) {
            $seqId = $ep['id'];
            if ($ep['season_name'] !== $currentSeason) {
                $currentSeason = $ep['season_name'];
                $tocHtml .= "<h2 class='season-hdr'>" . htmlspecialchars($currentSeason) . "</h2>";
            }
            $suffix = $langCode === 'en' ? '' : '_' . $langCode;
            $href = $isPreview
                ? "api.php?action=preview_episode&series_id={$seriesId}&seq_id={$seqId}&lang={$langCode}"
                : "ep_{$seqId}{$suffix}.html";

            $epName = $ep['name'];
            if (!empty($ep['en_name'])) $epName = $ep['en_name'];
            if ($langCode !== 'en' && !empty($ep['lang_name'])) $epName = $ep['lang_name'];

            $tocHtml .= "<a class='ep-link' href='{$href}'>&#9654; " . htmlspecialchars($epName) . "</a>";
        }

        $langHtml = '';
        if (count($availableLangs) > 1) {
            $langOptions = '';
            foreach ($availableLangs as $l) {
                if ($isPreview) {
                    $url = "api.php?action=preview_series&id={$seriesId}&lang={$l}";
                } else {
                    $suffix = $l === 'en' ? '' : '_' . $l;
                    $url = "index{$suffix}.html";
                }
                $langOptions .= '<a href="' . $url . '">' . strtoupper($l) . '</a>';
            }
            $currL = strtoupper($langCode);
            $langHtml = <<<HTML
<div class="lang-picker" id="lang-picker">
    <button class="lang-toggle" id="lang-toggle" aria-expanded="false">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path><path d="M2 12h20"></path></svg>
        <span class="lang-label">{$currL}</span>
    </button>
    <div class="lang-menu">{$langOptions}</div>
</div>
<script>
document.getElementById('lang-toggle')?.addEventListener('click', function(e) {
    e.stopPropagation(); document.getElementById('lang-picker').classList.toggle('open');
});
document.addEventListener('click', function(e) {
    const p = document.getElementById('lang-picker');
    if (p && !p.contains(e.target)) p.classList.remove('open');
});
</script>
HTML;
        }

        $commonStyles = "
        .top-actions { position: fixed; top: 20px; right: 20px; z-index: 999; display: flex; gap: 8px; align-items: flex-start; }
        .lang-picker { position: relative; }
        .lang-toggle { background: var(--bg-color); border: 1px solid rgba(205,164,52,0.18); border-radius: 20px; padding: 6px 12px; color: #c8b88a; font-family: var(--font-title); font-size: 0.6rem; cursor: pointer; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); display: flex; align-items: center; gap: 6px; transition: color 0.2s; }
        .lang-toggle:hover { color: var(--accent-color); }
        .lang-menu { position: absolute; top: 100%; right: 0; margin-top: 8px; background: var(--bg-color); border: 1px solid rgba(205,164,52,0.18); border-radius: 8px; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); display: none; flex-direction: column; overflow: hidden; min-width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
        .lang-picker.open .lang-menu { display: flex; }
        .lang-menu a { padding: 10px 16px; color: #c8b88a; text-decoration: none; font-family: var(--font-title); font-size: 0.65rem; text-align: center; transition: background 0.2s, color 0.2s; }
        .lang-menu a:hover { background: rgba(205,164,52,0.14); color: var(--accent-color); }
        ";
        
        $topRightHtml = '';
        if ($langHtml) {
            $topRightHtml = '<div class="top-actions">' . $langHtml . '</div>';
        }

        if ($template === 'hero_backdrop' && $cover) {
            $heroBgTag = $firstEpHref
                ? "<a href='{$firstEpHref}' class='hero-bg' aria-label='Read first episode'></a>"
                : "<div class='hero-bg'></div>";

            return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{$title}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Lora:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
    <style>
        :root { --bg-color: #020202; --text-color: #e5e0d8; --accent-color: #cda434; --font-title: 'Cinzel', serif; --font-body: 'Lora', serif; }
        body, html { margin: 0; padding: 0; background-color: var(--bg-color); color: var(--text-color); font-family: var(--font-body); min-height: 100vh; overflow-x: hidden; }
        .hero-bg { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: url('{$cover}') center/cover no-repeat; z-index: 1; display: block; text-decoration: none; cursor: pointer; }
        .hero-bg::after { content:''; position:absolute; inset:0; background: linear-gradient(to bottom, rgba(2,2,2,0.6) 0%, rgba(2,2,2,0.95) 100%); }
        .content { position: relative; z-index: 10; max-width: 800px; margin: 0 auto; padding: 100px 20px 80px; display: flex; flex-direction: column; align-items: center; text-align: center; }
        .series-title { font-family: var(--font-title); font-size: 3rem; font-weight: 400; margin: 0 0 24px; color: var(--accent-color); line-height: 1.2; letter-spacing: 3px; text-shadow: 0 4px 20px rgba(0,0,0,0.8); }
        .series-desc { font-size: 1.15rem; opacity: 0.85; line-height: 1.7; max-width: 650px; margin: 0 auto 60px; text-shadow: 0 2px 10px rgba(0,0,0,0.8); }
        .toc { width: 100%; max-width: 500px; text-align: left; background: rgba(10,10,15,0.6); padding: 40px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); backdrop-filter: blur(10px); }
        .season-hdr { font-family: var(--font-title); font-size: 1rem; color: #888; letter-spacing: 2px; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; margin: 30px 0 16px; }
        .season-hdr:first-child { margin-top: 0; }
        .ep-link { display: block; color: var(--text-color); text-decoration: none; font-size: 1.1rem; padding: 8px 0; transition: color 0.2s, transform 0.2s; border-radius: 4px; }
        .ep-link:hover { color: var(--accent-color); transform: translateX(6px); }
        {$commonStyles}
        @media (max-width: 768px) { .series-title { font-size: 2.2rem; } .toc { padding: 25px 20px; } }
    </style>
</head>
<body>
    {$previewBadge}
    {$topRightHtml}
    {$heroBgTag}
    <div class="content">
        <h1 class="series-title">{$title}</h1>
        <p class="series-desc">{$desc}</p>
        <div class="toc">{$tocHtml}</div>
    </div>
</body>
</html>
HTML;
        }

        if ($cover && $firstEpHref) {
            $coverHtml = "<a href='{$firstEpHref}'><img src='{$cover}' class='cover-img' alt='Cover'></a>";
        } elseif ($cover) {
            $coverHtml = "<img src='{$cover}' class='cover-img' alt='Cover'>";
        } else {
            $coverHtml = '';
        }

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{$title}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Lora:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
    <style>
        :root { --bg-color: #020202; --text-color: #e5e0d8; --accent-color: #cda434; --font-title: 'Cinzel', serif; --font-body: 'Lora', serif; }
        body, html { margin: 0; padding: 0; background-color: var(--bg-color); background-image: radial-gradient(circle at 50% 0%, #15151a 0%, #020202 60%); background-attachment: fixed; color: var(--text-color); font-family: var(--font-body); min-height: 100vh; }
        .content { max-width: 800px; margin: 0 auto; padding: 80px 20px; display: flex; flex-direction: column; align-items: center; text-align: center; }
        .cover-img { width: 100%; max-width: 500px; height: auto; border-radius: 4px; box-shadow: 0 10px 40px rgba(0,0,0,0.8); margin-bottom: 40px; border: 1px solid rgba(255,255,255,0.05); }
        .series-title { font-family: var(--font-title); font-size: 2.8rem; font-weight: 400; margin: 0 0 20px; color: var(--accent-color); line-height: 1.2; letter-spacing: 2px; }
        .series-desc { font-size: 1.15rem; opacity: 0.8; line-height: 1.7; max-width: 650px; margin: 0 auto 60px; }
        .toc { width: 100%; max-width: 600px; text-align: left; }
        .season-hdr { font-family: var(--font-title); font-size: 1rem; color: #888; letter-spacing: 2px; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; margin: 30px 0 16px; }
        .ep-link { display: block; color: var(--text-color); text-decoration: none; font-size: 1.1rem; padding: 8px 0; transition: color 0.2s, transform 0.2s; }
        .ep-link:hover { color: var(--accent-color); transform: translateX(6px); }
        {$commonStyles}
        @media (max-width: 768px) { .series-title { font-size: 2.2rem; } }
    </style>
</head>
<body>
    {$previewBadge}
    {$topRightHtml}
    <div class="content">
        {$coverHtml}
        <h1 class="series-title">{$title}</h1>
        <p class="series-desc">{$desc}</p>
        <div class="toc">{$tocHtml}</div>
    </div>
</body>
</html>
HTML;
    }

    // ── Rollout and Export Zip Generation ─────────────────────────────────────────

    public function exportSeriesZip(int $seriesId, string $publicPathAbs): ?string
    {
        $seriesStmt = $this->pdo->prepare("SELECT * FROM cinemagic_series WHERE id = ?");
        $seriesStmt->execute([$seriesId]);
        $series = $seriesStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$series) return null;

        $tempDir = sys_get_temp_dir();
        $zipName = $tempDir . '/magazine_series_' . $seriesId . '_' . time() . '.zip';
        $zip     = new \ZipArchive();
        if ($zip->open($zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) return null;

        $urlPrefix      = $series['asset_url_prefix'] ?? '';
        $repoAssetsPath = $this->repoAssetsPath($seriesId, $series['title']);
        $langsRaw       = $series['supported_languages'] ?? 'en';
        $langs          = array_filter(array_map('trim', explode(',', $langsRaw)));
        if (!in_array('en', $langs)) array_unshift($langs, 'en');

        $epStmt = $this->pdo->prepare("
            SELECT ns.id, ns.name, c.name as season_name
            FROM cinemagic_series_2_cinemagics sc
            JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = sc.cinemagic_id
            JOIN narrative_sequences ns ON ns.id = cs.sequence_id
            JOIN cinemagics c ON c.id = sc.cinemagic_id
            WHERE sc.series_id = ?
            ORDER BY sc.sort_order ASC, cs.sort_order ASC
        ");
        $epStmt->execute([$seriesId]);
        $episodes = $epStmt->fetchAll(\PDO::FETCH_ASSOC);

        $copiedAssets = [];

        $cover = $series['cover_image_url'] ?? '';
        if ($cover) {
            $fn = ltrim($cover, '/');
            if (!str_starts_with($fn, 'http')) {
                $abs = rtrim($publicPathAbs, '/') . '/' . $fn;
                if (file_exists($abs) && !in_array($fn, $copiedAssets)) {
                    $zip->addFile($abs, 'assets/' . basename($fn));
                    $copiedAssets[] = $fn;
                }
            }
        }

        $makeRel = empty($urlPrefix);

        foreach ($langs as $lang) {
            $suffix = $lang === 'en' ? '' : '_' . $lang;
            $epLinkFormat = "ep_%d{$suffix}.html";

            foreach ($episodes as $ep) {
                $seqId = $ep['id'];

                $epDataForHtml = $this->getEpisodeData($seqId, $urlPrefix, $makeRel, $epLinkFormat, $repoAssetsPath, $lang);
                $epDataForHtml['available_langs'] = $langs;
                $epDataForHtml['current_lang']    = $lang;
                
                $zip->addFromString("ep_{$seqId}{$suffix}.html", $this->renderEpisodeHtml($epDataForHtml));

                $sidecarJson = json_encode($epDataForHtml, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $zip->addFromString("data/ep_{$seqId}{$suffix}.js", "const episodeData = " . $sidecarJson . ";\n// export default episodeData;");

                // Package corresponding PDF
                $pdfLocalRel = "media/magazines/series_{$seriesId}/magazine_seq{$seqId}_{$lang}.pdf";
                $absPdf = rtrim($publicPathAbs, '/') . '/' . $pdfLocalRel;
                if (file_exists($absPdf) && !in_array($pdfLocalRel, $copiedAssets)) {
                    $zip->addFile($absPdf, 'assets/' . basename($absPdf));
                    $copiedAssets[] = $pdfLocalRel;
                }

                if ($lang === 'en') {
                    foreach ($epDataForHtml['frames'] as $frame) {
                        $fn = ltrim($frame['original_filename'] ?? '', '/');
                        if ($fn && !str_starts_with($fn, 'http') && !in_array($fn, $copiedAssets)) {
                            $abs = rtrim($publicPathAbs, '/') . '/' . $fn;
                            if (file_exists($abs)) {
                                $zip->addFile($abs, 'assets/' . basename($fn));
                                $copiedAssets[] = $fn;
                            }
                        }
                    }
                }
            }

            $zip->addFromString("index{$suffix}.html", $this->renderSeriesIndexHtml(
                $seriesId, false, $urlPrefix, $makeRel, $repoAssetsPath, $lang, $langs
            ));
        }

        $zip->close();
        return $zipName;
    }

    public function rolloutSeries(int $seriesId, string $publicPathAbs): array
    {
        $seriesStmt = $this->pdo->prepare("SELECT * FROM cinemagic_series WHERE id = ?");
        $seriesStmt->execute([$seriesId]);
        $series = $seriesStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$series) return ['success' => false, 'error' => 'Series not found'];

        $targetRepo = getenv('GITPAGES_REPO_PATH') ?: '/data/data/com.termux/files/home/www/gitpages/sg_showcase_01';
        if (!is_dir($targetRepo)) return ['success' => false, 'error' => 'Git repo not found: ' . $targetRepo];

        $seriesSlug     = $this->seriesSlug($seriesId, $series['title']);
        $repoAssetsPath = $this->repoAssetsPath($seriesId, $series['title']);
        $outDir         = rtrim($targetRepo, '/') . '/cinemagic_hub/' . $seriesSlug;
        $urlPrefix      = $series['asset_url_prefix'] ?? '';

        $langsRaw = $series['supported_languages'] ?? 'en';
        $langs    = array_filter(array_map('trim', explode(',', $langsRaw)));
        if (!in_array('en', $langs)) array_unshift($langs, 'en');

        @mkdir($outDir . '/assets', 0777, true);
        @mkdir($outDir . '/data',   0777, true);

        $epStmt = $this->pdo->prepare("
            SELECT ns.id, ns.name, c.name as season_name
            FROM cinemagic_series_2_cinemagics sc
            JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = sc.cinemagic_id
            JOIN narrative_sequences ns ON ns.id = cs.sequence_id
            JOIN cinemagics c ON c.id = sc.cinemagic_id
            WHERE sc.series_id = ?
            ORDER BY sc.sort_order ASC, cs.sort_order ASC
        ");
        $epStmt->execute([$seriesId]);
        $episodes = $epStmt->fetchAll(\PDO::FETCH_ASSOC);

        $copiedAssets = [];

        $cover = $series['cover_image_url'] ?? '';
        if ($cover) {
            $fn = ltrim($cover, '/');
            if (!str_starts_with($fn, 'http')) {
                $abs = rtrim($publicPathAbs, '/') . '/' . $fn;
                if (file_exists($abs) && !in_array($fn, $copiedAssets)) {
                    @copy($abs, $outDir . '/assets/' . basename($fn));
                    $copiedAssets[] = $fn;
                }
            }
        }

        $makeRel = empty($urlPrefix);

        foreach ($langs as $lang) {
            $suffix = $lang === 'en' ? '' : '_' . $lang;
            $epLinkFormat = "ep_%d{$suffix}.html";

            foreach ($episodes as $ep) {
                $seqId = $ep['id'];

                $epDataForHtml = $this->getEpisodeData($seqId, $urlPrefix, $makeRel, $epLinkFormat, $repoAssetsPath, $lang);
                $epDataForHtml['available_langs'] = $langs;
                $epDataForHtml['current_lang']    = $lang;

                file_put_contents($outDir . "/ep_{$seqId}{$suffix}.html", $this->renderEpisodeHtml($epDataForHtml));

                $sidecarJson = json_encode($epDataForHtml, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                file_put_contents($outDir . "/data/ep_{$seqId}{$suffix}.js", "const episodeData = " . $sidecarJson . ";");

                // Rollout corresponding PDF
                $pdfLocalRel = "media/magazines/series_{$seriesId}/magazine_seq{$seqId}_{$lang}.pdf";
                $absPdf = rtrim($publicPathAbs, '/') . '/' . $pdfLocalRel;
                if (file_exists($absPdf) && !in_array($pdfLocalRel, $copiedAssets)) {
                    @copy($absPdf, $outDir . '/assets/' . basename($absPdf));
                    $copiedAssets[] = $pdfLocalRel;
                }

                if ($lang === 'en') {
                    foreach ($epDataForHtml['frames'] as $frame) {
                        $fn = ltrim($frame['original_filename'] ?? '', '/');
                        if ($fn && !str_starts_with($fn, 'http') && !in_array($fn, $copiedAssets)) {
                            $abs = rtrim($publicPathAbs, '/') . '/' . $fn;
                            if (file_exists($abs)) {
                                @copy($abs, $outDir . '/assets/' . basename($fn));
                                $copiedAssets[] = $fn;
                            }
                        }
                    }
                }
            }

            file_put_contents($outDir . "/index{$suffix}.html", $this->renderSeriesIndexHtml(
                $seriesId, false, $urlPrefix, $makeRel, $repoAssetsPath, $lang, $langs
            ));
        }

        $payload = [
            'repo_path'      => $targetRepo,
            'branch_name'    => 'main',
            'remote_name'    => 'origin',
            'commit_message' => 'Magazine Rollout: ' . $series['title'],
            'add_all'        => true,
            'commit'         => true,
            'push'           => true,
            'pull_rebase'    => false,
            'amend'          => false,
            'allow_empty'    => false,
            'dry_run'        => false,
            'force_push'     => false,
            'git_user_name'  => getenv('GIT_BOT_NAME')  ?: 'Post Bot',
            'git_user_email' => getenv('GIT_BOT_EMAIL') ?: 'post-bot@example.invalid',
        ];
        $this->pdo->prepare(
            "INSERT INTO forge_jobs (job_type, label, status, priority, payload, created_at, updated_at)
             VALUES ('github_sync', ?, 'pending', 50, ?, NOW(), NOW())"
        )->execute([
            'Cinemagic Series Rollout: ' . $series['title'],
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return ['success' => true];
    }

    public function getPdfExportData(int $seriesId, int $sequenceId): array
    {
        $stmtSeq = $this->pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
        $stmtSeq->execute([$sequenceId]);
        $seq = $stmtSeq->fetch(\PDO::FETCH_ASSOC);
        if (!$seq) return ['success' => false, 'error' => 'Sequence not found'];

        $stmtSer = $this->pdo->prepare("SELECT * FROM cinemagic_series WHERE id = ?");
        $stmtSer->execute([$seriesId]);
        $series = $stmtSer->fetch(\PDO::FETCH_ASSOC);
        if (!$series) return ['success' => false, 'error' => 'Series not found'];

        $urlPrefix = $series['asset_url_prefix'] ?? '';

        $itemIds = json_decode($seq['sequence_data'] ?? '[]', true) ?: [];
        $pureSketchIds = [];
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
            $stmtS = $this->pdo->prepare("SELECT * FROM sketches WHERE id IN ($inClause)");
            $stmtS->execute($pureSketchIds);
            foreach ($stmtS->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $sketchesData[(int)$row['id']] = $row;
            }
        }

        $selectedFrameMap = [];
        $activeFrameIds = array_values(array_unique(array_filter($selectedFrameIds)));
        if (!empty($activeFrameIds)) {
            $inClauseFrames = implode(',', array_fill(0, count($activeFrameIds), '?'));
            $stmtF = $this->pdo->prepare("SELECT * FROM frames WHERE id IN ($inClauseFrames)");
            $stmtF->execute($activeFrameIds);
            foreach ($stmtF->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $selectedFrameMap[(int)$row['id']] = $row;
            }
        }

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
            $stmtFb = $this->pdo->prepare("
                SELECT f.*, f.entity_id AS _sketch_id 
                FROM frames f 
                INNER JOIN frames_2_sketches m ON m.from_id = f.id 
                WHERE f.entity_id IN ($inClauseFb) 
                ORDER BY f.id DESC
            ");
            $stmtFb->execute($sketchIdsNeedingLatestFrame);
            foreach ($stmtFb->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $sketchId = (int)$row['_sketch_id'];
                if (!isset($latestFrameBySketch[$sketchId])) {
                    $latestFrameBySketch[$sketchId] = $row;
                }
            }
        }

        $overlayTexts = [];
        if (!empty($pureSketchIds)) {
            $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));
            $stmtO = $this->pdo->prepare("
                SELECT sketch_id, text_content, language_code, display_order 
                FROM sketch_overlay_texts 
                WHERE sketch_id IN ($inClause) 
                ORDER BY sketch_id ASC, display_order ASC, id ASC
            ");
            $stmtO->execute($pureSketchIds);
            foreach ($stmtO->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $sid = (int)$row['sketch_id'];
                $lang = $row['language_code'];
                $overlayTexts[$sid][$lang][$row['display_order']] = $row['text_content'];
            }
        }

        $frames = [];
        $coverFrameId = null;

        foreach ($itemIds as $idx => $item) {
            $sid = is_array($item) ? (int)($item['sketch_id'] ?? 0) : (int)$item;
            if ($sid <= 0 || !isset($sketchesData[$sid])) continue;

            $sketchRow = $sketchesData[$sid];
            $activeFrameId = $selectedFrameIds[$idx] ?? null;
            $frameRow = null;

            if ($activeFrameId && isset($selectedFrameMap[$activeFrameId])) {
                $frameRow = $selectedFrameMap[$activeFrameId];
            } elseif (!$activeFrameId && isset($latestFrameBySketch[$sid])) {
                $frameRow = $latestFrameBySketch[$sid];
            }

            if (!$frameRow) continue;

            $frameId = (int)$frameRow['id'];
            if ($coverFrameId === null) $coverFrameId = $frameId;

            $filename = $frameRow['filename'] ?? '';
            $imageUrl = $this->resolveImageUrl($filename, $urlPrefix);

            $frameOverlays = [];
            if (isset($overlayTexts[$sid])) {
                foreach ($overlayTexts[$sid] as $lang => $langLines) {
                    ksort($langLines);
                    $frameOverlays[$lang] = array_values($langLines);
                }
            } else {
                $desc = $sketchRow['description'] ?? $sketchRow['desc'] ?? $sketchRow['prompt'] ?? '';
                if (!empty(trim($desc))) {
                    $frameOverlays['en'] = [trim($desc)];
                }
            }

            $frames[] = [
                'frame_id'      => $frameId,
                'sketch_id'     => $sid,
                'image_url'     => $imageUrl,
                'filename'      => $filename, // Passed up for absolute local path resolution
                'overlay_texts' => $frameOverlays
            ];
        }

        $stmtO = $this->pdo->prepare("SELECT language_code, name_overlay, description_overlay FROM sequence_overlay_texts WHERE sequence_id = ?");
        $stmtO->execute([$sequenceId]);
        
        $locNames = [];
        $locDescs = [];
        foreach ($stmtO->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $l = $row['language_code'];
            if (!empty(trim($row['name_overlay']))) $locNames[$l] = trim($row['name_overlay']);
            if (!empty(trim($row['description_overlay']))) $locDescs[$l] = trim($row['description_overlay']);
        }

        $finalSeqName = $locNames['en'] ?? $seq['name'];
        $finalSeqDesc = $locDescs['en'] ?? $seq['description'];

        return [
            'success'                  => true,
            'sequence_name'            => $finalSeqName,
            'description'              => $finalSeqDesc,
            'localized_sequence_names' => $locNames,
            'localized_descriptions'   => $locDescs,
            'cover_frame_id'           => $coverFrameId,
            'frames'                   => $frames
        ];
    }

    /**
     * Replaces JS client logic: packages local files and JSON into a robust PHP cURL request directly to PyAPI.
     */
    public function submitPdfJobToPyAPI(int $seriesId, int $sequenceId, array $langs, string $pyapiUrl, string $publicPathAbs): array
    {
        $exportData = $this->getPdfExportData($seriesId, $sequenceId);
        if (!$exportData['success']) {
            return $exportData;
        }

        $stmtS = $this->pdo->prepare("SELECT title, asset_url_prefix FROM cinemagic_series WHERE id = ?");
        $stmtS->execute([$seriesId]);
        $series = $stmtS->fetch(\PDO::FETCH_ASSOC);
        if (!$series) {
            return ['success' => false, 'error' => 'Series not found'];
        }

        // Pydantic strictly checks the schema, ensure we strip internal PHP markers like 'filename' and 'image_url'
        $safeFrames = [];
        foreach ($exportData['frames'] as $f) {
            $safeFrames[] = [
                'frame_id'      => $f['frame_id'],
                'sketch_id'     => $f['sketch_id'],
                'overlay_texts' => $f['overlay_texts']
            ];
        }
        
        
       $jobMeta = [
            'series_title'             => $series['title'],
            'sequence_name'            => $exportData['sequence_name'],
            'sequence_id'              => $sequenceId,
            'series_id'                => $seriesId,
            'languages'                => $langs,
            'asset_url_prefix'         => $series['asset_url_prefix'] ?? '',
            'description'              => $exportData['description'] ?? '',
            
            // THE FIX: Force empty arrays to be JSON objects {}
            'localized_sequence_names' => empty($exportData['localized_sequence_names']) ? new \stdClass() : $exportData['localized_sequence_names'],
            'localized_descriptions'   => empty($exportData['localized_descriptions']) ? new \stdClass() : $exportData['localized_descriptions'],
            
            'cover_frame_id'           => $exportData['cover_frame_id'],
            'frames'                   => $safeFrames
        ];
        

        /*
        $jobMeta = [
            'series_title'             => $series['title'],
            'sequence_name'            => $exportData['sequence_name'],
            'sequence_id'              => $sequenceId,
            'series_id'                => $seriesId,
            'languages'                => $langs,
            'asset_url_prefix'         => $series['asset_url_prefix'] ?? '',
            'description'              => $exportData['description'] ?? '',
            'localized_sequence_names' => $exportData['localized_sequence_names'] ?? [],
            'localized_descriptions'   => $exportData['localized_descriptions'] ?? [],
            'cover_frame_id'           => $exportData['cover_frame_id'],
            'frames'                   => $safeFrames
        ];
        */
        
        
        
        // Manually build raw multipart/form-data to enforce the exact field name "images" for Python List[UploadFile]
        $boundary = "----SAGEFormBoundary" . bin2hex(random_bytes(16));
        $body = "";

        // Add Meta JSON
        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Disposition: form-data; name=\"job_meta\"\r\n\r\n";
        $body .= json_encode($jobMeta) . "\r\n";

        $count = 0;
        foreach ($exportData['frames'] as $frame) {
            $fn = ltrim($frame['filename'] ?? '', '/');
            if (!$fn) continue;
            
            $absPath = rtrim($publicPathAbs, '/') . '/' . $fn;
            if (!file_exists($absPath)) {
                error_log("PDF Export: Frame file missing at " . $absPath);
                continue;
            }

            $mime = mime_content_type($absPath) ?: 'image/jpeg';
            $content = file_get_contents($absPath);
            
            $body .= "--" . $boundary . "\r\n";
            $body .= "Content-Disposition: form-data; name=\"images\"; filename=\"image_" . $frame['frame_id'] . ".jpg\"\r\n";
            $body .= "Content-Type: " . $mime . "\r\n\r\n";
            $body .= $content . "\r\n";
            $count++;
        }
        $body .= "--" . $boundary . "--\r\n";

        if ($count === 0 && !empty($exportData['frames'])) {
            return ['success' => false, 'error' => 'Could not locate any frame images on the server filesystem. PDF would be empty.'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, rtrim($pyapiUrl, '/') . '/magazine-pdf/submit');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: multipart/form-data; boundary=" . $boundary,
            "Content-Length: " . strlen($body)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'error' => 'cURL Error connecting to PyAPI: ' . $err];
        }
        if ($httpCode >= 400) {
            return ['success' => false, 'error' => "PyAPI Error HTTP {$httpCode}: " . $response];
        }

        $pyData = json_decode($response, true);
        if (!$pyData || empty($pyData['job_id'])) {
            return ['success' => false, 'error' => 'Invalid response from PyAPI: ' . $response];
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO magazine_pdf_jobs (series_id, sequence_id, languages, status)
             VALUES (?, ?, ?, 'pending')"
        );
        $stmt->execute([$seriesId, $sequenceId, implode(',', $langs)]);
        $jobDbId = (int)$this->pdo->lastInsertId();

        return [
            'success'      => true,
            'job_db_id'    => $jobDbId,
            'pyapi_job_id' => $pyData['job_id'],
            'images_sent'  => $count
        ];
    }

    /**
     * Downloads the finished ZIP from PyAPI, extracts the PDFs to a permanent SAGE folder, and cleans up the PyAPI temp job.
     */
    public function fetchAndStorePdfJob(int $jobDbId, string $pyJobId, string $pyapiUrl, string $publicPathAbs): void
    {
        $stmt = $this->pdo->prepare("SELECT series_id, sequence_id FROM magazine_pdf_jobs WHERE id = ?");
        $stmt->execute([$jobDbId]);
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$job) return;

        $seriesId = $job['series_id'];

        $zipUrl = rtrim($pyapiUrl, '/') . "/magazine-pdf/download/{$pyJobId}";
        
        $ch = curl_init($zipUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $zipData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$zipData || $httpCode >= 400) return;

        $targetDir = rtrim($publicPathAbs, '/') . "/media/magazines/series_{$seriesId}";
        if (!is_dir($targetDir)) @mkdir($targetDir, 0777, true);

        $tempZip = sys_get_temp_dir() . "/mag_tmp_{$pyJobId}.zip";
        file_put_contents($tempZip, $zipData);

        $zip = new \ZipArchive();
        if ($zip->open($tempZip) === true) {
            $zip->extractTo($targetDir);
            $zip->close();
        }
        @unlink($tempZip);

        // Tell PyAPI to cleanup and free disk space
        $cleanupUrl = rtrim($pyapiUrl, '/') . "/magazine-pdf/cleanup/{$pyJobId}";
        $ch = curl_init($cleanupUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}
