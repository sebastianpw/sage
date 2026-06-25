<?php
/**
 * SAGE Cinemagic Hub — Manager
 * src/CinemagicHub/CinemagicHubManager.php
 *
 * Magazine Series Publishing interface for the Cinemagic narrative system.
 * Manages Series -> (optional Seasons ->) Cinemagics (Seasons) -> Narrative Sequences (Episodes).
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

        // Safely add new enhancements
        try {
            $this->pdo->exec("
                ALTER TABLE `cinemagic_series` 
                ADD COLUMN IF NOT EXISTS `landing_page_script` VARCHAR(255) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS `seo_keywords` TEXT DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS `seo_description` TEXT DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS `pdf_full_upright` TINYINT(1) NOT NULL DEFAULT 0,
                ADD COLUMN IF NOT EXISTS `pdf_disable_texts` TINYINT(1) NOT NULL DEFAULT 0,
                ADD COLUMN IF NOT EXISTS `pdf_disable_fuki` TINYINT(1) NOT NULL DEFAULT 0;
            ");
            $this->pdo->exec("
                ALTER TABLE `cinemagics_2_sequences`
                ADD COLUMN IF NOT EXISTS `cover_image_url` VARCHAR(512) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS `seo_keywords` TEXT DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS `seo_description` TEXT DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS `social_links` JSON DEFAULT NULL;
            ");
            // Optional seasons layer
            $this->pdo->exec("
                ALTER TABLE `cinemagic_series`
                ADD COLUMN IF NOT EXISTS `has_seasons` TINYINT(1) NOT NULL DEFAULT 0;
            ");
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `cinemagic_series_seasons` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `series_id` int(11) NOT NULL,
                  `title` varchar(255) NOT NULL DEFAULT 'Season 1',
                  `sort_order` int(11) NOT NULL DEFAULT 0,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `idx_css_series` (`series_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            $this->pdo->exec("
                ALTER TABLE `cinemagic_series_2_cinemagics`
                ADD COLUMN IF NOT EXISTS `season_id` INT(11) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS `cover_image_url` VARCHAR(512) DEFAULT NULL;
            ");
        } catch (\Exception $e) {}
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
        $stmt = $this->pdo->query("SELECT id, title, cover_image_url, asset_url_prefix FROM cinemagic_series WHERE status = 'published' ORDER BY sort_order DESC, id DESC");
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
        $stmt = $this->pdo->query("SELECT id, title, cover_image_url FROM cinemagic_series WHERE status = 'published' ORDER BY sort_order DESC, id DESC");
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
        $stmt = $this->pdo->query("SELECT id, title, status FROM cinemagic_series ORDER BY sort_order DESC, id DESC");
        return ['success' => true, 'series' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    public function getSeriesDetails(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM cinemagic_series WHERE id = ?");
        $stmt->execute([$id]);
        $series = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$series) return ['success' => false, 'error' => 'Series not found'];

        $hasSeasonsLayer = (int)($series['has_seasons'] ?? 0) === 1;

        if ($hasSeasonsLayer) {
            // Return seasons with their attached cinemagics
            $seasonsStmt = $this->pdo->prepare("
                SELECT s.id, s.title, s.sort_order
                FROM cinemagic_series_seasons s
                WHERE s.series_id = ?
                ORDER BY s.sort_order ASC, s.id ASC
            ");
            $seasonsStmt->execute([$id]);
            $seasons = $seasonsStmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($seasons as &$season) {
                $cmStmt = $this->pdo->prepare("
                    SELECT c.id, c.name, sc.sort_order
                    FROM cinemagic_series_2_cinemagics sc
                    JOIN cinemagics c ON c.id = sc.cinemagic_id
                    WHERE sc.series_id = ? AND sc.season_id = ?
                    ORDER BY sc.sort_order ASC
                ");
                $cmStmt->execute([$id, $season['id']]);
                $season['cinemagics'] = $cmStmt->fetchAll(\PDO::FETCH_ASSOC);
            }
            unset($season);

            // Also fetch cinemagics not yet assigned to any season in this series.
            $unSeasonedStmt = $this->pdo->prepare("
                SELECT c.id, c.name
                FROM cinemagics c
                WHERE c.id NOT IN (
                    SELECT cinemagic_id 
                    FROM cinemagic_series_2_cinemagics 
                    WHERE series_id = ? AND season_id IS NOT NULL
                )
                ORDER BY c.name ASC
            ");
            $unSeasonedStmt->execute([$id]);
            $unSeasonedCinemagics = $unSeasonedStmt->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'success'              => true,
                'series'               => $series,
                'seasons'              => [],         // legacy key
                'series_seasons'       => $seasons,
                'unseasoned_cinemagics'=> $unSeasonedCinemagics,
            ];
        }

        // Original flat behaviour
        $sStmt = $this->pdo->prepare("
            SELECT c.id, c.name, sc.sort_order
            FROM cinemagic_series_2_cinemagics sc
            JOIN cinemagics c ON c.id = sc.cinemagic_id
            WHERE sc.series_id = ?
            ORDER BY sc.sort_order ASC
        ");
        $sStmt->execute([$id]);
        $seasons = $sStmt->fetchAll(\PDO::FETCH_ASSOC);

        return ['success' => true, 'series' => $series, 'seasons' => $seasons, 'series_seasons' => [], 'unseasoned_cinemagics' => []];
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
        $sort   = (int)($data['sort_order'] ?? 0);
        $prefix = $data['asset_url_prefix'] ?? '';
        $cover  = $data['cover_image_url'] ?? null;
        $tmpl   = $data['template'] ?? 'default';
        $langs  = $data['supported_languages'] ?? 'en';
        $hasSeasonsLayer = isset($data['has_seasons']) ? (int)$data['has_seasons'] : 0;
        $pdfFullUpright  = isset($data['pdf_full_upright']) ? (int)$data['pdf_full_upright'] : 0;
        $pdfDisableTexts = isset($data['pdf_disable_texts']) ? (int)$data['pdf_disable_texts'] : 0;
        $pdfDisableFuki  = isset($data['pdf_disable_fuki']) ? (int)$data['pdf_disable_fuki'] : 0;
        
        $script   = !empty($data['landing_page_script']) ? trim($data['landing_page_script']) : null;
        $seoKw    = $data['seo_keywords'] ?? null;
        $seoDesc  = $data['seo_description'] ?? null;

        if ($id) {
            $stmt = $this->pdo->prepare("UPDATE cinemagic_series SET title=?, description=?, status=?, sort_order=?, asset_url_prefix=?, cover_image_url=?, template=?, supported_languages=?, landing_page_script=?, seo_keywords=?, seo_description=?, has_seasons=?, pdf_full_upright=?, pdf_disable_texts=?, pdf_disable_fuki=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$title, $desc, $status, $sort, $prefix, $cover, $tmpl, $langs, $script, $seoKw, $seoDesc, $hasSeasonsLayer, $pdfFullUpright, $pdfDisableTexts, $pdfDisableFuki, $id]);
            return ['success' => true, 'id' => $id];
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO cinemagic_series (title, description, status, sort_order, asset_url_prefix, cover_image_url, template, supported_languages, landing_page_script, seo_keywords, seo_description, has_seasons, pdf_full_upright, pdf_disable_texts, pdf_disable_fuki, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$title, $desc, $status, $sort, $prefix, $cover, $tmpl, $langs, $script, $seoKw, $seoDesc, $hasSeasonsLayer, $pdfFullUpright, $pdfDisableTexts, $pdfDisableFuki]);
            return ['success' => true, 'id' => (int)$this->pdo->lastInsertId()];
        }
    }

    public function deleteSeries(int $id): array
    {
        $stmt = $this->pdo->prepare("DELETE FROM cinemagic_series WHERE id = ?");
        $stmt->execute([$id]);
        $stmt2 = $this->pdo->prepare("DELETE FROM cinemagic_series_seasons WHERE series_id = ?");
        $stmt2->execute([$id]);
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

    // ── Series Seasons (optional grouping layer) ──────────────────────────────────

    public function getSeriesSeasons(int $seriesId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, title, sort_order FROM cinemagic_series_seasons
            WHERE series_id = ? ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute([$seriesId]);
        return ['success' => true, 'seasons' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    public function saveSeriesSeason(int $seriesId, array $data): array
    {
        $id    = !empty($data['id']) ? (int)$data['id'] : null;
        $title = trim($data['title'] ?? 'Season');
        $sort  = (int)($data['sort_order'] ?? 0);

        if (!$title) return ['success' => false, 'error' => 'Title required'];

        if ($id) {
            $stmt = $this->pdo->prepare("UPDATE cinemagic_series_seasons SET title=?, sort_order=? WHERE id=? AND series_id=?");
            $stmt->execute([$title, $sort, $id, $seriesId]);
            return ['success' => true, 'id' => $id];
        } else {
            $maxStmt = $this->pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM cinemagic_series_seasons WHERE series_id=?");
            $maxStmt->execute([$seriesId]);
            $sort = (int)$maxStmt->fetchColumn();
            $stmt = $this->pdo->prepare("INSERT INTO cinemagic_series_seasons (series_id, title, sort_order) VALUES (?,?,?)");
            $stmt->execute([$seriesId, $title, $sort]);
            return ['success' => true, 'id' => (int)$this->pdo->lastInsertId()];
        }
    }

    public function deleteSeriesSeason(int $seriesId, int $seasonId): array
    {
        $stmt = $this->pdo->prepare("UPDATE cinemagic_series_2_cinemagics SET season_id = NULL WHERE series_id = ? AND season_id = ?");
        $stmt->execute([$seriesId, $seasonId]);

        $stmt2 = $this->pdo->prepare("DELETE FROM cinemagic_series_seasons WHERE id = ? AND series_id = ?");
        $stmt2->execute([$seasonId, $seriesId]);
        return ['success' => true];
    }

    public function assignCinemagicToSeriesSeason(int $seriesId, int $cinemagicId, int $seasonId): array
    {
        $checkStmt = $this->pdo->prepare("SELECT 1 FROM cinemagic_series_2_cinemagics WHERE series_id=? AND cinemagic_id=?");
        $checkStmt->execute([$seriesId, $cinemagicId]);
        if (!$checkStmt->fetchColumn()) {
            $maxStmt = $this->pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM cinemagic_series_2_cinemagics WHERE series_id=?");
            $maxStmt->execute([$seriesId]);
            $order = (int)$maxStmt->fetchColumn();
            $iStmt = $this->pdo->prepare("INSERT IGNORE INTO cinemagic_series_2_cinemagics (series_id, cinemagic_id, sort_order, season_id) VALUES (?,?,?,?)");
            $iStmt->execute([$seriesId, $cinemagicId, $order, $seasonId]);
        } else {
            $uStmt = $this->pdo->prepare("UPDATE cinemagic_series_2_cinemagics SET season_id=? WHERE series_id=? AND cinemagic_id=?");
            $uStmt->execute([$seasonId, $seriesId, $cinemagicId]);
        }
        return ['success' => true];
    }

    public function removeCinemagicFromSeriesSeason(int $seriesId, int $cinemagicId): array
    {
        $stmt = $this->pdo->prepare("DELETE FROM cinemagic_series_2_cinemagics WHERE series_id=? AND cinemagic_id=?");
        $stmt->execute([$seriesId, $cinemagicId]);
        return ['success' => true];
    }

    public function saveCinemagicCover(int $seriesId, int $cinemagicId, string $url): array
    {
        $stmt = $this->pdo->prepare("UPDATE cinemagic_series_2_cinemagics SET cover_image_url = ? WHERE series_id = ? AND cinemagic_id = ?");
        $stmt->execute([$url, $seriesId, $cinemagicId]);
        return ['success' => true];
    }

    // ── Episode queries ───────────────────────────────────────────────────────────

    public function getCinemagicIdForSequenceInSeries(int $seriesId, int $sequenceId): ?int 
    {
        $stmt = $this->pdo->prepare("
            SELECT cs.cinemagic_id 
            FROM cinemagics_2_sequences cs
            JOIN cinemagic_series_2_cinemagics sc ON sc.cinemagic_id = cs.cinemagic_id
            WHERE sc.series_id = ? AND cs.sequence_id = ? LIMIT 1
        ");
        $stmt->execute([$seriesId, $sequenceId]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    public function getEpisodeMeta(int $cinemagicId, int $sequenceId): array 
    {
        $stmt = $this->pdo->prepare("SELECT cover_image_url, seo_keywords, seo_description, social_links FROM cinemagics_2_sequences WHERE cinemagic_id = ? AND sequence_id = ?");
        $stmt->execute([$cinemagicId, $sequenceId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public function saveEpisodeMeta(int $cinemagicId, int $sequenceId, array $data): array 
    {
        $stmt = $this->pdo->prepare("
            UPDATE cinemagics_2_sequences 
            SET cover_image_url = ?, seo_keywords = ?, seo_description = ?, social_links = ?
            WHERE cinemagic_id = ? AND sequence_id = ?
        ");
        $stmt->execute([
            $data['cover_image_url'] ?? null,
            $data['seo_keywords'] ?? null,
            $data['seo_description'] ?? null,
            $data['social_links'] ?? null,
            $cinemagicId,
            $sequenceId
        ]);
        return ['success' => true];
    }
    
    // ── Local PDFs ───────────────────────────────────────────────────────────────

    public function getCinemagicPdfsForSeries(int $seriesId): array
    {
        $publicPathAbs = \App\Core\SpwBase::getInstance()->getPublicPath();
        $pdfDir = rtrim($publicPathAbs, '/') . "/media/magazines/series_{$seriesId}";
        if (!is_dir($pdfDir)) {
            return ['success' => true, 'pdfs' => []];
        }

        $pdfs = [];
        foreach (glob($pdfDir . '/*.pdf') ?: [] as $absPath) {
            $filename = basename($absPath);
            if (preg_match('/^magazine_seq(\d+)_([a-zA-Z0-9_-]+)\.pdf$/', $filename, $matches)) {
                $seqId = (int)$matches[1];
                $lang  = $matches[2];

                $stmt = $this->pdo->prepare("SELECT name FROM narrative_sequences WHERE id = ?");
                $stmt->execute([$seqId]);
                $seqName = $stmt->fetchColumn() ?: "Seq #{$seqId}";

                $pdfs[] = [
                    'filename'      => $filename,
                    'rel_path'      => "media/magazines/series_{$seriesId}/{$filename}",
                    'size'          => filesize($absPath),
                    'sequence_name' => $seqName,
                    'lang'          => $lang,
                    'mtime'         => filemtime($absPath),
                    'created_at'    => date('Y-m-d H:i:s', filemtime($absPath)),
                ];
            }
        }
        
        usort($pdfs, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

        return ['success' => true, 'pdfs' => $pdfs];
    }
    
   // ── Sitemaps (Local & Imported) ───────────────────────────────────────────────

    public function getSitemapImports(): array
    {
        $stmt = $this->pdo->query("SELECT id, system_name, created_at FROM sitemap_imports ORDER BY id DESC");
        return ['success' => true, 'imports' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    public function importSitemapJson(string $name, array $urls): array
    {
        if (empty($name)) return ['success' => false, 'error' => 'System name required'];
        $stmt = $this->pdo->prepare("INSERT INTO sitemap_imports (system_name, urls_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE urls_json = ?, created_at = NOW()");
        $json = json_encode($urls, JSON_UNESCAPED_SLASHES);
        $stmt->execute([$name, $json, $json]);
        return ['success' => true];
    }

    public function deleteSitemapImport(int $id): array
    {
        $stmt = $this->pdo->prepare("DELETE FROM sitemap_imports WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true];
    }

    public function generateLocalSitemapUrls(string $baseUrl): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $urls = [];
        
        // 1. Content Hub Index
        $urls[] = $baseUrl . '/index.html';

        // 2. Published Series & Episodes
        $seriesList = $this->pdo->query("SELECT * FROM cinemagic_series WHERE status = 'published'")->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($seriesList as $series) {
            $script = $this->getSeriesLandingScriptName($series);
            $langsRaw = $series['supported_languages'] ?? 'en';
            $langs = array_filter(array_map('trim', explode(',', $langsRaw)));
            if (!in_array('en', $langs)) array_unshift($langs, 'en');

            $hasSeasonsLayer = (int)($series['has_seasons'] ?? 0) === 1;
            $seasonFilter = $hasSeasonsLayer ? " AND sc.season_id IS NOT NULL " : "";

            $epStmt = $this->pdo->prepare("
                SELECT ns.id FROM cinemagic_series_2_cinemagics sc
                JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = sc.cinemagic_id
                JOIN narrative_sequences ns ON ns.id = cs.sequence_id
                WHERE sc.series_id = ? {$seasonFilter}
            ");
            $epStmt->execute([$series['id']]);
            $epIds = $epStmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($langs as $lang) {
                $suffix = $lang === 'en' ? '' : '_' . $lang;
                $urls[] = $baseUrl . '/' . $script . $suffix . '.html';
                
                foreach ($epIds as $epId) {
                    $urls[] = $baseUrl . '/ep_' . $epId . $suffix . '.html';
                }
            }
        }
        return array_unique($urls);
    }

    public function buildGlobalSitemapXml(string $baseUrl): string
    {
        $urls = $this->generateLocalSitemapUrls($baseUrl);
        
        $stmt = $this->pdo->query("SELECT urls_json FROM sitemap_imports");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $imported = json_decode($row['urls_json'], true) ?: [];
            $urls = array_merge($urls, $imported);
        }
        
        $urls = array_unique($urls);
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            $xml .= "  <url>\n    <loc>" . htmlspecialchars($url) . "</loc>\n  </url>\n";
        }
        $xml .= '</urlset>';
        return $xml;
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

    // ── Slug / Repo Path / Script Helpers ─────────────────────────────────────────

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

    public function getSeriesLandingScriptName(array $series): string 
    {
        $script = trim($series['landing_page_script'] ?? '');
        if ($script !== '') return $script;

        $stmt = $this->pdo->prepare("
            SELECT cs.sequence_id
            FROM cinemagic_series_2_cinemagics sc
            JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = sc.cinemagic_id
            WHERE sc.series_id = ?
            ORDER BY sc.sort_order ASC, cs.sort_order ASC
            LIMIT 1
        ");
        $stmt->execute([$series['id']]);
        $firstSeq = $stmt->fetchColumn();

        return $firstSeq ? 'index_' . $firstSeq : 'index_' . $series['id'];
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

        // Determine series
        $stmtSer = $this->pdo->prepare("
            SELECT s.* 
            FROM cinemagic_series s
            JOIN cinemagic_series_2_cinemagics sc ON sc.series_id = s.id
            JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = sc.cinemagic_id
            WHERE cs.sequence_id = ? LIMIT 1
        ");
        $stmtSer->execute([$seqId]);
        $series = $stmtSer->fetch(\PDO::FETCH_ASSOC);
        $seriesId = $series ? (int)$series['id'] : null;

        // Fetch Episode Specific Meta 
        $epMeta = [];
        $cinemagicInfo = null;
        if ($seriesId) {
            $cId = $this->getCinemagicIdForSequenceInSeries($seriesId, $seqId);
            if ($cId) {
                $epMeta = $this->getEpisodeMeta($cId, $seqId);
                $stmtCM = $this->pdo->prepare("SELECT id, name FROM cinemagics WHERE id = ?");
                $stmtCM->execute([$cId]);
                $cinemagicInfo = $stmtCM->fetch(\PDO::FETCH_ASSOC);
            }
        }

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
        
        // Fuki Texts - Load multi-lingual absolute positioning overlay texts
        $fukiTexts = [];
        if (!empty($pureSketchIds)) {
            $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));
            try {
                $stmtFuki = $this->pdo->prepare("SELECT * FROM fuki_texts WHERE sketch_id IN ($inClause) AND language_code IN ('en', ?) ORDER BY id ASC");
                $stmtFuki->execute([...$pureSketchIds, $langCode]);
                $fukiRaw = $stmtFuki->fetchAll(\PDO::FETCH_ASSOC);
                
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
            } catch (\Exception $e) {}
        }

        $episodesNav = [];
        if ($cinemagicInfo) {
            $stmtEp = $this->pdo->prepare("
                SELECT ns.id, ns.name, cs.sort_order, cs.chapter_label,
                       so_en.name_overlay AS en_name, so_lang.name_overlay AS lang_name,
                       cs.cover_image_url, ns.sequence_data
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
                    'cover_raw'     => $ep['cover_image_url'] ?? '',
                    'seq_data_raw'  => $ep['sequence_data'] ?? '',
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
                'overlay_texts'     => array_map('htmlspecialchars', $overrideTexts),
                'fuki_texts'        => $fukiTexts[$sid] ?? []
            ];
        }

        // Determine First Image for Episode Cover Fallback
        $explicitCoverUrl = $epMeta['cover_image_url'] ?? '';
        $episodeCoverUrl  = $explicitCoverUrl ? $this->resolveImageUrl($explicitCoverUrl, $urlPrefix, $makeLocalRelative, $repoAssetPath) : (!empty($frames) ? $frames[0]['thumb'] : '');

        // Determine Next Episode or Random Series
        $currentIdx = 0;
        foreach ($episodesNav as $i => $ep) {
            if ((int)$ep['id'] === (int)$seqId) {
                $currentIdx = $i;
                break;
            }
        }
        $nextEp = ($currentIdx < count($episodesNav) - 1) ? $episodesNav[$currentIdx + 1] : null;
        $nextTeaser = null;

        if ($nextEp) {
            $nxtCovRaw = $nextEp['cover_raw'];
            if (!$nxtCovRaw && $nextEp['seq_data_raw']) {
                $nxtFrames = $this->getSequenceFrames((int)$nextEp['id']);
                if (!empty($nxtFrames)) $nxtCovRaw = $nxtFrames[0]['url'];
            }
            $nxtCovUrl = $nxtCovRaw ? $this->resolveImageUrl($nxtCovRaw, $urlPrefix, $makeLocalRelative, $repoAssetPath) : '';
            
            $nextTeaser = [
                'type'  => 'next_episode',
                'title' => $nextEp['chapter_label'] ?: $nextEp['name'],
                'cover' => $nxtCovUrl,
                'url'   => $nextEp['url']
            ];
            
        } elseif ($series) {
            // Fetch ALL other published series for client-side randomization
            $randStmt = $this->pdo->prepare("SELECT * FROM cinemagic_series WHERE status = 'published' AND id != ?");
            $randStmt->execute([$seriesId]);
            $otherSeriesList = $randStmt->fetchAll(\PDO::FETCH_ASSOC);

            $randomTeasers = [];
            foreach ($otherSeriesList as $rs) {
                $rsCover  = $rs['cover_image_url'] ?? '';
                $rsPrefix = $rs['asset_url_prefix'] ?? '';
                $rsSlug   = $this->seriesSlug((int)$rs['id'], $rs['title']);
                $rsScript = $this->getSeriesLandingScriptName($rs);
                $suffix   = $langCode === 'en' ? '' : '_' . $langCode;
                
                $isPreview = strpos($linkFormat, 'api.php') !== false;

                if ($isPreview) {
                    $rsUrl = "api.php?action=preview_series&id={$rs['id']}&lang={$langCode}";
                    $rsCovUrl = $rsCover ? (str_starts_with($rsCover, '/') ? $rsCover : '/' . $rsCover) : '';
                } else {
                    $rsUrl = "../{$rsScript}{$suffix}.html";
                    
                    if ($rsCover) {
                        if ($rsPrefix !== '') {
                            $rsCovUrl = rtrim($rsPrefix, '/') . "/cinemagic_hub/{$rsSlug}/assets/" . basename($rsCover);
                        } elseif ($makeLocalRelative) {
                            $rsCovUrl = "../{$rsSlug}/assets/" . basename($rsCover);
                        } else {
                            $rsCovUrl = str_starts_with($rsCover, '/') ? $rsCover : '/' . $rsCover;
                        }
                    } else {
                        $rsCovUrl = '';
                    }
                }

                $randomTeasers[] = [
                    'title' => $rs['title'],
                    'cover' => $rsCovUrl,
                    'url'   => $rsUrl
                ];
            }

            $nextTeaser = ['type' => 'random_series', 'options' => $randomTeasers];
        }

        return [
            'id'           => $seqId,
            'name'         => $finalSeqName,
            'description'  => $finalSeqDesc,
            'cinemagic'    => $cinemagicInfo,
            'episodes_nav' => $episodesNav,
            'pdf_url'      => $pdfUrl,
            'frames'       => $frames,
            'meta_kw'      => $epMeta['seo_keywords'] ?? ($series['seo_keywords'] ?? ''),
            'meta_desc'    => $epMeta['seo_description'] ?? ($series['seo_description'] ?? ''),
            'social_links' => json_decode($epMeta['social_links'] ?? '[]', true) ?: [],
            'episode_cover'=> $episodeCoverUrl,
            'episode_cover_raw' => $explicitCoverUrl,
            'next_teaser'  => $nextTeaser,
            'is_preview'   => strpos($linkFormat, 'api.php') !== false
        ];
    }


    public function renderEpisodeHtml(array $epData): string
    {
        $title       = htmlspecialchars($epData['name']);
        $cmName      = $epData['cinemagic'] ? htmlspecialchars($epData['cinemagic']['name']) : '';
        $titleSuffix = $cmName ? ' — ' . $cmName : '';
        $desc        = !empty($epData['description']) ? nl2br(htmlspecialchars($epData['description'])) : '';
        $showNav     = count($epData['episodes_nav']) > 1;

        $metaKw   = htmlspecialchars($epData['meta_kw'] ?? '');
        $metaDesc = htmlspecialchars($epData['meta_desc'] ?? '');

        $currentIdx = 0;
        foreach ($epData['episodes_nav'] as $i => $ep) {
            if ((int)$ep['id'] === (int)$epData['id']) {
                $currentIdx = $i;
                break;
            }
        }
        $prevEp = ($currentIdx > 0) ? $epData['episodes_nav'][$currentIdx - 1] : null;
        $nextEp = ($currentIdx < count($epData['episodes_nav']) - 1) ? $epData['episodes_nav'][$currentIdx + 1] : null;

        // Episode Cover (Fallback to first image already resolved in array)
        $epCoverHtml = '';
        if (!empty($epData['episode_cover'])) {
            $safeCoverUrl = htmlspecialchars($epData['episode_cover']);
            $epCoverHtml = '<div class="ep-cover-wrapper observe-me visible">' . "\n" .
                           '    <img src="' . $safeCoverUrl . '" class="ep-cover-img" alt="Cover">' . "\n" .
                           '</div>' . "\n" .
                           '<div class="ep-scroll-indicator observe-me visible">' . "\n" .
                           '    <svg viewBox="0 0 24 24"><line x1="12" y1="4" x2="12" y2="20"/><polyline points="17 15 12 20 7 15"/></svg>' . "\n" .
                           '</div>';
        }
        
        $panelsHtml = '';
        foreach ($epData['frames'] as $index => $f) {
            $imgHtml = '';
            if (!empty($f['thumb'])) {
                $fukiJson = htmlspecialchars(json_encode($f['fuki_texts'] ?? []), ENT_QUOTES, 'UTF-8');
                $imgHtml = '<div class="panel-img-wrapper" style="position:relative; width:100%; display:flex; justify-content:center;">' .
                           '<div style="position:relative; width:100%; max-width:600px;">' .
                           '<img src="' . htmlspecialchars($f['thumb']) . '" class="panel-img observe-me" loading="lazy" alt="Frame ' . ($index + 1) . '" data-fuki="' . $fukiJson . '" onload="if(typeof renderFuki === \'function\') renderFuki(this);">' .
                           '<div class="fuki-layer" style="position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none;"></div>' .
                           '</div></div>';
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
        $langCode = $epData['current_lang'] ?? 'en';
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

        // Share Icon functionality
        $shareTexts = [
            'en' => 'Check out this episode: ',
            'de' => 'Sieh dir diese Episode an: ',
            'pt' => 'Confira este episódio: '
        ];
        $shareTitle = $shareTexts[$langCode] ?? $shareTexts['en'];
        $shareHtml = <<<HTML
<button class="lang-toggle" onclick="shareEpisode(this)" data-title="{$shareTitle}{$title}">
    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
    <span class="lang-label">SHARE</span>
</button>
<script>
function shareEpisode(btn) {
    if (navigator.share) {
        navigator.share({ title: btn.dataset.title, url: window.location.href }).catch(console.error);
    } else {
        navigator.clipboard.writeText(window.location.href);
        alert("Link copied!");
    }
}
</script>
HTML;

        // Add the Newsletter Button
        $newsletterHtml = <<<HTML
<a href="https://petersebring.com/newsletter.php" class="lang-toggle" style="text-decoration:none;" target="_blank" title="Subscribe to Newsletter">
    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
    <span class="lang-label">NEWS</span>
</a>
HTML;

        $topRightHtml = '<div class="top-actions">' . $newsletterHtml . $shareHtml . $pdfBtnHtml . $langHtml . '</div>';
  
        // End Info Box (Next Teaser + Social Links)
        $endTeaserHtml = '';
        if (!empty($epData['next_teaser'])) {
            $nt = $epData['next_teaser'];
            if ($nt['type'] === 'next_episode') {
                $preText = 'Continue Reading:';
                $endTeaserHtml .= <<<HTML
<a href="{$nt['url']}" class="end-teaser-link">
    <img src="{$nt['cover']}" class="end-teaser-cover" alt="Cover">
    <div class="end-teaser-text">
        <div class="end-teaser-pre">{$preText}</div>
        <div class="end-teaser-title">{$nt['title']}</div>
    </div>
</a>
HTML;
            } elseif ($nt['type'] === 'random_series' && !empty($nt['options'])) {
                $optionsJson = json_encode($nt['options'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
                $endTeaserHtml .= <<<HTML
<a href="#" class="end-teaser-link" id="random-teaser-link" style="display:none;">
    <img src="" class="end-teaser-cover" id="random-teaser-cover" alt="Cover">
    <div class="end-teaser-text">
        <div class="end-teaser-pre">Also discover:</div>
        <div class="end-teaser-title" id="random-teaser-title"></div>
    </div>
</a>
<script>
(function(){
    var options = {$optionsJson};
    if(options && options.length > 0) {
        var pick = options[Math.floor(Math.random() * options.length)];
        var link = document.getElementById('random-teaser-link');
        var cover = document.getElementById('random-teaser-cover');
        var title = document.getElementById('random-teaser-title');
        if(link && pick) {
            link.href = pick.url;
            if (pick.cover) { cover.src = pick.cover; } else { cover.style.display = 'none'; }
            title.textContent = pick.title;
            link.style.display = 'flex';
        }
    }
})();
</script>
HTML;
            }
        }


        $socialLinksHtml = '';
        if (!empty($epData['social_links'])) {
            $icons = [
                'instagram' => '<rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>',
                'youtube'   => '<path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33 2.78 2.78 0 0 0 1.94 2c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.33 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"/>',
                'facebook'  => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
                'twitter'   => '<path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"/>',
                'newsletter'=> '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>'
            ];
            foreach ($epData['social_links'] as $sl) {
                $svg = $icons[$sl['type']] ?? $icons['newsletter'];
                $socialLinksHtml .= '<a href="'.htmlspecialchars($sl['url']).'" class="soc-icon" target="_blank"><svg viewBox="0 0 24 24">'.$svg.'</svg></a>';
            }
        }

        $endBoxHtml = '';
        if ($endTeaserHtml || $socialLinksHtml) {
            $socWrap = $socialLinksHtml ? '<div class="soc-wrap">'.$socialLinksHtml.'</div>' : '';
            $endBoxHtml = '<div class="ep-end-box observe-me">' . $endTeaserHtml . $socWrap . '</div>';
        }

        $descHtml  = $desc ? '<p class="seq-desc">' . $desc . '</p>' : '';
        $bodyClass = $showNav ? ' class="has-ep-nav"' : '';

        return <<<HTML
<!doctype html>
<html lang="{$langCode}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=0" />
    <title>{$title}{$titleSuffix}</title>
    <meta name="keywords" content="{$metaKw}">
    <meta name="description" content="{$metaDesc}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Permanent+Marker&family=Oswald:wght@600;700&family=Cinzel:wght@400;600&family=Space+Mono:wght@400;700&family=Lora:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
    <style>
        :root { --bg-color: #020202; --text-color: #e5e0d8; --accent-color: #cda434; --font-title: 'Cinzel', serif; --font-body: 'Lora', serif; --nav-bg: rgba(8,6,4,0.82); --nav-border: rgba(205,164,52,0.18); --nav-text: #c8b88a; --nav-active-bg: rgba(205,164,52,0.14); --nav-active: #cda434; }
        body, html { margin: 0; padding: 0; background-color: var(--bg-color); background-image: radial-gradient(circle at 50% 0%, #15151a 0%, #020202 60%); background-attachment: fixed; color: var(--text-color); font-family: var(--font-body); overscroll-behavior: none; -webkit-font-smoothing: antialiased; }
        .story-container { max-width: 800px; margin: 0 auto; padding: 0; display: flex; flex-direction: column; align-items: center; }
        .seq-header { text-align: center; padding: 100px 20px 80px; width: 100%; box-sizing: border-box; }
        .seq-title { font-family: var(--font-title); font-size: 2.5rem; font-weight: 400; margin: 0 0 20px 0; color: var(--accent-color); line-height: 1.2; letter-spacing: 2px; }
        .seq-desc { font-size: 1.1rem; opacity: 0.7; line-height: 1.6; max-width: 600px; margin: 0 auto; }
        .ep-cover-wrapper { width: 100%; display: flex; justify-content: center; margin-bottom: 60px; padding: 0 20px; box-sizing: border-box; }
        .ep-cover-img { width: 100%; max-width: 600px; height: auto; border-radius: 4px; box-shadow: 0 10px 40px rgba(0,0,0,0.8); border: 1px solid rgba(255,255,255,0.05); }
        .ep-scroll-indicator { width: 100%; display: flex; justify-content: center; margin-bottom: 120px; color: var(--accent-color); opacity: 0.5; }
        .ep-scroll-indicator svg { width: 24px; height: 24px; fill: none; stroke: currentColor; stroke-width: 1.5; animation: floatDown 2.5s infinite ease-in-out; }
        @keyframes floatDown { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(10px); } }
        .panel { width: 100%; display: flex; flex-direction: column; align-items: center; margin-bottom: 80px; }
        .panel-img { width: 100%; height: auto; display: block; margin: 0; box-shadow: 0 4px 40px rgba(0,0,0,0.8); border-radius: 2px; }
        .text-blocks { width: 100%; padding: 50px 20px 30px; box-sizing: border-box; display: flex; flex-direction: column; gap: 35px; align-items: center; }
        .story-text { font-size: 1.25rem; line-height: 1.8; font-weight: 400; text-align: center; max-width: 650px; color: var(--text-color); letter-spacing: 0.5px; text-shadow: 0 2px 6px rgba(0,0,0,0.9); }
        .observe-me { opacity: 0; transform: translateY(25px); transition: opacity 1s cubic-bezier(0.25, 1, 0.5, 1), transform 1s cubic-bezier(0.25, 1, 0.5, 1); }
        .observe-me.visible { opacity: 1; transform: translateY(0); }
        .end-mark { margin: 60px 0 30px; font-family: var(--font-title); color: var(--accent-color); font-size: 1.5rem; letter-spacing: 4px; opacity: 0.4; text-align: center; }
        .top-actions { position: fixed; top: 20px; right: 20px; z-index: 999; display: flex; gap: 8px; align-items: flex-start; }
        .lang-picker { position: relative; }
        .lang-toggle { background: var(--nav-bg); border: 1px solid var(--nav-border); border-radius: 20px; padding: 6px 12px; color: var(--nav-text); font-family: var(--font-title); font-size: 0.6rem; cursor: pointer; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); display: flex; align-items: center; gap: 6px; transition: color 0.2s; }
        .lang-toggle:hover { color: var(--nav-active); }
        .lang-menu { position: absolute; top: 100%; right: 0; margin-top: 8px; background: var(--nav-bg); border: 1px solid var(--nav-border); border-radius: 8px; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); display: none; flex-direction: column; overflow: hidden; min-width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
        .lang-picker.open .lang-menu { display: flex; }
        .lang-menu a { padding: 10px 16px; color: var(--nav-text); text-decoration: none; font-family: var(--font-title); font-size: 0.65rem; text-align: center; transition: background 0.2s, color 0.2s; }
        .lang-menu a:hover { background: var(--nav-active-bg); color: var(--nav-active); }
        .ep-end-box { width: calc(100% - 40px); max-width: 500px; margin: 0 auto 120px; padding: 20px; background: rgba(10,10,14,0.6); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; backdrop-filter: blur(10px); box-sizing: border-box; }
        .end-teaser-link { display: flex; gap: 20px; align-items: center; text-decoration: none; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); transition: transform 0.2s; }
        .end-teaser-link:hover { transform: translateX(4px); }
        .end-teaser-cover { width: 80px; aspect-ratio: 3/4; object-fit: cover; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.5); }
        .end-teaser-pre { font-family: var(--font-title); font-size: 0.6rem; letter-spacing: 2px; color: var(--accent-color); opacity: 0.7; text-transform: uppercase; margin-bottom: 6px; }
        .end-teaser-title { font-family: var(--font-body); font-size: 1.1rem; color: var(--text-color); line-height: 1.4; }
        .soc-wrap { display: flex; justify-content: center; gap: 16px; padding-top: 20px; }
        .soc-icon { width: 36px; height: 36px; border-radius: 50%; background: var(--nav-bg); border: 1px solid var(--nav-border); display: flex; align-items: center; justify-content: center; color: var(--nav-text); transition: 0.2s; }
        .soc-icon:hover { color: var(--accent-color); border-color: var(--accent-color); background: var(--nav-active-bg); transform: translateY(-2px); }
        .soc-icon svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.5; }
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
    {$epCoverHtml}
    {$panelsHtml}
    <div class="end-mark observe-me">&#10086; FIN &#10086;</div>
    {$endBoxHtml}
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
            div.style.transform = `rotate(\${ft.rotation}deg)`;
            div.style.transformOrigin = 'top left';
            div.style.color = ft.fill_color;
            div.style.textAlign = ft.text_align;
            div.style.fontFamily = `"\${ft.font_family}", sans-serif`;
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
HTML;

        // Append Google Analytics if not in preview mode
        if (empty($epData['is_preview'])) {
            $html .= <<<HTML

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-SBSTRVS0NR"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-SBSTRVS0NR');
</script>

HTML;
        }

        $html .= "</body>\n</html>";
        return $html;
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

        $hasSeasons = (int)($series['has_seasons'] ?? 0) === 1;
        $seasonFilter = $hasSeasons ? " AND sc.season_id IS NOT NULL " : "";

        $epStmt = $this->pdo->prepare("
            SELECT ns.id, ns.name, c.name as season_name,
                   so_en.name_overlay AS en_name, so_lang.name_overlay AS lang_name,
                   cs.cover_image_url, ns.sequence_data, cs.chapter_label,
                   css.title AS super_season_title,
                   sc.cover_image_url AS cm_cover_image_url
            FROM cinemagic_series_2_cinemagics sc
            LEFT JOIN cinemagic_series_seasons css ON css.id = sc.season_id
            JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = sc.cinemagic_id
            JOIN narrative_sequences ns ON ns.id = cs.sequence_id
            JOIN cinemagics c ON c.id = sc.cinemagic_id
            LEFT JOIN sequence_overlay_texts so_en ON so_en.sequence_id = ns.id AND so_en.language_code = 'en'
            LEFT JOIN sequence_overlay_texts so_lang ON so_lang.sequence_id = ns.id AND so_lang.language_code = ?
            WHERE sc.series_id = ? {$seasonFilter}
            ORDER BY COALESCE(css.sort_order, 9999) ASC, sc.sort_order ASC, cs.sort_order ASC
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

        $metaKw   = htmlspecialchars($series['seo_keywords'] ?? '');
        $metaDesc = htmlspecialchars($series['seo_description'] ?? '');
        $script   = $this->getSeriesLandingScriptName($series);

        $previewBadge = $isPreview
            ? "<div style='position:absolute;top:20px;left:50%;transform:translateX(-50%);background:var(--accent-color);color:#000;padding:6px 16px;border-radius:4px;font-family:var(--font-title);font-size:0.7rem;font-weight:bold;letter-spacing:2px;z-index:999;text-transform:uppercase;'>Preview Mode</div>"
            : "";

        // Build hierarchy for seasons/cinemagics
        $hierarchy = [];
        foreach ($episodes as $ep) {
            $super = $ep['super_season_title'] ?: '';
            $cm    = $ep['season_name'] ?: 'Extras';
            $hierarchy[$super][$cm][] = $ep;
        }

        $tocHtml = '';
        foreach ($hierarchy as $superTitle => $cinemagics) {
            if ($superTitle !== '') {
                $tocHtml .= "<h2 class='super-season-hdr'>" . htmlspecialchars($superTitle) . "</h2>";
            }
            
            foreach ($cinemagics as $cmName => $eps) {
                $coverHtml = '';

                // Only generate the cover image if the Season Grouping layer is active
                if ($hasSeasons) {
                    $firstEp = $eps[0];
                    
                    // Priority 1: Explicit Cinemagic Cover
                    $rawCover = $firstEp['cm_cover_image_url'] ?: '';
                    
                    // Priority 2: Episode Cover Fallback
                    if (!$rawCover) {
                        $rawCover = $firstEp['cover_image_url'] ?: '';
                    }
                    
                    // Priority 3: Episode Frames Fallback
                    if (!$rawCover) {
                        $items = json_decode($firstEp['sequence_data'] ?? '[]', true) ?: [];
                        if (!empty($items)) {
                            $firstItem = $items[0];
                            $sid = is_array($firstItem) ? (int)($firstItem['sketch_id']??0) : (int)$firstItem;
                            $fid = is_array($firstItem) ? (int)($firstItem['frame_id']??0) : 0;
                            if ($fid) {
                                $fStmt = $this->pdo->prepare("SELECT filename FROM frames WHERE id = ?");
                                $fStmt->execute([$fid]);
                                $rawCover = $fStmt->fetchColumn();
                            } else if ($sid) {
                                $fStmt = $this->pdo->prepare("SELECT f.filename FROM frames f INNER JOIN frames_2_sketches m ON m.from_id = f.id WHERE f.entity_id = ? ORDER BY f.id DESC LIMIT 1");
                                $fStmt->execute([$sid]);
                                $rawCover = $fStmt->fetchColumn();
                            }
                        }
                    }

                    $cmCover = $rawCover;
                    if ($cmCover) {
                        if ($repoAssetPath !== '') {
                            $cmCover = rtrim($urlPrefix, '/') . '/' . trim($repoAssetPath, '/') . '/' . basename($cmCover);
                        } elseif ($makeLocalRelative) {
                            $cmCover = 'assets/' . basename($cmCover);
                        } elseif ($urlPrefix) {
                            $cmCover = rtrim($urlPrefix, '/') . '/' . ltrim($cmCover, '/');
                        } else {
                            $cmCover = str_starts_with($cmCover, '/') ? $cmCover : '/' . $cmCover;
                        }
                    }

                    $coverHtml = $cmCover 
                        ? "<img src='" . htmlspecialchars($cmCover) . "' class='cm-cover' alt='Cover' loading='lazy'>" 
                        : "<div class='cm-cover' style='background:#111; display:flex; align-items:center; justify-content:center;'><span style='color:#555;font-size:0.6rem;'>No Cover</span></div>";
                }

                $epLinks = '';
                foreach ($eps as $ep) {
                    $seqId = $ep['id'];
                    $suffix = $langCode === 'en' ? '' : '_' . $langCode;
                    $href = $isPreview
                        ? "api.php?action=preview_episode&series_id={$seriesId}&seq_id={$seqId}&lang={$langCode}"
                        : "ep_{$seqId}{$suffix}.html";

                    $epName = $ep['name'];
                    if (!empty($ep['en_name'])) $epName = $ep['en_name'];
                    if ($langCode !== 'en' && !empty($ep['lang_name'])) $epName = $ep['lang_name'];

                    $linkLabel = $ep['chapter_label'] ? htmlspecialchars($ep['chapter_label']) . ': ' . htmlspecialchars($epName) : htmlspecialchars($epName);

                    $epLinks .= "<a class='ep-link' href='{$href}'>" . $linkLabel . "</a>";
                }

                $cmNameSafe = htmlspecialchars($cmName);
                $tocHtml .= <<<HTML
<div class="cm-block">
    {$coverHtml}
    <div class="cm-info">
        <div class="cm-pre">Cinemagic Collection</div>
        <div class="cm-title">{$cmNameSafe}</div>
        <div class="cm-ep-list">
            {$epLinks}
        </div>
    </div>
</div>
HTML;
            }
        }

        $langHtml = '';
        if (count($availableLangs) > 1) {
            $langOptions = '';
            foreach ($availableLangs as $l) {
                if ($isPreview) {
                    $url = "api.php?action=preview_series&id={$seriesId}&lang={$l}";
                } else {
                    $suffix = $l === 'en' ? '' : '_' . $l;
                    $url = $script . $suffix . '.html';
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
        
        .toc { width: 100%; max-width: 600px; text-align: left; display: flex; flex-direction: column; gap: 24px; margin-top: 20px; }
        .super-season-hdr { font-family: var(--font-title); font-size: 1.1rem; color: #888; letter-spacing: 2px; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; margin: 20px 0 0px; }
        .cm-block { display: flex; gap: 20px; align-items: flex-start; padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .cm-cover { width: 80px; aspect-ratio: 3/4; object-fit: cover; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.5); flex-shrink: 0; }
        .cm-info { display: flex; flex-direction: column; flex: 1; }
        .cm-pre { font-family: var(--font-title); font-size: 0.6rem; letter-spacing: 2px; color: var(--accent-color); opacity: 0.7; text-transform: uppercase; margin-bottom: 6px; }
        .cm-title { font-family: var(--font-body); font-size: 1.2rem; color: var(--text-color); line-height: 1.3; margin-bottom: 8px; }
        .cm-ep-list { display: flex; flex-direction: column; gap: 8px; }
        .ep-link { display: inline-flex; align-items: flex-start; gap: 8px; color: var(--text-color); opacity: 0.85; text-decoration: none; font-size: 0.95rem; transition: color 0.2s, transform 0.2s, opacity 0.2s; line-height: 1; margin-bottom: 0px; }
.ep-link:hover { color: var(--accent-color); opacity: 1; transform: translateX(4px); }
        .ep-link::before { content: '▶'; font-size: 0.6rem; color: var(--accent-color); opacity: 0.6; margin-top: 6px; }
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
<html lang="{$langCode}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{$title}</title>
    <meta name="keywords" content="{$metaKw}">
    <meta name="description" content="{$metaDesc}">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Lora:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
    <style>
        :root { --bg-color: #020202; --text-color: #e5e0d8; --accent-color: #cda434; --font-title: 'Cinzel', serif; --font-body: 'Lora', serif; }
        body, html { margin: 0; padding: 0; background-color: var(--bg-color); color: var(--text-color); font-family: var(--font-body); min-height: 100vh; overflow-x: hidden; }
        .hero-bg { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: url('{$cover}') center/cover no-repeat; z-index: 1; display: block; text-decoration: none; cursor: pointer; }
        .hero-bg::after { content:''; position:absolute; inset:0; background: linear-gradient(to bottom, rgba(2,2,2,0.6) 0%, rgba(2,2,2,0.95) 100%); }
        .content { position: relative; z-index: 10; max-width: 800px; margin: 0 auto; padding: 100px 20px 80px; display: flex; flex-direction: column; align-items: center; text-align: center; }
        .series-title { font-family: var(--font-title); font-size: 3rem; font-weight: 400; margin: 0 0 24px; color: var(--accent-color); line-height: 1.2; letter-spacing: 3px; text-shadow: 0 4px 20px rgba(0,0,0,0.8); }
        .series-desc { font-size: 1.15rem; opacity: 0.85; line-height: 1.7; max-width: 650px; margin: 0 auto 60px; text-shadow: 0 2px 10px rgba(0,0,0,0.8); }
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

        $gaCode = '';
        if (!$isPreview) {
            $gaCode = <<<HTML
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-SBSTRVS0NR"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-SBSTRVS0NR');
</script>
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
<html lang="{$langCode}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{$title}</title>
    <meta name="keywords" content="{$metaKw}">
    <meta name="description" content="{$metaDesc}">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Lora:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
    <style>
        :root { --bg-color: #020202; --text-color: #e5e0d8; --accent-color: #cda434; --font-title: 'Cinzel', serif; --font-body: 'Lora', serif; }
        body, html { margin: 0; padding: 0; background-color: var(--bg-color); background-image: radial-gradient(circle at 50% 0%, #15151a 0%, #020202 60%); background-attachment: fixed; color: var(--text-color); font-family: var(--font-body); min-height: 100vh; }
        .content { max-width: 800px; margin: 0 auto; padding: 80px 20px; display: flex; flex-direction: column; align-items: center; text-align: center; }
        .cover-img { width: 100%; max-width: 500px; height: auto; border-radius: 4px; box-shadow: 0 10px 40px rgba(0,0,0,0.8); margin-bottom: 40px; border: 1px solid rgba(255,255,255,0.05); }
        .series-title { font-family: var(--font-title); font-size: 2.8rem; font-weight: 400; margin: 0 0 20px; color: var(--accent-color); line-height: 1.2; letter-spacing: 2px; }
        .series-desc { font-size: 1.15rem; opacity: 0.8; line-height: 1.7; max-width: 650px; margin: 0 auto 60px; }
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
    {$gaCode}
</body>
</html>
HTML;
    }


// ── Rollout and Export Zip Generation ─────────────────────────────────────────

    public function exportSeriesZip(int $seriesId, string $publicPathAbs, bool $excludeAssets = false): ?string
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
        
        $scriptName = $this->getSeriesLandingScriptName($series);

        $hasSeasons = (int)($series['has_seasons'] ?? 0) === 1;
        $seasonFilter = $hasSeasons ? " AND sc.season_id IS NOT NULL " : "";

        $epStmt = $this->pdo->prepare("
            SELECT ns.id, ns.name, c.name as season_name, sc.cover_image_url AS cm_cover_image_url
            FROM cinemagic_series_2_cinemagics sc
            JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = sc.cinemagic_id
            JOIN narrative_sequences ns ON ns.id = cs.sequence_id
            JOIN cinemagics c ON c.id = sc.cinemagic_id
            WHERE sc.series_id = ? {$seasonFilter}
            ORDER BY sc.sort_order ASC, cs.sort_order ASC
        ");
        $epStmt->execute([$seriesId]);
        $episodes = $epStmt->fetchAll(\PDO::FETCH_ASSOC);

        $copiedAssets = [];

        $cover = $series['cover_image_url'] ?? '';
        if (!$excludeAssets && $cover) {
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

                // Package the cinemagic cover asset if it exists
                if (!$excludeAssets && !empty($ep['cm_cover_image_url'])) {
                    $cmCovFn = ltrim($ep['cm_cover_image_url'], '/');
                    if (!str_starts_with($cmCovFn, 'http')) {
                        $cmCovAbs = rtrim($publicPathAbs, '/') . '/' . $cmCovFn;
                        if (file_exists($cmCovAbs) && !in_array($cmCovFn, $copiedAssets)) {
                            if (isset($zip)) { $zip->addFile($cmCovAbs, 'assets/' . basename($cmCovFn)); }
                            $copiedAssets[] = $cmCovFn;
                        }
                    }
                }

                $epDataForHtml = $this->getEpisodeData($seqId, $urlPrefix, $makeRel, $epLinkFormat, $repoAssetsPath, $lang);
                $epDataForHtml['available_langs'] = $langs;
                $epDataForHtml['current_lang']    = $lang;
                
                $zip->addFromString("ep_{$seqId}{$suffix}.html", $this->renderEpisodeHtml($epDataForHtml));

                $sidecarJson = json_encode($epDataForHtml, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $zip->addFromString("data/ep_{$seqId}{$suffix}.js", "const episodeData = " . $sidecarJson . ";\n// export default episodeData;");

                if (!$excludeAssets && !empty($epDataForHtml['episode_cover_raw'])) {
                    $covFn = ltrim($epDataForHtml['episode_cover_raw'], '/');
                    if (!str_starts_with($covFn, 'http')) {
                        $covAbs = rtrim($publicPathAbs, '/') . '/' . $covFn;
                        if (file_exists($covAbs) && !in_array($covFn, $copiedAssets)) {
                            $zip->addFile($covAbs, 'assets/' . basename($covFn));
                            $copiedAssets[] = $covFn;
                        }
                    }
                }

                if (!$excludeAssets) {
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
            }

            $zip->addFromString("{$scriptName}{$suffix}.html", $this->renderSeriesIndexHtml(
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
        $scriptName     = $this->getSeriesLandingScriptName($series);

        $langsRaw = $series['supported_languages'] ?? 'en';
        $langs    = array_filter(array_map('trim', explode(',', $langsRaw)));
        if (!in_array('en', $langs)) array_unshift($langs, 'en');

        @mkdir($outDir . '/assets', 0777, true);
        @mkdir($outDir . '/data',   0777, true);

        $hasSeasons = (int)($series['has_seasons'] ?? 0) === 1;
        $seasonFilter = $hasSeasons ? " AND sc.season_id IS NOT NULL " : "";

        $epStmt = $this->pdo->prepare("
            SELECT ns.id, ns.name, c.name as season_name, sc.cover_image_url AS cm_cover_image_url
            FROM cinemagic_series_2_cinemagics sc
            JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = sc.cinemagic_id
            JOIN narrative_sequences ns ON ns.id = cs.sequence_id
            JOIN cinemagics c ON c.id = sc.cinemagic_id
            WHERE sc.series_id = ? {$seasonFilter}
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

                // Package the cinemagic cover asset if it exists
                if (!empty($ep['cm_cover_image_url'])) {
                    $cmCovFn = ltrim($ep['cm_cover_image_url'], '/');
                    if (!str_starts_with($cmCovFn, 'http')) {
                        $cmCovAbs = rtrim($publicPathAbs, '/') . '/' . $cmCovFn;
                        if (file_exists($cmCovAbs) && !in_array($cmCovFn, $copiedAssets)) {
                            if (isset($outDir)) { @copy($cmCovAbs, $outDir . '/assets/' . basename($cmCovFn)); }
                            $copiedAssets[] = $cmCovFn;
                        }
                    }
                }

                $epDataForHtml = $this->getEpisodeData($seqId, $urlPrefix, $makeRel, $epLinkFormat, $repoAssetsPath, $lang);
                $epDataForHtml['available_langs'] = $langs;
                $epDataForHtml['current_lang']    = $lang;

                file_put_contents($outDir . "/ep_{$seqId}{$suffix}.html", $this->renderEpisodeHtml($epDataForHtml));

                $sidecarJson = json_encode($epDataForHtml, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                file_put_contents($outDir . "/data/ep_{$seqId}{$suffix}.js", "const episodeData = " . $sidecarJson . ";");

                if (!empty($epDataForHtml['episode_cover_raw'])) {
                    $covFn = ltrim($epDataForHtml['episode_cover_raw'], '/');
                    if (!str_starts_with($covFn, 'http')) {
                        $covAbs = rtrim($publicPathAbs, '/') . '/' . $covFn;
                        if (file_exists($covAbs) && !in_array($covFn, $copiedAssets)) {
                            @copy($covAbs, $outDir . '/assets/' . basename($covFn));
                            $copiedAssets[] = $covFn;
                        }
                    }
                }

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

            file_put_contents($outDir . "/{$scriptName}{$suffix}.html", $this->renderSeriesIndexHtml(
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

        $fukiTexts = [];
        if (!empty($pureSketchIds)) {
            $inClause = implode(',', array_fill(0, count($pureSketchIds), '?'));
            try {
                $stmtFuki = $this->pdo->prepare("SELECT * FROM fuki_texts WHERE sketch_id IN ($inClause) ORDER BY language_code ASC, id ASC");
                $stmtFuki->execute($pureSketchIds);
                $fukiRaw = $stmtFuki->fetchAll(\PDO::FETCH_ASSOC);

                $fukiBaseEn = [];
                $fukiByLang = [];
                foreach ($fukiRaw as $r) {
                    $sid = (int)$r['sketch_id'];
                    $l = $r['language_code'];
                    $uid = $r['element_uid'];
                    if ($l === 'en') {
                        $fukiBaseEn[$sid][$uid] = $r;
                    } else {
                        $fukiByLang[$l][$sid][$uid] = $r;
                    }
                }
                
                $availableLangs = array_unique(array_merge(['en'], array_keys($fukiByLang)));
                
                foreach ($pureSketchIds as $sid) {
                    foreach ($availableLangs as $l) {
                        $merged = $fukiBaseEn[$sid] ?? [];
                        if ($l !== 'en' && isset($fukiByLang[$l][$sid])) {
                            foreach ($fukiByLang[$l][$sid] as $uid => $langData) {
                                $merged[$uid] = array_merge($merged[$uid] ?? [], $langData);
                            }
                        }
                        if (!empty($merged)) {
                            $fukiTexts[$sid][$l] = array_values($merged);
                        }
                    }
                }
            } catch (\Exception $e) {}
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
                'filename'      => $filename, 
                'overlay_texts' => $frameOverlays,
                'fuki_texts'    => $fukiTexts[$sid] ?? []
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

        // Check if an explicit episode cover was set via Episode Meta
        $customCoverFile = null;
        $cId = $this->getCinemagicIdForSequenceInSeries($seriesId, $sequenceId);
        $epMeta = $cId ? $this->getEpisodeMeta($cId, $sequenceId) : [];
        if (!empty($epMeta['cover_image_url'])) {
            $fn = ltrim($epMeta['cover_image_url'], '/');
            if (!str_starts_with($fn, 'http')) {
                $customCoverFile = $fn;
                $coverFrameId = 0; // Use magic ID 0 for the explicit cover
            }
        }

        return [
            'success'                  => true,
            'sequence_name'            => $finalSeqName,
            'description'              => $finalSeqDesc,
            'localized_sequence_names' => $locNames,
            'localized_descriptions'   => $locDescs,
            'cover_frame_id'           => $coverFrameId,
            'custom_cover_file'        => $customCoverFile,
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

        $stmtS = $this->pdo->prepare("SELECT title, asset_url_prefix, pdf_full_upright, pdf_disable_texts, pdf_disable_fuki FROM cinemagic_series WHERE id = ?");
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
                // Force empty arrays to be JSON objects {} so Pydantic Dict validation doesn't fail
                'overlay_texts' => empty($f['overlay_texts']) ? new \stdClass() : $f['overlay_texts'],
                'fuki_texts'    => empty($f['fuki_texts']) ? new \stdClass() : $f['fuki_texts']
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
            
            // Force empty arrays to be JSON objects {}
            'localized_sequence_names' => empty($exportData['localized_sequence_names']) ? new \stdClass() : $exportData['localized_sequence_names'],
            'localized_descriptions'   => empty($exportData['localized_descriptions']) ? new \stdClass() : $exportData['localized_descriptions'],
            
            'cover_frame_id'           => $exportData['cover_frame_id'],
            'frames'                   => $safeFrames,
            'pdf_full_upright'         => (bool)($series['pdf_full_upright'] ?? false),
            'pdf_disable_texts'        => (bool)($series['pdf_disable_texts'] ?? false),
            'pdf_disable_fuki'         => (bool)($series['pdf_disable_fuki'] ?? false)
        ];

        
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
        
        // Attach the explicit episode cover if one exists
        if (!empty($exportData['custom_cover_file'])) {
            $absPath = rtrim($publicPathAbs, '/') . '/' . ltrim($exportData['custom_cover_file'], '/');
            if (file_exists($absPath)) {
                $mime = mime_content_type($absPath) ?: 'image/jpeg';
                $content = file_get_contents($absPath);
                
                $body .= "--" . $boundary . "\r\n";
                $body .= "Content-Disposition: form-data; name=\"images\"; filename=\"image_0.jpg\"\r\n";
                $body .= "Content-Type: " . $mime . "\r\n\r\n";
                $body .= $content . "\r\n";
                $count++;
            } else {
                error_log("PDF Export: Custom cover file missing at " . $absPath);
            }
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
