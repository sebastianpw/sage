<?php
/**
 * SAGE Content Hub — Manager
 * src/ContentHub/ContentHubManager.php
 *
 * Central service class for the Content Hub module.
 * Handles posts with extended social media metadata,
 * calendar data, asset browsing, stats, and export logic.
 */

namespace App\ContentHub;

class ContentHubManager
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        // Ensure magazine_highlight is in post_type enum seamlessly without inflating
        try {
            $this->pdo->exec("ALTER TABLE content_hub_posts MODIFY COLUMN post_type ENUM('image_grid','image_swiper','video_playlist','youtube_playlist','url_reference','story','reel','thread','scrollmagic_gallery','cinematic_story','anime_gallery','narrative_gallery','spatial_viewer','magazine_highlight') NOT NULL DEFAULT 'image_grid'");
        } catch (\Exception $e) {}
    }

    // ── Post CRUD ──────────────────────────────────────────────────────────

    public function getPosts(array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['platform'])) {
            // Match against both the legacy single-platform column and the multi-platform JSON array
            $where[]  = '(platform = :platform OR JSON_CONTAINS(platforms_json, JSON_QUOTE(:platform2)))';
            $params[':platform']  = $filters['platform'];
            $params[':platform2'] = $filters['platform'];
        }

        if (!empty($filters['status'])) {
            $where[]  = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[]  = '(title LIKE :search OR content LIKE :search2)';
            $params[':search']  = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        $page    = max(1, (int)($filters['page'] ?? 1));
        $perPage = (int)($filters['per_page'] ?? 40);
        $offset  = ($page - 1) * $perPage;

        $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM content_hub_posts
                WHERE " . implode(' AND ', $where) . "
                ORDER BY sort_order DESC, scheduled_at DESC, created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  \PDO::PARAM_INT);
        $stmt->execute();

        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $total = (int)$this->pdo->query("SELECT FOUND_ROWS()")->fetchColumn();

        return [
            'success' => true,
            'posts'   => $posts,
            'total'   => $total,
            'page'    => $page,
            'pages'   => (int)ceil($total / $perPage),
        ];
    }
    
    
   public function getPostById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM content_hub_posts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function getUpcomingPosts(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM content_hub_posts
             WHERE status = 'scheduled' AND scheduled_at >= NOW()
             ORDER BY scheduled_at ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getPublishedEpisodes(): array {
        $sql = "SELECT s.id as series_id, s.title as series_title, s.asset_url_prefix,
                       ns.id as sequence_id, ns.name as episode_name, ns.sequence_data,
                       cs.chapter_label, cs.cover_image_url as ep_cover, s.landing_page_script
                FROM cinemagic_series s
                JOIN cinemagic_series_2_cinemagics sc ON sc.series_id = s.id
                JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = sc.cinemagic_id
                JOIN narrative_sequences ns ON ns.id = cs.sequence_id
                WHERE s.status = 'published'
                ORDER BY s.sort_order DESC, s.id DESC, sc.sort_order ASC, cs.sort_order ASC";
        $episodes = $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($episodes as &$ep) {
            $cover = $ep['ep_cover'];
            if (!$cover) {
                // Fallback: Use the first page of the episode exactly as done in reading section
                $items = json_decode($ep['sequence_data'] ?? '[]', true) ?: [];
                if (!empty($items)) {
                    $first = $items[0];
                    $sid = is_array($first) ? (int)($first['sketch_id']??0) : (int)$first;
                    $fid = is_array($first) ? (int)($first['frame_id']??0) : 0;
                    if ($fid) {
                        $fStmt = $this->pdo->prepare("SELECT filename FROM frames WHERE id = ?");
                        $fStmt->execute([$fid]);
                        $cover = $fStmt->fetchColumn();
                    } else if ($sid) {
                        $fStmt = $this->pdo->prepare("SELECT f.filename FROM frames f INNER JOIN frames_2_sketches m ON m.from_id = f.id WHERE f.entity_id = ? ORDER BY f.id DESC LIMIT 1");
                        $fStmt->execute([$sid]);
                        $cover = $fStmt->fetchColumn();
                    }
                }
            }
            $ep['resolved_cover'] = $cover;
        }
        return ['success' => true, 'episodes' => $episodes];
    }

    public function savePost(array $data, string $publicPathAbs = ''): array
    {
        $id = !empty($data['id']) ? (int)$data['id'] : null;

        $slug = !empty($data['slug'])
            ? $this->slugify($data['slug'])
            : $this->slugify($data['title'] ?? 'post');

        $slug = $this->uniqueSlug($slug, $id);
        $mediaJson = $this->sanitiseJson($data['media_items'] ?? '[]');

        $scheduledAt = null;
        if (!empty($data['scheduled_at'])) {
            $ts = strtotime($data['scheduled_at']);
            if ($ts) $scheduledAt = date('Y-m-d H:i:s', $ts);
        }

        // Sanitise platforms_json
        $platformsJson = null;
        if (!empty($data['platforms_json'])) {
            $decoded = json_decode($data['platforms_json'], true);
            if (is_array($decoded)) {
                $platformsJson = json_encode(array_values(array_filter($decoded)));
            }
        }

        if ($id) {
            $sql = "UPDATE content_hub_posts SET
                        title             = :title,
                        slug              = :slug,
                        post_type         = :post_type,
                        status            = :status,
                        platform          = :platform,
                        platforms_json    = :platforms_json,
                        preview_image_url = :preview,
                        content           = :content,
                        hashtags          = :hashtags,
                        media_items       = :media,
                        sort_order        = :sort,
                        scheduled_at      = :scheduled_at,
                        notes             = :notes,
                        asset_url_prefix  = :asset_url_prefix,
                        updated_at        = NOW()
                    WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':title'          => $data['title'] ?? '',
                ':slug'           => $slug,
                ':post_type'      => $data['post_type'] ?? 'image_grid',
                ':status'         => $data['status'] ?? 'draft',
                ':platform'       => $data['platform'] ?? null,
                ':platforms_json' => $platformsJson,
                ':preview'        => $data['preview_image_url'] ?? null,
                ':content'        => $data['content'] ?? null,
                ':hashtags'       => $data['hashtags'] ?? null,
                ':media'          => $mediaJson,
                ':sort'           => (int)($data['sort_order'] ?? 0),
                ':scheduled_at'   => $scheduledAt,
                ':notes'          => $data['notes'] ?? null,
                ':asset_url_prefix' => $data['asset_url_prefix'] ?? null,
                ':id'             => $id,
            ]);

            $insertedId = $id;
        } else {
            $sql = "INSERT INTO content_hub_posts
                        (title, slug, post_type, status, platform, platforms_json, preview_image_url,
                         content, hashtags, media_items, sort_order, scheduled_at, notes, asset_url_prefix,
                         created_at, updated_at)
                    VALUES
                        (:title, :slug, :post_type, :status, :platform, :platforms_json, :preview,
                         :content, :hashtags, :media, :sort, :scheduled_at, :notes, :asset_url_prefix,
                         NOW(), NOW())";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':title'          => $data['title'] ?? '',
                ':slug'           => $slug,
                ':post_type'      => $data['post_type'] ?? 'image_grid',
                ':status'         => $data['status'] ?? 'draft',
                ':platform'       => $data['platform'] ?? null,
                ':platforms_json' => $platformsJson,
                ':preview'        => $data['preview_image_url'] ?? null,
                ':content'        => $data['content'] ?? null,
                ':hashtags'       => $data['hashtags'] ?? null,
                ':media'          => $mediaJson,
                ':sort'           => (int)($data['sort_order'] ?? 0),
                ':scheduled_at'   => $scheduledAt,
                ':notes'          => $data['notes'] ?? null,
                ':asset_url_prefix' => $data['asset_url_prefix'] ?? null,
            ]);

            $insertedId = (int)$this->pdo->lastInsertId();
        }

        // Handle copying media to a dedicated post sub-folder (skip for highlights since they act dynamically for grid indexing)
        $postType = $data['post_type'] ?? 'image_grid';
        if ($publicPathAbs && $postType !== 'magazine_highlight') {
            $postDirRel = '/content_hub/posts/post' . $insertedId;
            $postDirAbs = rtrim($publicPathAbs, '/') . $postDirRel;
            if (!file_exists($postDirAbs)) {
                @mkdir($postDirAbs, 0777, true);
            }

            // Copy Preview Image
            $previewUrl = $data['preview_image_url'] ?? null;
            if (!empty($previewUrl) && strpos($previewUrl, $postDirRel) !== 0 && !preg_match('/^https?:\/\//i', $previewUrl)) {
                $srcAbs = rtrim($publicPathAbs, '/') . '/' . ltrim($previewUrl, '/');
                if (file_exists($srcAbs)) {
                    $filename = basename($srcAbs);
                    $destAbs = $postDirAbs . '/' . $filename;
                    if (!file_exists($destAbs)) {
                        @copy($srcAbs, $destAbs);
                    }
                    $newPreviewUrl = $postDirRel . '/' . $filename;
                    $this->pdo->prepare("UPDATE content_hub_posts SET preview_image_url = ? WHERE id = ?")
                              ->execute([$newPreviewUrl, $insertedId]);
                }
            }

            // Copy Media Items & Thumbnails
            $mediaItems = json_decode($mediaJson, true) ?: [];
            $updatedMedia = [];
            $changed = false;

            foreach ($mediaItems as $item) {
                $src = $item['src'] ?? '';
                if (!empty($src) && strpos($src, $postDirRel) !== 0 && !preg_match('/^https?:\/\//i', $src)) {
                    $srcAbs = rtrim($publicPathAbs, '/') . '/' . ltrim($src, '/');
                    if (file_exists($srcAbs)) {
                        $filename = basename($srcAbs);
                        $destAbs = $postDirAbs . '/' . $filename;
                        if (!file_exists($destAbs)) {
                            @copy($srcAbs, $destAbs);
                        }
                        $item['original_src'] = $src;
                        $item['src'] = $postDirRel . '/' . $filename;
                        $changed = true;
                    }
                }

                // Copy Video Thumbnails
                $thumb = $item['thumb'] ?? ($item['thumbnail'] ?? '');
                if (!empty($thumb) && strpos($thumb, $postDirRel) !== 0 && !preg_match('/^https?:\/\//i', $thumb)) {
                    $thumbAbs = rtrim($publicPathAbs, '/') . '/' . ltrim($thumb, '/');
                    if (file_exists($thumbAbs)) {
                        $thumbDirAbs = $postDirAbs . '/thumbnails';
                        if (!file_exists($thumbDirAbs)) {
                            @mkdir($thumbDirAbs, 0777, true);
                        }
                        $thumbFilename = basename($thumbAbs);
                        $destThumbAbs = $thumbDirAbs . '/' . $thumbFilename;
                        if (!file_exists($destThumbAbs)) {
                            @copy($thumbAbs, $destThumbAbs);
                        }
                        
                        if (isset($item['thumb'])) {
                            $item['original_thumb'] = $thumb;
                            $item['thumb'] = $postDirRel . '/thumbnails/' . $thumbFilename;
                        } elseif (isset($item['thumbnail'])) {
                            $item['original_thumbnail'] = $thumb;
                            $item['thumbnail'] = $postDirRel . '/thumbnails/' . $thumbFilename;
                        }
                        $changed = true;
                    }
                }
                
                $updatedMedia[] = $item;
            }

            if ($changed) {
                $mediaJson = json_encode($updatedMedia, JSON_UNESCAPED_SLASHES);
                $this->pdo->prepare("UPDATE content_hub_posts SET media_items = ? WHERE id = ?")
                          ->execute([$mediaJson, $insertedId]);
            }
        }

        return ['success' => true, 'id' => $insertedId, 'action' => $id ? 'updated' : 'created'];
    }
    
    
    
   public function deletePost(int $id, string $publicPathAbs = ''): array
    {
        $stmt = $this->pdo->prepare("DELETE FROM content_hub_posts WHERE id = ?");
        $ok   = $stmt->execute([$id]);

        if ($ok && $publicPathAbs) {
            $postDirAbs = rtrim($publicPathAbs, '/') . '/content_hub/posts/post' . $id;
            if (is_dir($postDirAbs)) {
                $this->recursiveRmdir($postDirAbs);
            }
        }

        return ['success' => $ok];
    }

    private function recursiveRmdir(string $dir) 
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
                        $this->recursiveRmdir($dir. DIRECTORY_SEPARATOR .$object);
                    else
                        @unlink($dir. DIRECTORY_SEPARATOR .$object);
                }
            }
            @rmdir($dir);
        }
    }

    public function updatePostStatus(int $id, string $status): array
    {
        $allowed = ['draft', 'scheduled', 'published', 'archived'];
        if (!in_array($status, $allowed)) {
            return ['success' => false, 'error' => 'Invalid status'];
        }

        $extra = '';
        if ($status === 'published') {
            $extra = ", published_at = NOW()";
        }

        $stmt = $this->pdo->prepare("UPDATE content_hub_posts SET status = ?{$extra} WHERE id = ?");
        $ok   = $stmt->execute([$status, $id]);
        return ['success' => $ok];
    }

    // ── Export / Download / Rollout Methods ────────────────────────────────

