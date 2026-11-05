<?php
// public/gdriver.php
require_once "bootstrap.php";
$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// --- 1. Database Schema & Migration Setup ---
function ensureGdlSchemaAndSeed(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `gdl_documents` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `filename` VARCHAR(255) NOT NULL,
            `title` VARCHAR(255) NOT NULL DEFAULT 'Google Drive Archive',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `gdl_series` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `document_id` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `cover_url` TEXT,
            `sort_order` INT NOT NULL DEFAULT 0,
            `is_mature` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (`document_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `gdl_links` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `series_id` INT NOT NULL,
            `label` VARCHAR(255) NOT NULL,
            `gdrive_id` VARCHAR(255) NOT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (`series_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Ensure is_mature column exists if table was already created
    try {
        $pdo->exec("ALTER TABLE `gdl_series` ADD COLUMN `is_mature` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sort_order`");
    } catch (Exception $e) {
        // Column already exists
    }

    $count = (int)$pdo->query("SELECT COUNT(*) FROM `gdl_documents`")->fetchColumn();
    if ($count === 0) {
        $stmtDoc = $pdo->prepare("INSERT INTO `gdl_documents` (filename, title) VALUES (?, ?)");
        $stmtDoc->execute(['download.html', 'Google Drive Archive — The Anima Chronicles']);
        $docId = (int)$pdo->lastInsertId();

        $seed = [
            [
                'title' => 'D PRAINE',
                'cover' => 'https://raw.githubusercontent.com/sebastianpw/sg_showcase_01/main/cinemagic_hub/d-praine/assets/frame0074332.jpg',
                'is_mature' => 0,
                'links' => [
                    ['label' => 'S1 EP01-06', 'id' => '188BIR05DOjA0ZbcdRSQkJI18QX7OakVf'],
                    ['label' => 'S1 EP07-10', 'id' => '1vUUZsx82u-UcNFpI8_UWge9tIdYd5MmV'],
                    ['label' => 'S2 EP01-06', 'id' => '1IR4N2QN700K-os2orh5DmPZIJ9-Mhbp9'],
                    ['label' => 'S2 EP07-09', 'id' => '1KhEzkPp71BkKzVgiSwtM0h0SUupit_zY']
                ]
            ],
            [
                'title' => 'GINORA',
                'cover' => 'https://raw.githubusercontent.com/sebastianpw/sg_showcase_01/main/cinemagic_hub/cover-publishing/assets/frame0079729.jpg',
                'is_mature' => 0,
                'links' => [
                    ['label' => 'S1 Episode 1-5', 'id' => '1OxLr2t4oizqVYP8PdvOBc3tZ_z4pqO5Q'],
                    ['label' => 'Ginidex', 'id' => '1fC2U8F_Nwyi98fZM-QK2A6YveTydMM3s']
                ]
            ],
            [
                'title' => 'PIRATES!!',
                'cover' => 'https://raw.githubusercontent.com/sebastianpw/sg_showcase_01/main/cinemagic_hub/cover-publishing/assets/frame0079730.jpg',
                'is_mature' => 0,
                'links' => [
                    ['label' => 'Comic Book Showcase', 'id' => '1Je6hWcSc4CnavwmVxxMmN93tHedN5nBz']
                ]
            ],
            [
                'title' => 'STROBACHROME',
                'cover' => 'https://raw.githubusercontent.com/sebastianpw/sg_showcase_01/main/cinemagic_hub/cover-publishing/assets/frame0078408.jpg',
                'is_mature' => 0,
                'links' => [
                    ['label' => 'Species', 'id' => '1gDatzOqoGJlytg11xQi4HSutqXR9NW76'],
                    ['label' => 'Cast', 'id' => '1L5tu-iXifZyhlb-06jhaarDB3Gb73K42'],
                    ['label' => 'Atlas', 'id' => '1lSsfKcp631mJ7myjeSN0uqeABIteHeBZ']
                ]
            ],
            [
                'title' => 'RED LATITUDE',
                'cover' => 'https://raw.githubusercontent.com/sebastianpw/sg_showcase_01/main/cinemagic_hub/red-latitude/assets/frame0074440.jpg',
                'is_mature' => 0,
                'links' => [
                    ['label' => 'S1 Pilot', 'id' => '1f-1XuWnEfgTZVyFoSj0qeXpVB9ZxiFwz'],
                    ['label' => 'S1 EP02-05', 'id' => '16BktMg4FN-byavlZUS5XyXk21hFyc3W7'],
                    ['label' => 'S1 EP06-08', 'id' => '1TO-NJIPzpWyU1-4YONxD7Pfgg44LKJQS'],
                    ['label' => 'S1 EP09', 'id' => '1FXXBHv2H8NfMKziiuEB21YHeImyu3GC1'],
                    ['label' => 'S2 EP01-04', 'id' => '1_aDPog99T1L3Gnr4OCU1af7zjXwsRT4r'],
                    ['label' => 'S2 EP05-07', 'id' => '12_xLVQoamuhDf-sVbZGZ1eHHXn6LK3uB']
                ]
            ],
            [
                'title' => 'SWING CLIPPAZ',
                'cover' => 'https://raw.githubusercontent.com/sebastianpw/sg_showcase_01/main/cinemagic_hub/swing-clippaz/assets/frame0071013.jpg',
                'is_mature' => 0,
                'links' => [
                    ['label' => 'S1 EP01-12', 'id' => '1eTQ9f0D5A3RCiWpiMxuJ1y-gQifO6xQf'],
                    ['label' => 'S2 EP01-12', 'id' => '1PLcVZsL13jKy8m9lDe3r_4Bsio4wnZyl'],
                    ['label' => 'S3 EP01-12', 'id' => '10qs8RRfr00k-s3l4azt78i4xWjLKU6y1']
                ]
            ],
            [
                'title' => 'PRIMAL',
                'cover' => 'https://raw.githubusercontent.com/sebastianpw/sg_showcase_01/main/cinemagic_hub/primal/assets/frame0072078.jpg',
                'is_mature' => 0,
                'links' => [
                    ['label' => 'S1 EP01-08', 'id' => '18HmiyqVmMA7ecvbm5OEHnPqKq2s6O4gy']
                ]
            ],
            [
                'title' => 'SCHOOL LIFE',
                'cover' => 'https://raw.githubusercontent.com/sebastianpw/sg_showcase_01/main/cinemagic_hub/school-life/assets/frame0069620.jpg',
                'is_mature' => 0,
                'links' => [
                    ['label' => 'S1 & 2 Complete', 'id' => '1Ky_q7kDKb9k88SSe5cAV50xaDNUjsVJ1']
                ]
            ],
            [
                'title' => 'SKITCH GRIP BOODLE',
                'cover' => 'https://raw.githubusercontent.com/sebastianpw/sg_showcase_01/main/cinemagic_hub/skitch-grip-boodle/assets/frame0070644.jpg',
                'is_mature' => 0,
                'links' => [
                    ['label' => 'S1 EP01-13', 'id' => '1BUEYbXDfNYvB6fMVLNJTI3SeNFGipZs4']
                ]
            ],
            [
                'title' => 'STAR GIRLS',
                'cover' => 'https://raw.githubusercontent.com/sebastianpw/sg_showcase_01/main/cinemagic_hub/star-girls/assets/frame0072921.jpg',
                'is_mature' => 0,
                'links' => [
                    ['label' => 'S1 EP01-07', 'id' => '1LuIAtepzIwKKnPw1_wYsZryj-gW68GSg'],
                    ['label' => 'S2 EP01-06', 'id' => '126GxVNUmB0-RoGVo_FyESCglBWCrBnoC']
                ]
            ],
            [
                'title' => 'THERE WAS SOMEONE HERE',
                'cover' => 'https://raw.githubusercontent.com/sebastianpw/sg_showcase_01/main/cinemagic_hub/there-was-someone-here/assets/frame0074386.jpg',
                'is_mature' => 0,
                'links' => [
                    ['label' => 'Watch Episode File', 'id' => '1Y-9u0egMmNMJLe0BTvWbzMf13jkAtITh']
                ]
            ],
            [
                'title' => 'YOU ALREADY ANSWERED THIS ONCE',
                'cover' => 'https://raw.githubusercontent.com/sebastianpw/sg_showcase_01/main/cinemagic_hub/you-already-answered-this-once/assets/frame0074861.png',
                'is_mature' => 0,
                'links' => [
                    ['label' => 'Watch Feature File', 'id' => '1cu4QbOtoFXwz8YkKDGVrUJGDuCfZzYK0']
                ]
            ],
            [
                'title' => 'YUME',
                'cover' => 'https://raw.githubusercontent.com/sebastianpw/sg_showcase_01/main/cinemagic_hub/yume/assets/frame0071423.jpg',
                'is_mature' => 0,
                'links' => [
                    ['label' => 'S1 EP01-14', 'id' => '1tMKcw6fXqYoEdX0B_HJqL5inv6knzZrl']
                ]
            ]
        ];

        $stmtSeries = $pdo->prepare("INSERT INTO `gdl_series` (document_id, title, cover_url, sort_order, is_mature) VALUES (?, ?, ?, ?, ?)");
        $stmtLink = $pdo->prepare("INSERT INTO `gdl_links` (series_id, label, gdrive_id, sort_order) VALUES (?, ?, ?, ?)");

        foreach ($seed as $sIdx => $s) {
            $stmtSeries->execute([$docId, $s['title'], $s['cover'], $sIdx, $s['is_mature']]);
            $seriesId = (int)$pdo->lastInsertId();
            foreach ($s['links'] as $lIdx => $l) {
                $stmtLink->execute([$seriesId, $l['label'], $l['id'], $lIdx]);
            }
        }
    }
}
ensureGdlSchemaAndSeed($pdo);

// --- 2. Google OAuth Initialization ---
$jsonPath = PROJECT_ROOT . '/token/client_secret_google_oauth.json';
if (!file_exists($jsonPath)) {
    die("Google OAuth credentials missing.");
}

$client = new Google_Client();
$client->setAuthConfig($jsonPath);
$client->addScope(Google_Service_Drive::DRIVE_METADATA_READONLY);

$needAuth = false;
if (empty($_SESSION['google_access_token'])) {
    $needAuth = true;
} else {
    $token = $_SESSION['google_access_token'];
    $client->setAccessToken($token);

    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $token = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            $_SESSION['google_access_token'] = $token;
        } else {
            $needAuth = true;
        }
    }
}

