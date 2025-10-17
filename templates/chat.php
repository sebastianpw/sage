<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $title ?? '' ?></title>
<link rel="stylesheet" href="gallery.css">

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- jQuery via CDN -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<?php else: ?>
    <!-- jQuery via local copy -->
    <script src="/vendor/jquery/jquery-3.7.0.min.js"></script>
<?php endif; ?>

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 5px;
        background: #f9f9f9;
    }

    /* Header image */
    .header {
        position: relative;
        text-align: center;
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
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 8px;
        text-decoration: none;
        color: #333;
        font-weight: bold;
        transition: all 0.2s ease;
    }

    .script-list a:hover {
        background: #e0e0e0;
        border-color: #999;
    }

    /* Horizontal line groups */
    .horizontal-line {
        display: block;
        width: 90%;
        border-bottom: 1px solid #ccc;
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


</body>
</html>