public function exportGridHtml(bool $isPreview = false): string {
        global $spw;
        $projectRoot = $spw->getProjectPath();

        // ── Posts JSON (unchanged logic) ──────────────────────────────
        $posts    = $this->getPosts(['per_page' => 1000])['posts'];
        $gridData = [];
        foreach ($posts as $p) {
            $prefix = $p['asset_url_prefix'] ?? '';
            $item   = [
                'title'   => $p['title'],
                'preview' => $this->applyUrlPrefix($p['preview_image_url'], $prefix, true),
                'type'    => $p['post_type'],
            ];
            if ($p['post_type'] === 'url_reference') {
                $media        = json_decode($p['media_items'], true);
                $data         = (is_array($media) && isset($media[0])) ? $media[0] : $media;
                $item['file']   = $data['url'] ?? '#';
                $item['target'] = $data['target'] ?? '_self';
            } else {
                $item['file'] = $isPreview
                    ? 'api.php?action=preview_post&id=' . $p['id']
                    : 'posts/' . $p['slug'] . '.html';
            }
            $gridData[] = $item;
        }

        // ── System languages + lang picker ────────────────────────────────
        $langCode        = 'en';
        $systemLanguages = [];
        try {
            $lStmt           = $this->pdo->query("SELECT * FROM system_languages ORDER BY is_main DESC, code ASC");
            $systemLanguages = $lStmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { /* table may not exist yet */ }

        $langPickerHtml = '';
        if (count($systemLanguages) > 1) {
            $langOptions = '';
            foreach ($systemLanguages as $l) {
                $url          = 'index' . ($l['code'] === 'en' ? '' : '_' . $l['code']) . '.html';
                $langOptions .= '<a href="' . htmlspecialchars($url) . '">' . strtoupper($l['code']) . '</a>';
            }
            $currL          = strtoupper($langCode);
            $langPickerHtml = <<<HTML
<div class="lang-picker" id="lang-picker">
    <button class="lang-toggle" id="lang-toggle" aria-expanded="false" aria-haspopup="true">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/><path d="M2 12h20"/></svg>
        <span>{$currL}</span>
    </button>
    <div class="lang-menu" role="menu">{$langOptions}</div>
</div>
HTML;
        } else {
            $langPickerHtml = <<<HTML
<div class="lang-picker" id="lang-picker">
    <button class="lang-toggle" id="lang-toggle" aria-expanded="false">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/><path d="M2 12h20"/></svg>
        <span>EN</span>
    </button>
    <div class="lang-menu" role="menu"><a href="index.html">EN</a></div>
</div>
HTML;
        }

        // ── Published magazine series + Highlights ──────────────────────────────────────
        $magazineCardsHtml = '';
        
        
        
        
        
        
        
        
        
       
        try {
            // 1. HIGHLIGHTS
            $hlStmt = $this->pdo->prepare("SELECT * FROM content_hub_posts WHERE post_type = 'magazine_highlight' AND status = 'published' ORDER BY sort_order DESC, scheduled_at DESC, created_at DESC LIMIT 5");
            $hlStmt->execute();
            $highlights = $hlStmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($highlights as $hl) {
                $title = htmlspecialchars($hl['title']);
                $cover = $hl['preview_image_url'];
                $media = json_decode($hl['media_items'], true) ?: [];
                $seqId = (int)($media['sequence_id'] ?? 0);
                $seriesId = (int)($media['series_id'] ?? 0);

                // Fetch series title and LIVE prefix to construct the exact slug and base URL
                $seriesTitle = '';
                $liveSeriesPrefix = '';
                if ($seriesId) {
                    $stStmt = $this->pdo->prepare("SELECT title, asset_url_prefix FROM cinemagic_series WHERE id = ?");
                    $stStmt->execute([$seriesId]);
                    $sRow = $stStmt->fetch(\PDO::FETCH_ASSOC);
                    if ($sRow) {
                        $seriesTitle = $sRow['title'];
                        $liveSeriesPrefix = $sRow['asset_url_prefix'] ?? '';
                    }
                }
                $seriesSlug = preg_replace('/[^a-z0-9]+/', '-', strtolower($seriesTitle)) ?: 'series_' . $seriesId;
                $repoAssetPath = 'cinemagic_hub/' . $seriesSlug . '/assets';

                $suffix = $langCode === 'en' ? '' : '_' . $langCode;
                
                // Prioritize the live series prefix over the static one stored in the highlight post
                $assetPrefix = ($liveSeriesPrefix !== '') ? $liveSeriesPrefix : ($hl['asset_url_prefix'] ?? '');
                
                // REVERTED HREF: Point to the flat HTML structure
                $href = $isPreview
                    ? '../cinemagic_hub/api.php?action=preview_episode&series_id=' . $seriesId . '&seq_id=' . $seqId . '&lang=' . $langCode
                    : 'ep_' . $seqId . $suffix . '.html';

                // CORRECTED COVER: Properly handle Absolute URLs, Prefixes, and structural ../ relative paths
                if ($cover) {
                    if (preg_match('/^https?:\/\//i', $cover)) {
                        // Do not alter absolute external URLs
                    } else if ($isPreview) {
                        $cover = str_starts_with($cover, '/') ? $cover : '/' . $cover;
                    } else {
                        if ($assetPrefix !== '') {
                            $cover = rtrim($assetPrefix, '/') . '/' . $repoAssetPath . '/' . basename($cover);
                        } else {
                            $cover = '../' . $repoAssetPath . '/' . basename($cover);
                        }
                    }
                    $cover = htmlspecialchars($cover);
                    $thumbHtml = '<img class="mag-card-cover" src="' . $cover . '" alt="' . $title . '" loading="lazy">';
                } else {
                    $thumbHtml = '<div class="mag-card-cover-placeholder"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="1"/><path d="M3 9h18M9 21V9"/></svg></div>';
                }

                $metaLine = htmlspecialchars($hl['content'] ?? 'Featured Episode');

                $magazineCardsHtml .= <<<HTML
<a class="mag-card highlight-card fade-up" href="{$href}">
    <div class="highlight-badge">Featured Episode</div>
    {$thumbHtml}
    <div class="mag-card-body">
        <div class="mag-card-label">Magazine</div>
        <div class="mag-card-title">{$title}</div>
        <div class="mag-card-meta">{$metaLine}</div>
    </div>
</a>
HTML;
            }

            // 2. REGULAR SERIES
            $seriesStmt = $this->pdo->prepare("SELECT * FROM cinemagic_series WHERE status = 'published' ORDER BY sort_order DESC, id DESC");
            $seriesStmt->execute();
            $seriesList = $seriesStmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($seriesList as $series) {
                $sid     = (int)$series['id'];
                $title   = htmlspecialchars($series['title'] ?? 'Untitled');
                $status  = $series['status'] ?? 'draft';
                $seriesSlug = preg_replace('/[^a-z0-9]+/', '-', strtolower($series['title'] ?? '')) ?: 'series_' . $sid;

                // CORRECTED COVER: Respect Absolute URLs
                $cover = $series['cover_image_url'] ?? '';
                if ($cover) {
                    if (preg_match('/^https?:\/\//i', $cover)) {
                        // Do not alter absolute external URLs
                    } else if ($isPreview) {
                        $cover = str_starts_with($cover, '/') ? $cover : '/' . $cover;
                    } else {
                        $urlPrefix = $series['asset_url_prefix'] ?? '';
                        $repoAssetPath = 'cinemagic_hub/' . $seriesSlug . '/assets';
                        if ($urlPrefix !== '') {
                            $cover = rtrim($urlPrefix, '/') . '/' . $repoAssetPath . '/' . basename($cover);
                        } else {
                            $cover = '../' . $repoAssetPath . '/' . basename($cover);
                        }
                    }
                    $cover = htmlspecialchars($cover);
                }

                $seasonCount  = 0;
                $episodeCount = 0;
                try {
                    $hasSeasonsLayer = (int)($series['has_seasons'] ?? 0) === 1;
                    $seasonFilter = $hasSeasonsLayer ? " AND sc.season_id IS NOT NULL " : "";

                    $countStmt = $this->pdo->prepare(
                        "SELECT COUNT(DISTINCT sc.cinemagic_id) AS season_count,
                                COUNT(DISTINCT cs.sequence_id)  AS episode_count
                         FROM cinemagic_series_2_cinemagics sc
                         LEFT JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = sc.cinemagic_id
                         WHERE sc.series_id = ? {$seasonFilter}"
                    );
                    $countStmt->execute([$sid]);
                    $counts = $countStmt->fetch(\PDO::FETCH_ASSOC);
                    if ($counts) {
                        $seasonCount  = (int)($counts['season_count']  ?? 0);
                        $episodeCount = (int)($counts['episode_count'] ?? 0);
                    }
                } catch (\Throwable $e) {}

                $metaParts = [];
                if ($status === 'draft') $metaParts[] = '<span style="color:#f59e0b;">[DRAFT]</span>';
                if ($seasonCount)  $metaParts[] = $seasonCount  . ' ' . ($seasonCount  === 1 ? 'Season'  : 'Seasons');
                if ($episodeCount) $metaParts[] = $episodeCount . ' ' . ($episodeCount === 1 ? 'Episode' : 'Episodes');
                
                $metaLine = implode(' &middot; ', $metaParts);
                if ($metaLine === '') $metaLine = 'Magazine Series';

                $scriptName = trim($series['landing_page_script'] ?? '');
                if ($scriptName === '') {
                    try {
                        $fstStmt = $this->pdo->prepare("
                            SELECT cs.sequence_id
                            FROM cinemagic_series_2_cinemagics sc
                            JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = sc.cinemagic_id
                            WHERE sc.series_id = ?
                            ORDER BY sc.sort_order ASC, cs.sort_order ASC
                            LIMIT 1
                        ");
                        $fstStmt->execute([$sid]);
                        $firstSeq = $fstStmt->fetchColumn();
                        $scriptName = $firstSeq ? 'index_' . $firstSeq : 'index_' . $sid;
                    } catch (\Throwable $e) {
                        $scriptName = 'index_' . $sid;
                    }
                }
                
                $suffix = $langCode === 'en' ? '' : '_' . $langCode;
                
                // REVERTED HREF: Point to the flat HTML structure
                $href = $isPreview
                    ? '../cinemagic_hub/api.php?action=preview_series&id=' . $sid . '&lang=' . $langCode
                    : $scriptName . $suffix . '.html';

                if ($cover) {
                    $thumbHtml = '<img class="mag-card-cover" src="' . $cover . '" alt="' . $title . '" loading="lazy">';
                } else {
                    $thumbHtml = '<div class="mag-card-cover-placeholder"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="1"/><path d="M3 9h18M9 21V9"/></svg></div>';
                }

                $magazineCardsHtml .= <<<HTML
<a class="mag-card fade-up" href="{$href}">
    {$thumbHtml}
    <div class="mag-card-body">
        <div class="mag-card-label">Magazine Series</div>
        <div class="mag-card-title">{$title}</div>
        <div class="mag-card-meta">{$metaLine}</div>
    </div>
</a>
HTML;
            }
        } catch (\Throwable $e) {}

        
        
        
        


        
        
        
        
        
        
        
        
        
        
        
        
        
        
        

        if ($magazineCardsHtml === '') {
            $magazineCardsHtml = '<div class="mag-empty">No published magazine series yet.</div>';
        }

        // ── Assemble ───────────────────────────────────────────────────
        // ── Assemble ───────────────────────────────────────────────────
        $template = file_get_contents($projectRoot . '/templates/post_grid.html');
        $year     = date('Y');

        $seoMeta = <<<HTML
<title>Starlight Guardians — The Anima Chronicles</title>
<meta name="keywords" content="Starlight Guardians, The Anima Chronicles, original anime series, sci-fi fantasy anime, animated comic series, indie anime, webtoon science fiction, original animated universe, Anima magic system, Crater City, Shadow-Scab, Drift Coalition, Nova Terra, Tidalcross, Emberveil, Vortex Station, partnership versus force, anime worldbuilding, independent animation">
<meta name="description" content="Starlight Guardians: The Anima Chronicles is an original science fiction and fantasy animated series spanning five seasons across seven civilizations. An Anime story about what it costs to stop performing compliance — and what becomes possible when a world learns to ask before it takes.">
HTML;
        $template = str_replace('<title>Starlight Guardians — The Anima Chronicles</title>', $seoMeta, $template);

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

        return str_replace(
            ['{{POSTS_JSON}}', '{{MAGAZINE_CARDS_HTML}}', '{{LANG_PICKER_HTML}}', '{{LANG_CODE}}', '{{YEAR}}', '</body>'],
            [
                json_encode($gridData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                $magazineCardsHtml,
                $langPickerHtml,
                htmlspecialchars($langCode),
                $year,
                $gaCode . "\n</body>"
            ],
            $template
        );


        
        
        
        
    }
    
   public function exportPostZip(int $id, string $publicPathAbs): ?string {
        $post = $this->getPostById($id);
        if (!$post) return null;

        if ($post['post_type'] === 'magazine_highlight') {
            die("Magazine highlights are embedded directly within the grid and do not have standalone HTML pages.");
        }
        
        $tempDir = sys_get_temp_dir();
        $zipName = $tempDir . '/post_' . $id . '_' . time() . '.zip';
        
        $zip = new \ZipArchive();
        if ($zip->open($zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) return null;

        // cinematic_story uses a sidecar data JS file; generate static HTML that loads it
        if ($post['post_type'] === 'cinematic_story') {
            $assetsDir = 'post' . $id;
            $storyData = $this->buildCinematicStoryData($post, $post['asset_url_prefix'] ?? '');
            $jsContent = 'var storyboardData = ' . json_encode($storyData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
            $zip->addFromString('content_hub/posts/' . $assetsDir . '/data_' . $id . '.js', $jsContent);
            $html = $this->renderPostHtml($post, true, '', true);
            $zip->addFromString('content_hub/posts/' . $post['slug'] . '.html', $html);
            $this->addPostAssetsToZip($zip, $post, $publicPathAbs);
        } elseif ($post['post_type'] === 'anime_gallery') {
            // anime_gallery uses a sidecar data JS file with window.LOCATIONS + window.VIDEOS
            $assetsDir = 'post' . $id;
            [$locations, $videos] = $this->buildAnimeGalleryData($post, $post['asset_url_prefix'] ?? '');
            $jsContent  = 'window.LOCATIONS = ' . json_encode($locations, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
            $jsContent .= 'window.VIDEOS = '    . json_encode($videos,    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
            $zip->addFromString('content_hub/posts/' . $assetsDir . '/data_' . $id . '.js', $jsContent);
            $html = $this->renderPostHtml($post, true, '', true);
            $zip->addFromString('content_hub/posts/' . $post['slug'] . '.html', $html);
            $this->addPostAssetsToZip($zip, $post, $publicPathAbs);
        } elseif ($post['post_type'] === 'narrative_gallery') {
            // narrative_gallery uses a sidecar data JS file with const sequenceData
            $assetsDir  = 'post' . $id;
            $seqData    = $this->buildNarrativeGalleryData($post, $post['asset_url_prefix'] ?? '');
            $jsContent  = 'const sequenceData = '  . json_encode($seqData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
            $jsContent .= 'const rawExportData = {};' . "\n";
            $zip->addFromString('content_hub/posts/' . $assetsDir . '/data_' . $id . '.js', $jsContent);
            $html = $this->renderPostHtml($post, true, '', true);
            $zip->addFromString('content_hub/posts/' . $post['slug'] . '.html', $html);
            $this->addPostAssetsToZip($zip, $post, $publicPathAbs);
        } elseif ($post['post_type'] === 'spatial_viewer') {
            // spatial_viewer uses a sidecar data JS file with const seqData
            $assetsDir = 'post' . $id;
            $spatialData = $this->buildSpatialViewerData($post, $post['asset_url_prefix'] ?? '');
            $jsContent = 'const seqData = ' . json_encode($spatialData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
            $zip->addFromString('content_hub/posts/' . $assetsDir . '/data_' . $id . '.js', $jsContent);
            $html = $this->renderPostHtml($post, true, '', true);
            $zip->addFromString('content_hub/posts/' . $post['slug'] . '.html', $html);
            $this->addPostAssetsToZip($zip, $post, $publicPathAbs);
        } else {
            $html = $this->renderPostHtml($post, true, $post['asset_url_prefix'] ?? '', true);
            $zip->addFromString('content_hub/posts/' . $post['slug'] . '.html', $html);
            $this->addPostAssetsToZip($zip, $post, $publicPathAbs);
        }
        
        $zip->close();
        return $zipName;
    }

    public function exportAllHtml(): ?string {
        $posts = $this->getPosts(['per_page' => 1000])['posts'];
        $tempDir = sys_get_temp_dir();
        $zipName = $tempDir . '/content_hub_export_' . time() . '.zip';
        
        $zip = new \ZipArchive();
        if ($zip->open($zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) return null;
        
        $zip->addFromString('content_hub/index.html', $this->exportGridHtml(false));
        
        foreach ($posts as $post) {
            if ($post['post_type'] === 'magazine_highlight') {
                if (!empty($post['preview_image_url'])) {
                    $file = ltrim($post['preview_image_url'], '/');
                    if (!preg_match('/^https?:\/\//i', $file)) {
                        $absPath = rtrim($publicPathAbs ?? '', '/') . '/' . $file;
                        if (file_exists($absPath)) {
                            $zip->addFile($absPath, $file);
                        }
                    }
                }
                continue; // Skip individual HTML processing for highlight instances
            }
            
            $prefix = $post['asset_url_prefix'] ?? '';
            $html = $this->renderPostHtml($post, true, $prefix, false);
            $zip->addFromString('content_hub/posts/' . $post['slug'] . '.html', $html);

            // For cinematic_story also bundle the sidecar data JS
            if ($post['post_type'] === 'cinematic_story') {
                $storyData = $this->buildCinematicStoryData($post, $prefix);
                $jsContent = 'var storyboardData = ' . json_encode($storyData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
                $zip->addFromString('content_hub/posts/post' . $post['id'] . '/data_' . $post['id'] . '.js', $jsContent);
            }
            // For anime_gallery also bundle the sidecar data JS
            if ($post['post_type'] === 'anime_gallery') {
                [$locations, $videos] = $this->buildAnimeGalleryData($post, $prefix);
                $jsContent  = 'window.LOCATIONS = ' . json_encode($locations, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
                $jsContent .= 'window.VIDEOS = '    . json_encode($videos,    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
                $zip->addFromString('content_hub/posts/post' . $post['id'] . '/data_' . $post['id'] . '.js', $jsContent);
            }
            // For narrative_gallery also bundle the sidecar data JS
            if ($post['post_type'] === 'narrative_gallery') {
                $seqData   = $this->buildNarrativeGalleryData($post, $prefix);
                $jsContent  = 'const sequenceData = '  . json_encode($seqData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
                $jsContent .= 'const rawExportData = {};' . "\n";
                $zip->addFromString('content_hub/posts/post' . $post['id'] . '/data_' . $post['id'] . '.js', $jsContent);
            }
            // For spatial_viewer also bundle the sidecar data JS
            if ($post['post_type'] === 'spatial_viewer') {
                $spatialData = $this->buildSpatialViewerData($post, $prefix);
                $jsContent = 'const seqData = ' . json_encode($spatialData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
                $zip->addFromString('content_hub/posts/post' . $post['id'] . '/data_' . $post['id'] . '.js', $jsContent);
            }
        }
        
        $zip->close();
        return $zipName;
    }

    public function rolloutPost(int $id, string $publicPathAbs): array
    {
        $post = $this->getPostById($id);
        if (!$post) {
            return ['success' => false, 'error' => 'Post not found'];
        }

        $targetRepo = getenv('GITPAGES_REPO_PATH') ?: '/data/data/com.termux/files/home/www/gitpages/sg_showcase_01';
        if (!is_dir($targetRepo)) {
            return ['success' => false, 'error' => 'Git pages repository not found at: ' . $targetRepo];
        }

        // Setup the content_hub directory wrapper to match exact URL structures
        $contentHubDir = rtrim($targetRepo, '/') . '/content_hub';
        if (!is_dir($contentHubDir)) {
            @mkdir($contentHubDir, 0777, true);
        }

        // 1. Export the Grid HTML inside content_hub
        $gridHtml = $this->exportGridHtml(false);
        file_put_contents($contentHubDir . '/index.html', $gridHtml);

        if ($post['post_type'] === 'magazine_highlight') {
            // Bundle simply the highlight image for the grid index logic
            if (!empty($post['preview_image_url'])) {
                $file = ltrim($post['preview_image_url'], '/');
                if (!preg_match('/^https?:\/\//i', $file)) {
                    $absPath = rtrim($publicPathAbs, '/') . '/' . $file;
                    $destAbs = rtrim($targetRepo, '/') . '/' . $file;
                    if (file_exists($absPath)) {
                        @mkdir(dirname($destAbs), 0777, true);
                        @copy($absPath, $destAbs);
                    }
                }
            }
        } else {
            // 2. Export the Post HTML (applying the defined prefix)
            $postsDir = $contentHubDir . '/posts';
            if (!is_dir($postsDir)) {
                @mkdir($postsDir, 0777, true);
            }
            $postHtml = $this->renderPostHtml($post, true, $post['asset_url_prefix'] ?? '', true);
            file_put_contents($postsDir . '/' . $post['slug'] . '.html', $postHtml);

            // 3. For cinematic_story also write the sidecar data JS into the assets sub-folder
            if ($post['post_type'] === 'cinematic_story') {
                $assetsDirAbs = $postsDir . '/post' . $id;
                if (!is_dir($assetsDirAbs)) {
                    @mkdir($assetsDirAbs, 0777, true);
                }
                $storyData = $this->buildCinematicStoryData($post, $post['asset_url_prefix'] ?? '');
                $jsContent = 'var storyboardData = ' . json_encode($storyData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
                file_put_contents($assetsDirAbs . '/data_' . $id . '.js', $jsContent);
            }

            // 3b. For anime_gallery also write the sidecar data JS into the assets sub-folder
            if ($post['post_type'] === 'anime_gallery') {
                $assetsDirAbs = $postsDir . '/post' . $id;
                if (!is_dir($assetsDirAbs)) {
                    @mkdir($assetsDirAbs, 0777, true);
                }
                [$locations, $videos] = $this->buildAnimeGalleryData($post, $post['asset_url_prefix'] ?? '');
                $jsContent  = 'window.LOCATIONS = ' . json_encode($locations, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
                $jsContent .= 'window.VIDEOS = '    . json_encode($videos,    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
                file_put_contents($assetsDirAbs . '/data_' . $id . '.js', $jsContent);
            }

            // 3c. For narrative_gallery also write the sidecar data JS into the assets sub-folder
            if ($post['post_type'] === 'narrative_gallery') {
                $assetsDirAbs = $postsDir . '/post' . $id;
                if (!is_dir($assetsDirAbs)) {
                    @mkdir($assetsDirAbs, 0777, true);
                }
                $seqData   = $this->buildNarrativeGalleryData($post, $post['asset_url_prefix'] ?? '');
                $jsContent  = 'const sequenceData = '  . json_encode($seqData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
                $jsContent .= 'const rawExportData = {};' . "\n";
                file_put_contents($assetsDirAbs . '/data_' . $id . '.js', $jsContent);
            }

            // 3d. For spatial_viewer also write the sidecar data JS into the assets sub-folder
            if ($post['post_type'] === 'spatial_viewer') {
                $assetsDirAbs = $postsDir . '/post' . $id;
                if (!is_dir($assetsDirAbs)) {
                    @mkdir($assetsDirAbs, 0777, true);
                }
                $spatialData = $this->buildSpatialViewerData($post, $post['asset_url_prefix'] ?? '');
                $jsContent = 'const seqData = ' . json_encode($spatialData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
                file_put_contents($assetsDirAbs . '/data_' . $id . '.js', $jsContent);
            }

            // 4. Copy Post Assets relative to local repo mirroring the content_hub/posts structure dynamically
            $sourceAssetsDir = rtrim($publicPathAbs, '/') . '/content_hub/posts/post' . $id;
            $targetAssetsDir = $postsDir . '/post' . $id;

            if (is_dir($sourceAssetsDir)) {
                $this->recursiveCopy($sourceAssetsDir, $targetAssetsDir);
            }
        }

        // 5. Enqueue GitHub Sync job
        $payload = [
            'repo_path'       => $targetRepo,
            'branch_name'     => 'main',
            'remote_name'     => 'origin',
            'commit_message'  => 'Rollout post: ' . $post['title'],
            'add_all'         => true,
            'commit'          => true,
            'push'            => true,
            'pull_rebase'     => false,
            'amend'           => false,
            'allow_empty'     => false,
            'dry_run'         => false,
            'force_push'      => false,
            'git_user_name'   => getenv('GIT_BOT_NAME') ?: 'Post Bot',
            'git_user_email'  => getenv('GIT_BOT_EMAIL') ?: 'post-bot@example.invalid',
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO forge_jobs (job_type, label, status, priority, payload, created_at, updated_at)
            VALUES ('github_sync', ?, 'pending', 50, ?, NOW(), NOW())
        ");
        $stmt->execute([
            'GitHub Rollout: ' . $post['title'],
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);

        return ['success' => true];
    }

    private function recursiveCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        @mkdir($dst, 0777, true);
        while (($file = readdir($dir)) !== false) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
    
    private function addPostAssetsToZip(\ZipArchive $zip, array $post, string $publicPathAbs) {
        $files = [];
        if (!empty($post['preview_image_url'])) {
            $files[] = ltrim($post['preview_image_url'], '/');
        }
        $media = json_decode($post['media_items'], true) ?: [];
        foreach ($media as $item) {
            if (!empty($item['src']))       $files[] = ltrim($item['src'], '/');
            // Include video thumbnails in the zip
            if (!empty($item['thumb']))     $files[] = ltrim($item['thumb'], '/');
            if (!empty($item['thumbnail'])) $files[] = ltrim($item['thumbnail'], '/');
        }
        
        foreach (array_unique($files) as $file) {
            if (preg_match('/^https?:\/\//i', $file)) continue;
            
            $absPath = rtrim($publicPathAbs, '/') . '/' . $file;
            if (file_exists($absPath)) {
                $zip->addFile($absPath, $file);
            }
        }
    }
    
    
   public function renderPostHtml(array $post, bool $forStaticExport = false, string $urlPrefix = '', bool $makeLocalRelative = false): string {
        global $spw;
        $projectRoot = $spw->getProjectPath();
        $templatePath = $projectRoot . '/templates/post_detail_' . $post['post_type'] . '.html';
        
        if (!file_exists($templatePath)) {
            return "Error: Template not found at {$templatePath}.";
        }
        
        $template = file_get_contents($templatePath);
        $backLink = $forStaticExport ? '../index.html' : './';
        
        $media = json_decode($post['media_items'], true) ?: [];
        
        foreach ($media as &$item) {
            foreach (['src', 'url', 'thumbnail', 'thumb'] as $key) {
                if (isset($item[$key])) {
                    if (!empty($urlPrefix) && !preg_match('/^https?:\/\//i', $item[$key])) {
                        // Priority 1: User explicitly defined an asset_url_prefix mapping
                        $item[$key] = rtrim($urlPrefix, '/') . '/' . ltrim($item[$key], '/');
                    } else if ($makeLocalRelative && !preg_match('/^https?:\/\//i', $item[$key])) {
                        // Priority 2: Use structurally reliable relative paths for offline usage
                        if (str_starts_with($item[$key], '/content_hub/posts/')) {
                            $item[$key] = substr($item[$key], 19);
                        } else if (str_starts_with($item[$key], 'content_hub/posts/')) {
                            $item[$key] = substr($item[$key], 18);
                        } else {
                            if (str_starts_with($item[$key], '/')) {
                                $item[$key] = '../..' . $item[$key];
                            } else {
                                $item[$key] = '../../' . $item[$key];
                            }
                        }
                    } else {
                        // Fallback processing
                        $item[$key] = $this->applyUrlPrefix($item[$key], $urlPrefix, false);
                    }
                }
            }
        }
        $mediaJson = json_encode($media, JSON_UNESCAPED_SLASHES);
        
        $replacements = [
            '{{POST_TITLE}}' => htmlspecialchars($post['title']),
            '{{POST_CONTENT}}' => $post['content'] ?? '', 
            '{{BACK_TO_GRID_URL}}' => $backLink
        ];
        
        switch ($post['post_type']) {
            case 'image_grid':
            case 'image_swiper':
            case 'video_playlist':
            case 'scrollmagic_gallery':
                $replacements['{{MEDIA_ITEMS_JSON}}'] = $mediaJson;
                break;
            case 'youtube_playlist':
                $embedUrl = $media[0]['url'] ?? ''; 
                $replacements['{{YOUTUBE_EMBED_URL}}'] = htmlspecialchars($embedUrl);
                break;
            case 'url_reference':
                $url = $media[0]['url'] ?? ($media['url'] ?? '#');
                $replacements['{{TARGET_URL}}'] = htmlspecialchars($url);
                break;
            case 'cinematic_story':
                $replacements['{{STORY_DATA_JS}}'] = $this->buildCinematicStoryDataScriptTag($post, $forStaticExport, $urlPrefix, $makeLocalRelative);
                break;
            case 'anime_gallery':
                $replacements['{{ANIME_DATA_JS}}'] = $this->buildAnimeGalleryDataScriptTag($post, $forStaticExport, $urlPrefix);
                break;
            case 'narrative_gallery':
                $replacements['{{SEQUENCE_DATA_JS}}'] = $this->buildNarrativeGalleryDataScriptTag($post, $forStaticExport, $urlPrefix);
                break;
            case 'spatial_viewer':
                $replacements['{{SPATIAL_DATA_JS}}'] = $this->buildSpatialViewerDataScriptTag($post, $forStaticExport, $urlPrefix);
                break;
        }
        
        $html = str_replace(array_keys($replacements), array_values($replacements), $template);
        
        if ($forStaticExport) {
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
            $html = str_replace('</body>', $gaCode . "\n</body>", $html);
        }

        return $html;
    }

  
    
    
    

    // ── Cinematic Story: data assembly ────────────────────────────────────

    public function buildCinematicStoryData(array $post, string $urlPrefix = ''): array
    {
        $mediaItems = json_decode($post['media_items'] ?? '[]', true) ?: [];

        if (empty($mediaItems)) {
            return ['name' => $post['title'], 'frames' => []];
        }

        $frameIds = array_filter(array_map(fn($i) => (int)($i['id'] ?? 0), $mediaItems));

        $frameEntityMap = [];
        if (!empty($frameIds)) {
            $in = implode(',', array_fill(0, count($frameIds), '?'));
            $stmt = $this->pdo->prepare("SELECT id, entity_type, entity_id FROM frames WHERE id IN ($in)");
            $stmt->execute(array_values($frameIds));
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $frameEntityMap[(int)$row['id']] = [
                    'entity_type' => $row['entity_type'] ?? '',
                    'entity_id'   => (int)($row['entity_id'] ?? 0),
                ];
            }
        }

        $entityRequests = [];
        foreach ($frameEntityMap as $fid => $info) {
            if (!empty($info['entity_type']) && !empty($info['entity_id'])) {
                $entityRequests[$info['entity_type']][] = $info['entity_id'];
            }
        }

        $entityData = [];
        $allowedTables = ['sketches','characters','locations','spawns','generatives','animas',
                          'artifacts','lotations','character_poses','character_anima_poses','character_expressions'];
        foreach ($entityRequests as $eType => $eIds) {
            if (!in_array($eType, $allowedTables)) continue;
            $uniqueIds = array_values(array_unique($eIds));
            $in = implode(',', array_fill(0, count($uniqueIds), '?'));
            try {
                $stmt = $this->pdo->prepare("SELECT id, name, description FROM `$eType` WHERE id IN ($in)");
                $stmt->execute($uniqueIds);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $entityData[$eType][(int)$row['id']] = [
                        'name'        => $row['name']        ?? '',
                        'description' => $row['description'] ?? '',
                    ];
                }
            } catch (\Throwable $e) {}
        }

        $sketchIds = array_values(array_unique($entityRequests['sketches'] ?? []));
        $analysisMap = [];
        if (!empty($sketchIds)) {
            $in = implode(',', array_fill(0, count($sketchIds), '?'));
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT sketch_id, classification, thematics, entities, recommendations, scoring
                     FROM sketch_analysis WHERE sketch_id IN ($in)"
                );
                $stmt->execute($sketchIds);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $analysisMap[(int)$row['sketch_id']] = $row;
                }
            } catch (\Throwable $e) {}
        }

        $frames = [];
        foreach ($mediaItems as $item) {
            $frameId   = (int)($item['id'] ?? 0);
            $srcRaw    = $item['src'] ?? '';

            $thumb = $srcRaw;
            if (!empty($urlPrefix) && !preg_match('/^https?:\/\//i', $thumb)) {
                $thumb = rtrim($urlPrefix, '/') . '/' . ltrim($thumb, '/');
            }

            $eType = $frameEntityMap[$frameId]['entity_type'] ?? '';
            $eId   = $frameEntityMap[$frameId]['entity_id']   ?? 0;

            $entName = $entityData[$eType][$eId]['name']        ?? '';
            $entDesc = $entityData[$eType][$eId]['description'] ?? '';

            $name = !empty($entName) ? $entName : ($item['name'] ?? ($item['alt'] ?? ''));
            $desc = !empty($entDesc) ? $entDesc : '';

            $curation = [];
            if ($eType === 'sketches' && $eId > 0 && isset($analysisMap[$eId])) {
                $a = $analysisMap[$eId];
                $scoringObj = json_decode($a['scoring'] ?? '{}', true) ?: [];
                $curation = [
                    'class'  => json_decode($a['classification']  ?? '{}', true) ?: [],
                    'themes' => json_decode($a['thematics']        ?? '{}', true) ?: [],
                    'entities' => json_decode($a['entities']       ?? '{}', true) ?: [],
                    'recs'   => json_decode($a['recommendations']  ?? '{}', true) ?: [],
                    'score'  => $scoringObj['overall_quality'] ?? 0,
                ];
            }

            $frames[] = [
                'id'               => $frameId,
                '_active_frame_id' => $frameId,
                'thumb'            => $thumb,
                'name'             => $name,
                'desc'             => $desc,
                'curation'         => $curation,
            ];
        }

        return [
            'name'   => $post['title'],
            'frames' => $frames,
        ];
    }

    private function buildCinematicStoryDataScriptTag(array $post, bool $forStaticExport, string $urlPrefix, bool $makeLocalRelative): string
    {
        if ($forStaticExport) {
            $sidecarPath = 'post' . $post['id'] . '/data_' . $post['id'] . '.js';
            return '<script src="' . htmlspecialchars($sidecarPath) . '"></script>';
        }

        $storyData = $this->buildCinematicStoryData($post, $urlPrefix);
        $json = json_encode($storyData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        return '<script>var storyboardData = ' . $json . ';</script>';
    }


// ── Anime Gallery: data assembly ──────────────────────────────────────

    public function buildAnimeGalleryData(array $post, string $urlPrefix = ''): array
    {
        $mediaItems = json_decode($post['media_items'] ?? '[]', true) ?: [];

        if (empty($mediaItems)) {
            return [[$post['title'] => []], []];
        }

        $isVideoItem = function (array $item): bool {
            if (isset($item['type']) && $item['type'] === 'video') return true;
            $src = $item['src'] ?? ($item['url'] ?? '');
            return (bool)preg_match('/\.(mp4|webm|ogg|mov)(\?|$)/i', $src);
        };

        $frameIds = [];
        foreach ($mediaItems as $item) {
            if (!$isVideoItem($item) && !empty($item['id'])) {
                $frameIds[] = (int)$item['id'];
            }
        }
        $frameIds = array_values(array_filter(array_unique($frameIds)));

        $frameEntityMap = [];
        if (!empty($frameIds)) {
            $in   = implode(',', array_fill(0, count($frameIds), '?'));
            $stmt = $this->pdo->prepare("SELECT id, entity_type, entity_id FROM frames WHERE id IN ($in)");
            $stmt->execute($frameIds);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $frameEntityMap[(int)$row['id']] = [
                    'entity_type' => $row['entity_type'] ?? '',
                    'entity_id'   => (int)($row['entity_id'] ?? 0),
                ];
            }
        }

        $entityRequests = [];
        foreach ($frameEntityMap as $info) {
            if (!empty($info['entity_type']) && !empty($info['entity_id'])) {
                $entityRequests[$info['entity_type']][] = $info['entity_id'];
            }
        }

        $entityNames = [];
        $allowedTables = ['sketches','characters','locations','spawns','generatives','animas',
                          'artifacts','lotations','character_poses','character_anima_poses','character_expressions'];
        foreach ($entityRequests as $eType => $eIds) {
            if (!in_array($eType, $allowedTables)) continue;
            $uniqueIds = array_values(array_unique($eIds));
            $in = implode(',', array_fill(0, count($uniqueIds), '?'));
            try {
                $stmt = $this->pdo->prepare("SELECT id, name FROM `$eType` WHERE id IN ($in)");
                $stmt->execute($uniqueIds);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $entityNames[$eType][(int)$row['id']] = $row['name'] ?? '';
                }
            } catch (\Throwable $e) {}
        }

        $resolveGroupName = function (array $item) use ($frameEntityMap, $entityNames, $post): string {
            $frameId = (int)($item['id'] ?? 0);
            if ($frameId && isset($frameEntityMap[$frameId])) {
                $eType = $frameEntityMap[$frameId]['entity_type'];
                $eId   = $frameEntityMap[$frameId]['entity_id'];
                if (!empty($entityNames[$eType][$eId])) {
                    return $entityNames[$eType][$eId];
                }
            }
            return $item['alt'] ?? ($item['name'] ?? $post['title']);
        };

        $applyPrefix = function (?string $url) use ($urlPrefix): string {
            if (!$url) return '';
            if (preg_match('/^https?:\/\//i', $url)) return $url;
            if (!empty($urlPrefix)) {
                return rtrim($urlPrefix, '/') . '/' . ltrim($url, '/');
            }
            return $url;
        };

        $locations = [];
        $videos    = [];

        foreach ($mediaItems as $item) {
            if ($isVideoItem($item)) {
                $group = $item['group'] ?? ($item['alt'] ?? ($item['name'] ?? ($item['title'] ?? $post['title'])));
                $src   = $item['src'] ?? ($item['url'] ?? '');
                $thumb = $item['thumb'] ?? ($item['thumbnail'] ?? '');
                $videos[$group][] = [
                    'id'        => (int)($item['id'] ?? 0),
                    'title'     => $item['name'] ?? ($item['alt'] ?? ($item['title'] ?? '')),
                    'url'       => $applyPrefix($src),
                    'thumbnail' => $applyPrefix($thumb),
                ];
                if (!isset($locations[$group])) {
                    $locations[$group] = [];
                }
            } else {
                $group = $resolveGroupName($item);
                $src   = $item['src'] ?? '';
                $locations[$group][] = [
                    'src'    => $applyPrefix($src),
                    'width'  => (int)($item['width']  ?? 1024),
                    'height' => (int)($item['height'] ?? 1024),
                    'alt'    => $item['alt'] ?? ($item['name'] ?? ''),
                ];
                if (!isset($videos[$group])) {
                    $videos[$group] = [];
                }
            }
        }

        $videos = array_filter($videos, fn($v) => !empty($v));

        return [$locations, $videos];
    }

    private function buildAnimeGalleryDataScriptTag(array $post, bool $forStaticExport, string $urlPrefix): string
    {
        if ($forStaticExport) {
            $sidecarPath = 'post' . $post['id'] . '/data_' . $post['id'] . '.js';
            return '<script src="' . htmlspecialchars($sidecarPath) . '"></script>';
        }

        [$locations, $videos] = $this->buildAnimeGalleryData($post, $urlPrefix);
        $locJson = json_encode($locations, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        $vidJson = json_encode($videos,    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        return '<script>window.LOCATIONS = ' . $locJson . '; window.VIDEOS = ' . $vidJson . ';</script>';
    }
    
    
    
    
   // ── Narrative Gallery: data assembly ─────────────────────────────────

    public function buildNarrativeGalleryData(array $post, string $urlPrefix = ''): array
    {
        $mediaItems = json_decode($post['media_items'] ?? '[]', true) ?: [];

        if (empty($mediaItems)) {
            return ['name' => $post['title'], 'description' => $post['content'] ?? '', 'frames' => []];
        }

        $frameIds = array_values(array_filter(array_unique(
            array_map(fn($i) => (int)($i['id'] ?? 0), $mediaItems)
        )));

        $frameEntityMap = [];
        if (!empty($frameIds)) {
            $in   = implode(',', array_fill(0, count($frameIds), '?'));
            $stmt = $this->pdo->prepare("SELECT id, entity_type, entity_id FROM frames WHERE id IN ($in)");
            $stmt->execute($frameIds);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $frameEntityMap[(int)$row['id']] = [
                    'entity_type' => $row['entity_type'] ?? '',
                    'entity_id'   => (int)($row['entity_id'] ?? 0),
                ];
            }
        }

        $entityRequests = [];
        foreach ($frameEntityMap as $info) {
            if (!empty($info['entity_type']) && !empty($info['entity_id'])) {
                $entityRequests[$info['entity_type']][] = $info['entity_id'];
            }
        }

        $entityData    = [];
        $allowedTables = ['sketches','characters','locations','spawns','generatives','animas',
                          'artifacts','lotations','character_poses','character_anima_poses','character_expressions'];
        foreach ($entityRequests as $eType => $eIds) {
            if (!in_array($eType, $allowedTables)) continue;
            $uniqueIds = array_values(array_unique($eIds));
            $in = implode(',', array_fill(0, count($uniqueIds), '?'));
            try {
                $stmt = $this->pdo->prepare("SELECT id, name, description FROM `$eType` WHERE id IN ($in)");
                $stmt->execute($uniqueIds);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $entityData[$eType][(int)$row['id']] = [
                        'name'        => $row['name']        ?? '',
                        'description' => $row['description'] ?? '',
                    ];
                }
            } catch (\Throwable $e) {}
        }

        $sketchIds   = array_values(array_unique($entityRequests['sketches'] ?? []));
        $analysisMap = [];
        if (!empty($sketchIds)) {
            $in = implode(',', array_fill(0, count($sketchIds), '?'));
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT sketch_id, classification, thematics, entities, recommendations, scoring
                     FROM sketch_analysis WHERE sketch_id IN ($in)"
                );
                $stmt->execute($sketchIds);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $analysisMap[(int)$row['sketch_id']] = $row;
                }
            } catch (\Throwable $e) {}
        }

        $frames = [];
        foreach ($mediaItems as $item) {
            $frameId = (int)($item['id'] ?? 0);
            $srcRaw  = $item['src'] ?? '';

            $thumb = $srcRaw;
            if (!empty($urlPrefix) && !preg_match('/^https?:\/\//i', $thumb)) {
                $thumb = rtrim($urlPrefix, '/') . '/' . ltrim($thumb, '/');
            }

            $eType = $frameEntityMap[$frameId]['entity_type'] ?? '';
            $eId   = $frameEntityMap[$frameId]['entity_id']   ?? 0;

            $entName = $entityData[$eType][$eId]['name']        ?? '';
            $entDesc = $entityData[$eType][$eId]['description'] ?? '';

            $name = !empty($entName) ? $entName : ($item['alt'] ?? ($item['name'] ?? ''));
            $desc = !empty($entDesc) ? $entDesc : '';

            $curation = [];
            if ($eType === 'sketches' && $eId > 0 && isset($analysisMap[$eId])) {
                $a = $analysisMap[$eId];
                $scoringObj = json_decode($a['scoring'] ?? '{}', true) ?: [];
                $curation = [
                    'class'    => json_decode($a['classification']  ?? '{}', true) ?: [],
                    'themes'   => json_decode($a['thematics']        ?? '{}', true) ?: [],
                    'entities' => json_decode($a['entities']         ?? '{}', true) ?: [],
                    'scoring'  => $scoringObj,
                    'recs'     => json_decode($a['recommendations']  ?? '{}', true) ?: [],
                ];
            }

            $frames[] = [
                'id'               => $frameId,
                '_active_frame_id' => $frameId,
                'thumb'            => $thumb,
                'name'             => $name,
                'desc'             => $desc,
                'curation'         => $curation,
            ];
        }

        return [
            'name'        => $post['title'],
            'description' => $post['content'] ?? '',
            'frames'      => $frames,
        ];
    }

    private function buildNarrativeGalleryDataScriptTag(array $post, bool $forStaticExport, string $urlPrefix): string
    {
        if ($forStaticExport) {
            $sidecarPath = 'post' . $post['id'] . '/data_' . $post['id'] . '.js';
            return '<script src="' . htmlspecialchars($sidecarPath) . '"></script>';
        }

        $seqData = $this->buildNarrativeGalleryData($post, $urlPrefix);
        $json    = json_encode($seqData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        return '<script>const sequenceData = ' . $json . '; const rawExportData = {};</script>';
    }
    
    
    // ── Spatial Viewer: data assembly ─────────────────────────────────────

    public function buildSpatialViewerData(array $post, string $urlPrefix = ''): array
    {
        $mediaItems = json_decode($post['media_items'] ?? '[]', true) ?: [];

        if (empty($mediaItems)) {
            return ['id' => (int)$post['id'], 'name' => $post['title'], 'description' => $post['content'] ?? '', 'items' => []];
        }

        $frameIds = array_values(array_filter(array_unique(
            array_map(fn($i) => (int)($i['id'] ?? 0), $mediaItems)
        )));

        $frameEntityMap = [];
        if (!empty($frameIds)) {
            $in   = implode(',', array_fill(0, count($frameIds), '?'));
            $stmt = $this->pdo->prepare("SELECT id, entity_type, entity_id FROM frames WHERE id IN ($in)");
            $stmt->execute($frameIds);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $frameEntityMap[(int)$row['id']] = [
                    'entity_type' => $row['entity_type'] ?? '',
                    'entity_id'   => (int)($row['entity_id'] ?? 0),
                ];
            }
        }

        $entityRequests = [];
        foreach ($frameEntityMap as $info) {
            if (!empty($info['entity_type']) && !empty($info['entity_id'])) {
                $entityRequests[$info['entity_type']][] = $info['entity_id'];
            }
        }

        $entityNames   = [];
        $allowedTables = ['sketches','characters','locations','spawns','generatives','animas',
                          'artifacts','lotations','character_poses','character_anima_poses','character_expressions'];
        foreach ($entityRequests as $eType => $eIds) {
            if (!in_array($eType, $allowedTables)) continue;
            $uniqueIds = array_values(array_unique($eIds));
            $in = implode(',', array_fill(0, count($uniqueIds), '?'));
            try {
                $stmt = $this->pdo->prepare("SELECT id, name FROM `$eType` WHERE id IN ($in)");
                $stmt->execute($uniqueIds);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $entityNames[$eType][(int)$row['id']] = $row['name'] ?? '';
                }
            } catch (\Throwable $e) {}
        }

        $sketchIds   = array_values(array_unique($entityRequests['sketches'] ?? []));
        $analysisMap = [];
        if (!empty($sketchIds)) {
            $in = implode(',', array_fill(0, count($sketchIds), '?'));
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT sketch_id, classification, thematics, entities, recommendations, scoring
                     FROM sketch_analysis WHERE sketch_id IN ($in)"
                );
                $stmt->execute($sketchIds);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $analysisMap[(int)$row['sketch_id']] = $row;
                }
            } catch (\Throwable $e) {}
        }

        $items = [];
        foreach ($mediaItems as $item) {
            $frameId = (int)($item['id'] ?? 0);
            $srcRaw  = $item['src'] ?? '';

            $thumb = $srcRaw;
            if (!empty($urlPrefix) && !preg_match('/^https?:\/\//i', $thumb)) {
                $thumb = rtrim($urlPrefix, '/') . '/' . ltrim($thumb, '/');
            }

            $eType = $frameEntityMap[$frameId]['entity_type'] ?? '';
            $eId   = $frameEntityMap[$frameId]['entity_id']   ?? 0;
            $name  = $entityNames[$eType][$eId] ?? ($item['alt'] ?? ($item['name'] ?? ''));

            $curation = [];
            if ($eType === 'sketches' && $eId > 0 && isset($analysisMap[$eId])) {
                $a = $analysisMap[$eId];
                $scoringObj = json_decode($a['scoring'] ?? '{}', true) ?: [];
                $curation = [
                    'class'    => json_decode($a['classification']  ?? '{}', true) ?: [],
                    'themes'   => json_decode($a['thematics']        ?? '{}', true) ?: [],
                    'entities' => json_decode($a['entities']         ?? '{}', true) ?: [],
                    'scoring'  => $scoringObj,
                    'recs'     => json_decode($a['recommendations']  ?? '{}', true) ?: [],
                ];
            }

            $items[] = [
                'id'       => $frameId,
                'name'     => $name,
                'thumb'    => $thumb,
                'curation' => $curation,
            ];
        }

        return [
            'id'          => (int)$post['id'],
            'name'        => $post['title'],
            'description' => $post['content'] ?? '',
            'items'       => $items,
        ];
    }

    private function buildSpatialViewerDataScriptTag(array $post, bool $forStaticExport, string $urlPrefix): string
    {
        if ($forStaticExport) {
            $sidecarPath = 'post' . $post['id'] . '/data_' . $post['id'] . '.js';
            return '<script src="' . htmlspecialchars($sidecarPath) . '"></script>';
        }

        $spatialData = $this->buildSpatialViewerData($post, $urlPrefix);
        $json        = json_encode($spatialData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        return '<script>const seqData = ' . $json . ';</script>';
    }

    private function applyUrlPrefix(?string $url, string $prefix, bool $makeRelativeForGrid = false): ?string {
        if (!$url) return $url;
        if (preg_match('/^https?:\/\//i', $url)) return $url;
        if (!empty($prefix)) {
            return rtrim($prefix, '/') . '/' . ltrim($url, '/');
        }
        if ($makeRelativeForGrid) {
            if (str_starts_with($url, '/content_hub/')) {
                return substr($url, 13);
            } else if (str_starts_with($url, '/')) {
                return '..' . $url;
            }
        }
        return $url;
    }




    
   
    // ── Calendar ───────────────────────────────────────────────────────────

    public function getCalendarData(int $year, int $month): array
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));

        $stmt = $this->pdo->prepare(
            "SELECT id, title, status, platform, platforms_json,
                    COALESCE(scheduled_at, created_at) AS event_date
             FROM content_hub_posts
             WHERE DATE(COALESCE(scheduled_at, created_at)) BETWEEN :from AND :to
             ORDER BY event_date ASC"
        );
        $stmt->execute([':from' => $from, ':to' => $to]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $calendar = [];
        foreach ($rows as $row) {
            $day = substr($row['event_date'], 0, 10);
            $calendar[$day][] = [
                'id'       => (int)$row['id'],
                'title'    => $row['title'],
                'status'   => $row['status'],
                'platform' => $row['platform'],
                'platforms_json' => $row['platforms_json'],
            ];
        }

        return ['success' => true, 'calendar' => $calendar, 'year' => $year, 'month' => $month];
    }

    // ── Dashboard stats ────────────────────────────────────────────────────

    public function getDashboardStats(): array
    {
        $counts = $this->pdo->query(
            "SELECT status, COUNT(*) as cnt FROM content_hub_posts GROUP BY status"
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        $rows = $this->pdo->query(
            "SELECT platform, platforms_json FROM content_hub_posts"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $byPlatform = [];
        foreach ($rows as $row) {
            $platforms = [];
            if (!empty($row['platforms_json'])) {
                $decoded = json_decode($row['platforms_json'], true);
                if (is_array($decoded) && count($decoded) > 0) {
                    $platforms = $decoded;
                }
            }
            if (empty($platforms) && !empty($row['platform'])) {
                $platforms = [$row['platform']];
            }
            foreach ($platforms as $p) {
                $p = trim($p);
                if ($p === '') continue;
                $byPlatform[$p] = ($byPlatform[$p] ?? 0) + 1;
            }
        }

        $total = (int)$this->pdo->query("SELECT COUNT(*) FROM content_hub_posts")->fetchColumn();

        $framesCount     = $this->safeCount("SELECT COUNT(*) FROM frames");
        $videosCount     = $this->safeCount("SELECT COUNT(*) FROM videos");
        $storyboardCount = $this->safeCount("SELECT COUNT(*) FROM storyboards");

        return [
            'success'          => true,
            'stats'            => [
                'total'            => $total,
                'draft'            => (int)($counts['draft']     ?? 0),
                'scheduled'        => (int)($counts['scheduled'] ?? 0),
                'published'        => (int)($counts['published'] ?? 0),
                'archived'         => (int)($counts['archived']  ?? 0),
                'by_platform'      => $byPlatform ?: [],
                'frames_count'     => $framesCount,
                'videos_count'     => $videosCount,
                'storyboards_count'=> $storyboardCount,
            ]
        ];
    }
    
    
    
    
    
       // ── Asset browsing ─────────────────────────────────────────────────────

    public function searchContainers(string $type, string $q, int $page, int $limit = 3): array {
        $offset = max(0, ($page - 1) * $limit);
        $items = [];
        $total = 0;

        if ($type === 'map_runs') {
            $where = "1=1";
            $params = [];
            if ($q) { 
                $where .= " AND (note LIKE ? OR id = ?)"; 
                $params = ["%$q%", (int)$q]; 
            }
            
            $stmtC = $this->pdo->prepare("SELECT COUNT(*) FROM map_runs WHERE $where");
            $stmtC->execute($params);
            $total = (int)$stmtC->fetchColumn();

            $stmt = $this->pdo->prepare("
                SELECT id, note as name, created_at as meta,
                       (SELECT COUNT(*) FROM frames WHERE map_run_id = map_runs.id) as frame_count 
                FROM map_runs 
                WHERE $where 
                ORDER BY id DESC LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } elseif ($type === 'storyboards') {
            $where = "is_archived = 0";
            $params = [];
            if ($q) { 
                $where .= " AND (name LIKE ? OR id = ?)"; 
                $params = ["%$q%", (int)$q]; 
            }
            
            $stmtC = $this->pdo->prepare("SELECT COUNT(*) FROM storyboards WHERE $where");
            $stmtC->execute($params);
            $total = (int)$stmtC->fetchColumn();

            $stmt = $this->pdo->prepare("
                SELECT id, name, custom_tag as meta,
                       (SELECT COUNT(*) FROM storyboard_frames WHERE storyboard_id = storyboards.id) as frame_count
                FROM storyboards 
                WHERE $where 
                ORDER BY updated_at DESC LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } elseif ($type === 'videos') {
            $where = "m.entity_type = 'animatics'";
            $params = [];
            if ($q) {
                $where .= " AND (m.note LIKE ? OR m.id = ?)";
                $params = ["%$q%", (int)$q];
            }

            $stmtC = $this->pdo->prepare(
                "SELECT COUNT(DISTINCT m.id) FROM map_runs m
                 INNER JOIN videos v ON m.id = v.map_run_id
                 WHERE $where"
            );
            $stmtC->execute($params);
            $total = (int)$stmtC->fetchColumn();

            $stmt = $this->pdo->prepare("
                SELECT m.id, m.note as name, m.created_at as meta,
                       COUNT(v.id) as frame_count
                FROM map_runs m
                INNER JOIN videos v ON m.id = v.map_run_id
                WHERE $where
                GROUP BY m.id
                ORDER BY m.id DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [
            'success' => true, 
            'items'   => $items, 
            'total'   => $total, 
            'page'    => $page,
            'pages'   => max(1, ceil($total / $limit))
        ];
    }

    public function getContainerFrames(string $type, int $containerId): array {
        if ($type === 'map_runs') {
            $stmt = $this->pdo->prepare("SELECT id, name, filename FROM frames WHERE map_run_id = ? ORDER BY id ASC");
            $stmt->execute([$containerId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return array_map(fn($r) => [
                'id' => $r['id'],
                'name' => $r['name'],
                'url' => $this->frameUrl($r['filename'])
            ], $rows);
        } elseif ($type === 'storyboards') {
            $stmt = $this->pdo->prepare("
                SELECT sf.frame_id as id, sf.name, sf.filename 
                FROM storyboard_frames sf 
                WHERE sf.storyboard_id = ? 
                ORDER BY sf.sort_order ASC
            ");
            $stmt->execute([$containerId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return array_map(fn($r) => [
                'id' => $r['id'],
                'name' => $r['name'],
                'url' => $this->frameUrl($r['filename'])
            ], $rows);
        }
        return [];
    }

    
    
    
   public function searchAssets(string $type, string $query = '', int $mapRunId = 0): array
    {
        $assets = match($type) {
            'videos'   => $this->getVideoAssets($query, $mapRunId),
            'sketches' => $this->getSketchAssets($query),
            default    => [],
        };

        return ['success' => true, 'assets' => $assets, 'type' => $type];
    }

    private function getVideoAssets(string $q, int $mapRunId = 0): array
    {
        try {
            $sql = "SELECT id, name, url, thumbnail, duration, type, created_at FROM videos";
            $conditions = [];
            $params = [];

            if ($mapRunId > 0) {
                $conditions[] = "map_run_id = :map_run_id";
                $params[':map_run_id'] = $mapRunId;
            }
            if ($q) {
                $conditions[] = "name LIKE :q";
                $params[':q'] = "%$q%";
            }
            if ($conditions) {
                $sql .= " WHERE " . implode(' AND ', $conditions);
            }
            $sql .= " ORDER BY id DESC LIMIT 200";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return array_map(fn($r) => [
                'id'        => $r['id'],
                'name'      => $r['name'],
                'url'       => $this->frameUrl($r['url']),
                'thumb'     => $r['thumbnail'] ? $this->frameUrl($r['thumbnail']) : $this->frameUrl($r['url']),
                'src'       => $this->frameUrl($r['url']),
                'title'     => $r['name'],
                'thumbnail' => $r['thumbnail'] ? $this->frameUrl($r['thumbnail']) : '',
                'type'      => $r['type'] ?: 'video/mp4',
                'duration'  => (int)($r['duration'] ?? 0),
                'created_at'=> $r['created_at'] ?? '',
                'alt'       => $r['name'],
            ], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function getSketchAssets(string $q): array
    {
        try {
            $sql = "SELECT s.id, s.name,
                           f.filename
                    FROM sketches s
                    LEFT JOIN frames_2_sketches fs ON fs.to_id = s.id
                    LEFT JOIN frames f ON f.id = fs.from_id
                    GROUP BY s.id";
            $params = [];
            if ($q) {
                $sql .= " HAVING s.name LIKE :q OR MIN(s.description) LIKE :q2";
                $params = [':q' => "%$q%", ':q2' => "%$q%"];
            }
            $sql .= " ORDER BY s.id DESC LIMIT 60";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return array_map(fn($r) => [
                'id'   => $r['id'],
                'name' => $r['name'],
                'url'  => $r['filename'] ? $this->frameUrl($r['filename']) : '',
            ], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function frameUrl(string $filename): string
    {
        return str_starts_with($filename, '/') ? $filename : '/' . $filename;
    }

    private function safeCount(string $sql): int
    {
        try {
            return (int)$this->pdo->query($sql)->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return $text ?: 'post-' . time();
    }

    private function uniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $base   = $slug;
        $suffix = 0;

        do {
            $check = $suffix ? "{$base}-{$suffix}" : $base;
            $sql   = "SELECT id FROM content_hub_posts WHERE slug = :slug" . ($excludeId ? " AND id != :id" : "");
            $stmt  = $this->pdo->prepare($sql);
            $stmt->bindValue(':slug', $check);
            if ($excludeId) $stmt->bindValue(':id', $excludeId, \PDO::PARAM_INT);
            $stmt->execute();
            $exists = (bool)$stmt->fetchColumn();
            $suffix++;
        } while ($exists);

        return $check;
    }

    private function sanitiseJson(string $raw): string
    {
        $raw = trim($raw);
        if (empty($raw)) return '[]';
        if (substr($raw, -1) === ';') $raw = substr($raw, 0, -1);
        if (json_decode($raw) !== null) return $raw;
        $json = preg_replace('/([{,]\s*)(\w+)(\s*:)/', '$1"$2"$3', $raw);
        $json = str_replace("'", '"', $json);
        return json_decode($json) !== null ? $json : '[]';
    }
}


