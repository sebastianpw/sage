<?php
// view_storyboard_paginated.php - Paginated storyboard view with bulk delete
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\UI\Modules\ModuleRegistry;

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

if (isset($_GET['ajax_storyboard_json']) && $_GET['ajax_storyboard_json'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $search = $_GET['search'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = 10;
    
    $where = "WHERE 1=1";
    $params = [];
    if ($search !== '') {
        $where .= " AND (name LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM storyboards $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $pageSize));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $pageSize;
    
    $stmt = $pdo->prepare("SELECT id, name, category, is_archived FROM storyboards $where ORDER BY updated_at DESC, id DESC LIMIT ? OFFSET ?");
    $bindIdx = 1;
    foreach ($params as $p) $stmt->bindValue($bindIdx++, $p, PDO::PARAM_STR);
    $stmt->bindValue($bindIdx++, $pageSize, PDO::PARAM_INT);
    $stmt->bindValue($bindIdx, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'storyboards' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'page' => $page,
        'totalPages' => $totalPages,
        'total' => $total
    ]);
    exit;
}

function renderStoryboardBrowseFragment(PDO $pdo, int $page, int $pageSize, string $search = ''): string
{
    $pageSize = max(1, $pageSize);

    $where = "WHERE 1=1";
    $params = [];
    if ($search !== '') {
        $where .= " AND (sb.name LIKE ? OR sb.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM storyboards sb $where");
    $countStmt->execute($params);
    $totalStoryboards = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalStoryboards / $pageSize));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $pageSize;

    $stmt = $pdo->prepare("
        SELECT
            sb.id,
            sb.name,
            sb.description,
            sb.directory,
            sb.category,
            sb.is_archived,
            sb.updated_at,
            COALESCE(fc.frame_count, 0) AS frame_count,
            pf.filename AS preview_filename
        FROM storyboards sb
        LEFT JOIN (
            SELECT storyboard_id, COUNT(*) AS frame_count
            FROM storyboard_frames
            GROUP BY storyboard_id
        ) fc ON fc.storyboard_id = sb.id
        LEFT JOIN storyboard_frames sfp
            ON sfp.id = (
                SELECT sf2.id
                FROM storyboard_frames sf2
                WHERE sf2.storyboard_id = sb.id
                ORDER BY sf2.sort_order ASC, sf2.id ASC
                LIMIT 1
            )
        LEFT JOIN frames pf ON sfp.frame_id = pf.id
        $where
        ORDER BY sb.updated_at DESC, sb.id DESC
        LIMIT ? OFFSET ?
    ");
    
    $bindIdx = 1;
    foreach ($params as $p) {
        $stmt->bindValue($bindIdx++, $p, PDO::PARAM_STR);
    }
    $stmt->bindValue($bindIdx++, $pageSize, PDO::PARAM_INT);
    $stmt->bindValue($bindIdx, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $storyboards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_start();
    ?>
    <div class="forge-header-bar">
        <div class="forge-logo">
            <div class="forge-logo-icon">⬛</div>
            <span>Storyboards</span>
        </div>
        
        <input type="text" id="sb-search-input" class="forge-page-input" style="width: 150px; text-align: left;" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
        
        <div class="forge-save-status">Tap a storyboard to open it</div>
    </div>

    <div class="forge-pagination-bar" style="justify-content:flex-start;">
        <a class="forge-page-btn sb-page-link <?php echo $page <= 1 ? 'primary' : ''; ?>"
           href="?sb_page=<?php echo max(1, $page - 1); ?>"
           data-page="<?php echo max(1, $page - 1); ?>"
           <?php echo $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
            <i class="fa fa-chevron-left"></i> Prev
        </a>

        <input
            id="sb-page-input"
            class="forge-page-input"
            type="number"
            min="1"
            max="<?php echo $totalPages; ?>"
            value="<?php echo $page; ?>"
        >

        <div class="forge-page-meta">
            / <?php echo $totalPages; ?> pages · <?php echo $totalStoryboards; ?> storyboards · 10/page
        </div>

        <a class="forge-page-btn sb-page-link <?php echo $page >= $totalPages ? 'primary' : ''; ?>"
           href="?sb_page=<?php echo min($totalPages, $page + 1); ?>"
           data-page="<?php echo min($totalPages, $page + 1); ?>"
           <?php echo $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
            Next <i class="fa fa-chevron-right"></i>
        </a>

        <div class="forge-save-status" id="browse-save-status">Page <?php echo $page; ?> loaded</div>
    </div>

    <div class="storyboard-browser-grid">
        <?php if (!$storyboards): ?>
            <div class="storyboard-browser-empty">
                No storyboards found.
            </div>
        <?php endif; ?>

        <?php foreach ($storyboards as $sb): ?>
            <?php
                $sbId = (int)$sb['id'];
                $safeName = htmlspecialchars($sb['name'] ?? '');
                $description = (string)($sb['description'] ?? '');
                $snippet = $description;
                $descriptionLength = function_exists('mb_strlen') ? mb_strlen($description) : strlen($description);
                if ($descriptionLength > 180) {
                    $snippet = function_exists('mb_substr') ? mb_substr($description, 0, 180) : substr($description, 0, 180);
                    $snippet .= '…';
                }

                $frameCount = (int)($sb['frame_count'] ?? 0);
                $previewFilename = htmlspecialchars((string)($sb['preview_filename'] ?? ''));
            ?>
            <a class="storyboard-browser-card" href="?id=<?php echo $sbId; ?>">
                <div class="storyboard-browser-preview">
                    <?php if ($previewFilename !== ''): ?>
                        <img src="<?php echo $previewFilename; ?>" alt="<?php echo $safeName; ?> preview" loading="lazy">
                    <?php else: ?>
                        <div class="storyboard-browser-preview-empty">
                            <i class="fa fa-image"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="storyboard-browser-card-top">
                    <div class="storyboard-browser-name"><?php echo $safeName; ?></div>
                    <?php if ((int)($sb['is_archived'] ?? 0) === 1): ?>
                        <span class="storyboard-badge">archived</span>
                    <?php endif; ?>
                </div>

                <div class="storyboard-browser-desc">
                    <?php echo nl2br(htmlspecialchars($snippet)); ?>
                </div>

                <div class="storyboard-browser-count">
                    <i class="fa fa-layer-group"></i>
                    <span><?php echo $frameCount; ?> item<?php echo $frameCount === 1 ? '' : 's'; ?></span>
                </div>

                <div class="storyboard-browser-meta">
                    <span>#<?php echo $sbId; ?></span>
                    <span><?php echo htmlspecialchars((string)($sb['category'] ?? '')); ?></span>
                    <span><?php echo htmlspecialchars((string)($sb['updated_at'] ?? '')); ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

$storyboardId = (int)($_GET['id'] ?? 0);

if (!$storyboardId && isset($_GET['ajax_storyboard_list']) && (string)$_GET['ajax_storyboard_list'] === '1') {
    $search = $_GET['search'] ?? '';
    echo renderStoryboardBrowseFragment($pdo, max(1, (int)($_GET['sb_page'] ?? 1)), 10, $search);
    exit;
}

if (!$storyboardId) {
    $pageTitle = 'Storyboards';
    $listPage = max(1, (int)($_GET['sb_page'] ?? 1));
    $search = $_GET['search'] ?? '';

    ob_start();

    echo '<link rel="stylesheet" href="/css/toast.css">';
    echo '<script src="/js/toast.js"></script>';
    echo '<script src="/js/gear_menu_globals.js"></script>';
    ?>

    <?php if (\App\Core\SpwBase::CDN_USAGE): ?>
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
    <?php else: ?>
      <link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
    <?php endif; ?>

    <?php if (\App\Core\SpwBase::CDN_USAGE): ?>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <?php else: ?>
      <link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css" />
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/base.css" />

    <style>
    :root {
        --forge-bg:          #080b10;
        --forge-surface:     #0e1319;
        --forge-card:        #111820;
        --forge-card-hover:  #141e28;
        --forge-border:      #1c2535;
        --forge-border-glow: #2a3a52;
        --forge-text:        #c8d4e8;
        --forge-text-dim:    #5a6a80;
        --forge-text-bright: #e8f0ff;
        --forge-amber:       #f5a623;
        --forge-amber-dim:   rgba(245,166,35,0.08);
        --forge-amber-mid:   rgba(245,166,35,0.15);
        --forge-amber-glow:  rgba(245,166,35,0.4);
        --forge-red:         #f05060;
        --forge-red-dim:     rgba(240,80,96,0.1);
        --mono: 'Space Mono', 'Fira Mono', monospace;
        --sans: 'Syne', system-ui, sans-serif;
        --forge-radius: 6px;
    }
    [data-theme="light"], html[data-theme="light"] {
        --forge-bg:          #f6f8fa;
        --forge-surface:     #e1e4e8;
        --forge-card:        #ffffff;
        --forge-card-hover:  #f3f4f6;
        --forge-border:      #d1d5db;
        --forge-border-glow: #9ca3af;
        --forge-text:        #111827;
        --forge-text-dim:    #4b5563;
        --forge-text-bright: #000000;
        --forge-amber:       #d97706;
        --forge-amber-dim:   rgba(217,119,6,0.1);
        --forge-amber-mid:   rgba(217,119,6,0.2);
        --forge-amber-glow:  rgba(217,119,6,0.4);
        --forge-red:         #dc2626;
        --forge-red-dim:     rgba(220,38,38,0.1);
    }

    .storyboard-wrap {
        padding: 10px;
        font-family: var(--sans);
        color: var(--forge-text);
    }

    .forge-header-bar,
    .forge-toolbar,
    .forge-pagination-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        background: var(--forge-surface);
        border: 1px solid var(--forge-border);
        border-radius: var(--forge-radius);
        margin-bottom: 10px;
        flex-wrap: wrap;
    }

    .forge-logo {
        font-family: var(--mono);
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--forge-amber);
        letter-spacing: 2px;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 7px;
        flex-shrink: 1;
        min-width: 0;
        overflow: hidden;
    }
    .forge-logo-icon {
        width: 26px; height: 26px;
        background: var(--forge-amber-mid);
        border: 1px solid var(--forge-amber-glow);
        border-radius: var(--forge-radius);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        flex-shrink: 0;
    }
    .forge-logo span {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
    }

    .forge-back-btn,
    .forge-tool-btn,
    .forge-page-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 10px;
        background: transparent;
        border: 1px solid var(--forge-border);
        border-radius: var(--forge-radius);
        color: var(--forge-text-dim);
        font-family: var(--mono);
        font-size: 0.72rem;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.15s;
        white-space: nowrap;
    }
    .forge-back-btn:hover,
    .forge-tool-btn:hover,
    .forge-page-btn:hover {
        border-color: var(--forge-amber);
        color: var(--forge-amber);
        background: var(--forge-amber-dim);
    }
    .forge-tool-btn.primary,
    .forge-page-btn.primary {
        background: var(--forge-amber);
        color: #000;
        border-color: var(--forge-amber);
        font-weight: 700;
    }
    .forge-tool-btn.primary:hover,
    .forge-page-btn.primary:hover {
        filter: brightness(1.1);
        color: #000;
        background: var(--forge-amber);
    }

    .forge-page-input {
        width: 72px;
        padding: 5px 8px;
        background: transparent;
        border: 1px solid var(--forge-border);
        border-radius: var(--forge-radius);
        color: var(--forge-text-bright);
        font-family: var(--mono);
        font-size: 0.72rem;
        text-align: center;
        outline: none;
    }
    .forge-page-input:focus {
        border-color: var(--forge-amber);
        box-shadow: 0 0 0 2px var(--forge-amber-dim);
    }

    .forge-page-meta {
        font-family: var(--mono);
        font-size: 0.72rem;
        color: var(--forge-text-dim);
    }

    .forge-save-status {
        font-family: var(--mono);
        font-size: 0.68rem;
        color: var(--forge-text-dim);
        margin-left: auto;
    }

    .sb-description {
        padding: 8px 12px;
        background: var(--forge-surface);
        border: 1px solid var(--forge-border);
        border-radius: var(--forge-radius);
        margin-bottom: 10px;
        font-family: var(--mono);
        font-size: 0.75rem;
        color: var(--forge-text-dim);
        line-height: 1.5;
    }

    .storyboard-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        align-items: start;
    }

    .frame-card {
        background: var(--forge-card);
        border: 1px solid var(--forge-border);
        border-radius: var(--forge-radius);
        padding: 5px;
        display: flex;
        flex-direction: column;
        gap: 5px;
        position: relative;
        transition: border-color 0.2s, background 0.2s;
    }
    .frame-card:hover {
        border-color: var(--forge-border-glow);
        background: var(--forge-card-hover);
    }

    .frame-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 4px;
        font-family: var(--mono);
        font-size: 0.62rem;
        color: var(--forge-text-dim);
    }
    .frame-name {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .frame-actions { display: flex; gap: 3px; align-items: center; }

    .frame-check {
        width: 16px;
        height: 16px;
        margin: 0;
        accent-color: var(--forge-amber);
        cursor: pointer;
        flex-shrink: 0;
    }

    .delete-single-btn {
        padding: 3px 6px;
        background: transparent;
        border: 1px solid var(--forge-border);
        border-radius: 3px;
        color: var(--forge-text-dim);
        font-size: 0.65rem;
        cursor: pointer;
        line-height: 1;
        transition: all 0.15s;
    }
    .delete-single-btn:hover {
        border-color: var(--forge-red);
        color: var(--forge-red);
        background: var(--forge-red-dim);
    }

    .bulk-delete-btn {
        border-color: var(--forge-red);
        color: var(--forge-red);
    }
    .bulk-delete-btn:hover {
        background: var(--forge-red-dim);
        border-color: var(--forge-red);
        color: var(--forge-red);
    }

    .frame-thumb {
        width: 100%;
        height: 150px;
        object-fit: cover;
        border-radius: calc(var(--forge-radius) - 1px);
        cursor: pointer;
        display: block;
    }

    .sb-menu { position: absolute !important; }

    .storyboard-browser-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 8px;
        align-items: stretch;
    }

    .storyboard-browser-card {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 10px 12px;
        background: var(--forge-card);
        border: 1px solid var(--forge-border);
        border-radius: var(--forge-radius);
        text-decoration: none;
        color: var(--forge-text);
        transition: border-color 0.2s, background 0.2s, transform 0.2s;
        min-height: 110px;
    }
    .storyboard-browser-card:hover {
        border-color: var(--forge-border-glow);
        background: var(--forge-card-hover);
        transform: translateY(-1px);
    }

    .storyboard-browser-preview {
        width: 100%;
        height: 96px;
        border: 1px solid var(--forge-border);
        border-radius: calc(var(--forge-radius) - 1px);
        overflow: hidden;
        background: var(--forge-surface);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .storyboard-browser-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .storyboard-browser-preview-empty {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--forge-text-dim);
        font-size: 1.2rem;
    }

    .storyboard-browser-card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
    }

    .storyboard-browser-name {
        font-family: var(--sans);
        font-size: 0.92rem;
        font-weight: 700;
        color: var(--forge-text-bright);
        line-height: 1.2;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        min-width: 0;
    }

    .storyboard-browser-desc {
        font-family: var(--mono);
        font-size: 0.72rem;
        color: var(--forge-text-dim);
        line-height: 1.4;
        min-height: 2.8em;
    }

    .storyboard-browser-count {
        display: flex;
        align-items: center;
        gap: 5px;
        font-family: var(--mono);
        font-size: 0.65rem;
        color: var(--forge-text-dim);
        white-space: nowrap;
    }
    .storyboard-browser-count i {
        color: var(--forge-amber);
    }

    .storyboard-browser-meta {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        font-family: var(--mono);
        font-size: 0.65rem;
        color: var(--forge-text-dim);
    }

    .storyboard-badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 6px;
        border: 1px solid var(--forge-border);
        border-radius: 999px;
        font-family: var(--mono);
        font-size: 0.6rem;
        color: var(--forge-text-dim);
        background: transparent;
        flex-shrink: 0;
        white-space: nowrap;
    }

    .storyboard-browser-empty {
        padding: 14px 12px;
        border: 1px dashed var(--forge-border);
        border-radius: var(--forge-radius);
        font-family: var(--mono);
        font-size: 0.74rem;
        color: var(--forge-text-dim);
        background: var(--forge-surface);
    }
    </style>

    <div class="view-container storyboard-wrap">
        <div id="storyboard-browser">
            <?php echo renderStoryboardBrowseFragment($pdo, $listPage, 10, $search); ?>
        </div>
    </div>

    <?= $spw->getJquery() ?>

    <script>
    $(function(){
      function loadStoryboardBrowserPage(page, search) {
        let p = parseInt(page, 10);
        if (isNaN(p) || p < 1) p = 1;

        if (search === undefined) {
            search = $('#sb-search-input').val() || '';
        }

        const $input = $('#sb-page-input');
        const maxPage = parseInt($input.attr('max'), 10) || p;
        if (p > maxPage && maxPage > 0) p = maxPage;
        $input.val(p);
        $('#browse-save-status').text('Loading...');

        $.get(window.location.pathname, {
          ajax_storyboard_list: 1,
          sb_page: p,
          search: search
        })
        .done(function(html){
          const focusSearch = document.activeElement && document.activeElement.id === 'sb-search-input';
          $('#storyboard-browser').html(html);
          
          if (focusSearch) {
              const $newInput = $('#sb-search-input');
              $newInput.focus();
              const val = $newInput.val();
              if (val) {
                  $newInput[0].setSelectionRange(val.length, val.length);
              }
          }
          
          if (window.history && window.history.replaceState) {
            const nextUrl = new URL(window.location.href);
            nextUrl.searchParams.delete('id');
            nextUrl.searchParams.delete('page');
            nextUrl.searchParams.delete('ajax_storyboard_list');
            nextUrl.searchParams.set('sb_page', p);
            if (search) {
                nextUrl.searchParams.set('search', search);
            } else {
                nextUrl.searchParams.delete('search');
            }
            history.replaceState(null, '', nextUrl.pathname + nextUrl.search);
          }
        })
        .fail(function(xhr, status){
          if (status !== 'abort') {
            $('#browse-save-status').text('Load failed');
          }
        });
      }

      let searchTimeout;
      $(document).on('input', '#sb-search-input', function(){
        const val = $(this).val();
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function(){
            loadStoryboardBrowserPage(1, val);
        }, 400);
      });

      $(document).on('change', '#sb-page-input', function(){
        loadStoryboardBrowserPage(this.value);
      });

      $(document).on('keydown', '#sb-page-input', function(e){
        if (e.key === 'Enter') {
          e.preventDefault();
          $(this).trigger('change');
        }
      });

      $(document).on('click', '.sb-page-link', function(e){
        e.preventDefault();
        const p = parseInt($(this).data('page'), 10);
        if (!isNaN(p)) {
          loadStoryboardBrowserPage(p);
        }
      });
    });
    </script>

    <?php
    $content = ob_get_clean();
    $content .= $eruda ?? '';
    $spw->renderLayout($content, $pageTitle);
    exit;
}

