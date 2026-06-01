<?php
/**
 * SAGE Mail Hub — Main Interface
 * public/mail_hub/index.php
 */

session_start();
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Mail Hub · SAGE AI</title>
<script>
(function(){
    try{var t=localStorage.getItem('spw_theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════
   FORGE DESIGN SYSTEM — Mail Hub
═══════════════════════════════════════════════════════════ */
:root {
    --bg:          #080b10;
    --surface:     #0e1319;
    --card:        #111820;
    --card-hover:  #141e28;
    --border:      #1c2535;
    --border-glow: #2a3a52;
    --text:        #c8d4e8;
    --text-dim:    #5a6a80;
    --text-bright: #e8f0ff;
    --amber:       #f5a623;
    --amber-dim:   rgba(245,166,35,0.10);
    --green:       #22d3a0;
    --green-dim:   rgba(34,211,160,0.10);
    --red:         #f05060;
    --red-dim:     rgba(240,80,96,0.10);
    --blue:        #4da6ff;
    --blue-dim:    rgba(77,166,255,0.10);
    --purple:      #c084fc;
    --purple-dim:  rgba(192,132,252,0.10);
    --mono:        'Space Mono', monospace;
    --sans:        'Syne', system-ui, sans-serif;
    --radius:      6px;
}
html[data-theme="light"],:root[data-theme="light"] {
    --bg:#f6f8fa; --surface:#e1e4e8; --card:#ffffff; --card-hover:#f0f2f5;
    --border:#d1d5db; --border-glow:#9ca3af; --text:#111827; --text-dim:#6b7280;
    --text-bright:#000000; --amber:#d97706; --amber-dim:rgba(217,119,6,.09);
    --green:#059669; --green-dim:rgba(5,150,105,.09);
    --red:#dc2626; --red-dim:rgba(220,38,38,.09);
    --blue:#2563eb; --blue-dim:rgba(37,99,235,.09);
    --purple:#9333ea; --purple-dim:rgba(147,51,234,.09);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;background:var(--bg);color:var(--text);font-family:var(--sans);font-size:14px;line-height:1.5;}
::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:var(--border-glow);border-radius:4px;}

/* ── Layout ── */
.mh-layout{display:flex;flex-direction:column;height:100vh;overflow:hidden;}
.mh-header{display:flex;align-items:center;gap:12px;padding:10px 16px;background:var(--surface);border-bottom:1px solid var(--border);flex-shrink:0;flex-wrap:wrap;}
.mh-logo{display:flex;align-items:center;gap:8px;font-family:var(--mono);font-size:.85rem;font-weight:700;color:var(--amber);letter-spacing:1.5px;text-transform:uppercase;white-space:nowrap;}
.mh-logo i{font-size:1.1rem;}
.mh-tabs{display:flex;gap:2px;flex:1;flex-wrap:wrap;}
.tab-btn{padding:5px 12px;background:transparent;border:1px solid transparent;border-radius:var(--radius);cursor:pointer;font-family:var(--mono);font-size:.72rem;color:var(--text-dim);letter-spacing:.5px;transition:all .15s;display:flex;align-items:center;gap:6px;white-space:nowrap;}
.tab-btn:hover{color:var(--text);border-color:var(--border);}
.tab-btn.active{color:var(--amber);border-color:var(--amber);background:var(--amber-dim);}
.tab-btn i{font-size:.8rem;}
.mh-header-right{display:flex;gap:6px;align-items:center;margin-left:auto;}

.mh-body{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:14px;}

/* ── Views ── */
.view{display:none;flex-direction:column;gap:14px;}
.view.active{display:flex;}

/* ── Cards ── */
.forge-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
.forge-card-header{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--border);gap:8px;flex-wrap:wrap;}
.forge-card-title{font-family:var(--mono);font-size:.72rem;font-weight:700;color:var(--amber);text-transform:uppercase;letter-spacing:1px;display:flex;align-items:center;gap:7px;}
.forge-card-title i{font-size:.85rem;}
.forge-card-body{padding:14px;}

/* ── Stats row ── */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;}
@media(max-width:600px){.stats-row{grid-template-columns:repeat(2,1fr);}}
.stat-chip{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;display:flex;flex-direction:column;gap:4px;transition:border-color .15s;}
.stat-chip:hover{border-color:var(--border-glow);}
.stat-chip-label{font-family:var(--mono);font-size:.62rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);}
.stat-chip-value{font-family:var(--mono);font-size:1.5rem;font-weight:700;color:var(--text-bright);line-height:1;}
.stat-chip-sub{font-family:var(--mono);font-size:.65rem;color:var(--text-dim);}

/* ── Buttons ── */
.btn-primary{padding:6px 14px;background:var(--amber);color:#000;border:none;border-radius:var(--radius);cursor:pointer;font-family:var(--mono);font-size:.72rem;font-weight:700;letter-spacing:.5px;display:inline-flex;align-items:center;gap:6px;transition:all .15s;white-space:nowrap;}
.btn-primary:hover{filter:brightness(1.12);}
.btn-secondary{padding:5px 12px;background:transparent;color:var(--text-dim);border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;font-family:var(--mono);font-size:.72rem;display:inline-flex;align-items:center;gap:6px;transition:all .15s;white-space:nowrap;}
.btn-secondary:hover{border-color:var(--border-glow);color:var(--text);}
.btn-danger{padding:5px 12px;background:transparent;color:var(--red);border:1px solid var(--red-dim);border-radius:var(--radius);cursor:pointer;font-family:var(--mono);font-size:.72rem;display:inline-flex;align-items:center;gap:6px;transition:all .15s;}
.btn-danger:hover{background:var(--red-dim);}
.btn-icon{width:30px;height:30px;background:transparent;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-dim);transition:all .15s;font-size:.85rem;flex-shrink:0;}
.btn-icon:hover{border-color:var(--amber);color:var(--amber);}

/* ── Table ── */
.forge-table-wrap{overflow-x:auto;}
.forge-table{width:100%;border-collapse:collapse;font-family:var(--mono);font-size:.72rem;text-align:left;}
.forge-table th{padding:9px 12px;border-bottom:1px solid var(--border);color:var(--text-dim);font-weight:normal;text-transform:uppercase;letter-spacing:1px;background:var(--surface);white-space:nowrap;}
.forge-table td{padding:10px 12px;border-bottom:1px solid var(--border);color:var(--text);}
.forge-table tr:hover td{background:var(--card-hover);}
.forge-table tr:last-child td{border-bottom:none;}

/* ── Badges ── */
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:4px;font-family:var(--mono);font-size:.62rem;text-transform:uppercase;letter-spacing:.5px;border:1px solid;white-space:nowrap;}
.badge-draft     {color:var(--text-dim); border-color:var(--border);}
.badge-scheduled {color:var(--blue);     border-color:var(--blue);     background:var(--blue-dim);}
.badge-sending   {color:var(--amber);    border-color:var(--amber);    background:var(--amber-dim);}
.badge-sent      {color:var(--green);    border-color:var(--green);    background:var(--green-dim);}
.badge-cancelled,.badge-failed{color:var(--red);border-color:var(--red);background:var(--red-dim);}
.badge-active    {color:var(--green);    border-color:var(--green);    background:var(--green-dim);}
.badge-unsubscribed,.badge-bounced{color:var(--text-dim);border-color:var(--border);}
.badge-brevo     {color:var(--purple);   border-color:var(--purple);   background:var(--purple-dim);}
.badge-smtp      {color:var(--blue);     border-color:var(--blue);     background:var(--blue-dim);}

