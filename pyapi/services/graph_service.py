# pyapi/services/graph_service.py
"""
Graph Service
Offloads ForceAtlas2 layout calculations from the client to the server,
saving significant memory and CPU on mobile browsers (Termux).
"""
import math
import random
import logging
from fastapi import APIRouter
from pydantic import BaseModel
from typing import List, Dict, Any

logger = logging.getLogger(__name__)

try:
    from fa2 import ForceAtlas2
    import networkx as nx
    HAS_FA2 = True
except ImportError:
    HAS_FA2 = False

router = APIRouter(tags=["graph"])

class GraphPayload(BaseModel):
    nodes: List[Dict[str, Any]]
    edges: List[Dict[str, Any]]
    iterations: int = 150

@router.post("/layout")
def compute_layout(payload: GraphPayload):
    nodes = payload.nodes
    edges = payload.edges
    iterations = payload.iterations if payload.iterations > 0 else 150
    
    if not nodes:
        return {"positions": {}}

    final_positions = {}
    
    # Try using the compiled fa2 library if available
    if HAS_FA2:
        try:
            logger.info("Using fa2 library for ForceAtlas2 layout")
            G = nx.Graph()
            for n in nodes:
                G.add_node(str(n.get("id")))
            for e in edges:
                G.add_edge(str(e.get("source")), str(e.get("target")))
                
            forceatlas2 = ForceAtlas2(
                outboundAttractionDistribution=True,
                linLogMode=False,
                adjustSizes=False,
                edgeWeightInfluence=1.0,
                jitterTolerance=1.0,
                barnesHutOptimize=len(nodes) > 50,
                barnesHutTheta=1.2,
                scalingRatio=2.0,
                strongGravityMode=False,
                gravity=1.0,
                verbose=False
            )
            positions = forceatlas2.forceatlas2_networkx_layout(G, pos=None, iterations=iterations)
            
            if positions:
                min_x = min([p[0] for p in positions.values()])
                max_x = max([p[0] for p in positions.values()])
                min_y = min([p[1] for p in positions.values()])
                max_y = max([p[1] for p in positions.values()])
                
                range_x = max_x - min_x if max_x - min_x > 0 else 1
                range_y = max_y - min_y if max_y - min_y > 0 else 1
                
                for vid, p in positions.items():
                    final_positions[vid] = {
                        "x": ((p[0] - min_x) / range_x) * 100,
                        "y": ((p[1] - min_y) / range_y) * 100
                    }
            return {"positions": final_positions}
        except Exception as e:
            logger.warning(f"fa2 layout failed, falling back to pure Python FA2-lite: {e}")

    # Fallback to zero-dependency Pure Python FA2-lite algorithm
    # Guaranteed to run flawlessly on Termux without pip install compiling issues
    logger.info("Using pure Python ForceAtlas2-lite layout")
    positions = {}
    degrees = {}
    for n in nodes:
        nid = str(n.get("id"))
        positions[nid] = {
            "x": random.uniform(0, 100),
            "y": random.uniform(0, 100),
            "dx": 0.0,
            "dy": 0.0
        }
        degrees[nid] = 1 # Avoid division by zero
        
    edge_list = []
    for e in edges:
        src = str(e.get("source"))
        tgt = str(e.get("target"))
        if src in positions and tgt in positions:
            edge_list.append((src, tgt))
            degrees[src] += 1
            degrees[tgt] += 1
            
    kr = 50.0   # Repulsion strength
    kg = 1.0    # Gravity strength
    speed = 0.1 # Movement dampener
    t = 10.0    # Cooling temperature preventing explosions
    dt = t / (iterations + 1)
    
    node_ids = list(positions.keys())
    num_nodes = len(node_ids)
    
    for _ in range(iterations):
        # Reset forces
        for v in positions.values():
            v["dx"] = 0.0
            v["dy"] = 0.0
            
        # Degree-based repulsion
        for i in range(num_nodes):
            v = node_ids[i]
            for j in range(i + 1, num_nodes):
                u = node_ids[j]
                dx = positions[v]["x"] - positions[u]["x"]
                dy = positions[v]["y"] - positions[u]["y"]
                dist_sq = dx*dx + dy*dy
                if dist_sq > 0.01:
                    dist = math.sqrt(dist_sq)
                    repulse = kr * (degrees[v] * degrees[u]) / dist
                    fx = (dx / dist) * repulse
                    fy = (dy / dist) * repulse
                    positions[v]["dx"] += fx
                    positions[v]["dy"] += fy
                    positions[u]["dx"] -= fx
                    positions[u]["dy"] -= fy
                    
        # Edge Attraction
        for src, tgt in edge_list:
            dx = positions[tgt]["x"] - positions[src]["x"]
            dy = positions[tgt]["y"] - positions[src]["y"]
            dist_sq = dx*dx + dy*dy
            if dist_sq > 0.01:
                dist = math.sqrt(dist_sq)
                fx = (dx / dist) * dist
                fy = (dy / dist) * dist
                positions[src]["dx"] += fx
                positions[src]["dy"] += fy
                positions[tgt]["dx"] -= fx
                positions[tgt]["dy"] -= fy
                
        # Central Gravity
        for v, p in positions.items():
            dx = 50 - p["x"]
            dy = 50 - p["y"]
            dist_sq = dx*dx + dy*dy
            if dist_sq > 0.01:
                dist = math.sqrt(dist_sq)
                gravity = kg * degrees[v]
                p["dx"] += (dx / dist) * gravity
                p["dy"] += (dy / dist) * gravity
                
        # Move & Cool down
        for p in positions.values():
            disp_x = p["dx"] * speed
            disp_y = p["dy"] * speed
            disp = math.sqrt(disp_x*disp_x + disp_y*disp_y)
            if disp > 0:
                limited_disp = min(disp, t)
                p["x"] += (disp_x / disp) * limited_disp
                p["y"] += (disp_y / disp) * limited_disp
                
        t -= dt
        if t <= 0: t = 0.1

    # Normalize to fixed viewport [0, 100]
    min_x = min([p["x"] for p in positions.values()])
    max_x = max([p["x"] for p in positions.values()])
    min_y = min([p["y"] for p in positions.values()])
    max_y = max([p["y"] for p in positions.values()])
    
    range_x = max_x - min_x if max_x - min_x > 0 else 1
    range_y = max_y - min_y if max_y - min_y > 0 else 1
    
    for vid, p in positions.items():
        final_positions[vid] = {
            "x": ((p["x"] - min_x) / range_x) * 100,
            "y": ((p["y"] - min_y) / range_y) * 100
        }
        
    return {"positions": final_positions}