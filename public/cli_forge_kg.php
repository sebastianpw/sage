<?php
// public/cli_forge_kg.php
?>
<div class="hub-view active" id="view-kg">
    <div class="view-header">
        <div class="view-title-row">
            <i class="bi bi-diagram-3" style="color:var(--amber);font-size:18px;flex-shrink:0"></i>
            <div class="view-title">KG → Sketch Generator</div>
        </div>
        <div class="view-tabs">
            <button class="vtab active" data-panel="kg-config" onclick="Hub.switchPanel('kg','config',this)">Configure</button>
            <button class="vtab" data-panel="kg-json" onclick="Hub.switchPanel('kg','json',this)">JSON Preview</button>
            <button class="vtab" data-panel="kg-queue" onclick="Hub.switchPanel('kg','queue',this)">Queue</button>
        </div>
    </div>

    <!-- Configure panel -->
    <div class="panel-scroll" id="kg-config">
        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l">
                    <div class="section-num">1</div>
                    <div class="section-lbl"><strong>Mode and Scope</strong><span>Category-based or global node type.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Mode</div>
                    <div class="pill-row">
                        <label class="pill"><input type="radio" name="kg_mode" value="1" checked> By Category</label>
                        <label class="pill"><input type="radio" name="kg_mode" value="2"> By Node Type</label>
                    </div>
                </div>
                <div class="field" id="kg-cat-block">
                    <div class="flabel">Knowledge Graph Category</div>
                    <select id="kg_category_id">
                        <option value="0">-- ALL KG CATEGORIES --</option>
                        <?php foreach ($kgCats as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"><?= cfe($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <div class="flabel">Node Type</div>
                    <select id="kg_node_type"></select>
                    <div class="fhint" id="kg_type_info">Node types load after selecting scope.</div>
                </div>
            </div>
        </details>

        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l">
                    <div class="section-num">2</div>
                    <div class="section-lbl"><strong>Filter and Range</strong><span>History filter, offset, amount, delay.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="two-col">
                    <div class="field">
                        <div class="flabel">History Filter</div>
                        <select id="kg_history_filter">
                            <option value="new">new</option>
                            <option value="all">all</option>
                            <option value="hist">hist</option>
                        </select>
                    </div>
                    <div class="field">
                        <div class="flabel">Offset</div>
                        <input type="number" id="kg_offset" min="0" step="1" value="0">
                    </div>
                </div>
                <div class="field">
                    <div class="flabel">Amount</div>
                    <input type="text" id="kg_amount" placeholder="blank = all remaining">
                </div>
                <div class="field">
                    <div class="flabel">Delay (microseconds)</div>
                    <input type="number" id="kg_delay_us" min="0" step="1000" value="500000">
                </div>
                <div class="field">
                    <div class="pill-row">
                        <label class="pill"><input type="checkbox" id="kg_confirm"> confirm=true</label>
                        <label class="pill"><input type="checkbox" id="kg_dry_run"> Dry run</label>
                    </div>
                </div>
            </div>
        </details>

        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l">
                    <div class="section-num">3</div>
                    <div class="section-lbl"><strong>Generator and Tags</strong><span>Select config and attach tags.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Generator Config</div>
                    <select id="kg_generator_config_id">
                        <?php foreach ($genConfig as $gc): ?>
                            <option value="<?= cfe($gc['config_id']) ?>"><?= cfe($gc['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <div class="flabel">Tags</div>
                    <input type="text" id="kg_tags" placeholder="sketch, lore, character">
                    <div class="fhint">Comma-separated. Linked to generated sketches.</div>
                </div>
            </div>
        </details>

        <details class="forge-section">
            <summary class="section-head">
                <div class="section-head-l">
                    <div class="section-num">4</div>
                    <div class="section-lbl"><strong>Job Label and Priority</strong><span>Name this job and set scheduler priority.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Job Label</div>
                    <input type="text" id="kg_label" placeholder="KG Sketch — characters batch">
                </div>
                <div class="field">
                    <div class="flabel">Priority (1 = highest, 99 = lowest)</div>
                    <input type="number" id="kg_priority" min="1" max="99" value="50">
                </div>
            </div>
        </details>
    </div>

    <!-- JSON Preview panel -->
    <div class="panel-scroll" id="kg-json" style="display:none">
        <pre class="json-pre" id="kg_json_out"></pre>
        <div class="fhint" style="margin-top:8px">CLI runner: <code>php public/cli_kg_to_sketch_generator_json.php --config=&lt;file.json&gt;</code></div>
    </div>

    <!-- Queue panel -->
    <div id="kg-queue" style="display:none; flex:1; flex-direction:column; overflow:hidden">
        <div class="queue-header" id="kg-queue-header">
            <div class="q-count-pills" id="kg-q-counts"></div>
            <button class="icon-btn" style="width:32px;height:32px;font-size:13px" onclick="Hub.refreshQueue('kg')" title="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
        </div>
        <div class="queue-table-wrap"><table class="queue-table"><thead><tr><th>ID</th><th>Label</th><th>Status</th><th>Priority</th><th>Created</th><th>Actions</th></tr></thead><tbody id="kg-q-tbody"></tbody></table></div>
        <div class="q-pagination" id="kg-q-pagination"></div>
    </div>
</div>

