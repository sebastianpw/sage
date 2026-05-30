<?php
// public/cli_forge_autopilot.php
?>
<div class="hub-view" id="view-autopilot">
    <div class="view-header">
        <div class="view-title-row">
            <i class="bi bi-rocket-takeoff" style="color:var(--amber);font-size:18px;flex-shrink:0"></i>
            <div class="view-title">Sketch Autopilot</div>
        </div>
        <div class="view-tabs">
            <button class="vtab active" data-panel="autopilot-config" onclick="Hub.switchPanel('autopilot','config',this)">Configure</button>
            <button class="vtab" data-panel="autopilot-json" onclick="Hub.switchPanel('autopilot','json',this)">JSON Preview</button>
            <button class="vtab" data-panel="autopilot-queue" onclick="Hub.switchPanel('autopilot','queue',this)">Queue</button>
        </div>
    </div>

    <div class="panel-scroll" id="autopilot-config">
        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l"><div class="section-num">1</div>
                    <div class="section-lbl"><strong>Generator and Run Count</strong><span>rapidcreate generator and number of sketches.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Description Generator</div>
                    <select id="ap_desc_gen_id"></select>
                </div>
                <div class="field">
                    <div class="flabel">Amount (0 = infinite)</div>
                    <input type="number" id="ap_amount" min="0" step="1" value="0">
                </div>
            </div>
        </details>

        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l"><div class="section-num">2</div>
                    <div class="section-lbl"><strong>Ingredient Probabilities</strong><span>Chance (%) for each ingredient family.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="ingr-grid" id="ap_ingr_grid">
                    <!-- populated by JS -->
                </div>
            </div>
        </details>

        <details class="forge-section">
            <summary class="section-head">
                <div class="section-head-l"><div class="section-num">3</div>
                    <div class="section-lbl"><strong>Job Label and Priority</strong><span>Name and prioritise this queue entry.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Job Label</div>
                    <input type="text" id="ap_label" placeholder="Autopilot — 10 sketches">
                </div>
                <div class="field">
                    <div class="flabel">Priority (1 = highest, 99 = lowest)</div>
                    <input type="number" id="ap_priority" min="1" max="99" value="50">
                </div>
            </div>
        </details>
    </div>

    <div class="panel-scroll" id="autopilot-json" style="display:none">
        <pre class="json-pre" id="ap_json_out"></pre>
        <div class="fhint" style="margin-top:8px">CLI runner: <code>php public/cli_autopilot_json.php --config=&lt;file.json&gt;</code></div>
    </div>

    <div id="autopilot-queue" style="display:none;flex:1;flex-direction:column;overflow:hidden">
        <div class="queue-header"><div class="q-count-pills" id="autopilot-q-counts"></div><button class="icon-btn" style="width:32px;height:32px;font-size:13px" onclick="Hub.refreshQueue('autopilot')"><i class="bi bi-arrow-clockwise"></i></button></div>
        <div class="queue-table-wrap"><table class="queue-table"><thead><tr><th>ID</th><th>Label</th><th>Status</th><th>Priority</th><th>Created</th><th>Actions</th></tr></thead><tbody id="autopilot-q-tbody"></tbody></table></div>
        <div class="q-pagination" id="autopilot-q-pagination"></div>
    </div>
</div>

