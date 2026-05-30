<?php
// public/api_md.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

header('Content-Type: application/json');

function jsonResponse($status, $message, $data = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'POST') {

        // --- NEW: IMPORT MD FILE via multipart/form-data ---
        if ($action === 'import_md') {
            // Expecting a multipart/form-data upload with field 'file' and optional 'category_id'
            if (!isset($_FILES['file'])) {
                jsonResponse('error', 'No file uploaded');
            }

            $file = $_FILES['file'];
            if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
                jsonResponse('error', 'Upload error (code ' . ($file['error'] ?? 'unknown') . ')');
            }

            // Basic safety checks
            $maxSize = 5 * 1024 * 1024; // 5 MB
            if ($file['size'] > $maxSize) {
                jsonResponse('error', 'File too large');
            }

            $allowedExt = ['md', 'markdown', 'txt'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt)) {
                jsonResponse('error', 'Invalid file type. Allowed: .md, .markdown, .txt');
            }

            $content = file_get_contents($file['tmp_name']);
            if ($content === false) {
                jsonResponse('error', 'Failed to read uploaded file');
            }

            // Sanitize filename (use filename without extension as 'name')
            $baseName = pathinfo($file['name'], PATHINFO_FILENAME);
            $safeName = trim(preg_replace('/[^a-z0-9 _\-\.]/i', '', $baseName));
            if ($safeName === '') $safeName = 'Imported Document';

            // category can come via POST field (multipart) or omitted
            $catId = null;
            if (isset($_POST['category_id']) && $_POST['category_id'] !== '') {
                $catId = (int)$_POST['category_id'];
                if ($catId === 0) $catId = null;
            }

            $stmt = $pdo->prepare("INSERT INTO documentations (name, content, description, category_id, type) VALUES (?, ?, ?, ?, 'md')");
            $stmt->execute([$safeName, $content, null, $catId]);
            $newId = $pdo->lastInsertId();

            jsonResponse('success', 'Imported', ['id' => $newId]);
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // 1. SAVE DOCUMENT
        if ($action === 'save') {
            $id = $input['id'] ?? null;
            $content = $input['content'] ?? '';
            $description = $input['description'] ?? '';
            $desc_short = $input['desc_short'] ?? '';
            $keywords = $input['keywords'] ?? '';
            $name = $input['name'] ?? 'Untitled Doc';
            $catId = $input['category_id'] ?? 1;
            
            // Handle target_collection (now stores collection NAME, not ID)
            $targetCollection = !empty($input['target_collection']) ? $input['target_collection'] : null;

            if ($id) {
                // Update existing - include desc_short and keywords
                $stmt = $pdo->prepare("UPDATE documentations SET content = ?, description = ?, desc_short = ?, keywords = ?, name = ?, category_id = ?, target_collection = ? WHERE id = ?");
                $stmt->execute([$content, $description, $desc_short, $keywords, $name, $catId, $targetCollection, $id]);
                jsonResponse('success', 'Document updated', ['id' => $id]);
            } else {
                // Create new - include desc_short and keywords
                $stmt = $pdo->prepare("INSERT INTO documentations (name, content, description, desc_short, keywords, category_id, target_collection) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $content, $description, $desc_short, $keywords, $catId, $targetCollection]);
                $newId = $pdo->lastInsertId();
                jsonResponse('success', 'Document created', ['id' => $newId]);
            }
        }
        
        // 2. TOGGLE AUDIO
        if ($action === 'toggle_audio') {
            $id = $input['id'] ?? null;
            $state = $input['state'] ?? 0;
            if ($id) {
                $stmt = $pdo->prepare("UPDATE documentations SET regenerate_audios = ? WHERE id = ?");
                $stmt->execute([$state, $id]);
                jsonResponse('success', 'Audio flag updated');
            } else {
                jsonResponse('error', 'No ID provided');
            }
        }

        // 3. DELETE DOCUMENT
        if ($action === 'delete') {
            $id = $input['id'] ?? null;
            if ($id) {
                $stmt = $pdo->prepare("UPDATE documentations SET is_active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                jsonResponse('success', 'Document deleted');
            } else {
                jsonResponse('error', 'No ID provided');
            }
        }

        // 4. CREATE CATEGORY
        if ($action === 'create_category') {
            $name = trim($input['name'] ?? '');
            if ($name) {
                $stmt = $pdo->prepare("SELECT id FROM documentation_categories WHERE name = ?");
                $stmt->execute([$name]);
                if($stmt->fetch()) {
                    jsonResponse('error', 'Category already exists');
                }

                $stmt = $pdo->prepare("INSERT INTO documentation_categories (name) VALUES (?)");
                $stmt->execute([$name]);
                jsonResponse('success', 'Category created', ['id' => $pdo->lastInsertId(), 'name' => $name]);
            } else {
                jsonResponse('error', 'Invalid name');
            }
        }
    } 
    elseif ($method === 'GET') {
        
        // 5. GET DOCUMENT
        if ($action === 'get' && isset($_GET['id'])) {
            // Now selecting target_collection (NAME) instead of target_collection_id
            $stmt = $pdo->prepare("SELECT id, name, content, description, desc_short, keywords, category_id, target_collection FROM documentations WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($doc) {
                if(is_null($doc['content'])) $doc['content'] = '';
                if(is_null($doc['description'])) $doc['description'] = '';
                if(is_null($doc['desc_short'])) $doc['desc_short'] = '';
                if(is_null($doc['keywords'])) $doc['keywords'] = '';
                if(is_null($doc['target_collection'])) $doc['target_collection'] = '';
                jsonResponse('success', 'Loaded', ['data' => $doc]);
            } else {
                jsonResponse('error', 'Document not found');
            }
        }

        // 6. GET TTS TEXT
        if ($action === 'get_tts_text' && isset($_GET['id'])) {
            header('Content-Type: text/plain');
            $stmt = $pdo->prepare("SELECT content FROM documentations WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($doc && !empty($doc['content'])) {
                $currentReporting = error_reporting();
                error_reporting($currentReporting & ~E_DEPRECATED);
                $parsedown = new Parsedown();
                $html = $parsedown->text($doc['content']);
                error_reporting($currentReporting);

                $html = str_replace(['</p>', '<br>', '<br />', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>', '</li>', '</div>', '</blockquote>', '</pre>'], "\n", $html);
                $text = strip_tags($html);
                $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
                $text = preg_replace('/\n\s*\n/', "\n\n", $text); 
                echo trim($text);
            } else {
                echo "";
            }
            exit;
        }

        // 7. GET CATEGORIES
        if ($action === 'get_categories') {
            $stmt = $pdo->query("SELECT id, name FROM documentation_categories ORDER BY name ASC");
            jsonResponse('success', 'Loaded', ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // 8. GET CHROMA COLLECTIONS
        if ($action === 'get_chroma_collections') {
            $stmt = $pdo->query("SELECT id, name, type FROM chroma_collections ORDER BY name ASC");
            jsonResponse('success', 'Loaded', ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // 9. FULL TEXT SEARCH
        if ($action === 'search') {
            $q = $_GET['q'] ?? '';
            $stmt = $pdo->prepare("SELECT id FROM documentations WHERE is_active = 1 AND (name LIKE ? OR content LIKE ?)");
            $term = '%' . $q . '%';
            $stmt->execute([$term, $term]);
            jsonResponse('success', 'Search results', ['ids' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    jsonResponse('error', $e->getMessage());
}