/* ── Filter bar ── */
.filter-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.filter-select,.filter-input{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:5px 10px;font-family:var(--mono);font-size:.72rem;color:var(--text);cursor:pointer;outline:none;transition:border-color .15s;}
.filter-select:focus,.filter-input:focus{border-color:var(--amber);}
.filter-input{min-width:160px;flex:1;}

/* ── Form / Modal ── */
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:1000;display:none;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;}
.modal-backdrop.open{display:flex;}
.modal-box{background:var(--surface);border:1px solid var(--border-glow);border-radius:var(--radius);width:100%;max-width:640px;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,.6);animation:fadeUp .2s ease;}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--border);}
.modal-title{font-family:var(--mono);font-size:.78rem;font-weight:700;color:var(--amber);text-transform:uppercase;letter-spacing:1px;}
.modal-body{padding:16px;display:flex;flex-direction:column;gap:12px;max-height:80vh;overflow-y:auto;}
.modal-footer{padding:10px 16px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;}

.form-group{display:flex;flex-direction:column;gap:5px;}
.form-label{font-family:var(--mono);font-size:.65rem;text-transform:uppercase;letter-spacing:.5px;color:var(--text-dim);}
.form-control{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:7px 10px;font-family:var(--sans);font-size:.85rem;color:var(--text-bright);width:100%;outline:none;resize:vertical;transition:border-color .15s;}
.form-control:focus{border-color:var(--amber);}
.form-control::placeholder{color:var(--text-dim);}
select.form-control{cursor:pointer;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:500px){.form-row{grid-template-columns:1fr;}}
.form-hint{font-family:var(--mono);font-size:.62rem;color:var(--text-dim);margin-top:2px;line-height:1.4;}

/* Rich-text body editor */
.body-editor-tabs{display:flex;gap:2px;margin-bottom:8px;}
.body-tab{padding:4px 10px;background:transparent;border:1px solid var(--border);border-radius:4px;cursor:pointer;font-family:var(--mono);font-size:.65rem;color:var(--text-dim);transition:all .15s;}
.body-tab.active{background:var(--amber-dim);color:var(--amber);border-color:var(--amber);}

/* ── Progress bar ── */
.progress-bar{height:6px;background:var(--border);border-radius:3px;overflow:hidden;margin-top:4px;}
.progress-fill{height:100%;background:var(--green);border-radius:3px;transition:width .4s ease;}

/* ── Empty / spinner ── */
.empty-state{text-align:center;padding:36px 20px;color:var(--text-dim);}
.empty-state i{font-size:2rem;margin-bottom:10px;display:block;}
.empty-state p{font-family:var(--mono);font-size:.75rem;}
.spinner{width:18px;height:18px;border:2px solid var(--border);border-top-color:var(--amber);border-radius:50%;animation:spin .7s linear infinite;display:inline-block;vertical-align:middle;}
@keyframes spin{to{transform:rotate(360deg);}}
.loading-row td{text-align:center;padding:36px;color:var(--text-dim);}

/* ── Toast ── */
.toast-wrap{position:fixed;bottom:20px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:8px;}
.toast{padding:9px 14px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);font-family:var(--mono);font-size:.75rem;color:var(--text);box-shadow:0 4px 20px rgba(0,0,0,.5);display:flex;align-items:center;gap:7px;animation:fadeUp .2s ease;cursor:pointer;max-width:300px;}
.toast.success{border-color:var(--green);}
.toast.error{border-color:var(--red);color:var(--red);}
.toast.out{animation:fadeDown .2s forwards;}
@keyframes fadeDown{to{opacity:0;transform:translateY(10px);}}

/* ── Inline action group ── */
.action-group{display:flex;gap:4px;align-items:center;}

/* ── Send progress badge ── */
.send-progress{display:flex;flex-direction:column;gap:2px;min-width:80px;}
.send-nums{font-family:var(--mono);font-size:.62rem;color:var(--text-dim);}

/* ── Provider card ── */
.provider-list{display:flex;flex-direction:column;gap:8px;}
.provider-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.provider-card.is-default{border-color:var(--amber);}
.provider-card-info{flex:1;min-width:0;}
.provider-card-name{font-family:var(--mono);font-size:.78rem;font-weight:700;color:var(--text-bright);margin-bottom:3px;}
.provider-card-meta{font-family:var(--mono);font-size:.65rem;color:var(--text-dim);}

/* ── Mobile bottom nav ── */
.mobile-nav{display:none;position:fixed;bottom:0;left:0;right:0;background:var(--surface);border-top:1px solid var(--border);z-index:100;padding-bottom:env(safe-area-inset-bottom);}
.mobile-nav-inner{display:flex;}
.mobile-tab{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:8px 0;color:var(--text-dim);font-family:var(--mono);font-size:.58rem;cursor:pointer;border:none;background:none;transition:color .15s;letter-spacing:.5px;}
.mobile-tab.active{color:var(--amber);}
.mobile-tab i{font-size:1.2rem;margin-bottom:3px;}

@media(max-width:720px){
    .mh-tabs{display:none;}
    .mobile-nav{display:block;}
    .mh-body{padding-bottom:80px;}
    .hide-md { display: none !important; }
}
@media(max-width:500px){
    .hide-sm { display: none !important; }
}
</style>
</head>
<body>

<div class="mh-layout">

<!-- ═══ HEADER ═══ -->
<header class="mh-header">
    <div class="mh-logo">
        <i class="bi bi-envelope-paper"></i> Mail Hub
    </div>
    <nav class="mh-tabs">
        <button class="tab-btn active" data-view="dashboard"     onclick="switchView('dashboard',this)">
            <i class="bi bi-bar-chart-line"></i> Dashboard
        </button>
        <button class="tab-btn" data-view="newsletters"  onclick="switchView('newsletters',this)">
            <i class="bi bi-newspaper"></i> Newsletters
        </button>
        <button class="tab-btn" data-view="templates"    onclick="switchView('templates',this)">
            <i class="bi bi-layout-text-window"></i> Templates
        </button>
        <button class="tab-btn" data-view="subscribers"  onclick="switchView('subscribers',this)">
            <i class="bi bi-people"></i> Subscribers
        </button>
        <button class="tab-btn" data-view="providers"    onclick="switchView('providers',this)">
            <i class="bi bi-plug"></i> Providers
        </button>
    </nav>
    <div class="mh-header-right">
        <button class="btn-icon" title="Refresh" onclick="refreshView()"><i class="bi bi-arrow-clockwise"></i></button>
    </div>
</header>