if ($needAuth) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No active session. Please log in again.', 'isAuthError' => true]);
        exit;
    } else {
        header('Location: /google_login.php?drive=1&redirect=' . urlencode('/gdriver.php'));
        exit;
    }
}

$driveService = new Google_Service_Drive($client);

// --- 3. AJAX Endpoints (Drive + Document Persistence) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        if ($action === 'list') {
            $folderId = $_POST['folderId'] ?? 'root';
            $folderId = preg_replace('/[^a-zA-Z0-9_-]/', '', $folderId);
            $pageToken = !empty($_POST['pageToken']) ? trim($_POST['pageToken']) : null;

            $optParams = [
                'q' => "'" . $folderId . "' in parents and trashed = false",
                'pageSize' => 100,
                'fields' => 'nextPageToken, files(id, name, mimeType, iconLink, modifiedTime, size)',
                'orderBy' => 'folder, name'
            ];
            if ($pageToken) {
                $optParams['pageToken'] = $pageToken;
            }

            $results = $driveService->files->listFiles($optParams);
            $filesList = [];
            foreach ($results->getFiles() as $file) {
                $filesList[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'mimeType' => $file->getMimeType(),
                    'iconLink' => $file->getIconLink(),
                    'modifiedTime' => $file->getModifiedTime(),
                    'size' => $file->getSize()
                ];
            }
            echo json_encode([
                'success' => true, 
                'files' => $filesList,
                'nextPageToken' => $results->getNextPageToken() ?: null
            ]);
            exit;
        }

        if ($action === 'get_documents') {
            $stmt = $pdo->query("SELECT id, filename, title, updated_at FROM `gdl_documents` ORDER BY id ASC");
            echo json_encode(['success' => true, 'documents' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($action === 'load_document') {
            $docId = (int)($_POST['id'] ?? 0);
            $stmtDoc = $pdo->prepare("SELECT * FROM `gdl_documents` WHERE id = ?");
            $stmtDoc->execute([$docId]);
            $doc = $stmtDoc->fetch(PDO::FETCH_ASSOC);

            if (!$doc) {
                echo json_encode(['success' => false, 'error' => 'Document not found']);
                exit;
            }

            $stmtSeries = $pdo->prepare("SELECT * FROM `gdl_series` WHERE document_id = ? ORDER BY sort_order ASC, id ASC");
            $stmtSeries->execute([$docId]);
            $seriesRows = $stmtSeries->fetchAll(PDO::FETCH_ASSOC);

            $series = [];
            $stmtLinks = $pdo->prepare("SELECT label AS name, gdrive_id AS id FROM `gdl_links` WHERE series_id = ? ORDER BY sort_order ASC, id ASC");

            foreach ($seriesRows as $sRow) {
                $stmtLinks->execute([$sRow['id']]);
                $series[] = [
                    'id' => (int)$sRow['id'],
                    'title' => $sRow['title'],
                    'cover' => $sRow['cover_url'] ?? '',
                    'is_mature' => (int)($sRow['is_mature'] ?? 0),
                    'pdfs' => $stmtLinks->fetchAll(PDO::FETCH_ASSOC)
                ];
            }

            echo json_encode([
                'success' => true,
                'document' => [
                    'id' => (int)$doc['id'],
                    'filename' => $doc['filename'],
                    'title' => $doc['title']
                ],
                'series' => $series
            ]);
            exit;
        }

        if ($action === 'save_document') {
            $docId = (int)($_POST['id'] ?? 0);
            $filename = trim($_POST['filename'] ?? 'download.html');
            $title = trim($_POST['title'] ?? 'Google Drive Archive');
            $seriesData = json_decode($_POST['series_json'] ?? '[]', true) ?: [];

            $pdo->beginTransaction();

            $stmtDoc = $pdo->prepare("UPDATE `gdl_documents` SET filename = ?, title = ? WHERE id = ?");
            $stmtDoc->execute([$filename, $title, $docId]);

            $stmtOld = $pdo->prepare("SELECT id FROM `gdl_series` WHERE document_id = ?");
            $stmtOld->execute([$docId]);
            $oldIds = $stmtOld->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($oldIds)) {
                $inClause = implode(',', array_fill(0, count($oldIds), '?'));
                $stmtDelLinks = $pdo->prepare("DELETE FROM `gdl_links` WHERE series_id IN ($inClause)");
                $stmtDelLinks->execute($oldIds);
                $stmtDelSeries = $pdo->prepare("DELETE FROM `gdl_series` WHERE document_id = ?");
                $stmtDelSeries->execute([$docId]);
            }

            $stmtInsSeries = $pdo->prepare("INSERT INTO `gdl_series` (document_id, title, cover_url, sort_order, is_mature) VALUES (?, ?, ?, ?, ?)");
            $stmtInsLink = $pdo->prepare("INSERT INTO `gdl_links` (series_id, label, gdrive_id, sort_order) VALUES (?, ?, ?, ?)");

            foreach ($seriesData as $sIdx => $s) {
                $isMature = !empty($s['is_mature']) ? 1 : 0;
                $stmtInsSeries->execute([$docId, $s['title'] ?? 'Untitled', $s['cover'] ?? '', $sIdx, $isMature]);
                $newSeriesId = (int)$pdo->lastInsertId();
                if (!empty($s['pdfs']) && is_array($s['pdfs'])) {
                    foreach ($s['pdfs'] as $pIdx => $pdf) {
                        $stmtInsLink->execute([$newSeriesId, $pdf['name'] ?? '', $pdf['id'] ?? '', $pIdx]);
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'create_document') {
            $filename = trim($_POST['filename'] ?? 'new_download.html');
            if (empty($filename)) $filename = 'download_' . time() . '.html';
            $title = trim($_POST['title'] ?? 'Archive');

            $stmt = $pdo->prepare("INSERT INTO `gdl_documents` (filename, title) VALUES (?, ?)");
            $stmt->execute([$filename, $title]);
            $newId = (int)$pdo->lastInsertId();

            echo json_encode(['success' => true, 'id' => $newId]);
            exit;
        }

        if ($action === 'delete_document') {
            $docId = (int)($_POST['id'] ?? 0);
            $count = (int)$pdo->query("SELECT COUNT(*) FROM `gdl_documents`")->fetchColumn();
            if ($count <= 1) {
                echo json_encode(['success' => false, 'error' => 'Cannot delete the only remaining document.']);
                exit;
            }

            $pdo->beginTransaction();
            $stmtOld = $pdo->prepare("SELECT id FROM `gdl_series` WHERE document_id = ?");
            $stmtOld->execute([$docId]);
            $oldIds = $stmtOld->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($oldIds)) {
                $inClause = implode(',', array_fill(0, count($oldIds), '?'));
                $pdo->prepare("DELETE FROM `gdl_links` WHERE series_id IN ($inClause)")->execute($oldIds);
                $pdo->prepare("DELETE FROM `gdl_series` WHERE document_id = ?")->execute([$docId]);
            }
            $pdo->prepare("DELETE FROM `gdl_documents` WHERE id = ?")->execute([$docId]);
            $pdo->commit();

            echo json_encode(['success' => true]);
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $rawMsg = $e->getMessage();
        $displayMsg = $rawMsg;
        $decoded = json_decode($rawMsg, true);
        if (isset($decoded['error']['message'])) {
            $displayMsg = $decoded['error']['message'];
        }
        $isAuthError = false;
        $lowerMsg = strtolower($rawMsg);
        if (strpos($lowerMsg, 'insufficient permission') !== false || strpos($lowerMsg, 'invalid credentials') !== false) {
            $isAuthError = true;
        }
        echo json_encode(['success' => false, 'error' => $displayMsg, 'isAuthError' => $isAuthError]);
        exit;
    }
}

require "eruda_var.php";
ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />

<style>
/* Exact tokens and styles imported from narseq.php */
:root, [data-theme="dark"] {
    --pl-bg:          #080b10;
    --pl-surface:     #0e1319;
    --pl-card:        #111820;
    --pl-border:      #1c2535;
    --pl-text:        #c8d4e8;
    --pl-text-dim:    #5a6a80;
    --pl-amber:       #f5a623;
    --pl-teal:        #3ab5c8;
}
[data-theme="light"] {
    --pl-bg:          #f4f6fa;
    --pl-surface:     #ffffff;
    --pl-card:        #ffffff;
    --pl-border:      #d0d8e8;
    --pl-text:        #1a2233;
    --pl-text-dim:    #7a8aaa;
    --pl-amber:       #c8880a;
    --pl-teal:        #1a8090;
}

body { background: var(--pl-bg); color: var(--pl-text); font-family: 'Syne', system-ui, sans-serif; margin: 0; padding: 0; }

.pl-nav { display:flex; align-items:center; gap:6px; padding:8px 12px; padding-left:68px; background:rgba(0,0,0,.6); border-bottom:1px solid var(--pl-border); position:sticky; top:0; z-index:100; backdrop-filter:blur(6px); }
[data-theme="light"] .pl-nav { background:rgba(244,246,250,.92); }
.pl-nav-btn { font-family:'Space Mono',monospace; font-size:.7rem; padding:5px 9px; border:1px solid var(--pl-border); border-radius:4px; color:var(--pl-text-dim); text-decoration:none; transition:all .2s; background:var(--pl-surface); cursor:pointer; display:inline-flex; align-items:center; gap:4px; white-space:nowrap; }
.pl-nav-btn:hover { color:var(--pl-teal); border-color:var(--pl-teal); }

@media (max-width: 480px) {
    .pl-nav { padding-left: 60px; gap: 4px; padding-right: 6px; }
    .pl-nav-btn { padding: 4px 6px; font-size: 0.65rem; }
}

.workspace { max-width:900px; margin:0 auto; padding:30px 15px 100px; }

/* Item Wrapper */
.seq-item-wrap { position: relative; transition: opacity 0.2s; }
.seq-item-wrap:last-child .inline-add-row { display: none !important; }

/* Drag Hover States */
.seq-item-wrap.drag-over-top .scene-block { border-top: 2px solid var(--pl-teal); padding-top: 14px; }
.seq-item-wrap.drag-over-bottom .scene-block { border-bottom: 2px solid var(--pl-teal); padding-bottom: 14px; }
.seq-item-wrap.dragging { opacity: 0.4; z-index: 999; }

.scene-block { position:relative; background:var(--pl-card); border:1px solid var(--pl-border); border-radius:6px; padding:16px; padding-left:36px; margin-bottom:10px; box-shadow:0 4px 15px rgba(0,0,0,.2); transition: border 0.15s, padding 0.15s; }
[data-theme="light"] .scene-block { box-shadow:0 2px 8px rgba(0,0,0,.05); }

/* Drag Handle */
.drag-handle { position:absolute; left:0; top:0; bottom:0; width:32px; display:flex; align-items:center; justify-content:center; color:var(--pl-text-dim); font-size:1rem; cursor:grab; opacity:0.3; transition:opacity .2s; touch-action:none; user-select:none; border-right:1px solid var(--pl-border); }
.scene-block:hover .drag-handle { opacity:1; }
.drag-handle:active { cursor:grabbing; color:var(--pl-teal); background:rgba(58,181,200,0.05); }

/* Remove Button */
.item-remove-btn {
    position: absolute; top: 8px; right: 8px; width: 30px; height: 30px;
    border-radius: 4px; background: rgba(0,0,0,0.1); color: var(--pl-text-dim);
    border: 1px solid rgba(255,255,255,0.05);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.15s; font-size: 1.3rem; z-index: 10; 
    opacity: 0.85; 
}
[data-theme="light"] .item-remove-btn { background: rgba(0,0,0,0.03); border-color: rgba(0,0,0,0.05); }
.item-remove-btn:hover, .item-remove-btn:active { 
    background: rgba(255, 68, 68, 0.1); color: #ff4444 !important; 
    border-color: rgba(255, 68, 68, 0.3); opacity: 1; 
}

/* Horizontal Preview Layout - Identical to narseq.php */
.sketch-flex { display: flex; gap: 15px; align-items: flex-start; }

/* Small Square Thumbnail Preview (Left of Text) */
.sketch-thumb { position: relative; width: 100px; height: 100px; flex-shrink: 0; background: #000; border-radius: 4px; overflow: hidden; border: 1px solid var(--pl-border); cursor: pointer; transition: filter 0.15s; }
.sketch-thumb:hover { filter: brightness(1.2); }
.sketch-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.sketch-thumb a { display: block; width: 100%; height: 100%; }

.sketch-info { flex: 1; display: flex; flex-direction: column; justify-content: flex-start; overflow: hidden; min-width: 0; }
.sketch-id { font-family: 'Space Mono', monospace; font-size: 0.65rem; color: var(--pl-teal); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 5px; padding: 2px 0; display: inline-block; }
.sketch-title { font-size: 1.05rem; font-weight: bold; margin: 0; color: var(--pl-text); outline: none; }

/* Inline Split/Add Divider */
.inline-add-row { display:flex; align-items:center; gap:8px; margin:8px 0; opacity:0.4; transition:opacity .2s; cursor:pointer; }
.inline-add-row:hover { opacity:1; }
.inline-add-row .add-divider { flex:1; height:1px; background:var(--pl-border); }
.inline-add-btn { display:flex; align-items:center; gap:6px; padding:4px 12px; border-radius:12px; border:1px dashed var(--pl-border); background:var(--pl-bg); color:var(--pl-text-dim); font-family:'Space Mono',monospace; font-size:.65rem; text-transform:uppercase; cursor:pointer; transition:all .2s; }
.inline-add-row:hover .inline-add-btn { border-color:var(--pl-amber); color:var(--pl-amber); background:rgba(245,166,35,.05); }

/* Modals */
.su-modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:300000; display:none; align-items:center; justify-content:center; backdrop-filter:blur(2px); }
.su-modal-backdrop.active { display:flex; }
.su-modal-box { width:100%; max-width:540px; background:var(--pl-surface); border:1px solid var(--pl-border); border-radius:8px; padding:20px; box-shadow:0 10px 40px rgba(0,0,0,.5); margin:16px; display:flex; flex-direction:column; max-height:85vh; }
.su-modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
.su-modal-title { font-size:1rem; font-weight:bold; font-family:'Space Mono',monospace; color:var(--pl-teal); text-transform:uppercase; letter-spacing:1px; }
.su-modal-close { background:transparent; border:none; color:var(--pl-text-dim); cursor:pointer; font-size:1.2rem; }
.su-input { width:100%; box-sizing:border-box; background:var(--pl-card); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; padding:8px 12px; font-family:'Syne',sans-serif; font-size:.85rem; outline:none; transition:border-color .2s; }
.su-input:focus { border-color:var(--pl-teal); }
.pl-btn { padding:7px 14px; border-radius:4px; border:1px solid; font-family:'Space Mono',monospace; font-size:.75rem; cursor:pointer; transition:all .15s; white-space:nowrap; }
.pl-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.pl-btn-secondary { border-color:var(--pl-border); background:var(--pl-card); color:var(--pl-text-dim); }
.pl-btn-secondary:hover:not(:disabled) { border-color:var(--pl-amber); color:var(--pl-amber); }
.pl-btn-primary { border-color:var(--pl-amber); background:var(--pl-amber); color:#000; font-weight:bold; }
.pl-btn-primary:hover:not(:disabled) { filter:brightness(1.1); }
.pl-btn-teal { border-color:var(--pl-teal); background:var(--pl-teal); color:#000; font-weight:bold; }
.pl-btn-teal:hover:not(:disabled) { filter:brightness(1.1); }

/* Nested PDF drag items */
.pdf-list { display: flex; flex-direction: column; gap: 6px; margin-top: 8px; width: 100%; }
.pdf-item {
    position: relative; display: flex; align-items: center; gap: 8px;
    background: var(--pl-surface); border: 1px solid var(--pl-border);
    border-radius: 4px; padding: 6px 6px 6px 28px; transition: border-color .15s;
}
.pdf-item:hover { border-color: var(--pl-teal); }
.pdf-drag-handle {
    position: absolute; left: 0; top: 0; bottom: 0; width: 26px;
    display: flex; align-items: center; justify-content: center;
    color: var(--pl-text-dim); font-size: 0.9rem; cursor: grab; opacity: 0.4;
    touch-action: none;
}
.pdf-item:hover .pdf-drag-handle { opacity: 1; }
.pdf-drag-handle:active { cursor: grabbing; color: var(--pl-teal); }
.pdf-item.drag-over-top { border-top: 2px solid var(--pl-teal); }
.pdf-item.drag-over-bottom { border-bottom: 2px solid var(--pl-teal); }
.pdf-item.dragging { opacity: 0.3; }

/* Explorer Modal Items */
.ff-dropdown-item {
    padding: 8px 10px; font-size: 0.75rem; cursor: pointer;
    border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--pl-text);
    display: flex; justify-content: space-between; align-items:center;
    background: var(--pl-card); border: 1px solid var(--pl-border); border-radius: 4px;
    margin-bottom: 4px; transition: all 0.15s;
}
.ff-dropdown-item:hover { background: rgba(58,181,200,0.1); color: var(--pl-teal); border-color: var(--pl-teal); }

/* Prevent mobile browsers from auto-zooming on input focus */
@media screen and (max-width: 768px) {
    .su-input,
    input[type="text"],
    input[type="number"],
    select,
    textarea {
        font-size: 16px !important;
    }
}
</style>

<div class="pl-nav">
    <button class="pl-nav-btn" onclick="openDocModal()"><i class="bi bi-folder2"></i> Docs</button>
    <button class="pl-nav-btn pl-btn-teal" onclick="saveActiveDoc()"><i class="bi bi-save"></i> Save</button>
    <button class="pl-nav-btn pl-btn-teal" style="margin-left:auto;" onclick="addSeries()">＋ New Series</button>
    <button class="pl-nav-btn pl-btn-primary" onclick="exportHTML()"><i class="bi bi-download"></i> Export</button>
</div>

<div class="workspace">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <span style="font-family:'Space Mono',monospace; font-size:0.8rem; color:var(--pl-teal);" id="docIndicator">Loading doc...</span>
        <span style="font-family:'Space Mono',monospace; font-size:0.7rem; color:var(--pl-text-dim);" id="saveStatus"></span>
    </div>
    
    <div id="seriesContainer"></div>
</div>

<!-- Drive Explorer Modal -->
<div id="driveModal" class="su-modal-backdrop" onmousedown="if(event.target===this)closeDriveModal()">
    <div class="su-modal-box">
        <div class="su-modal-header">
            <div class="su-modal-title"><i class="bi bi-folder2-open"></i> Select File from Drive</div>
            <button onclick="closeDriveModal()" class="su-modal-close">✕</button>
        </div>
        
        <div style="display:flex; align-items:center; gap:8px; padding-bottom:10px; margin-bottom:10px; border-bottom:1px solid var(--pl-border); font-family:'Space Mono',monospace; font-size:0.75rem; color:var(--pl-teal);">
            <span>Folder:</span> <strong id="current-folder-name">Root</strong>
        </div>

        <div id="file-list" style="overflow-y:auto; flex:1; padding-right:4px;">
            <div style="text-align:center; padding: 20px; color:var(--pl-text-dim); font-size:0.85rem;">Loading files...</div>
        </div>

        <!-- Drive Pagination Bar -->
        <div id="drivePagination" style="display:flex; justify-content:space-between; align-items:center; padding-top:10px; margin-top:8px; border-top:1px solid var(--pl-border);">
            <button type="button" class="pl-btn pl-btn-secondary" id="drivePrevBtn" onclick="changeDrivePage(-1)">« Prev</button>
            <span id="drivePageLabel" style="font-family:'Space Mono',monospace; font-size:0.75rem; color:var(--pl-text-dim);">Page 1</span>
            <button type="button" class="pl-btn pl-btn-secondary" id="driveNextBtn" onclick="changeDrivePage(1)">Next »</button>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:10px;">
            <button onclick="closeDriveModal()" class="pl-btn pl-btn-secondary">Cancel</button>
        </div>
    </div>
</div>

<!-- Document Management Modal -->
<div id="docModal" class="su-modal-backdrop" onmousedown="if(event.target===this)closeDocModal()">
    <div class="su-modal-box">
        <div class="su-modal-header">
            <div class="su-modal-title"><i class="bi bi-files"></i> Document Manager</div>
            <button onclick="closeDocModal()" class="su-modal-close">✕</button>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:16px;">
            <input type="text" id="newDocFilename" class="su-input" placeholder="e.g. series_new.html" style="font-size:0.8rem;">
            <button class="pl-btn pl-btn-teal" onclick="createDoc()">＋ Create</button>
        </div>

        <div style="font-family:'Space Mono',monospace; font-size:0.65rem; color:var(--pl-text-dim); text-transform:uppercase; margin-bottom:6px;">Stored Documents</div>
        <div id="docListContainer" style="overflow-y:auto; flex:1; display:flex; flex-direction:column; gap:4px; max-height:45vh;"></div>

        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px; border-top:1px solid var(--pl-border); padding-top:12px;">
            <button onclick="closeDocModal()" class="pl-btn pl-btn-secondary">Close</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/toast.js"></script>

<!-- PhotoSwipe Lightbox Module with dynamic natural aspect ratio preservation -->
<script type="module">
    import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
    window.initLightbox = () => {
        if (window.pswpLightbox) window.pswpLightbox.destroy();
        window.pswpLightbox = new PhotoSwipeLightbox({
            gallery: '#seriesContainer',
            children: 'a.editor-pswp-item',
            pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        });

        // Solve aspect ratio distortion on zoom by reading natural proportions
        window.pswpLightbox.addFilter('itemData', (itemData, index) => {
            const img = itemData.element?.querySelector('img');
            if (img && img.naturalWidth && img.naturalHeight) {
                itemData.width = img.naturalWidth;
                itemData.height = img.naturalHeight;
            }
            return itemData;
        });

        window.pswpLightbox.init();
    };
</script>

<script>
// --- State Management ---
let currentDoc = { id: 0, filename: 'download.html', title: 'Google Drive Archive' };
let seriesData = [];
let activeSeriesIdx = null;

// Drive pagination state
let currentFolder = 'root';
let currentFolderName = 'Root';
let folderHistory = [];
let drivePageTokens = [null];
let driveCurrentPageIndex = 0;
let driveNextPageToken = null;

document.addEventListener('DOMContentLoaded', () => {
    const savedDocId = localStorage.getItem('gdl_active_doc_id');
    if (savedDocId) {
        loadDoc(parseInt(savedDocId, 10));
    } else {
        fetchDocsList(true);
    }
});

// --- Document Logic ---
function fetchDocsList(autoLoadFirst = false) {
    const fd = new FormData();
    fd.append('action', 'get_documents');
    fetch('/gdriver.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success && res.documents.length) {
                renderDocsList(res.documents);
                if (autoLoadFirst) {
                    loadDoc(res.documents[0].id);
                }
            }
        });
}

function openDocModal() {
    fetchDocsList(false);
    document.getElementById('docModal').classList.add('active');
}
function closeDocModal() {
    document.getElementById('docModal').classList.remove('active');
}

function renderDocsList(docs) {
    const cont = document.getElementById('docListContainer');
    cont.innerHTML = docs.map(d => `
        <div class="ff-dropdown-item" style="padding:10px; ${d.id === currentDoc.id ? 'border-color:var(--pl-teal); background:rgba(58,181,200,0.1);' : ''}">
            <div style="flex:1; min-width:0; cursor:pointer;" onclick="loadDoc(${d.id}); closeDocModal();">
                <div style="font-weight:bold; color:var(--pl-text); font-size:0.85rem;">${d.filename}</div>
                <div style="font-size:0.65rem; color:var(--pl-text-dim); margin-top:2px;">#${d.id} &bull; ${d.title}</div>
            </div>
            <div style="display:flex; gap:6px;">
                <button class="pl-btn pl-btn-teal" style="padding:3px 8px; font-size:0.65rem;" onclick="loadDoc(${d.id}); closeDocModal();">Open</button>
                <button class="item-remove-btn" style="position:static; width:26px; height:26px; font-size:1rem;" onclick="deleteDoc(${d.id})"><i class="bi bi-trash"></i></button>
            </div>
        </div>
    `).join('');
}

function loadDoc(docId) {
    const fd = new FormData();
    fd.append('action', 'load_document');
    fd.append('id', docId);

    fetch('/gdriver.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                currentDoc = res.document;
                seriesData = res.series;
                localStorage.setItem('gdl_active_doc_id', currentDoc.id);
                document.getElementById('docIndicator').textContent = `${currentDoc.filename} (#${currentDoc.id})`;
                render();
            } else {
                fetchDocsList(true);
            }
        });
}

