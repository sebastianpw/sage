<?php
// public/view_video_details.php
require_once __DIR__ . '/bootstrap.php';

use App\UI\Modules\VideoFrameExtractorModule;
use App\UI\Modules\ImageEditorModule;

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("No video ID provided.");

// ═══════════════════════════════════════════════════════
// INLINE API HANDLER
// ═══════════════════════════════════════════════════════
if (isset($_REQUEST['api_action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['api_action'];
    try {
        if ($action === 'get_video') {
            $vid = (int)($_GET['id'] ?? 0);
            if (!$vid) throw new Exception('Missing id');
            $stmt = $pdo->prepare("
                SELECT v.*, c.name as category_name, va.to_id as animatic_id
                FROM videos v
                LEFT JOIN video_categories c ON v.category_id = c.id
                LEFT JOIN videos_2_animatics va ON v.id = va.from_id
                WHERE v.id = ?
            ");
            $stmt->execute([$vid]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$video) throw new Exception('Video not found');
            $plStmt = $pdo->prepare("SELECT playlist_id FROM video_playlist_items WHERE video_id = ?");
            $plStmt->execute([$vid]);
            $video['playlists'] = $plStmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['status' => 'ok', 'video' => $video]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    exit;
}

// Fetch Video
$stmt = $pdo->prepare("
    SELECT v.*, c.name as category_name, va.to_id as animatic_id
    FROM videos v
    LEFT JOIN video_categories c ON v.category_id = c.id
    LEFT JOIN videos_2_animatics va ON v.id = va.from_id
    WHERE v.id = ?
");
$stmt->execute([$id]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$video) die("Video not found.");

// Fetch Categories for the edit form (video_categories exists per vidbat usage)
$categories = $pdo->query("SELECT * FROM video_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
// Playlists and per-video membership are loaded via video_admin_api.php at runtime (matching vidbat)

$videoExtractor = new VideoFrameExtractorModule();
$imageEditor    = new ImageEditorModule();

function fmtSize(?int $b): string {
    return $b ? number_format($b / 1024 / 1024, 1) . ' MB' : '';
}
function fmtDur(?float $s): string {
    if (!$s) return '0:00';
    $m  = (int)floor($s / 60);
    $sc = (int)floor($s % 60);
    return $m . ':' . str_pad((string)$sc, 2, '0', STR_PAD_LEFT);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Video Details #<?= $id ?></title>
    <script>
        (function() {
            try {
                var theme = localStorage.getItem('spw_theme');
                if (theme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else if (theme === 'light') {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/toast.css">
    <script src="/js/toast.js"></script>
    <style>
        /* ── Reset ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Mono', 'Fira Mono', monospace;
            height: 100dvh;
            overflow: hidden;
        }

        /* ── Layout ── */
        .detail-layout {
            display: flex;
            flex-direction: column;
            height: 100dvh;
            width: 100vw;
            overflow: hidden;
        }

        /* ── Player ── */
        .detail-player-wrapper {
            width: 100%;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-bottom: 1px solid var(--border);
        }
        .detail-player-wrapper video {
            width: 100%;
            max-height: 45vh;
            display: block;
            background: #000;
        }

        /* ── Scrollable Content ── */
        .detail-content {
            padding: 14px;
            overflow-y: auto;
            flex: 1;
            min-height: 0;
            padding-bottom: calc(14px + env(safe-area-inset-bottom));
        }

        /* ── Title & Meta ── */
        .detail-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text);
        }
        .detail-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 14px;
        }

        /* ── Action Grid ── */
        .detail-actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }
        .btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10px 4px;
            font-size: 0.75rem;
            font-family: inherit;
            font-weight: 700;
            gap: 4px;
            border-radius: 6px;
            cursor: pointer;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-muted);
            -webkit-tap-highlight-color: transparent;
            transition: background 0.1s, border-color 0.1s, color 0.1s;
            text-decoration: none;
            letter-spacing: 0.5px;
            line-height: 1.2;
            text-align: center;
        }
        .btn:active { transform: scale(0.95); }
        .btn-outline-success  { border-color: var(--green);  color: var(--green); }
        .btn-outline-success:active  { background: var(--green-dim); }
        .btn-outline-primary  { border-color: var(--accent); color: var(--accent); }
        .btn-outline-primary:active  { background: rgba(108,99,255,0.12); }
        .btn-outline-secondary { border-color: var(--border); color: var(--text-muted); }
        .btn-outline-secondary:active { background: rgba(255,255,255,0.05); color: var(--text); }
        .btn-outline-danger   { border-color: var(--danger); color: var(--danger); }
        .btn-outline-danger:active   { background: rgba(255,101,132,0.12); }
        .btn-sm { font-size: 0.75rem; padding: 6px 12px; flex-direction: row; height: auto; }
        .btn-success { background: var(--green); border-color: var(--green); color: #000; font-weight: 700; }
        .btn-success:active { opacity: 0.85; }

        /* ── Description Button ── */
        .detail-desc-btn {
            width: 100%;
            text-align: left;
            padding: 10px;
            background: rgba(255,255,255,0.03);
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.08);
            color: var(--text);
            cursor: pointer;
            font-family: inherit;
            font-size: 0.8rem;
        }
        .detail-desc-btn:hover { background: rgba(255,255,255,0.06); }

        /* ── Modal Sheet (bottom-sheet style, matching vidbat) ── */
        .rv-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            z-index: 200;
            display: none;
            align-items: flex-end;
            justify-content: center;
            padding: 0;
        }
        .rv-modal-overlay.active { display: flex; }
        @media (min-width: 600px) {
            .rv-modal-overlay { align-items: center; padding: 20px; }
        }
        .rv-modal-sheet {
            width: 100%;
            max-width: 480px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px 12px 0 0;
            display: flex;
            flex-direction: column;
            max-height: 85dvh;
            overflow: hidden;
        }
        @media (min-width: 600px) {
            .rv-modal-sheet { border-radius: 10px; max-height: 80dvh; }
        }
        .rv-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px 10px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        .rv-modal-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text);
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .rv-modal-close {
            width: 32px;
            height: 32px;
            background: transparent;
            border: 1px solid var(--border);
            color: var(--muted);
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-tap-highlight-color: transparent;
        }
        .rv-modal-close:active { color: var(--danger); border-color: var(--danger); }
        .rv-modal-body {
            padding: 20px;
            overflow-y: auto;
            color: var(--text);
            flex: 1;
            min-height: 0;
        }
        .rv-modal-footer {
            padding: 10px 14px;
            border-top: 1px solid var(--border);
            flex-shrink: 0;
            display: flex;
            gap: 8px;
        }
        .rv-confirm-btn {
            flex: 1;
            min-height: 48px;
            background: var(--green);
            border: none;
            color: #000;
            font-family: inherit;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 4px;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            transition: opacity 0.1s;
        }
        .rv-confirm-btn:disabled { opacity: 0.35; cursor: not-allowed; }
        .rv-confirm-btn:active   { opacity: 0.8; }

        /* ── Form Controls (inside modals) ── */
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid rgba(var(--muted-border-rgb), 0.2);
            font-size: 0.9rem;
            background: var(--bg);
            color: var(--text);
            font-family: inherit;
            transition: border-color 0.15s ease;
        }
        .form-control:focus { outline: none; border-color: var(--accent); }
        textarea.form-control { resize: vertical; min-height: 80px; }

        /* ── Rembg Modal ── */
        .rembg-color-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        .rembg-swatch {
            width: 36px;
            height: 36px;
            border-radius: 4px;
            border: 2px solid rgba(255,255,255,0.15);
            flex-shrink: 0;
            cursor: pointer;
            transition: border-color 0.15s;
        }
        .rembg-swatch:active { border-color: var(--accent); }
        .rembg-hex-input {
            flex: 1;
            padding: 8px 10px;
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 4px;
            font-family: inherit;
            font-size: 0.9rem;
            letter-spacing: 1px;
        }
        .rembg-hex-input:focus { outline: none; border-color: var(--accent); }
        .rembg-pick-btn {
            padding: 8px 12px;
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            border-radius: 4px;
            font-family: inherit;
            font-size: 0.7rem;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            line-height: 1.3;
            text-align: center;
            -webkit-tap-highlight-color: transparent;
        }
        .rembg-pick-btn:active { background: rgba(108,99,255,0.15); }
        .rembg-info-row {
            padding: 8px 14px;
            font-size: 0.7rem;
            color: var(--muted);
            flex-shrink: 0;
        }

        /* ── Color Sampler Modal ── */
        .sampler-canvas-wrap {
            flex: 1;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000;
            min-height: 0;
            cursor: crosshair;
            touch-action: none;
        }
        #samplerCanvas { display: block; max-width: 100%; max-height: 100%; }
        .sampler-result-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
            background: var(--card);
        }
        .sampler-result-swatch {
            width: 40px;
            height: 40px;
            border-radius: 4px;
            border: 2px solid rgba(255,255,255,0.15);
            flex-shrink: 0;
        }
        .sampler-result-hex {
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 2px;
            color: var(--text);
            font-family: 'DM Mono', 'Fira Mono', monospace;
        }
        .sampler-hint {
            font-size: 0.65rem;
            color: var(--muted);
            padding: 6px 14px;
            flex-shrink: 0;
        }
        /* sampler modal sheet needs taller max-height */
        #samplerModal .rv-modal-sheet { max-height: 92dvh; }

        /* ── Assign Modal ── */
        .rv-assign-current {
            padding: 8px 14px;
            font-size: 0.7rem;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            min-height: 36px;
        }
        .rv-assign-current .node-name { color: var(--green); font-weight: 700; }
        .rv-unassign-btn {
            padding: 3px 8px;
            border: 1px solid var(--danger);
            background: transparent;
            color: var(--danger);
            border-radius: 3px;
            font-size: 0.6rem;
            font-family: inherit;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            white-space: nowrap;
        }
        .rv-tree-toolbar {
            padding: 6px 10px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 6px;
            flex-shrink: 0;
            flex-wrap: wrap;
        }
        .rv-tree-toolbar input {
            flex: 1;
            min-width: 100px;
            padding: 5px 8px;
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 4px;
            font-family: inherit;
            font-size: 0.75rem;
        }
        .rv-tree-toolbar input:focus { outline: none; border-color: var(--accent); }
        .rv-tree-toolbar select {
            padding: 5px 6px;
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 4px;
            font-family: inherit;
            font-size: 0.7rem;
        }
        .rv-tree-add-btn {
            padding: 5px 12px;
            background: var(--accent);
            border: none;
            color: #fff;
            border-radius: 4px;
            font-family: inherit;
            font-size: 0.7rem;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            -webkit-tap-highlight-color: transparent;
        }
        .rv-tree-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 8px 6px;
            background: var(--bg);
            min-height: 100px;
        }
        .rv-tree-scroll::-webkit-scrollbar { width: 3px; }
        .rv-tree-scroll::-webkit-scrollbar-thumb { background: var(--border); }

        /* jstree dark theme overrides */
        .jstree-default .jstree-anchor { color: var(--text) !important; line-height: 28px; height: 28px; }
        .jstree-default .jstree-hovered { background: rgba(108,99,255,0.12) !important; border-radius: 4px; }
        .jstree-default .jstree-clicked { background: rgba(108,99,255,0.25) !important; color: var(--accent) !important; border-radius: 4px; }
        .jstree-default .jstree-icon { color: var(--muted); }
        .jstree-default { background: transparent !important; color: var(--text); }
        .jstree-container-ul { background: transparent !important; }

        /* assigned badge in meta row */
        .rv-assigned-badge {
            color: var(--accent);
            font-weight: 700;
            font-size: 0.8rem;
            max-width: 160px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/themes/default/style.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>

<div class="detail-layout">
    <div class="detail-player-wrapper">
        <video id="detailVideoPlayer" controls playsinline controlsList="nodownload">
            <source src="<?= htmlspecialchars($video['url']) ?>" type="video/mp4">
        </video>
    </div>

    <div class="detail-content">
        <div class="detail-title" id="displayTitle"><?= htmlspecialchars($video['name'] ?: 'Video #' . $video['id']) ?></div>
        <div class="detail-meta-row">
            <span><strong>ID:</strong> <?= $video['id'] ?></span>
            <span id="displayCategory"><strong>Cat:</strong> <?= htmlspecialchars($video['category_name'] ?: '-') ?></span>
            <span><strong>Size:</strong> <?= fmtSize((int)($video['file_size'] ?? 0)) ?></span>
            <span><strong>Dur:</strong> <?= fmtDur((float)($video['duration'] ?? 0)) ?></span>
            <span class="rv-assigned-badge" id="assignedBadge" style="display:none;"></span>
        </div>

        <div class="detail-actions-grid">
            <button class="btn btn-outline-success" onclick="openFrameExtractor()">✂️<br>Frame</button>
            <?php if ($video['animatic_id']): ?>
                <button class="btn btn-outline-primary" onclick="openAnimatic(<?= (int)$video['animatic_id'] ?>)">🎬<br>Animatic</button>
            <?php endif; ?>
            <button class="btn btn-outline-success"
                    id="btnRembg"
                    data-thumbnail="<?= htmlspecialchars($video['thumbnail'] ?? '') ?>"
                    data-animatic-id="<?= (int)($video['animatic_id'] ?? 0) ?>"
                    onclick="openRembgModal()">◩<br>Rembg</button>
            <button class="btn btn-outline-secondary" onclick="regenerateThumb(this)">🌇<br>Thumb</button>
            <button class="btn btn-outline-primary" id="btnAssign" onclick="openAssignModal()">⬡<br>Assign</button>
            <button class="btn btn-outline-secondary" onclick="openPlaylistModal()">📃<br>Playlst</button>
            <a href="<?= htmlspecialchars($video['url']) ?>" download class="btn btn-outline-primary" target="_blank">⬇️<br>Dwnload</a>
            <button class="btn btn-outline-danger" onclick="deleteVideo()">❌<br>Delete</button>
        </div>

        <button class="detail-desc-btn" onclick="document.getElementById('descModal').classList.add('active')">
            📄 <strong>Description:</strong>
            <span id="displayDescSnippet"><?= htmlspecialchars(mb_substr($video['description'] ?? 'None', 0, 60)) ?>…</span>
            <div style="font-size:0.72rem; color:var(--text-muted); margin-top:4px;">(Tap to view full)</div>
        </button>
    </div>
</div>

<!-- ══ EDIT VIDEO MODAL ══ -->
<div id="editModal" class="rv-modal-overlay">
    <div class="rv-modal-sheet">
        <div class="rv-modal-header">
            <span class="rv-modal-title">✏️ Edit Video</span>
            <button class="rv-modal-close" onclick="document.getElementById('editModal').classList.remove('active')">✕</button>
        </div>
        <form id="editForm">
            <div class="rv-modal-body">
                <div class="form-group">
                    <label>Internal Name</label>
                    <input type="text" class="form-control" id="editName" value="<?= htmlspecialchars($video['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" id="editDescription" rows="4"><?= htmlspecialchars($video['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select class="form-control" id="editCategory">
                        <option value="">None</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $video['category_id'] == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" id="editActive" <?= $video['is_active'] ? 'checked' : '' ?>> Active
                    </label>
                </div>
            </div>
            <div class="rv-modal-footer">
                <button type="button" class="rv-confirm-btn"
                        style="background:var(--bg); border:1px solid var(--border); color:var(--text); flex:0.5;"
                        onclick="document.getElementById('editModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="rv-confirm-btn">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ ASSIGN TREE MODAL ══ -->
<div class="rv-modal-overlay" id="assignModal">
    <div class="rv-modal-sheet">
        <div class="rv-modal-header">
            <span class="rv-modal-title">⬡ Assign to Story Node</span>
            <button class="rv-modal-close" onclick="closeAssignModal()">✕</button>
        </div>
        <div class="rv-assign-current" id="assignCurrentStrip">
            <span id="assignCurrentText" style="color:var(--muted);">No assignment</span>
            <button class="rv-unassign-btn" id="btnUnassign" style="display:none;" onclick="unassignVideo()">Unassign</button>
        </div>
        <div class="rv-tree-toolbar">
            <input type="text" id="newNodeName" placeholder="New node name…">
            <select id="newNodeType">
                <option value="folder">Folder</option>
                <option value="episode">Episode</option>
                <option value="sequence">Sequence</option>
                <option value="scene">Scene</option>
                <option value="other">Other</option>
            </select>
            <button class="rv-tree-add-btn" onclick="createTreeNode()">+ Add</button>
        </div>
        <div class="rv-tree-scroll">
            <div id="assignTree">Loading…</div>
        </div>
        <div class="rv-modal-footer">
            <button class="rv-confirm-btn" id="btnAssignConfirm" disabled onclick="confirmAssign()">
                Assign to Selected Node
            </button>
        </div>
    </div>
</div>

<!-- ══ ADD TO PLAYLIST MODAL ══ -->
<div id="addToPlaylistModal" class="rv-modal-overlay">
    <div class="rv-modal-sheet">
        <div class="rv-modal-header">
            <span class="rv-modal-title">📃 Add to Playlist</span>
            <button class="rv-modal-close" onclick="document.getElementById('addToPlaylistModal').classList.remove('active')">✕</button>
        </div>
        <div class="rv-modal-body">
            <div id="playlistCheckboxes">Loading…</div>
        </div>
        <div class="rv-modal-footer">
            <button type="button" class="rv-confirm-btn"
                    style="background:var(--bg); border:1px solid var(--border); color:var(--text); flex:0.5;"
                    onclick="document.getElementById('addToPlaylistModal').classList.remove('active')">Cancel</button>
            <button type="button" class="rv-confirm-btn" onclick="savePlaylists()">Save</button>
        </div>
    </div>
</div>

<!-- ══ DESCRIPTION MODAL ══ -->
<div id="descModal" class="rv-modal-overlay" style="z-index: 300;">
    <div class="rv-modal-sheet">
        <div class="rv-modal-header">
            <span class="rv-modal-title">📄 Full Description</span>
            <button class="rv-modal-close" onclick="document.getElementById('descModal').classList.remove('active')">✕</button>
        </div>
        <div class="rv-modal-body" id="descModalBody"
             style="white-space: pre-wrap; font-size: 0.9rem; line-height: 1.6;"><?= htmlspecialchars($video['description'] ?: 'No description.') ?></div>
        <div class="rv-modal-footer">
            <button type="button" class="rv-confirm-btn"
                    style="background:var(--bg); border:1px solid var(--border); color:var(--text);"
                    onclick="document.getElementById('descModal').classList.remove('active')">Back</button>
        </div>
    </div>
</div>

<!-- ══ REMBG CONFIRMATION MODAL ══ -->
<div id="rembgModal" class="rv-modal-overlay" style="z-index: 400;">
    <div class="rv-modal-sheet">
        <div class="rv-modal-header">
            <span class="rv-modal-title">◩ Remove Background</span>
            <button class="rv-modal-close" onclick="closeRembgModal()">✕</button>
        </div>

        <div class="rembg-color-row">
            <div class="rembg-swatch" id="rembgSwatch" onclick="syncSwatchFromInput()" title="Current color"></div>
            <input type="text" class="rembg-hex-input" id="rembgHexInput" value="#00FB00" maxlength="7"
                   oninput="onRembgHexInput()" placeholder="#00FB00">
            <button class="rembg-pick-btn" onclick="openSamplerModal()">Pick from<br>Thumb</button>
        </div>

        <div class="rembg-info-row">
            Target animatic: <span id="rembgAnimaticId" style="color:var(--accent); font-weight:700;">—</span>
            &nbsp;|&nbsp; Source video: <span id="rembgVideoId" style="color:var(--accent); font-weight:700;">—</span>
        </div>

        <div class="rv-modal-footer" style="gap:8px;">
            <button class="rv-confirm-btn"
                    style="background:var(--bg); border:1px solid var(--border); color:var(--text); flex:0.5;"
                    onclick="closeRembgModal()">Cancel</button>
            <button class="rv-confirm-btn" id="btnRembgConfirm" onclick="confirmRembg()">Queue Removal</button>
        </div>
    </div>
</div>

<!-- ══ COLOR SAMPLER MODAL ══ -->
<div id="samplerModal" class="rv-modal-overlay" style="z-index: 500;">
    <div class="rv-modal-sheet">
        <div class="rv-modal-header">
            <span class="rv-modal-title">🎨 Pick Green Color</span>
            <button class="rv-modal-close" onclick="closeSamplerModal()">✕</button>
        </div>

        <div class="sampler-hint">Tap the green area on the thumbnail to sample its color.</div>

        <div class="sampler-canvas-wrap" id="samplerCanvasWrap">
            <canvas id="samplerCanvas"></canvas>
        </div>

        <div class="sampler-result-row">
            <div class="sampler-result-swatch" id="samplerSwatch" style="background:#00FB00;"></div>
            <span class="sampler-result-hex" id="samplerHex">#00FB00</span>
            <span style="font-size:0.65rem; color:var(--muted); margin-left:auto;">Tap to sample</span>
        </div>

        <div class="rv-modal-footer" style="gap:8px;">
            <button class="rv-confirm-btn"
                    style="background:var(--bg); border:1px solid var(--border); color:var(--text); flex:0.5;"
                    onclick="closeSamplerModal()">Cancel</button>
            <button class="rv-confirm-btn" onclick="useSampledColor()">Use This Color</button>
        </div>
    </div>
</div>

<!-- Modules Rendering -->
<?= $videoExtractor->render() ?>
<?= $imageEditor->render() ?>

<script>
(function () {
    'use strict';

    // ── Static data from PHP ──
    const videoId      = <?= $id ?>;
    const videoUrl     = <?= json_encode($video['url']) ?>;
    const animaticId   = <?= (int)($video['animatic_id'] ?? 0) ?>;
    const thumbnailUrl = <?= json_encode($video['thumbnail'] ?? '') ?>;

    // ── Rembg / Sampler State ──
    let rembgThumbnailUrl = thumbnailUrl;
    let samplerPickedColor = '#00FB00';
    let samplerImg = null;
    const SAMPLE_RADIUS = 10;

    // ════════════════════════════════════
    // FRAME EXTRACTOR
    // ════════════════════════════════════
    window.openFrameExtractor = function () {
        const p = document.getElementById('detailVideoPlayer');
        if (p) p.pause();
        if (window.VideoFrameExtractor) window.VideoFrameExtractor.open(videoUrl, videoId);
    };

    // ════════════════════════════════════
    // OPEN ANIMATIC
    // ════════════════════════════════════
    window.openAnimatic = function (animId) {
        if (window.parent && typeof window.parent.showEntityFormInModal === 'function') {
            window.parent.showEntityFormInModal('animatics', animId);
        } else {
            window.open('animatics_crud.php?id=' + animId, '_blank');
        }
    };

    // ════════════════════════════════════
    // REGENERATE THUMBNAIL
    // ════════════════════════════════════
    window.regenerateThumb = function (btn) {
        if (!confirm('Regenerate thumbnail?')) return;
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '…';
        fetch('/video_admin_api.php?action=regenerate_thumbnail', {
            method: 'POST',
            body: JSON.stringify({ id: videoId })
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'ok') {
                if (typeof Toast !== 'undefined') Toast.show('Thumbnail updated', 'success');
            } else {
                if (typeof Toast !== 'undefined') Toast.show(d.message || 'Error', 'error');
            }
        })
        .finally(() => { btn.disabled = false; btn.innerHTML = origHtml; });
    };

    // ════════════════════════════════════
    // DELETE VIDEO
    // ════════════════════════════════════
    window.deleteVideo = function () {
        if (!confirm('Delete video permanently?')) return;
        fetch('/video_admin_api.php?action=delete_video', {
            method: 'POST',
            body: JSON.stringify({ id: videoId })
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'ok') {
                if (typeof Toast !== 'undefined') Toast.show('Deleted');
                if (window.parent && typeof window.parent.closeModal === 'function') {
                    window.parent.closeModal();
                }
            } else {
                if (typeof Toast !== 'undefined') Toast.show(d.message, 'error');
            }
        });
    };

    // ════════════════════════════════════
    // EDIT FORM
    // ════════════════════════════════════
    document.getElementById('editForm').onsubmit = function (e) {
        e.preventDefault();
        const name = document.getElementById('editName').value;
        const desc = document.getElementById('editDescription').value;
        const catEl = document.getElementById('editCategory');
        const catText = catEl.options[catEl.selectedIndex].text;

        fetch('/video_admin_api.php?action=update_video', {
            method: 'POST',
            body: JSON.stringify({
                id: videoId,
                name: name,
                description: desc,
                category_id: catEl.value || null,
                is_active: document.getElementById('editActive').checked ? 1 : 0
            })
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'ok') {
                document.getElementById('editModal').classList.remove('active');
                if (typeof Toast !== 'undefined') Toast.show('Saved');
                // Update visible UI
                document.getElementById('displayTitle').textContent = name;
                document.getElementById('displayCategory').innerHTML =
                    '<strong>Cat:</strong> ' + (catEl.value ? escH(catText) : '-');
                const snippet = desc ? desc.substring(0, 60) + '…' : 'None';
                document.getElementById('displayDescSnippet').textContent = snippet;
                document.getElementById('descModalBody').textContent = desc || 'No description.';
                // Notify parent if needed
                if (window.parent) {
                    window.parent.postMessage({ type: 'video_updated', videoId: videoId, name: name }, '*');
                }
            } else {
                if (typeof Toast !== 'undefined') Toast.show(d.message, 'error');
            }
        });
    };

    // ════════════════════════════════════
    // PLAYLISTS
    // ════════════════════════════════════
    // ════════════════════════════════════
    // PLAYLISTS
    // ════════════════════════════════════
    window.openPlaylistModal = function () {
        const div = document.getElementById('playlistCheckboxes');
        div.innerHTML = 'Loading…';
        document.getElementById('addToPlaylistModal').classList.add('active');

        Promise.all([
            fetch('/video_admin_api.php?action=list_playlists').then(r => r.json()),
            fetch('/video_admin_api.php?action=get_video&id=' + videoId).then(r => r.json())
        ]).then(([plData, vidData]) => {
            const playlists = (plData.status === 'ok') ? plData.playlists : [];
            const currentIds = (vidData.status === 'ok' && vidData.video.playlists)
                ? vidData.video.playlists.map(String) : [];
            div.innerHTML = playlists.map(p =>
                `<label style="display:block;padding:8px;cursor:pointer;border-bottom:1px solid var(--border);font-size:0.85rem;">
                    <input type="checkbox" value="${p.id}" style="margin-right:8px;accent-color:var(--accent);"
                           ${currentIds.includes(String(p.id)) ? 'checked' : ''}>
                    ${escH(p.name)}
                 </label>`
            ).join('') || '<div style="color:var(--muted);padding:8px;">No playlists found.</div>';
        });
    };

    window.savePlaylists = function () {
        const pids = Array.from(document.querySelectorAll('#playlistCheckboxes input:checked'))
                          .map(cb => cb.value);
        fetch('/video_admin_api.php?action=sync_video_playlists', {
            method: 'POST',
            body: JSON.stringify({ video_id: videoId, playlist_ids: pids })
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'ok') {
                document.getElementById('addToPlaylistModal').classList.remove('active');
                if (typeof Toast !== 'undefined') Toast.show('Playlists updated');
            } else {
                if (typeof Toast !== 'undefined') Toast.show(d.message, 'error');
            }
        });
    };

    // ════════════════════════════════════
    // REMBG MODAL
    // ════════════════════════════════════
    window.openRembgModal = function () {
        setRembgColor('#00FB00');
        document.getElementById('rembgVideoId').textContent   = '#' + videoId;
        document.getElementById('rembgAnimaticId').textContent = animaticId ? '#' + animaticId : 'none';
        document.getElementById('rembgModal').classList.add('active');
    };

    window.closeRembgModal = function () {
        document.getElementById('rembgModal').classList.remove('active');
    };

    function setRembgColor(hex) {
        hex = hex.toUpperCase();
        if (!/^#[0-9A-F]{6}$/.test(hex)) return;
        document.getElementById('rembgHexInput').value = hex;
        document.getElementById('rembgSwatch').style.background = hex;
    }

    window.onRembgHexInput = function () {
        const val = document.getElementById('rembgHexInput').value.trim();
        if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
            document.getElementById('rembgSwatch').style.background = val;
        }
    };

    window.syncSwatchFromInput = function () {
        window.onRembgHexInput();
    };

    window.confirmRembg = function () {
        const hex = document.getElementById('rembgHexInput').value.trim().toUpperCase();
        if (!/^#[0-9A-F]{6}$/.test(hex)) {
            if (typeof Toast !== 'undefined') Toast.show('Invalid hex color', 'error');
            return;
        }
        const btn = document.getElementById('btnRembgConfirm');
        const origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Queuing…';

        fetch('/video_admin_api.php?action=queue_rembg', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: videoId, chromakey_color: hex })
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'ok') {
                closeRembgModal();
                if (typeof Toast !== 'undefined') Toast.show('Background removal queued ✓', 'success');
            } else {
                if (typeof Toast !== 'undefined') Toast.show(d.message || 'Error', 'error');
            }
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = origText;
        });
    };

    // ════════════════════════════════════
    // COLOR SAMPLER MODAL
    // ════════════════════════════════════
    window.openSamplerModal = function () {
        if (!rembgThumbnailUrl) {
            if (typeof Toast !== 'undefined') Toast.show('No thumbnail available', 'error');
            return;
        }
        samplerPickedColor = document.getElementById('rembgHexInput').value.trim() || '#00FB00';
        document.getElementById('samplerSwatch').style.background = samplerPickedColor;
        document.getElementById('samplerHex').textContent = samplerPickedColor.toUpperCase();
        document.getElementById('samplerModal').classList.add('active');
        requestAnimationFrame(() => { loadSamplerImage(rembgThumbnailUrl); });
    };

    window.closeSamplerModal = function () {
        document.getElementById('samplerModal').classList.remove('active');
    };

    function loadSamplerImage(url) {
        const canvas = document.getElementById('samplerCanvas');
        const wrap   = document.getElementById('samplerCanvasWrap');
        const ctx    = canvas.getContext('2d');
        const img    = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function () {
            samplerImg = img;
            const wrapW = wrap.clientWidth;
            const wrapH = wrap.clientHeight;
            const scale = Math.min(wrapW / img.naturalWidth, wrapH / img.naturalHeight);
            canvas.width  = Math.round(img.naturalWidth  * scale);
            canvas.height = Math.round(img.naturalHeight * scale);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        };
        img.onerror = function () {
            if (typeof Toast !== 'undefined') Toast.show('Could not load thumbnail', 'error');
        };
        img.src = url;
    }

    function sampleCanvasAt(canvasX, canvasY) {
        const canvas = document.getElementById('samplerCanvas');
        const ctx    = canvas.getContext('2d');
        const r = SAMPLE_RADIUS;
        let totalR = 0, totalG = 0, totalB = 0, count = 0;
        const x0 = Math.max(0, Math.round(canvasX - r));
        const y0 = Math.max(0, Math.round(canvasY - r));
        const x1 = Math.min(canvas.width  - 1, Math.round(canvasX + r));
        const y1 = Math.min(canvas.height - 1, Math.round(canvasY + r));
        const imageData = ctx.getImageData(x0, y0, x1 - x0 + 1, y1 - y0 + 1);
        const data = imageData.data;
        for (let py = y0; py <= y1; py++) {
            for (let px = x0; px <= x1; px++) {
                const dx = px - canvasX, dy = py - canvasY;
                if (dx * dx + dy * dy <= r * r) {
                    const idx = ((py - y0) * (x1 - x0 + 1) + (px - x0)) * 4;
                    totalR += data[idx]; totalG += data[idx + 1]; totalB += data[idx + 2];
                    count++;
                }
            }
        }
        if (count === 0) return null;
        const avgR = Math.round(totalR / count);
        const avgG = Math.round(totalG / count);
        const avgB = Math.round(totalB / count);
        return '#' + [avgR, avgG, avgB].map(v => v.toString(16).padStart(2, '0')).join('').toUpperCase();
    }

    function drawIndicator(canvasX, canvasY) {
        const canvas = document.getElementById('samplerCanvas');
        const ctx    = canvas.getContext('2d');
        if (samplerImg) ctx.drawImage(samplerImg, 0, 0, canvas.width, canvas.height);
        ctx.beginPath();
        ctx.arc(canvasX, canvasY, SAMPLE_RADIUS + 2, 0, Math.PI * 2);
        ctx.strokeStyle = 'rgba(0,0,0,0.7)';
        ctx.lineWidth = 2.5;
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(canvasX, canvasY, SAMPLE_RADIUS, 0, Math.PI * 2);
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 1.5;
        ctx.stroke();
    }

    function handleSampleAt(clientX, clientY) {
        const canvas = document.getElementById('samplerCanvas');
        const rect   = canvas.getBoundingClientRect();
        const canvasX = clientX - rect.left;
        const canvasY = clientY - rect.top;
        const hex = sampleCanvasAt(canvasX, canvasY);
        if (!hex) return;
        samplerPickedColor = hex;
        drawIndicator(canvasX, canvasY);
        document.getElementById('samplerSwatch').style.background = hex;
        document.getElementById('samplerHex').textContent = hex;
    }

    document.getElementById('samplerCanvas').addEventListener('click', function (e) {
        handleSampleAt(e.clientX, e.clientY);
    });
    document.getElementById('samplerCanvas').addEventListener('touchend', function (e) {
        e.preventDefault();
        const touch = e.changedTouches[0];
        handleSampleAt(touch.clientX, touch.clientY);
    }, { passive: false });

    window.useSampledColor = function () {
        setRembgColor(samplerPickedColor);
        closeSamplerModal();
    };

    // ════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════
    function escH(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Close modals on overlay click
    document.querySelectorAll('.rv-modal-overlay').forEach(el => {
        el.addEventListener('click', function (e) {
            if (e.target === el) el.classList.remove('active');
        });
    });

    // Escape key closes top-most modal
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        // Close in reverse z-index order
        const order = ['samplerModal', 'rembgModal', 'descModal', 'addToPlaylistModal', 'editModal'];
        for (const id of order) {
            const el = document.getElementById(id);
            if (el && el.classList.contains('active')) {
                el.classList.remove('active');
                break;
            }
        }
    });

    // Init swatch display
    setRembgColor('#00FB00');

    // Boot: fetch current assignment for this video
    fetchAssignment();

})();
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>
<script>
(function () {
    'use strict';

    // ── Video ID (from PHP, mirrored here since this is a separate IIFE) ──
    const videoId = <?= $id ?>;

    // ── Assign Tree State ──
    let assignTreeInited = false;
    let assignNodeId     = null;
    let assignNodeName   = '';
    let assignmentCache  = null; // null = not yet fetched, false = no assignment

    // ── API base (same page for tree actions, video_admin_api for assignment) ──
    // Tree fetch/create/assign/unassign all go to view_vidbat_review.php equivalents
    // but here we use video_admin_api.php where available, else the inline handler
    // Note: tree_fetch, tree_create_node, tree_assign_batch, tree_unassign_batch,
    //       tree_get_assignment are all served by view_vidbat_review.php?api_action=...
    // We replicate that here via a dedicated endpoint reference.
    // vidbat uses ?api_action= on itself; we call view_vidbat_review.php directly.
    const TREE_API = 'view_vidbat_review.php';

    function escH(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ── Assignment badge ──
    function updateAssignBadge(assignment) {
        const badge   = document.getElementById('assignedBadge');
        const btn     = document.getElementById('btnAssign');
        if (assignment && assignment.node_name) {
            badge.textContent = '⬡ ' + assignment.node_name;
            badge.style.display = 'inline';
            if (btn) btn.style.borderColor = 'var(--green)';
            if (btn) btn.style.color = 'var(--green)';
        } else {
            badge.style.display = 'none';
            if (btn) btn.style.borderColor = '';
            if (btn) btn.style.color = '';
        }
    }

    function fetchAssignment() {
        fetch(TREE_API + '?api_action=tree_get_assignment&video_id=' + videoId)
            .then(r => r.json())
            .then(d => {
                if (d.status === 'ok') {
                    assignmentCache = d.assignment || false;
                    updateAssignBadge(assignmentCache);
                }
            });
    }

    // ── Open assign modal ──
    window.openAssignModal = function () {
        if (assignmentCache && assignmentCache.node_name) {
            document.getElementById('assignCurrentText').innerHTML =
                'Currently: <span class="node-name">' + escH(assignmentCache.node_name) + '</span>';
            document.getElementById('btnUnassign').style.display = 'inline-block';
        } else {
            document.getElementById('assignCurrentText').innerHTML =
                '<span style="color:var(--muted);">No assignment yet</span>';
            document.getElementById('btnUnassign').style.display = 'none';
        }

        assignNodeId   = null;
        assignNodeName = '';
        document.getElementById('btnAssignConfirm').disabled = true;
        document.getElementById('assignModal').classList.add('active');

        if (!assignTreeInited) initAssignTree();
        else $('#assignTree').jstree('refresh');
    };

    window.closeAssignModal = function () {
        document.getElementById('assignModal').classList.remove('active');
    };

    function initAssignTree() {
        assignTreeInited = true;
        $('#assignTree').jstree({
            core: {
                data: {
                    url: TREE_API + '?api_action=tree_fetch',
                    dataType: 'json',
                    dataFilter: function (raw) {
                        try {
                            const j = JSON.parse(raw);
                            return JSON.stringify(j.status === 'ok' ? j.tree : []);
                        } catch (e) { return '[]'; }
                    }
                },
                themes: { name: 'default', dots: true, icons: true },
                check_callback: false,
            },
            plugins: ['types', 'state'],
            types: {
                folder:   { icon: 'bi bi-folder2' },
                episode:  { icon: 'bi bi-film' },
                sequence: { icon: 'bi bi-collection-play' },
                scene:    { icon: 'bi bi-camera-video' },
                other:    { icon: 'bi bi-tag' },
            },
        })
        .on('select_node.jstree', function (e, data) {
            assignNodeId   = data.node.data.db_id;
            assignNodeName = data.node.text;
            document.getElementById('btnAssignConfirm').disabled = false;
        })
        .on('deselect_node.jstree', function () {
            assignNodeId   = null;
            assignNodeName = '';
            document.getElementById('btnAssignConfirm').disabled = true;
        });
    }

    window.createTreeNode = function () {
        const name     = document.getElementById('newNodeName').value.trim();
        const nodeType = document.getElementById('newNodeType').value;
        if (!name) return;

        const sel      = $('#assignTree').jstree('get_selected', true);
        const parentId = sel.length ? sel[0].data.db_id : null;

        fetch(TREE_API + '?api_action=tree_create_node', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ name, node_type: nodeType, parent_id: parentId }),
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'ok') {
                document.getElementById('newNodeName').value = '';
                $('#assignTree').jstree('refresh');
                if (typeof Toast !== 'undefined') Toast.show('Node created', 'success');
            } else {
                if (typeof Toast !== 'undefined') Toast.show(d.message || 'Error', 'error');
            }
        });
    };

    window.confirmAssign = function () {
        if (!assignNodeId) return;

        const btn = document.getElementById('btnAssignConfirm');
        btn.disabled = true;
        btn.textContent = 'Assigning…';

        fetch(TREE_API + '?api_action=tree_assign_batch', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ node_id: assignNodeId, video_ids: [videoId] }),
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'ok') {
                assignmentCache = { node_id: assignNodeId, node_name: assignNodeName };
                updateAssignBadge(assignmentCache);
                closeAssignModal();
                if (typeof Toast !== 'undefined') Toast.show('Assigned to: ' + assignNodeName, 'success');
            } else {
                if (typeof Toast !== 'undefined') Toast.show(d.message || 'Error', 'error');
            }
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Assign to Selected Node';
        });
    };

    window.unassignVideo = function () {
        fetch(TREE_API + '?api_action=tree_unassign_batch', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ video_ids: [videoId] }),
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'ok') {
                assignmentCache = false;
                updateAssignBadge(false);
                document.getElementById('btnUnassign').style.display = 'none';
                document.getElementById('assignCurrentText').innerHTML =
                    '<span style="color:var(--muted);">No assignment yet</span>';
                if (typeof Toast !== 'undefined') Toast.show('Unassigned', 'success');
            }
        });
    };

    // Escape key also closes assign modal
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const m = document.getElementById('assignModal');
            if (m && m.classList.contains('active')) {
                closeAssignModal();
            }
        }
    });

})();
</script>
<script src="/js/theme-manager.js"></script>


<?php //echo $eruda; ?>

</body>
</html>
