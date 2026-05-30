#!/bin/bash

# ==============================================================================
# SAGE RESET TOOL
# ------------------------------------------------------------------------------
# 1. Wipes vectors from Chroma for selected collections.
# 2. Wipes corresponding rows from MariaDB 'vector_state' table.
# 3. Supports Shortcuts:
#      LORE  — All lore/doc collections (excludes images, sketches, KG)
#      IMAGE — Image and sketch collections only
#      KG    — Knowledge Graph node collections only
#              (sage_kg_nodes_meta + sage_kg_nodes_content)
#      Y     — Interactive per-collection selection
#
# NOTE: Collections are NEVER deleted — only their vectors are wiped via
# delete_where. Deleting a Chroma collection changes its internal UUID,
# which breaks all subsequent API references until the process restarts.
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MYSQL_ARGS=$("$SCRIPT_DIR"/db_name.sh main-conn)
PYAPI_URL=$("$SCRIPT_DIR"/pyapi_echo.sh)

# ------------------------------------------------------------------------------
# Load Collections from Database
# ------------------------------------------------------------------------------
mapfile -t ALL_COLLECTIONS < <(mysql $MYSQL_ARGS -N -e "SELECT name FROM chroma_collections ORDER BY name ASC;")

if [ ${#ALL_COLLECTIONS[@]} -eq 0 ]; then
    echo "Error: No collections found in database table 'chroma_collections'."
    exit 1
fi

# ==============================================================================
# SECURITY WARNING
# ==============================================================================
echo ""
echo -e "\033[0;31m██████████████████████████████████████████████████████████████████████\033[0m"
echo -e "\033[0;31m█   HUGE SECURITY WARNING: DESTRUCTIVE ACTION                        █\033[0m"
echo -e "\033[0;31m██████████████████████████████████████████████████████████████████████\033[0m"
echo ""
echo "You are about to DELETE vectors and tracking state."
echo "This will force a re-ingestion for the affected collections."
echo ""
echo "Shortcuts:"
echo "  Type 'LORE'  to scrub ALL lore/doc collections (excludes images, sketches, KG)."
echo "  Type 'IMAGE' to scrub ALL image and sketch collections."
echo "  Type 'KG'    to scrub Knowledge Graph node collections only."
echo "  Type 'Y'     to interactively select collections one by one."
echo ""
echo -n "Type 'Y', 'LORE', 'IMAGE', or 'KG' to continue: "
read -r CONFIRM

CONFIRM_UPPER=$(echo "$CONFIRM" | tr '[:lower:]' '[:upper:]')

TARGET_COLLECTIONS=()

# Known KG collection names (must match chroma_collections table)
KG_COLLECTIONS=("sage_kg_nodes_meta" "sage_kg_nodes_content")

# Known image/sketch collection names
IMAGE_COLLECTIONS=("sage_nu_images" "sage_sketches_nu")

# ------------------------------------------------------------------------------
# Selection Logic
# ------------------------------------------------------------------------------

if [ "$CONFIRM_UPPER" == "LORE" ]; then
    echo ""
    echo ">>> SHORTCUT: LORE scrubbing selected."
    for COLL in "${ALL_COLLECTIONS[@]}"; do
        IS_EXCLUDED=false
        for EX in "${IMAGE_COLLECTIONS[@]}" "${KG_COLLECTIONS[@]}"; do
            if [[ "$COLL" == "$EX" ]]; then
                IS_EXCLUDED=true
                break
            fi
        done
        if [ "$IS_EXCLUDED" = false ]; then
            TARGET_COLLECTIONS+=("$COLL")
        fi
    done

elif [ "$CONFIRM_UPPER" == "IMAGE" ]; then
    echo ""
    echo ">>> SHORTCUT: IMAGE scrubbing selected."
    for COLL in "${ALL_COLLECTIONS[@]}"; do
        for IM in "${IMAGE_COLLECTIONS[@]}"; do
            if [[ "$COLL" == "$IM" ]]; then
                TARGET_COLLECTIONS+=("$COLL")
                break
            fi
        done
    done

elif [ "$CONFIRM_UPPER" == "KG" ]; then
    echo ""
    echo ">>> SHORTCUT: KG scrubbing selected."
    echo "    Targets: sage_kg_nodes_meta + sage_kg_nodes_content"
    for COLL in "${ALL_COLLECTIONS[@]}"; do
        for KG in "${KG_COLLECTIONS[@]}"; do
            if [[ "$COLL" == "$KG" ]]; then
                TARGET_COLLECTIONS+=("$COLL")
                break
            fi
        done
    done

elif [ "$CONFIRM_UPPER" == "Y" ]; then
    echo ""
    echo ">>> INTERACTIVE MODE selected."
    echo "----------------------------------------"
    for COLL in "${ALL_COLLECTIONS[@]}"; do
        echo -n "   Scrub collection '$COLL'? [y/N]: "
        read -r yn
        if [[ "$yn" =~ ^[Yy]$ ]]; then
            TARGET_COLLECTIONS+=("$COLL")
        fi
    done

else
    echo "Aborted."
    exit 1
fi

if [ ${#TARGET_COLLECTIONS[@]} -eq 0 ]; then
    echo "No collections selected. Exiting."
    exit 0
fi

# ------------------------------------------------------------------------------
# Execution Loop
# ------------------------------------------------------------------------------

echo ""
echo "========================================"
echo " STARTING SCRUB PROCESS"
echo "========================================"

for COLL in "${TARGET_COLLECTIONS[@]}"; do
    echo "------------------------------------------------------------"
    echo " Target: $COLL"

    # ------------------------------------------------------------------
    # Determine metadata_field / metadata_value for delete_where.
    #
    # Every vector in every collection carries a stable metadata marker:
    #   sage_kg_nodes_meta    -> subtype = "meta"
    #   sage_kg_nodes_content -> subtype = "content"
    #   sage_nu_images        -> type    = "frame"
    #   sage_sketches_nu      -> type    = "sketch"
    #   (all lore collections)-> type    = "documentation"
    #
    # We always use delete_where — never delete_collection — so the
    # Chroma collection's internal UUID is preserved and all API
    # references remain valid after the scrub.
    # ------------------------------------------------------------------
    META_FIELD="type"
    META_VALUE="documentation"

    if [[ "$COLL" == "sage_nu_images" ]]; then
        META_VALUE="frame"
    elif [[ "$COLL" == "sage_sketches_nu" ]]; then
        META_VALUE="sketch"
    elif [[ "$COLL" == "sage_kg_nodes_meta" ]]; then
        META_FIELD="subtype"
        META_VALUE="meta"
    elif [[ "$COLL" == "sage_kg_nodes_content" ]]; then
        META_FIELD="subtype"
        META_VALUE="content"
    fi

    # 1. CLEAN CHROMA
    echo -n "   [Chroma] Deleting vectors ($META_FIELD='$META_VALUE')... "
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$PYAPI_URL/chroma/delete_where" \
        -F "collection=$COLL" \
        -F "metadata_field=$META_FIELD" \
        -F "metadata_value=$META_VALUE")

    if [ "$HTTP_CODE" == "200" ]; then
        echo "OK"
    else
        echo "Failed (HTTP $HTTP_CODE)"
    fi

    # 2. CLEAN MARIADB vector_state
    echo -n "   [DB]     Cleaning 'vector_state' table... "
    mariadb $MYSQL_ARGS -e "DELETE FROM vector_state WHERE collection = '$COLL';"

    if [ $? -eq 0 ]; then
        echo "OK"
    else
        echo "Failed"
    fi

done

echo ""
echo "========================================"
echo " RESET COMPLETE."
echo "========================================"

