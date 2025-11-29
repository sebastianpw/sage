<?php
require_once __DIR__ . '/bootstrap.php';
require      __DIR__ . '/env_locals.php';
require_once PROJECT_ROOT . '/src/Dictionary/DictionaryManager.php';

use App\Dictionary\DictionaryManager;

$dictManager = new DictionaryManager($pdo);
$dict = [
    'id' => '', 'title' => '', 'slug' => '', 'description' => '',
    'source_author' => '', 'source_title' => '', 'language_code' => 'en', 
    'sort_order' => 0
];
$pageTitle = 'Create New Dictionary';
$formAction = 'save';

if (isset($_GET['id'])) {
    $dict = $dictManager->getDictionaryById($_GET['id']);
    $pageTitle = 'Edit Dictionary';
    $formAction = 'update';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <script>
    (function() {
        try {
            var theme = localStorage.getItem('spw_theme');
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else if (theme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        } catch (e) {}
    })();
    </script>
    <link rel="stylesheet" href="/css/base.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $pageTitle; ?></h1>
            <a href="dictionaries_admin.php">&larr; Back to Admin</a>
        </div>

        <div class="form-container">
            <form action="dictionaries_admin.php" method="post">
                <input type="hidden" name="action" value="<?php echo $formAction; ?>">
                <?php if ($dict['id']): ?>
                    <input type="hidden" name="id" value="<?php echo $dict['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="title">Dictionary Title *</label>
                    <input type="text" id="title" name="title" class="form-control" 
                           value="<?php echo htmlspecialchars($dict['title']); ?>" required>
                    <small>e.g., "Tropic of Cancer Vocabulary" or "Miller Complete Works"</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="slug">URL Slug</label>
                        <input type="text" id="slug" name="slug" class="form-control" 
                               value="<?php echo htmlspecialchars($dict['slug']); ?>">
                        <small>Leave blank to auto-generate from title.</small>
                    </div>

                    <div class="form-group">
                        <label for="sort_order">Sort Order</label>
                        <input type="number" id="sort_order" name="sort_order" class="form-control" 
                               value="<?php echo (int)$dict['sort_order']; ?>">
                        <small>Higher numbers appear first.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($dict['description']); ?></textarea>
                    <small>Optional description of this dictionary's purpose or content.</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="source_author">Source Author</label>
                        <input type="text" id="source_author" name="source_author" class="form-control" 
                               value="<?php echo htmlspecialchars($dict['source_author']); ?>">
                        <small>e.g., Henry Miller</small>
                    </div>

                    <div class="form-group">
                        <label for="source_title">Source Title</label>
                        <input type="text" id="source_title" name="source_title" class="form-control" 
                               value="<?php echo htmlspecialchars($dict['source_title']); ?>">
                        <small>e.g., Tropic of Cancer</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="language_code">Language Code</label>
                    <select id="language_code" name="language_code" class="form-control">
                        <option value="en" <?php echo ($dict['language_code'] === 'en') ? 'selected' : ''; ?>>English (en)</option>
                        <option value="de" <?php echo ($dict['language_code'] === 'de') ? 'selected' : ''; ?>>German (de)</option>
                        <option value="fr" <?php echo ($dict['language_code'] === 'fr') ? 'selected' : ''; ?>>French (fr)</option>
                        <option value="es" <?php echo ($dict['language_code'] === 'es') ? 'selected' : ''; ?>>Spanish (es)</option>
                        <option value="it" <?php echo ($dict['language_code'] === 'it') ? 'selected' : ''; ?>>Italian (it)</option>
                    </select>
                    <small>Language for lemmatization processing.</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Dictionary</button>
                    <a href="dictionaries_admin.php" class="btn-link">Cancel</a>
                </div>
            </form>
        </div>

        <?php if ($dict['id']): ?>
            <div class="section" style="margin-top: 24px;">
                <h2 class="section-header">Next Steps</h2>
                <div class="card">
                    <p>Dictionary created! Now you can:</p>
                    <div class="flex-gap" style="margin-top: 16px;">
                        <a href="dictionary_parse.php?id=<?php echo $dict['id']; ?>" class="btn btn-primary">
                            Upload & Parse Texts
                        </a>
                        <a href="lemma_viewer.php?dict_id=<?php echo $dict['id']; ?>" class="btn btn-secondary">
                            View Lemmas
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
