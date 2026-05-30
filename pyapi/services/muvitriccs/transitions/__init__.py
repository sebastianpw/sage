# pyapi/services/muvitriccs/transitions/__init__.py
"""
MuviTriccs Transition Dispatcher
Re-exports render_transition_frame and routes each name to its family renderer.
"""

from .transitions_core     import render_core
from .transitions_motion   import render_motion
from .transitions_optical  import render_optical
from .transitions_stylized import render_stylized
from .transitions_flow     import render_flow
from .transitions_depth    import render_depth
from .transitions_creative import render_creative
from .transitions_epic     import render_epic
from .transitions_movie    import render_movie

import random
from typing import Optional

import numpy as np

from ..easing import get_easing
from ..primitives import blend

# Transitions needing pre-computed analysis (importable from here for convenience)
FLOW_TRANSITIONS  = {"optical_flow_warp", "motion_blur_cut"}
DEPTH_TRANSITIONS = {"depth_parallax"}

_CORE_NAMES = {
    "cross_dissolve", "fade_to_black", "fade_to_white", "luma_wipe",
}
_MOTION_NAMES = {
    "slide_left", "slide_right", "slide_up", "slide_down",
    "push_left", "push_right", "zoom_in", "zoom_out",
    "spin_cw", "spin_ccw", "whip_pan_left", "whip_pan_right",
}
_OPTICAL_NAMES = {
    "motion_blur_cut", "radial_blur_cut", "defocus_cut",
}
_STYLIZED_NAMES = {
    "flash", "glitch", "rgb_split", "wave_warp", "lens_distortion",
    "film_burn", "light_leak", "scanline_tear", "vhs_dropout",
}
_FLOW_NAMES  = {"optical_flow_warp"}
_DEPTH_NAMES = {"depth_parallax"}
_CREATIVE_NAMES = {
    "pixel_sort", "ink_wash", "shatter", "smear_frame",
    "cube_rotate_left", "cube_rotate_right",
    "page_curl", "kaleidoscope", "ripple_water", "dream_blur",
}
_EPIC_NAMES = {
    "speed_ramp", "shockwave", "strobe_cut", "motion_trail", "glare_hit",
}
_MOVIE_NAMES = {
    "iris_wipe", "venetian_blind", "cross_zoom",
    "tilt_shift_cut", "cinematic_bars", "whip_zoom",
}


def render_transition_frame(
    a: np.ndarray,
    b: np.ndarray,
    t: float,
    name: str,
    spec: dict,
    w: int, h: int,
    rng: random.Random,
    flow_ab:  Optional[np.ndarray] = None,
    depth_a:  Optional[np.ndarray] = None,
    depth_b:  Optional[np.ndarray] = None,
) -> np.ndarray:
    """
    Central dispatcher. Routes each transition name to its family renderer.
    Falls back to gamma-correct cross_dissolve for unknown names.
    """
    kwargs = dict(
        a=a, b=b, t=t, name=name, spec=spec, w=w, h=h, rng=rng,
        flow_ab=flow_ab, depth_a=depth_a, depth_b=depth_b,
    )

    if name in _CORE_NAMES:
        return render_core(**kwargs)
    elif name in _MOTION_NAMES:
        return render_motion(**kwargs)
    elif name in _OPTICAL_NAMES:
        return render_optical(**kwargs)
    elif name in _STYLIZED_NAMES:
        return render_stylized(**kwargs)
    elif name in _FLOW_NAMES:
        return render_flow(**kwargs)
    elif name in _DEPTH_NAMES:
        return render_depth(**kwargs)
    elif name in _CREATIVE_NAMES:
        return render_creative(**kwargs)
    elif name in _EPIC_NAMES:
        return render_epic(**kwargs)
    elif name in _MOVIE_NAMES:
        return render_movie(**kwargs)
    else:
        easing_fn = get_easing(spec.get("easing", "ease_in_out_cubic"))
        return blend(a, b, easing_fn(t))