$pageSize = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $pageSize;

// Get storyboard info
$stmt = $pdo->prepare("SELECT * FROM storyboards WHERE id = ?");
$stmt->execute([$storyboardId]);
$storyboard = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$storyboard) {
    die('Storyboard not found');
}

// Count total frames
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM storyboard_frames
    WHERE storyboard_id = ?
");
$stmt->execute([$storyboardId]);
$totalFrames = (int)$stmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalFrames / $pageSize));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $pageSize;
}

// Copy only the current page frames to storyboard directory if needed
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$storyboardDir = $docRoot . $storyboard['directory'];

if (!is_dir($storyboardDir)) {
    mkdir($storyboardDir, 0777, true);
}

// Copy uncopied frames for this page only
$stmt = $pdo->prepare("
    SELECT sf.*, f.filename AS source_filename
    FROM storyboard_frames sf
    LEFT JOIN frames f ON sf.frame_id = f.id
    WHERE sf.storyboard_id = ?
      AND sf.is_copied = 0
      AND sf.frame_id IS NOT NULL
    ORDER BY sf.sort_order ASC, sf.id ASC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $storyboardId, PDO::PARAM_INT);
$stmt->bindValue(2, $pageSize, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$framesToCopy = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($framesToCopy as $frame) {
    $sourceFile = $docRoot . '/' . ltrim($frame['source_filename'], '/');

    if (file_exists($sourceFile)) {
        $ext = pathinfo($sourceFile, PATHINFO_EXTENSION);
        $newFilename = 'frame' . str_pad($frame['id'], 7, '0', STR_PAD_LEFT) . '.' . $ext;
        $destFile = $storyboardDir . '/' . $newFilename;
        $destRelPath = $storyboard['directory'] . '/' . $newFilename;

        if (copy($sourceFile, $destFile)) {
            $updateStmt = $pdo->prepare("
                UPDATE storyboard_frames
                SET filename = ?, is_copied = 1, original_filename = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$destRelPath, $frame['source_filename'], $frame['id']]);
        }
    }
}

