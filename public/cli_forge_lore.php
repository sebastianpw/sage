<?php
// public/cli_forge_lore.php
?>
<div class="hub-view" id="view-lore">
    <div class="view-header">
        <div class="view-title-row">
            <i class="bi bi-diagram-2" style="color:var(--amber);font-size:18px;flex-shrink:0"></i>
            <div class="view-title">Lore → Sketch Generator</div>
        </div>
        <div class="view-tabs">
            <button class="vtab active" data-panel="lore-config" onclick="Hub.switchPanel('lore','config',this)">Configure</button>
            <button class="vtab" data-panel="lore-json" onclick="Hub.switchPanel('lore','json',this)">JSON Preview</button>
            <button class="vtab" data-panel="lore-queue" onclick="Hub.switchPanel('lore','queue',this)">Queue</button>
        </div>
    </div>

    <div class="panel-scroll" id="lore-config">
        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l"><div class="section-num">1</div>
                    <div class="section-lbl"><strong>Document</strong><span>Choose the lore document for context.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Lore Document</div>
                    <select id="lore_doc_id">
                        <?php foreach ($loreDocs as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= cfe($d['name']) ?><?= $d['cat_name'] ? ' — '.cfe($d['cat_name']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <div class="flabel">Document Keywords</div>
                    <div class="meta-chip" id="lore_kw_box" style="display:block;padding:8px 10px;border-radius:var(--r-sm);font-size:11px">—</div>
                    <div class="fhint">Runner falls back to these keywords if tags are blank.</div>
                </div>
            </div>
        </details>

        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l"><div class="section-num">2</div>
                    <div class="section-lbl"><strong>Entity Group and Range</strong><span>Select entity family, offset, and amount.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Entity Group</div>
                    <select id="lore_group_key"></select>
                </div>
                <div class="two-col">
                    <div class="field">
                        <div class="flabel">Offset</div>
                        <input type="number" id="lore_offset" min="0" step="1" value="0">
                    </div>
                    <div class="field">
                        <div class="flabel">Amount</div>
                        <input type="text" id="lore_amount" placeholder="blank = all">
                    </div>
                </div>
                <div class="field">
                    <div class="flabel">Delay (microseconds)</div>
                    <input type="number" id="lore_delay_us" min="0" step="1000" value="500000">
                </div>
                <div class="field">
                    <div class="pill-row">
                        <label class="pill"><input type="checkbox" id="lore_confirm"> confirm=true</label>
                        <label class="pill"><input type="checkbox" id="lore_dry_run"> Dry run</label>
                    </div>
                </div>
            </div>
        </details>

        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l"><div class="section-num">3</div>
                    <div class="section-lbl"><strong>Generator and Tags</strong><span>Config and batch tags.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Generator Config</div>
                    <select id="lore_generator_config_id">
                        <?php foreach ($genConfig as $gc): ?>
                            <option value="<?= cfe($gc['config_id']) ?>"><?= cfe($gc['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <div class="flabel">Tags</div>
                    <input type="text" id="lore_tags" placeholder="comma-separated tags">
                    <div class="fhint">Leave blank to use document keywords automatically.</div>
                </div>
            </div>
        </details>

        <details class="forge-section">
            <summary class="section-head">
                <div class="section-head-l"><div class="section-num">4</div>
                    <div class="section-lbl"><strong>Job Label and Priority</strong><span>Name and prioritise this queue entry.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Job Label</div>
                    <input type="text" id="lore_label" placeholder="Lore Sketch — characters">
                </div>
                <div class="field">
                    <div class="flabel">Priority (1 = highest, 99 = lowest)</div>
                    <input type="number" id="lore_priority" min="1" max="99" value="50">
                </div>
            </div>
        </details>
    </div>

    <div class="panel-scroll" id="lore-json" style="display:none">
        <pre class="json-pre" id="lore_json_out"></pre>
        <div class="fhint" style="margin-top:8px">CLI runner: <code>php public/cli_lore_to_sketch_generator_json.php --config=&lt;file.json&gt;</code></div>
    </div>

    <div id="lore-queue" style="display:none;flex:1;flex-direction:column;overflow:hidden">
        <div class="queue-header"><div class="q-count-pills" id="lore-q-counts"></div><button class="icon-btn" style="width:32px;height:32px;font-size:13px" onclick="Hub.refreshQueue('lore')"><i class="bi bi-arrow-clockwise"></i></button></div>
        <div class="queue-table-wrap"><table class="queue-table"><thead><tr><th>ID</th><th>Label</th><th>Status</th><th>Priority</th><th>Created</th><th>Actions</th></tr></thead><tbody id="lore-q-tbody"></tbody></table></div>
        <div class="q-pagination" id="lore-q-pagination"></div>
    </div>
</div>

