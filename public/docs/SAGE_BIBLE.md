# SAGE — World-Bible: Best-Practice Master Template
**(“The Bible to build the Bible” — industry standard, pragmatic, SAGE-friendly)**

---

> Purpose: capture the immutable constraints, tonal gravity, and production-ready archetypes of your IP so every episode, scene, and generated asset aligns to a single source of truth.

---

# 1 — Core Principles (Keep these visible)
- **Precision in rules, freedom in detail.** Define what *must* hold true across the IP; allow creative freedom inside those rules.  
- **Single source of truth.** One canonical “World vX.Y” document/entity in SAGE. Everything references it.  
- **Constrain to free creativity.** Use constraints to reduce choice paralysis for AI generation.  
- **Versioned & auditable.** Changes must have changelog, author, date, and reason.  
- **Composable building blocks.** Worlds → Factions → Archetypes → Locations → Artifacts → Characters → Episodes → Scenes → Shots.

---

# 2 — World v0.1 (Template + Example)
## Template (fill these fields)
- **Title:**  
- **Core Premise (1–3 sentences):**  
- **Theme(s) (3 max):**  
- **Primary Tone / Mood:**  
- **Anchor Images / Visual Tags:** (one-line tags: “decay, chrome, fog, warm amber light”)  
- **Top 5 World Rules (hard constraints):**
  1.  
  2.  
  3.  
  4.  
  5.  
- **Primary Factions (3–5):** (name + one-line purpose/goal)  
- **Archetype Palette (5 archetypes):** (name + one-line motivation)  
- **Key Locations (5):** (name + narrative purpose + mood)  
- **Signature Artifacts / Mechanics (3–5):** (what they do + thematic cost)  
- **What never happens (absolute negatives):**  
- **Version / Author / Date / Changelog (short):**

## Example (minimal)
- **Title:** Nightfall Verge  
- **Core Premise:** In a dying city powered by dream-engines, memory is currency; thieves, priests, and machines fight to keep the past private.  
- **Themes:** Memory vs identity; Debt and freedom; Who gets to remember?  
- **Tone:** Neo-noir, wistful, intimate scale  
- **Top 5 Rules:**
  1. Memory extraction consumes a single personal memory permanently.
  2. No faster-than-light travel; long journeys are months/years.
  3. AI can’t fabricate new memories—only rearrange or suppress.
  4. Blood magic exists but breaks language in the speaker.
  5. Death is permanent (no resurrection tropes).
- **Factions:** Memory Guild (archives), Alley Courts (informal justice), Dream-Foundry (engineers).  
- **Archetypes:** The Archivist (keeper), The Debt Runner (survivor), The Former Priest (doubter), The Mirror (robotic child), The Gatekeeper (obstacle).  
- **Key Location:** The Vault (hoards memories) — cold, metallic, silent.  
- **Artifacts:** Mnemonic Key — unlocks one sealed memory at cost.  
- **Negatives:** No deus ex machina resurrections.

---

# 3 — World Structure Schema (SAGE mapping)
> Keep this as the canonical entity mapping you’ll implement in SAGE.

- **worlds**
  - id, title, core_premise, themes (array), tone, anchor_tags, rules (json), never_happens (json), version, author, changelog, created_at
- **factions**
  - id, world_id, name, short_purpose, alignment_tag, assets_reference
- **archetypes**
  - id, world_id, name, motivation, typical_conflict, example_traits
- **locations**
  - id, world_id, name, narrative_purpose, mood_tags, example_frames (frame_ids)
- **artifacts**
  - id, world_id, name, function, thematic_cost, restrictions
- **world_tags**
  - taxonomy for visual & semantic guidance (used by generators)

Implement as normalized tables + a human-editable JSON blob for rules and examples for rapid iteration.

---

# 4 — Naming, Versioning, and Conventions (must follow)
- **World:** `World_v{MAJOR}.{MINOR}` (e.g., `World_v1.0`)  
- **Entity stable IDs:** `W{world_id}_F{faction_id}` etc.  
- **Frames / Shots:** `S{scene:02}_SH{shot:02}_{seed}` → e.g., `S12_SH03_00012345.png`  
- **Story/Script files:** `EP{episode:02}_BeatSheet_v{version}.md`  
- **Changelog entry:** `YYYY-MM-DD — name — short reason`  
- **Use seeds:** Every generated image/video must record `seed`, `model`, `prompt_version`, `service_provider`, `generation_params` in frame metadata.

---

# 5 — Archetype → Character Workflow (best practice)
1. **Define Archetype** (name, drive, fear, typical arc).
2. **Instantiate Character** (override example traits: name, goals, flaws).
3. **Assign Faction and Home Location.**
4. **Define 3 signature beats** for the character across the season (inciting, turning, endpoint).
5. **Create visual anchors** (3 reference frames: neutral, emotional high, emotional low).
6. **Link dialogue audio patterns** (voice tags, cadence, preferred phoneme-mapping for mouth shapes).

*Why:* this keeps characters consistent while letting AI generate many variations from the archetypal seed.

---

# 6 — World Rules → Generation Filters (practical)
- Implement rule checks before generation:
  - Tag-based filter: If `artifact.requires_memory == true` then `prompt` must include `memory_cost`.
  - Hard ban filter: If a prompt attempts a banned action (from `never_happens`) block generation.
