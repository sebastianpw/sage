<?php
// public/entity_form.php
// Entity editor — redesigned from entity_form.php
// Unified dark-terminal aesthetic matching enhanimatics + vidbat UI language
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\SpwBase;
use App\UI\Modules\ModuleRegistry;
use App\UI\Modules\VideoFrameExtractorModule;
use App\UI\Modules\ImageEditorModule;

$spw     = SpwBase::getInstance();
$mysqli  = $spw->getMysqli();
$pdo     = $spw->getPDO();

$entityType  = $_GET['entity_type'] ?? '';
$entityId    = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;
$redirectUrl = $_GET['redirect_url'] ?? "gallery_{$entityType}_nu.php";

if (!preg_match('/^[a-z_]+$/', $entityType)) die('Invalid entity type.');

$tableCheck = $mysqli->query("SHOW TABLES LIKE '{$entityType}'");
if ($tableCheck->num_rows === 0) die("Entity table '{$entityType}' does not exist.");

// ── Column metadata ──
$columnInfo = [];
$result = $mysqli->query("SHOW FULL COLUMNS FROM `{$entityType}`");
while ($col = $result->fetch_assoc()) $columnInfo[$col['Field']] = $col;

// ── Handle POST ──
$notification = '';
$notificationType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update' && $entityId > 0) {
    $updates = []; $types = ''; $values = [];
    foreach ($_POST as $key => $value) {
        if ($key === 'action' || $key === 'id') continue;
        if (!isset($columnInfo[$key])) continue;
        $colInfo  = $columnInfo[$key];
        $colType  = strtolower($colInfo['Type']);
        $nullable = ($colInfo['Null'] === 'YES');
        $updates[] = "`{$key}` = ?";
        if ($value === '' && $nullable) { $values[] = null; $types .= 's'; continue; }
        if (preg_match('/^(tiny|small|medium|big)?int/', $colType)) { $types .= 'i'; $values[] = (int)$value; }
        elseif (preg_match('/^(float|double|decimal)/', $colType)) { $types .= 'd'; $values[] = (float)$value; }
        else { $types .= 's'; $values[] = $value; }
    }
    if (!empty($updates)) {
        $sql  = "UPDATE `{$entityType}` SET " . implode(', ', $updates) . " WHERE id = ?";
        $types .= 'i'; $values[] = $entityId;
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$values);
            $notification     = $stmt->execute() ? 'Saved successfully.' : 'Error: ' . $stmt->error;
            $notificationType = $stmt->execute() ? 'success' : 'error';
            $stmt->close();
        }
    }
}

// ── Fetch entity ──
if (!$entityId) die("No entity ID provided.");
$stmt = $mysqli->prepare("SELECT * FROM `{$entityType}` WHERE id = ?");
$stmt->bind_param('i', $entityId);
$stmt->execute();
$entity = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$entity) die("Entity #{$entityId} not found.");

// ── Sketch analysis (curation) ──
$curationData = null;
if ($entityType === 'sketches') {
    $cur = $mysqli->prepare("SELECT overall_quality,classification,scoring,entities,thematics,recommendations FROM sketch_analysis WHERE sketch_id=?");
    if ($cur) {
        $cur->bind_param('i', $entityId);
        $cur->execute();
        $row = $cur->get_result()->fetch_assoc();
        $cur->close();
        if ($row && !empty($row['classification'])) {
            $curationData = [
                'score' => $row['overall_quality'],
                'class' => json_decode($row['classification'], true),
                'score_breakdown' => json_decode($row['scoring'], true),
                'entities' => json_decode($row['entities'], true),
                'themes'   => json_decode($row['thematics'], true),
                'recs'     => json_decode($row['recommendations'], true),
            ];
        }
    }
}

// ── Mini Graph References (Sketches only) ──
$mgAgNodeId = 0;
$mgKgNodeId = 0;
$mgLoreDocId = null;

$fuzzSafeEntities = ['sketches', 'characters', 'animas', 'locations', 'backgrounds', 'artifacts', 'vehicles'];
$showFuzzGraph = in_array($entityType, $fuzzSafeEntities);

if ($entityType === 'sketches') {
    $stmt = $mysqli->prepare(
        "SELECT slh.doc_id, slh.entity_type, slh.entity_name
         FROM sketch_lore_history slh
         WHERE slh.sketch_id = ?
         ORDER BY slh.id DESC"
    );
    $histRows = [];
    if ($stmt) {
        $stmt->bind_param('i', $entityId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $histRows[] = $row;
        $stmt->close();
    }

    $anyEntityName  = null;
    $loreEntityName = null;

    foreach ($histRows as $row) {
        if ($anyEntityName === null) {
            $anyEntityName = $row['entity_name'];
        }
        if ($mgLoreDocId === null && substr($row['entity_type'], -1) === 's') {
            $mgLoreDocId    = (int)$row['doc_id'];
            $loreEntityName = $row['entity_name'];
        }
        if ($anyEntityName !== null && $mgLoreDocId !== null) break;
    }

    if ($mgLoreDocId && $loreEntityName) {
        $stmt = $mysqli->prepare("SELECT id FROM ag_nodes WHERE doc_id = ? AND LOWER(name) = LOWER(?) AND status='active' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('is', $mgLoreDocId, $loreEntityName);
            $stmt->execute();
            if ($r = $stmt->get_result()->fetch_assoc()) $mgAgNodeId = (int)$r['id'];
            $stmt->close();
        }
    }

    if ($anyEntityName) {
        $stmt = $mysqli->prepare("SELECT id FROM kg_nodes WHERE LOWER(name) = LOWER(?) AND status='active' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $anyEntityName);
            $stmt->execute();
            if ($r = $stmt->get_result()->fetch_assoc()) $mgKgNodeId = (int)$r['id'];
            $stmt->close();
        }
    }
}

// ── Audio sections ──
$isAudioEntity  = (strpos($entityType, 'audio_') === 0) && ($entityType !== 'audio_voice_identity');
$sourceAudioFile = null;
$resultAudios    = [];

if ($isAudioEntity) {
    if (!empty($entity['wav2wav_audio_filename'])) $sourceAudioFile = $entity['wav2wav_audio_filename'];
    $mapTable   = "audios_2_{$entityType}";
    $checkMap   = $mysqli->query("SHOW TABLES LIKE '$mapTable'");
    if ($checkMap && $checkMap->num_rows > 0) {
        $stmt = $mysqli->prepare("SELECT a.* FROM audios a JOIN $mapTable m ON a.id=m.from_id WHERE m.to_id=? ORDER BY a.created_at DESC");
        if ($stmt) { $stmt->bind_param('i', $entityId); $stmt->execute(); $resultAudios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }
    }
}
if ($entityType === 'composites') {
    $stmt = $mysqli->prepare("SELECT a.* FROM audios a JOIN composite_audios ca ON a.id=ca.audio_id WHERE ca.composite_id=? ORDER BY ca.created_at DESC");
    if ($stmt) { $stmt->bind_param('i', $entityId); $stmt->execute(); $resultAudios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }
}

// ── Video sections ──
$resultVideos     = [];
$videoSectionTitle = 'Related Videos';
if ($entityType === 'animatics') {
    $videoSectionTitle = 'Rendered Videos';
    $stmt = $mysqli->prepare("SELECT v.* FROM videos v JOIN videos_2_animatics va ON v.id=va.from_id WHERE va.to_id=? ORDER BY v.created_at DESC");
    if ($stmt) { $stmt->bind_param('i', $entityId); $stmt->execute(); $resultVideos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }
} else {
    $framesMapTable = "frames_2_{$entityType}";
    $checkFM = $mysqli->query("SHOW TABLES LIKE '$framesMapTable'");
    if ($checkFM && $checkFM->num_rows > 0) {
        $stmt = $mysqli->prepare("SELECT v.*, a.id as source_animatic_id FROM videos v JOIN videos_2_animatics va ON v.id=va.from_id JOIN animatics a ON va.to_id=a.id JOIN $framesMapTable f2e ON a.img2img_frame_id=f2e.from_id WHERE f2e.to_id=? ORDER BY v.created_at DESC");
        if ($stmt) { $stmt->bind_param('i', $entityId); $stmt->execute(); $resultVideos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }
    }
}

// ── Frame sections ──
$frames = []; $specialFrames = []; $compositeFrames = []; $depthFrames = [];
if (!$isAudioEntity) {
    // Direct frames
    $stmt = $mysqli->prepare("SELECT id,name,filename,depth_map_filename FROM frames WHERE entity_type=? AND entity_id=? ORDER BY id DESC");
    if ($stmt) {
        $stmt->bind_param('si', $entityType, $entityId);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $frames[] = $row;
            if (!empty($row['depth_map_filename'])) $depthFrames[] = ['id'=>$row['id'],'name'=>($row['name']?:'Frame '.$row['id']).' (Depth)','filename'=>$row['depth_map_filename']];
        }
        $stmt->close();
    }
    // Mapped frames
    $fmTable = "frames_2_{$entityType}";
    $checkFM = $mysqli->query("SHOW TABLES LIKE '$fmTable'");
    if ($checkFM && $checkFM->num_rows > 0) {
        $stmt = $mysqli->prepare("SELECT f.id,f.name,f.filename,f.depth_map_filename FROM frames f JOIN $fmTable m ON f.id=m.from_id WHERE m.to_id=? ORDER BY f.id DESC");
        if ($stmt) {
            $stmt->bind_param('i', $entityId);
            $stmt->execute();
            $existingIds = array_column($frames, 'id');
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                if (!in_array($row['id'], $existingIds)) {
                    $frames[] = $row; $existingIds[] = $row['id'];
                    if (!empty($row['depth_map_filename'])) $depthFrames[] = ['id'=>$row['id'],'name'=>($row['name']?:'Frame '.$row['id']).' (Depth)','filename'=>$row['depth_map_filename']];
                }
            }
            $stmt->close();
        }
    }
    // Special frames (img2img / cnmap)
    foreach (['img2img_frame_id' => 'img2img', 'cnmap_frame_id' => 'cnmap'] as $col => $key) {
        if (!empty($entity[$col])) {
            $fid = (int)$entity[$col];
            $stmt = $mysqli->prepare("SELECT id,name,filename FROM frames WHERE id=?");
            if ($stmt) { $stmt->bind_param('i', $fid); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); if ($row) $specialFrames[$key] = $row; $stmt->close(); }
        }
    }
    // Composite frames
    if ($entityType === 'composites') {
        $stmt = $mysqli->prepare("SELECT f.id,f.name,f.filename,f.depth_map_filename FROM composite_frames cf JOIN frames f ON f.id=cf.frame_id WHERE cf.composite_id=? ORDER BY cf.created_at DESC,f.id DESC");
        if ($stmt) {
            $stmt->bind_param('i', $entityId);
            $stmt->execute();
            $dIds = array_column($depthFrames, 'id');
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $compositeFrames[] = $row;
                if (!empty($row['depth_map_filename']) && !in_array($row['id'], $dIds)) { $depthFrames[] = ['id'=>$row['id'],'name'=>($row['name']?:'Frame '.$row['id']).' (Depth)','filename'=>$row['depth_map_filename']]; $dIds[] = $row['id']; }
            }
            $stmt->close();
        }
    }
}