<!-- ═══ BODY ═══ -->
<main class="mh-body">

    <!-- ── DASHBOARD ─────────────────────────────────────────── -->
    <div class="view active" id="view-dashboard">
        <div class="stats-row" id="stats-row">
            <div class="stat-chip"><div class="stat-chip-label">Newsletters</div><div class="stat-chip-value" id="stat-total">—</div><div class="stat-chip-sub">total</div></div>
            <div class="stat-chip"><div class="stat-chip-label">Sent 7d</div><div class="stat-chip-value" id="stat-sent7d">—</div><div class="stat-chip-sub">emails</div></div>
            <div class="stat-chip"><div class="stat-chip-label">Subscribers</div><div class="stat-chip-value" id="stat-subs">—</div><div class="stat-chip-sub">live from Brevo</div></div>
            <div class="stat-chip"><div class="stat-chip-label">Queue</div><div class="stat-chip-value" id="stat-queue">—</div><div class="stat-chip-sub">pending</div></div>
        </div>

        <div class="forge-card">
            <div class="forge-card-header">
                <div class="forge-card-title"><i class="bi bi-newspaper"></i> Recent Campaigns</div>
                <button class="btn-primary" onclick="switchView('newsletters',document.querySelector('[data-view=newsletters]')); openNewsletterModal();">
                    <i class="bi bi-plus-lg"></i> New Newsletter
                </button>
            </div>
            <div class="forge-table-wrap">
                <table class="forge-table">
                    <thead><tr>
                        <th>Title</th><th>Status</th><th class="hide-sm">Sent</th><th class="hide-md">Scheduled</th><th></th>
                    </tr></thead>
                    <tbody id="dash-nl-body"><tr class="loading-row"><td colspan="5"><span class="spinner"></span></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── NEWSLETTERS ───────────────────────────────────────── -->
    <div class="view" id="view-newsletters">
        <div class="filter-bar">
            <select class="filter-select" id="nl-filter-status" onchange="loadNewsletters()">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="scheduled">Scheduled</option>
                <option value="sending">Sending</option>
                <option value="sent">Sent</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <input class="filter-input" id="nl-search" placeholder="Search title / subject…" oninput="debounce(loadNewsletters,350)()">
            <button class="btn-primary" onclick="openNewsletterModal()"><i class="bi bi-plus-lg"></i> New</button>
        </div>

        <div class="forge-card">
            <div class="forge-table-wrap">
                <table class="forge-table">
                    <thead><tr>
                        <th>Title / Subject</th>
                        <th>Status</th>
                        <th class="hide-sm">Progress</th>
                        <th class="hide-md">Scheduled</th>
                        <th class="hide-md">List</th>
                        <th></th>
                    </tr></thead>
                    <tbody id="nl-table-body"><tr class="loading-row"><td colspan="6"><span class="spinner"></span></td></tr></tbody>
                </table>
            </div>
            <div style="padding:8px 14px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;font-family:var(--mono);font-size:.65rem;color:var(--text-dim);">
                <span id="nl-count">—</span>
                <div style="display:flex;gap:6px;">
                    <button class="btn-icon" onclick="changePage('nl',-1)"><i class="bi bi-chevron-left"></i></button>
                    <span id="nl-page-info" style="padding:4px 8px;align-self:center;">1 / 1</span>
                    <button class="btn-icon" onclick="changePage('nl',1)"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TEMPLATES ─────────────────────────────────────────── -->
    <div class="view" id="view-templates">
        <div style="display:flex;justify-content:flex-end;margin-bottom:4px;">
            <button class="btn-primary" onclick="openTemplateModal()"><i class="bi bi-plus-lg"></i> New Template</button>
        </div>
        <div class="forge-card">
            <div class="forge-table-wrap">
                <table class="forge-table">
                    <thead><tr>
                        <th>Template Name</th>
                        <th class="hide-md">Created</th>
                        <th class="hide-sm">Last Updated</th>
                        <th></th>
                    </tr></thead>
                    <tbody id="tpl-table-body"><tr class="loading-row"><td colspan="4"><span class="spinner"></span></td></tr></tbody>
                </table>
            </div>
        </div>
        
        <div class="forge-card" style="margin-top:14px;">
            <div class="forge-card-header">
                <div class="forge-card-title"><i class="bi bi-info-circle"></i> How Templates Work</div>
            </div>
            <div class="forge-card-body" style="font-family:var(--mono);font-size:.72rem;color:var(--text-dim);line-height:1.8;">
                Templates act as wrappers around your newsletter content. When crafting a newsletter, select a template from the dropdown and write your specific content in the email body.
                <br><br>
                Use the tag <code style="color:var(--amber);background:rgba(255,255,255,0.05);padding:2px 6px;border-radius:3px;">{{content}}</code> in your template's HTML and Plain Text versions. 
                At send-time, the system will seamlessly inject your newsletter content exactly where that tag is placed.
            </div>
        </div>
    </div>

    <!-- ── SUBSCRIBERS ───────────────────────────────────────── -->
    <div class="view" id="view-subscribers">
        
        <!-- GDPR Info Banner -->
        <div class="forge-card" style="border-color:var(--amber-dim); background:rgba(245,166,35,0.02); margin-bottom:4px;">
            <div class="forge-card-header" style="border-bottom-color:var(--border-glow);">
                <div class="forge-card-title" style="color:var(--amber);"><i class="bi bi-shield-check"></i> Master Data in Brevo</div>
            </div>
            <div class="forge-card-body" style="font-family:var(--mono);font-size:.72rem;color:var(--text);line-height:1.7;">
                For strict GDPR compliance and enhanced security, all PII (Personal Identifiable Information) is managed online at Brevo. 
                This local view is a <strong>read-only live query</strong>. 
                The system fetches emails just-in-time during the sending process and discards them immediately.
            </div>
        </div>

        <div class="filter-bar">
            <select class="filter-select" id="sub-filter-status" onchange="loadSubscribers()">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="unsubscribed">Unsubscribed</option>
            </select>
            <button class="btn-primary" onclick="loadSubscribers()" style="margin-left:auto;">
                <i class="bi bi-arrow-clockwise"></i> Refresh Live Data
            </button>
        </div>

        <div class="forge-card">
            <div class="forge-table-wrap">
                <table class="forge-table">
                    <thead><tr>
                        <th>Email</th><th class="hide-sm">Name</th><th>Status</th><th class="hide-md">Source</th><th class="hide-md">Added/Updated</th>
                    </tr></thead>
                    <tbody id="sub-table-body"><tr class="loading-row"><td colspan="5"><span class="spinner"></span></td></tr></tbody>
                </table>
            </div>
            <div style="padding:8px 14px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;font-family:var(--mono);font-size:.65rem;color:var(--text-dim);">
                <span id="sub-count">—</span>
                <div style="display:flex;gap:6px;">
                    <button class="btn-icon" onclick="changePage('sub',-1)"><i class="bi bi-chevron-left"></i></button>
                    <span id="sub-page-info" style="padding:4px 8px;align-self:center;">1 / 1</span>
                    <button class="btn-icon" onclick="changePage('sub',1)"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── PROVIDERS ─────────────────────────────────────────── -->
    <div class="view" id="view-providers">
        <div style="display:flex;justify-content:flex-end;">
            <button class="btn-primary" onclick="openProviderModal()"><i class="bi bi-plus-lg"></i> Add Provider</button>
        </div>
        <div class="provider-list" id="provider-list">
            <div class="forge-card"><div class="forge-card-body"><div class="empty-state"><span class="spinner"></span></div></div></div>
        </div>

        <div class="forge-card" style="margin-top:4px;">
            <div class="forge-card-header">
                <div class="forge-card-title"><i class="bi bi-info-circle"></i> About Brevo Free Tier</div>
            </div>
            <div class="forge-card-body" style="font-family:var(--mono);font-size:.72rem;color:var(--text-dim);line-height:1.8;">
                <p>Brevo's free plan includes <strong style="color:var(--amber);">300 emails/day</strong> with no monthly send cap. Ideal for small subscriber lists.</p>
                <p style="margin-top:8px;">Set <code style="color:var(--blue);">daily_limit</code> to <code>300</code> in the provider config to respect the free-tier cap. The system will pause sending and resume the following day automatically.</p>
                <p style="margin-top:8px;">The queue architecture means exceeding the daily limit is graceful — remaining jobs stay <code>pending</code> and will resume on the next scheduler run once the day rolls over.</p>
            </div>
        </div>
    </div>

