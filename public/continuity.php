<?php
// public/continuity.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Continuity Console";

ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
/* Forge Design System — cloned verbatim from SketchUp so the two modules feel like one family */
:root, [data-theme="dark"] {
    --pl-bg:          #080b10;
    --pl-surface:     #0e1319;
    --pl-card:        #111820;
    --pl-border:      #1c2535;
    --pl-text:        #c8d4e8;
    --pl-text-dim:    #5a6a80;
    --pl-amber:       #f5a623;
    --pl-red:         #f05060;
    --pl-green:       #4caf80;
    --pl-teal:        #3ab5c8;
    --pl-purple:      #a78bfa;
    --radius:         6px;
    --radius-lg:      10px;
    --mono:           'Space Mono', 'Fira Mono', monospace;
    --sans:           'Syne', system-ui, sans-serif;
}
[data-theme="light"] {
    --pl-bg:          #f4f6fa;
    --pl-surface:     #ffffff;
    --pl-card:        #ffffff;
    --pl-border:      #d0d8e8;
    --pl-text:        #1a2233;
    --pl-text-dim:    #7a8aaa;
    --pl-amber:       #c8880a;
    --pl-red:         #d03040;
    --pl-green:       #2e8a58;
    --pl-teal:        #1a8090;
    --pl-purple:      #7c3aed;
}

body { background: var(--pl-bg); color: var(--pl-text); font-family: 'Syne', system-ui, sans-serif; margin: 0; padding: 0; }