// ── Field config ──
function getFieldConfig(array $col): array {
    $cfg = [
        'name'     => $col['Field'],
        'type'     => 'text',
        'required' => $col['Null']==='NO' && $col['Default']===null && $col['Extra']!=='auto_increment',
        'readonly' => false,
        'label'    => ucwords(str_replace('_',' ',$col['Field'])),
        'help'     => $col['Comment'] ?: '',
        'nullable' => $col['Null']==='YES',
        'placeholder' => '',
    ];
    if ($col['Extra']==='auto_increment' || $col['Key']==='PRI') { $cfg['readonly']=true; $cfg['type']='number'; }
    if (in_array($col['Field'],['created_at','updated_at'])) { $cfg['readonly']=true; $cfg['type']='datetime-local'; }
    if (stripos($col['Type'],'tinyint(1)')!==false || in_array($col['Field'],['img2img','depth2img','cnmap','regenerate_images','regenerate_audios','regenerate_videos'])) $cfg['type']='checkbox';
    if (preg_match('/^(tiny|small|medium|big)?int\(/',$col['Type']) && $cfg['type']==='text') { $cfg['type']='number'; if($cfg['nullable'])$cfg['placeholder']='NULL'; }
    if (stripos($col['Type'],'text')!==false || stripos($col['Type'],'longtext')!==false) $cfg['type']='textarea';
    if (preg_match('/varchar\((\d+)\)/',$col['Type'],$m) && (int)$m[1]>200) $cfg['type']='textarea';
    if (stripos($col['Type'],'date')!==false && $cfg['type']==='text') $cfg['type']='datetime-local';
    return $cfg;
}

$columns = [];
$res = $mysqli->query("SHOW FULL COLUMNS FROM `{$entityType}`");
while ($col = $res->fetch_assoc()) $columns[] = $col;
$allFields = array_map('getFieldConfig', $columns);

$alwaysNames = ['id','name','description','prompt','prompt_negative','seed','regenerate_images','regenerate_audios','regenerate_videos'];
$systemNames = ['created_at','updated_at'];
$alwaysFields = $systemFields = $advancedFields = [];
foreach ($allFields as $f) {
    if (in_array($f['name'], $systemNames)) $systemFields[] = $f;
    elseif (in_array($f['name'], $alwaysNames) && array_key_exists($f['name'], $entity)) $alwaysFields[] = $f;
    else $advancedFields[] = $f;
}

// ── Modules ──
$registry    = ModuleRegistry::getInstance();
$gearMenu    = $registry->create('gear_menu',['position'=>'top-right','icon'=>'&#9881;','icon_size'=>'1.5em','show_for_entities'=>[$entityType,'depth_map']]);
if (!$isAudioEntity) {
    $gearMenu->addStandardActions($entityType,['overrides'=>[
        'delete'     => ['callback'=>'if(confirm("Delete?")){ window.deleteFrame(entity,entityId,frameId,wrapper); }'],
        'view_frame' => ['callback'=>'window.location.href="view_frame.php?frame_id="+frameId;'],
    ]]);
    $gearMenu->addAction('depth_map',['label'=>'View Frame','icon'=>'&#128065;','callback'=>'window.location.href="view_frame.php?frame_id="+frameId;']);
}
$imageEditor = $registry->create('image_editor',['modes'=>['mask','crop'],'show_transform_tab'=>true,'show_filters_tab'=>true,'enable_rotate'=>true,'enable_resize'=>true,'preset_filters'=>['grayscale','vintage','sepia','clarendon','gingham','moon','lark','reyes','juno','slumber']]);
$videoExtractor = new VideoFrameExtractorModule();

