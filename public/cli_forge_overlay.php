<?php
// public/cli_forge_overlay.php
// Overlay Text Composer sub-view for CLI Forge Hub.
// Paired with cli_overlay_compose.php
// job_type: overlay_compose
// Payload keys: cinemagic_id (int|null), sequence_id (int|null), sketch_id (int|null), rerun (bool)
?>
<div class="hub-view" id="view-overlay">
    <div class="view-header">
        <div class="view-title-row">
            <i class="bi bi-card-text" style="color:var(--amber);font-size:18px;flex-shrink:0"></i>
            <div class="view-title">Overlay Text Composer</div>
        </div>
        <div class="view-tabs">
            <button class="vtab active" data-panel="overlay-config" onclick="Hub.switchPanel('overlay','config',this)">Configure</button>
            <button class="vtab" data-panel="overlay-json" onclick="Hub.switchPanel('overlay','json',this)">JSON Preview</button>
            <button class="vtab" data-panel="overlay-queue" onclick="Hub.switchPanel('overlay','queue',this)">Queue</button>
        </div>
    </div>

    <!-- ── CONFIG PANEL ── -->
    <div class="panel-scroll" id="overlay-config">

        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l">
                    <div class="section-num">1</div>
                    <div class="section-lbl">
                        <strong>Selection Mode</strong>
                        <span>Choose how to target sketches for overlay generation.</span>
                    </div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="pill-row">
                        <label class="pill">
                            <input type="radio" name="overlay_mode" id="overlay_mode_cinemagic" value="cinemagic" checked onchange="Hub.overlayModeChange()">
                            Cinemagic / Sequence
                        </label>
                        <label class="pill">
                            <input type="radio" name="overlay_mode" id="overlay_mode_sketch" value="sketch" onchange="Hub.overlayModeChange()">
                            Single Sketch ID
                        </label>
                    </div>
                    <div class="fhint">Cinemagic mode targets all sketches within a sequence. Sketch ID mode targets one sketch directly.</div>
                </div>
            </div>
        </details>

        <!-- ── CINEMAGIC / SEQUENCE FIELDS ── -->
        <div id="overlay-cinemagic-fields">

            <details class="forge-section" open>
                <summary class="section-head">
                    <div class="section-head-l">
                        <div class="section-num">2</div>
                        <div class="section-lbl">
                            <strong>Cinemagic (Magazine)</strong>
                            <span>Filter sequences by cinemagic, or leave on "All sequences" to browse ungrouped.</span>
                        </div>
                    </div>
                    <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
                </summary>
                <div class="section-body">
                    <div class="field">
                        <div class="flabel">Cinemagic</div>
                        <select id="overlay_cinemagic_id" onchange="Hub.overlayLoadSequences()">
                            <option value="0">— All sequences (no cinemagic filter) —</option>
                        </select>
                        <div class="fhint">Selecting a cinemagic loads only its sequences below. Leave unset to see every narrative sequence.</div>
                    </div>
                </div>
            </details>

            <details class="forge-section" open>
                <summary class="section-head">
                    <div class="section-head-l">
                        <div class="section-num">3</div>
                        <div class="section-lbl">
                            <strong>Sequence</strong>
                            <span>One sequence, or "All" to process every sequence in the selected scope.</span>
                        </div>
                    </div>
                    <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
                </summary>
                <div class="section-body">
                    <div class="field">
                        <div class="flabel">Narrative Sequence</div>
                        <select id="overlay_sequence_id" oninput="Hub.updateJson('overlay')">
                            <option value="0">— All sequences in scope —</option>
                        </select>
                        <div class="fhint">Selecting "All" queues one job per sequence. A specific sequence queues a single job.</div>
                    </div>
                </div>
            </details>

        </div><!-- /#overlay-cinemagic-fields -->

        <!-- ── SKETCH ID FIELD ── -->
        <div id="overlay-sketch-fields" style="display:none">

            <details class="forge-section" open>
                <summary class="section-head">
                    <div class="section-head-l">
                        <div class="section-num">2</div>
                        <div class="section-lbl">
                            <strong>Sketch ID</strong>
                            <span>Enter the sketches.id directly to compose overlay texts for a single sketch.</span>
                        </div>
                    </div>
                    <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
                </summary>
                <div class="section-body">
                    <div class="field">
                        <div class="flabel">Sketch ID</div>
                        <input type="number" id="overlay_sketch_id" min="1" placeholder="e.g. 1234" oninput="Hub.updateJson('overlay')">
                        <div class="fhint">The numeric primary key from the sketches table.</div>
                    </div>
                </div>
            </details>

        </div><!-- /#overlay-sketch-fields -->

        <details class="forge-section">
            <summary class="section-head">
                <div class="section-head-l">
                    <div class="section-num">4</div>
                    <div class="section-lbl">
                        <strong>Run Options</strong>
                        <span>Force re-compose or skip sketches that already have English overlay texts.</span>
                    </div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="pill-row">
                        <label class="pill">
                            <input type="checkbox" id="overlay_rerun" onchange="Hub.updateJson('overlay')">
                            Force re-compose (rerun)
                        </label>
                    </div>
                    <div class="fhint">When unchecked, sketches that already have English overlay rows are skipped.</div>
                </div>
            </div>
        </details>

        <details class="forge-section">
            <summary class="section-head">
                <div class="section-head-l">
                    <div class="section-num">5</div>
                    <div class="section-lbl"><strong>Job Label and Priority</strong></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Job Label</div>
                    <input type="text" id="overlay_label" placeholder="Overlay — seq #164">
                </div>
                <div class="field">
                    <div class="flabel">Priority (1 = highest, 99 = lowest)</div>
                    <input type="number" id="overlay_priority" min="1" max="99" value="50">
                </div>
            </div>
        </details>

    </div><!-- /#overlay-config -->

    <!-- ── JSON PREVIEW PANEL ── -->
    <div class="panel-scroll" id="overlay-json" style="display:none">
        <pre class="json-pre" id="overlay_json_out"></pre>
        <div class="fhint" style="margin-top:8px">
            CLI runner:
            <code>php public/cli_overlay_compose.php --seq=&lt;id&gt;</code>
            &nbsp;|&nbsp;
            <code>php public/cli_overlay_compose.php --cinemagic=&lt;id&gt;</code>
            &nbsp;|&nbsp;
            <code>php public/cli_overlay_compose.php --sketch=&lt;id&gt;</code>
        </div>
    </div>

    <!-- ── QUEUE PANEL ── -->
    <div id="overlay-queue" style="display:none;flex:1;flex-direction:column;overflow:hidden">
        <div class="queue-header">
            <div class="q-count-pills" id="overlay-q-counts"></div>
            <button class="icon-btn" style="width:32px;height:32px;font-size:13px" onclick="Hub.refreshQueue('overlay')">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="queue-table-wrap">
            <table class="queue-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Label</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="overlay-q-tbody"></tbody>
            </table>
        </div>
        <div class="q-pagination" id="overlay-q-pagination"></div>
    </div>