</main>
</div>

<!-- ═══ MOBILE NAV ═══ -->
<div class="mobile-nav">
    <div class="mobile-nav-inner">
        <button class="mobile-tab active" data-view="dashboard" onclick="switchView('dashboard',this)"><i class="bi bi-bar-chart-line"></i>Dash</button>
        <button class="mobile-tab" data-view="newsletters" onclick="switchView('newsletters',this)"><i class="bi bi-newspaper"></i>Letters</button>
        <button class="mobile-tab" data-view="templates" onclick="switchView('templates',this)"><i class="bi bi-layout-text-window"></i>Tpl</button>
        <button class="mobile-tab" data-view="subscribers" onclick="switchView('subscribers',this)"><i class="bi bi-people"></i>Subs</button>
    </div>
</div>

<!-- ═══ NEWSLETTER MODAL ═══ -->
<div class="modal-backdrop" id="nl-modal" onclick="closeOnBackdrop(event,'nl-modal')">
<div class="modal-box">
    <div class="modal-header">
        <div class="modal-title" id="nl-modal-title">New Newsletter</div>
        <button class="btn-icon" onclick="closeModal('nl-modal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="nl-id">
        <div class="form-row">
            <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Title (internal)</label>
                <input type="text" class="form-control" id="nl-title" placeholder="Campaign title…">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Subject Line</label>
            <input type="text" class="form-control" id="nl-subject" placeholder="Your email subject…">
        </div>
        <div class="form-group">
            <label class="form-label">Preview / Preheader Text</label>
            <input type="text" class="form-control" id="nl-preview" placeholder="Short text shown after subject in inbox…" maxlength="255">
        </div>
        
        <!-- Template selection -->
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Email Wrapper Template</label>
                <select class="form-control" id="nl-template">
                    <option value="">— No Template (Raw Content) —</option>
                </select>
                <div class="form-hint">Selected template wraps your email body</div>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="form-control" id="nl-status">
                    <option value="draft">Draft</option>
                    <option value="scheduled">Scheduled</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Provider</label>
                <select class="form-control" id="nl-provider">
                    <option value="">— Use default —</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Scheduled At</label>
                <input type="datetime-local" class="form-control" id="nl-scheduled">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">From Name</label>
                <input type="text" class="form-control" id="nl-from-name" placeholder="Leave blank for provider default">
            </div>
            <div class="form-group">
                <label class="form-label">From Email</label>
                <input type="email" class="form-control" id="nl-from-email" placeholder="Leave blank for provider default">
            </div>
        </div>

        <!-- Body Editor -->
        <div class="form-group">
            <label class="form-label">Email Content Body</label>
            <div class="body-editor-tabs">
                <button class="body-tab active" onclick="switchBodyTab('nl','html',this)">HTML</button>
                <button class="body-tab" onclick="switchBodyTab('nl','text',this)">Plain Text</button>
            </div>
            <textarea class="form-control" id="nl-body-html" rows="10"
                placeholder="&lt;p&gt;Your specific newsletter content goes here...&lt;/p&gt;"></textarea>
            <textarea class="form-control" id="nl-body-text" rows="10" style="display:none;"
                placeholder="Plain text version (auto-generated from HTML if blank)…"></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Notes (internal)</label>
            <input type="text" class="form-control" id="nl-notes" placeholder="Optional internal notes…">
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn-secondary" onclick="closeModal('nl-modal')">Cancel</button>
        <button class="btn-primary" onclick="saveNewsletter()"><i class="bi bi-floppy"></i> Save</button>
    </div>
</div>
</div>

<!-- ═══ TEMPLATE MODAL ═══ -->
<div class="modal-backdrop" id="tpl-modal" onclick="closeOnBackdrop(event,'tpl-modal')">
<div class="modal-box">
    <div class="modal-header">
        <div class="modal-title" id="tpl-modal-title">New Template</div>
        <button class="btn-icon" onclick="closeModal('tpl-modal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="tpl-id">
        <div class="form-group">
            <label class="form-label">Template Name</label>
            <input type="text" class="form-control" id="tpl-name" placeholder="e.g. SAGE Dark Theme">
        </div>
        
        <div class="form-group">
            <label class="form-label">Wrapper Code</label>
            <div class="form-hint" style="margin-bottom:6px;">Use <strong style="color:var(--amber);">{{content}}</strong> where the newsletter body should be injected.</div>
            <div class="body-editor-tabs">
                <button class="body-tab active" onclick="switchBodyTab('tpl','html',this)">HTML</button>
                <button class="body-tab" onclick="switchBodyTab('tpl','text',this)">Plain Text</button>
            </div>
            <textarea class="form-control" id="tpl-body-html" rows="14" style="font-family:var(--mono);font-size:0.75rem;"
                placeholder="&lt;html&gt;... {{content}} ...&lt;/html&gt;"></textarea>
            <textarea class="form-control" id="tpl-body-text" rows="14" style="display:none;font-family:var(--mono);font-size:0.75rem;"
                placeholder="Template Header\n\n{{content}}\n\nTemplate Footer"></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn-secondary" onclick="closeModal('tpl-modal')">Cancel</button>
        <button class="btn-primary" onclick="saveTemplate()"><i class="bi bi-floppy"></i> Save</button>
    </div>
</div>
</div>

