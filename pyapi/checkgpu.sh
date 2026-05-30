# save as /data/data/com.termux/files/home/check_gpu.sh and run: bash check_gpu.sh
echo "===== Android device info ====="
getprop ro.product.model || true
getprop ro.product.device || true
getprop ro.hardware || true
getprop ro.build.version.release || true
echo
echo "===== Kernel ====="
uname -a || true
echo
echo "===== CPU info (first lines) ====="
head -n 12 /proc/cpuinfo || true
echo
echo "===== KGSL (Adreno) present? ====="
if [ -d /sys/class/kgsl ]; then echo "KGSL directory exists: /sys/class/kgsl (likely Adreno)"; ls -la /sys/class/kgsl || true; else echo "No /sys/class/kgsl"; fi
echo
echo "===== DRM / DRI present? ====="
if [ -d /sys/class/drm ] || [ -d /dev/dri ]; then echo "DRM/DRI present"; ls -la /sys/class/drm /dev/dri 2>/dev/null || true; else echo "No /sys/class/drm nor /dev/dri"; fi
echo
echo "===== Android features (from package manager) ====="
# 'pm' might not be available inside proot; run it if accessible
pm list features 2>/dev/null | egrep -i 'vulkan|gpu|nnapi' || echo "(pm not available or no vulkan/nnapi features listed)"
echo
echo "===== Python checks (if python installed in this env) ====="
python - <<'PY' 2>/dev/null || { echo "python not available in this shell"; exit 0; }
import sys
print("python", sys.version.split()[0])
try:
    import torch
    print("torch:", torch.__version__, "cuda available:", torch.cuda.is_available())
except Exception as e:
    print("torch not installed or import failed:", e)
try:
    import onnxruntime as ort
    print("onnxruntime providers:", ort.get_available_providers())
except Exception as e:
    print("onnxruntime not installed or import failed:", e)
PY
echo "===== done ====="
