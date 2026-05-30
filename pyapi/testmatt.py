# test_matting_cpu.py
import time
from PIL import Image
from rembg import remove, new_session

img = Image.open("test.png").convert("RGBA")   # put a small test.png next to this script
sess = new_session("u2net")                    # will fall back to CPU session
t0 = time.time()
out = remove(img, session=sess, alpha_matting=True,
             alpha_matting_foreground_threshold=240,
             alpha_matting_background_threshold=10,
             alpha_matting_erode_size=8,
             alpha_matting_base_size=512)
dt = time.time() - t0
print("Elapsed (s):", dt)
out.save("test_out.png")
