#!/bin/bash

#./dump_code.sh code.txt ../src/Core/ChatUI.php ../src/Core/ChatUiAjax.php



# Get current timestamp in YYYYMMDD_HHMMSS format
TIMESTAMP=$(date "+%Y%m%d_%H%M%S")

# Set output filename with timestamp
OUTFILE="code_$TIMESTAMP.txt"

# Call your main dump_code.sh script with the generated output filename and hardcoded input files
./dumpcode.sh "$OUTFILE" ../src/Core/ChatUI.php ../src/Core/ChatUiAjax.php 



echo "Dump created: $OUTFILE"



