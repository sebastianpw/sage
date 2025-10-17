<?php
require __DIR__ . '/bootstrap.php';

$spw = \App\Core\SpwBase::getInstance();
$mysqli = $spw->getMysqli();

// Get offset and chunk size from query parameters
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$chunkGet = isset($_GET['chunk']) ? (int)$_GET['chunk'] : 50;

// Fetch one pastebin entry
$stmt = $mysqli->prepare("SELECT id, description FROM pastebin ORDER BY `order` ASC LIMIT 1 OFFSET ?");
$stmt->bind_param('i', $offset);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo "No pastebin entry found for offset $offset.\n";
    exit;
}

// Split description into n-word chunks
$words = preg_split('/\s+/', trim($row['description']));
$chunks = array_chunk($words, $chunkGet);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pastebin Chunks</title>
<link rel="stylesheet" href="/css/toast.css">
<style>
.copy-div {
    border: 1px solid #ccc;
    padding: 10px;
    margin: 10px 0;
    white-space: pre-wrap;
    font-family: monospace;
    cursor: pointer;
    background-color: #f9f9f9;
overflow: auto;
}
.copy-div:hover {
    background-color: #eef;
}
</style>

<?php echo $eruda; ?>

</head>
<body>

<h2>Pastebin ID: <?php echo htmlspecialchars($row['id']); ?></h2>
<p>Double-click a chunk to copy it to clipboard.</p>

<?php foreach ($chunks as $i => $chunkWords):
    $chunkText = implode(' ', $chunkWords);
    $encodedText = rawurlencode($chunkText); // URL-encode for safe curl usage
    $fileId = $row['id'] . '_' . ($i + 1);
    $curlCommand = "curl -X POST \"http://localhost:8008/synthesize/?model=ramona&text=$encodedText\" --output {$fileId}.mp3";
?>
<div class="copy-div" ondblclick="copyToClipboard(this)">
<?php echo htmlspecialchars($curlCommand); ?>
</div>
<?php endforeach; ?>

<div id="toast-container"></div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>                                             
<script src="/js/toast.js"></script>

<script>
// Copy to clipboard and show Toast
function copyToClipboard(div) {
    const text = div.textContent;
    navigator.clipboard.writeText(text).then(() => {
        Toast.show('Chunk copied to clipboard!', 'success');
    }).catch(err => {
        console.error('Failed to copy text: ', err);
        Toast.show('Failed to copy text', 'error');
    });
}
</script>

</body>
</html>
