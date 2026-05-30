<?php
// public/cli_rapid_gen.php
// Run this from the command line: php public/cli_rapid_gen.php

// 1. Load the Application Environment
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

define('CLI_MODE', true);

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;
use App\Core\AIProvider;

// --- CONFIGURATION ---
$userId = 1; // Assuming Admin ID 1 for CLI operations
$fallbackDescGenId = '446437576e785bbf3d188624dd9794eb'; // Fallback NuSketch Desc Gen
$nameGenId = '9bf6de291765e2ced28589de857a9f0b';       // NuEntity Name Gen ID
$delaySeconds = 2; // Cool-down between generations to be polite to APIs

// --- ANSI COLORS ---
const C_RESET = "\033[0m";
const C_RED = "\033[31m";
const C_GREEN = "\033[32m";
const C_YELLOW = "\033[33m";
const C_BLUE = "\033[34m";
const C_CYAN = "\033[36m";

// --- SETUP SERVICES ---
$em = $spw->getEntityManager();
$conn = $em->getConnection();
$repo = $em->getRepository(GeneratorConfig::class);

// Initialize AI Services
$logger = $spw->getFileLogger();
$aiProvider = $spw->getAIProvider(); // Should be available via spw or new AIProvider($logger)
if (!$aiProvider) $aiProvider = new AIProvider($logger);

$validator = new SchemaValidator();
$normalizer = new ResponseNormalizer();
$generatorService = new GeneratorService($aiProvider, $validator, $normalizer, $logger);

echo "\n" . C_CYAN . "==========================================" . C_RESET . "\n";
echo C_CYAN . "   🚀 RAPID SHOWCASE CLI GENERATOR" . C_RESET . "\n";
echo C_CYAN . "==========================================" . C_RESET . "\n";

// --- MAIN LOOP ---

while (true) {
    // 1. Fetch Next Job (Excluding Archived)
    $sql = "SELECT * FROM rapid_showcase WHERE is_generated = 0 AND is_archived = 0 ORDER BY id ASC LIMIT 1";
    $job = $conn->fetchAssociative($sql);

    if (!$job) {
        echo "\n" . C_GREEN . "✅ All scenarios processed! Exiting." . C_RESET . "\n";
        break;
    }

    $refCode = $job['reference_code'];
    $title = $job['title'];
    $cat = $job['category'];
    $configId = $job['generator_config_id'] ?: $fallbackDescGenId;

    echo "\n" . C_YELLOW . "Processing Job [{$job['id']}]: $refCode" . C_RESET . "\n";
    echo "   Category: $cat\n";
    echo "   Scenario: $title\n";

    try {
        // 2. Prepare Context (Same as JS)
        $context = "TITLE: $title\nCATEGORY: $cat\n\nSCENARIO:\n" . $job['description_prompt'];

        // 3. Load Description Generator Config
        $descConfig = $repo->findOneBy(['configId' => $configId]);
        
        // Fallback if specific config not found/inactive, try default
        if (!$descConfig) {
            echo C_RED . "   Warning: Assigned config $configId not found. Using fallback." . C_RESET . "\n";
            $descConfig = $repo->findOneBy(['configId' => $fallbackDescGenId]);
        }
        
        if (!$descConfig) {
            throw new Exception("No valid generator configuration found.");
        }

        echo "   Using Generator: " . $descConfig->getTitle() . "\n";

        // 4. Generate Description
        echo "   ⚡ Generating Description... ";
        $descResult = $generatorService->generate($descConfig, [
            'entity_name' => $context, // This variable maps to context in most configs
            'random_seed' => rand(1, 999999)
        ]);

        if (!$descResult->isSuccess()) {
            throw new Exception("Description Generation failed.");
        }

        // Extract text safely
        $generatedData = $descResult->getData();
        $finalDescription = '';
        if (is_array($generatedData)) {
            $finalDescription = $generatedData['description'] ?? $generatedData['text'] ?? json_encode($generatedData);
        } else {
            $finalDescription = (string)$generatedData;
        }
        echo C_GREEN . "OK" . C_RESET . "\n";

        // 5. Generate Name
        echo "   ⚡ Generating Name... ";
        $nameConfig = $repo->findOneBy(['configId' => $nameGenId]);
        if (!$nameConfig) {
            // Fallback: use title from DB if name gen missing
            $finalNamePart = $title;
            echo C_RED . " (Name Gen Missing, using Title) " . C_RESET;
        } else {
            $nameResult = $generatorService->generate($nameConfig, [
                'entity_name' => $finalDescription, // Pass description as context for name
                'entity_type' => 'sketch',
                'random_seed' => rand(1, 999999)
            ]);
            
            $nameData = $nameResult->getData();
            $finalNamePart = '';
             if (is_array($nameData)) {
                $finalNamePart = $nameData['name'] ?? $nameData['text'] ?? $title;
            } else {
                $finalNamePart = (string)$nameData;
            }
            // Clean quotes
            $finalNamePart = trim($finalNamePart, '"\'');
            echo C_GREEN . "OK ($finalNamePart)" . C_RESET . "\n";
        }

        // Combine Reference + Name (No brackets in desc, strictly in name)
        $finalName = "$refCode: $finalNamePart";

        // 6. Save to Sketches Table
        echo "   💾 Saving to DB... ";
        
        $insertSql = "INSERT INTO sketches (name, description, `order`, created_at, updated_at) VALUES (?, ?, 0, NOW(), NOW())";
        $stmtInsert = $conn->prepare($insertSql);
        $stmtInsert->bindValue(1, $finalName);
        $stmtInsert->bindValue(2, $finalDescription);
        $stmtInsert->executeStatement();
        
        $newSketchId = $conn->lastInsertId();

        // 7. Update Rapid Showcase Table
        $updateSql = "UPDATE rapid_showcase SET is_generated = 1, created_sketch_id = ? WHERE id = ?";
        $stmtUpdate = $conn->prepare($updateSql);
        $stmtUpdate->bindValue(1, $newSketchId);
        $stmtUpdate->bindValue(2, $job['id']);
        $stmtUpdate->executeStatement();

        echo C_GREEN . "Done (Sketch ID: $newSketchId)" . C_RESET . "\n";

        // 8. Cooldown
        if ($delaySeconds > 0) {
            // Echo a little progress dot
            for ($i = 0; $i < $delaySeconds; $i++) {
                usleep(1000000); // 1 sec
                echo "."; 
            }
            echo "\n";
        }

    } catch (Exception $e) {
        echo C_RED . "\n   ❌ ERROR: " . $e->getMessage() . C_RESET . "\n";
        // Let's sleep longer on error to prevent hammer loops.
        sleep(5);
    }
}