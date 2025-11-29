<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=0.6">
<title><?= $title ?? '' ?></title>

<script>
  (function() {
    try {
      var theme = localStorage.getItem('spw_theme');
      if (theme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
      } else if (theme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
      }
      // If no theme is set, we do nothing and let the CSS media query handle it.
    } catch (e) {
      // Fails gracefully
    }
  })();
</script>


    <script src="/js/theme-manager.js"></script> 

<link rel="stylesheet" href="gallery.css">

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- jQuery via CDN -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<?php else: ?>
    <!-- jQuery via local copy -->
    <script src="/vendor/jquery/jquery-3.7.0.min.js"></script>
<?php endif; ?>

<!-- REPLACE the entire <style> block in templates/gallery.php with this -->
<style>
    /* NEW: Add theme variables so this layout is self-sufficient */
    :root {
        --float-bg: #ffffff;
        --float-border: #d1d5db;
        --float-text: #111827;
        --float-muted: #6b7280;
        --float-hover: #f3f4f6;
        --float-btn-bg: #f8f9fa;
    }
    html[data-theme="dark"] {
        --float-bg: #0f1724;
        --float-border: #1f2937;
        --float-text: #cbd5e1;
        --float-muted: #94a3b8;
        --float-hover: #111827;
        --float-btn-bg: #0b1220;
    }
    @media (prefers-color-scheme: dark) {
      :root:not([data-theme]) {
        --float-bg: #0f1724;
        --float-border: #1f2937;
        --float-text: #cbd5e1;
        --float-muted: #94a3b8;
        --float-hover: #111827;
        --float-btn-bg: #0b1220;
      }
    }

    /* UPDATED: All styles below now use the theme variables */
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 5px;
        background-color: var(--float-bg);
        color: var(--float-text);
    }

    /* Header image */
    .header {
        position: relative;
        text-align: center;
        /* Color for overlay text, white is usually best for images */
        color: white; 
    }

    .header img {
        width: 100%;
        max-height: 400px;
        object-fit: cover;
    }

    /* Optional headline overlay (hidden if poster has title) */
    .header h1 {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: rgba(0,0,0,0.5);
        padding: 10px 20px;
        border-radius: 8px;
        display: none;
    }

    /* Dashboard links container */
    .script-list {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 20px;
        padding: 20px;
        max-width: 900px;
        margin: auto;
    }

    /* Card-style buttons */
    .script-list a {
        display: block;
        padding: 15px 25px;
        background-color: var(--float-btn-bg);
        border: 1px solid var(--float-border);
        border-radius: 8px;
        text-decoration: none;
        color: var(--float-text);
        font-weight: bold;
        transition: all 0.2s ease;
    }

    .script-list a:hover {
        background-color: var(--float-hover);
        border-color: var(--float-muted);
    }

    /* Horizontal line groups */
    .horizontal-line {
        display: block;
        width: 90%;
        border-bottom: 1px solid var(--float-border);
        margin: 10px auto;
        height: 0;
    }

    @media (max-width: 600px) {
        .script-list a {
            width: 100%;
            text-align: center;
        }
    }
</style>


</head>
<body>


<?php if (file_exists(__DIR__.'/header.php')) include __DIR__.'/header.php'; ?>

<main>
    <?= $content ?? '' ?>
</main>

<?php if (file_exists(__DIR__.'/footer.php')) include __DIR__.'/footer.php'; ?>


    <style>
        /*
         * Gallery-specific Floatool Override
         * Increases floatool size to compensate for the page's initial-scale=0.6
         */
        #floatool {
            /* Original size was 180%, we increase it to ~300% to counteract the 0.6 scale */
            font-size: 300%;
        }

        /* Also override the responsive size */
        @media (max-width: 768px) {
            #floatool, #floatool {
                /* Original responsive size was 150%, we increase it to ~250% */
                font-size: 240%;
            }


            .floatool-buttons button {
                min-width: 50px;
                min-height: 65px;
                margin: 0 !important;
                padding: 0 7px;

            }

            .floatool-handle { 
                font-size: 30px;
            }

            #floatool {
            min-width: 70px;
            min-height: 70px;
            }


        }
    </style>


</body>
</html>

