# pyapi/services/muvitriccs/easing.py
"""
MuviTriccs Easing Library
All easing functions used by transition renderers.
"""

import math


def ease_linear(t):           return t
def ease_in_cubic(t):         return t ** 3
def ease_out_cubic(t):        return 1 - (1 - t) ** 3
def ease_in_out_cubic(t):     return 4*t**3 if t < 0.5 else 1 - (-2*t+2)**3/2
def ease_in_out_quart(t):     return 8*t**4 if t < 0.5 else 1 - (-2*t+2)**4/2
def ease_in_out_sine(t):      return -(math.cos(math.pi * t) - 1) / 2
def ease_overshoot(t, s=1.70158):
    return 1 + (s+1)*(t-1)**3 + s*(t-1)**2
def ease_elastic(t):
    if t == 0 or t == 1: return t
    return -(2**(10*t-10)) * math.sin((t*10-10.75)*(2*math.pi)/3)


EASING_MAP = {
    "linear":            ease_linear,
    "ease_in_cubic":     ease_in_cubic,
    "ease_out_cubic":    ease_out_cubic,
    "ease_in_out_cubic": ease_in_out_cubic,
    "ease_in_out_quart": ease_in_out_quart,
    "ease_in_out_sine":  ease_in_out_sine,
    "ease_overshoot":    ease_overshoot,
    "ease_elastic":      ease_elastic,
}


def get_easing(name: str):
    return EASING_MAP.get(name, ease_in_out_cubic)
