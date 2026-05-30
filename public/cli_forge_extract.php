<?php
// public/cli_forge_extract.php
?>
<div class="hub-view" id="view-extract">
    <div class="view-header">
        <div class="view-title-row">
            <i class="bi bi-file-earmark-text" style="color:var(--amber);font-size:18px;flex-shrink:0"></i>
            <div class="view-title">MD Curator — Extract</div>
        </div>
        <div class="view-tabs">
            <button class="vtab active" data-panel="extract-config" onclick="Hub.switchPanel('extract','config',this)">Configure</button>
            <button class="vtab" data-panel="extract-json" onclick="Hub.switchPanel('extract','json',this)">JSON Preview</button>
            <button class="vtab" data-panel="extract-queue" onclick="Hub.switchPanel('extract','queue',this)">Queue</button>
        </div>
    </div>

    <div class="panel-scroll" id="extract-config">
        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l"><div class="section-num">1</div>
                    <div class="section-lbl"><strong>Scope</strong><span>Category, limit, chunk size, model override.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Documentation Category</div>
                    <select id="ext_targetCategoryId">
                        <option value="0">Process EVERYTHING</option>
                        <?php foreach ($docCats as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"><?= cfe($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="two-col">
                    <div class="field">
                        <div class="flabel">Limit</div>
                        <input type="number" id="ext_limit" min="1" step="1" value="10">
                    </div>
                    <div class="field">
                        <div class="flabel">Chars per chunk</div>
                        <input type="number" id="ext_charLimit" min="500" step="100" value="4000">
                    </div>
                </div>
                <div class="field">
                    <div class="flabel">Override model</div>
                    <input type="text" id="ext_overrideModel" placeholder="leave blank to use config default">
                </div>
            </div>
        </details>

        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l"><div class="section-num">2</div>
                    <div class="section-lbl"><strong>Fixed generator configs</strong><span>Curator + Showrunner configs are fixed.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body" id="ext_fixed_chips">
                <!-- populated by JS -->
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
                    <input type="text" id="ext_label" placeholder="MD Extract — all docs">
                </div>
                <div class="field">
                    <div class="flabel">Priority (1 = highest, 99 = lowest)</div>
                    <input type="number" id="ext_priority" min="1" max="99" value="50">
                </div>
            </div>
        </details>
    </div>

    <div class="panel-scroll" id="extract-json" style="display:none">
        <pre class="json-pre" id="ext_json_out"></pre>
        <div class="fhint" style="margin-top:8px">CLI runner: <code>php public/cli_md_curator_extract_json.php --config=&lt;file.json&gt;</code></div>
    </div>

    <div id="extract-queue" style="display:none;flex:1;flex-direction:column;overflow:hidden">
        <div class="queue-header"><div class="q-count-pills" id="extract-q-counts"></div><button class="icon-btn" style="width:32px;height:32px;font-size:13px" onclick="Hub.refreshQueue('extract')"><i class="bi bi-arrow-clockwise"></i></button></div>
        <div class="queue-table-wrap"><table class="queue-table"><thead><tr><th>ID</th><th>Label</th><th>Status</th><th>Priority</th><th>Created</th><th>Actions</th></tr></thead><tbody id="extract-q-tbody"></tbody></table></div>
        <div class="q-pagination" id="extract-q-pagination"></div>
    </div>
</div>

