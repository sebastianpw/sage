#!/bin/bash

# MD Showcase Prompt Validator
# Validates structure for showcase prompt MD files
# Usage: ./validate_prompts.sh <file.md> [--fix]

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

FILE="${1:-}"
FIX_MODE="${2:-}"

# Required sections inside prompt blocks
REQUIRED_SECTIONS=("VISUAL FOCUS:" "SCALE MARKERS:" "MATERIALS:" "CAMERA DISTANCE:" "LIGHTING:" "ATMOSPHERIC:" "MOOD:")

# Optional sections (should be checked but not required)
OPTIONAL_SECTIONS=("CONTINUITY NOTES:" "GENERATOR NOTE:")

# Validation counters
ERRORS=0
WARNINGS=0
FIXES=0

print_error() {
    echo -e "${RED}[ERROR]${NC} Line ${BLUE}${1}${NC}: ${2}"
    ((ERRORS++))
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} Line ${BLUE}${1}${NC}: ${2}"
    ((WARNINGS++))
}

print_fix() {
    echo -e "${GREEN}[FIXED]${NC} Line ${BLUE}${1}${NC}: ${2}"
    ((FIXES++))
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} ${1}"
}

validate_date_code() {
    local date_code="$1"
    if [[ ! "$date_code" =~ ^[0-9]{6}$ ]]; then
        return 1
    fi
    
    # Validate it's a plausible date (YYMMDD format)
    local yy="${date_code:0:2}"
    local mm="${date_code:2:2}"
    local dd="${date_code:4:2}"
    
    if (( mm < 1 || mm > 12 )); then
        return 1
    fi
    
    if (( dd < 1 || dd > 31 )); then
        return 1
    fi
    
    return 0
}

fix_date_code() {
    local line="$1"
    local content="$2"
    
    # Try to extract existing date or generate current date
    if [[ "$content" =~ --\ ([0-9]{6}) ]]; then
        # Already has date, ensure format
        local existing="${BASH_REMATCH[1]}"
        if validate_date_code "$existing"; then
            echo "$content"
            return 0
        fi
    fi
    
    # Generate current date in YYMMDD format
    local current_date=$(date +%y%m%d)
    local fixed="${content/-- /-- $current_date }"
    
    # Ensure we have exactly one date
    fixed=$(echo "$fixed" | sed -E 's/(-- )([0-9]{6} )?/\1'"$current_date"' /')
    
    echo "$fixed"
}

