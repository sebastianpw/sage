<?php
// public/cli_forge_narseq.php
?>
<div class="hub-view" id="view-narseq">
    <div class="view-header">
        <div class="view-title-row">
            <i class="bi bi-collection-play" style="color:var(--amber);font-size:18px;flex-shrink:0"></i>
            <div class="view-title">Narrative Sequence Composer</div>
        </div>
        <div class="view-tabs">
            <button class="vtab active" data-panel="narseq-config" onclick="Hub.switchPanel('narseq','config',this)">Configure</button>
            <button class="vtab" data-panel="narseq-json" onclick="Hub.switchPanel('narseq','json',this)">JSON Preview</button>
            <button class="vtab" data-panel="narseq-queue" onclick="Hub.switchPanel('narseq','queue',this)">Queue</button>
        </div>
    </div>

    <div class="panel-scroll" id="narseq-config">
        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l"><div class="section-num">1</div>
                    <div class="section-lbl"><strong>Sequence</strong><span>Select the narrative sequence to compose.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Narrative Sequence</div>
                    <select id="narseq_sequence_id">
                        <option value="0">— loading sequences… —</option>
                    </select>
                    <div class="fhint">Sequences are ordered most-recent first.</div>
                </div>
            </div>
        </details>

        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l"><div class="section-num">2</div>
                    <div class="section-lbl"><strong>Run Options</strong><span>Rerun flag and optional model override.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="pill-row">
                        <label class="pill"><input type="checkbox" id="narseq_rerun"> --rerun (force regenerate all beats)</label>
                    </div>
                </div>
                <div class="field">
                    <div class="flabel">Override model</div>
                    <input type="text" id="narseq_override_model" placeholder="leave blank to use config defaults">
                    <div class="fhint">e.g. claude-sonnet-4-6 — overrides all three generator configs.</div>
                </div>
            </div>
        </details>

        <details class="forge-section">
            <summary class="section-head">
                <div class="section-head-l"><div class="section-num">3</div>
                    <div class="section-lbl"><strong>Job Label and Priority</strong></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Job Label</div>
                    <input type="text" id="narseq_label" placeholder="Seq Composer — sequence name">
                </div>
                <div class="field">
                    <div class="flabel">Priority (1 = highest, 99 = lowest)</div>
                    <input type="number" id="narseq_priority" min="1" max="99" value="50">
                </div>
            </div>
        </details>
    </div>

    <div class="panel-scroll" id="narseq-json" style="display:none">
        <pre class="json-pre" id="narseq_json_out"></pre>
        <div class="fhint" style="margin-top:8px">CLI runner: <code>php public/cli_narrative_sequence_compose.php --seq=&lt;id&gt;</code></div>
    </div>

    <div id="narseq-queue" style="display:none;flex:1;flex-direction:column;overflow:hidden">
        <div class="queue-header"><div class="q-count-pills" id="narseq-q-counts"></div><button class="icon-btn" style="width:32px;height:32px;font-size:13px" onclick="Hub.refreshQueue('narseq')"><i class="bi bi-arrow-clockwise"></i></button></div>
        <div class="queue-table-wrap"><table class="queue-table"><thead><tr><th>ID</th><th>Label</th><th>Status</th><th>Priority</th><th>Created</th><th>Actions</th></tr></thead><tbody id="narseq-q-tbody"></tbody></table></div>
        <div class="q-pagination" id="narseq-q-pagination"></div>
    </div>
</div>