function saveActiveDoc() {
    const fd = new FormData();
    fd.append('action', 'save_document');
    fd.append('id', currentDoc.id);
    fd.append('filename', currentDoc.filename);
    fd.append('title', currentDoc.title);
    fd.append('series_json', JSON.stringify(seriesData));

    const statusEl = document.getElementById('saveStatus');
    statusEl.textContent = 'Saving...';

    fetch('/gdriver.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                statusEl.textContent = 'Saved!';
                setTimeout(() => statusEl.textContent = '', 2000);
                if (window.Toast) Toast.show('Document saved', 'success');
            } else {
                statusEl.textContent = 'Save error';
                if (window.Toast) Toast.show('Save failed: ' + res.error, 'error');
            }
        });
}

function createDoc() {
    const fn = document.getElementById('newDocFilename').value.trim();
    if (!fn) return;

    const fd = new FormData();
    fd.append('action', 'create_document');
    fd.append('filename', fn);
    fd.append('title', fn);

    fetch('/gdriver.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                document.getElementById('newDocFilename').value = '';
                loadDoc(res.id);
                closeDocModal();
            }
        });
}

function deleteDoc(docId) {
    if (!confirm('Are you sure you want to delete this document container?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_document');
    fd.append('id', docId);

    fetch('/gdriver.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (docId === currentDoc.id) {
                    fetchDocsList(true);
                } else {
                    fetchDocsList(false);
                }
            } else {
                if (window.Toast) Toast.show(res.error, 'warn');
            }
        });
}

