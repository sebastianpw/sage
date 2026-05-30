#!/usr/bin/env bash
# gen_images_array.sh
#
# Generate JS-style image arrays from a simple newline list of image URLs.
#
# Usage:
#   ./gen_images_array.sh input_urls.txt output.js
#   ./gen_images_array.sh input.txt output.js --alt=frame --width=800 --height=800
#   ./gen_images_array.sh in.txt out.js --split=100       # split into out-1.js out-2.js ...
#   ./gen_images_array.sh in.txt out.json --json --alt=prefix
#
# Options:
#   --alt=order|prefix|frame    Choose alt-labeling:
#                               order  => Post Screenshot N (default)
#                               prefix => 3-digit prefix before the hyphen (001)
#                               frame  => the digits after 'frame' (0000513)
#   --width=N                   width numeric (default 1024)
#   --height=N                  height numeric (default 1024)
#   --split=N                   split into files with N items each (optional)
#   --json                      output strict JSON (array of objects with double quotes)
#
set -euo pipefail

progname=$(basename "$0")

usage() {
  cat <<EOF
Usage: $progname <input_urls.txt> <output_path> [options]

Positional:
  <input_urls.txt>    plain text file with one image URL per line
  <output_path>       destination file (will be overwritten) -- if --split used, files will be named base-1.ext, base-2.ext...

Options:
  --alt=order|prefix|frame   (default: order)
  --width=N                  (default: 1024)
  --height=N                 (default: 1024)
  --split=N                  (optional, split into multiple files)
  --json                     output strict JSON (double-quoted keys/strings)
  -h, --help                 show this help
EOF
  exit 1
}

# defaults
alt_mode="order"
width=1024
height=1024
split=0
output_json=0

# parse args
if [ "$#" -lt 2 ]; then usage; fi

infile="$1"; shift
outfile="$1"; shift

while [ "$#" -gt 0 ]; do
  case "$1" in
    --alt=*)
      alt_mode="${1#--alt=}"
      shift
      ;;
    --width=*)
      width="${1#--width=}"
      shift
      ;;
    --height=*)
      height="${1#--height=}"
      shift
      ;;
    --split=*)
      split="${1#--split=}"
      shift
      ;;
    --json)
      output_json=1
      shift
      ;;
    -h|--help)
      usage
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage
      ;;
  esac
done

if [ ! -f "$infile" ]; then
  echo "Input file not found: $infile" >&2
  exit 2
fi

# Read urls, trim whitespace, skip blank lines
# ADDED: Remove UTF-8 BOM (EF BB BF) from beginning of file if present
mapfile -t urls < <(sed -e '1s/^\xEF\xBB\xBF//' -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' "$infile" | awk 'NF')

count=${#urls[@]}
if [ "$count" -eq 0 ]; then
  echo "No URLs found in $infile" >&2
  exit 3
fi

# helper: get alt label for a url
get_alt() {
  local url="$1"
  local idx="$2"   # 1-based index for fallback
  case "$alt_mode" in
    order)
      printf "Post Screenshot %d" "$idx"
      ;;
    prefix)
      # basename then capture leading 3-digit prefix before hyphen
      local base
      base="$(basename "$url")"
      if [[ "$base" =~ ^([0-9]{3})- ]]; then
        printf "%s" "${BASH_REMATCH[1]}"
      else
        printf "Post Screenshot %d" "$idx"
      fi
      ;;
    frame)
      # find 'frame' followed by digits
      if [[ "$url" =~ frame([0-9]+) ]]; then
        printf "%s" "${BATCH_REMATCH[1]}"
      else
        printf "Post Screenshot %d" "$idx"
      fi
      ;;
    *)
      printf "Post Screenshot %d" "$idx"
      ;;
  esac
}

# prepare splitting
if [ "$split" -gt 0 ]; then
  # determine base name and extension
  outdir=$(dirname "$outfile")
  basefile=$(basename "$outfile")
  ext=""
  name_only="$basefile"
  if [[ "$basefile" == *.* ]]; then
    ext=".${basefile##*.}"
    name_only="${basefile%.*}"
  fi
  mkdir -p "$outdir"
fi

# function to write one file with a given slice
write_slice() {
  local outpath="$1"
  local start_idx="$2"
  local end_idx="$3"  # inclusive
  local total="$4"

  # Choose style: JS-style (single-quoted keys allowed) or JSON (double quotes)
  if [ "$output_json" -eq 1 ]; then
    # JSON: keys and strings double-quoted
    : > "$outpath"
    printf "[\n" >> "$outpath"
    local first=1
    for ((i = start_idx; i <= end_idx; i++)); do
      url="${urls[i-1]}"
      # escape double quotes and backslashes
      esc="${url//\\/\\\\}"
      esc="${esc//\"/\\\"}"
      alt=$(get_alt "$url" "$i")
      escalt="${alt//\"/\\\"}"
      if [ $first -eq 1 ]; then
        printf "  { \"src\": \"%s\", \"width\": %d, \"height\": %d, \"alt\": \"%s\" }" "$esc" "$width" "$height" "$escalt" >> "$outpath"
        first=0
      else
        printf ",\n  { \"src\": \"%s\", \"width\": %d, \"height\": %d, \"alt\": \"%s\" }" "$esc" "$width" "$height" "$escalt" >> "$outpath"
      fi
    done
    printf "\n]\n" >> "$outpath"
  else
    # JS-style: use single quotes for strings (escape single quotes)
    : > "$outpath"
    printf "[\n" >> "$outpath"
    local first=1
    for ((i = start_idx; i <= end_idx; i++)); do
      url="${urls[i-1]}"
      # escape single quotes and backslashes
      esc="${url//\\/\\\\}"
      esc="${esc//\'/\\\'}"
      alt=$(get_alt "$url" "$i")
      escalt="${alt//\'/\\\'}"
      if [ $first -eq 1 ]; then
        printf "  { src: '%s', width: %d, height: %d, alt: '%s' }" "$esc" "$width" "$height" "$escalt" >> "$outpath"
        first=0
      else
        printf ",\n  { src: '%s', width: %d, height: %d, alt: '%s' }" "$esc" "$width" "$height" "$escalt" >> "$outpath"
      fi
    done
    printf "\n]\n" >> "$outpath"
  fi

  echo "Wrote $((end_idx - start_idx + 1)) entries to $outpath"
}

# No split — write single file
if [ "$split" -le 0 ]; then
  write_slice "$outfile" 1 "$count" "$count"
  exit 0
fi

# With splitting
chunk="$split"
slice=1
fileidx=1
while [ "$slice" -le "$count" ]; do
  end=$(( slice + chunk - 1 ))
  if [ "$end" -gt "$count" ]; then end="$count"; fi
  outpath="${outdir}/${name_only}-${fileidx}${ext}"
  write_slice "$outpath" "$slice" "$end" "$count"
  slice=$(( end + 1 ))
  fileidx=$(( fileidx + 1 ))
done

exit 0
