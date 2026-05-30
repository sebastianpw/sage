# bash/genhances_fromdb.sh

#!/bin/bash

# ==============================================================================
# SAGE Frame Enhancement (Manual Invocation Wrapper)
# Usage: ./genhances_fromdb.sh prompt_type [limit] [offset] [no_styles] [add_to_prompt]
#
# NOTE: This script has been modernized to act as a seamless wrapper around the
# unified queue system. It proxies commands directly into genhances_queue.sh 
# passing the "manual" scope so it inherits UI models and runs immediately.
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

PROMPT_TYPE="$1"
LIMIT="$2"
OFFSET="$3"
NO_STYLES="$4"
ADD_TO_PROMPT="$5"

# Pass arguments to queue ingestion: 
# [batch_limit] [worker_scope] [entity_type_filter] [limit] [offset] [no_styles] [add_to_prompt]
"$SCRIPT_DIR/genhances_queue.sh" "" "manual" "$PROMPT_TYPE" "$LIMIT" "$OFFSET" "$NO_STYLES" "$ADD_TO_PROMPT"