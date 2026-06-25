<?php
/**
 * SAGE Pytoon Manager
 * src/Pytoon/PytoonManager.php
 *
 * Webtoon packaging pipeline:
 *   - Lists cinemagic_series, cinemagic_hub_posts, plus any raw PDF files in the media folder.
 *   - Tracks cover composition jobs and PDF-split jobs.
 *   - Sends files to PyAPI /pytoon/* endpoints and polls for results.
 *   - Manages configurable canvas sizes stored in pytoon_canvas_sizes.
 */

namespace App\Pytoon;

class PytoonManager
{
    private \PDO $pdo;
    private string $pyapiUrl;
    private string $publicPathAbs;

    // Where uploaded / generated webtoon assets live
    private const WEBTOON_DIR = 'media/webtoon';
    private const PDF_INBOX   = 'media/webtoon/pdf_inbox';

    public function __construct(\PDO $pdo, string $pyapiUrl, string $publicPathAbs)
    {
        $this->pdo           = $pdo;
        $this->pyapiUrl      = rtrim($pyapiUrl, '/');
        $this->publicPathAbs = rtrim($publicPathAbs, '/');
        $this->ensureTables();
        $this->ensureDirs();
    }

    // ── Schema bootstrap ─────────────────────────────────────────────────────

    private function ensureTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `pytoon_jobs` (
              `id`           int(11)      NOT NULL AUTO_INCREMENT,
              `job_type`     enum('pdf_split','cover_compose') NOT NULL,
              `label`        varchar(255) NOT NULL DEFAULT '',
              `status`       enum('pending','processing','done','error') NOT NULL DEFAULT 'pending',
              `pyapi_job_id` varchar(64)  DEFAULT NULL,
              `source_ref`   varchar(512) DEFAULT NULL COMMENT 'series/post ID or raw PDF path that spawned this job',
              `result_zip`   varchar(512) DEFAULT NULL,
              `page_count`   int(11)      NOT NULL DEFAULT 0,
              `error_msg`    text         DEFAULT NULL,
              `created_at`   timestamp    NOT NULL DEFAULT current_timestamp(),
              `updated_at`   timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_pj_status` (`status`),
              KEY `idx_pj_type`   (`job_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Canvas sizes table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `pytoon_canvas_sizes` (
              `id`         int(11)      NOT NULL AUTO_INCREMENT,
              `label`      varchar(100) NOT NULL DEFAULT '',
              `width`      int(11)      NOT NULL DEFAULT 1080,
              `height`     int(11)      NOT NULL DEFAULT 1920,
              `sort_order` int(11)      NOT NULL DEFAULT 0,
              `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Seed default sizes if table is empty
        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM pytoon_canvas_sizes")->fetchColumn();
        if ($count === 0) {
            $this->pdo->exec("
                INSERT INTO pytoon_canvas_sizes (label, width, height, sort_order) VALUES
                ('Webtoon (1080 × 1920)', 1080, 1920, 0),
                ('Tapas (960 × 1440)',    960,  1440, 1);
            ");
        }
    }

    private function ensureDirs(): void
    {
        $dirs = [
            self::WEBTOON_DIR,
            self::PDF_INBOX,
            'media/webtoon/covers',
            'media/webtoon/splits',
        ];
        foreach ($dirs as $d) {
            $abs = $this->publicPathAbs . '/' . $d;
            if (!is_dir($abs)) {
                @mkdir($abs, 0777, true);
            }
        }
    }

    // ── Canvas sizes ─────────────────────────────────────────────────────────

    public function getCanvasSizes(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, label, width, height, sort_order
               FROM pytoon_canvas_sizes
              ORDER BY sort_order ASC, id ASC"
        );
        return ['success' => true, 'sizes' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    public function addCanvasSize(string $label, int $width, int $height): array
    {
        if ($width < 1 || $height < 1) {
            return ['success' => false, 'error' => 'Width and height must be positive integers'];
        }
        $maxOrder = (int)$this->pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM pytoon_canvas_sizes")->fetchColumn();
        $stmt = $this->pdo->prepare(
            "INSERT INTO pytoon_canvas_sizes (label, width, height, sort_order) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([trim($label), $width, $height, $maxOrder + 1]);
        return ['success' => true, 'id' => (int)$this->pdo->lastInsertId()];
    }

    public function deleteCanvasSize(int $id): array
    {
        // Prevent deleting the last size
        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM pytoon_canvas_sizes")->fetchColumn();
        if ($count <= 1) {
            return ['success' => false, 'error' => 'Cannot delete the only remaining canvas size'];
        }
        $stmt = $this->pdo->prepare("DELETE FROM pytoon_canvas_sizes WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true];
    }

    // ── Dashboard data ───────────────────────────────────────────────────────

    /**
     * Returns all published cinemagic series for the left-panel listing.
     */
    public function getSeriesList(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, title, status, cover_image_url, sort_order
               FROM cinemagic_series
              ORDER BY sort_order DESC, id DESC"
        );
        return ['success' => true, 'series' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    /**
     * Returns all PDF files found in the PDF inbox directory.
     */
    public function getPdfInboxList(): array
    {
        $inboxAbs = $this->publicPathAbs . '/' . self::PDF_INBOX;
        $pdfs     = [];
        if (is_dir($inboxAbs)) {
            foreach (glob($inboxAbs . '/*.pdf') ?: [] as $path) {
                $pdfs[] = [
                    'filename' => basename($path),
                    'rel_path' => self::PDF_INBOX . '/' . basename($path),
                    'size'     => filesize($path),
                    'mtime'    => filemtime($path),
                ];
            }
        }
        return ['success' => true, 'pdfs' => $pdfs];
    }

    /**
     * Returns episodes (sequences) attached to a series — used for PDF export picker.
     */
    public function getSeriesEpisodes(int $seriesId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ns.id, ns.name, c.name AS season_name, sc.cinemagic_id,
                   cs.cover_image_url AS ep_cover
              FROM cinemagic_series_2_cinemagics sc
              JOIN cinemagics_2_sequences cs ON cs.cinemagic_id = sc.cinemagic_id
              JOIN narrative_sequences ns ON ns.id = cs.sequence_id
              JOIN cinemagics c ON c.id = sc.cinemagic_id
             WHERE sc.series_id = ?
             ORDER BY sc.sort_order ASC, cs.sort_order ASC
        ");
        $stmt->execute([$seriesId]);
        return ['success' => true, 'episodes' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    /**
     * Returns cover image URL for a series (absolute local path for display).
     */
    public function getSeriesCoverUrl(int $seriesId): string
    {
        $stmt = $this->pdo->prepare("SELECT cover_image_url FROM cinemagic_series WHERE id = ?");
        $stmt->execute([$seriesId]);
        $cover = $stmt->fetchColumn();
        if (!$cover) return '';
        return str_starts_with($cover, '/') ? $cover : '/' . $cover;
    }

    /**
     * Returns actual physical PDF files for a given series, ensuring we only 
     * return the absolute latest generated files without DB job duplicates.
     */
    public function getCinemagicPdfsForSeries(int $seriesId): array
    {
        $pdfDir = $this->publicPathAbs . "/media/magazines/series_{$seriesId}";
        if (!is_dir($pdfDir)) {
            return ['success' => true, 'pdfs' => []];
        }

        $pdfs = [];
        // Scan directory for all PDFs
        foreach (glob($pdfDir . '/*.pdf') ?: [] as $absPath) {
            $filename = basename($absPath);
            // Expected format from Cinemagic Hub: magazine_seq{seqId}_{lang}.pdf
            if (preg_match('/^magazine_seq(\d+)_([a-zA-Z0-9_-]+)\.pdf$/', $filename, $matches)) {
                $seqId = (int)$matches[1];
                $lang  = $matches[2];

                // Fetch real sequence name from DB
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
        
        // Sort by modification time descending (newest files first)
        usort($pdfs, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

        return ['success' => true, 'pdfs' => $pdfs];
    }

    // ── Job management ───────────────────────────────────────────────────────

    public function getJobs(int $limit = 30): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM pytoon_jobs ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return ['success' => true, 'jobs' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    public function createJob(string $type, string $label, string $sourceRef = ''): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO pytoon_jobs (job_type, label, status, source_ref)
             VALUES (?, ?, 'pending', ?)"
        );
        $stmt->execute([$type, $label, $sourceRef]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateJobPyapiId(int $jobId, string $pyapiJobId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE pytoon_jobs SET pyapi_job_id = ?, status = 'processing' WHERE id = ?"
        );
        $stmt->execute([$pyapiJobId, $jobId]);
    }

    public function updateJobStatus(int $jobId, string $status, array $extra = []): void
    {
        $sets   = ['status = ?'];
        $params = [$status];

        if (isset($extra['error_msg'])) { $sets[] = 'error_msg = ?'; $params[] = $extra['error_msg']; }
        if (isset($extra['page_count'])) { $sets[] = 'page_count = ?'; $params[] = (int)$extra['page_count']; }
        if (isset($extra['result_zip'])) { $sets[] = 'result_zip = ?'; $params[] = $extra['result_zip']; }

        $params[] = $jobId;
        $this->pdo->prepare("UPDATE pytoon_jobs SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
    }

    // ── PyAPI proxy helpers ──────────────────────────────────────────────────

    /**
     * Submit a PDF file (by absolute path on server) to PyAPI for splitting.
     * Returns ['success', 'job_db_id', 'pyapi_job_id'] or ['success' => false, 'error'].
     */
    public function submitPdfSplitJob(string $absPath, string $label, int $dpi = 150, int $quality = 88): array
    {
        if (!file_exists($absPath)) {
            return ['success' => false, 'error' => 'File not found: ' . basename($absPath)];
        }

        $jobDbId = $this->createJob('pdf_split', $label, $absPath);

        $boundary = '----SAGEPytoon' . bin2hex(random_bytes(12));
        $body     = '';

        // PDF file
        $mime    = 'application/pdf';
        $content = file_get_contents($absPath);
        $body   .= "--{$boundary}\r\n";
        $body   .= "Content-Disposition: form-data; name=\"file\"; filename=\"" . basename($absPath) . "\"\r\n";
        $body   .= "Content-Type: {$mime}\r\n\r\n";
        $body   .= $content . "\r\n";

        // Form fields
        foreach (['dpi' => $dpi, 'quality' => $quality] as $k => $v) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$k}\"\r\n\r\n";
            $body .= $v . "\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->pyapiUrl . '/pytoon/pdf/split',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                "Content-Type: multipart/form-data; boundary={$boundary}",
                "Content-Length: " . strlen($body),
            ],
        ]);

        $resp    = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            $this->updateJobStatus($jobDbId, 'error', ['error_msg' => $curlErr]);
            return ['success' => false, 'error' => 'cURL: ' . $curlErr];
        }
        if ($code >= 400) {
            $detail = json_decode($resp, true)['detail'] ?? $resp;
            $this->updateJobStatus($jobDbId, 'error', ['error_msg' => "HTTP {$code}: {$detail}"]);
            return ['success' => false, 'error' => "PyAPI HTTP {$code}: {$detail}"];
        }

        $data = json_decode($resp, true);
        if (empty($data['job_id'])) {
            $this->updateJobStatus($jobDbId, 'error', ['error_msg' => 'No job_id in response']);
            return ['success' => false, 'error' => 'Invalid PyAPI response'];
        }

        $this->updateJobPyapiId($jobDbId, $data['job_id']);
        return ['success' => true, 'job_db_id' => $jobDbId, 'pyapi_job_id' => $data['job_id']];
    }

    /**
     * Poll PyAPI job status and sync to DB.
     */
    public function pollPdfJob(int $jobDbId, string $pyapiJobId): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->pyapiUrl . '/pytoon/pdf/status/' . $pyapiJobId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            return ['success' => false, 'error' => "HTTP {$code}"];
        }

        $data   = json_decode($resp, true) ?: [];
        $status = $data['status'] ?? 'processing';
        $extra  = [];

        if (!empty($data['error'])) $extra['error_msg'] = $data['error'];
        if (!empty($data['page_count'])) $extra['page_count'] = $data['page_count'];

        if ($status === 'done') {
            // Download and store ZIP
            $zipResult = $this->fetchAndStoreZip($jobDbId, $pyapiJobId);
            if ($zipResult) $extra['result_zip'] = $zipResult;
        }

        $this->updateJobStatus($jobDbId, $status, $extra);
        return ['success' => true, 'status' => $status, 'page_count' => $data['page_count'] ?? 0];
    }

    /**
     * Download the finished ZIP from PyAPI and store it locally.
     * Returns the relative web-accessible path or null on failure.
     */
    private function fetchAndStoreZip(int $jobDbId, string $pyapiJobId): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->pyapiUrl . '/pytoon/pdf/download/' . $pyapiJobId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$data || $code >= 400) return null;

        $outDir = $this->publicPathAbs . '/media/webtoon/splits';
        @mkdir($outDir, 0777, true);
        $filename = "split_job{$jobDbId}.zip";
        $absOut   = $outDir . '/' . $filename;
        file_put_contents($absOut, $data);

        // Tell PyAPI to free its temp storage
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->pyapiUrl . '/pytoon/pdf/cleanup/' . $pyapiJobId,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        curl_exec($ch);
        curl_close($ch);

        return 'media/webtoon/splits/' . $filename;
    }

    /**
     * Handle an uploaded PDF (multipart from browser) — save to inbox then dispatch.
     */
    public function handleUploadedPdf(array $fileInfo, int $dpi, int $quality): array
    {
        $inboxAbs = $this->publicPathAbs . '/' . self::PDF_INBOX;
        $name     = preg_replace('/[^a-zA-Z0-9_.\-]/', '_', basename($fileInfo['name']));
        $dest     = $inboxAbs . '/' . time() . '_' . $name;

        if (!move_uploaded_file($fileInfo['tmp_name'], $dest)) {
            return ['success' => false, 'error' => 'Could not save uploaded file'];
        }

        return $this->submitPdfSplitJob($dest, 'Uploaded: ' . $name, $dpi, $quality);
    }

    /**
     * Re-process an existing inbox PDF.
     */
    public function reprocessInboxPdf(string $relPath, int $dpi, int $quality): array
    {
        // Security: strip traversal
        $clean = ltrim(str_replace(['..', '//'], ['', '/'], $relPath), '/');
        $abs   = $this->publicPathAbs . '/' . $clean;
        $label = 'Re-process: ' . basename($abs);
        return $this->submitPdfSplitJob($abs, $label, $dpi, $quality);
    }

    /**
     * Split an existing Cinemagic PDF (from media/magazines/) into webtoon pages.
     * relPath must start with media/magazines/
     */
    public function splitCinemagicPdf(string $relPath, int $dpi, int $quality): array
    {
        $clean = ltrim(str_replace(['..', '//'], ['', '/'], $relPath), '/');
        if (!str_starts_with($clean, 'media/magazines/')) {
            return ['success' => false, 'error' => 'Invalid path — must be under media/magazines/'];
        }
        $abs   = $this->publicPathAbs . '/' . $clean;
        $label = 'Cinemagic PDF: ' . basename($abs);
        return $this->submitPdfSplitJob($abs, $label, $dpi, $quality);
    }

    /**
     * Compose a cover: proxy multipart upload to PyAPI, return the JPEG bytes
     * (caller is responsible for streaming to browser or saving to disk).
     */
    public function composeCoverFromUpload(array $fileInfo, float $x, float $y, float $scale, int $canvasW, int $canvasH, int $quality): array
    {
        $srcPath = $fileInfo['tmp_name'];
        if (!file_exists($srcPath)) {
            return ['success' => false, 'error' => 'Upload tmp file missing'];
        }

        $boundary = '----SAGECoverCompose' . bin2hex(random_bytes(10));
        $body     = '';

        $mime    = mime_content_type($srcPath) ?: 'image/jpeg';
        $content = file_get_contents($srcPath);
        $body   .= "--{$boundary}\r\n";
        $body   .= "Content-Disposition: form-data; name=\"file\"; filename=\"cover_src.jpg\"\r\n";
        $body   .= "Content-Type: {$mime}\r\n\r\n";
        $body   .= $content . "\r\n";

        foreach (['x' => $x, 'y' => $y, 'scale' => $scale, 'canvas_w' => $canvasW, 'canvas_h' => $canvasH, 'quality' => $quality] as $k => $v) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$k}\"\r\n\r\n";
            $body .= $v . "\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->pyapiUrl . '/pytoon/cover/compose',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                "Content-Type: multipart/form-data; boundary={$boundary}",
                "Content-Length: " . strlen($body),
            ],
        ]);
        $resp    = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ctype   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) return ['success' => false, 'error' => 'cURL: ' . $curlErr];
        if ($code >= 400) return ['success' => false, 'error' => "PyAPI HTTP {$code}"];
        if (!str_starts_with($ctype ?? '', 'image/')) return ['success' => false, 'error' => 'PyAPI did not return image'];

        return ['success' => true, 'jpeg_bytes' => $resp, 'content_type' => $ctype];
    }

    /**
     * Save composed cover to disk and return the relative URL.
     */
    public function saveComposedCover(string $jpegBytes, string $label, int $canvasW = 1080, int $canvasH = 1920): string
    {
        $dir      = $this->publicPathAbs . '/media/webtoon/covers';
        @mkdir($dir, 0777, true);
        $slug     = preg_replace('/[^a-z0-9]+/', '_', strtolower($label)) ?: 'cover';
        $filename = $slug . '_' . time() . "_{$canvasW}x{$canvasH}.jpg";
        file_put_contents($dir . '/' . $filename, $jpegBytes);
        return 'media/webtoon/covers/' . $filename;
    }

    /**
     * List previously saved covers.
     */
    public function getSavedCovers(): array
    {
        $dir  = $this->publicPathAbs . '/media/webtoon/covers';
        $list = [];
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.jpg') ?: [] as $path) {
                $list[] = [
                    'filename' => basename($path),
                    'url'      => '/media/webtoon/covers/' . basename($path),
                    'size'     => filesize($path),
                    'mtime'    => filemtime($path),
                ];
            }
            usort($list, fn($a, $b) => $b['mtime'] - $a['mtime']);
        }
        return ['success' => true, 'covers' => $list];
    }

    /**
     * Delete a file from the covers or splits directory.
     */
    public function deleteAsset(string $relPath): array
    {
        $clean = ltrim(str_replace('..', '', $relPath), '/');
        // Only allow deleting from webtoon subdirs
        if (!str_starts_with($clean, 'media/webtoon/')) {
            return ['success' => false, 'error' => 'Invalid path'];
        }
        $abs = $this->publicPathAbs . '/' . $clean;
        if (file_exists($abs)) {
            unlink($abs);
        }
        return ['success' => true];
    }
}

