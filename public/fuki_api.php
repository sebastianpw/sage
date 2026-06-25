<?php
/**
 * fuki_api.php
 * Fuki — Multilingual Text Overlay Editor API
 */
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

header('Content-Type: application/json; charset=utf-8');

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        
        case 'load_elements':
            $sequenceId = (int)($_GET['sequence_id'] ?? 0);
            $lang       = strtolower(trim($_GET['lang'] ?? 'en'));
            if (!$sequenceId) throw new Exception('sequence_id required');

            // 1. Fetch base English elements
            $stmtEn = $pdo->prepare("SELECT * FROM fuki_texts WHERE sequence_id = ? AND language_code = 'en'");
            $stmtEn->execute([$sequenceId]);
            $enElements = $stmtEn->fetchAll(PDO::FETCH_ASSOC);

            $elementsMap = [];
            foreach ($enElements as $row) {
                $elementsMap[$row['element_uid']] = $row;
            }

            // 2. Fetch and merge Target Language elements (if not EN)
            if ($lang !== 'en') {
                $stmtLang = $pdo->prepare("SELECT * FROM fuki_texts WHERE sequence_id = ? AND language_code = ?");
                $stmtLang->execute([$sequenceId, $lang]);
                foreach ($stmtLang->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    // Override the EN base with Lang-specific translations and adjusted positions
                    $elementsMap[$row['element_uid']] = array_merge($elementsMap[$row['element_uid']] ?? [], $row);
                }
            }

            echo json_encode(['success' => true, 'elements' => array_values($elementsMap)]);
            break;

        case 'save_element':
            $sequenceId = (int)($_POST['sequence_id'] ?? 0);
            $sketchId   = (int)($_POST['sketch_id'] ?? 0);
            $uid        = trim($_POST['element_uid'] ?? '');
            $lang       = strtolower(trim($_POST['lang'] ?? 'en'));
            
            if (!$sequenceId || !$sketchId || !$uid) throw new Exception('Missing identifying parameters');

            // Force an EN base record to exist if we are saving a different language first (anchor logic)
            if ($lang !== 'en') {
                $checkEn = $pdo->prepare("SELECT 1 FROM fuki_texts WHERE element_uid = ? AND language_code = 'en'");
                $checkEn->execute([$uid]);
                if (!$checkEn->fetchColumn()) {
                    $insEn = $pdo->prepare("
                        INSERT IGNORE INTO fuki_texts 
                        (sequence_id, sketch_id, element_uid, language_code, text_content, x, y, width, rotation, font_family, font_size, fill_color, text_align, is_bold, is_italic, is_underline)
                        VALUES (?, ?, ?, 'en', '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $insEn->execute([
                        $sequenceId, $sketchId, $uid, 
                        (float)($_POST['x'] ?? 0), (float)($_POST['y'] ?? 0), (float)($_POST['width'] ?? 200), (float)($_POST['rotation'] ?? 0),
                        trim($_POST['font_family'] ?? 'Bangers'), (float)($_POST['font_size'] ?? 24), trim($_POST['fill_color'] ?? '#111111'),
                        trim($_POST['text_align'] ?? 'center'), (int)($_POST['is_bold'] ?? 0), (int)($_POST['is_italic'] ?? 0), (int)($_POST['is_underline'] ?? 0)
                    ]);
                }
            }

            // Upsert the actual requested language record
            $stmt = $pdo->prepare("
                INSERT INTO fuki_texts 
                (sequence_id, sketch_id, element_uid, language_code, text_content, x, y, width, rotation, font_family, font_size, fill_color, text_align, is_bold, is_italic, is_underline)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                text_content=VALUES(text_content), x=VALUES(x), y=VALUES(y), width=VALUES(width), rotation=VALUES(rotation),
                font_family=VALUES(font_family), font_size=VALUES(font_size), fill_color=VALUES(fill_color), text_align=VALUES(text_align),
                is_bold=VALUES(is_bold), is_italic=VALUES(is_italic), is_underline=VALUES(is_underline)
            ");
            
            $stmt->execute([
                $sequenceId, $sketchId, $uid, $lang,
                $_POST['text_content'] ?? '',
                (float)($_POST['x'] ?? 0),
                (float)($_POST['y'] ?? 0),
                (float)($_POST['width'] ?? 200),
                (float)($_POST['rotation'] ?? 0),
                trim($_POST['font_family'] ?? 'Bangers'),
                (float)($_POST['font_size'] ?? 24),
                trim($_POST['fill_color'] ?? '#111111'),
                trim($_POST['text_align'] ?? 'center'),
                (int)($_POST['is_bold'] ?? 0),
                (int)($_POST['is_italic'] ?? 0),
                (int)($_POST['is_underline'] ?? 0)
            ]);

            echo json_encode(['success' => true]);
            break;

        case 'delete_element':
            $uid = trim($_POST['element_uid'] ?? '');
            if (!$uid) throw new Exception('element_uid required');

            // Deleting a text bubble removes it across ALL languages universally
            $stmt = $pdo->prepare("DELETE FROM fuki_texts WHERE element_uid = ?");
            $stmt->execute([$uid]);

            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception("Unknown action: $action");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}