<!-- ═══ PROVIDER MODAL ═══ -->
<div class="modal-backdrop" id="prov-modal" onclick="closeOnBackdrop(event,'prov-modal')">
<div class="modal-box">
    <div class="modal-header">
        <div class="modal-title" id="prov-modal-title">Add Provider</div>
        <button class="btn-icon" onclick="closeModal('prov-modal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="prov-id">
        <div class="form-row">
            <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Display Name</label>
                <input type="text" class="form-control" id="prov-name" placeholder="e.g. Brevo Free Tier">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Driver</label>
                <select class="form-control" id="prov-driver" onchange="renderProviderConfigFields()">
                    <option value="brevo">Brevo (API)</option>
                    <option value="smtp">SMTP</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Daily Limit</label>
                <input type="number" class="form-control" id="prov-daily-limit" placeholder="300 (Brevo free)">
                <div class="form-hint">Leave blank for unlimited.</div>
            </div>
        </div>
        <!-- Dynamic config fields -->
        <div id="prov-config-fields"></div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Default From Email</label>
                <input type="email" class="form-control" id="prov-from-email" placeholder="hello@yourdomain.com">
            </div>
            <div class="form-group">
                <label class="form-label">Default From Name</label>
                <input type="text" class="form-control" id="prov-from-name" placeholder="The Anima Chronicles">
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:16px;margin-top:2px;">
            <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-family:var(--mono);font-size:.72rem;color:var(--text);">
                <input type="checkbox" id="prov-is-default" style="accent-color:var(--amber);"> Set as Default
            </label>
            <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-family:var(--mono);font-size:.72rem;color:var(--text);">
                <input type="checkbox" id="prov-is-enabled" checked style="accent-color:var(--amber);"> Enabled
            </label>
        </div>
        <div class="form-group" style="margin-top:4px;">
            <label class="form-label">Notes</label>
            <input type="text" class="form-control" id="prov-notes" placeholder="Optional notes…">
        </div>
        <!-- Test section -->
        <div style="border-top:1px solid var(--border);padding-top:10px;margin-top:4px;">
            <div style="font-family:var(--mono);font-size:.65rem;color:var(--text-dim);margin-bottom:8px;">TEST SEND</div>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="email" class="form-control" id="prov-test-email" placeholder="your@email.com" style="flex:1;">
                <button class="btn-secondary" onclick="testProvider()"><i class="bi bi-send"></i> Test</button>
            </div>
            <div id="prov-test-result" style="font-family:var(--mono);font-size:.65rem;margin-top:6px;"></div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn-secondary" onclick="closeModal('prov-modal')">Cancel</button>
        <button class="btn-primary" onclick="saveProvider()"><i class="bi bi-floppy"></i> Save Provider</button>
    </div>
</div>
</div>

<!-- ═══ ENQUEUE CONFIRM MODAL ═══ -->
<div class="modal-backdrop" id="enqueue-modal" onclick="closeOnBackdrop(event,'enqueue-modal')">
<div class="modal-box" style="max-width:420px;">
    <div class="modal-header">
        <div class="modal-title"><i class="bi bi-send" style="margin-right:6px;"></i> Send Newsletter</div>
        <button class="btn-icon" onclick="closeModal('enqueue-modal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body">
        <p style="font-family:var(--mono);font-size:.75rem;color:var(--text);line-height:1.7;">
            You are about to enqueue <strong id="enqueue-title" style="color:var(--amber);"></strong> for delivery.
        </p>
        <p style="font-family:var(--mono);font-size:.72rem;color:var(--text-dim);margin-top:8px;line-height:1.7;">
            This will create individual queue jobs for every active subscriber directly from Brevo.
            The scheduler worker will process them in batches, fetching emails just-in-time.
        </p>
    </div>
    <div class="modal-footer">
        <button class="btn-secondary" onclick="closeModal('enqueue-modal')">Cancel</button>
        <button class="btn-primary" style="background:var(--green);" onclick="confirmEnqueue()"><i class="bi bi-send-check"></i> Queue for Dispatch</button>
    </div>
</div>
</div>

<div class="toast-wrap" id="toast-wrap"></div>

<script>
'use strict';

const API = 'api.php';

// ── State ───────────────────────────────────────────────────────
let currentView  = 'dashboard';
let nlPage       = 1; let nlTotalPages = 1;
let subPage      = 1; let subTotalPages = 1;
let enqueueTarget = null;

let _lists     = [];
let _providers = [];
let _templates = [];

// ── View switching ───────────────────────────────────────────────
function switchView(view, btn) {
    document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
    document.querySelectorAll('.tab-btn, .mobile-tab').forEach(b => b.classList.remove('active'));

    document.getElementById('view-' + view).classList.add('active');
    if (btn) btn.classList.add('active');
    else document.querySelectorAll(`[data-view="${view}"]`).forEach(b => b.classList.add('active'));

    currentView = view;
    if (view === 'dashboard')   loadDashboard();
    if (view === 'newsletters') loadNewsletters();
    if (view === 'templates')   loadTemplates();
    if (view === 'subscribers') loadSubscribers();
    if (view === 'providers')   loadProviders();
}

function refreshView() {
    switchView(currentView, null);
}

// ── API helper ────────────────────────────────────────────────────
async function api(payload) {
    const res = await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return res.json();
}

// ── Dashboard ─────────────────────────────────────────────────────
async function loadDashboard() {
    const r = await api({ action: 'get_stats' });
    if (!r.ok) return;
    const d = r.data;

    const total = Object.values(d.newsletters || {}).reduce((a, b) => a + parseInt(b), 0);
    el('stat-total').textContent   = total;
    el('stat-sent7d').textContent  = d.sent_last_7d   ?? 0;
    el('stat-subs').textContent    = d.active_subscribers ?? 0;
    el('stat-queue').textContent   = d.pending_queue  ?? 0;

    const tbody = el('dash-nl-body');
    const nls   = d.recent_newsletters || [];
    if (!nls.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="loading-row" style="color:var(--text-dim);font-family:var(--mono);font-size:.72rem;">No newsletters yet</td></tr>';
        return;
    }
    tbody.innerHTML = nls.map(nl => `
        <tr>
            <td><strong style="color:var(--text-bright);">${esc(nl.title)}</strong></td>
            <td><span class="badge badge-${nl.status}">${nl.status}</span></td>
            <td class="hide-sm">${sendProgress(nl)}</td>
            <td class="hide-md" style="color:var(--text-dim);">${fmtDate(nl.sent_at) || '—'}</td>
            <td><button class="btn-icon" title="Open" onclick="switchView('newsletters',null); editNewsletter(${nl.id})"><i class="bi bi-pencil"></i></button></td>
        </tr>`).join('');
}

// ── Newsletters ───────────────────────────────────────────────────
async function loadNewsletters() {
    const tbody = el('nl-table-body');
    tbody.innerHTML = '<tr class="loading-row"><td colspan="6"><span class="spinner"></span></td></tr>';

    const r = await api({
        action: 'list_newsletters',
        status: el('nl-filter-status').value,
        search: el('nl-search').value,
        page:   nlPage,
        limit:  40,
    });
    if (!r.ok) { toast('Failed to load newsletters', 'error'); return; }

    const { newsletters, total, page } = r.data;
    nlTotalPages = r.data.pages || 1;
    nlPage       = page;
    el('nl-count').textContent = total + ' newsletter' + (total !== 1 ? 's' : '');
    el('nl-page-info').textContent = page + ' / ' + nlTotalPages;

    if (!newsletters.length) {
        tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state"><i class="bi bi-newspaper"></i><p>No newsletters found</p></div></td></tr>';
        return;
    }

    tbody.innerHTML = newsletters.map(nl => `
        <tr>
            <td style="max-width:220px;">
                <div style="font-family:var(--mono);font-size:.75rem;color:var(--text-bright);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(nl.title)}</div>
                <div style="font-family:var(--mono);font-size:.65rem;color:var(--text-dim);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;">${esc(nl.subject)}</div>
            </td>
            <td><span class="badge badge-${nl.status}">${nl.status}</span></td>
            <td class="hide-sm">${sendProgress(nl)}</td>
            <td class="hide-md" style="color:var(--text-dim);font-family:var(--mono);font-size:.65rem;">${fmtDate(nl.scheduled_at) || '—'}</td>
            <td class="hide-md" style="color:var(--text-dim);font-family:var(--mono);font-size:.65rem;">Brevo Live</td>
            <td>
                <div class="action-group">
                    ${nl.status === 'draft' || nl.status === 'scheduled' ? `<button class="btn-icon" title="Send" onclick="openEnqueueModal(${nl.id},'${esc(nl.title)}')"><i class="bi bi-send"></i></button>` : ''}
                    <button class="btn-icon" title="Edit" onclick="editNewsletter(${nl.id})"><i class="bi bi-pencil"></i></button>
                    <button class="btn-icon" title="Duplicate" onclick="duplicateNewsletter(${nl.id})"><i class="bi bi-copy"></i></button>
                    ${nl.status === 'draft' || nl.status === 'cancelled' ? `<button class="btn-icon" title="Delete" style="color:var(--red);" onclick="deleteNewsletter(${nl.id},'${esc(nl.title)}')"><i class="bi bi-trash3"></i></button>` : ''}
                </div>
            </td>
        </tr>`).join('');
}

