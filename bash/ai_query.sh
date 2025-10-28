#!/bin/bash
# ai_query.sh - A wrapper to query the AIProvider via CLI.
#
# Forwards a query to the AIProvider class, combining a command-line prompt
# with content piped from STDIN.
#
# Usage:
#   cat context.txt | ./ai_query.sh [options] "Your main instruction" > output.txt
#
# Examples:
#   # Generate code from a file's content
#   cat src/SomeClass.php | ./ai_query.sh "Please add PHPUnit tests for this class" > tests/SomeClassTest.php
#
#   # Use a different model
#   cat report.txt | ./ai_query.sh -m "gemini" "Summarize this report"
#
#   # Provide a system prompt
#   ./ai_query.sh -s "You are a bash expert" "Create a script to backup a directory" > backup.sh
#
# Options:
#   -l, --list-models        List available models (JSON) from AIProvider and exit.
#   -m, --model <model>      Specify the AI model (e.g., 'groq/compound', 'gemini').
#                           Default: 'groq/compound'
#   -s, --system <prompt>    Provide a system prompt.
#   -u, --user <prompt>      Provide the user prompt (can also be the first non-option argument).
#   -h, --help               Show this help message and the underlying PHP script's help.
#
# --- Setup ---
# Find the script's own directory
SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &> /dev/null && pwd)
# Assume project root is one level up from the 'bash' directory
PROJECT_ROOT="$SCRIPT_DIR/.."
PHP_RUNNER="$SCRIPT_DIR/ai_query_runner.php"

# --- Pre-flight Checks ---
if [ ! -f "$PHP_RUNNER" ]; then
    echo "Error: The required PHP runner script was not found." >&2
    echo "Expected location: $PHP_RUNNER" >&2
    echo "Please ensure you have created the helper script cli/ai_query_runner.php." >&2
    exit 1
fi

if ! command -v php &> /dev/null; then
    echo "Error: 'php' command not found. Please install PHP and ensure it's in your PATH." >&2
    exit 1
fi

# --- Argument Parsing & Help ---
# Check for help flag specifically in bash to provide more context
for arg in "$@"; do
  if [[ "$arg" == "-l" ]] || [[ "$arg" == "--list-models" ]]; then
    php "$PHP_RUNNER" --list-models
    exit 0
  fi
  if [[ "$arg" == "-h" ]] || [[ "$arg" == "--help" ]]; then
    echo "Usage for ai_query.sh:"
    echo "  cat context.txt | $(basename "$0") [options] \"Your instruction\" > output.txt"
    echo ""
    echo "This script is a user-friendly wrapper for a PHP CLI tool."
    echo "All options are passed directly to the underlying PHP script."
    echo "-------------------------------------------------------------"
    php "$PHP_RUNNER" --help
    exit 0
  fi
done

# --- Execution ---
# Execute the PHP script, passing all arguments from this shell script.
# The PHP script is designed to handle STDIN, so piping works automatically.
php "$PHP_RUNNER" "$@"


