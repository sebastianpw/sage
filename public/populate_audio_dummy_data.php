<?php
/**
 * public/populate_audio_dummy_data.php
 * Generates dummy data for testing Audio CRUD interfaces.
 */

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// --- CONFIGURATION ---
$targetLines = 200;
$truncateFirst = false; // Set to true if you want to wipe tables automatically before filling

// Fake Data Sources
$characters = [
    1 => 'Commander Shepard',
    2 => 'Garrus Vakarian',
    3 => 'Liara Tsoni', 
    4 => 'The Illusive Man',
    5 => 'Joker'
];

$sentences = [
    "I've got a bad feeling about this mission.",
    "Target locked. Engaging systems.",
    "Did you hear that noise in the ventilation?",
    "We are running out of time, Commander.",
    "Systems optimal. Weapons charged.",
    "I don't think we're alone here.",
    "Scanning the horizon for hostiles.",
    "Shields are down! Taking cover!",
    "Requesting immediate evac at sector 7.",
    "The data stream is corrupted.",
    "Hold your fire! Friendly!",
    "Initiating warp drive sequence.",
    "What is your command, sir?",
    "The ancient artifact is glowing again.",
    "This creates a resonance in the mass effect field."
];

$prefixes = ['Cmd', 'Act', 'React', 'Emote', 'Narrative'];

// --- EXECUTION ---

echo "<pre>";
echo "Starting Dummy Data Generation...\n";

if ($truncateFirst) {
    echo "Truncating tables...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE audio_dialogue_lines");
    $pdo->exec("TRUNCATE TABLE audios_2_audio_dialogue_lines");
    // Note: We might not want to truncate 'audios' entirely if it holds data for other entities,
    // but for this example script we will assume clean slate or append.
    // $pdo->exec("TRUNCATE TABLE audios"); 
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
}

$pdo->beginTransaction();

try {
    $audioCount = 0;

    for ($i = 1; $i <= $targetLines; $i++) {
        // 1. Generate Dialogue Line
        $charId = array_rand($characters);
        $sentence = $sentences[array_rand($sentences)];
        $prefix = $prefixes[array_rand($prefixes)];
        $name = sprintf("%s_%03d", $prefix, $i);
        
        $stmt = $pdo->prepare("INSERT INTO audio_dialogue_lines 
            (`name`, `description`, `order`, `character_id`, `created_at`) 
            VALUES (:name, :desc, :order, :char, NOW())");
        
        $stmt->execute([
            'name' => $name,
            'desc' => $sentence,
            'order' => $i,
            'char' => $charId
        ]);
        
        $lineId = $pdo->lastInsertId();

        // 2. Decide if we have audios (80% chance)
        if (rand(1, 100) <= 80) {
            // Generate 1 to 3 takes
            $takes = rand(1, 3);
            
            for ($t = 1; $t <= $takes; $t++) {
                // Fake filename
                $safeName = strtolower($name);
                $filename = "/storage/audios/inference/{$safeName}_take{$t}.wav";
                
                // Insert into 'audios'
                // Note: We populate entity_type/id AND the mapping table as requested
                $stmtAudio = $pdo->prepare("INSERT INTO audios 
                    (`name`, `filename`, `entity_type`, `entity_id`, `created_at`, `rvc_model_name`) 
                    VALUES (:name, :filename, 'audio_dialogue_lines', :eid, NOW(), :model)");
                
                $stmtAudio->execute([
                    'name' => "{$name}_v{$t}",
                    'filename' => $filename,
                    'eid' => $lineId,
                    'model' => 'RVC_Model_v2_Epoch' . rand(10, 100)
                ]);
                
                $audioId = $pdo->lastInsertId();
                $audioCount++;

                // 3. Insert into Mapping Table 'audios_2_audio_dialogue_lines'
                // Assuming from_id = audios.id, to_id = dialogue_line.id based on standard linking
                $stmtMap = $pdo->prepare("INSERT IGNORE INTO audios_2_audio_dialogue_lines 
                    (`from_id`, `to_id`) VALUES (:aid, :lid)");
                
                $stmtMap->execute([
                    'aid' => $audioId,
                    'lid' => $lineId
                ]);
            }
        }
    }

    $pdo->commit();
    echo "Success!\n";
    echo "Generated $targetLines dialogue lines.\n";
    echo "Generated $audioCount audio inferences.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}

echo "</pre>";
?>