function sendProgress(nl) {
    const total = parseInt(nl.total_recipients) || 0;
    const sent  = parseInt(nl.total_sent) || 0;
    const failed= parseInt(nl.total_failed) || 0;
    if (!total) return '<span style="color:var(--text-dim);font-family:var(--mono);font-size:.65rem;">—</span>';
    const pct   = Math.round((sent / total) * 100);
    return `<div class="send-progress">
        <div class="send-nums">${sent} / ${total}${failed ? ` · <span style="color:var(--red);">${failed} failed</span>` : ''}</div>
        <div class="progress-bar"><div class="progress-fill" style="width:${pct}%"></div></div>
    </div>`;
}

// Newsletter modal
async function openNewsletterModal() {
    resetNlModal();
    await Promise.all([loadProvidersForSelect(), loadTemplatesForSelect()]);
    el('nl-modal').classList.add('open');
}

function resetNlModal() {
    el('nl-modal-title').textContent = 'New Newsletter';
    if(el('nl-id')) el('nl-id').value = '';
    ['nl-title','nl-subject','nl-preview','nl-from-name','nl-from-email','nl-reply-to','nl-body-html','nl-body-text','nl-notes'].forEach(id => {
        if(el(id)) el(id).value = '';
    });
    if(el('nl-status'))    el('nl-status').value    = 'draft';
    if(el('nl-scheduled')) el('nl-scheduled').value = '';
    if(el('nl-provider'))  el('nl-provider').value  = '';
    if(el('nl-template'))  el('nl-template').value  = '';
    switchBodyTab('nl', 'html', document.querySelector('#nl-modal .body-tab'));
}

async function editNewsletter(id) {
    const r = await api({ action: 'get_newsletter', id });
    if (!r.ok) { toast('Failed to load newsletter', 'error'); return; }
    const nl = r.data;
    await Promise.all([loadProvidersForSelect(), loadTemplatesForSelect()]);
    el('nl-modal-title').textContent = 'Edit Newsletter';
    el('nl-id').value          = nl.id;
    el('nl-title').value       = nl.title ?? '';
    el('nl-subject').value     = nl.subject ?? '';
    el('nl-preview').value     = nl.preview_text ?? '';
    el('nl-from-name').value   = nl.from_name ?? '';
    el('nl-from-email').value  = nl.from_email ?? '';
    el('nl-reply-to').value    = nl.reply_to ?? '';
    el('nl-body-html').value   = nl.body_html ?? '';
    el('nl-body-text').value   = nl.body_text ?? '';
    el('nl-notes').value       = nl.notes ?? '';
    el('nl-status').value      = nl.status ?? 'draft';
    el('nl-provider').value    = nl.provider_id ?? '';
    el('nl-template').value    = nl.template_id ?? '';
    if (nl.scheduled_at) el('nl-scheduled').value = nl.scheduled_at.replace(' ', 'T').slice(0,16);
    el('nl-modal').classList.add('open');
    switchView('newsletters', document.querySelector('[data-view=newsletters]'));
}

async function saveNewsletter() {
    const newsletter = {
        id:           el('nl-id').value || null,
        title:        el('nl-title').value.trim(),
        subject:      el('nl-subject').value.trim(),
        preview_text: el('nl-preview').value.trim(),
        from_name:    el('nl-from-name').value.trim(),
        from_email:   el('nl-from-email').value.trim(),
        reply_to:     el('nl-reply-to').value.trim(),
        body_html:    el('nl-body-html').value,
        body_text:    el('nl-body-text').value,
        status:       el('nl-status').value,
        provider_id:  el('nl-provider').value || null,
        template_id:  el('nl-template').value || null,
        scheduled_at: el('nl-scheduled').value || null,
        notes:        el('nl-notes').value.trim(),
    };
    if (!newsletter.title) { toast('Title is required', 'error'); return; }
    if (!newsletter.subject) { toast('Subject is required', 'error'); return; }

    const r = await api({ action: 'save_newsletter', newsletter });
    if (!r.ok) { toast(r.error || 'Save failed', 'error'); return; }
    toast(newsletter.id ? 'Newsletter updated' : 'Newsletter created', 'success');
    closeModal('nl-modal');
    loadNewsletters();
    loadDashboard();
}

async function duplicateNewsletter(id) {
    const r = await api({ action: 'duplicate_newsletter', id });
    if (!r.ok) { toast(r.error || 'Duplicate failed', 'error'); return; }
    toast('Newsletter duplicated', 'success');
    loadNewsletters();
}

async function deleteNewsletter(id, title) {
    if (!confirm(`Delete "${title}"? This cannot be undone.`)) return;
    const r = await api({ action: 'delete_newsletter', id });
    if (!r.ok) { toast(r.error || 'Delete failed', 'error'); return; }
    toast('Newsletter deleted', 'info');
    loadNewsletters();
}

function openEnqueueModal(id, title) {
    enqueueTarget = id;
    el('enqueue-title').textContent = title;
    el('enqueue-modal').classList.add('open');
}

async function confirmEnqueue() {
    if (!enqueueTarget) return;
    const r = await api({ action: 'enqueue_newsletter', id: enqueueTarget });
    closeModal('enqueue-modal');
    enqueueTarget = null;
    if (!r.ok) { toast(r.error || 'Enqueue failed', 'error'); return; }
    toast(`Queued ${r.data.queued} recipients`, 'success');
    loadNewsletters();
    loadDashboard();
}

// ── Templates ─────────────────────────────────────────────────────
async function loadTemplates() {
    const tbody = el('tpl-table-body');
    tbody.innerHTML = '<tr class="loading-row"><td colspan="4"><span class="spinner"></span></td></tr>';

    const r = await api({ action: 'list_templates' });
    if (!r.ok) { toast('Failed to load templates', 'error'); return; }
    _templates = r.data || [];

    if (!_templates.length) {
        tbody.innerHTML = '<tr><td colspan="4"><div class="empty-state"><i class="bi bi-layout-text-window"></i><p>No templates created yet</p></div></td></tr>';
        return;
    }

    tbody.innerHTML = _templates.map(t => `
        <tr>
            <td><strong style="color:var(--text-bright);">${esc(t.name)}</strong></td>
            <td class="hide-md" style="color:var(--text-dim);font-family:var(--mono);font-size:.65rem;">${fmtDate(t.created_at)}</td>
            <td class="hide-sm" style="color:var(--text-dim);font-family:var(--mono);font-size:.65rem;">${fmtDate(t.updated_at)}</td>
            <td>
                <div class="action-group">
                    <button class="btn-icon" title="Edit" onclick="editTemplate(${t.id})"><i class="bi bi-pencil"></i></button>
                    <button class="btn-icon" title="Delete" style="color:var(--red);" onclick="deleteTemplate(${t.id},'${esc(t.name)}')"><i class="bi bi-trash3"></i></button>
                </div>
            </td>
        </tr>`).join('');
}