// --- Rendering Series ---
function render() {
    const container = document.getElementById('seriesContainer');
    if (!seriesData.length) {
        container.innerHTML = `
            <div style="text-align:center; padding:40px; color:var(--pl-text-dim); border:1px dashed var(--pl-border); border-radius:6px; font-family:'Space Mono',monospace; font-size:0.85rem;">
                No series in this document. Click "＋ New Series" above to add one.
            </div>`;
        return;
    }

    container.innerHTML = seriesData.map((series, sIdx) => {
        const isMature = !!series.is_mature;
        return `
        <div class="seq-item-wrap" data-sindex="${sIdx}">
            <div class="scene-block">
                <div class="item-remove-btn" title="Remove Series" onclick="removeSeries(${sIdx})"><i class="bi bi-x"></i></div>
                <div class="drag-handle" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>

                <div class="sketch-flex">
                    <!-- Left: Small Square Thumbnail Preview (100px x 100px) -->
                    <div class="sketch-thumb">
                        <a href="${series.cover || '#'}" class="editor-pswp-item" target="_blank" data-pswp-width="1024" data-pswp-height="1024">
                            <img src="${series.cover}" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'100\\' height=\\'100\\' fill=\\'%23080b10\\'><text x=\\'50%\\' y=\\'50%\\' fill=\\'%235a6a80\\' font-family=\\'monospace\\' font-size=\\'10\\' text-anchor=\\'middle\\' dy=\\'4\\'>No Image</text></svg>'">
                        </a>
                    </div>

                    <!-- Right: Info, Title, Mature Toggle, Cover URL, & Dynamic PDFs -->
                    <div class="sketch-info">
                        <div class="sketch-id">Series #${String(sIdx + 1).padStart(2, '0')}</div>

                        <!-- Title and Mature Checkbox Inline -->
                        <div style="display:flex; gap:8px; align-items:center; margin-bottom:6px;">
                            <input type="text" class="su-input sketch-title" style="flex:1; font-weight:bold; font-size:1.05rem; padding:6px 10px;" value="${series.title}" placeholder="Series Title" oninput="seriesData[${sIdx}].title = this.value">
                            <label title="Mature content (18+)" style="display:inline-flex; align-items:center; gap:4px; cursor:pointer; padding:6px 10px; background:var(--pl-surface); border:1px solid var(--pl-border); border-radius:4px; user-select:none; flex-shrink:0;">
                                <input type="checkbox" style="accent-color:var(--pl-amber); width:15px; height:15px; cursor:pointer; margin:0;" ${isMature ? 'checked' : ''} onchange="seriesData[${sIdx}].is_mature = this.checked ? 1 : 0">
                                <span style="font-size:1rem; line-height:1;">🔞</span>
                            </label>
                        </div>

                        <input type="text" class="su-input" style="font-size:0.75rem; padding:5px 8px; margin-bottom:8px;" value="${series.cover}" placeholder="Cover Image URL (GitHub / Web)" oninput="updateCover(${sIdx}, this.value)">

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px;">
                            <span style="font-family:'Space Mono',monospace; font-size:0.65rem; color:var(--pl-amber); text-transform:uppercase;">Attached PDFs</span>
                            <button type="button" class="pl-btn pl-btn-teal" style="padding:3px 8px; font-size:0.65rem;" onclick="openDriveModal(${sIdx})">
                                <i class="bi bi-google"></i> ＋ Add PDF
                            </button>
                        </div>

                        <div class="pdf-list" data-sindex="${sIdx}">
                            ${series.pdfs.map((pdf, pIdx) => `
                                <div class="pdf-item" data-pindex="${pIdx}">
                                    <div class="pdf-drag-handle"><i class="bi bi-grip-vertical"></i></div>
                                    <div style="display:flex; flex-direction:column; gap:4px; flex:1; min-width:0;">
                                        <input type="text" class="su-input" style="font-size:0.75rem; padding:4px 8px;" value="${pdf.name}" placeholder="Label (e.g. S1 EP01)" oninput="seriesData[${sIdx}].pdfs[${pIdx}].name = this.value">
                                        <input type="text" class="su-input" style="font-family:'Space Mono',monospace; font-size:0.65rem; padding:3px 8px; color:var(--pl-text-dim);" value="${pdf.id}" placeholder="Drive File ID" oninput="seriesData[${sIdx}].pdfs[${pIdx}].id = this.value">
                                    </div>
                                    <button type="button" class="item-remove-btn" style="position:static; width:26px; height:26px; font-size:1rem; flex-shrink:0;" onclick="removePdf(${sIdx}, ${pIdx})"><i class="bi bi-trash"></i></button>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>

            <div class="inline-add-row" onclick="addSeriesAt(${sIdx + 1})">
                <div class="add-divider"></div>
                <button class="inline-add-btn"><i class="bi bi-plus-lg"></i> Insert Series Here</button>
                <div class="add-divider"></div>
            </div>
        </div>
        `;
    }).join('');

    if (window.initLightbox) window.initLightbox();
}

function addSeries() {
    seriesData.push({ title: "New Series", cover: "", is_mature: 0, pdfs: [] });
    render();
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
}

function addSeriesAt(index) {
    seriesData.splice(index, 0, { title: "New Series", cover: "", is_mature: 0, pdfs: [] });
    render();
}

function removeSeries(sIdx) {
    if (confirm("Remove this entire series?")) {
        seriesData.splice(sIdx, 1);
        render();
    }
}

function updateCover(sIdx, url) {
    seriesData[sIdx].cover = url;
    const wrap = document.querySelector(`.seq-item-wrap[data-sindex="${sIdx}"]`);
    if (wrap) {
        const link = wrap.querySelector('.sketch-thumb a');
        const img = wrap.querySelector('.sketch-thumb img');
        if (link) link.href = url || '#';
        if (img) {
            img.src = url;
            img.onload = () => {
                link.dataset.pswpWidth = img.naturalWidth;
                link.dataset.pswpHeight = img.naturalHeight;
            };
        }
        if (window.initLightbox) window.initLightbox();
    }
}

function removePdf(sIdx, pIdx) {
    seriesData[sIdx].pdfs.splice(pIdx, 1);
    render();
}

// --- Google Drive Explorer Modal Logic with Pagination ---
function openDriveModal(sIdx) {
    activeSeriesIdx = sIdx;
    document.getElementById('driveModal').classList.add('active');
    if (folderHistory.length === 0) {
        loadFolder('root', 'Root', null, true);
    }
}

function closeDriveModal() {
    document.getElementById('driveModal').classList.remove('active');
    activeSeriesIdx = null;
}

async function loadFolder(folderId = 'root', folderName = 'Root', token = null, resetPagination = true) {
    const listEl = document.getElementById('file-list');
    listEl.innerHTML = '<div style="text-align:center; padding:20px; color:var(--pl-text-dim); font-size:0.85rem;">Loading files...</div>';
    
    currentFolder = folderId;
    currentFolderName = folderName;
    document.getElementById('current-folder-name').innerText = folderName;

    if (resetPagination) {
        drivePageTokens = [null];
        driveCurrentPageIndex = 0;
        driveNextPageToken = null;
    }

    const fd = new FormData();
    fd.append('action', 'list');
    fd.append('folderId', folderId);
    if (token) {
        fd.append('pageToken', token);
    }

    try {
        const res = await fetch('/gdriver.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            listEl.innerHTML = '';
            driveNextPageToken = data.nextPageToken;

            // Update pagination UI
            document.getElementById('drivePrevBtn').disabled = (driveCurrentPageIndex <= 0);
            document.getElementById('driveNextBtn').disabled = !driveNextPageToken;
            document.getElementById('drivePageLabel').textContent = `Page ${driveCurrentPageIndex + 1}`;

            if (folderId !== 'root' && folderHistory.length > 0 && driveCurrentPageIndex === 0) {
                const backItem = document.createElement('div');
                backItem.className = 'ff-dropdown-item';
                backItem.innerHTML = `<span><i class="bi bi-arrow-up-circle"></i> .. (Go Up)</span>`;
                backItem.onclick = () => {
                    folderHistory.pop();
                    const prev = folderHistory[folderHistory.length - 1] || { id: 'root', name: 'Root' };
                    loadFolder(prev.id, prev.name, null, true);
                };
                listEl.appendChild(backItem);
            } else if (driveCurrentPageIndex === 0 && (folderHistory.length === 0 || folderHistory[folderHistory.length - 1].id !== folderId)) {
                folderHistory.push({ id: folderId, name: folderName });
            }

            if (data.files.length === 0) {
                listEl.innerHTML += '<div style="text-align:center; padding:20px; color:var(--pl-text-dim); font-size:0.8rem;">Folder is empty.</div>';
            }

            data.files.forEach(file => {
                const item = document.createElement('div');
                item.className = 'ff-dropdown-item';

                const isFolder = file.mimeType === 'application/vnd.google-apps.folder';
                const icon = isFolder ? '<i class="bi bi-folder-fill" style="color:var(--pl-amber); margin-right:8px;"></i>' : '<i class="bi bi-file-earmark-pdf" style="color:var(--pl-teal); margin-right:8px;"></i>';

                item.innerHTML = `
                    <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1;">
                        ${icon} ${file.name}
                    </span>
                    <span style="font-size:0.65rem; color:var(--pl-text-dim); margin-left:8px;">
                        ${isFolder ? 'Folder' : 'Select'}
                    </span>
                `;

                item.onclick = () => {
                    if (isFolder) {
                        loadFolder(file.id, file.name, null, true);
                    } else {
                        if (activeSeriesIdx !== null) {
                            seriesData[activeSeriesIdx].pdfs.push({
                                name: file.name.replace(/\.pdf$/i, ''),
                                id: file.id
                            });
                            render();
                            closeDriveModal();
                            if (window.Toast) Toast.show(`Added "${file.name}"`, 'success');
                        }
                    }
                };
                listEl.appendChild(item);
            });
        } else {
            listEl.innerHTML = `<div style="color:#ff4444; padding:20px; font-size:0.85rem;">Error: ${data.error}</div>`;
        }
    } catch (err) {
        listEl.innerHTML = `<div style="color:#ff4444; padding:20px; font-size:0.85rem;">Network error fetching files.</div>`;
    }
}

function changeDrivePage(direction) {
    if (direction === 1 && driveNextPageToken) {
        driveCurrentPageIndex++;
        if (drivePageTokens.length <= driveCurrentPageIndex) {
            drivePageTokens.push(driveNextPageToken);
        }
        loadFolder(currentFolder, currentFolderName, drivePageTokens[driveCurrentPageIndex], false);
    } else if (direction === -1 && driveCurrentPageIndex > 0) {
        driveCurrentPageIndex--;
        loadFolder(currentFolder, currentFolderName, drivePageTokens[driveCurrentPageIndex], false);
    }
}

// --- Buttery Drag & Drop Pointer Engine (Exact narseq.php mechanics) ---
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('seriesContainer');
    let dragSrc = null, pointerClone = null, pointerOffsetY = 0, isPdf = false, currentList = null;

    container.addEventListener('pointerdown', e => {
        const handle = e.target.closest('.drag-handle, .pdf-drag-handle');
        if (!handle) return;

        isPdf = handle.classList.contains('pdf-drag-handle');
        const wrap = isPdf ? handle.closest('.pdf-item') : handle.closest('.seq-item-wrap');
        if (!wrap) return;

        dragSrc = wrap;
        currentList = isPdf ? dragSrc.parentElement : container;

        const rect = wrap.getBoundingClientRect();
        pointerOffsetY = e.clientY - rect.top;

        pointerClone = wrap.cloneNode(true);
        pointerClone.style.cssText = `position:fixed; left:${rect.left}px; top:${rect.top}px; width:${rect.width}px; opacity:0.8; pointer-events:none; z-index:99999; background:var(--pl-card); border-radius:4px; box-shadow:0 10px 40px rgba(0,0,0,0.6);`;
        document.body.appendChild(pointerClone);

        wrap.classList.add('dragging');
        e.preventDefault();
    }, { passive: false });

    document.addEventListener('pointermove', e => {
        if (!pointerClone || !dragSrc) return;
        e.preventDefault();

        pointerClone.style.top = (e.clientY - pointerOffsetY) + 'px';

        const selector = isPdf ? '.pdf-item' : '.seq-item-wrap';
        currentList.querySelectorAll(selector).forEach(w => w.classList.remove('drag-over-top', 'drag-over-bottom'));

        const target = document.elementFromPoint(e.clientX, e.clientY);
        const wrap = target ? target.closest(selector) : null;

        if (wrap && wrap !== dragSrc && wrap.parentNode === currentList) {
            const rect = wrap.getBoundingClientRect();
            wrap.classList.add(e.clientY < rect.top + rect.height / 2 ? 'drag-over-top' : 'drag-over-bottom');
        }
    }, { passive: false });

    document.addEventListener('pointerup', e => {
        if (!dragSrc) return;

        if (pointerClone) { pointerClone.remove(); pointerClone = null; }
        dragSrc.classList.remove('dragging');

        const selector = isPdf ? '.pdf-item' : '.seq-item-wrap';
        currentList.querySelectorAll(selector).forEach(w => w.classList.remove('drag-over-top', 'drag-over-bottom'));

        const target = document.elementFromPoint(e.clientX, e.clientY);
        const wrap = target ? target.closest(selector) : null;

        if (wrap && wrap !== dragSrc && wrap.parentNode === currentList) {
            const rect = wrap.getBoundingClientRect();
            if (e.clientY < rect.top + rect.height / 2) {
                currentList.insertBefore(dragSrc, wrap);
            } else {
                currentList.insertBefore(dragSrc, wrap.nextSibling);
            }
        }

        persistSortOrder();
        dragSrc = null;
        currentList = null;
    });

    function persistSortOrder() {
        const nextData = [];
        const seriesElements = Array.from(document.querySelectorAll('.seq-item-wrap'));

        seriesElements.forEach(sEl => {
            const sIdx = sEl.dataset.sindex;
            const original = seriesData[sIdx];
            const updated = { ...original, pdfs: [] };

            const pdfElements = Array.from(sEl.querySelectorAll('.pdf-item'));
            pdfElements.forEach(pEl => {
                const pIdx = pEl.dataset.pindex;
                updated.pdfs.push(original.pdfs[pIdx]);
            });

            nextData.push(updated);
        });

        seriesData = nextData;
        render();
    }
});

// --- Export to exact download.html format with instant top-item visibility & 18+ indicator ---
function exportHTML() {
    let output = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<title>${currentDoc.title}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Lora:ital,wght@0,400;0,500;0,600;1,400;1,500&display=swap" rel="stylesheet">
<style>
:root {
    --bg:           #020202;
    --bg-lift:      #0c0c10;
    --border:       rgba(255,255,255,0.06);
    --border-gold:  rgba(205,164,52,0.20);
    --gold:         #cda434;
    --gold-dim:     rgba(205,164,52,0.13);
    --gold-glow:    rgba(205,164,52,0.06);
    --text:         #e5e0d8;
    --text-muted:   #8a8070;
    --text-dim:     #3e3a32;
    --nav-bg:       rgba(8,6,4,0.85);
    --nav-border:   rgba(205,164,52,0.18);
    --nav-text:     #c8b88a;
    --font-title:   'Cinzel', serif;
    --font-body:    'Lora', serif;
    --card-radius:  3px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 14px; -webkit-font-smoothing: antialiased; }
body {
    font-family: var(--font-body);
    background: var(--bg);
    background-image: radial-gradient(ellipse 120% 60% at 50% 0%, #141418 0%, #020202 65%);
    background-attachment: fixed;
    color: var(--text);
    min-height: 100vh;
    overflow-x: hidden;
}
a { color: inherit; text-decoration: none; }
.hero { text-align: center; padding: 70px 24px 40px; position: relative; }
.hero-eyebrow { font-family: var(--font-title); font-size: 0.55rem; letter-spacing: 0.35em; color: var(--gold); opacity: 0.7; text-transform: uppercase; margin-bottom: 22px; }
.hero-title { font-family: var(--font-title); font-size: clamp(1.9rem, 6vw, 3.2rem); font-weight: 400; color: var(--gold); letter-spacing: 0.06em; line-height: 1.15; margin-bottom: 18px; }
.hero-sub { font-family: var(--font-body); font-size: 0.95rem; font-style: italic; color: var(--text-muted); letter-spacing: 0.02em; max-width: 520px; margin: 0 auto; line-height: 1.7; }
.section { max-width: 900px; margin: 0 auto; padding: 0 16px; }
.section-rule { display: flex; align-items: center; gap: 16px; margin: 0 0 40px; }
.section-rule-line { flex: 1; height: 1px; background: var(--border-gold); opacity: 0.5; }
.section-rule-label { font-family: var(--font-title); font-size: 0.55rem; letter-spacing: 0.3em; color: var(--gold); opacity: 0.7; text-transform: uppercase; white-space: nowrap; }
.series-list { display: flex; flex-direction: column; gap: 24px; padding-bottom: 60px; }
.series-row { display: flex; gap: 24px; background: var(--bg-lift); border: 1px solid var(--border); border-radius: var(--card-radius); padding: 20px; transition: border-color 0.3s ease, transform 0.3s ease; }
.series-row:hover { border-color: var(--border-gold); transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.4); }
.series-cover { width: 150px; aspect-ratio: 3/4; object-fit: cover; object-position: center top; border-radius: 2px; background: #0a0a0d; flex-shrink: 0; }
.series-content { display: flex; flex-direction: column; justify-content: center; flex-grow: 1; min-width: 0; }
.series-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; gap: 12px; }
.series-title { font-family: var(--font-title); font-size: 1.25rem; font-weight: 600; color: var(--text); letter-spacing: 0.06em; text-transform: uppercase; margin: 0; }
.series-mature-badge { font-size: 1.45rem; line-height: 1; flex-shrink: 0; }
.series-links-grid { display: flex; flex-wrap: wrap; gap: 10px; }
.gdrive-link { display: inline-flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border); border-radius: 3px; padding: 10px 16px; font-family: var(--font-title); font-size: 0.65rem; letter-spacing: 0.15em; color: var(--text-muted); text-decoration: none; text-transform: uppercase; transition: all 0.25s ease; }
.gdrive-link svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; transition: transform 0.25s ease; }
.gdrive-link:hover { background: var(--gold-dim); border-color: var(--border-gold); color: var(--gold); }
.gdrive-link:hover svg { transform: translateX(2px) translateY(-2px); }
@media (max-width: 600px) {
    .series-row { flex-direction: column; align-items: center; text-align: center; padding: 24px 16px; }
    .series-header { justify-content: center; }
    .series-links-grid { justify-content: center; }
}
.site-footer { border-top: 1px solid var(--border); padding: 30px 24px 40px; text-align: center; display: flex; align-items: center; justify-content: center; gap: 24px; flex-wrap: wrap; margin-top: 40px; }
.site-footer span { font-family: var(--font-title); font-size: 0.48rem; letter-spacing: 0.22em; color: var(--text-dim); text-transform: uppercase; }
.site-footer-sep { width: 1px; height: 10px; background: var(--border); display: inline-block; }
.fade-up { opacity: 0; transform: translateY(22px); transition: opacity 0.7s cubic-bezier(0.25,1,0.5,1), transform 0.7s cubic-bezier(0.25,1,0.5,1); }
.fade-up.visible { opacity: 1; transform: translateY(0); }
</style>
</head>
<body>
<div class="hero fade-up visible">
    <div class="hero-eyebrow">A SAGE Production</div>
    <h1 class="hero-title">${currentDoc.title}</h1>
    <p class="hero-sub">Secure access to the Google Drive original PDFs.</p>
</div>
<section class="section fade-up visible">
    <div class="section-rule">
        <div class="section-rule-line"></div>
        <div class="section-rule-label">Series Archive</div>
        <div class="section-rule-line"></div>
    </div>
    <div class="series-list">\n`;

    seriesData.forEach((series, sIdx) => {
        const initialVisible = sIdx < 3 ? ' visible' : '';
        const matureIcon = series.is_mature ? `<span class="series-mature-badge" title="Mature (18+)">🔞</span>` : '';
        output += `        <div class="series-row fade-up${initialVisible}">
            <img class="series-cover" src="${series.cover}" alt="${series.title}" loading="lazy">
            <div class="series-content">
                <div class="series-header">
                    <h2 class="series-title">${series.title}</h2>
                    ${matureIcon}
                </div>
                <div class="series-links-grid">\n`;

        series.pdfs.forEach(pdf => {
            output += `                    <a class="gdrive-link" href="https://drive.google.com/file/d/${pdf.id}/view?usp=drivesdk" target="_blank">
                        <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/></svg>
                        ${pdf.name}
                    </a>\n`;
        });

        output += `                </div>
            </div>
        </div>\n`;
    });

    output += `    </div>
</section>
<footer class="site-footer">
    <span>&copy; ${new Date().getFullYear()} Starlight Guardians</span>
    <div class="site-footer-sep"></div>
    <span>Archive Access</span>
</footer>
<script>
(function () {
    'use strict';
    const fadeEls = document.querySelectorAll('.fade-up');
    
    function revealInView() {
        fadeEls.forEach(el => {
            const rect = el.getBoundingClientRect();
            if (rect.top <= (window.innerHeight || document.documentElement.clientHeight) + 60) {
                el.classList.add('visible');
            }
        });
    }

    const fadeObs = new IntersectionObserver((entries, obs) => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.classList.add('visible');
                obs.unobserve(e.target);
            }
        });
    }, { threshold: 0.01, rootMargin: '0px 0px 60px 0px' });

    fadeEls.forEach(el => fadeObs.observe(el));

    revealInView();
    window.addEventListener('load', revealInView);
})();
<\/script>
</body>
</html>`;

    const blob = new Blob([output], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = currentDoc.filename || 'download.html';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
    if (window.Toast) Toast.show(`Exported ${currentDoc.filename}`, 'success');
}
</script>
<?php
$content = ob_get_clean();
//$content .= $eruda ?? '';
$spw->renderLayout($content, "Drive Series Builder", $spw->getProjectPath() . '/templates/curation.php');