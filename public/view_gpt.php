<?php

require "error_reporting.php";
require "eruda_var.php";
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdoSys = $spw->getSysPDO();

$pageTitle = "GPT Conversations";
$content = "";

// Determine requested conversation
$convId = $_GET['id'] ?? null;
$page   = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Fetch conversations for list view
$search = trim($_GET['search'] ?? '');
$params = [];
$searchSql = '';

if ($search !== '') {
    $searchSql = "WHERE c.title LIKE :q OR m.content_text LIKE :q";
    $params[':q'] = "%$search%";
}

// Total conversations count
$stmtTotal = $pdoSys->prepare("
    SELECT COUNT(DISTINCT c.external_id) 
    FROM gpt_conversations c
    LEFT JOIN gpt_messages m ON c.external_id = m.conversation_external_id
    $searchSql
");
$stmtTotal->execute($params);
$totalConversations = intval($stmtTotal->fetchColumn());
$pages = max(1, ceil($totalConversations / $perPage));

// Fetch current page conversations
$stmtConvs = $pdoSys->prepare("
    SELECT DISTINCT c.*
    FROM gpt_conversations c
    LEFT JOIN gpt_messages m ON c.external_id = m.conversation_external_id
    $searchSql
    ORDER BY c.updated_at DESC
    LIMIT :offset, :limit
");
foreach ($params as $k => $v) {
    $stmtConvs->bindValue($k, $v, PDO::PARAM_STR);
}
$stmtConvs->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtConvs->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmtConvs->execute();
$conversations = $stmtConvs->fetchAll(PDO::FETCH_ASSOC);

// If a specific conversation is requested, fetch its messages
$conv = null;
$messages = [];
if ($convId) {
    $stmtConv = $pdoSys->prepare("SELECT * FROM gpt_conversations WHERE external_id = :eid");
    $stmtConv->execute([':eid' => $convId]);
    $conv = $stmtConv->fetch(PDO::FETCH_ASSOC);

    if ($conv) {
        $stmtMsgs = $pdoSys->prepare("SELECT * FROM gpt_messages WHERE conversation_external_id = :eid ORDER BY message_index ASC");
        $stmtMsgs->execute([':eid' => $convId]);
        $messages = $stmtMsgs->fetchAll(PDO::FETCH_ASSOC);
    }
}

ob_start();

/**
 * Render content with fenced code blocks (```lang\ncode```).
 * Handles multiple blocks, LF/CRLF endings, and spacing after language specifier.
 */
function renderContentWithCodeBlocks(string $text): string {
    $out = '';
    // Robust pattern: allows spaces after language, handles \r?\n and multiline content
    $pattern = '/```([a-zA-Z0-9_-]+)?\s*\r?\n([\s\S]*?)```/m';
    $lastPos = 0;

    if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $i => $match) {
            $matchStart = $match[1];
            $matchLen   = strlen($match[0]);

            // Text before the code block
            $before = substr($text, $lastPos, $matchStart - $lastPos);
            if ($before !== '') {
                $out .= nl2br(htmlspecialchars($before, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            }

            // Extract details
            $lang = strtolower(trim($matches[1][$i][0] ?: 'plaintext'));
            $code = $matches[2][$i][0];
            $escapedCode = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $out .= '<pre><code class="language-' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '">' . $escapedCode . '</code></pre>';

            $lastPos = $matchStart + $matchLen;
        }

        // Append trailing text
        $after = substr($text, $lastPos);
        if ($after !== '') {
            $out .= nl2br(htmlspecialchars($after, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
    } else {
        $out = nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    return $out;
}

?>

<!-- Prism CSS: GitHub Dark / Tomorrow -->
<link href="https://cdn.jsdelivr.net/npm/prismjs/themes/prism-tomorrow.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/prismjs/plugins/toolbar/prism-toolbar.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/prismjs/plugins/copy-to-clipboard/prism-copy-to-clipboard.css" rel="stylesheet">

<style>
html, body {
    background-color: #000;
    color: #f0f6fc;
    margin: 0;
    padding: 0;
}
.view-container { 
    max-width: 900px; 
    margin: 20px auto; 
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: #f0f6fc;
    background-color: #0d1117;
    padding: 20px;
    border-radius: 12px;
}

/* === Search bar === */
.search-bar { display:flex; gap:10px; margin-bottom:20px; }
.search-bar input[type="text"] { 
    flex:1; padding:6px 10px; 
    font-size:16px; border:1px solid #30363d; border-radius:4px; 
    background-color:#161b22; color:#c9d1d9;
}
.search-bar button { padding:6px 12px; background:#238636; color:#fff; border:none; border-radius:4px; cursor:pointer; }

/* === Conversation list === */
.conversation-list { border-top:1px solid #21262d; max-width:720px; margin:auto; }
.conversation-item { 
    padding:10px; border-bottom:1px solid #21262d; 
    cursor:pointer; transition: background .2s; 
    display:flex; justify-content:space-between; align-items:center; 
    border-radius:4px; text-decoration:none; color:#c9d1d9;
}
.conversation-item:hover { background:#161b22; }
.conversation-title { font-weight:bold; }
.conversation-meta { font-size:0.85rem; color:#8b949e; }
.badge { background:#238636; color:#fff; border-radius:999px; padding:0.25rem 0.5rem; font-size:0.8rem; }

/* === Chat view === */
.chat-container { max-width:720px; margin:auto; }
.chat-message { margin-bottom:1rem; display:flex; flex-direction:column; }
.chat-user { align-items:flex-end; }
.chat-assistant { align-items:flex-start; }
.chat-bubble { 
    padding:0.75rem 1rem; 
    border-radius:0.75rem; 
    background-color:#161b22; 
    max-width:80%; 
    word-wrap:break-word; 
    overflow-x:auto; 
    color:#c9d1d9;
}
.chat-user .chat-bubble { background-color:#238636; color:#fff; }
.chat-time { font-size:0.7rem; color:#8b949e; margin-top:0.25rem; }

/* === Code blocks — GitHub dark style === */
.chat-bubble pre[class*="language-"] {
    background: #0d1117 !important;
    border: 1px solid #30363d;
    border-radius: 8px;
    padding: 12px 14px;
    overflow-x: auto;
    font-size: 0.9rem;
    box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    margin: 8px 0;
}
.chat-bubble code {
    font-family: "JetBrains Mono", Consolas, "Courier New", monospace;
    color: #c9d1d9;
}

/* Prism token colors — keep them if you want to override particular tokens */
.token.comment,.token.prolog,.token.doctype,.token.cdata { color:#8b949e; }
.token.punctuation { color:#c9d1d9; }
.token.property,.token.tag,.token.boolean,.token.number,.token.constant,.token.symbol,.token.deleted { color:#79c0ff; }
.token.selector,.token.attr-name,.token.string,.token.char,.token.builtin,.token.inserted { color:#a5d6ff; }
.token.operator,.token.entity,.language-css .token.string,.style .token.string { color:#ff7b72; }
.token.atrule,.token.attr-value,.token.keyword { color:#d2a8ff; }
.token.function,.token.class-name { color:#ffa657; }
.token.regex,.token.important,.token.variable { color:#f2cc60; }

/* Pagination */
.pagination { margin-top:15px; text-align:center; }
.pagination a { margin:0 5px; text-decoration:none; color:#58a6ff; }
.pagination a.current { font-weight:bold; text-decoration:underline; }

/* Jump link */
.jump-end { font-size:0.9rem; text-decoration:none; color:#58a6ff; margin-bottom:10px; display:inline-block; }
.jump-end:hover { text-decoration:underline; }
</style>

<div class="view-container">

<?php if ($conv && $messages): ?>
    <!-- Single Conversation -->
    <h2 class="mb-2 text-center fw-bold"><?= htmlspecialchars($conv['title'] ?? 'Untitled Conversation') ?></h2>
    <a href="#endMessages" class="jump-end">Jump to end ⬇</a>

    <div class="chat-container mb-4">
        <?php foreach ($messages as $msg): ?>
            <?php 
                $role = $msg['role'] ?? '';
                $contentText = $msg['content_text'] ?? '';
                $time = $msg['created_at'] ?? '';
                $cls = $role === 'user' ? 'chat-user' : 'chat-assistant';

                // Convert message text (with fenced code blocks) into safe HTML
                $contentHtml = renderContentWithCodeBlocks($contentText);
            ?>
            <div class="chat-message <?= $cls ?>">
                <div class="chat-bubble"><?= $contentHtml ?></div>
                <div class="chat-time"><?= htmlspecialchars($time) ?></div>
            </div>
        <?php endforeach; ?>
        <a id="endMessages"></a>
    </div>

    <a href="view_gpt.php" class="btn btn-sm btn-secondary mb-4">⬅ Back to conversation list</a>

    <!-- Prism: load scripts programmatically and robustly -->
    <script>
		(function(){




const scripts = [
    "https://cdn.jsdelivr.net/npm/prismjs/prism.js",
    "https://cdn.jsdelivr.net/npm/prismjs/components/prism-markup.min.js",
    "https://cdn.jsdelivr.net/npm/prismjs@1.30.0/components/prism-markup-templating.min.js",
    "https://cdn.jsdelivr.net/npm/prismjs/components/prism-css.min.js",
    "https://cdn.jsdelivr.net/npm/prismjs/components/prism-javascript.min.js",
    "https://cdn.jsdelivr.net/npm/prismjs/components/prism-php.min.js",
    "https://cdn.jsdelivr.net/npm/prismjs/components/prism-bash.min.js",
    "https://cdn.jsdelivr.net/npm/prismjs/components/prism-json.min.js",
    "https://cdn.jsdelivr.net/npm/prismjs/components/prism-python.min.js",
    "https://cdn.jsdelivr.net/npm/prismjs/components/prism-sql.min.js",
    "https://cdn.jsdelivr.net/npm/prismjs/plugins/toolbar/prism-toolbar.min.js",
    "https://cdn.jsdelivr.net/npm/prismjs/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js"
];



        // Sequential loader that returns a Promise
        function loadScript(url) {
            return new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = url;
                s.async = false; // keep execution order
                s.crossOrigin = "anonymous"; // allow proper error stacks
                s.onload = () => {
                    console.log("Loaded:", url);
                    resolve(url);
                };
                s.onerror = (ev) => {
                    console.error("Failed to load script:", url, ev);
                    reject(new Error("Failed to load " + url));
                };
                document.head.appendChild(s);
            });
        }

        async function loadAllAndHighlight() {
            try {
                for (let i = 0; i < scripts.length; i++) {
                    await loadScript(scripts[i]);
                }

                // If Prism is present, highlight
                const container = document.querySelector('.chat-container') || document;
                if (typeof Prism !== 'undefined') {
                    try {
                        if (Prism.highlightAllUnder) {
                            Prism.highlightAllUnder(container);
                        } else if (Prism.highlightAll) {
                            Prism.highlightAll();
                        }
                        console.log("Prism highlighting completed.");
                    } catch (e) {
                        console.error("Prism highlighting error:", e);
                    }
                } else {
                    console.warn("Prism not available after loading scripts.");
                }
            } catch (err) {
                console.error("Script loading interrupted:", err);
            }
        }

        // capture window errors to get more info
        window.addEventListener('error', function(ev) {
            console.error("Global error event:", ev.message, "at", ev.filename + ":" + ev.lineno + ":" + ev.colno, ev.error);
        });

        // start process after DOM is parsed
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", loadAllAndHighlight);
        } else {
            loadAllAndHighlight();
        }
    })();
    </script>

<?php else: ?>
    <!-- Conversation List -->
    <h2 class="mb-4 text-center fw-bold"><a href="dashboard.php">💬</a> GPT Conversations</h2>
    <form class="search-bar" onsubmit="return false;">
        <input type="text" id="searchInput" placeholder="Search conversations..." value="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
        <button type="button" id="searchBtn">Search</button>
    </form>

    <div id="conversationList" class="conversation-list">
        <?php foreach ($conversations as $c): ?>
            <a href="?id=<?= htmlspecialchars($c['external_id'] ?? '') ?>" class="conversation-item">
                <div>
                    <div class="conversation-title"><?= htmlspecialchars($c['title'] ?? 'Untitled') ?></div>
                    <div class="conversation-meta"><?= htmlspecialchars($c['created_at'] ?? '') ?> UTC</div>
                </div>
                <span class="badge"><?= intval($c['message_count'] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
        <?php if (empty($conversations)): ?>
            <div class="conversation-item">No conversations found.</div>
        <?php endif; ?>
    </div>

    <div class="pagination" id="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="?page=<?= $i ?>" class="<?= $i == $page ? 'current' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>

    <script>
    const searchInput = document.getElementById('searchInput');
    const searchBtn = document.getElementById('searchBtn');
    const convList = document.getElementById('conversationList');
    const paginationEl = document.getElementById('pagination');

    function fetchConversations(q = '', page = 1) {
        fetch(`gpt_conversations_ajax.php?q=${encodeURIComponent(q)}&page=${page}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    convList.innerHTML = '';
                    if (data.data.length === 0) {
                        convList.innerHTML = '<div class="conversation-item">No conversations found.</div>';
                    } else {
                        data.data.forEach(c => {
                            const a = document.createElement('a');
                            a.href = '?id=' + encodeURIComponent(c.external_id);
                            a.className = 'conversation-item';
                            a.innerHTML = `
                                <div>
                                    <div class="conversation-title">${c.title ? c.title : 'Untitled'}</div>
                                    <div class="conversation-meta">${c.created_at} UTC</div>
                                </div>
                                <span class="badge">${c.message_count}</span>
                            `;
                            convList.appendChild(a);
                        });
                    }
                    paginationEl.innerHTML = '';
                    for (let i = 1; i <= data.pages; i++) {
                        const link = document.createElement('a');
                        link.href = '?page=' + i;
                        link.textContent = i;
                        if (i === page) link.className = 'current';
                        link.addEventListener('click', e => {
                            e.preventDefault();
                            fetchConversations(searchInput.value, i);
                        });
                        paginationEl.appendChild(link);
                    }
                }
            });
    }

    searchBtn.addEventListener('click', () => fetchConversations(searchInput.value));
    searchInput.addEventListener('keyup', e => { if(e.key === 'Enter') fetchConversations(searchInput.value); });
    </script>
<?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$content .= $eruda;
$spw->renderLayout($content, $pageTitle);
?>