function openTemplateModal() {
    el('tpl-modal-title').textContent = 'New Template';
    el('tpl-id').value = '';
    el('tpl-name').value = '';
    el('tpl-body-html').value = '';
    el('tpl-body-text').value = '';
    switchBodyTab('tpl', 'html', document.querySelector('#tpl-modal .body-tab'));
    el('tpl-modal').classList.add('open');
}

async function editTemplate(id) {
    const r = await api({ action: 'get_template', id });
    if (!r.ok) { toast('Failed to load template', 'error'); return; }
    const t = r.data;
    el('tpl-modal-title').textContent = 'Edit Template';
    el('tpl-id').value        = t.id;
    el('tpl-name').value      = t.name;
    el('tpl-body-html').value = t.body_html || '';
    el('tpl-body-text').value = t.body_text || '';
    switchBodyTab('tpl', 'html', document.querySelector('#tpl-modal .body-tab'));
    el('tpl-modal').classList.add('open');
}

async function saveTemplate() {
    const template = {
        id:        el('tpl-id').value || null,
        name:      el('tpl-name').value.trim(),
        body_html: el('tpl-body-html').value,
        body_text: el('tpl-body-text').value
    };
    if (!template.name) { toast('Template name required', 'error'); return; }

    const r = await api({ action: 'save_template', template });
    if (!r.ok) { toast(r.error || 'Save failed', 'error'); return; }
    toast(template.id ? 'Template updated' : 'Template created', 'success');
    closeModal('tpl-modal');
    loadTemplates();
}

async function deleteTemplate(id, name) {
    if (!confirm(`Delete template "${name}"? Newsletters using it will revert to raw content.`)) return;
    const r = await api({ action: 'delete_template', id });
    if (!r.ok) { toast('Delete failed', 'error'); return; }
    toast('Template deleted', 'info');
    loadTemplates();
}

async function loadTemplatesForSelect() {
    const r = await api({ action: 'list_templates' });
    if (!r.ok) return;
    const sel = el('nl-template');
    const cur = sel.value;
    sel.innerHTML = '<option value="">— No Template (Raw Content) —</option>' +
        r.data.map(t => `<option value="${t.id}">${esc(t.name)}</option>`).join('');
    if (cur) sel.value = cur;
}

