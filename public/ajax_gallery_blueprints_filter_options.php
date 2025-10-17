<?php require_once __DIR__ . '/bootstrap.php'; require __DIR__ . '/env_locals.php';

header('Content-Type: application/json');

$spw = \App\Core\SpwBase::getInstance();
$mysqli = $spw->getMysqli();

$entity = $_GET['entity'] ?? '';

if ($entity !== 'prompt_matrix_blueprints') {
    echo json_encode(['error' => 'Invalid entity']);
    exit;
}

// Get current filter state
$entityType = $_GET['entity_type'] ?? 'all';
$entityId = $_GET['entity_id'] ?? 'all';

// Build WHERE clause based on current filters
$where = [];
if ($entityType !== 'all') {
    $where[] = "blueprint_entity_type='" . $mysqli->real_escape_string($entityType) . "'";
}
if ($entityId !== 'all') {
    $where[] = "blueprint_entity_id='" . $mysqli->real_escape_string($entityId) . "'";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$result = [];

// Fetch entity types (always unfiltered)
$sql = "SELECT DISTINCT blueprint_entity_type FROM v_gallery_prompt_matrix_blueprints 
        WHERE blueprint_entity_type IS NOT NULL 
        ORDER BY blueprint_entity_type";
$res = $mysqli->query($sql);
$result['blueprint_entity_type'] = [];
while ($row = $res->fetch_assoc()) {
    $result['blueprint_entity_type'][] = $row['blueprint_entity_type'];
}

// Fetch entity IDs (filtered by entity_type if selected)
$entityIdWhere = $entityType !== 'all' 
    ? "WHERE blueprint_entity_type='" . $mysqli->real_escape_string($entityType) . "'" 
    : '';
$sql = "SELECT DISTINCT blueprint_entity_id FROM v_gallery_prompt_matrix_blueprints 
        $entityIdWhere
        AND blueprint_entity_id IS NOT NULL 
        ORDER BY blueprint_entity_id";
$res = $mysqli->query($sql);
$result['blueprint_entity_id'] = [];
while ($row = $res->fetch_assoc()) {
    $result['blueprint_entity_id'][] = $row['blueprint_entity_id'];
}

// Fetch blueprint names (filtered by both entity_type AND entity_id)
$sql = "SELECT DISTINCT blueprint_name FROM v_gallery_prompt_matrix_blueprints 
        $whereClause
        AND blueprint_name IS NOT NULL 
        ORDER BY blueprint_name";
$res = $mysqli->query($sql);
$result['blueprint_name'] = [];
while ($row = $res->fetch_assoc()) {
    $result['blueprint_name'][] = $row['blueprint_name'];
}

echo json_encode($result);
