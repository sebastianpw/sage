# pyapi/services/wordnet_service.py
"""
WordNet service for pyapi.

Uses get_db_connection(name='wordnet') so it always connects to the DB
defined by DATABASE_WORDNET_URL in .env.local (falls back to project DB only if not configured).
Includes a small debug endpoint to show the active database name.
"""
from fastapi import APIRouter, HTTPException, Query
from pydantic import BaseModel
from typing import List, Any, Dict
import logging
from .db_connector import get_db_connection
import mysql.connector

router = APIRouter(tags=["wordnet"])
logger = logging.getLogger(__name__)

# ----- Pydantic models (simple) -----
class LemmaSense(BaseModel):
    synsetid: int
    wordid: int | None = None
    casedwordid: int | None = None
    lemma: str | None = None
    senseid: int | None = None
    sensenum: int | None = None
    lexid: int | None = None
    tagcount: int | None = None
    sensekey: str | None = None
    cased: str | None = None
    pos: str | None = None
    lexdomainid: int | None = None
    definition: str | None = None
    sampleset: str | None = None

class SynsetDetail(BaseModel):
    synsetid: int
    pos: str
    lexdomainid: int
    definition: str
    synonyms: List[str] = []

# ---- End models ----

def _get_wordnet_conn():
    """
    Obtain a connection explicitly to the WordNet DB.
    Uses the db_connector get_db_connection(name='wordnet').
    """
    conn = get_db_connection(name='wordnet')
    if conn is None:
        logger.error("get_db_connection(name='wordnet') returned None")
        raise HTTPException(status_code=500, detail="WordNet DB connection failed.")
    return conn

@router.get("/wordnet/debug")
def wordnet_debug() -> Dict[str, Any]:
    """
    Simple debug endpoint: returns which env key is configured and the active database name.
    Useful to verify the service connects to the intended DB.
    """
    conn = _get_wordnet_conn()
    try:
        cur = conn.cursor()
        try:
            cur.execute("SELECT DATABASE()")
            row = cur.fetchone()
            dbname = row[0] if row else None
        except Exception as e:
            logger.exception("Failed to run SELECT DATABASE(): %s", e)
            dbname = None
        finally:
            try:
                cur.close()
            except Exception:
                pass
        return {"status": "ok", "active_database": dbname}
    except Exception as e:
        logger.exception("Debug endpoint error: %s", e)
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/wordnet/lemma/{lemma}", response_model=List[LemmaSense])
def get_by_lemma(lemma: str):
    conn = _get_wordnet_conn()
    try:
        cur = conn.cursor()
        cur.execute(
            "SELECT synsetid, wordid, casedwordid, lemma, senseid, sensenum, lexid, tagcount, sensekey, cased, pos, lexdomainid, definition, sampleset FROM dict WHERE lemma = %s",
            (lemma,),
        )
        rows = cur.fetchall()
        cols = [desc[0] for desc in cur.description] if cur.description else []
        results = []
        for row in rows:
            # map into dict keyed by column names
            dd = {cols[i]: row[i] for i in range(len(cols))}
            results.append(dd)
        cur.close()
        return results
    except Exception as e:
        logger.exception("Error fetching lemma %s: %s", lemma, e)
        raise HTTPException(status_code=500, detail=f"Query failed: {e}")


@router.get("/wordnet/synset/{synsetid}", response_model=SynsetDetail)
def get_synset(synsetid: int):
    conn = _get_wordnet_conn()
    try:
        cur = conn.cursor()
        cur.execute("SELECT synsetid, pos, lexdomainid, definition FROM synsets WHERE synsetid = %s", (synsetid,))
        syn = cur.fetchone()
        if not syn:
            cur.close()
            raise HTTPException(status_code=404, detail=f"Synset {synsetid} not found")
        syndict = {"synsetid": syn[0], "pos": syn[1], "lexdomainid": syn[2], "definition": syn[3], "synonyms": []}
        # fetch words in synset
        cur.execute(
            "SELECT w.lemma FROM words w JOIN senses s ON w.wordid = s.wordid WHERE s.synsetid = %s ORDER BY s.sensenum",
            (synsetid,),
        )
        syndict["synonyms"] = [r[0] for r in cur.fetchall()]
        cur.close()
        return syndict
    except HTTPException:
        raise
    except Exception as e:
        logger.exception("Error fetching synset %s: %s", synsetid, e)
        raise HTTPException(status_code=500, detail=f"Query failed: {e}")


@router.get("/wordnet/search")
def search_lemmas(q: str = Query(..., min_length=1), limit: int = 50):
    conn = _get_wordnet_conn()
    try:
        cur = conn.cursor()
        pattern = f"%{q}%"
        cur.execute("SELECT DISTINCT lemma FROM words WHERE lemma LIKE %s ORDER BY lemma LIMIT %s", (pattern, limit))
        rows = [r[0] for r in cur.fetchall()]
        cur.close()
        return {"query": q, "count": len(rows), "results": rows}
    except Exception as e:
        logger.exception("Search error: %s", e)
        raise HTTPException(status_code=500, detail=f"Search failed: {e}")


@router.get("/wordnet/hypernyms/{synsetid}")
def get_hypernyms(synsetid: int):
    conn = _get_wordnet_conn()
    try:
        cur = conn.cursor()
        cur.execute("SELECT linkid FROM linktypes WHERE link = 'hypernym' LIMIT 1")
        row = cur.fetchone()
        if not row:
            cur.close()
            raise HTTPException(status_code=404, detail="No linktype 'hypernym' found")
        hyper_id = row[0]
        cur.execute(
            "SELECT s2.synsetid, s2.definition FROM semlinks l JOIN synsets s2 ON l.synset2id = s2.synsetid WHERE l.synset1id = %s AND l.linkid = %s",
            (synsetid, hyper_id),
        )
        results = [{"synsetid": r[0], "definition": r[1]} for r in cur.fetchall()]
        cur.close()
        return {"synsetid": synsetid, "hypernyms": results}
    except Exception as e:
        logger.exception("Hypernym fetch error: %s", e)
        raise HTTPException(status_code=500, detail=f"Query failed: {e}")


@router.get("/wordnet/morph/{morph}")
def get_morph(morph: str):
    conn = _get_wordnet_conn()
    try:
        cur = conn.cursor()
        try:
            # try morphology view if present
            cur.execute("SELECT morphid, wordid, lemma, pos, morph FROM morphology WHERE morph = %s", (morph,))
            rows = cur.fetchall()
            if rows:
                cols = [d[0] for d in cur.description]
                results = [{cols[i]: r[i] for i in range(len(cols))} for r in rows]
                cur.close()
                return {"query": morph, "results": results}
        except mysql.connector.Error:
            # fallback to legacy morphmaps+morphs+words join
            pass

        cur.execute(
            "SELECT w.lemma FROM morphmaps mm JOIN morphs m ON mm.morphid = m.morphid JOIN words w ON mm.wordid = w.wordid WHERE m.morph = %s",
            (morph,),
        )
        rows = [r[0] for r in cur.fetchall()]
        cur.close()
        return {"query": morph, "results": rows}
    except Exception as e:
        logger.exception("Morphology error: %s", e)
        raise HTTPException(status_code=500, detail=f"Query failed: {e}")
