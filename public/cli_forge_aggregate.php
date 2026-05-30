<?php
// public/cli_forge_aggregate.php
?>
<div class="hub-view" id="view-aggregate">
    <div class="view-header">
        <div class="view-title-row">
            <i class="bi bi-journal-text" style="color:var(--amber);font-size:18px;flex-shrink:0"></i>
            <div class="view-title">MD Curator — Aggregate</div>
        </div>
        <div class="view-tabs">
            <button class="vtab active" data-panel="aggregate-config" onclick="Hub.switchPanel('aggregate','config',this)">Configure</button>
            <button class="vtab" data-panel="aggregate-json" onclick="Hub.switchPanel('aggregate','json',this)">JSON Preview</button>
            <button class="vtab" data-panel="aggregate-queue" onclick="Hub.switchPanel('aggregate','queue',this)">Queue</button>
        </div>
    </div>

    <div class="panel-scroll" id="aggregate-config">
        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l"><div class="section-num">1</div>
                    <div class="section-lbl"><strong>Scope</strong><span>Category and document limit.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Documentation Category</div>
                    <select id="agg_targetCategoryId">
                        <option value="0">Process EVERYTHING</option>
                        <?php foreach ($docCats as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"><?= cfe($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <div class="flabel">Limit</div>
                    <input type="number" id="agg_limit" min="1" step="1" value="100">
                </div>
            </div>
        </details>

        <details class="forge-section">
            <summary class="section-head">
                <div class="section-head-l"><div class="section-num">2</div>
                    <div class="section-lbl"><strong>Job Label and Priority</strong></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Job Label</div>
                    <input type="text" id="agg_label" placeholder="MD Aggregate — all docs">
                </div>
                <div class="field">
                    <div class="flabel">Priority (1 = highest, 99 = lowest)</div>
                    <input type="number" id="agg_priority" min="1" max="99" value="50">
                </div>
            </div>
        </details>
    </div>

    <div class="panel-scroll" id="aggregate-json" style="display:none">
        <pre class="json-pre" id="agg_json_out"></pre>
        <div class="fhint" style="margin-top:8px">CLI runner: <code>php public/cli_md_curator_aggregate_json.php --config=&lt;file.json&gt;</code></div>
    </div>

    <div id="aggregate-queue" style="display:none;flex:1;flex-direction:column;overflow:hidden">
        <div class="queue-header"><div class="q-count-pills" id="aggregate-q-counts"></div><button class="icon-btn" style="width:32px;height:32px;font-size:13px" onclick="Hub.refreshQueue('aggregate')"><i class="bi bi-arrow-clockwise"></i></button></div>
        <div class="queue-table-wrap"><table class="queue-table"><thead><tr><th>ID</th><th>Label</th><th>Status</th><th>Priority</th><th>Created</th><th>Actions</th></tr></thead><tbody id="aggregate-q-tbody"></tbody></table></div>
        <div class="q-pagination" id="aggregate-q-pagination"></div>
    </div>
</div>

