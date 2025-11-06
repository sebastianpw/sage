<?php
// public/chat.php
// Dark-themed chat page with Prism syntax highlighting (loader + MutationObserver).

// Ensure session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require __DIR__ . '/../vendor/autoload.php';

use App\Core\ChatUI;

$spw = \App\Core\SpwBase::getInstance();
$cdn = \App\Core\SpwBase::CDN_USAGE;

// Safe user id
$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

// Chat UI HTML
$ui = new ChatUI($userId);
$uiHtml = $ui->render();

// Prism CSS (CDN or local fallback)
$prismCss = '';
if ($cdn) {
    $prismCss = <<<HTML
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs/themes/prism-tomorrow.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs/plugins/toolbar/prism-toolbar.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs/plugins/copy-to-clipboard/prism-copy-to-clipboard.css">
HTML;
} else {
    $prismCss = <<<HTML
<link rel="stylesheet" href="/vendor/prismjs/themes/prism-tomorrow.min.css">
<link rel="stylesheet" href="/vendor/prismjs/plugins/toolbar/prism-toolbar.css">
<link rel="stylesheet" href="/vendor/prismjs/plugins/copy-to-clipboard/prism-copy-to-clipboard.css">
HTML;
}

// Dark theme overrides: adjust the CSS custom properties used in ChatUI
$darkOverrides = <<<CSS
<style>
/* Force a dark theme by overriding ChatUI CSS variables and some page-level colors */
:root {
  --bg: #0d1117;
  --muted: #8b949e;
  --user-bg: #2ea043; /* green-ish */
  --assistant-bg: #0f1720;
  --accent: #238636;
  --accent-700: #1f6b2a;
  --radius: 12px;
  --max-width: 920px;
}

/* Page background override */
html, body {
  background: #000 !important;
  color: #c9d1d9 !important;
}

/* Make sure messages area uses darker background */
#messages {
  background: linear-gradient(180deg, #0d1117, #071018) !important;
}

/* Make textareas darker */
#user-input {
  background: #0b1116 !important;
  color: #dbe7ef !important;
  border-color: #22303a !important;
}

/* Keep the message bubbles legible in dark mode */
.message.user { background: var(--user-bg) !important; color: #fff !important; }
.message.assistant { background: #081018 !important; color: #cfe6ff !important; }

/* Code block tweaks to fit dark theme */
pre[class*="language-"], code[class*="language-"] {
  background: #071018 !important;
  color: #c9d1d9 !important;
  border: 1px solid #1f2a30 !important;
}

/* Prism toolbar button contrast */
.prism-toolbar {
  background: rgba(255,255,255,0.02) !important;
  border-radius: 6px !important;
}
</style>
CSS;

// Prism loader + MutationObserver to highlight dynamic code blocks added to #messages
$prismLoaderScript = '';
if ($cdn) {
    // use CDN list
    $prismScriptsArray = [
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
} else {
    // local fallback
    $prismScriptsArray = [
        "/vendor/prismjs/prism.js",
        "/vendor/prismjs/components/prism-markup.min.js",
        "/vendor/prismjs/components/prism-markup-templating.min.js",
        "/vendor/prismjs/components/prism-css.min.js",
        "/vendor/prismjs/components/prism-javascript.min.js",
        "/vendor/prismjs/components/prism-php.min.js",
        "/vendor/prismjs/components/prism-bash.min.js",
        "/vendor/prismjs/components/prism-json.min.js",
        "/vendor/prismjs/components/prism-python.min.js",
        "/vendor/prismjs/components/prism-sql.min.js",
        "/vendor/prismjs/plugins/toolbar/prism-toolbar.min.js",
        "/vendor/prismjs/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js"
    ];
}

// Build a JS array literal for loader
$scriptArrayJson = json_encode(array_values($prismScriptsArray));

$prismLoaderScript = <<<JS
<script>
(function(){
    const scripts = $scriptArrayJson;

    function loadScript(url) {
        return new Promise((resolve, reject) => {
            // If already loaded, resolve immediately
            if (document.querySelector('script[data-src="'+url+'"]')) {
                return resolve(url);
            }
            const s = document.createElement('script');
            s.dataset.src = url;
            s.src = url;
            s.async = false; // preserve order
            s.crossOrigin = "anonymous";
            s.onload = () => resolve(url);
            s.onerror = (ev) => reject(new Error('Failed to load ' + url));
            document.head.appendChild(s);
        });
    }

    async function loadAll() {
        try {
            for (let i = 0; i < scripts.length; i++) {
                await loadScript(scripts[i]);
            }
            // initial highlight (if any code blocks are already present)
            try {
                if (typeof Prism !== 'undefined') {
                    const container = document.getElementById('messages') || document;
                    if (Prism.highlightAllUnder) Prism.highlightAllUnder(container);
                    else if (Prism.highlightAll) Prism.highlightAll();
                }
            } catch (e) {
                console.warn('Prism initial highlight failed', e);
            }

            // Observe #messages for newly added code blocks and re-run Prism.highlight for them
            const messages = document.getElementById('messages');
            if (!messages) return;

            const obs = new MutationObserver((mutations) => {
                // If Prism isn't available skip
                if (typeof Prism === 'undefined') return;
                for (const mut of mutations) {
                    for (const node of mut.addedNodes) {
                        if (!(node instanceof Element)) continue;
                        // If a code block or pre is added anywhere inside the node, highlight
                        if (node.querySelector && (node.querySelector('pre[class*="language-"], code[class*="language-"]') || node.matches('pre[class*="language-"], code[class*="language-"]'))) {
                            try {
                                Prism.highlightAllUnder(node);
                            } catch (e) {
                                console.warn('Prism highlight error', e);
                            }
                        }
                    }
                }
            });

            obs.observe(messages, { childList: true, subtree: true });
        } catch (err) {
            console.error('Prism loader error', err);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadAll);
    } else {
        loadAll();
    }
})();
</script>
JS;



// --- Tiny preload: accept session_id=HEX in URL and call SPWChat.loadChat(hex) ---
$preloadScript = '';
if (!empty($_GET['session_id'])) {
    $raw = (string) $_GET['session_id'];
    // accept only reasonable hex strings (adjust length if your session_id is longer)
    if (preg_match('/^[0-9a-fA-F]{8,64}$/', $raw)) {
        $hexJs = json_encode($raw);
        $preloadScript = <<<JS
<script>
(function(){
  const preloadId = {$hexJs};
  if (!preloadId) return;
  const maxWait = 5000; // ms
  const interval = 150; // ms
  let waited = 0;
  function tryLoad() {
    if (window.SPWChat && typeof window.SPWChat.loadChat === 'function') {
      try { window.SPWChat.loadChat(preloadId); } catch(e) { /* ignore */ }
      return true;
    }
    return false;
  }
  function poll() {
    if (tryLoad()) return;
    waited += interval;
    if (waited >= maxWait) return;
    setTimeout(poll, interval);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', poll);
  } else {
    poll();
  }
})();
</script>
JS;
    }
}

// later, where you build the page content (keep this as in your original file)
// e.g. $content = $prismCss . $darkOverrides . $uiHtml . $prismLoaderScript . $preloadScript;



// Assemble final content. Keep original ChatUI HTML intact, but prepend Prism CSS & dark overrides
$content = $prismCss . $darkOverrides . $uiHtml . $prismLoaderScript . $preloadScript;

// Render via layout (preserve original template path usage)
$spw->renderLayout($content, "Chat", $spw->getProjectPath() . '/templates/chat.php');
