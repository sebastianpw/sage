"""
SAGE Fuzz Forge - High-Speed Sparse Clustering Service
Uses TF-IDF Character N-Grams and chunked Sparse Cosine Similarity to achieve 
O(N) time complexity for Levenshtein/Typo grouping on massive datasets.

Optimized for Android/Termux Environments (Strict RAM limits).
"""

import gc
import logging
import time
import uuid
from concurrent.futures import ThreadPoolExecutor
from threading import Lock
from typing import Any, Callable, Dict, List, Optional

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel

try:
    from sklearn.feature_extraction.text import TfidfVectorizer
    import numpy as np
    from scipy.sparse import csr_matrix
    ML_AVAILABLE = True
except ImportError:
    ML_AVAILABLE = False

logger = logging.getLogger(__name__)
router = APIRouter(tags=["fuzz"])

# Keep the worker count intentionally low for mobile/tablet RAM safety.
FUTURE_EXECUTOR = ThreadPoolExecutor(max_workers=1)

JOB_LOCK = Lock()
JOB_REGISTRY: Dict[str, Dict[str, Any]] = {}


class FuzzItem(BaseModel):
    id: str
    text: str


class ClusterRequest(BaseModel):
    items: List[FuzzItem]
    threshold: float = 0.82  # 0.82-0.85 catches most typos/plurals safely


class UnionFind:
    """Disjoint-set data structure to connect clustered components instantly."""
    def __init__(self, n):
        self.parent = list(range(n))

    def find(self, i):
        if self.parent[i] == i:
            return i
        self.parent[i] = self.find(self.parent[i])
        return self.parent[i]

    def union(self, i, j):
        root_i = self.find(i)
        root_j = self.find(j)
        if root_i != root_j:
            self.parent[root_i] = root_j


def _job_now() -> float:
    return time.time()


def _create_job(total_items: int, threshold: float) -> str:
    job_id = uuid.uuid4().hex
    with JOB_LOCK:
        JOB_REGISTRY[job_id] = {
            "job_id": job_id,
            "status": "queued",
            "created_at": _job_now(),
            "updated_at": _job_now(),
            "total_items": total_items,
            "threshold": threshold,
            "progress": {
                "stage": "queued",
                "processed": 0,
                "total": total_items,
                "message": "Queued for processing",
            },
            "result": None,
            "error": None,
        }
    return job_id


def _update_job(job_id: str, **updates: Any) -> None:
    with JOB_LOCK:
        job = JOB_REGISTRY.get(job_id)
        if not job:
            return
        job.update(updates)
        job["updated_at"] = _job_now()


def _get_job_copy(job_id: str) -> Optional[Dict[str, Any]]:
    with JOB_LOCK:
        job = JOB_REGISTRY.get(job_id)
        if not job:
            return None
        return {
            "job_id": job["job_id"],
            "status": job["status"],
            "created_at": job["created_at"],
            "updated_at": job["updated_at"],
            "total_items": job["total_items"],
            "threshold": job["threshold"],
            "progress": dict(job.get("progress") or {}),
            "result": job.get("result"),
            "error": job.get("error"),
        }