// Load current page frames
$stmt = $pdo->prepare("
    SELECT sf.*, f.entity_type, f.entity_id, f.rating, f.filename AS source_filename
    FROM storyboard_frames sf
    LEFT JOIN frames f ON sf.frame_id = f.id
    WHERE sf.storyboard_id = ?
    ORDER BY sf.sort_order ASC, sf.id ASC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $storyboardId, PDO::PARAM_INT);
$stmt->bindValue(2, $pageSize, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$frames = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Module configuration
$registry = ModuleRegistry::getInstance();

$gearMenu = $registry->create('gear_menu', [
    'position' => 'top-right',
    'show_for_entities' => null,
]);

$allEntityTypes = [
    'characters', 'character_expressions', 'character_anima_poses', 'character_poses', 'animas', 'locations', 'backgrounds',
    'artifacts', 'vehicles', 'scene_parts', 'controlnet_maps', 'spawns',
    'generatives', 'sketches', 'prompt_matrix_blueprints', 'composites'
];

foreach ($allEntityTypes as $entityType) {
    $gearMenu->addStandardActions($entityType, [
        'overrides' => [
            'delete' => [
                'label' => 'Delete Original Frame',
                'condition' => 'frameId > 0'
            ]
        ]
    ]);
}

$imageEditor = $registry->create('image_editor', [
    'modes' => ['mask', 'crop'],
    'show_transform_tab' => true,
    'show_filters_tab' => true,
    'enable_rotate' => true,
    'enable_resize' => true,
    'preset_filters' => ['grayscale', 'vintage', 'sepia', 'blur', 'sharpen'],
]);

$pageTitle = "Storyboard: " . htmlspecialchars($storyboard['name']);
ob_start();

echo '<link rel="stylesheet" href="/css/toast.css">';
echo '<script src="/js/toast.js"></script>';
echo '<script src="/js/gear_menu_globals.js"></script>';
echo $gearMenu->render();
echo $imageEditor->render();
require __DIR__ . '/modal_frame_details.php';
?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<?php else: ?>
  <link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
<?php endif; ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
<?php else: ?>
  <link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css" />
<?php endif; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/base.css" />

<style>
:root {
    --forge-bg:          #080b10;
    --forge-surface:     #0e1319;
    --forge-card:        #111820;
    --forge-card-hover:  #141e28;
    --forge-border:      #1c2535;
    --forge-border-glow: #2a3a52;
    --forge-text:        #c8d4e8;
    --forge-text-dim:    #5a6a80;
    --forge-text-bright: #e8f0ff;
    --forge-amber:       #f5a623;
    --forge-amber-dim:   rgba(245,166,35,0.08);
    --forge-amber-mid:   rgba(245,166,35,0.15);
    --forge-amber-glow:  rgba(245,166,35,0.4);
    --forge-red:         #f05060;
    --forge-red-dim:     rgba(240,80,96,0.1);
    --mono: 'Space Mono', 'Fira Mono', monospace;
    --sans: 'Syne', system-ui, sans-serif;
    --forge-radius: 6px;
}
[data-theme="light"], html[data-theme="light"] {
    --forge-bg:          #f6f8fa;
    --forge-surface:     #e1e4e8;
    --forge-card:        #ffffff;
    --forge-card-hover:  #f3f4f6;
    --forge-border:      #d1d5db;
    --forge-border-glow: #9ca3af;
    --forge-text:        #111827;
    --forge-text-dim:    #4b5563;
    --forge-text-bright: #000000;
    --forge-amber:       #d97706;
    --forge-amber-dim:   rgba(217,119,6,0.1);
    --forge-amber-mid:   rgba(217,119,6,0.2);
    --forge-amber-glow:  rgba(217,119,6,0.4);
    --forge-red:         #dc2626;
    --forge-red-dim:     rgba(220,38,38,0.1);
}

.storyboard-wrap {
    padding: 10px;
    font-family: var(--sans);
    color: var(--forge-text);
}

.forge-header-bar,
.forge-toolbar,
.forge-pagination-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: var(--forge-surface);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.forge-logo {
    font-family: var(--mono);
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--forge-amber);
    letter-spacing: 2px;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 7px;
    flex-shrink: 1;
    min-width: 0;
    overflow: hidden;
}
.forge-logo-icon {
    width: 26px; height: 26px;
    background: var(--forge-amber-mid);
    border: 1px solid var(--forge-amber-glow);
    border-radius: var(--forge-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
}
.forge-logo span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}

