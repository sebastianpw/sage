<?php

require "e.php";
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$mysqli = $spw->getMysqli();
$dbname = $spw->getDbName();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Character Gallery</title>
<link rel="stylesheet" href="gallery.css">

<style>

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



<?php

require_once "ImageStashGallery.php";

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $title = trim($_POST['title'] ?? '');
    $image = $_FILES['image'];

    if ($image['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . "/uploads/image_stash/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $safeName = time() . "_" . preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($image['name']));
        $targetPath = $uploadDir . $safeName;

        if (move_uploaded_file($image['tmp_name'], $targetPath)) {
            // Store in DB
            $stmt = $mysqli->prepare("INSERT INTO image_stash (title, file_path, created_at) VALUES (?, ?, NOW())");
            $relPath = "uploads/image_stash/" . $safeName; // relative path for browser
            $stmt->bind_param("ss", $title, $relPath);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Render gallery
$gallery = new ImageStashGallery($mysqli);
echo $gallery->render();
?>


<div class="script-list">
    <div class="horizontal-line"></div>
    <a href="sql_crud_image_stash.php">✏️ Edit</a>
    <div class="horizontal-line"></div>
</div>


<!-- Upload form -->
<div style="margin:20px;">
    <h3>Upload New Image</h3>
    <form method="post" enctype="multipart/form-data" action="image_stash.php">
        <input type="text" name="title" placeholder="Title"><br><br>
        <input type="file" name="image" required><br><br>
        <button type="submit">Upload</button>
    </form>
</div>



<script>
$(document).on('click', '.copy-btn', function() {
    var path = $(this).data('path'); // get the data-path attribute
    if (navigator.clipboard) {
        navigator.clipboard.writeText(path).then(function() {
            alert('Copied: ' + path);
        }).catch(function(err) {
            console.error('Failed to copy: ', err);
        });
    } else {
        // fallback for older browsers
        var $temp = $('<input>');
        $('body').append($temp);
        $temp.val(path).select();
        document.execCommand('copy');
        $temp.remove();
        alert('Copied: ' + path);
    }
});
</script>




</body>
</html>






