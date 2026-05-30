<?php
// public/cli_forge_translation.php
// Translation Composer sub-view for CLI Forge Hub.
// Paired with cli_translation_compose.php
// job_type: translation_compose
// Payload keys: cinemagic_id (int|null), sequence_id (int|null), lang (string), rerun (bool)
?>
<div class="hub-view" id="view-translation">
    <div class="view-header">
        <div class="view-title-row">
            <i class="bi bi-translate" style="color:var(--amber);font-size:18px;flex-shrink:0"></i>
            <div class="view-title">Translation Composer</div>
        </div>
        <div class="view-tabs">
            <button class="vtab active" data-panel="translation-config" onclick="Hub.switchPanel('translation','config',this)">Configure</button>
            <button class="vtab" data-panel="translation-json" onclick="Hub.switchPanel('translation','json',this)">JSON Preview</button>
            <button class="vtab" data-panel="translation-queue" onclick="Hub.switchPanel('translation','queue',this)">Queue</button>
        </div>
    </div>

    <!-- ── CONFIG PANEL ── -->
    <div class="panel-scroll" id="translation-config">

        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l">
                    <div class="section-num">1</div>
                    <div class="section-lbl">
                        <strong>Cinemagic (Magazine)</strong>
                        <span>Only cinemagic-linked sequences are eligible for translation.</span>
                    </div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Cinemagic</div>
                    <select id="translation_cinemagic_id" onchange="Hub.loadTranslationMeta ? Hub.refreshMeta('translation') : null">
                        <option value="0">— loading... —</option>
                    </select>
                    <div class="fhint">Select a cinemagic to filter its sequences below, or leave on "All" to see every linked sequence.</div>
                </div>
            </div>
        </details>

        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l">
                    <div class="section-num">2</div>
                    <div class="section-lbl">
                        <strong>Sequence</strong>
                        <span>One sequence, or leave at "All" to process every sequence in the selected cinemagic.</span>
                    </div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Narrative Sequence</div>
                    <select id="translation_sequence_id" oninput="Hub.updateJson('translation')">
                        <option value="0">— All sequences in cinemagic —</option>
                    </select>
                    <div class="fhint">Selecting "All" will queue one job per sequence. Selecting a specific sequence queues a single job.</div>
                </div>
            </div>
        </details>

        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l">
                    <div class="section-num">3</div>
                    <div class="section-lbl">
                        <strong>Target Language</strong>
                        <span>Must exist in system_languages with is_main = 0.</span>
                    </div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Language</div>
                    <select id="translation_lang" oninput="Hub.updateJson('translation')">
                        <option value="">— loading... —</option>
                    </select>
                    <div class="fhint">Only non-main languages from system_languages are shown.</div>
                </div>
            </div>
        </details>

        <details class="forge-section">
            <summary class="section-head">
                <div class="section-head-l">
                    <div class="section-num">4</div>
                    <div class="section-lbl">
                        <strong>Run Options</strong>
                        <span>Force re-translate or skip already-translated items.</span>
                    </div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="pill-row">
                        <label class="pill">
                            <input type="checkbox" id="translation_rerun" onchange="Hub.updateJson('translation')">
                            Force re-translate (rerun)
                        </label>
                    </div>
                    <div class="fhint">When unchecked, already-translated sketch and sequence overlay rows are skipped.</div>
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
                    <input type="text" id="translation_label" placeholder="Translation — seq #96 → pt">
                </div>
                <div class="field">
                    <div class="flabel">Priority (1 = highest, 99 = lowest)</div>
                    <input type="number" id="translation_priority" min="1" max="99" value="50">
                </div>
            </div>
        </details>

    </div><!-- /#translation-config -->

    <!-- ── JSON PREVIEW PANEL ── -->
    <div class="panel-scroll" id="translation-json" style="display:none">
        <pre class="json-pre" id="translation_json_out"></pre>
        <div class="fhint" style="margin-top:8px">
            CLI runner:
            <code>php public/cli_translation_compose.php --seq=&lt;id&gt; --lang=&lt;code&gt;</code>
            &nbsp;|&nbsp;
            <code>php public/cli_translation_compose.php --cinemagic=&lt;id&gt; --lang=&lt;code&gt;</code>
        </div>
    </div>

    <!-- ── QUEUE PANEL ── -->
    <div id="translation-queue" style="display:none;flex:1;flex-direction:column;overflow:hidden">
        <div class="queue-header">
            <div class="q-count-pills" id="translation-q-counts"></div>
            <button class="icon-btn" style="width:32px;height:32px;font-size:13px" onclick="Hub.refreshQueue('translation')">
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
                <tbody id="translation-q-tbody"></tbody>
            </table>
        </div>
        <div class="q-pagination" id="translation-q-pagination"></div>
    </div>

</div><!-- /#view-translation -->