.forge-back-btn,
.forge-tool-btn,
.forge-page-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    background: transparent;
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    color: var(--forge-text-dim);
    font-family: var(--mono);
    font-size: 0.72rem;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
    white-space: nowrap;
}
.forge-back-btn:hover,
.forge-tool-btn:hover,
.forge-page-btn:hover {
    border-color: var(--forge-amber);
    color: var(--forge-amber);
    background: var(--forge-amber-dim);
}
.forge-tool-btn.primary,
.forge-page-btn.primary {
    background: var(--forge-amber);
    color: #000;
    border-color: var(--forge-amber);
    font-weight: 700;
}
.forge-tool-btn.primary:hover,
.forge-page-btn.primary:hover {
    filter: brightness(1.1);
    color: #000;
    background: var(--forge-amber);
}

.forge-page-input {
    width: 72px;
    padding: 5px 8px;
    background: transparent;
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    color: var(--forge-text-bright);
    font-family: var(--mono);
    font-size: 0.72rem;
    text-align: center;
    outline: none;
}
.forge-page-input:focus {
    border-color: var(--forge-amber);
    box-shadow: 0 0 0 2px var(--forge-amber-dim);
}

.forge-page-meta {
    font-family: var(--mono);
    font-size: 0.72rem;
    color: var(--forge-text-dim);
}