// Body tab switcher (reusable for nl and tpl)
function switchBodyTab(prefix, tab, btn) {
    const modal = btn.closest('.modal-box');
    modal.querySelectorAll('.body-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    el(prefix + '-body-html').style.display = tab === 'html' ? '' : 'none';
    el(prefix + '-body-text').style.display = tab === 'text' ? '' : 'none';
}

// ── Subscribers (Sync Interface) ──────────────────────────────────
async function loadSubscribers() {
    const tbody = el('sub-table-body');
    tbody.innerHTML = '<tr class="loading-row"><td colspan="5"><span class="spinner"></span></td></tr>';
    
    // NOTE: search is removed here because Brevo fetching doesn't use it.
    const r = await api({
        action:  'list_subscribers',
        status:  el('sub-filter-status').value,
        page:    subPage,
        limit:   50,
    });
    
    if (!r.ok) { toast('Failed to load subscribers from Brevo', 'error'); return; }
    const { subscribers, total, pages, page } = r.data;
    subTotalPages = pages || 1;
    subPage       = page;
    el('sub-count').textContent   = total + ' subscriber' + (total !== 1 ? 's' : '') + ' in Brevo';
    el('sub-page-info').textContent = page + ' / ' + subTotalPages;

    if (!subscribers.length) {
        tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><i class="bi bi-people"></i><p>No active subscribers found in Brevo.</p></div></td></tr>';
        return;
    }

    tbody.innerHTML = subscribers.map(s => `
        <tr>
            <td style="font-family:var(--mono);font-size:.72rem;color:var(--text-bright);">${esc(s.email)}</td>
            <td class="hide-sm" style="font-family:var(--mono);font-size:.72rem;">${esc((s.first_name || '') + ' ' + (s.last_name || '')).trim() || '—'}</td>
            <td><span class="badge badge-${s.status}">${s.status}</span></td>
            <td class="hide-md" style="color:var(--text-dim);font-family:var(--mono);font-size:.65rem;">Brevo Live</td>
            <td class="hide-md" style="color:var(--text-dim);font-family:var(--mono);font-size:.65rem;">${fmtDate(s.updated_at || s.created_at)}</td>
        </tr>`).join('');
}


// ── Providers ─────────────────────────────────────────────────────
async function loadProviders() {
    const list = el('provider-list');
    list.innerHTML = '<div class="forge-card"><div class="forge-card-body"><div class="empty-state"><span class="spinner"></span></div></div></div>';
    const r = await api({ action: 'list_providers' });
    if (!r.ok) { toast('Failed to load providers', 'error'); return; }
    _providers = r.data.providers || [];

    if (!_providers.length) {
        list.innerHTML = '<div class="forge-card"><div class="forge-card-body"><div class="empty-state"><i class="bi bi-plug"></i><p>No providers configured yet</p></div></div></div>';
        return;
    }

    list.innerHTML = _providers.map(p => `
        <div class="provider-card ${p.is_default ? 'is-default' : ''}">
            <div class="provider-card-info">
                <div class="provider-card-name">
                    ${esc(p.name)}
                    ${p.is_default ? '<span style="color:var(--amber);font-size:.65rem;margin-left:6px;">★ DEFAULT</span>' : ''}
                </div>
                <div class="provider-card-meta">
                    <span class="badge badge-${p.driver}">${p.driver}</span>
                    ${p.is_enabled ? '' : '<span class="badge badge-cancelled" style="margin-left:4px;">disabled</span>'}
                    ${p.daily_limit ? `<span style="margin-left:8px;">Limit: ${p.sent_today}/${p.daily_limit}/day</span>` : '<span style="margin-left:8px;">No daily limit</span>'}
                </div>
            </div>
            <div class="action-group">
                <button class="btn-icon" title="Edit" onclick="editProvider(${p.id})"><i class="bi bi-pencil"></i></button>
                <button class="btn-icon" title="Delete" style="color:var(--red);" onclick="deleteProvider(${p.id},'${esc(p.name)}')"><i class="bi bi-trash3"></i></button>
            </div>
        </div>`).join('');
}

function renderProviderConfigFields() {
    const driver = el('prov-driver').value;
    const container = el('prov-config-fields');
    if (driver === 'brevo') {
        container.innerHTML = `
        <div class="form-group">
            <label class="form-label">Brevo API Key</label>
            <input type="text" class="form-control" id="prov-api-key" placeholder="xkeysib-…">
            <div class="form-hint">Generate at brevo.com → Settings → API Keys</div>
        </div>`;
    } else if (driver === 'smtp') {
        container.innerHTML = `
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">SMTP Host</label>
                <input type="text" class="form-control" id="prov-smtp-host" placeholder="smtp.gmail.com">
            </div>
            <div class="form-group">
                <label class="form-label">Port</label>
                <select class="form-control" id="prov-smtp-port">
                    <option value="587">587 (STARTTLS)</option>
                    <option value="465">465 (SSL)</option>
                    <option value="25">25 (plain)</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" id="prov-smtp-user" placeholder="user@example.com">
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" id="prov-smtp-pass" placeholder="••••••••">
            </div>
        </div>`;
    } else {
        container.innerHTML = '';
    }
}

function openProviderModal() {
    el('prov-modal-title').textContent = 'Add Provider';
    el('prov-id').value         = '';
    el('prov-name').value       = '';
    el('prov-driver').value     = 'brevo';
    el('prov-daily-limit').value= '300';
    el('prov-from-email').value = '';
    el('prov-from-name').value  = '';
    el('prov-is-default').checked = !_providers.length;
    el('prov-is-enabled').checked = true;
    el('prov-notes').value      = '';
    el('prov-test-result').textContent = '';
    renderProviderConfigFields();
    el('prov-modal').classList.add('open');
}

async function editProvider(id) {
    const prov = _providers.find(p => p.id === id);
    if (!prov) return;
    const config = (typeof prov.config === 'string') ? JSON.parse(prov.config || '{}') : (prov.config || {});
    el('prov-modal-title').textContent = 'Edit Provider';
    el('prov-id').value          = prov.id;
    el('prov-name').value        = prov.name;
    el('prov-driver').value      = prov.driver;
    el('prov-daily-limit').value = prov.daily_limit || '';
    el('prov-from-email').value  = config.default_from || '';
    el('prov-from-name').value   = config.default_name || '';
    el('prov-is-default').checked= !!prov.is_default;
    el('prov-is-enabled').checked= !!prov.is_enabled;
    el('prov-notes').value       = prov.notes || '';
    el('prov-test-result').textContent = '';
    renderProviderConfigFields();
    // Fill driver-specific fields
    if (prov.driver === 'brevo') {
        el('prov-api-key').value   = config.api_key     || '';
    } else if (prov.driver === 'smtp') {
        el('prov-smtp-host').value = config.host        || '';
        el('prov-smtp-port').value = config.port        || '587';
        el('prov-smtp-user').value = config.username    || '';
        el('prov-smtp-pass').value = config.password    || '';
    }
    el('prov-modal').classList.add('open');
}

async function saveProvider(closeModalAfter) {
    const shouldClose = closeModalAfter !== false;
    const driver = el('prov-driver').value;
    const config = {
        default_from: el('prov-from-email').value.trim(),
        default_name: el('prov-from-name').value.trim(),
    };
    if (driver === 'brevo') {
        config.api_key = el('prov-api-key').value.trim();
    } else if (driver === 'smtp') {
        config.host     = el('prov-smtp-host').value.trim();
        config.port     = parseInt(el('prov-smtp-port').value) || 587;
        config.username = el('prov-smtp-user').value.trim();
        config.password = el('prov-smtp-pass').value;
        config.encryption = parseInt(config.port) === 465 ? 'ssl' : 'tls';
    }

    const provider = {
        id:          el('prov-id').value || null,
        name:        el('prov-name').value.trim(),
        driver,
        config:      JSON.stringify(config),
        daily_limit: el('prov-daily-limit').value ? parseInt(el('prov-daily-limit').value) : null,
        is_default:  el('prov-is-default').checked ? 1 : 0,
        is_enabled:  el('prov-is-enabled').checked ? 1 : 0,
        notes:       el('prov-notes').value.trim(),
    };

    if (!provider.name) { toast('Name is required', 'error'); return null; }

    const r = await api({ action: 'save_provider', provider });
    if (!r.ok) { toast(r.error || 'Save failed', 'error'); return null; }
    
    el('prov-id').value = r.data.id; 

    if (shouldClose) {
        toast('Provider saved', 'success');
        closeModal('prov-modal');
    }
    loadProviders();
    return r.data.id;
}

async function deleteProvider(id, name) {
    if (!confirm(`Delete provider "${name}"?`)) return;
    const r = await api({ action: 'delete_provider', id });
    if (!r.ok) { toast(r.error || 'Delete failed', 'error'); return; }
    toast('Provider deleted', 'info');
    loadProviders();
}

async function testProvider() {
    const to = el('prov-test-email').value.trim();
    if (!to) { toast('Enter a test email address', 'error'); return; }

    const res = el('prov-test-result');
    res.textContent = 'Sending…';
    res.style.color = 'var(--text-dim)';

    const provId = await saveProvider(false);
    if (!provId) { res.textContent = 'Save failed.'; return; }

    const r = await api({ action: 'test_provider', provider_id: provId, to_email: to });
    if (r.ok && r.data && r.data.success) {
        res.textContent = '✓ Test email sent!';
        res.style.color = 'var(--green)';
    } else {
        const err = (r.data && r.data.error) || r.error || 'Unknown error';
        res.textContent = '✗ ' + err;
        res.style.color = 'var(--red)';
    }
}

async function loadProvidersForSelect() {
    const r = await api({ action: 'list_providers' });
    if (!r.ok) return;
    const sel = el('nl-provider');
    const cur = sel.value;
    sel.innerHTML = '<option value="">— Use default —</option>' +
        (r.data.providers || []).map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('');
    if (cur) sel.value = cur;
}

// ── Pagination ─────────────────────────────────────────────────────
function changePage(type, delta) {
    if (type === 'nl') {
        nlPage = Math.max(1, Math.min(nlTotalPages, nlPage + delta));
        loadNewsletters();
    } else {
        subPage = Math.max(1, Math.min(subTotalPages, subPage + delta));
        loadSubscribers();
    }
}

// ── Modal helpers ──────────────────────────────────────────────────
function closeModal(id)              { el(id).classList.remove('open'); }
function closeOnBackdrop(e, id)      { if (e.target === el(id)) closeModal(id); }

// ── Toast ──────────────────────────────────────────────────────────
function toast(msg, type = 'info', ms = 3000) {
    const d = document.createElement('div');
    d.className = `toast ${type}`;
    d.innerHTML = (type === 'success' ? '✓ ' : type === 'error' ? '✕ ' : '◆ ') + esc(msg);
    d.onclick   = () => dismiss(d);
    el('toast-wrap').appendChild(d);
    const dismiss = t => { t.classList.add('out'); setTimeout(() => t.remove(), 300); };
    setTimeout(() => dismiss(d), ms);
}

// ── Utils ──────────────────────────────────────────────────────────
function el(id)     { return document.getElementById(id); }
function esc(s)     { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }
function fmtDate(s) { if (!s) return null; const d = new Date(s); return isNaN(d) ? s : d.toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}); }

function debounce(fn, ms) {
    let t;
    return function(...a) { clearTimeout(t); t = setTimeout(() => fn.apply(this, a), ms); };
}

// ── Bootstrap ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadDashboard();
    renderProviderConfigFields();
});
</script>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>