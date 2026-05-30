<?php
// public/import_rapid_showcase.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$em = $spw->getEntityManager();
$conn = $em->getConnection();

// Mapping Generator Names from MD to Config IDs (based on your SQL dump)
$genMap = [
    // Nova Terra
    'NOVA TERRA NU Anima EDTD NuBloom NuSketch Desc Gen' => '207244d8d8a6ac432783b73e88b6c44d', // ID 33
    // Crater City
    'CRATER CITY NU Anima EDTD NuBloom NuSketch Desc Gen' => 'fbfe77d7d9ddaee5b77d5975419e4257', // ID 34
    // Vortex Station
    'VORTEX STATION NU Anima EDTD NuBloom NuSketch Desc Gen' => 'f0a11d64d501a6cb038d27ec34cbe642', // ID 35
    // Surface / Drift
    'SURFACE NU Anima EDTD NuBloom NuSketch Desc Gen' => '07982d89896066f2dd7e333dd721ed6b', // ID 32
    // Tidalcross Surface (Using Surface Gen as fallback if specific one missing, or ID 32)
    'TC-SURFACE' => '07982d89896066f2dd7e333dd721ed6b', 
    // Fallbacks/Generics
    'Any location generator + Bloom Oracle' => 'e76db8f464c7e35851685a0dbc8f3da8', // ID 9 (Generic NuSketch) or similar
];

// Files to import
$files = [
    'sg_rapid_showcase.md',
    'sg_rapid_tidal.md'
];

echo "<pre>";
echo "<h2>Importing Rapid Showcase Scenarios...</h2>";

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) {
        echo "File not found: $file<br>";
        continue;
    }

    $content = file_get_contents($path);
    echo "Processing $file...<br>";

    // Split by Header 2 (Categories)
    $sections = preg_split('/^##\s+(.+)$/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    
    // Array logic: [0]=preamble, [1]=Header1, [2]=Content1, [3]=Header2, [4]=Content2...
    for ($i = 1; $i < count($sections); $i += 2) {
        $category = trim($sections[$i]);
        $block = $sections[$i+1];

        // Find Generator for this section
        $currentGenId = null;
        if (preg_match('/\*\*Generator\*\*:\s*`?([^`\n]+)`?/i', $block, $m)) {
            $genName = trim($m[1]);
            // Lookup ID
            foreach ($genMap as $key => $id) {
                if (stripos($genName, $key) !== false || stripos($key, $genName) !== false) {
                    $currentGenId = $id;
                    break;
                }
            }
            if (!$currentGenId) $currentGenId = 'e76db8f464c7e35851685a0dbc8f3da8'; // Fallback
        }

        // Parse individual Scenarios (### Header)
        // Regex looks for ### [CODE]: [Title] ... content ... ```[prompt]```
        $pattern = '/###\s*([A-Z0-9-]+):\s*(.*?)\s*\n.*?```(.*?)```/s';
        
        if (preg_match_all($pattern, $block, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $refCode = trim($match[1]);
                $title = trim($match[2]);
                $promptRaw = trim($match[3]);
                
                // Remove newlines from prompt to make it a single string
                $cleanPrompt = preg_replace('/\s+/', ' ', $promptRaw);

                // Insert into DB
                try {
                    // Check if exists
                    $check = $conn->fetchOne("SELECT id FROM rapid_showcase WHERE reference_code = ?", [$refCode]);
                    
                    if (!$check) {
                        $stmt = $conn->prepare("INSERT INTO rapid_showcase (reference_code, title, category, description_prompt, generator_config_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        $stmt->bindValue(1, $refCode);
                        $stmt->bindValue(2, $title);
                        $stmt->bindValue(3, $category);
                        $stmt->bindValue(4, $cleanPrompt);
                        $stmt->bindValue(5, $currentGenId);
                        $stmt->executeStatement();
                        echo "  [OK] Imported: $refCode - $title<br>";
                    } else {
                        echo "  [SKIP] Exists: $refCode<br>";
                    }
                } catch (Exception $e) {
                    echo "  [ERR] $refCode: " . $e->getMessage() . "<br>";
                }
            }
        }
    }
}
echo "Done.";