.forge-save-status {
    font-family: var(--mono);
    font-size: 0.68rem;
    color: var(--forge-text-dim);
    margin-left: auto;
}

.sb-description {
    padding: 8px 12px;
    background: var(--forge-surface);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    margin-bottom: 10px;
    font-family: var(--mono);
    font-size: 0.75rem;
    color: var(--forge-text-dim);
    line-height: 1.5;
}

.storyboard-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    align-items: start;
}

.frame-card {
    background: var(--forge-card);
    border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius);
    padding: 5px;
    display: flex;
    flex-direction: column;
    gap: 5px;
    position: relative;
    transition: border-color 0.2s, background 0.2s;
}
.frame-card:hover {
    border-color: var(--forge-border-glow);
    background: var(--forge-card-hover);
}

.frame-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 4px;
    font-family: var(--mono);
    font-size: 0.62rem;
    color: var(--forge-text-dim);
}
.frame-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.frame-actions { display: flex; gap: 3px; align-items: center; }

.frame-check {
    width: 16px;
    height: 16px;
    margin: 0;
    accent-color: var(--forge-amber);
    cursor: pointer;
    flex-shrink: 0;
}

.delete-single-btn {
    padding: 3px 6px;
    background: transparent;
    border: 1px solid var(--forge-border);
    border-radius: 3px;
    color: var(--forge-text-dim);
    font-size: 0.65rem;
    cursor: pointer;
    line-height: 1;
    transition: all 0.15s;
}
.delete-single-btn:hover {
    border-color: var(--forge-red);
    color: var(--forge-red);
    background: var(--forge-red-dim);
}

.bulk-delete-btn {
    border-color: var(--forge-red);
    color: var(--forge-red);
}
.bulk-delete-btn:hover {
    background: var(--forge-red-dim);
    border-color: var(--forge-red);
    color: var(--forge-red);
}

.frame-thumb {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: calc(var(--forge-radius) - 1px);
    cursor: pointer;
    display: block;
}

.sb-menu { position: absolute !important; }

