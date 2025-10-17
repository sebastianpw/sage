<?php 
require_once __DIR__ . '/bootstrap.php'; 
require __DIR__ . '/env_locals.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST']; // your domain, e.g., example.com
$baseUrl = $protocol . '://' . $host;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>phpMyAdmin</title>
<link rel="stylesheet" href="css/form.css">
<style>
    .file-list { list-style: none; padding: 0; }
    .file-list li { margin-bottom: 0.3em; }
    .result.success { color: #1a7f37; font-weight: 600; }
    .result.error { color: #b42318; font-weight: 600; }
</style>
<script>
function toggleSelectAll(source) {
    checkboxes = document.getElementsByName('files[]');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}
</script>
</head>
<body>




<div style="position: relative;">

    <div style="position: absolute;">

        <a href="/dashboard.php" 
           title="Dashboard" 
           style="text-decoration: none; font-size: 24px; display: inline-block; position: absolute; top: 10px; left: 10px; z-index: 999;">
            &#x1F5C3;
        </a>

        <h2 style="margin: 0; padding: 0 0 20px 0; position: absolute; top: 10px; left: 50px;">
            phpMyAdmin
        </h2>          

    </div>

</div>


<div style="position: absolute; top: 70px; height: 660px;">
    <iframe style="height: 660px; width: 350px;" src="<?php echo $baseUrl; ?>/admin/"></iframe>
</div>

</body>
</html>