- Use these filters as middleware in `genframe_db.sh` or your scheduler.

---

# 7 — Story → Episode → Scene Mapping (concise template)
**World → Series → Season → Episode → Part → Scene → Shot**

For **each Episode**:
- Episode entity: title, logline, world_ref, part_count, target_duration
- For each Part: one-line description, estimated runtime
- For each Scene: slugline (LOCATION — INT/EXT, time), characters, purpose, emotional_target
- For Shots: camera, action, essential props, duration_range, required frames/assets

Provide templates in SAGE so automation can generate placeholders.

---

# 8 — Production Guidelines (scaling & QA)
- **Fast sketch iteration:** 80% thumbnails are good. Iterate 5x quickly, pick best 20%.  
- **Acceptance criteria per shot:** Does it convey the purpose? If yes → keep.  
- **Automated checks:**  
  - Broken image detection (FFmpeg/validate).  
  - Metadata completeness (seed, model, world_ref).  
  - Aspect ratio & DPI checks.
- **Style consistency:** Maintain a `style_profile` entity (palette, lighting rules, camera rules). Attach to world/episode/character as overrides.
- **Storage & cleanup:** Keep 3 generations per seed: draft, selected, final. Auto-clean older drafts older than X days unless pinned.

---

# 9 — Audio & Lip-Sync Governance
- **Audio entities** store: speaker_id, language, voice_profile, phoneme_timestamps, raw_wav_id.  
- **Mouth shapes** map: vowel clusters → mouth sprite id (you already have).  
- **Rule:** Phoneme map must include fallback mapping for unknown phonemes.  
- **Naming:** `dialog_EP{ep}_SC{scene}_L{line}.wav` and link to frame chains.

---

# 10 — Tools, Automation & Orchestration (practical tips)
- **World checks run at generation time.** Hook world validation into scheduler preflight.  
- **Prompt matrix templates:** Define for each archetype + style_profile to keep prompts consistent.  
- **Retry policy:** 3 retries per job with exponential backoff and alternate provider fallback.  
- **Lineage:** `frames_chains` must store `parent_frame_id`, `transform_type`, `params`, `service`.  
- **Experiment tagging:** `experiment/{world_id}/{topic}/{date}` for AB tests.

---

# 11 — Governance, Collaboration & Ownership
- **Authoring rights:** Only designated authors can bump World MAJOR version. Minor patches allowed by leads.  
- **Review cadence:** Weekly editorial review for narrative drift; quarterly world audits.  
- **Emergency rollback:** If a world rule is accidentally changed, copy the previous version to `World_vX.Y+1` and annotate why.

---

# 12 — Minimal Implementation Roadmap (do this now)
1. Create `World` entity and populate **World v0.1** (use the template above).  
2. Add `archetypes`, `factions`, `locations` tables/entities and tag taxonomy.  
3. Add validation middleware to generator pipeline that enforces `world.rules`.  
4. Create small UI view: World → quick readout (premise, themes, rules, factions, archetypes).  
5. Run one episode planning session using the World v0.1 constraints and produce a 25-part beat sheet.  
6. Freeze World v0.1 for that season; only allow fixes via changelog.

---

# 13 — Quick Checklists
## When creating a world:
- [ ] Core premise written (≤3 sentences)  
- [ ] 3 themes listed  
- [ ] 5 hard rules defined  
- [ ] 3–5 factions named  
- [ ] 5 archetypes defined  
- [ ] 3 locations & 3 artifacts described  
- [ ] Version, author, and changelog present

## Before a generation run:
- [ ] World ID attached to job  
- [ ] Prompt validated against `never_happens` and `rules`  
- [ ] Style profile attached  
- [ ] Seeds & model params present  
- [ ] Output metadata template attached

---

# 14 — Example "World v0.1" Entry (copy-paste starter)

Title: Nightfall Verge Core Premise: In a dying city powered by dream-engines, memory is currency; thieves and archivists fight to keep the past private. Themes: Memory vs Identity; Debt and Freedom; Who gets to remember? Tone: Neo-noir, wistful Top Rules: 1) Memory extraction is permanent. 2) No FTL travel. 3) AI cannot fabricate memories. 4) Blood magic fractures language. 5) Death is permanent. Factions: Memory Guild (archives), Alley Courts (survivors), Dream-Foundry (engineers) Archetypes: Archivist; Debt Runner; Former Priest; Mirror (robot child); Gatekeeper Locations: The Vault (archive) — cold/metallic/silent; The Docks — humid/echoing Artifacts: Mnemonic Key — opens sealed memory but consumes a truth Never: No resurrections; no time travel Version: v0.1 — Author: Peter Sebring — 2025-12-25

---

# 15 — Final notes (practical philosophy)
- Build the world to **reduce** choices for generation, not to restrict creativity.  
- Make the World entity the canonical weight of truth that the whole SAGE pipeline can query programmatically.  
- Treat this document as living but guarded: major edits require a reasoned changelog.

---

# 16 — Next actions I can do for you (pick any)
- Turn this MD into a SAGE `worlds` entity + SQL schema.  
- Generate a filled **World v0.1** from one of your seed ideas.  
- Produce a small UI mock for the "World quick readout" in SAGE.  
- Auto-generate a 25-part beat sheet constrained by a given World v0.1.

Which one do you want me to produce right now?