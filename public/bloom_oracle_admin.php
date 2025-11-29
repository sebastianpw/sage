<?php
require_once __DIR__ . '/bootstrap.php';
require      __DIR__ . '/env_locals.php';

require_once PROJECT_ROOT . '/src/Dictionary/DictionaryManager.php';

use App\Dictionary\DictionaryManager;

$dictManager = new DictionaryManager($pdo);
$allDictionaries = $dictManager->getAllDictionaries();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bloom Oracle Admin</title>
    <script>
    (function() {
        try {
            var theme = localStorage.getItem('spw_theme');
            if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        } catch (e) {}
    })();
    </script>
    <link rel="stylesheet" href="/css/base.css">
    <style>
        .result-container {
            background-color: var(--card-alt);
            border: 1px solid rgba(var(--muted-border-rgb), 0.3);
            border-radius: 8px;
            padding: 20px;
            margin-top: 24px;
            min-height: 100px;
        }
        .result-container pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 14px;
        }
        .spinner {
            display: inline-block; width: 16px; height: 16px;
            border: 2px solid rgba(var(--muted-border-rgb), 0.3);
            border-top-color: var(--accent); border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        .hidden { display: none; }
        /* Style for the copy button icon */
        .btn-icon {
            vertical-align: middle;
            margin-right: 6px;
            width: 16px;
            height: 16px;
            stroke-width: 2;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bloom Oracle Admin</h1>
            <a href="dictionaries_admin.php" class="btn btn-secondary">&larr; Dictionary Admin</a>
        </div>

        <div class="card">
            <h3 style="margin-top: 0;">Generate Bloom Hint</h3>
            <p>Select dictionaries and parameters to generate a probabilistic hint for the AI.</p>
            
            <form id="oracleForm" class="form-container" style="padding: 0;">
                <div class="form-group">
                    <label for="dictionary_ids">Source Dictionaries *</label>
                    <select id="dictionary_ids" name="dictionary_ids[]" class="form-control" multiple required size="5">
                        <?php foreach ($allDictionaries as $dict): ?>
                            <option value="<?php echo $dict['id']; ?>">
                                <?php echo htmlspecialchars($dict['title']); ?> (<?php echo number_format($dict['actual_lemma_count']); ?> lemmas)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Hold Ctrl/Cmd to select multiple dictionaries.</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="num_words">Words to Sample</label>
                        <input type="number" id="num_words" name="num_words" class="form-control" value="200" min="10" max="5000">
                        <small>How many random words to build the filter from.</small>
                    </div>
                    <div class="form-group">
                        <label for="error_rate">Error Rate</label>
                        <input type="number" id="error_rate" name="error_rate" class="form-control" value="0.01" step="0.0001" min="0.0001" max="0.5">
                        <small>Lower values create larger filters.</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="seed">Random Seed</label>
                    <input type="number" id="seed" name="seed" class="form-control" placeholder="Optional, for reproducible results">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="generateBtn">
                        <span id="btnText">Generate Hint</span>
                        <span id="btnSpinner" class="spinner" style="display:none;"></span>
                    </button>
                    <!-- ===== NEW: Copy Button (initially hidden) ===== -->
                    <button type="button" class="btn btn-secondary hidden" id="copyBtn">
                        <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        <span id="copyBtnText">Copy JSON</span>
                    </button>
                </div>
            </form>
        </div>

        <div class="result-container" id="resultContainer">
            <pre><code id="resultCode">Results will appear here...</code></pre>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('oracleForm');
    const generateBtn = document.getElementById('generateBtn');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');
    const resultCode = document.getElementById('resultCode');

    // ===== NEW: Get references to the copy button and its text =====
    const copyBtn = document.getElementById('copyBtn');
    const copyBtnText = document.getElementById('copyBtnText');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // UI feedback
        generateBtn.disabled = true;
        btnText.style.display = 'none';
        btnSpinner.style.display = 'inline-block';
        resultCode.textContent = 'Generating...';

        // ===== NEW: Hide the copy button on each new request =====
        copyBtn.classList.add('hidden');

        try {
            const formData = new FormData(form);
            const dictionaryIds = formData.getAll('dictionary_ids[]').join(',');
            
            if (!dictionaryIds) {
                throw new Error('Please select at least one dictionary.');
            }

            const params = new URLSearchParams({
                dictionary_ids: dictionaryIds,
                num_words: formData.get('num_words'),
                error_rate: formData.get('error_rate'),
                seed: formData.get('seed'),
            });
            
            const response = await fetch(`/bloom_oracle.php?${params.toString()}`);
            const result = await response.json();
            
            if (!response.ok || !result.success) {
                throw new Error(result.error || 'An unknown error occurred.');
            }
            
            const finalHint = { bloom_hint: result.data };
            resultCode.textContent = JSON.stringify(finalHint, null, 2);

            // ===== NEW: Show the copy button on success =====
            copyBtn.classList.remove('hidden');

        } catch (error) {
            resultCode.textContent = `Error: ${error.message}`;
        } finally {
            generateBtn.disabled = false;
            btnText.style.display = 'inline-block';
            btnSpinner.style.display = 'none';
        }
    });

    // ===== NEW: Add event listener for the copy button =====
    copyBtn.addEventListener('click', () => {
        const textToCopy = resultCode.textContent;
        navigator.clipboard.writeText(textToCopy).then(() => {
            // Success feedback
            const originalHTML = copyBtn.innerHTML;
            copyBtnText.textContent = 'Copied!';
            copyBtn.disabled = true; // Briefly disable to prevent spamming
            
            setTimeout(() => {
                copyBtnText.textContent = 'Copy JSON';
                copyBtn.disabled = false;
            }, 2000); // Revert after 2 seconds
        }).catch(err => {
            console.error('Failed to copy text: ', err);
            alert('Could not copy text to clipboard.');
        });
    });
});
</script>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>