fix_generator_line() {
    local line="$1"
    local content="$2"
    
    if [[ ! "$content" =~ \\*\\*Generator\\*\\*: ]]; then
        if [[ "$content" =~ Generator: ]]; then
            # Fix missing bold
            fixed="${content/Generator: /**Generator**: }"
            echo "$fixed"
        else
            # Add default generator
            echo "**Generator**: \`GLOBAL Crater City Environmental Generator\`"
        fi
    elif [[ ! "$content" =~ \\\` ]]; then
        # Check if has any backtick at all
        if [[ "$content" =~ :([^\\\`]+)$ ]]; then
            local gen_name="${BASH_REMATCH[1]}"
            fixed="${content/:$gen_name/: \`$gen_name\`}"
            echo "$fixed"
        else
            echo "$content"
        fi
    else
        # Check if properly enclosed in backticks
        if [[ ! "$content" =~ \\\`.*\\\` ]]; then
            # Has backtick but not both sides
            if [[ "$content" =~ :(.*)\\\` ]]; then
                local gen_part="${BASH_REMATCH[1]}"
                fixed="${content/:$gen_part\`/: \`$gen_part\`}"
                echo "$fixed"
            elif [[ "$content" =~ :\\\`(.*) ]]; then
                local gen_part="${BASH_REMATCH[1]}"
                fixed="${content/:\`$gen_part/: \`$gen_part\`}"
                echo "$fixed"
            fi
        else
            echo "$content"
        fi
    fi
}

fix_prompt_header() {
    local line="$1"
    local content="$2"
    
    if [[ ! "$content" =~ ^###\ ([A-Z]+-[A-Z]+-[0-9]{2}-)?[0-9]{6}: ]]; then
        # Try to extract code and date
        if [[ "$content" =~ ^###\ ([^:]+): ]]; then
            local prefix="${BASH_REMATCH[1]}"
            
            # Check if has date code
            if [[ "$prefix" =~ [0-9]{6}$ ]]; then
                # Already has date at end
                echo "$content"
                return 0
            fi
            
            # Add current date
            local current_date=$(date +%y%m%d)
            local fixed="### $prefix-$current_date:${content#*:}"
            echo "$fixed"
        else
            # No colon found, this is malformed
            echo "$content"
        fi
    else
        echo "$content"
    fi
}

add_missing_section() {
    local section="$1"
    local content="$2"
    
    case "$section" in
        "VISUAL FOCUS:")
            echo "$content\nVISUAL FOCUS: [Description of visual focus points]"
            ;;
        "SCALE MARKERS:")
            echo "$content\nSCALE MARKERS: [Size, dimensions, quantity markers]"
            ;;
        "MATERIALS:")
            echo "$content\nMATERIALS: [Key materials and construction details]"
            ;;
        "CAMERA DISTANCE:")
            echo "$content\nCAMERA DISTANCE: [Shot distance and camera movement]"
            ;;
        "LIGHTING:")
            echo "$content\nLIGHTING: [Light sources, color temperature, quality]"
            ;;
        "ATMOSPHERIC:")
            echo "$content\nATMOSPHERIC: [Sound, smell, tactile elements, atmosphere]"
            ;;
        "MOOD:")
            echo "$content\nMOOD: [Emotional tone and narrative feeling]"
            ;;
        *)
            echo "$content"
            ;;
    esac
}

validate_file() {
    local file="$1"
    local fix_mode="$2"
    
    echo -e "${BLUE}=== Validating: $file ===${NC}\n"
    
    if [[ ! -f "$file" ]]; then
        echo -e "${RED}File not found: $file${NC}"
        exit 1
    fi
    
    # Read file into array
    mapfile -t lines < "$file"
    
    # State tracking
    local in_prompt_block=0
    local in_code_block=0
    local current_prompt_start=0
    local current_location=""
    local prompt_blocks=()
    local line_num=0
    local temp_file=""
    
    if [[ "$fix_mode" == "--fix" ]]; then
        temp_file=$(mktemp)
    fi
    
    for ((i=0; i<${#lines[@]}; i++)); do
        ((line_num++))
        local line="${lines[$i]}"
        local trimmed="${line#"${line%%[![:space:]]*}"}"
        
        # Write to temp file if in fix mode
        if [[ "$fix_mode" == "--fix" ]]; then
            echo "$line" >> "$temp_file"
        fi
        
        # Check level 1 header (first line)
        if (( i == 0 )); then
            if [[ ! "$line" =~ ^#\ .+\ —\ [0-9]{6} ]]; then
                print_error "$line_num" "Level 1 header missing or malformed. Expected: '# TITLE: SUBTITLE — YYMMDD'"
                if [[ "$fix_mode" == "--fix" ]]; then
                    # Remove last line from temp file and replace
                    truncate -s -$((${#line}+1)) "$temp_file"
                    local fixed=$(fix_date_code "$line_num" "$line")
                    echo "$fixed" >> "$temp_file"
                    print_fix "$line_num" "Fixed level 1 header date"
                fi
            fi
            
            # Validate date code
            if [[ "$line" =~ —\ ([0-9]{6}) ]]; then
                local date_code="${BASH_REMATCH[1]}"
                if ! validate_date_code "$date_code"; then
                    print_error "$line_num" "Invalid date code: $date_code (should be YYMMDD)"
                fi
            fi
            continue
        fi
        
        # Check level 2 header for document type
        if (( i == 1 )) && [[ ! "$line" =~ ^##\ .+ ]]; then
            print_error "$line_num" "Missing level 2 header (document type) on line 2"
        fi
        
        # Check horizontal rule separator
        if (( i == 2 )) && [[ ! "$line" =~ ^--- ]]; then
            print_error "$line_num" "Missing horizontal rule (---) after headers"
        fi
        
        # Check location headers (## X. )
        if [[ "$line" =~ ^##\ [0-9]+\.\  ]]; then
            current_location="$line"
            
            # Check next line for generator declaration
            if (( i+1 < ${#lines[@]} )); then
                local next_line="${lines[$i+1]}"
                if [[ ! "$next_line" =~ \*\*Generator\*\*:\ \` ]]; then
                    print_error "$((i+2))" "Missing generator declaration after location header"
                    if [[ "$fix_mode" == "--fix" ]]; then
                        # Insert generator line after current line in temp file
                        local temp_file2=$(mktemp)
                        head -n "$((i+1))" "$temp_file" > "$temp_file2"
                        echo "**Generator**: \`GLOBAL Crater City Environmental Generator\`" >> "$temp_file2"
                        tail -n "+$((i+2))" "$temp_file" >> "$temp_file2"
                        mv "$temp_file2" "$temp_file"
                        print_fix "$((i+2))" "Added missing generator declaration"
                    fi
                fi
            fi
        fi
        
        # Check prompt headers (### )
        if [[ "$line" =~ ^###\  ]]; then
            if [[ ! "$line" =~ ^###\ ([A-Z]+-[A-Z]+-[0-9]{2}-)?[0-9]{6}: ]]; then
                print_error "$line_num" "Prompt header malformed. Expected: '### CODE-YYMMDD: Title — Description'"
                if [[ "$fix_mode" == "--fix" ]]; then
                    # Remove last line from temp file and replace
                    local temp_file2=$(mktemp)
                    head -n "$((line_num-1))" "$temp_file" > "$temp_file2"
                    local fixed=$(fix_prompt_header "$line_num" "$line")
                    echo "$fixed" >> "$temp_file2"
                    tail -n "+$((line_num+1))" "$temp_file" >> "$temp_file2"
                    mv "$temp_file2" "$temp_file"
                    print_fix "$line_num" "Fixed prompt header format"
                fi
            fi
            
            in_prompt_block=1
            current_prompt_start="$line_num"
            prompt_blocks+=("$line_num:$line")
            continue
        fi
        
        # Check for code block start
        if [[ "$line" =~ ^\`\`\`$ ]] && (( in_prompt_block == 1 )); then
            if (( in_code_block == 0 )); then
                in_code_block=1
                local code_block_content=""
            else
                in_code_block=0
                in_prompt_block=0
                
                # Validate code block content
                validate_code_block "$code_block_content" "$current_prompt_start"
            fi
            continue
        fi
        
        # Collect code block content
        if (( in_code_block == 1 )); then
            code_block_content+="$line"$'\n'
        fi
    done
    
    # Final validation summary
    echo -e "\n${BLUE}=== Validation Summary ===${NC}"
    echo -e "Total lines processed: ${BLUE}$line_num${NC}"
    echo -e "Prompt blocks found: ${BLUE}${#prompt_blocks[@]}${NC}"
    
    if (( ERRORS == 0 )); then
        echo -e "${GREEN}✓ No critical errors found${NC}"
    else
        echo -e "${RED}✗ $ERRORS critical error(s) found${NC}"
    fi
    
    if (( WARNINGS > 0 )); then
        echo -e "${YELLOW}⚠ $WARNINGS warning(s) found${NC}"
    fi
    
    if [[ "$fix_mode" == "--fix" ]] && (( FIXES > 0 )); then
        echo -e "${GREEN}🛠 $FIXES issue(s) fixed${NC}"
        
        # Backup original file
        local backup="${file}.backup.$(date +%s)"
        cp "$file" "$backup"
        echo -e "Backup saved to: ${BLUE}$backup${NC}"
        
        # Replace file with fixed version
        mv "$temp_file" "$file"
        echo -e "Fixed file saved to: ${BLUE}$file${NC}"
        
        # Run validation again to confirm fixes
        echo -e "\n${BLUE}=== Re-validating fixed file ===${NC}"
        validate_file "$file" ""
    elif [[ "$fix_mode" == "--fix" ]]; then
        rm -f "$temp_file"
    fi
    
    return $ERRORS
}

validate_code_block() {
    local content="$1"
    local start_line="$2"
    
    local missing_sections=()
    local line_offset="$start_line"
    
    # Check for each required section
    for section in "${REQUIRED_SECTIONS[@]}"; do
        if ! grep -q "^$section" <<< "$content"; then
            missing_sections+=("$section")
            print_error "$line_offset" "Missing required section: $section"
        fi
    done
    
    # Check for code block length (should have content)
    local line_count=$(wc -l <<< "$content")
    if (( line_count < 5 )); then
        print_warning "$line_offset" "Code block seems too short ($line_count lines)"
    fi
    
    # Check for proper section formatting (uppercase, colon)
    while IFS= read -r block_line; do
        if [[ ! "$line" =~ ^#[[:space:]].*[[:space:]]—[[:space:]][0-9]{6} ]]; then
            # This is a section header line
            local section_name="${block_line%%:*}:"
            local valid_section=0
            
            for req_section in "${REQUIRED_SECTIONS[@]}" "${OPTIONAL_SECTIONS[@]}"; do
                if [[ "$section_name" == "$req_section" ]]; then
                    valid_section=1
                    break
                fi
            done
            
            if (( valid_section == 0 )); then
                print_warning "$line_offset" "Unknown section: $section_name"
            fi
        fi
        ((line_offset++))
    done <<< "$content"
}

show_usage() {
    echo -e "${BLUE}MD Showcase Prompt Validator${NC}"
    echo -e "Usage: $0 <file.md> [--fix]"
    echo -e "\nOptions:"
    echo -e "  <file.md>    Markdown file to validate"
    echo -e "  --fix        Attempt to fix common issues automatically"
    echo -e "\nValidation checks:"
    echo -e "  ✓ Level 1 header with date code (YYMMDD)"
    echo -e "  ✓ Level 2 document type header"
    echo -e "  ✓ Horizontal rule separator (---)"
    echo -e "  ✓ Location headers with generator declaration"
    echo -e "  ✓ Prompt headers with proper code-date format"
    echo -e "  ✓ Code blocks with required sections"
    echo -e "  ✓ All required sections present in prompts"
}

# Main execution
if [[ "$FILE" == "--help" ]] || [[ "$FILE" == "-h" ]] || [[ -z "$FILE" ]]; then
    show_usage
    exit 0
fi

# Check if file exists
if [[ ! -f "$FILE" ]]; then
    echo -e "${RED}Error: File '$FILE' not found${NC}"
    show_usage
    exit 1
fi

# Validate file extension
if [[ ! "$FILE" =~ \.md$ ]]; then
    echo -e "${YELLOW}Warning: File does not have .md extension${NC}"
fi

# Run validation
if validate_file "$FILE" "$FIX_MODE"; then
    if (( ERRORS == 0 )); then
        echo -e "\n${GREEN}✅ Validation passed! File is ready for import.${NC}"
        exit 0
    else
        echo -e "\n${RED}❌ Validation failed with $ERRORS error(s)${NC}"
        exit 1
    fi
else
    exit 1
fi