/* Import Modal styles */
.sb-modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.8); z-index: 1000;
    display: none; align-items: center; justify-content: center;
}
.sb-modal-box {
    background: var(--forge-bg); border: 1px solid var(--forge-border);
    border-radius: var(--forge-radius); width: 90%; max-width: 500px;
    max-height: 80vh; display: flex; flex-direction: column;
}
.sb-modal-header {
    padding: 10px 15px; border-bottom: 1px solid var(--forge-border);
    display: flex; justify-content: space-between; align-items: center;
    font-family: var(--sans); color: var(--forge-text-bright); font-weight: 700;
}
.sb-modal-close {
    background: none; border: none; color: var(--forge-text-dim); cursor: pointer; font-size: 1.2rem; transition: color 0.2s;
}
.sb-modal-close:hover { color: var(--forge-red); }
.sb-modal-search {
    padding: 10px 15px; border-bottom: 1px solid var(--forge-border);
}
.sb-modal-search input {
    width: 100%; padding: 8px; border: 1px solid var(--forge-border);
    background: var(--forge-surface); color: var(--forge-text);
    border-radius: var(--forge-radius); font-family: var(--sans); outline: none;
}
.sb-modal-search input:focus { border-color: var(--forge-amber); }
.sb-modal-list {
    flex: 1; overflow-y: auto; padding: 10px;
}
.sb-modal-item {
    padding: 10px; border-bottom: 1px solid var(--forge-border);
    cursor: pointer; display: flex; flex-direction: column; gap: 4px;
    border-radius: var(--forge-radius);
}
.sb-modal-item:hover { background: var(--forge-card-hover); }
.sb-modal-item-title { font-weight: 700; color: var(--forge-text-bright); font-family: var(--sans); }
.sb-modal-item-meta { font-size: 0.7rem; color: var(--forge-text-dim); font-family: var(--mono); }
.sb-modal-pagination {
    padding: 10px; border-top: 1px solid var(--forge-border);
    display: flex; justify-content: space-between; align-items: center;
}
</style>

<div class="view-container storyboard-wrap">

    <div class="forge-header-bar">
        <div class="forge-logo">
            <div class="forge-logo-icon">⬛</div>
            <span><?php echo htmlspecialchars($storyboard['name']); ?></span>
        </div>
        <a href="view_storyboards_pg.php" class="forge-back-btn">
            <i class="fa fa-arrow-left"></i> Storyboards
        </a>
    </div>

    <?php if ($storyboard['description']): ?>
    <div class="sb-description">
        <?php echo nl2br(htmlspecialchars($storyboard['description'])); ?>
    </div>
    <?php endif; ?>

    <div class="forge-pagination-bar" style="justify-content:flex-start;">
        <a class="forge-page-btn <?php echo $page <= 1 ? 'primary' : ''; ?>"
           href="?id=<?php echo $storyboardId; ?>&page=<?php echo max(1, $page - 1); ?>"
           <?php echo $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
            <i class="fa fa-chevron-left"></i> Prev
        </a>

        <form id="page-form" method="get" action="" style="display:flex; align-items:center; gap:8px; margin:0;">
            <input type="hidden" name="id" value="<?php echo $storyboardId; ?>">
            <input
                id="page-input"
                class="forge-page-input"
                type="number"
                name="page"
                min="1"
                max="<?php echo $totalPages; ?>"
                value="<?php echo $page; ?>"
            >
            <div class="forge-page-meta">
                / <?php echo $totalPages; ?> pages · <?php echo $totalFrames; ?> frames · 50/page
            </div>
        </form>

        <a class="forge-page-btn <?php echo $page >= $totalPages ? 'primary' : ''; ?>"
           href="?id=<?php echo $storyboardId; ?>&page=<?php echo min($totalPages, $page + 1); ?>"
           <?php echo $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
            Next <i class="fa fa-chevron-right"></i>
        </a>

        <button type="button" id="btn-delete-selected" class="forge-tool-btn bulk-delete-btn">
            <i class="fa fa-trash"></i> Delete selected
        </button>

        <button type="button" id="btn-import-selected" class="forge-tool-btn">
            <i class="fa fa-file-import"></i> Copy to Storyboard
        </button>

        <label style="display:flex; align-items:center; gap:6px; font-family:var(--mono); font-size:0.72rem; color:var(--forge-text-dim);">
            <input type="checkbox" id="check-all">
            Select all
        </label>

        <div class="forge-save-status" id="save-status">Page <?php echo $page; ?> loaded</div>
    </div>

    <div class="forge-toolbar">
        <button id="btn-auto-prefix" class="forge-tool-btn" title="Rename files with numeric prefixes">
            <i class="fa fa-sort-numeric-asc"></i> Auto-prefix
        </button>
        <button id="btn-export" class="forge-tool-btn" title="Export as ZIP">
            <i class="fa fa-download"></i> Export ZIP
        </button>
        <div class="forge-save-status" id="save-status-2"></div>
    </div>

    <div id="storyboard" class="storyboard-grid pswp-gallery">
        <?php foreach ($frames as $frame): ?>
            <?php
                $safeName = htmlspecialchars($frame['name'] ?? '');
                $safeFilename = htmlspecialchars($frame['filename'] ?? '');
                $frameId = (int)$frame['id'];
            ?>
            <div class="frame-card"
                 data-id="<?php echo $frameId; ?>"
                 data-storyboard-frame-id="<?php echo $frameId; ?>"
                 data-entity="<?php echo htmlspecialchars($frame['entity_type'] ?? ''); ?>"
                 data-entity-id="<?php echo (int)($frame['entity_id'] ?? 0); ?>"
                 data-frame-id="<?php echo (int)($frame['frame_id'] ?? 0); ?>"
                 data-rating="<?php echo (int)($frame['rating'] ?? 0); ?>">

                <a href="<?php echo $safeFilename; ?>"
                   data-pswp-width="768"
                   data-pswp-height="768"
                   target="_blank"
                   rel="noreferrer">
                    <img src="<?php echo $safeFilename; ?>" alt="<?php echo $safeName; ?>" class="frame-thumb" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;" />
                </a>

                <div class="frame-meta">
                    <div class="frame-name" title="<?php echo $safeName; ?>"><?php echo $safeName; ?></div>
                    <div class="frame-actions">
                        <input type="checkbox" class="frame-check" value="<?php echo $frameId; ?>">
                        <button class="delete-single-btn btn-delete" data-id="<?php echo $frameId; ?>" title="Delete from storyboard">✕</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- Modal for importing to another storyboard -->
