<?php
/**
 * ai_query_runner.php - CLI interface for AIProvider
 * 
 * Unified CLI script that works with both:
 * - bash/ai_query.sh (backward compatible)
 * - pyapi/services/aiprovider_service.py (new Python wrapper)
 * 
 * Usage:
 *   php ai_query_runner.php --model openai --user "Your prompt"
 *   php ai_query_runner.php --list-models
 *   echo "context" | php ai_query_runner.php --model gemini --user "Analyze"
 *   echo '[{"role":"user","content":"Hi"}]' | php ai_query_runner.php --messages
 */

// Ensure we're running from CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("This script can only be run from the command line.\n");
}

// Find the project root (script is in PROJECT_ROOT/bash/)
$projectRoot = dirname(__DIR__);

// Bootstrap the application to get $spw instance and load environment
require $projectRoot . '/public/bootstrap.php';

// --- Argument Parsing ---
// Support both short and long options for maximum compatibility
$options = getopt(
    "m:s:u:hl",
    [
        "model:",
        "system:",
        "user:",
        "help",
        "list-models",
        "messages",          // New: for multi-turn conversations
        "temperature:",      // New: for temperature control
        "max-tokens:"        // New: for token limit
    ]
);

/**
 * Display usage information
 */
function display_usage() {
    $usage = <<<'EOD'
Usage: php ai_query_runner.php [options] [user_prompt_argument]

Sends a query to an AI model, combining a command-line prompt with piped-in content.

Options:
  -h, --help                Display this help message
  -l, --list-models         List available models (JSON) and exit
  -m, --model <model>       AI model to use (default: 'openai')
                           Examples: 'groq/compound', 'gemini-2.5-flash'
  -s, --system <prompt>     System prompt (sets AI role/persona)
  -u, --user <prompt>       User prompt (can also be first non-option arg)
  
  --messages                Read conversation messages from STDIN as JSON array
                           Format: [{"role":"user","content":"..."},...]
  --temperature <float>     Temperature 0.0-2.0 (controls randomness)
  --max-tokens <int>        Maximum tokens to generate

Input:
  Additional context can be piped via STDIN. It will be appended to the
  user prompt, separated by newlines. In --messages mode, STDIN must
  contain a JSON array of message objects.

Examples:
  # Simple prompt
  php ai_query_runner.php -m openai -u "Hello, AI!"
  
  # With system prompt
  php ai_query_runner.php -s "You are a code expert" -u "Explain recursion"
  
  # With piped context
  cat file.txt | php ai_query_runner.php -u "Summarize this file"
  
  # Multi-turn conversation
  echo '[{"role":"user","content":"Hi"},{"role":"assistant","content":"Hello!"}]' | \
    php ai_query_runner.php --messages
  
  # List available models
  php ai_query_runner.php --list-models

EOD;
    fwrite(STDERR, $usage);
    exit(1);
}

// Show help if requested
if (isset($options['h']) || isset($options['help'])) {
    display_usage();
}

// List models if requested (backward compatible)
if (isset($options['l']) || isset($options['list-models'])) {
    $catalog = \App\Core\AIProvider::getModelCatalog();
    echo json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

// Get AIProvider instance from bootstrap
global $spw;
$aiProvider = $spw->getAIProvider();

// --- Handle --messages mode (new feature for Python service) ---
if (isset($options['messages'])) {
    // Read JSON messages from STDIN
    $stdinContent = file_get_contents('php://stdin');
    
    if (empty($stdinContent)) {
        fwrite(STDERR, "Error: --messages mode requires JSON array on STDIN\n");
        exit(1);
    }
    
    $messages = json_decode($stdinContent, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        fwrite(STDERR, "Error: Invalid JSON on STDIN: " . json_last_error_msg() . "\n");
        exit(1);
    }
    
    if (!is_array($messages)) {
        fwrite(STDERR, "Error: STDIN must contain a JSON array of messages\n");
        exit(1);
    }
    
    // Get model and options
    $model = $options['m'] ?? $options['model'] ?? \App\Core\AIProvider::getDefaultModel();
    
    $apiOptions = [];
    if (isset($options['temperature'])) {
        $apiOptions['temperature'] = (float)$options['temperature'];
    }
    if (isset($options['max-tokens'])) {
        $apiOptions['max_tokens'] = (int)$options['max-tokens'];
    }
    
    try {
        fwrite(STDERR, "[INFO] Sending conversation to model: $model...\n");
        $response = $aiProvider->sendMessage($model, $messages, $apiOptions);
        echo $response; // Clean output to STDOUT
        fwrite(STDERR, "\n");
        exit(0);
    } catch (\Exception $e) {
        fwrite(STDERR, "[ERROR] " . $e->getMessage() . "\n");
        exit(1);
    }
}

// --- Standard prompt mode (backward compatible) ---

// Parse model
$model = $options['m'] ?? $options['model'] ?? \App\Core\AIProvider::getDefaultModel();

// Parse system prompt
$systemPrompt = $options['s'] ?? $options['system'] ?? null;

// Parse user prompt from options
$userPromptCli = $options['u'] ?? $options['user'] ?? '';

// Check for non-option argument as user prompt (backward compatible)
// This allows: `php script.php "my prompt"` without -u flag
if (empty($userPromptCli)) {
    foreach ($argv as $i => $arg) {
        if ($i === 0) continue; // skip script name
        
        if (strpos($arg, '-') !== 0) {
            // Check if this arg is a value for some option
            $isOptionValue = false;
            foreach ($options as $opt_val) {
                if (is_array($opt_val)) {
                    if (in_array($arg, $opt_val, true)) {
                        $isOptionValue = true;
                        break;
                    }
                } else if ($arg === $opt_val) {
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

// --- Read from STDIN (backward compatible) ---
$stdinContent = '';

// Detect if STDIN has content (pipe mode)
if (function_exists('stream_isatty')) {
    // Modern PHP: use stream_isatty
    if (stream_isatty(STDIN) === false) {
        $stdinContent = file_get_contents('php://stdin');
    }
} else {
    // Fallback: check file stats
    $stat = fstat(STDIN);
    if (isset($stat['size']) && $stat['size'] > 0) {
        $stdinContent = file_get_contents('php://stdin');
    }
}

// --- Validate input ---
if (empty($userPromptCli) && empty($stdinContent)) {
    fwrite(STDERR, "Error: No user prompt provided via argument or STDIN.\n\n");
    display_usage();
}

// --- Combine prompts (backward compatible behavior) ---
// CLI prompt + STDIN content, separated by newlines
$fullUserPrompt = trim($userPromptCli . "\n\n" . $stdinContent);

// --- Parse additional options (new features) ---
$apiOptions = [];

if (isset($options['temperature'])) {
    $apiOptions['temperature'] = (float)$options['temperature'];
}

if (isset($options['max-tokens'])) {
    $apiOptions['max_tokens'] = (int)$options['max-tokens'];
}

// --- Execute AI Query ---
try {
    fwrite(STDERR, "[INFO] Sending query to model: $model...\n");
    
    $response = $aiProvider->sendPrompt(
        $model,
        $fullUserPrompt,
        $systemPrompt,
        $apiOptions
    );
    
    // Write clean response to STDOUT (can be redirected)
    echo $response;
    
} catch (\Exception $e) {
    fwrite(STDERR, "[ERROR] An error occurred: " . $e->getMessage() . "\n");
    exit(1);
}

// Add final newline to STDERR to separate from shell prompt
fwrite(STDERR, "\n");
exit(0);