</div><!-- /#view-overlay -->

<?php
/*
 * ── JS integration notes ────────────────────────────────────────────────────
 *
 * The following Hub methods must be implemented in the Forge Hub JS alongside
 * the existing translation methods. Pattern mirrors Hub.loadTranslationMeta().
 *
 * Hub.overlayModeChange()
 *   Toggles #overlay-cinemagic-fields / #overlay-sketch-fields visibility
 *   based on the selected radio, then calls Hub.updateJson('overlay').
 *
 * Hub.overlayLoadSequences()
 *   Called on cinemagic select change. Fetches sequences for the picked
 *   cinemagic (or all sequences if value=0) and repopulates
 *   #overlay_sequence_id, then calls Hub.updateJson('overlay').
 *
 * Hub.updateJson('overlay')  — builds the payload preview:
 *
 *   const mode     = document.querySelector('input[name="overlay_mode"]:checked').value;
 *   const rerun    = document.getElementById('overlay_rerun').checked;
 *   let payload    = { rerun };
 *
 *   if (mode === 'sketch') {
 *     const sid = parseInt(document.getElementById('overlay_sketch_id').value) || 0;
 *     if (sid > 0) payload.sketch_id = sid;
 *   } else {
 *     const cinId = parseInt(document.getElementById('overlay_cinemagic_id').value) || 0;
 *     const seqId = parseInt(document.getElementById('overlay_sequence_id').value) || 0;
 *     if (seqId > 0)      payload.sequence_id  = seqId;
 *     else if (cinId > 0) payload.cinemagic_id = cinId;
 *     // cinId=0 + seqId=0 means "all sequences" — handled by CLI queue expansion
 *   }
 *
 *   document.getElementById('overlay_json_out').textContent =
 *     JSON.stringify(payload, null, 2);
 *
 * Hub.loadOverlayMeta()  — called on view activation, fetches:
 *   - /api/forge/cinemagics  → populates #overlay_cinemagic_id
 *   - /api/forge/sequences   → populates #overlay_sequence_id (initial full list)
 *
 * Queue wiring uses the same Hub.refreshQueue('overlay') / Hub.switchPanel()
 * pattern as the translation view. job_type filter: 'overlay_compose'.
 * ────────────────────────────────────────────────────────────────────────── */
?>
