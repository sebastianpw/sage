<?php

/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

var_dump($argv);
 */

try {

require_once 'prompt_parser.php';
require_once 'bootstrap.php'; // provides $pdo

$parser = new PromptParser($pdo);

// Get template from command line argument
$template = $argv[1] ?? '';

if (!$template) {
    echo "Usage: php testprompt.php '<template_prompt>'\n";
    exit(1);
}

$finalPrompt = $parser->parseTemplate($template);

echo urlencode($finalPrompt);

} catch (Exception $e) {
	var_dump($e);
}
