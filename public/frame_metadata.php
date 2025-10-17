<?php
require __DIR__ . '/bootstrap.php'; // Adjust path if necessary

// Fetch the name from the query parameter
$name = isset($_GET['name']) ? $_GET['name'] : null;
if (!$name) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'name parameter is required']);
    exit;
}

// Query the frames table for metadata based on the name
$stmt = $pdo->prepare("SELECT * FROM frames WHERE name = ?");
$stmt->execute([$name]);
$frame = $stmt->fetch(PDO::FETCH_ASSOC);

if ($frame) {
    // Frame data
    $frame_id = $frame['id']; // Frame ID
    $entity_name = 'Entity for frameId ' . $frame_id . ' with name ' . $frame['name'] ?? 'Unknown Entity';
    $entity_description = $frame['prompt'] ?? 'No description available.';
    $entity_type = $frame['entity_type'] ?? 'None';
    $entity_id = $frame['entity_id'] ?? null;

    // Query the referenced entity table
    if ($frame['entity_type'] && $frame['entity_id']) {
        $entityTable = $frame['entity_type'];
        $entityStmt = $pdo->prepare("SELECT * FROM `$entityTable` WHERE id = ?");
        $entityStmt->execute([$frame['entity_id']]);
        $entityData = $entityStmt->fetch(PDO::FETCH_ASSOC);

        // Get referenced entity data if available
        if ($entityData) {
            $entity_name = $entityData['name'] ?? $entity_name;
            $entity_description = $entityData['description'] ?? $entity_description;
        }
    }

    // Return the response including the frame's id
    echo json_encode([
        'frame_id' => $frame_id, // Display the frame's id
        'entity_name' => $entity_name,
        'entity_description' => $entity_description,
        'entity_type' => $entity_type,
        'entity_id' => $entity_id
    ]);
} else {
    // Handle if no frame was found
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Frame not found']);
}
?>