<div id="sb-import-modal" class="sb-modal-overlay">
    <div class="sb-modal-box">
        <div class="sb-modal-header">
            <span>Select Target Storyboard</span>
            <button class="sb-modal-close" title="Close"><i class="fa fa-times"></i></button>
        </div>
        <div class="sb-modal-search">
            <input type="text" id="sb-modal-search-input" placeholder="Search storyboards...">
        </div>
        <div class="sb-modal-list" id="sb-modal-list">
            <!-- Items injected here -->
        </div>
        <div class="sb-modal-pagination">
            <button class="forge-page-btn" id="sb-modal-prev">Prev</button>
            <span class="forge-page-meta" id="sb-modal-page-info">Page 1</span>
            <button class="forge-page-btn" id="sb-modal-next">Next</button>
        </div>
    </div>
</div>

<?= $spw->getJquery() ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.umd.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.umd.js"></script>
<?php else: ?>
  <script src="/vendor/photoswipe/photoswipe.umd.js"></script>
  <script src="/vendor/photoswipe/photoswipe-lightbox.umd.js"></script>
<?php endif; ?>

<script>
$(function(){
  const storyboardId = <?php echo (int)$storyboardId; ?>;
  const totalPages = <?php echo (int)$totalPages; ?>;

  function initPhotoSwipe() {
    if (window.lightbox) {
      window.lightbox.destroy();
    }
    window.lightbox = new PhotoSwipeLightbox({
      gallery: '.pswp-gallery',
      children: 'a',
      pswpModule: PhotoSwipe,
      initialZoomLevel: 'fit',
      secondaryZoomLevel: 1,
      paddingFn: (viewportSize) => { return {} }
    });
    window.lightbox.init();
  }

  initPhotoSwipe();

  if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
      window.GearMenu.attach(document.getElementById('storyboard'));
  }

  $('#page-input').on('change', function(){
    let p = parseInt(this.value, 10);
    if (isNaN(p) || p < 1) p = 1;
    if (p > totalPages) p = totalPages;
    window.location.href = '?id=<?php echo (int)$storyboardId; ?>&page=' + p;
  });

  $('#page-input').on('keydown', function(e){
    if (e.key === 'Enter') {
      e.preventDefault();
      $(this).trigger('change');
    }
  });

  $('#check-all').on('change', function(){
    $('.frame-check').prop('checked', this.checked);
  });

  $(document).on('change', '.frame-check', function(){
    const total = $('.frame-check').length;
    const checked = $('.frame-check:checked').length;
    $('#check-all').prop('checked', total > 0 && total === checked);
  });

  function selectedStoryboardFrameIds() {
    return $('.frame-check:checked').map(function(){
      return parseInt(this.value, 10);
    }).get().filter(Boolean);
  }

  $('#btn-delete-selected').on('click', function(){
    const ids = selectedStoryboardFrameIds();
    if (!ids.length) {
      alert('No frames selected.');
      return;
    }

    if (!confirm('Remove ' + ids.length + ' selected frame(s) from this storyboard?')) {
      return;
    }

    $('#save-status').text('Deleting...');
    $('#save-status-2').text('Deleting...');

    $.post('storyboards_api.php', {
      action: 'bulk_delete_storyboard_frames',
      storyboard_id: storyboardId,
      storyboard_frame_ids: JSON.stringify(ids)
    })
    .done(function(res){
      if (res.success) {
        ids.forEach(function(id){
          $('.frame-card[data-storyboard-frame-id="' + id + '"]').remove();
        });
        $('#check-all').prop('checked', false);
        $('#save-status').text('Deleted ' + (res.deleted_count || ids.length) + ' frame(s)');
        $('#save-status-2').text('Deleted');
      } else {
        $('#save-status').text('Delete failed: ' + (res.message || 'unknown'));
        $('#save-status-2').text('Delete failed');
      }
    })
    .fail(function(){
      $('#save-status').text('Delete failed: server error');
      $('#save-status-2').text('Delete failed: server error');
    });
  });

  // Modal logic for Import button
  let modalPage = 1;
  let modalSearch = '';
  
  function loadModalStoryboards() {
    $('#sb-modal-list').html('<div style="text-align:center; padding: 20px; color: var(--forge-text-dim);">Loading...</div>');
    $.get(window.location.pathname, {
        ajax_storyboard_json: 1,
        page: modalPage,
        search: modalSearch
    }).done(function(res) {
        if (res.success) {
            $('#sb-modal-list').empty();
            if (res.storyboards.length === 0) {
                $('#sb-modal-list').html('<div style="text-align:center; padding: 20px; color: var(--forge-text-dim);">No storyboards found.</div>');
            } else {
                res.storyboards.forEach(function(sb) {
                    const $item = $('<div class="sb-modal-item"></div>');
                    $item.data('id', sb.id);
                    $item.data('name', sb.name);
                    
                    const $title = $('<div class="sb-modal-item-title"></div>').text(sb.name);
                    const isArchived = sb.is_archived == 1 ? ' <span class="storyboard-badge">archived</span>' : '';
                    const $meta = $('<div class="sb-modal-item-meta"></div>').html('#' + sb.id + ' &middot; ' + sb.category + isArchived);
                    
                    $item.append($title, $meta);
                    $('#sb-modal-list').append($item);
                });
            }
            $('#sb-modal-page-info').text('Page ' + res.page + ' of ' + res.totalPages);
            $('#sb-modal-prev').prop('disabled', res.page <= 1).toggleClass('primary', res.page > 1);
            $('#sb-modal-next').prop('disabled', res.page >= res.totalPages).toggleClass('primary', res.page < res.totalPages);
            modalPage = res.page;
        }
    });
  }

  let msTimeout;
  $('#sb-modal-search-input').on('input', function() {
      modalSearch = $(this).val();
      modalPage = 1;
      clearTimeout(msTimeout);
      msTimeout = setTimeout(loadModalStoryboards, 300);
  });

  $('#sb-modal-prev').on('click', function() {
      if (modalPage > 1) {
          modalPage--;
          loadModalStoryboards();
      }
  });

  $('#sb-modal-next').on('click', function() {
      modalPage++;
      loadModalStoryboards();
  });

  $('.sb-modal-close').on('click', function() {
      $('#sb-import-modal').css('display', 'none');
  });

  function selectedOriginalFrameIds() {
    return $('.frame-check:checked').map(function(){
      return parseInt($(this).closest('.frame-card').data('frame-id'), 10);
    }).get().filter(Boolean);
  }

  $('#btn-import-selected').on('click', function() {
      const ids = selectedOriginalFrameIds();
      const rawChecked = $('.frame-check:checked').length;
      
      if (rawChecked === 0) {
          alert('No frames selected.');
          return;
      }
      
      if (ids.length === 0) {
          alert('Selected frames are standalone and cannot be copied.');
          return;
      }
      
      if (ids.length < rawChecked) {
          if (!confirm((rawChecked - ids.length) + ' selected frame(s) are standalone and will be skipped. Continue?')) {
              return;
          }
      }
      
      modalPage = 1;
      modalSearch = '';
      $('#sb-modal-search-input').val('');
      loadModalStoryboards();
      $('#sb-import-modal').css('display', 'flex');
  });

  $(document).on('click', '.sb-modal-item', function() {
      const targetSbId = $(this).data('id');
      const targetSbName = $(this).data('name');
      const ids = selectedOriginalFrameIds();
      
      if (!confirm('Copy ' + ids.length + ' frame(s) to "' + targetSbName + '"?')) return;
      
      $('#sb-import-modal').css('display', 'none');
      $('#save-status').text('Copying...');
      
      const fd = new URLSearchParams(); 
      fd.append('storyboard_id', targetSbId); 
      fd.append('frame_ids', JSON.stringify(ids));
      
      fetch('/storyboard_import.php', { 
          method: 'POST', 
          body: fd, 
          headers: {'Content-Type':'application/x-www-form-urlencoded'} 
      })
      .then(r => r.json())
      .then(d => {
          if (d.success) {
              $('#save-status').text('Copied to ' + targetSbName);
              if (typeof Toast !== 'undefined') Toast.show(d.message || 'Frames copied successfully', 'success');
              $('#check-all').prop('checked', false).trigger('change');
          } else {
              $('#save-status').text('Copy failed');
              if (typeof Toast !== 'undefined') Toast.show('Failed: ' + (d.message || 'error'), 'error');
          }
      })
      .catch(e => {
          $('#save-status').text('Copy error');
          console.error(e);
          if (typeof Toast !== 'undefined') Toast.show('Error during copy', 'error');
      });
  });

  $(document).on('click', '.btn-delete', function(){
    const $card = $(this).closest('.frame-card');
    const id = parseInt($card.data('storyboard-frame-id'), 10);
    const name = $card.find('.frame-name').text().trim();

    if (!confirm('Remove "' + name + '" from this storyboard?')) return;

    $.post('storyboards_api.php', {
      action: 'delete_storyboard_frame',
      storyboard_frame_id: id
    })
    .done(function(res){
      if (res.success) {
        $card.remove();
        $('#save-status').text('Deleted: ' + name);
        $('#save-status-2').text('Deleted');
      } else {
        alert('Delete failed: ' + (res.message || 'unknown'));
      }
    })
    .fail(function(){
      alert('Server request failed');
    });
  });

  $('#btn-auto-prefix').on('click', function(){
    if (!confirm('Auto-prefix will rename files on disk with numeric prefixes (001_filename). Proceed?')) return;

    const order = [];
    $('#storyboard .frame-card').each(function(){
      order.push($(this).data('id'));
    });

    $('#save-status').text('Renaming files...');
    $('#save-status-2').text('Renaming files...');

    $.post('storyboards_api.php', {
      action: 'rename_in_order',
      storyboard_id: storyboardId,
      order: JSON.stringify(order)
    })
    .done(function(res){
      if (res.success) {
        $('#save-status').text('Files renamed. Reloading...');
        $('#save-status-2').text('Files renamed');
        setTimeout(function(){ location.reload(); }, 700);
      } else {
        $('#save-status').text('Rename failed: ' + (res.message || 'unknown'));
        $('#save-status-2').text('Rename failed');
      }
    })
    .fail(function(){
      $('#save-status').text('Rename failed: server error');
      $('#save-status-2').text('Rename failed: server error');
    });
  });

  $('#btn-export').on('click', function(){
    if (!confirm('Export this storyboard as a ZIP file with all frames in order?')) return;

    $('#save-status').text('Creating ZIP...');
    $('#save-status-2').text('Creating ZIP...');

    $.post('storyboards_api.php', { action: 'export_zip', storyboard_id: storyboardId })
      .done(function(res){
        if (res.success && res.download_url) {
          $('#save-status').text('Downloading...');
          $('#save-status-2').text('Downloading...');
          window.location.href = res.download_url;
          setTimeout(function(){
            $('#save-status').text('Export complete');
            $('#save-status-2').text('Export complete');
          }, 1000);
        } else {
          $('#save-status').text('Export failed: ' + (res.message || 'unknown'));
          $('#save-status-2').text('Export failed');
        }
      })
      .fail(function(){
        $('#save-status').text('Export failed: server error');
        $('#save-status-2').text('Export failed: server error');
      });
  });
});
</script>

<?php
$content = ob_get_clean();
$content .= $eruda ?? '';
$spw->renderLayout($content, $pageTitle);
?>
