<?php
// public/recipe_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

header('Content-Type: application/json');

// Use the SYSTEM database connection for all recipe operations
$pdoSys = $spw->getSysPDO();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        case 'list_recipes':
            // We join with recipe_groups to get the name and count ingredients
            $sql = "SELECT 
                        r.id,
                        r.output_filename,
                        r.rerun_command,
                        r.created_at,
                        rg.name as group_name,
                        (SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = r.id) as ingredient_count
                    FROM recipes r
                    JOIN recipe_groups rg ON r.recipe_group_id = rg.id
                    ORDER BY r.created_at DESC
                    LIMIT 200";
            
            $stmt = $pdoSys->query($sql);
            $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['status' => 'ok', 'recipes' => $recipes]);
            break;

        case 'get_recipe_details':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) {
                throw new Exception('Missing Recipe ID');
            }

            // Get all ingredients for a specific recipe
            $sql = "SELECT 
                        ri.source_filename,
                        ris.content_hash
                    FROM recipe_ingredients ri
                    JOIN recipe_ingredient_snapshots ris ON ri.snapshot_id = ris.id
                    WHERE ri.recipe_id = ?
                    ORDER BY ri.display_order ASC";
            
            $stmt = $pdoSys->prepare($sql);
            $stmt->execute([$id]);
            $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'ok', 'ingredients' => $ingredients]);
            break;

        case 'delete_recipe':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            if (!$id) {
                throw new Exception('Missing Recipe ID');
            }

            // The foreign key constraints with ON DELETE CASCADE will handle cleanup
            // in recipe_ingredients automatically.
            $stmt = $pdoSys->prepare("DELETE FROM recipes WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['status' => 'ok', 'message' => 'Recipe deleted']);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

