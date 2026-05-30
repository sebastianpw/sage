<?php
// public/cli_forge_tagextract.php
?>
<div class="hub-view" id="view-tagextract">
    <div class="view-header">
        <div class="view-title-row">
            <i class="bi bi-tags" style="color:var(--amber);font-size:18px;flex-shrink:0"></i>
            <div class="view-title">Sketch Tag Extractor</div>
        </div>
        <div class="view-tabs">
            <button class="vtab active" data-panel="tagextract-config" onclick="Hub.switchPanel('tagextract','config',this)">Configure</button>
            <button class="vtab" data-panel="tagextract-json" onclick="Hub.switchPanel('tagextract','json',this)">JSON Preview</button>
            <button class="vtab" data-panel="tagextract-queue" onclick="Hub.switchPanel('tagextract','queue',this)">Queue</button>
        </div>
    </div>

    <div class="panel-scroll" id="tagextract-config">
        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l"><div class="section-num">1</div>
                    <div class="section-lbl"><strong>Target Range</strong><span>Select sketch IDs to process.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="two-col">
                    <div class="field">
                        <div class="flabel">From Sketch ID</div>
                        <input type="number" id="tagextract_from" min="1" step="1" value="1">
                    </div>
                    <div class="field">
                        <div class="flabel">To Sketch ID</div>
                        <input type="number" id="tagextract_to" min="1" step="1" value="1000">
                    </div>
                </div>
                <div class="fhint" id="tagextract_range_hint" style="margin-top:8px">DB Sketch Range: loading...</div>
            </div>
        </details>

        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l"><div class="section-num">2</div>
                    <div class="section-lbl"><strong>Run Options</strong><span>Batch size and dry run.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Batch Size</div>
                    <input type="number" id="tagextract_batch" min="1" step="1" value="5">
                </div>
                <div class="field">
                    <div class="pill-row">
                        <label class="pill"><input type="checkbox" id="tagextract_dry_run"> Dry run</label>
                    </div>
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
                    <input type="text" id="tagextract_label" placeholder="Tag Extract — #1000 to #2000">
                </div>
                <div class="field">
                    <div class="flabel">Priority (1 = highest, 99 = lowest)</div>
                    <input type="number" id="tagextract_priority" min="1" max="99" value="50">
                </div>
            </div>
        </details>
    </div>

    <div class="panel-scroll" id="tagextract-json" style="display:none">
        <pre class="json-pre" id="tagextract_json_out"></pre>
        <div class="fhint" style="margin-top:8px">CLI runner: <code>php public/cli_sketch_tag_extractor.php --from=&lt;id&gt; --to=&lt;id&gt;</code></div>
    </div>

    <div id="tagextract-queue" style="display:none;flex:1;flex-direction:column;overflow:hidden">
        <div class="queue-header"><div class="q-count-pills" id="tagextract-q-counts"></div><button class="icon-btn" style="width:32px;height:32px;font-size:13px" onclick="Hub.refreshQueue('tagextract')"><i class="bi bi-arrow-clockwise"></i></button></div>
        <div class="queue-table-wrap"><table class="queue-table"><thead><tr><th>ID</th><th>Label</th><th>Status</th><th>Priority</th><th>Created</th><th>Actions</th></tr></thead><tbody id="tagextract-q-tbody"></tbody></table></div>
        <div class="q-pagination" id="tagextract-q-pagination"></div>
    </div>
</div>

