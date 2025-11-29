# pyapi/services/bloom_service.py
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field
from typing import List
import random
import base64
import logging
from bloom_filter2 import BloomFilter
from .db_connector import get_db_connection

router = APIRouter(tags=["bloom"])
logger = logging.getLogger(__name__)

# --- CHANGE 1: Update Pydantic models to include the sampled words ---
class BloomMeta(BaseModel):
    dictionary_ids_used: List[int]
    num_words_sampled: int
    requested_words: int
    error_rate: float
    seed_used: int | None
    sampled_lemmas: List[str] # The cleartext words

class BloomResponse(BaseModel):
    bloom: dict
    meta: BloomMeta

class BloomRequest(BaseModel):
    dictionary_ids: List[int] = Field(..., description="List of dictionary IDs to source words from.")
    num_words: int = Field(200, gt=0, le=5000, description="Number of random words to sample for the filter.")
    error_rate: float = Field(0.01, gt=0, lt=1, description="Desired false positive rate for the Bloom filter.")
    seed: int | None = Field(None, description="Optional random seed for reproducibility.")


@router.post("/generate", response_model=BloomResponse)
def generate_bloom_filter(request: BloomRequest):
    # Note: We no longer need random.seed() here, as the DB will handle it.
    
    conn = get_db_connection()
    if conn is None:
        raise HTTPException(status_code=500, detail="Database connection failed.")
    
    lemmas = []
    try:
        with conn.cursor() as cursor:
            id_placeholders = ','.join(['%s'] * len(request.dictionary_ids))
            
            # --- CHANGE 2: Build the query and params dynamically based on seed ---
            params = list(request.dictionary_ids)
            if request.seed is not None:
                # Use a seeded RAND() for deterministic results
                order_by_clause = "ORDER BY RAND(%s)"
                params.append(request.seed)
            else:
                # Use a non-seeded RAND() for true randomness
                order_by_clause = "ORDER BY RAND()"

            query = f"""
                SELECT DISTINCT l.lemma
                FROM dict_lemmas l
                JOIN dict_lemma_2_dictionary l2d ON l.id = l2d.lemma_id
                WHERE l2d.dictionary_id IN ({id_placeholders})
                {order_by_clause}
                LIMIT %s
            """
            params.append(request.num_words)
            
            cursor.execute(query, tuple(params))
            
            lemmas = [row[0] for row in cursor.fetchall()]

    except Exception as e:
        logger.exception("Failed to fetch lemmas from database.")
        raise HTTPException(status_code=500, detail=f"Database query failed: {e}")
    finally:
        if conn.is_connected():
            conn.close()
            logger.info("Database connection closed.")
            
    if not lemmas:
        raise HTTPException(status_code=404, detail="No words found for the given dictionary IDs.")

    bloom_filter = BloomFilter(max_elements=len(lemmas), error_rate=request.error_rate)
    for lemma in lemmas:
        bloom_filter.add(lemma)

    bit_array = bloom_filter.backend.array_
    bitset_bytes = bit_array.tobytes()
    bitset_base64 = base64.b64encode(bitset_bytes).decode('ascii')

    return {
        "bloom": {
            "bits": bloom_filter.num_bits_m,
            "hash_functions": bloom_filter.num_probes_k,
            "encoding": "base64",
            "data": bitset_base64,
            "meaning": "A probabilistic representation of a word set."
        },
        "meta": {
            "dictionary_ids_used": request.dictionary_ids,
            "num_words_sampled": len(lemmas),
            "requested_words": request.num_words,
            "error_rate": request.error_rate,
            "seed_used": request.seed,
            "sampled_lemmas": lemmas # --- CHANGE 3: Include the cleartext words ---
        }
    }