/* Navigation — identical component to SketchUp's for shared muscle memory */
.pl-nav { display:flex; align-items:center; gap:10px; padding:10px 16px; background:rgba(0,0,0,.6); border-bottom:1px solid var(--pl-border); position:sticky; top:0; z-index:100; backdrop-filter:blur(6px); flex-wrap:wrap; }
[data-theme="light"] .pl-nav { background:rgba(244,246,250,.92); }
.pl-nav-title { font-family:'Space Mono',monospace; font-size:.8rem; color:var(--pl-text); flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pl-nav-btn { padding:6px 12px; border:1px solid var(--pl-border); border-radius:4px; color:var(--pl-text-dim); text-decoration:none; transition:all .2s; background:var(--pl-surface); cursor:pointer; font-family:'Space Mono',monospace; font-size:.7rem; display:inline-flex; align-items:center; gap:6px; }
.pl-nav-btn:hover { color:var(--pl-amber); border-color:var(--pl-amber); }
.pl-nav-btn.primary { color:#000; background:var(--pl-teal); border-color:var(--pl-teal); font-weight:bold; }
.pl-nav-btn.primary:hover { filter:brightness(1.1); color:#000; }

/* Workspace & Cards */
.workspace { max-width:900px; margin:0 auto; padding:30px 15px 100px; }
.scene-block { background:var(--pl-card); border:1px solid var(--pl-border); border-radius:6px; padding:20px; margin-bottom:24px; box-shadow:0 4px 20px rgba(0,0,0,.3); }
[data-theme="light"] .scene-block { box-shadow:0 2px 10px rgba(0,0,0,.08); }
.scene-header { margin-bottom:15px; border-bottom:1px solid var(--pl-border); padding-bottom:14px; }
.scene-title { font-family:'Space Mono',monospace; font-size:.85rem; color:var(--pl-teal); text-transform:uppercase; letter-spacing:1px; margin:0; display:flex; align-items:center; gap:8px; }
.scene-sub { font-size:.8rem; color:var(--pl-text-dim); margin:0 0 14px; line-height:1.5; }

/* Form inputs & Autocomplete */
.su-input { width:100%; background:var(--pl-surface); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; padding:8px 12px; font-family:'Syne',sans-serif; font-size:.85rem; box-sizing:border-box; transition:border-color .2s; }
.su-input:focus { outline:none; border-color:var(--pl-amber); }
.su-textarea { width:100%; min-height:220px; background:var(--pl-surface); color:var(--pl-text); border:1px solid var(--pl-border); border-radius:4px; padding:12px; font-family:var(--mono); font-size:.78rem; line-height:1.5; box-sizing:border-box; resize:vertical; }
.su-textarea:focus { outline:none; border-color:var(--pl-amber); }

.su-ac-wrap { position:relative; margin-bottom: 12px; }
.su-ac-dropdown { position:absolute; top:100%; left:0; right:0; z-index:10; background:var(--pl-card); border:1px solid var(--pl-border); border-top:none; border-radius:0 0 4px 4px; max-height:200px; overflow-y:auto; display:none; box-shadow:0 4px 12px rgba(0,0,0,.5); }
.su-ac-dropdown.open { display:block; }
.su-ac-item { padding:8px 12px; font-size:.8rem; cursor:pointer; transition:background .1s; border-bottom:1px solid var(--pl-border); color:var(--pl-text); }
.su-ac-item:hover { background:rgba(58,181,200,.1); color:var(--pl-teal); }
.su-ac-item:last-child { border-bottom:none; }

.row-flex { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }

.pl-btn { padding:7px 14px; border-radius:4px; border:1px solid; font-family:'Space Mono',monospace; font-size:.75rem; cursor:pointer; transition:all .15s; white-space:nowrap; }
.pl-btn-secondary { border-color:var(--pl-border); background:var(--pl-surface); color:var(--pl-text-dim); }
.pl-btn-secondary:hover { border-color:var(--pl-amber); color:var(--pl-amber); }

/* Chips */
.beat-entity-chips { display:flex; flex-wrap:wrap; gap:6px; }
.beat-entity-chip { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:12px; font-size:.75rem; font-family:'Space Mono',monospace; border:1px solid; }
.chip-sket { background:rgba(58,181,200,.1); border-color:var(--pl-teal); color:var(--pl-teal); }
.chip-char { background:rgba(167,139,250,.1); border-color:var(--pl-purple); color:var(--pl-purple); }
.chip-map { background:rgba(245,166,35,.1); border-color:var(--pl-amber); color:var(--pl-amber); }
.chip-seq { background:rgba(76,175,80,.1); border-color:var(--pl-green); color:var(--pl-green); }
.chip-remove { background:none; border:none; cursor:pointer; color:inherit; opacity:.6; font-size:.8rem; padding:0; line-height:1; transition:opacity .15s; }
.chip-remove:hover { opacity:1; }

/* Status cards for auto-increment display */
.ai-status-grid { display:flex; gap:12px; flex-wrap:wrap; }
.ai-status-card { flex:1; min-width:180px; background:var(--pl-surface); border:1px solid var(--pl-border); border-radius:6px; padding:14px; }
.ai-status-table { font-family:var(--mono); font-size:.7rem; color:var(--pl-text-dim); text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; }
.ai-status-value { font-family:var(--mono); font-size:1.5rem; color:var(--pl-teal); font-weight:bold; }
.ai-status-rows { font-family:var(--mono); font-size:.68rem; color:var(--pl-text-dim); margin-top:4px; }

/* Sample preview list */
.sample-list { max-height:400px; overflow-y:auto; border:1px solid var(--pl-border); border-radius:6px; background:var(--pl-surface); }
.sample-row { padding:10px 12px; border-bottom:1px solid var(--pl-border); font-family:var(--mono); font-size:.75rem; }
.sample-row:last-child { border-bottom:none; }
.sample-row .sr-id { color:var(--pl-amber); margin-right:8px; font-weight:bold; }
.sample-row .sr-name { color:var(--pl-text); font-weight:bold; font-size:.8rem; }
.sample-block-title { padding:8px 12px; background:rgba(0,0,0,0.2); border-bottom:1px solid var(--pl-border); color:var(--pl-text-dim); font-family:var(--mono); font-size:.7rem; font-weight:bold; text-transform:uppercase; position:sticky; top:0; backdrop-filter:blur(4px); }

.su-check-label { display:flex; align-items:center; gap:8px; font-size:.85rem; cursor:pointer; margin-bottom:10px; user-select:none; color:var(--pl-text); }
.su-check-label input[type="checkbox"] { accent-color:var(--pl-teal); width:16px; height:16px; cursor:pointer; }
</style>

<script>
window.ContinuityApp = (function() {
    const INSTRUCTIONS_KEY = 'sl_continuity_instructions_v2';

    const DEFAULT_INSTRUCTIONS = `## AI INSTRUCTIONS — HOW TO APPLY CONTINUITY WHEN WRITING NEW SKETCH INSERTS

1. TARGET TABLE: \`sketches\`. Use INSERT IGNORE, wrapped in a single transaction
   (START TRANSACTION; ... COMMIT;) for each episode or paired-episode file.

2. COLUMN ORDER (all 21 columns present in every row, NULL where unused):
   \`name\`, \`description\`, \`description_raw\`, \`prompt_negative\`, \`seed\`,
   \`searchable\`, \`mood\`, \`created_at\`, \`updated_at\`, \`regenerate_images\`,
   \`active_map_run_id\`, \`state_id_active\`, \`img2img\`, \`depth2img\`,
   \`img2img_frame_id\`, \`img2img_frame_filename\`, \`img2img_prompt\`, \`cnmap\`,
   \`cnmap_frame_id\`, \`cnmap_frame_filename\`, \`cnmap_prompt\`.
   Convention for unused fields: description_raw/prompt_negative/mood/etc = NULL,
   seed = '1', searchable = NULL, regenerate_images/img2img/depth2img/cnmap = '0'.

3. NAMING CONVENTION: \`EPxx SCNyy_Title_In_Underscores\` — e.g. \`EP29 SCN04_The_Locked_Cellar\`.
   Always sequential SCN01–SCN15 (or as specified) within an episode. Never renumber
   or skip without instruction.

4. THE description FIELD IS THE ACTUAL IMAGE PROMPT. It must be:
   - Fully self-contained and stateless-call-safe: every character reintroduced by
     full name, age, and established palette EVERY time they appear in a scene,
     because each row may be rendered by an isolated model call with no memory
     of any other row.
   - Free of ALL production/meta commentary. Never write "per Bible convention,"
     "established since Episode X," "Season Three," "per the outline," or any
     phrase that refers to the writers' room, prior conversation, or document
     structure. The prompt is consumed by an image model and must read as a
     pure visual scene description — nothing else.
   - Written as a single flowing paragraph describing a full comic-book page
     with multiple panels, ending with a short "Cel-shaded anime art style, ..."
     clause summarizing 3–4 key visual/technique notes for the renderer.
     prefix with: "Anime comic book panel scene, formatted as a full comic book page with multiple panels, black page background, panels separated by black gutters, "
   - Encapsulated. Each sketch is produced by a single isolated AI image inference call with zero access to any other sketch, scene, or episode context. Every row must therefore be fully self-contained and explicit: always restate the full character and scene description needed for rendering, and never depend on prior references, shared continuity memory, or omitted details.


5. TIMESTAMPS: created_at/updated_at identical per row, sequential by ~30 seconds
   across a 15-scene episode, using the actual date of writing (not a fictional
   in-story date) unless told otherwise.

6. AGE RATING: all content stays within a 14+ register — tension, dread, and
   implication are the tools; no gore, no explicit content, violence brief
   and non-graphic when present at all.

7. provide exact continuity queue job INSERT statement 

\`\`\`markdown
# AI Prompt: SQL Generation for Character Continuity Queue

You are an expert SQL assistant. Your task is to analyze a database dump of two tables (\`sketches\` and \`characters\`) and generate \`INSERT IGNORE\` SQL statements to populate the \`continuity_jobs\` queue table. 

Below are the details of the database schemas, matching rules, and output requirements.

---

## 1. Target Schema: \`continuity_jobs\`

The generated queries must insert rows into \`continuity_jobs\`. Your inserts should focus on the following fields:

| Column | Type | Description |
| :--- | :--- | :--- |
| \`sketch_id\` | INT | The ID from the \`sketches\` table. |
| \`character_id\` | INT | The ID from the \`characters\` table. |
| \`sort_order\` | INT | \`0\`-indexed position of the character within this specific sketch (e.g., if sketch #1 has characters A and B, character A has \`sort_order = 0\`, character B has \`sort_order = 1\`). |
| \`status\` | VARCHAR | Must always be \`'pending'\`. |
| \`cont_gen_id\` | INT | The generator configuration ID. Default to \`126\` (or a user-specified ID) unless instructed otherwise. |

### Schema Reference for Insert
\`\`\`sql
INSERT IGNORE INTO continuity_jobs (sketch_id, character_id, sort_order, status, cont_gen_id)
VALUES (sketch_id, character_id, sort_order, 'pending', generator_id);
\`\`\`
*(Using \`INSERT IGNORE\` ensures we do not trigger errors on the unique constraint \`uq_cj_sketch_char\` if a mapping already exists).*

---

## 2. Character Matching Rules

To determine if a character from the \`characters\` table belongs in a scene from the \`sketches\` table, apply the following analysis:

1. **Direct Matches**: Search the sketch's \`name\` and \`description\` fields for the character's exact name.
2. **Aliases and Variations**: Identify variations or parts of a character's name (e.g., if the character is "Spiegel, Spike", match "Spike" or "Spike Spiegel").
3. **Contextual Clues**: If the sketch description explicitly details a character's unique physical appearance (matching the description in the \`characters\` table) without naming them directly, associate that character with the sketch.
4. **Exclusion**: If a character is not mentioned, referred to, or physically implied in the sketch text, do not generate a job for them on that sketch.

---

## 3. SQL Compilation Requirements

When generating the final SQL output:

* **Group by Sketch**: Order the inserts by \`sketch_id\` ascending.
* **Deterministic Sort Order**: Within each sketch group, assign \`sort_order\` starting at \`0\` for the first matched character, incrementing by \`1\` for each subsequent character (e.g., \`0, 1, 2...\`). Base this order on the appearance order in the text or alphabetically by character name if they appear together.
* **Clean Syntax**: Output standard SQL insert blocks, combining multiple values into a single query block where appropriate.

---

## 4. Input Format Example (For Reference Only)

\`\`\`sql
-- Sample Sketches Dump
INSERT INTO sketches (id, name, description) VALUES 
(101, 'Scene 1', 'Spike stands in the rain holding a grey umbrella, looking at the city lights.'),
(102, 'Scene 2', 'A young woman with purple hair and a red vest walks past a spaceship.');

-- Sample Characters Dump
INSERT INTO characters (id, name, description) VALUES 
(5, 'Spike Spiegel', 'Tall, lean man with messy dark green hair, wearing a blue suit.'),
(9, 'Faye Valentine', 'Young woman with purple hair, red vest, gold shorts.');
\`\`\`

### Expected Output Structure
\`\`\`sql
-- Continuity queue mapping for Scene 1 (Spike)
INSERT IGNORE INTO continuity_jobs (sketch_id, character_id, sort_order, status, cont_gen_id) VALUES
(101, 5, 0, 'pending', 126);

-- Continuity queue mapping for Scene 2 (Faye)
INSERT IGNORE INTO continuity_jobs (sketch_id, character_id, sort_order, status, cont_gen_id) VALUES
(102, 9, 0, 'pending', 126);
\`\`\`

---

## 5. Instructions for Generation

Please analyze the provided SQL dumps for \`sketches\` and \`characters\` below.
Identify all logical scene-to-character associations, and write the appropriate \`INSERT IGNORE\` statement block. Provide only the SQL statements or keep descriptions brief.
\`\`\``;

    let ranges = []; 
    let autoIncStatuses = [];
    let mapRunTimer, seqTimer;

    function loadInstructions() {
        const saved = localStorage.getItem(INSTRUCTIONS_KEY);
        document.getElementById('instructionsBox').value = saved || DEFAULT_INSTRUCTIONS;
    }

    function saveInstructions() {
        localStorage.setItem(INSTRUCTIONS_KEY, document.getElementById('instructionsBox').value);
        if (window.Toast) Toast.show('Instructions saved locally.', 'success');
    }

    function resetInstructions() {
        document.getElementById('instructionsBox').value = DEFAULT_INSTRUCTIONS;
        localStorage.removeItem(INSTRUCTIONS_KEY);
        if (window.Toast) Toast.show('Reset to default instructions.', 'info');
    }

    function refreshAutoIncrements() {
        const grid = document.getElementById('aiStatusGrid');
        grid.innerHTML = '<div style="color:var(--pl-text-dim); font-family:var(--mono); font-size:.75rem;">Loading…</div>';
        fetch('continuity_api.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'get_auto_increments'})
        }).then(r=>r.json()).then(res => {
            if (!res.ok) { grid.innerHTML = '<div style="color:var(--pl-red);">Failed to load.</div>'; return; }
            autoIncStatuses = res.statuses;
            grid.innerHTML = res.statuses.map(s => `
                <div class="ai-status-card">
                    <div class="ai-status-table">${s.table}</div>
                    <div class="ai-status-value">${s.auto_increment !== null ? s.auto_increment : '—'}</div>
                    <div class="ai-status-rows">~${s.rows !== null ? s.rows : '?'} rows (estimate)</div>
                </div>
            `).join('');
        }).catch(() => { grid.innerHTML = '<div style="color:var(--pl-red);">Request failed.</div>'; });
    }

    function addRange(table) {
        const prefix = table === 'sketches' ? 'sketch' : 'char';
        let fromId = parseInt(document.getElementById(prefix + 'FromId').value);
        let toId = parseInt(document.getElementById(prefix + 'ToId').value);

        // Allow single ID lookup if only one field is populated
        if (!isNaN(fromId) && isNaN(toId)) toId = fromId;
        if (isNaN(fromId) && !isNaN(toId)) fromId = toId;

        if (isNaN(fromId) || isNaN(toId) || fromId <= 0 || toId <= 0) {
            if (window.Toast) Toast.show('Enter a valid positive ID.', 'warn');
            return;
        }

        const list = document.getElementById('sampleList');
        list.innerHTML = '<div style="padding:12px; color:var(--pl-text-dim); font-family:var(--mono); font-size:.72rem;">Loading…</div>';

        fetch('continuity_api.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'analyze_range', table, from_id: fromId, to_id: toId})
        }).then(r=>r.json()).then(res => {
            if (!res.ok) { 
                if (window.Toast) Toast.show(res.error || 'Lookup failed.', 'error'); 
                renderSample();
                return; 
            }
            if (res.result.count === 0) { 
                if (window.Toast) Toast.show('No rows found in that ID range.', 'warn'); 
                renderSample();
                return; 
            }
            
            ranges.push(res.result);
            
            document.getElementById(prefix + 'FromId').value = '';
            document.getElementById(prefix + 'ToId').value = '';

            renderRanges();
            renderSample();
        }).catch(() => { 
            if (window.Toast) Toast.show('Request failed.', 'error');
            renderSample();
        });
    }

    // --- Map Runs Autocomplete ---
    function searchMapRuns(q) {
        clearTimeout(mapRunTimer);
        const dd = document.getElementById('mapRunAc');
        mapRunTimer = setTimeout(() => {
            fetch('continuity_api.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({action:'search_map_runs', q: q.trim()}) })
            .then(r=>r.json()).then(res => {
                if (!res.ok || !res.results.length) { dd.innerHTML='<div class="su-ac-item">No results</div>'; dd.classList.add('open'); return; }
                dd.innerHTML = res.results.map(x => `
                    <div class="su-ac-item" onclick="ContinuityApp.selectMapRun(${x.id})">
                        <span style="color:var(--pl-text-dim); font-family:var(--mono); font-size:0.7rem; margin-right:6px;">#${x.id}</span>
                        ${x.note || 'No note'} 
                        <span style="float:right; font-size:0.65rem; color:var(--pl-text-dim);">${x.created_at.split(' ')[0]}</span>
                    </div>
                `).join('');
                dd.classList.add('open');
            });
        }, 150);
    }

    function selectMapRun(id) {
        document.getElementById('mapRunAc').classList.remove('open');
        document.getElementById('mapRunSearch').value = '';
        
        const list = document.getElementById('sampleList');
        list.innerHTML = '<div style="padding:12px; color:var(--pl-text-dim); font-family:var(--mono); font-size:.72rem;">Loading…</div>';

        fetch('continuity_api.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({action:'analyze_map_run', map_run_id: id}) })
        .then(r=>r.json()).then(res => {
            if (!res.ok) { if(window.Toast) Toast.show(res.error, 'error'); renderSample(); return; }
            if (res.result.count === 0) { if(window.Toast) Toast.show('Map Run has no sketches.', 'warn'); renderSample(); return; }
            ranges.push(res.result);
            renderRanges();
            renderSample();
        });
    }

    // --- Narrative Sequences Autocomplete ---
    function searchSequences(q) {
        clearTimeout(seqTimer);
        const dd = document.getElementById('seqAc');
        seqTimer = setTimeout(() => {
            fetch('continuity_api.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({action:'search_sequences', q: q.trim()}) })
            .then(r=>r.json()).then(res => {
                if (!res.ok || !res.results.length) { dd.innerHTML='<div class="su-ac-item">No results</div>'; dd.classList.add('open'); return; }
                dd.innerHTML = res.results.map(x => `
                    <div class="su-ac-item" onclick="ContinuityApp.selectSequence(${x.id})">
                        <span style="color:var(--pl-text-dim); font-family:var(--mono); font-size:0.7rem; margin-right:6px;">#${x.id}</span>
                        ${x.name || 'Untitled'}
                    </div>
                `).join('');
                dd.classList.add('open');
            });
        }, 150);
    }

    function selectSequence(id) {
        document.getElementById('seqAc').classList.remove('open');
        document.getElementById('seqSearch').value = '';
        
        const list = document.getElementById('sampleList');
        list.innerHTML = '<div style="padding:12px; color:var(--pl-text-dim); font-family:var(--mono); font-size:.72rem;">Loading…</div>';

        fetch('continuity_api.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({action:'analyze_sequence', seq_id: id}) })
        .then(r=>r.json()).then(res => {
            if (!res.ok) { if(window.Toast) Toast.show(res.error, 'error'); renderSample(); return; }
            if (res.result.count === 0) { if(window.Toast) Toast.show('Sequence has no sketches.', 'warn'); renderSample(); return; }
            ranges.push(res.result);
            renderRanges();
            renderSample();
        });
    }

    function removeRange(idx) {
        ranges.splice(idx, 1);
        renderRanges();
        renderSample();
    }

    function renderRanges() {
        const sketchHtml = [];
        const charHtml = [];
        const mapHtml = [];
        const seqHtml = [];

        ranges.forEach((r, i) => {
            let cls = 'chip-sket';
            if (r.sub_type === 'map_run') cls = 'chip-map';
            else if (r.sub_type === 'sequence') cls = 'chip-seq';
            else if (r.table === 'characters') cls = 'chip-char';

            const chip = `
                <span class="beat-entity-chip ${cls}">
                    <span>${r.source_label} (${r.count} rows)</span>
                    <button class="chip-remove" onclick="ContinuityApp.removeRange(${i})"><i class="bi bi-x"></i></button>
                </span>
            `;

            if (r.sub_type === 'map_run') mapHtml.push(chip);
            else if (r.sub_type === 'sequence') seqHtml.push(chip);
            else if (r.table === 'characters') charHtml.push(chip);
            else sketchHtml.push(chip);
        });

        document.getElementById('sketchRangesList').innerHTML = sketchHtml.length ? sketchHtml.join('') : '<div style="font-size:.75rem; color:var(--pl-text-dim); padding:4px;">No sketch ranges added.</div>';
        document.getElementById('charRangesList').innerHTML = charHtml.length ? charHtml.join('') : '<div style="font-size:.75rem; color:var(--pl-text-dim); padding:4px;">No character ranges added.</div>';
        document.getElementById('mapRunRangesList').innerHTML = mapHtml.length ? mapHtml.join('') : '<div style="font-size:.75rem; color:var(--pl-text-dim); padding:4px;">No map runs added.</div>';
        document.getElementById('seqRangesList').innerHTML = seqHtml.length ? seqHtml.join('') : '<div style="font-size:.75rem; color:var(--pl-text-dim); padding:4px;">No sequences added.</div>';
    }

    function renderSample() {
        const list = document.getElementById('sampleList');
        if (ranges.length === 0) {
            list.innerHTML = '<div style="padding:12px; color:var(--pl-text-dim); font-family:var(--mono); font-size:.72rem;">No ranges added yet.</div>';
            return;
        }
        
        let html = '';
        ranges.forEach((r, idx) => {
            if (r.sample && r.sample.length > 0) {
                html += `<div class="sample-block-title">Set #${idx+1}: ${r.table} &middot; ${r.source_label}</div>`;
                
                r.sample.forEach(s => {
                    let details = '';
                    if (r.table === 'sketches') {
                        if (s.mood) details += ` &middot; <span style="color:var(--pl-text-dim);">Mood: ${s.mood}</span>`;
                        if (s.description) details += `<div style="margin-top:6px; color:var(--pl-text-dim); line-height:1.4;">${s.description}</div>`;
                    } else if (r.table === 'characters') {
                        if (s.role) details += ` &middot; <span style="color:var(--pl-text-dim);">Role: ${s.role}</span>`;
                        if (s.description) details += `<div style="margin-top:6px; color:var(--pl-text-dim); line-height:1.4;">${s.description}</div>`;
                    }
                    html += `
                        <div class="sample-row">
                            <span class="sr-id">#${s.id}</span>
                            <span class="sr-name">${s.name}</span>${details}
                        </div>
                    `;
                });
            }
        });
        
        list.innerHTML = html;
    }

    function buildPacket() {
        const lines = [];
        lines.push('# CONTINUITY PACKET');
        lines.push('Generated: ' + new Date().toISOString());
        lines.push('');
        lines.push('## CURRENT AUTO_INCREMENT STATUS');
        if (autoIncStatuses.length) {
            autoIncStatuses.forEach(s => {
                lines.push(`- \`${s.table}\`: next auto_increment = **${s.auto_increment}** (~${s.rows} existing rows)`);
            });
        } else {
            lines.push('_(Not fetched this session — click "Refresh" before generating for accurate numbers.)_');
        }
        lines.push('');
        lines.push('## CONFIRMED ID RANGES (from lookups this session)');
        if (ranges.length) {
            ranges.forEach(r => {
                lines.push(`- Table \`${r.table}\`: requested ${r.source_label}, found **${r.count}** rows (actual id ${r.actual_min_id}–${r.actual_max_id}). First: "${r.first_name}". Last: "${r.last_name}".`);
            });
        } else {
            lines.push('_(No ranges looked up this session.)_');
        }
        lines.push('');
        
        if (ranges.length) {
            ranges.forEach((r, idx) => {
                if (r.sample && r.sample.length > 0) {
                    lines.push(`## DATA FROM LOOKUP #${idx+1} — \`${r.table}\` (${r.source_label})`);
                    lines.push('');
                    r.sample.forEach(s => {
                        lines.push(`### #${s.id} — ${s.name}`);
                        if (r.table === 'sketches') {
                            if (s.mood) lines.push(`_Mood: ${s.mood}_`);
                            lines.push('');
                            if (s.description) lines.push(s.description);
                        } else if (r.table === 'characters') {
                            if (s.role) lines.push(`_Role: ${s.role}_`);
                            if (s.age_background) lines.push(`_Background: ${s.age_background}_`);
                            lines.push('');
                            if (s.description) lines.push(s.description);
                            if (s.motivations) lines.push(`\n**Motivations:** ${s.motivations}`);
                        }
                        lines.push('');
                    });
                }
            });
        }
        
        lines.push('---');
        lines.push('');
        lines.push(document.getElementById('instructionsBox').value);
        return lines.join('\n');
    }

    function downloadPacket() {
        const content = buildPacket();
        const blob = new Blob([content], {type: 'text/markdown;charset=utf-8'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        const stamp = new Date().toISOString().slice(0,19).replace(/[:T]/g,'-');
        a.href = url;
        a.download = `continuity_packet_${stamp}.md`;
        document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(url);
        if (window.Toast) Toast.show('Continuity packet downloaded.', 'success');
    }

    return {
        loadInstructions, saveInstructions, resetInstructions,
        refreshAutoIncrements,
        addRange, removeRange,
        searchMapRuns, selectMapRun,
        searchSequences, selectSequence,
        downloadPacket
    };
})();
</script>

<div class="pl-nav">
    <span class="pl-nav-title"><i class="bi bi-diagram-3"></i> Continuity Console</span>
    <button class="pl-nav-btn primary" onclick="ContinuityApp.downloadPacket()">
        <i class="bi bi-download"></i> Download Continuity Packet
    </button>
</div>

<div class="workspace">

    <!-- Panel A: Auto-Increment Status -->
    <div class="scene-block">
        <div class="scene-header">
            <h3 class="scene-title"><i class="bi bi-hash"></i> Next ID / Auto-Increment Status</h3>
        </div>
        <p class="scene-sub">Check this before writing a new batch of inserts, so you know where the new rows will land.</p>
        <div class="row-flex">
            <button class="pl-btn pl-btn-secondary" onclick="ContinuityApp.refreshAutoIncrements()">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
        <div class="ai-status-grid" id="aiStatusGrid">
            <div style="color:var(--pl-text-dim); font-family:var(--mono); font-size:.75rem;">Click Refresh to load…</div>
        </div>
    </div>

    <div style="display: flex; flex-wrap: wrap; gap: 24px;">
        <!-- Panel B: Sketch Continuity Ranges -->
        <div class="scene-block" style="flex: 1; min-width: 300px; margin-bottom:0;">
            <div class="scene-header">
                <h3 class="scene-title" style="color:var(--pl-teal);"><i class="bi bi-images"></i> Sketch Ranges</h3>
            </div>
            <p class="scene-sub" style="min-height:38px;">Add ID bounds (or a single ID) of sketches.</p>
            <div class="row-flex">
                <input type="number" id="sketchFromId" class="su-input" placeholder="From ID" style="max-width:85px;">
                <input type="number" id="sketchToId" class="su-input" placeholder="To ID" style="max-width:85px;">
                <button class="pl-btn pl-btn-secondary" onclick="ContinuityApp.addRange('sketches')">
                    <i class="bi bi-plus-lg"></i> Add
                </button>
            </div>
            <div id="sketchRangesList" class="beat-entity-chips" style="margin-top:12px;">
                <div style="font-size:.75rem; color:var(--pl-text-dim); padding:4px;">No sketch ranges added.</div>
            </div>
        </div>

        <!-- Panel C: Character Continuity Ranges -->
        <div class="scene-block" style="flex: 1; min-width: 300px; margin-bottom:0;">
            <div class="scene-header">
                <h3 class="scene-title" style="color:var(--pl-purple);"><i class="bi bi-person-lines-fill"></i> Character Ranges</h3>
            </div>
            <p class="scene-sub" style="min-height:38px;">Add ID bounds (or a single ID) of characters.</p>
            <div class="row-flex">
                <input type="number" id="charFromId" class="su-input" placeholder="From ID" style="max-width:85px;">
                <input type="number" id="charToId" class="su-input" placeholder="To ID" style="max-width:85px;">
                <button class="pl-btn pl-btn-secondary" onclick="ContinuityApp.addRange('characters')">
                    <i class="bi bi-plus-lg"></i> Add
                </button>
            </div>
            <div id="charRangesList" class="beat-entity-chips" style="margin-top:12px;">
                <div style="font-size:.75rem; color:var(--pl-text-dim); padding:4px;">No character ranges added.</div>
            </div>
        </div>
    </div>

    <div style="display: flex; flex-wrap: wrap; gap: 24px; margin-top:24px; margin-bottom:24px;">
        <!-- Panel D: Map Runs -->
        <div class="scene-block" style="flex: 1; min-width: 300px; margin-bottom:0;">
            <div class="scene-header">
                <h3 class="scene-title" style="color:var(--pl-amber);"><i class="bi bi-map"></i> Map Runs (Sketches)</h3>
            </div>
            <p class="scene-sub" style="min-height:38px;">Auto-propose map runs to include their generated sketches.</p>
            <div class="row-flex">
                <div class="su-ac-wrap" style="flex:1; margin-bottom:0;">
                    <input type="text" id="mapRunSearch" class="su-input" placeholder="Search map runs..." onfocus="ContinuityApp.searchMapRuns(this.value)" oninput="ContinuityApp.searchMapRuns(this.value)">
                    <div id="mapRunAc" class="su-ac-dropdown"></div>
                </div>
            </div>
            <div id="mapRunRangesList" class="beat-entity-chips" style="margin-top:12px;">
                <div style="font-size:.75rem; color:var(--pl-text-dim); padding:4px;">No map runs added.</div>
            </div>
        </div>

        <!-- Panel E: Narrative Sequences -->
        <div class="scene-block" style="flex: 1; min-width: 300px; margin-bottom:0;">
            <div class="scene-header">
                <h3 class="scene-title" style="color:var(--pl-green);"><i class="bi bi-film"></i> Narrative Sequences</h3>
            </div>
            <p class="scene-sub" style="min-height:38px;">Auto-propose sequences to include their curated sketches.</p>
            <div class="row-flex">
                <div class="su-ac-wrap" style="flex:1; margin-bottom:0;">
                    <input type="text" id="seqSearch" class="su-input" placeholder="Search sequences..." onfocus="ContinuityApp.searchSequences(this.value)" oninput="ContinuityApp.searchSequences(this.value)">
                    <div id="seqAc" class="su-ac-dropdown"></div>
                </div>
            </div>
            <div id="seqRangesList" class="beat-entity-chips" style="margin-top:12px;">
                <div style="font-size:.75rem; color:var(--pl-text-dim); padding:4px;">No sequences added.</div>
            </div>
        </div>
    </div>

    <!-- Panel F: Accumulated Continuity Data -->
    <div class="scene-block">
        <div class="scene-header">
            <h3 class="scene-title"><i class="bi bi-collection"></i> Accumulated Continuity Data</h3>
        </div>
        <p class="scene-sub">Combined full records of all ranges/collections added above. This data is bundled directly into the Markdown packet.</p>
        <div class="sample-list" id="sampleList">
            <div style="padding:12px; color:var(--pl-text-dim); font-family:var(--mono); font-size:.72rem;">No ranges added yet.</div>
        </div>
    </div>

    <!-- Panel G: AI Continuity Instructions -->
    <div class="scene-block">
        <div class="scene-header">
            <h3 class="scene-title"><i class="bi bi-robot"></i> AI Continuity Instructions</h3>
        </div>
        <p class="scene-sub">This text is bundled into every downloaded packet. Edit it as your conventions evolve — it saves to this browser automatically. Nothing here is sent anywhere except into your downloaded file.</p>
        <textarea id="instructionsBox" class="su-textarea"></textarea>
        <div class="row-flex" style="margin-top:10px;">
            <button class="pl-btn pl-btn-secondary" onclick="ContinuityApp.saveInstructions()">
                <i class="bi bi-check2"></i> Save Locally
            </button>
            <button class="pl-btn pl-btn-secondary" onclick="ContinuityApp.resetInstructions()">
                <i class="bi bi-arrow-counterclockwise"></i> Reset to Default
            </button>
        </div>
    </div>

</div>

<script src="/js/toast.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    ContinuityApp.loadInstructions();
    ContinuityApp.refreshAutoIncrements();
    
    // Close dropdowns on outside click
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.su-ac-wrap')) {
            document.querySelectorAll('.su-ac-dropdown').forEach(el => el.classList.remove('open'));
        }
    });
});
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>
