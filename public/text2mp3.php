<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/VoicePool.php';

// Initialize VoicePool
$voicePool = new VoicePool();
try {
    $voices = $voicePool->listModels();
} catch (Exception $e) {
    $voices = [];
    $spw->getFileLogger()->debug(['VoicePool error: ' => $e->getMessage()]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Text to MP3</title>
<link rel="stylesheet" href="/css/toast.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="/js/toast.js"></script>
<?php echo $eruda; ?>
<style>
body { font-family: sans-serif; padding: 20px; }
textarea { width: 100%; height: 150px; font-family: monospace; }
button { margin-top: 10px; padding: 10px 15px; }
select { margin-top: 10px; padding: 5px; width: 100%; }
#loader { display: none; margin-top: 10px; font-weight: bold; }
</style>
</head>
<body>

<h2>Text to MP3</h2>

<label for="voice">Select Voice:</label>
<select id="voice">
    <?php foreach($voices as $key => $label): ?>
        <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($key); ?></option>
    <?php endforeach; ?>
</select>

<label for="text">Enter Text (max 250 words):</label>
<textarea id="text"></textarea>

<button id="sendBtn">Generate MP3 & Download</button>
<div id="loader">Processing... Please wait.</div>

<div id="toast-container"></div>

<script>
$(document).ready(function(){
    $('#sendBtn').click(function(){
        let text = $('#text').val().trim();
        let words = text.split(/\s+/);
        if(words.length > 250){
            Toast.show('Text too long! Max 250 words.', 'error');
            return;
        }

        let model = $('#voice').val();
        if(!model){
            Toast.show('Please select a voice.', 'error');
            return;
        }

        Toast.show('Request sent! MP3 will download when ready.', 'info');
        $('#loader').show();
        $(this).prop('disabled', true);

        $.ajax({
            url: 'tts_generate.php',
            method: 'POST',
            data: { text: text, model: model },
            xhrFields: { responseType: 'blob' },
            success: function(blob){
                $('#loader').hide();
                $('#sendBtn').prop('disabled', false);

                let url = window.URL.createObjectURL(blob);
                let a = document.createElement('a');
                a.href = url;
                a.download = 'tts_output.mp3';
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);

                Toast.show('Download started!', 'success');
            },
            error: function(xhr){
                $('#loader').hide();
                $('#sendBtn').prop('disabled', false);
                Toast.show('TTS request failed!', 'error');
                console.error(xhr);
            }
        });
    });
});
</script>

</body>
</html>