$pageTitle = ucfirst($entityType) . ' #' . $entityId . ': ' . ($entity['name'] ?? '—');
?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=0.85">
<title><?= htmlspecialchars($pageTitle) ?></title>
<script>
(function(){try{var t=localStorage.getItem('spw_theme');if(t==='dark'||t==='light')document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
</script>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css"/>
<link href="https://vjs.zencdn.net/8.5.2/video-js.css" rel="stylesheet"/>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="/js/toast.js"></script>
<script src="/js/gear_menu_globals.js"></script>
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/umd/photoswipe-lightbox.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/amplitudejs@5.3.2/dist/amplitude.js"></script>
<script src="https://vjs.zencdn.net/8.5.2/video.min.js"></script>

<style>
/* ─────────────────────────────────────────
   DESIGN TOKENS  (extend base.css vars)
───────────────────────────────────────── */
:root {
    --accent:      #8b5cf6;
    --accent-dim:  rgba(139,92,246,0.12);
    --accent-glow: rgba(139,92,246,0.35);
    --green:       #00e5a0;
    --green-dim:   rgba(0,229,160,0.1);
    --amber:       #f59e0b;
    --amber-dim:   rgba(245,158,11,0.1);
    --danger:      #ef4444;
    --danger-dim:  rgba(239,68,68,0.1);
    --radius:      6px;
    --radius-lg:   10px;
    --tap:         44px;
    --font:        'DM Mono','Fira Mono',monospace;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{background:var(--bg);color:var(--text);font-family:var(--font);min-height:100vh;overflow-x:hidden;}

/* ─────────────────────────────────────────
   LAYOUT
───────────────────────────────────────── */
.ent-wrap{max-width:900px;margin:0 auto;padding:0 0 80px;}

/* ─────────────────────────────────────────
   MASTHEAD
───────────────────────────────────────── */
.ent-masthead{
    display:flex;align-items:center;justify-content:space-between;
    padding:12px 16px;
    background:var(--card);
    border-bottom:1px solid var(--border);
    position:sticky;top:0;z-index:90;
    gap:10px;
}
.ent-masthead-left{display:flex;align-items:center;gap:10px;min-width:0;}
.ent-type-badge{
    font-size:0.6rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;
    padding:3px 8px;border-radius:3px;
    background:var(--accent-dim);border:1px solid var(--accent);color:var(--accent);
    white-space:nowrap;flex-shrink:0;
}
.ent-masthead-right{display:flex;align-items:center;gap:8px;flex-shrink:0;}

.curation-badge{
    font-size:0.65rem;font-weight:700;padding:3px 8px;border-radius:3px;
    background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.35);
    color:#10b981;cursor:pointer;white-space:nowrap;
}
.curation-badge:hover{background:rgba(16,185,129,0.2);}

/* ─────────────────────────────────────────
   SECTION BLOCKS
───────────────────────────────────────── */
.ent-section{
    margin:12px 12px 0;
    background:var(--card);
    border:1px solid var(--border);
    border-radius:var(--radius-lg);
    overflow:visible;
}
.ent-section-hd{
    display:flex;align-items:center;justify-content:space-between;
    padding:10px 14px;
    border-bottom:1px solid var(--border);
    font-size:0.7rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;
    color:var(--text-muted);
    cursor:pointer;
    user-select:none;
    -webkit-tap-highlight-color:transparent;
}
.ent-section-hd .hd-left{display:flex;align-items:center;gap:8px;}
.ent-section-hd .hd-icon{font-size:0.9rem;opacity:0.7;}
.ent-section-hd .hd-chevron{
    font-size:0.7rem;color:var(--text-muted);
    transition:transform 0.2s;margin-left:4px;
}
.ent-section.collapsed .hd-chevron{transform:rotate(-90deg);}
.ent-section-body{padding:14px;}
.ent-section.collapsed .ent-section-body{display:none;}
/* sections that are never collapsible */
.ent-section.always-open .ent-section-hd{cursor:default;}

/* ─────────────────────────────────────────
   FORM FIELDS — floating-label style
───────────────────────────────────────── */
.field-row{margin-bottom:10px;position:relative;}
.field-row+.field-row{margin-top:2px;}

/* Checkbox rows are different */
.field-row.is-checkbox{display:flex;align-items:center;gap:10px;padding:8px 0;}
.field-row.is-checkbox label{font-size:0.8rem;color:var(--text);cursor:pointer;display:flex;align-items:center;gap:8px;}
.field-row.is-checkbox input[type=checkbox]{width:18px;height:18px;accent-color:var(--accent);cursor:pointer;flex-shrink:0;}

/* Text / number inputs */
.field-input{
    width:100%;
    padding:10px 12px;
    background:rgba(0,0,0,0.25);
    border:1px solid var(--border);
    border-radius:var(--radius);
    color:var(--text);
    font-family:var(--font);
    font-size:0.85rem;
    transition:border-color 0.15s,background 0.15s;
}
.field-input::placeholder{color:var(--text-muted);opacity:0.7;}
.field-input:focus{outline:none;border-color:var(--accent);background:rgba(139,92,246,0.04);}
.field-input[readonly]{opacity:0.45;cursor:not-allowed;}

/* ── FIX 1: Textarea expand on focus / shrink on blur ──
   Collapsed: 2 lines tall. Expanded: grows up to 320px.
   JS adds/removes .expanded and manages inline style.height.
   On blur the inline style is fully cleared so the CSS
   collapsed rule takes effect again cleanly. */
textarea.field-input{
    resize:none;
    overflow:hidden;
    height:calc(1.5em * 2 + 22px);
    line-height:1.5;
    transition:height 0.2s ease,border-color 0.15s,background 0.15s;
}
textarea.field-input.expanded{
    overflow-y:auto;
}

/* Inline grid for two-column system fields */
.field-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
@media(max-width:480px){.field-grid-2{grid-template-columns:1fr;}}

/* ─────────────────────────────────────────
   SAVE BUTTON
───────────────────────────────────────── */
.ent-save-bar{
    display:flex;align-items:center;justify-content:space-between;
    padding:10px 14px;
    border-top:1px solid var(--border);
    gap:10px;
}
.btn-save{
    padding:10px 24px;border-radius:var(--radius);border:none;
    background:var(--accent);color:#fff;font-family:var(--font);
    font-size:0.8rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;
    cursor:pointer;transition:opacity 0.15s,transform 0.1s;
}
.btn-save:active{opacity:0.85;transform:scale(0.97);}
.btn-back{
    padding:10px 16px;border-radius:var(--radius);
    border:1px solid var(--border);background:transparent;
    color:var(--text-muted);font-family:var(--font);font-size:0.8rem;
    cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;
    transition:border-color 0.15s,color 0.15s;
}
.btn-back:hover{border-color:var(--text-muted);color:var(--text);}

/* ─────────────────────────────────────────
   NOTIFICATION
───────────────────────────────────────── */
.notif{
    margin:12px 12px 0;padding:10px 14px;border-radius:var(--radius);
    font-size:0.8rem;font-weight:600;
}
.notif-success{background:var(--green-dim);border:1px solid var(--green);color:var(--green);}
.notif-error{background:var(--danger-dim);border:1px solid var(--danger);color:var(--danger);}

/* ─────────────────────────────────────────
   IMAGE GRIDS  (enhanimatics style)
───────────────────────────────────────── */
.frames-grid-en{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(120px,1fr));
    gap:8px;
}
.f-card-en{
    aspect-ratio:1;background:#0a0a0f;
    border:2px solid var(--border);border-radius:var(--radius);
    position:relative;overflow:visible;
    cursor:pointer;transition:border-color 0.15s;
}
.f-card-en:hover{border-color:var(--accent);}
.f-link-en{display:block;width:100%;height:calc(100% - 22px);overflow:hidden;border-radius:calc(var(--radius) - 2px);}
.f-link-en img{width:100%;height:100%;object-fit:cover;display:block;transition:transform 0.2s;border-radius:calc(var(--radius) - 2px);}
.f-card-en:hover .f-link-en img{transform:scale(1.04);}
.f-label-en{
    position:absolute;bottom:0;left:0;right:0;height:22px;
    background:rgba(10,10,15,0.92);
    border-top:1px solid var(--border);
    padding:0 6px;
    display:flex;align-items:center;justify-content:space-between;
    font-size:0.58rem;color:var(--text-muted);
}
.f-gear-wrap{position:absolute;top:4px;right:4px;z-index:10;opacity:0;transition:opacity 0.15s;}
.f-card-en:hover .f-gear-wrap{opacity:1;}
/* special tag badges */
.f-badge{
    position:absolute;top:4px;left:4px;
    font-size:0.5rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;
    padding:2px 5px;border-radius:2px;z-index:5;
}
.f-badge-img2img{background:rgba(139,92,246,0.75);color:#fff;}
.f-badge-cnmap{background:rgba(245,158,11,0.75);color:#000;}
.f-badge-composite{background:rgba(0,229,160,0.7);color:#000;}
.f-badge-depth{background:rgba(100,100,200,0.7);color:#fff;}
/* special grid (3-col fixed for source images) */
.frames-grid-special{
    display:grid;grid-template-columns:repeat(3,1fr);gap:8px;
}
.f-card-en.empty{visibility:hidden;pointer-events:none;}

/* ─────────────────────────────────────────
   VIDEO SECTION  (vidbat style)
   FIX 2: player box is square (aspect-ratio 1/1)
───────────────────────────────────────── */
.video-run-grid{
    display:flex;flex-direction:row;gap:12px;
    overflow:hidden;
}
.video-player-box{
    flex:0 0 auto;
    width:min(55vw,420px);
    aspect-ratio:1/1;
    background:#000;border-radius:var(--radius);overflow:hidden;
    box-shadow:0 4px 16px rgba(0,0,0,0.4);
    display:flex;align-items:center;justify-content:center;
}
.video-js{width:100%;height:100%;}
.video-playlist-strip{
    flex:1;display:flex;flex-direction:column;gap:8px;
    overflow-y:auto;overflow-x:hidden;padding-bottom:4px;
}
.video-thumb-card{
    flex-shrink:0;display:flex;flex-direction:row;gap:0;
    background:rgba(0,0,0,0.3);border:1px solid var(--border);border-radius:var(--radius);
    overflow:hidden;cursor:pointer;transition:border-color 0.15s;height:68px;
}
.video-thumb-card:hover{border-color:var(--accent);}
.video-thumb-card.active{border-color:var(--accent);background:var(--accent-dim);}
.vt-img{width:100px;height:68px;object-fit:cover;flex-shrink:0;background:#000;}
.vt-info{padding:6px 8px;flex:1;min-width:0;display:flex;flex-direction:column;justify-content:center;gap:3px;}
.vt-title{font-size:0.7rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text);}
.vt-dur{font-size:0.62rem;color:var(--text-muted);}
.vt-actions{padding:4px 6px;display:flex;align-items:center;flex-shrink:0;}
.vt-detail-btn{
    padding:4px 8px;border-radius:3px;border:1px solid var(--accent);
    background:transparent;color:var(--accent);
    font-family:var(--font);font-size:0.6rem;font-weight:700;
    cursor:pointer;white-space:nowrap;
    -webkit-tap-highlight-color:transparent;
    transition:background 0.1s;
}
.vt-detail-btn:active{background:var(--accent-dim);}
@media(max-width:640px){
    .video-run-grid{flex-direction:column;height:auto;}
    .video-player-box{flex:none;width:100%;aspect-ratio:1/1;}
    .video-playlist-strip{flex-direction:row;overflow-x:auto;overflow-y:hidden;flex-wrap:nowrap;}
    .video-thumb-card{flex-direction:column;width:120px;height:auto;flex-shrink:0;}
    .vt-img{width:120px;height:70px;}
}

/* ─────────────────────────────────────────
   AUDIO SECTION  (same vibe)
───────────────────────────────────────── */
.audio-source-box{
    display:flex;align-items:center;gap:12px;
    padding:12px;background:rgba(0,0,0,0.2);
    border:1px solid var(--border);border-radius:var(--radius);margin-bottom:12px;
}
.audio-source-icon{font-size:1.6rem;flex-shrink:0;}
.audio-source-meta{flex:1;min-width:0;}
.audio-source-label{font-size:0.65rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px;}
.audio-source-meta audio{width:100%;height:32px;}
.audio-source-file{font-size:0.62rem;color:var(--text-muted);margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

/* Playlist */
.amplitude-list-box{
    border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;
}
.amplitude-player-mini{
    padding:12px 14px;background:rgba(0,0,0,0.3);
    border-bottom:1px solid var(--border);
    display:flex;flex-direction:column;gap:8px;
}
.mini-track-info{font-size:0.75rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.mini-controls-row{display:flex;align-items:center;gap:10px;}
.mini-play-btn{
    width:36px;height:36px;border-radius:50%;
    border:1px solid var(--accent);background:transparent;color:var(--accent);
    display:flex;align-items:center;justify-content:center;cursor:pointer;
    flex-shrink:0;font-size:0.9rem;
    -webkit-tap-highlight-color:transparent;transition:background 0.1s;
}
.mini-play-btn:active{background:var(--accent-dim);}
.mini-play-btn.amplitude-playing::before{content:'⏸';}
.mini-play-btn.amplitude-paused::before{content:'▶';}
input[type=range].mini-slider{
    flex:1;-webkit-appearance:none;height:3px;
    background:var(--border);border-radius:2px;cursor:pointer;
}
input[type=range].mini-slider::-webkit-slider-thumb{-webkit-appearance:none;width:12px;height:12px;border-radius:50%;background:var(--accent);margin-top:-4.5px;}
.mini-time{font-size:0.65rem;color:var(--text-muted);white-space:nowrap;}

.audio-track-row{
    display:flex;align-items:center;padding:8px 12px;
    border-bottom:1px solid var(--border);cursor:pointer;
    transition:background 0.1s;gap:10px;
}
.audio-track-row:last-child{border-bottom:none;}
.audio-track-row:hover{background:rgba(255,255,255,0.03);}
.audio-track-row.amplitude-active-song-container{background:var(--accent-dim);border-left:2px solid var(--accent);padding-left:10px;}
.at-icon{font-size:0.8rem;color:var(--text-muted);flex-shrink:0;}
.at-meta{flex:1;min-width:0;}
.at-title{font-size:0.78rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text);}
.at-sub{font-size:0.62rem;color:var(--text-muted);}
.at-date{font-size:0.6rem;color:var(--text-muted);white-space:nowrap;}

/* ─────────────────────────────────────────
   CURATION MODAL
───────────────────────────────────────── */
.modal-overlay-curation{
    position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:99999;
    display:none;align-items:center;justify-content:center;padding:16px;
}
.modal-overlay-curation.active{display:flex;}
.modal-curation-card{
    width:100%;max-width:640px;max-height:85vh;overflow-y:auto;
    background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);
    padding:20px;position:relative;
}
.modal-curation-close{
    position:absolute;top:12px;right:14px;
    background:none;border:none;color:var(--text-muted);font-size:1.3rem;cursor:pointer;
}
.modal-curation-close:hover{color:var(--text);}
.curation-row{display:flex;gap:12px;padding:8px 0;border-bottom:1px solid var(--border);font-size:0.82rem;}
.curation-lbl{font-weight:700;color:var(--text-muted);min-width:100px;flex-shrink:0;font-size:0.75rem;}
.curation-val{flex:1;color:var(--text);}
.pill{display:inline-block;padding:2px 8px;border-radius:12px;font-size:0.72rem;margin:2px;}
.pill-theme{border:1px solid var(--accent);color:var(--accent);background:var(--accent-dim);}
.pill-char{border:1px solid var(--amber);color:var(--amber);background:var(--amber-dim);}
.score-chip{
    display:inline-block;padding:4px 12px;border-radius:4px;
    background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.4);
    color:#10b981;font-weight:700;font-size:1.1rem;margin-bottom:12px;
}
.score-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:0.78rem;}
.score-item{display:flex;justify-content:space-between;padding:4px 0;}
.score-item strong{color:var(--text);}

/* ─────────────────────────────────────────
   COLLAPSIBLE ADVANCED (subtle toggle)
───────────────────────────────────────── */
.adv-toggle-btn{
    width:100%;padding:8px 14px;
    background:transparent;border:none;
    color:var(--text-muted);font-family:var(--font);font-size:0.7rem;font-weight:700;
    letter-spacing:1px;text-transform:uppercase;text-align:left;cursor:pointer;
    display:flex;align-items:center;gap:8px;
    -webkit-tap-highlight-color:transparent;
    transition:color 0.15s;
}
.adv-toggle-btn:hover{color:var(--text);}
.adv-toggle-btn .adv-chevron{transition:transform 0.2s;font-style:normal;}
.adv-toggle-btn.open .adv-chevron{transform:rotate(90deg);}
.adv-body{display:none;padding:0 14px 14px;}
.adv-body.open{display:block;}

/* ─────────────────────────────────────────
   MISC UTILS
───────────────────────────────────────── */
.text-muted{color:var(--text-muted);}
/* Gear menu — exact working CSS from entity_form.php */
.gear-menu,.gear-popup,.gear-menu-popup{position:absolute;z-index:99999!important;}
.frames-grid-en,.frames-grid-special,.f-card-en{overflow:visible!important;}
.pswp{z-index:999999;}

/* Frame View Modal / Iframe Modal */
.view-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 100000; display: none; align-items: center; justify-content: center; }
.view-modal.active { display: flex; }
.view-modal-content { width: 95vw; height: 95vh; background: #000; position: relative; border: 1px solid #444; box-shadow: 0 0 30px rgba(0,0,0,0.5); }
.view-close { position: absolute; top: 10px; right: 10px; width: 32px; height: 32px; background: rgba(0,0,0,0.8); color: #fff; border: 1px solid #444; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; z-index: 200; transition: all 0.2s; }
.view-close:hover { background: #fff; color: #000; }
iframe.frame-viewer { width: 100%; height: 100%; border: none; }
</style>
</head>
<body>
<?php echo $gearMenu->render(); ?>
<?php echo $imageEditor->render(); ?>
<?php echo $videoExtractor->render(); ?>

<!-- ════════════ MASTHEAD ════════════ -->
<div class="ent-masthead">
    <div class="ent-masthead-left">
        <span class="ent-type-badge"><?= htmlspecialchars($entityType) ?></span>
        <?php /* if ($entityType === 'sketches' && ($mgKgNodeId > 0 || $mgAgNodeId > 0)): ?>
            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center; margin-left:6px;">
                <span style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Mini Graph</span>
                <?php if ($mgKgNodeId > 0): ?>
                    <?php $kgUrl = "mini_graph.php?graph=kg&node_id={$mgKgNodeId}"; ?>
                    <a href="<?= htmlspecialchars($kgUrl) ?>" target="_blank" style="color:var(--text, #eee); text-decoration:none; font-size:0.85em;">🔮 KG</a>
                    <button onclick="openIframeModal('<?= htmlspecialchars($kgUrl, ENT_QUOTES) ?>')" style="background:none; border:1px solid #444; border-radius:4px; padding:2px 7px; cursor:pointer; color:#aaa; font-size:0.78em; font-family:var(--font);">⤢ modal</button>
                <?php endif; ?>
                <?php if ($mgAgNodeId > 0): ?>
                    <?php $agUrl = "mini_graph.php?graph=ag&doc_id={$mgLoreDocId}&node_id={$mgAgNodeId}"; ?>
                    <?php if ($mgKgNodeId > 0): ?><span style="color:#444;">|</span><?php endif; ?>
                    <a href="<?= htmlspecialchars($agUrl) ?>" target="_blank" style="color:var(--text, #eee); text-decoration:none; font-size:0.85em;">📜 AG</a>
                    <button onclick="openIframeModal('<?= htmlspecialchars($agUrl, ENT_QUOTES) ?>')" style="background:none; border:1px solid #444; border-radius:4px; padding:2px 7px; cursor:pointer; color:#aaa; font-size:0.78em; font-family:var(--font);">⤢ modal</button>
                <?php endif; ?>
            </div>
        <?php endif; */ ?>
    </div>
    <div class="ent-masthead-right">
        <?php if ($curationData): ?>
            <span class="curation-badge" onclick="openCurationModal()">🕵️ <?= $curationData['score'] ?></span>
        <?php endif; ?>
        <a href="<?= htmlspecialchars($redirectUrl) ?>" class="btn-back">← Back</a>
    </div>
</div>

<div class="ent-wrap">

<?php if ($notification): ?>
<div class="notif notif-<?= $notificationType ?>"><?= htmlspecialchars($notification) ?></div>
<?php endif; ?>

<!-- ════════════ CORE FIELDS ════════════ -->
<form method="post" action="entity_form.php?entity_type=<?= urlencode($entityType) ?>&entity_id=<?= $entityId ?>&redirect_url=<?= urlencode($redirectUrl) ?>">
<input type="hidden" name="action" value="update">
<input type="hidden" name="id" value="<?= $entityId ?>">

<div class="ent-section always-open">
    <div class="ent-section-hd" style="cursor:default;">
        <div class="hd-left">
            <span class="hd-icon">✏️</span>
            <?php if ($showFuzzGraph || ($entityType === 'sketches' && ($mgKgNodeId > 0 || $mgAgNodeId > 0))): ?>
                <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center; margin-left:6px;">
                    <?php if ($showFuzzGraph): ?>
                        <?php $fuzzUrl = "/fuzzgraph.php?entity={$entityType}&id={$entityId}"; ?>
                        <a href="<?= htmlspecialchars($fuzzUrl) ?>" target="_blank" style="color:var(--text, #eee); text-decoration:none; font-size:0.85em;">🕸️ Fuzz</a>
                        <button type="button" onclick="openIframeModal('<?= htmlspecialchars($fuzzUrl, ENT_QUOTES) ?>')" style="background:none; border:1px solid var(--border); border-radius:4px; padding:2px 7px; cursor:pointer; color:var(--text-muted); font-size:0.78em; font-family:var(--font);">⤢ modal</button>
                    <?php endif; ?>

                    <?php if ($entityType === 'sketches' && $mgKgNodeId > 0): ?>
                        <?php if ($showFuzzGraph): ?><span style="color:var(--border);">|</span><?php endif; ?>
                        <?php $kgUrl = "mini_graph.php?graph=kg&node_id={$mgKgNodeId}"; ?>
                        <a href="<?= htmlspecialchars($kgUrl) ?>" target="_blank" style="color:var(--text, #eee); text-decoration:none; font-size:0.85em;">🔮 KG</a>
                        <button type="button" onclick="openIframeModal('<?= htmlspecialchars($kgUrl, ENT_QUOTES) ?>')" style="background:none; border:1px solid var(--border); border-radius:4px; padding:2px 7px; cursor:pointer; color:var(--text-muted); font-size:0.78em; font-family:var(--font);">⤢ modal</button>
                    <?php endif; ?>
                    
                    <?php if ($entityType === 'sketches' && $mgAgNodeId > 0): ?>
                        <?php $agUrl = "mini_graph.php?graph=ag&doc_id={$mgLoreDocId}&node_id={$mgAgNodeId}"; ?>
                        <?php if ($showFuzzGraph || $mgKgNodeId > 0): ?><span style="color:var(--border);">|</span><?php endif; ?>
                        <a href="<?= htmlspecialchars($agUrl) ?>" target="_blank" style="color:var(--text, #eee); text-decoration:none; font-size:0.85em;">📜 AG</a>
                        <button type="button" onclick="openIframeModal('<?= htmlspecialchars($agUrl, ENT_QUOTES) ?>')" style="background:none; border:1px solid var(--border); border-radius:4px; padding:2px 7px; cursor:pointer; color:var(--text-muted); font-size:0.78em; font-family:var(--font);">⤢ modal</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="ent-section-body">

        <?php foreach ($alwaysFields as $f):
            $val = $entity[$f['name']] ?? '';
            $fid = 'f_' . $f['name'];
        ?>
        <div class="field-row <?= $f['type']==='checkbox' ? 'is-checkbox' : '' ?>">
            <?php if ($f['type'] === 'checkbox'): ?>
                <input type="hidden" name="<?= htmlspecialchars($f['name']) ?>" value="0" <?= $f['readonly']?'disabled':'' ?>>
                <input type="checkbox" id="<?= $fid ?>" name="<?= htmlspecialchars($f['name']) ?>" value="1" <?= $val?'checked':'' ?> <?= $f['readonly']?'disabled':'' ?>>
                <label for="<?= $fid ?>"><?= htmlspecialchars($f['label']) ?></label>
            <?php elseif ($f['type'] === 'textarea'): ?>
                <textarea id="<?= $fid ?>" name="<?= htmlspecialchars($f['name']) ?>"
                          class="field-input auto-grow"
                          placeholder="<?= htmlspecialchars($f['label']) ?>"
                          <?= $f['required']?'required':'' ?>
                          <?= $f['readonly']?'readonly':'' ?>><?= htmlspecialchars($val) ?></textarea>
            <?php else: ?>
                <input type="<?= $f['type'] ?>" id="<?= $fid ?>"
                       name="<?= htmlspecialchars($f['name']) ?>"
                       class="field-input"
                       placeholder="<?= htmlspecialchars($f['label']) ?><?= $f['nullable']?' (optional)':'' ?>"
                       value="<?= htmlspecialchars($val) ?>"
                       <?= $f['required']?'required':'' ?>
                       <?= $f['readonly']?'readonly':'' ?>>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

    </div>

    <!-- Advanced toggle -->
    <?php if (!empty($advancedFields) || !empty($systemFields)): ?>
    <button type="button" class="adv-toggle-btn" id="advToggle" onclick="toggleAdv()">
        <span class="adv-chevron">›</span> Advanced &amp; System Fields
    </button>
    <div class="adv-body" id="advBody">

        <?php if (!empty($advancedFields)): ?>
        <div style="padding-top:4px;padding-bottom:8px;font-size:0.65rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--text-muted);">Advanced</div>
        <?php foreach ($advancedFields as $f):
            $val = $entity[$f['name']] ?? '';
            $fid = 'fa_' . $f['name'];
        ?>
        <div class="field-row <?= $f['type']==='checkbox' ? 'is-checkbox' : '' ?>" style="margin-bottom:8px;">
            <?php if ($f['type'] === 'checkbox'): ?>
                <input type="hidden" name="<?= htmlspecialchars($f['name']) ?>" value="0" <?= $f['readonly']?'disabled':'' ?>>
                <input type="checkbox" id="<?= $fid ?>" name="<?= htmlspecialchars($f['name']) ?>" value="1" <?= $val?'checked':'' ?> <?= $f['readonly']?'disabled':'' ?>>
                <label for="<?= $fid ?>"><?= htmlspecialchars($f['label']) ?></label>
            <?php elseif ($f['type'] === 'textarea'): ?>
                <textarea id="<?= $fid ?>" name="<?= htmlspecialchars($f['name']) ?>"
                          class="field-input auto-grow"
                          placeholder="<?= htmlspecialchars($f['label']) ?>"
                          <?= $f['required']?'required':'' ?>
                          <?= $f['readonly']?'readonly':'' ?>><?= htmlspecialchars($val) ?></textarea>
            <?php else: ?>
                <input type="<?= $f['type'] ?>" id="<?= $fid ?>"
                       name="<?= htmlspecialchars($f['name']) ?>"
                       class="field-input"
                       placeholder="<?= htmlspecialchars($f['label']) ?><?= $f['nullable']?' (optional)':'' ?>"
                       value="<?= htmlspecialchars($val) ?>"
                       <?= $f['required']?'required':'' ?>
                       <?= $f['readonly']?'readonly':'' ?>>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($systemFields)): ?>
        <div style="padding-top:12px;padding-bottom:8px;font-size:0.65rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--text-muted);">System (Read-only)</div>
        <div class="field-grid-2">
        <?php foreach ($systemFields as $f):
            $val = $entity[$f['name']] ?? '';
            if ($val) $val = date('Y-m-d H:i:s', strtotime($val));
        ?>
            <div class="field-row">
                <input type="text" class="field-input" placeholder="<?= htmlspecialchars($f['label']) ?>" value="<?= htmlspecialchars($val) ?>" readonly>
            </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="ent-save-bar">
        <a href="<?= htmlspecialchars($redirectUrl) ?>" class="btn-back">← Back</a>
        <button type="submit" class="btn-save">Save Changes</button>
    </div>
</div>
</form>

<!-- ════════════ AUDIO: SOURCE ════════════ -->
<?php if ($isAudioEntity && $sourceAudioFile): ?>
<div class="ent-section always-open">
    <div class="ent-section-hd" style="cursor:default;">
        <div class="hd-left"><span class="hd-icon">🎤</span> Source Audio (Wav2Wav)</div>
    </div>
    <div class="ent-section-body">
        <div class="audio-source-box">
            <span class="audio-source-icon">🎤</span>
            <div class="audio-source-meta">
                <div class="audio-source-label">Current Source</div>
                <audio controls src="<?= htmlspecialchars($sourceAudioFile) ?>" preload="metadata"></audio>
                <div class="audio-source-file"><?= htmlspecialchars(basename($sourceAudioFile)) ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════════════ AUDIO: RESULTS ════════════ -->
<?php if (!empty($resultAudios)): ?>
<div class="ent-section" id="section-audio">
    <div class="ent-section-hd" onclick="toggleSection('section-audio')">
        <div class="hd-left"><span class="hd-icon">🎵</span> Result Audios (<?= count($resultAudios) ?>)</div>
        <span class="hd-chevron">›</span>
    </div>
    <div class="ent-section-body">
        <div class="amplitude-list-box">
            <div class="amplitude-player-mini" id="audio-mini-player">
                <div class="mini-track-info" amplitude-song-info="name" amplitude-main-song-info="true">Select a track</div>
                <div class="mini-controls-row">
                    <div class="mini-play-btn amplitude-play-pause" amplitude-main-play-pause="true"></div>
                    <input type="range" class="mini-slider" amplitude-song-slider="true" amplitude-main-song-slider="true"/>
                    <span class="mini-time"><span amplitude-current-time="true" amplitude-main-current-time="true">0:00</span></span>
                </div>
            </div>
            <?php foreach ($resultAudios as $idx => $au): ?>
            <div class="audio-track-row amplitude-play-pause" data-amplitude-song-index="<?= $idx ?>">
                <span class="at-icon">♪</span>
                <div class="at-meta">
                    <div class="at-title"><?= htmlspecialchars($au['name'] ?: 'Audio #'.$au['id']) ?></div>
                    <div class="at-sub"><?= htmlspecialchars($au['rvc_model_name'] ?? 'Raw') ?></div>
                </div>
                <span class="at-date"><?= date('M d', strtotime($au['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script>
Amplitude.init({ songs: <?= json_encode(array_map(fn($a)=>['name'=>$a['name']?:'Audio #'.$a['id'],'artist'=>$a['rvc_model_name']??'Raw','url'=>$a['filename']], $resultAudios)) ?>, default_album_art:'/img/audio_placeholder.png' });
</script>
<?php endif; ?>

<!-- ════════════ VIDEOS ════════════ -->
<?php if (!empty($resultVideos)): ?>
<?php $uniqueVid = 'vp_'.$entityType.'_'.$entityId; $firstVid = $resultVideos[0]; ?>
<div class="ent-section" id="section-video">
    <div class="ent-section-hd" onclick="toggleSection('section-video')">
        <div class="hd-left"><span class="hd-icon">🎬</span> <?= htmlspecialchars($videoSectionTitle) ?> (<?= count($resultVideos) ?>)</div>
        <span class="hd-chevron">›</span>
    </div>
    <div class="ent-section-body">
        <div class="video-run-grid">
            <div class="video-player-box">
                <video id="player-<?= $uniqueVid ?>" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto">
                    <source src="/<?= ltrim($firstVid['url'],'/') ?>" type="video/mp4">
                </video>
            </div>
            <div class="video-playlist-strip">
                <?php foreach ($resultVideos as $vi => $vid):
                    $vUrl  = '/'.ltrim($vid['url'],'/');
                    $thumb = '/'.ltrim($vid['thumbnail'] ?? 'img/video_placeholder.png','/');
                    $vName = $vid['name'] ?: 'Video #'.$vid['id'];
                    $dur   = $vid['duration'] ? floor($vid['duration']).'s' : '';
                ?>
                <div class="video-thumb-card <?= $vi===0?'active':'' ?>"
                     onclick="changeVideo('<?= $uniqueVid ?>','<?= htmlspecialchars($vUrl) ?>',this,<?= $vid['id'] ?>)">
                    <img class="vt-img" src="<?= htmlspecialchars($thumb) ?>" loading="lazy">
                    <div class="vt-info">
                        <div class="vt-title" title="<?= htmlspecialchars($vName) ?>"><?= htmlspecialchars($vName) ?></div>
                        <div class="vt-dur"><?= $dur ?></div>
                    </div>
                    <div class="vt-actions">
                        <button class="vt-detail-btn" onclick="event.stopPropagation();openVidDetail(<?= $vid['id'] ?>)">🎬</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════════════ SOURCE FRAMES ════════════ -->
<?php if (!$isAudioEntity && !empty($specialFrames)): ?>
<div class="ent-section always-open" id="section-special">
    <div class="ent-section-hd" style="cursor:default;">
        <div class="hd-left"><span class="hd-icon">🖼️</span> Source Images (img2img / CN Map)</div>
    </div>
    <div class="ent-section-body">
        <div class="frames-grid-special pswp-gallery" id="pswp-special">
            <?php foreach (['img2img','cnmap'] as $k):
                if (!isset($specialFrames[$k])) continue;
                $sf = $specialFrames[$k];
                $file = '/'.ltrim($sf['filename'],'/')
            ?>
            <div class="f-card-en img-wrapper" data-frame-id="<?= $sf['id'] ?>" data-entity="<?= htmlspecialchars($entityType) ?>" data-entity-id="<?= $entityId ?>">
                <a href="<?= htmlspecialchars($file) ?>" class="f-link-en pswp-gallery-item" data-pswp-src="<?= htmlspecialchars($file) ?>" data-pswp-width="1024" data-pswp-height="1024">
                    <img src="<?= htmlspecialchars($file) ?>" alt="#<?= $sf['id'] ?>" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                </a>
                <span class="f-badge f-badge-<?= $k ?>"><?= strtoupper($k) ?></span>
                <div class="f-label-en"><span>#<?= $sf['id'] ?></span></div>
            </div>
            <?php endforeach; ?>
            <?php for ($i=count($specialFrames); $i<3; $i++): ?><div class="f-card-en empty"></div><?php endfor; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════════════ COMPOSITE FRAMES ════════════ -->
<?php if (!$isAudioEntity && !empty($compositeFrames)): ?>
<div class="ent-section always-open" id="section-composite">
    <div class="ent-section-hd" style="cursor:default;">
        <div class="hd-left"><span class="hd-icon">🔲</span> Composite Frames (<?= count($compositeFrames) ?>)</div>
    </div>
    <div class="ent-section-body">
        <div class="frames-grid-en pswp-gallery" id="pswp-composite">
            <?php foreach ($compositeFrames as $cf):
                $file = '/'.ltrim($cf['filename'],'/');
                $name = htmlspecialchars($cf['name']?:'Frame '.$cf['id']);
            ?>
            <div class="f-card-en img-wrapper" data-frame-id="<?= $cf['id'] ?>" data-entity="<?= htmlspecialchars($entityType) ?>" data-entity-id="<?= $entityId ?>" data-type="composite">
                <a href="<?= htmlspecialchars($file) ?>" class="f-link-en pswp-gallery-item" data-pswp-src="<?= htmlspecialchars($file) ?>" data-pswp-width="1024" data-pswp-height="1024">
                    <img src="<?= htmlspecialchars($file) ?>" alt="<?= $name ?>" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                </a>
                <span class="f-badge f-badge-composite">C</span>
                <div class="f-gear-wrap"><?php /* gear injected by GearMenu.attach */ ?></div>
                <div class="f-label-en"><span><?= $name ?></span></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════════════ FRAMES ════════════ -->
<?php if (!$isAudioEntity && !empty($frames)): ?>
<div class="ent-section always-open" id="section-frames">
    <div class="ent-section-hd" style="cursor:default;">
        <div class="hd-left"><span class="hd-icon">🎨</span> Frames (<?= count($frames) ?>)</div>
    </div>
    <div class="ent-section-body">
        <div class="frames-grid-en pswp-gallery" id="pswp-frames">
            <?php foreach ($frames as $fr):
                $file = '/'.ltrim($fr['filename'],'/');
                $name = htmlspecialchars($fr['name']?:'Frame '.$fr['id']);
            ?>
            <div class="f-card-en img-wrapper" data-frame-id="<?= $fr['id'] ?>" data-entity="<?= htmlspecialchars($entityType) ?>" data-entity-id="<?= $entityId ?>">
                <a href="<?= htmlspecialchars($file) ?>" class="f-link-en pswp-gallery-item" data-pswp-src="<?= htmlspecialchars($file) ?>" data-pswp-width="1024" data-pswp-height="1024">
                    <img src="<?= htmlspecialchars($file) ?>" alt="<?= $name ?>" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                </a>
                <div class="f-gear-wrap"></div>
                <div class="f-label-en"><span><?= $name ?></span></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════════════ DEPTH MAPS ════════════ -->
<?php if (!$isAudioEntity && !empty($depthFrames)): ?>
<div class="ent-section always-open" id="section-depth">
    <div class="ent-section-hd" style="cursor:default;">
        <div class="hd-left"><span class="hd-icon">🌊</span> Depth Maps (<?= count($depthFrames) ?>)</div>
    </div>
    <div class="ent-section-body">
        <div class="frames-grid-en pswp-gallery" id="pswp-depth">
            <?php foreach ($depthFrames as $df):
                $file = '/'.ltrim($df['filename'],'/');
                $name = htmlspecialchars($df['name']);
            ?>
            <div class="f-card-en img-wrapper" data-frame-id="<?= $df['id'] ?>" data-entity="depth_map" data-entity-id="<?= $entityId ?>" data-type="depth">
                <a href="<?= htmlspecialchars($file) ?>" class="f-link-en pswp-gallery-item" data-pswp-src="<?= htmlspecialchars($file) ?>" data-pswp-width="1024" data-pswp-height="1024">
                    <img src="<?= htmlspecialchars($file) ?>" alt="<?= $name ?>" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                </a>
                <span class="f-badge f-badge-depth">D</span>
                <div class="f-gear-wrap"></div>
                <div class="f-label-en"><span><?= $name ?></span></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

</div><!-- /.ent-wrap -->

<!-- ════════════ CURATION MODAL ════════════ -->
<?php if ($curationData): ?>
<div class="modal-overlay-curation" id="curationModal">
    <div class="modal-curation-card">
        <button class="modal-curation-close" onclick="closeCurationModal()">✕</button>
        <h3 style="font-size:0.9rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin-bottom:14px;color:var(--text);">🕵️ Narrative Analysis</h3>
        <div class="score-chip"><?= $curationData['score'] ?></div>
        <?php $c=$curationData['class'] ?? []; ?>
        <?php if(!empty($c['narrative_function'])): ?><div class="curation-row"><span class="curation-lbl">Function</span><span class="curation-val"><?= htmlspecialchars($c['narrative_function']) ?></span></div><?php endif; ?>
        <?php if(!empty($c['emotional_tone'])): ?><div class="curation-row"><span class="curation-lbl">Tone</span><span class="curation-val"><?= htmlspecialchars($c['emotional_tone']) ?></span></div><?php endif; ?>
        <?php $th=$curationData['themes']['primary_themes'] ?? []; if($th): ?>
        <div class="curation-row"><span class="curation-lbl">Themes</span><span class="curation-val"><?php foreach((array)$th as $t) echo '<span class="pill pill-theme">'.htmlspecialchars($t).'</span>'; ?></span></div>
        <?php endif; ?>
        <?php $chars=$curationData['entities']['characters'] ?? []; if($chars): ?>
        <div class="curation-row"><span class="curation-lbl">Characters</span><span class="curation-val"><?php foreach($chars as $ch) echo '<span class="pill pill-char">'.htmlspecialchars($ch).'</span>'; ?></span></div>
        <?php endif; ?>
        <?php $recs=$curationData['recs'] ?? []; if(!empty($recs['potential_use'])): ?>
        <div style="margin-top:12px;padding:10px;background:var(--amber-dim);border:1px dashed rgba(245,158,11,0.4);border-radius:var(--radius);font-size:0.78rem;color:var(--amber);">💡 <?= htmlspecialchars($recs['potential_use']) ?></div>
        <?php endif; ?>
        <?php $sb=$curationData['score_breakdown'] ?? []; if($sb): ?>
        <div style="margin-top:14px;font-size:0.65rem;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px;">Score Breakdown</div>
        <div class="score-grid">
            <?php foreach(['narrative_completeness'=>'Narrative','visual_impact'=>'Visual','production_readiness'=>'Production','visual_distinctiveness'=>'Distinctiveness'] as $k=>$lbl): if(isset($sb[$k])): ?>
            <div class="score-item"><span class="text-muted"><?= $lbl ?></span><strong><?= $sb[$k] ?></strong></div>
            <?php endif; endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Frame View / Mini Graph Modal -->
<div class="view-modal" id="viewModal">
    <div class="view-modal-content">
        <div class="view-close" onclick="closeIframeModal()"><i class="bi bi-x-lg"></i></div>
        <iframe id="frameViewer" class="frame-viewer" src=""></iframe>
    </div>
</div>

<script>
// ── Section toggle ──
function toggleSection(id) {
    const s = document.getElementById(id);
    if (!s) return;
    s.classList.toggle('collapsed');
    try { localStorage.setItem('ent_sec_'+id, s.classList.contains('collapsed')?'0':'1'); } catch(e){}
}
// Restore section states
document.querySelectorAll('.ent-section[id]').forEach(s => {
    if (s.classList.contains('always-open')) return;
    try {
        const saved = localStorage.getItem('ent_sec_'+s.id);
        if (saved === '0') s.classList.add('collapsed');
        else if (saved === '1') s.classList.remove('collapsed');
    } catch(e){}
});

// ── Advanced toggle ──
function toggleAdv() {
    const btn  = document.getElementById('advToggle');
    const body = document.getElementById('advBody');
    if (!btn || !body) return;
    const open = body.classList.toggle('open');
    btn.classList.toggle('open', open);
    try { localStorage.setItem('ent_adv_<?= $entityType ?>_<?= $entityId ?>', open?'1':'0'); } catch(e){}
}
try { if (localStorage.getItem('ent_adv_<?= $entityType ?>_<?= $entityId ?>') === '1') { document.getElementById('advBody')?.classList.add('open'); document.getElementById('advToggle')?.classList.add('open'); } } catch(e){}

// ── FIX 1: Textarea expand on focus / shrink on blur ──
document.addEventListener('focus', function(e) {
    if (!e.target.classList.contains('auto-grow')) return;
    const el = e.target;
    el.classList.add('expanded');
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 320) + 'px';
}, true);
document.addEventListener('blur', function(e) {
    if (!e.target.classList.contains('auto-grow')) return;
    const el = e.target;
    el.classList.remove('expanded');
    el.style.height = '';
    el.style.maxHeight = '';
    el.style.overflowY = '';
}, true);
document.addEventListener('input', function(e) {
    if (!e.target.classList.contains('auto-grow') || !e.target.classList.contains('expanded')) return;
    const el = e.target;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 320) + 'px';
});

// ── Curation modal ──
function openCurationModal() { document.getElementById('curationModal')?.classList.add('active'); }
function closeCurationModal() { document.getElementById('curationModal')?.classList.remove('active'); }
document.getElementById('curationModal')?.addEventListener('click', e => { if(e.target.id==='curationModal') closeCurationModal(); });

// ── PhotoSwipe ──
function initPswpGallery(id) {
    const el = document.getElementById(id);
    if (!el || typeof PhotoSwipeLightbox === 'undefined') return;
    try {
        new PhotoSwipeLightbox({
            gallery: el,
            children: 'a.f-link-en',
            pswpModule: PhotoSwipe,
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        }).init();
    } catch(e) {}
}
document.addEventListener('DOMContentLoaded', function() {
    initPswpGallery('pswp-frames');
    initPswpGallery('pswp-depth');
    initPswpGallery('pswp-composite');
    initPswpGallery('pswp-special');
});

// ── Video player (vidbat style) ──
let currentDetailVideoId = <?= !empty($resultVideos) ? $resultVideos[0]['id'] : 'null' ?>;

document.querySelectorAll('.video-js').forEach(el => {
    if (!el.player) {
        const vp = videojs(el, { controls:true, preload:'auto', fill:true, fluid:true });
        vp.on('ended', function() {
            const active = el.closest('.video-run-grid')?.querySelector('.video-thumb-card.active');
            if (active?.nextElementSibling) active.nextElementSibling.click();
        });
    }
});

window.changeVideo = function(uid, url, cardEl, vidId) {
    currentDetailVideoId = vidId;
    const pid = 'player-'+uid;
    const pel = document.getElementById(pid);
    cardEl.closest('.video-playlist-strip')?.querySelectorAll('.video-thumb-card').forEach(c=>c.classList.remove('active'));
    cardEl.classList.add('active');
    if (pel) {
        const vp = videojs.getPlayer ? videojs.getPlayer(pid) : null;
        if (vp) { vp.src({type:'video/mp4',src:url}); vp.play().catch(()=>{}); }
        else { pel.src=url; pel.play?.().catch(()=>{}); }
    }
};

window.openVidDetail = function(videoId) {
    if (typeof window.showVideoDetailsModal === 'function') window.showVideoDetailsModal(videoId);
    else if (window.parent && typeof window.parent.showVideoDetailsModal === 'function') window.parent.showVideoDetailsModal(videoId);
};

// ── GearMenu attach ──
$(document).ready(function() {
    if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
        document.querySelectorAll('.frames-grid-en').forEach(g => { try { window.GearMenu.attach(g); } catch(e){} });
        document.querySelectorAll('.frames-grid-special').forEach(g => { try { window.GearMenu.attach(g); } catch(e){} });
    }
    if (window.deleteFrame) {
        const orig = window.deleteFrame;
        window.deleteFrame = function(entity, entityId, frameId, wrapper) {
            orig(entity, entityId, frameId);
            $(wrapper).fadeOut(350, function(){ $(this).remove(); });
        };
    }
});

// ── Iframe Modal ──
function openIframeModal(url) {
    document.getElementById('frameViewer').src = url;
    document.getElementById('viewModal').classList.add('active');
}
function closeIframeModal() {
    document.getElementById('viewModal').classList.remove('active');
    setTimeout(() => { document.getElementById('frameViewer').src = ''; }, 200);
}
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeIframeModal(); });
</script>

<?php ob_start(); include __DIR__ . '/modal_frame_details.php'; echo ob_get_clean(); ?>
<script src="/js/theme-manager.js"></script>

<?php // echo $eruda; ?>

</body>
</html>