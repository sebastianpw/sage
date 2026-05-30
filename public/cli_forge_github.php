<?php
// public/cli_forge_github.php
?>
<div class="hub-view" id="view-github">
    <div class="view-header">
        <div class="view-title-row">
            <i class="bi bi-github" style="color:var(--amber);font-size:18px;flex-shrink:0"></i>
            <div class="view-title">GitHub Sync</div>
        </div>
        <div class="view-tabs">
            <button class="vtab active" data-panel="github-config" onclick="Hub.switchPanel('github','config',this)">Configure</button>
            <button class="vtab" data-panel="github-json" onclick="Hub.switchPanel('github','json',this)">JSON Preview</button>
            <button class="vtab" data-panel="github-queue" onclick="Hub.switchPanel('github','queue',this)">Queue</button>
        </div>
    </div>

    <div class="panel-scroll" id="github-config">
        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l">
                    <div class="section-num">1</div>
                    <div class="section-lbl"><strong>Repository and Branch</strong><span>Target repo, branch, and remote.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Repository Path</div>
                    <input type="text" id="github_repo_path" value="<?= cfe($projectPath) ?>" placeholder="/path/to/repo">
                </div>
                <div class="two-col">
                    <div class="field">
                        <div class="flabel">Branch Name</div>
                        <input type="text" id="github_branch_name" value="main" placeholder="main">
                    </div>
                    <div class="field">
                        <div class="flabel">Remote Name</div>
                        <input type="text" id="github_remote_name" value="origin" placeholder="origin">
                    </div>
                </div>
                <div class="field">
                    <div class="flabel">Commit Message</div>
                    <input type="text" id="github_commit_message" value="Auto commit from PHP" placeholder="Auto commit from PHP">
                </div>
            </div>
        </details>

        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l">
                    <div class="section-num">2</div>
                    <div class="section-lbl"><strong>Actions</strong><span>Choose which Git operations to run.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="pill-row">
                    <label class="pill"><input type="checkbox" id="github_add_all" checked> git add -A</label>
                    <label class="pill"><input type="checkbox" id="github_commit" checked> git commit</label>
                    <label class="pill"><input type="checkbox" id="github_push" checked> git push</label>
                    <label class="pill"><input type="checkbox" id="github_pull_rebase"> pull --rebase first</label>
                    <label class="pill"><input type="checkbox" id="github_amend"> --amend</label>
                    <label class="pill"><input type="checkbox" id="github_allow_empty"> --allow-empty</label>
                    <label class="pill"><input type="checkbox" id="github_force_push"> --force-with-lease</label>
                    <label class="pill"><input type="checkbox" id="github_dry_run"> Dry run</label>
                </div>
            </div>
        </details>

        <details class="forge-section" open>
            <summary class="section-head">
                <div class="section-head-l">
                    <div class="section-num">3</div>
                    <div class="section-lbl"><strong>Git Identity</strong><span>Commit author identity for non-interactive runs.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="two-col">
                    <div class="field">
                        <div class="flabel">Author Name</div>
                        <input type="text" id="github_git_user_name" value="<?= cfe(getenv('GIT_BOT_NAME') ?: 'Post Bot') ?>" placeholder="Post Bot">
                    </div>
                    <div class="field">
                        <div class="flabel">Author Email</div>
                        <input type="text" id="github_git_user_email" value="<?= cfe(getenv('GIT_BOT_EMAIL') ?: 'post-bot@example.invalid') ?>" placeholder="post-bot@example.invalid">
                    </div>
                </div>
            </div>
        </details>

        <details class="forge-section">
            <summary class="section-head">
                <div class="section-head-l">
                    <div class="section-num">4</div>
                    <div class="section-lbl"><strong>Job Label and Priority</strong><span>Name and prioritize this queue entry.</span></div>
                </div>
                <i class="bi bi-chevron-down" style="color:var(--dim);font-size:15px;flex-shrink:0"></i>
            </summary>
            <div class="section-body">
                <div class="field">
                    <div class="flabel">Job Label</div>
                    <input type="text" id="github_label" placeholder="GitHub Sync — auto commit">
                </div>
                <div class="field">
                    <div class="flabel">Priority (1 = highest, 99 = lowest)</div>
                    <input type="number" id="github_priority" min="1" max="99" value="50">
                </div>
            </div>
        </details>
    </div>

    <div class="panel-scroll" id="github-json" style="display:none">
        <pre class="json-pre" id="github_json_out"></pre>
        <div class="fhint" style="margin-top:8px">CLI runner: <code>php public/cli_github_sync.php --repo=&lt;path&gt; --branch=&lt;name&gt;</code></div>
    </div>

    <div id="github-queue" style="display:none;flex:1;flex-direction:column;overflow:hidden">
        <div class="queue-header">
            <div class="q-count-pills" id="github-q-counts"></div>
            <button class="icon-btn" style="width:32px;height:32px;font-size:13px" onclick="Hub.refreshQueue('github')" title="Refresh">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="queue-table-wrap">
            <table class="queue-table">
                <thead>
                    <tr><th>ID</th><th>Label</th><th>Status</th><th>Priority</th><th>Created</th><th>Actions</th></tr>
                </thead>
                <tbody id="github-q-tbody"></tbody>
            </table>
        </div>
        <div class="q-pagination" id="github-q-pagination"></div>
    </div>
</div>