def _run_clustering(
    req: ClusterRequest,
    progress_cb: Optional[Callable[[str, int, int, str], None]] = None,
):
    if not ML_AVAILABLE:
        raise HTTPException(status_code=500, detail="scikit-learn or scipy not installed on PyAPI server.")
    
    if not req.items:
        return {"status": "success", "clusters": []}
        
    texts = [item.text for item in req.items]
    ids = [item.id for item in req.items]
    n = len(texts)
    
    if n == 1:
        return {"status": "success", "clusters": [[ids[0]]]}
        
    try:
        logger.info(f"Fuzz Service: Vectorizing {n} items (Low-RAM Mode)...")
        if progress_cb:
            progress_cb("vectorizing", 0, n, f"Vectorizing {n} items (Low-RAM Mode)...")
        
        # 1. Vectorize using float32 and 3-char n-grams to keep memory usage tiny.
        # ngram_range=(3, 3) prevents the massive density bloat caused by 2-char combos.
        vectorizer = TfidfVectorizer(
            analyzer='char_wb', 
            ngram_range=(3, 3), 
            min_df=1, 
            dtype=np.float32
        )
        X = vectorizer.fit_transform(texts)
        
        uf = UnionFind(n)
        
        # Micro-batching for Android. 
        # 250 x 100,000 = 25M elements max per batch (Safely < 150MB RAM spike)
        batch_size = 250 
        total_batches = (n // batch_size) + 1
        
        logger.info(f"Fuzz Service: Matrix dot products across {total_batches} batches...")
        if progress_cb:
            progress_cb("similarity", 0, n, f"Matrix dot products across {total_batches} batches...")
        
        # 2. Chunked Sparse Dot Product
        for start in range(0, n, batch_size):
            end = min(start + batch_size, n)
            X_batch = X[start:end]
            
            # Sparse dot product -> exactly Cosine Similarity since TF-IDF is L2 normalized
            sim_matrix = X_batch.dot(X.T)
            
            # Pull coordinates above threshold
            rows, cols = sim_matrix.nonzero()
            data = sim_matrix.data
            
            for r_batch, c, val in zip(rows, cols, data):
                r_global = r_batch + start
                # Only check upper triangle to avoid duplicate work
                if r_global < c and val >= req.threshold:
                    uf.union(r_global, c)
            
            # AGGRESSIVE RAM CLEANUP for Termux limits
            del sim_matrix
            del X_batch
            del rows, cols, data
            gc.collect()
            
            if progress_cb:
                progress_cb(
                    "clustering",
                    end,
                    n,
                    f"Processed {end}/{n} items clustered..."
                )
            
            if (start // batch_size) % 40 == 0:
                logger.info(f"Fuzz Service: Progress {end}/{n} items clustered...")
                    
        # 3. Assemble the final clusters
        logger.info("Fuzz Service: Assembling final cluster lists...")
        if progress_cb:
            progress_cb("assembling", n, n, "Assembling final cluster lists...")
            
        cluster_map = {}
        for i in range(n):
            root = uf.find(i)
            if root not in cluster_map:
                cluster_map[root] = []
            cluster_map[root].append(ids[i])
            
        results = list(cluster_map.values())
        logger.info(f"Fuzz Service: Successfully grouped {n} items into {len(results)} clusters.")
        
        # Final cleanup
        del X
        del cluster_map
        del uf
        gc.collect()
        
        if progress_cb:
            progress_cb("done", n, n, f"Successfully grouped {n} items into {len(results)} clusters.")
        
        return {
            "status": "success", 
            "clusters": results
        }
        
    except Exception as e:
        logger.exception("Fuzz Service clustering failed.")
        raise HTTPException(status_code=500, detail=str(e))


def _run_job(job_id: str, payload: Dict[str, Any]) -> None:
    try:
        _update_job(
            job_id,
            status="running",
            progress={
                "stage": "starting",
                "processed": 0,
                "total": len(payload.get("items") or []),
                "message": "Job started",
            },
        )

        req = ClusterRequest(**payload)

        def progress_cb(stage: str, processed: int, total: int, message: str) -> None:
            _update_job(
                job_id,
                progress={
                    "stage": stage,
                    "processed": processed,
                    "total": total,
                    "message": message,
                },
            )

        result = _run_clustering(req, progress_cb=progress_cb)

        _update_job(
            job_id,
            status="success",
            result=result,
            error=None,
            progress={
                "stage": "done",
                "processed": len(req.items),
                "total": len(req.items),
                "message": "Job completed successfully",
            },
        )
    except Exception as e:
        logger.exception("Async fuzz job failed.")
        _update_job(
            job_id,
            status="error",
            result=None,
            error=str(e),
            progress={
                "stage": "error",
                "processed": 0,
                "total": len(payload.get("items") or []),
                "message": "Job failed",
            },
        )


@router.post("/cluster")
def cluster_strings(req: ClusterRequest):
    result = _run_clustering(req)
    return result


@router.post("/cluster/async")
def cluster_strings_async(req: ClusterRequest):
    if not ML_AVAILABLE:
        raise HTTPException(status_code=500, detail="scikit-learn or scipy not installed on PyAPI server.")

    job_id = _create_job(total_items=len(req.items), threshold=req.threshold)

    payload = {
        "items": [item.dict() for item in req.items],
        "threshold": req.threshold,
    }

    FUTURE_EXECUTOR.submit(_run_job, job_id, payload)

    return {
        "status": "queued",
        "job_id": job_id,
        "status_url": f"/fuzz/cluster/status/{job_id}",
    }


@router.get("/cluster/status/{job_id}")
def cluster_job_status(job_id: str):
    job = _get_job_copy(job_id)
    if not job:
        raise HTTPException(status_code=404, detail="Unknown job id.")

    response = {
        "job_id": job["job_id"],
        "status": job["status"],
        "created_at": job["created_at"],
        "updated_at": job["updated_at"],
        "total_items": job["total_items"],
        "threshold": job["threshold"],
        "progress": job.get("progress") or {},
    }

    if job["status"] == "success":
        result = job.get("result") or {}
        response.update(result)
    elif job["status"] == "error":
        response["error"] = job.get("error")

    return response
