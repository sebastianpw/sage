#!/usr/bin/env python3
# fix_unicode_escapes.py
# Run from the same directory as the PHP file:
#   python3 fix_unicode_escapes.py exposanimaticism.php
#
# Replaces all \uXXXX sequences with their actual UTF-8 characters.
# Writes the fixed content back to the same file (backs up original as .bak).

import re
import sys
import shutil

def replace_unicode_escapes(text):
    def replacer(match):
        codepoint = int(match.group(1), 16)
        return chr(codepoint)
    return re.sub(r'\\u([0-9a-fA-F]{4})', replacer, text)

if len(sys.argv) < 2:
    print("Usage: python3 fix_unicode_escapes.py <file.php>")
    sys.exit(1)

filepath = sys.argv[1]

with open(filepath, 'r', encoding='utf-8') as f:
    original = f.read()

fixed = replace_unicode_escapes(original)

changed = original.count('\\u')
if changed == 0:
    print("No \\uXXXX sequences found. File unchanged.")
    sys.exit(0)

shutil.copy2(filepath, filepath + '.bak')
print(f"Backed up original to {filepath}.bak")

with open(filepath, 'w', encoding='utf-8') as f:
    f.write(fixed)

print(f"Done. Replaced all \\uXXXX sequences in {filepath}")
