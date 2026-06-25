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

        // --- EXPORT DOCS ---
        if ($action === 'export_docs') {
            $docIds = $input['doc_ids'] ?? [];
            $withMeta = !empty($input['with_meta']);
            
            if (empty($docIds)) {
                jsonResponse('error', 'No documents selected');
            }
            
            $placeholders = implode(',', array_fill(0, count($docIds), '?'));
            $stmt = $pdo->prepare("
                SELECT d.*, c.name as category_name 
                FROM documentations d 
                LEFT JOIN documentation_categories c ON d.category_id = c.id 
                WHERE d.id IN ($placeholders) AND d.is_active = 1
            ");
            $stmt->execute($docIds);
            $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $exportData = [];
            foreach ($docs as $doc) {
                $item = [
                    'id' => (int)$doc['id'],
                    'name' => $doc['name'],
                    'category' => $doc['category_name'] ?: 'Uncategorized',
                    'content' => $doc['content']
                ];
                if ($withMeta) {
                    $item['description'] = $doc['description'];
                    $item['desc_short'] = $doc['desc_short'];
                    $item['keywords'] = $doc['keywords'];
                    $item['target_collection'] = $doc['target_collection'];
                }
                $exportData[] = $item;
            }
            
            $snapshot = [
                'export_meta' => [
                    'generated_at' => date('c'),
                    'export_type' => 'md_documents',
                    'total_documents' => count($exportData),
                    'with_meta' => $withMeta
                ],
                'documents' => $exportData
            ];
            
            jsonResponse('success', 'Export built', ['snapshot' => $snapshot]);
        }

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
        
        // --- EXPORT TREE DATA ---
        if ($action === 'export_tree_data') {
            $cats = $pdo->query("SELECT id, name FROM documentation_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            $docs = $pdo->query("SELECT id, name, category_id FROM documentations WHERE is_active = 1 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse('success', 'Tree data', ['categories' => $cats, 'docs' => $docs]);
        }

        // --- EXPORT AJAX SEARCH ---
        if ($action === 'export_search') {
            $q = trim($_GET['q'] ?? '');
            if ($q === '') jsonResponse('success', '', ['hits' => []]);
            
            $term = '%' . $q . '%';
            $stmt = $pdo->prepare("SELECT id, name, content FROM documentations WHERE is_active = 1 AND (name LIKE ? OR content LIKE ?)");
            $stmt->execute([$term, $term]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $hits = [];
            foreach ($results as $r) {
                $nameHit = htmlspecialchars($r['name']);
                $nameHit = preg_replace("/(" . preg_quote($q, '/') . ")/i", "<mark style='background:rgba(245,158,11,0.4); color:inherit; border-radius:2px; padding:0 2px;'>$1</mark>", $nameHit);
                
                $excerpt = '';
                $snippet = strip_tags($r['content'] ?? '');
                $pos = stripos($snippet, $q);
                if ($pos !== false) {
                    $start = max(0, $pos - 40);
                    $snippetSub = mb_substr($snippet, $start, 100);
                    $snippetSub = htmlspecialchars($snippetSub);
                    $snippetSub = preg_replace("/(" . preg_quote($q, '/') . ")/i", "<mark style='background:rgba(245,158,11,0.4); color:inherit; border-radius:2px; padding:0 2px;'>$1</mark>", $snippetSub);
                    $excerpt = '...' . $snippetSub . '...';
                }
                
                $hits[] = [
                    'id' => (int)$r['id'],
                    'name_hl' => $nameHit,
                    'excerpt' => $excerpt
                ];
            }
            jsonResponse('success', 'Search results', ['hits' => $hits]);
        }

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