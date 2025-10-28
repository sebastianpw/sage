<?php
// cli/ai_query_runner.php
// Find the project root. This assumes the script is in PROJECT_ROOT/cli/
$projectRoot = dirname(__DIR__);

// Bootstrap the application to get the $spw instance and load the environment.
// This is crucial for accessing AIProvider and its dependencies.
require $projectRoot . '/public/bootstrap.php';

// --- Argument Parsing ---
$options = getopt("m:s:u:hl", ["model:", "system:", "user:", "help", "list-models"]);

function display_usage() {
    $usage = <<<EOD
Usage: php ai_query_runner.php [options] [user_prompt_argument]
Sends a query to an AI model, combining a command-line prompt with piped-in content.

Options:
  -l, --list-models        List available models (JSON) from AIProvider and exit.
  -m, --model <model>      The AI model to use (e.g., 'groq/compound', 'gemini').
                          Default: 'groq/compound' from AIProvider logic.
  -s, --system <prompt>    The system prompt to set the AI's role or persona.
  -u, --user <prompt>      The user prompt. Can also be provided as the first
                          non-option argument.
  -h, --help               Display this help message.

Input:
  Additional context can be piped to this script via STDIN. It will be appended
  to the user prompt, separated by newlines.

Example:
  cat file.txt | php cli/ai_query_runner.php -s "You are a code reviewer" "Review the code from the context"
  php cli/ai_query_runner.php -m "gemini" "Translate this to French: Hello World" > translation.txt

EOD;
    fwrite(STDERR, $usage);
    exit(1);
}

if (isset($options['h']) || isset($options['help'])) {
    display_usage();
}

// Set defaults
$model = $options['m'] ?? $options['model'] ?? 'groq/compound';
$systemPrompt = $options['s'] ?? $options['system'] ?? null;
$userPromptCli = $options['u'] ?? $options['user'] ?? '';

// Check for a non-option argument for the user prompt.
// This allows for a more natural command: `script.php "my prompt"`
if (empty($userPromptCli)) {
    foreach ($argv as $i => $arg) {
        if ($i === 0) continue; // skip script name
        if (strpos($arg, '-') !== 0) {
            // Find the first argument that is not an option or its value
            $isOptionValue = false;
            foreach ($options as $opt_val) {
                if ($arg === $opt_val) {
                    $isOptionValue = true;
                    break;
                }
            }
            if (!$isOptionValue) {
                $userPromptCli = $arg;
                break;
            }
        }
    }
}


// If user asked for model list, print JSON catalog and exit (CLI friendly)
if (isset($options['l']) || isset($options['list-models'])) {
    $catalog = \App\Core\AIProvider::getModelCatalog();
    // print pretty JSON for CLI consumption
    echo json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}


// --- Read from STDIN ---
$stdinContent = '';

// Check if the script is being run in a pipe (not a terminal)
if (function_exists('stream_isatty')) {
    if (stream_isatty(STDIN) === false) {
        $stdinContent = file_get_contents('php://stdin');
    }
} else {
    // Fallback if stream_isatty is not available: attempt to read non-empty STDIN
    $stat = fstat(STDIN);
    if ($stat['size'] > 0) {
        $stdinContent = file_get_contents('php://stdin');
    }
}

// --- Combine Prompts ---
if (empty($userPromptCli) && empty($stdinContent)) {
    fwrite(STDERR, "Error: No user prompt provided either as an argument or via STDIN.\n\n");
    display_usage();
}

// Combine the CLI prompt and the piped content, ensuring separation.
$fullUserPrompt = trim($userPromptCli . "\n\n" . $stdinContent);

// --- Execute AI Query ---
try {
    global $spw; // from bootstrap.php
    $aiProvider = $spw->getAIProvider();
    fwrite(STDERR, "[INFO] Sending query to model: $model...\n");
    $response = $aiProvider->sendPrompt(
        $model,
        $fullUserPrompt,
        $systemPrompt
    );
    // Write the clean response to STDOUT, so it can be redirected
    echo $response;
} catch (\Exception $e) {
    fwrite(STDERR, "[ERROR] An error occurred: " . $e->getMessage() . "\n");
    exit(1);
}

// Add a final newline to STDERR to separate from shell prompt
fwrite(STDERR, "\n");
exit(0);
