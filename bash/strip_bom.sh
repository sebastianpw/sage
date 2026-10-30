#!/data/data/com.termux/files/usr/bin/bash
# Run from your project root on the Android/Termux box.
# Strips a UTF-8 BOM (EF BB BF) from the start of each listed file, in place.
for f in \
  public/sqlish_api.php \
  public/wrish_api.php \
  public/wtf_api.php \
  public/splash_api.php \
  src/Service/SqlishEngine.php \
  src/Service/SplashEngine.php \
  src/Wtf/WorkflowExecutor.php
do
  if [ -f "$f" ]; then
    if head -c 3 "$f" | grep -q $'\xef\xbb\xbf'; then
      tail -c +4 "$f" > "$f.tmp" && mv "$f.tmp" "$f"
      echo "Stripped BOM: $f"
    else
      echo "No BOM found (ok): $f"
    fi
  else
    echo "Not found, skipped: $f"
  fi
done
