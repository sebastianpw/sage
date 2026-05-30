<?php
// public/view_datamining_storyboards.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require "entity_icons.php";

use App\UI\Modules\ModuleRegistry;

// ---------------------------------------------------------------------------
// AJAX — detected via X-Requested-With header, must exit before any HTML
// ---------------------------------------------------------------------------
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action'])
) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'];

    // ------------------------------------------------------------------
    // Core graph builder — reads storyboard_frames bipartite graph
    // ------------------------------------------------------------------
    function buildGraph($pdo) {
        $rows = $pdo->query("
            SELECT sf.frame_id, sf.storyboard_id,
                   s.name      AS sb_name,
                   sc.name     AS cat_name,
                   sc.code     AS cat_code
            FROM   storyboard_frames sf
            JOIN   storyboards s ON s.id = sf.storyboard_id
            LEFT JOIN storyboard_categories sc ON sc.id = s.category_id
            WHERE  s.is_archived = 0 AND sf.frame_id IS NOT NULL
        ")->fetchAll(PDO::FETCH_ASSOC);

        $frameToBoards = [];  // frame_id -> [ sb_id => [name, cat_name, cat_code] ]
        $boardToFrames = [];  // sb_id    -> [ frame_id, ... ]
        foreach ($rows as $r) {
            $fid = (int)$r['frame_id'];
            $sid = (int)$r['storyboard_id'];
            $frameToBoards[$fid][$sid] = ['name'=>$r['sb_name'],'cat_name'=>$r['cat_name'],'cat_code'=>$r['cat_code']];
            $boardToFrames[$sid][]     = $fid;
        }
        return [$frameToBoards, $boardToFrames];
    }

    // ------------------------------------------------------------------
    // Full frame info fetch — mirrors getFramesForRun in original view
    // Returns map: frame_id => frame_data_array
    // ------------------------------------------------------------------
    function fetchFramesFull($pdo, array $ids) {
        if (empty($ids)) return [];
        $in  = implode(',', array_map('intval', $ids));
        $sql = "
            SELECT
                f.id AS frame_id, f.name AS frame_name, f.filename,
                f.prompt, f.entity_type, f.entity_id, f.rating,
                f.img2img_frame_id,
                ie.tool AS edit_tool, ie.note AS edit_note,
                s.description AS full_sketch_desc,
                gc.title      AS gen_config_title,
                st.name       AS template_name,
                st.core_idea  AS template_core,
                st.shot_type, st.camera_angle, st.perspective,
                intr.name     AS interaction_name,
                ms.id         AS meta_id,
                sa.overall_quality, sa.classification, sa.scoring,
                sa.entities, sa.thematics, sa.recommendations
            FROM frames f
            LEFT JOIN image_edits ie    ON ie.derived_frame_id = f.id
            LEFT JOIN sketches s        ON s.id = f.entity_id AND f.entity_type = 'sketches'
            LEFT JOIN meta_sketches ms  ON ms.sketch_id = s.id
            LEFT JOIN generator_config gc ON gc.id = ms.desc_gen_config_id
            LEFT JOIN sketch_templates st ON st.id = ms.sketch_template_id
            LEFT JOIN interactions intr   ON intr.id = ms.interaction_id
            LEFT JOIN sketch_analysis sa  ON sa.sketch_id = s.id
            WHERE f.id IN ($in)
        ";
        $frames = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // Ingredients
        $sketchIds = [];
        foreach ($frames as $f)
            if ($f['entity_type'] === 'sketches' && $f['entity_id'] > 0) $sketchIds[] = (int)$f['entity_id'];
        $sketchIds = array_values(array_unique($sketchIds));

        $ingredientsMap = []; $genConfigIds = [];
        if (!empty($sketchIds)) {
            $q  = implode(',', array_fill(0, count($sketchIds), '?'));
            $st = $pdo->prepare("SELECT * FROM sketch_ingredients WHERE sketch_id IN ($q) ORDER BY sort_order ASC");
            $st->execute($sketchIds);
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $ingredientsMap[$row['sketch_id']][] = $row;
                if (in_array($row['ingredient_type'],['generator_config_desc','generator_config_name']) && $row['source_id'])
                    $genConfigIds[] = $row['source_id'];
            }
        }
        $genTitles = [];
        if (!empty($genConfigIds)) {
            $genConfigIds = array_values(array_unique($genConfigIds));
            $q  = implode(',', array_fill(0, count($genConfigIds), '?'));
            $st = $pdo->prepare("SELECT id, title FROM generator_config WHERE id IN ($q)");
            $st->execute($genConfigIds);
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) $genTitles[$r['id']] = $r['title'];
        }

        foreach ($frames as &$f) {
            $f['normalized_ingredients'] = [];
            $sid = $f['entity_id'];
            if (isset($ingredientsMap[$sid])) {
                foreach ($ingredientsMap[$sid] as $ing) {
                    $snap  = json_decode($ing['snapshot_data'] ?? '[]', true);
                    $label = $snap['name'] ?? ucfirst(str_replace('_',' ',$ing['ingredient_type']));
                    if ($ing['ingredient_type'] === 'generator_config_desc') $label = 'Gen (Desc): '.($genTitles[$ing['source_id']]??'Unknown');
                    if ($ing['ingredient_type'] === 'generator_config_name') $label = 'Gen (Name): '.($genTitles[$ing['source_id']]??'Unknown');
                    $f['normalized_ingredients'][] = ['type'=>$ing['ingredient_type'],'label'=>$label,'detail'=>$ing['prompt_fragment']??''];
                }
            } elseif (!empty($f['meta_id'])) {
                if ($f['gen_config_title'])  $f['normalized_ingredients'][] = ['type'=>'generator_config','label'=>'Generator','detail'=>$f['gen_config_title']];
                if ($f['template_name'])     $f['normalized_ingredients'][] = ['type'=>'sketch_template','label'=>'Template: '.$f['template_name'],'detail'=>$f['template_core']];
                if ($f['interaction_name'])  $f['normalized_ingredients'][] = ['type'=>'interaction','label'=>'Interaction','detail'=>$f['interaction_name']];
            }
            $f['curation'] = null;
            if (!empty($f['classification'])) {
                $f['curation'] = [
                    'score'=>$f['overall_quality'],
                    'class'=>json_decode($f['classification'],true),
                    'score_breakdown'=>json_decode($f['scoring'],true),
                    'entities'=>json_decode($f['entities'],true),
                    'themes'=>json_decode($f['thematics'],true),
                    'recs'=>json_decode($f['recommendations'],true),
                ];
            }
        }
        unset($f);

        $map = [];
        foreach ($frames as $f) $map[(int)$f['frame_id']] = $f;
        return $map;
    }

    // Helper: attach storyboard list to frame result array
    function attachBoards($info, array $ids, array $ftb) {
        $result = [];
        foreach ($ids as $fid) {
            $fi = $info[$fid] ?? null; if (!$fi) continue;
            $fi['storyboards'] = array_values($ftb[$fid] ?? []);
            $result[] = $fi;
        }
        return $result;
    }

    // ------------------------------------------------------------------
    // DEGREE
    // ------------------------------------------------------------------
    if ($action === 'degree') {
        [$ftb, $btf] = buildGraph($pdo);
        $min      = max(1,(int)($_POST['min_degree']??2));
        $max      = max(1,(int)($_POST['max_degree']??999));
        $limit    = min((int)($_POST['limit']??60),200);
        $minRating = (int)($_POST['min_rating']??0);
        $deg   = [];
        foreach ($ftb as $fid=>$boards) { $d=count($boards); if($d>=$min&&$d<=$max) $deg[$fid]=$d; }
        arsort($deg);
        $ids   = array_slice(array_keys($deg),0,$limit*3,true); // fetch extra to allow rating filter
        $info  = fetchFramesFull($pdo,$ids);
        $result= [];
        foreach ($ids as $fid) {
            $fi=$info[$fid]??null; if(!$fi) continue;
            if($minRating>0 && (int)($fi['rating']??0)<$minRating) continue;
            $fi['degree']=$deg[$fid]; $fi['storyboards']=array_values($ftb[$fid]);
            $result[]=$fi;
            if(count($result)>=$limit) break;
        }
        echo json_encode(['success'=>true,'concept'=>'degree','frames'=>$result,'total'=>count($deg)]);
        exit;
    }

    // ------------------------------------------------------------------
    // ORPHANS
    // ------------------------------------------------------------------
    if ($action === 'orphans') {
        [$ftb,] = buildGraph($pdo);
        $limit     = min((int)($_POST['limit']??60),200);
        $minRating = (int)($_POST['min_rating']??0);
        $linked    = array_flip(array_keys($ftb));
        $singles   = [];
        foreach ($ftb as $fid=>$boards) if(count($boards)===1) $singles[]=$fid;
        $allIds    = $pdo->query("SELECT id FROM frames ORDER BY id DESC LIMIT 10000")->fetchAll(PDO::FETCH_COLUMN);
        $unlinked  = [];
        foreach ($allIds as $fid) { $fid=(int)$fid; if(!isset($linked[$fid])) $unlinked[]=$fid; }
        $half      = (int)($limit/2);
        $ids       = array_unique(array_merge(array_slice($unlinked,0,$half*3),array_slice($singles,0,$half*3)));
        $info      = fetchFramesFull($pdo,$ids);
        $result    = [];
        foreach ($ids as $fid) {
            $fi=$info[$fid]??null; if(!$fi) continue;
            if($minRating>0 && (int)($fi['rating']??0)<$minRating) continue;
            $boards=$ftb[$fid]??[];
            $fi['degree']=count($boards); $fi['orphan_type']=count($boards)===0?'unlinked':'singleton';
            $fi['storyboards']=array_values($boards);
            $result[]=$fi;
            if(count($result)>=$limit) break;
        }
        echo json_encode(['success'=>true,'concept'=>'orphans','frames'=>$result,
            'stats'=>['unlinked'=>count($unlinked),'singletons'=>count($singles)]]);
        exit;
    }

    // ------------------------------------------------------------------
    // CLUSTERING
    // ------------------------------------------------------------------
    if ($action === 'clustering') {
        [$ftb,$btf] = buildGraph($pdo);
        $limit     = min((int)($_POST['limit']??60),200);
        $minRating = (int)($_POST['min_rating']??0);
        $pivot     = (int)($_POST['pivot_sb']??0);
        if (!$pivot) { $mx=0; foreach($btf as $sid=>$f) if(count($f)>$mx){$mx=count($f);$pivot=$sid;} }
        $shared    = [];
        foreach ($btf[$pivot]??[] as $fid) foreach($ftb[$fid] as $sid=>$si) $shared[$sid]=($shared[$sid]??0)+1;
        arsort($shared);
        $related   = [];
        foreach (array_keys($shared) as $sid) foreach($btf[$sid]??[] as $fid) $related[$fid]=($related[$fid]??0)+$shared[$sid];
        arsort($related);
        $ids       = array_slice(array_keys($related),0,$limit*3,true);
        $st        = $pdo->prepare("SELECT name FROM storyboards WHERE id=?"); $st->execute([$pivot]);
        $pname     = $st->fetchColumn()?:"SB #$pivot";
        $info      = fetchFramesFull($pdo,$ids);
        $result    = [];
        foreach ($ids as $fid) {
            $fi=$info[$fid]??null; if(!$fi) continue;
            if($minRating>0 && (int)($fi['rating']??0)<$minRating) continue;
            $fi['degree']=count($ftb[$fid]??[]); $fi['cluster_score']=$related[$fid];
            $fi['storyboards']=array_values($ftb[$fid]??[]);
            $result[]=$fi;
            if(count($result)>=$limit) break;
        }
        echo json_encode(['success'=>true,'concept'=>'clustering','pivot_sb'=>$pivot,
            'pivot_name'=>$pname,'frames'=>$result,'co_occurring_boards'=>count($shared)]);
        exit;
    }

    // ------------------------------------------------------------------
    // COMMUNITIES
    // ------------------------------------------------------------------
    if ($action === 'communities') {
        [$ftb,$btf] = buildGraph($pdo);
        $limit     = min((int)($_POST['limit']??60),200);
        $minRating = (int)($_POST['min_rating']??0);
        $thresh    = max(0.01,(float)($_POST['threshold']??0.1));
        $target    = (int)($_POST['target_sb']??0);
        if (!$target) { $mx=0; foreach($btf as $sid=>$f) if(count($f)>$mx){$mx=count($f);$target=$sid;} }
        $tgt       = array_flip($btf[$target]??[]);
        $sims      = [];
        foreach ($btf as $sid=>$fids) {
            if($sid===$target) continue;
            $inter=count(array_intersect_key(array_flip($fids),$tgt));
            $union=count(array_unique(array_merge($fids,array_keys($tgt))));
            if(!$union) continue;
            $j=$inter/$union;
            if($j>=$thresh) $sims[$sid]=['jaccard'=>round($j,3),'shared'=>$inter];
        }
        arsort($sims);
        $allSbIds = array_merge([$target],array_keys($sims));
        $in       = implode(',',array_map('intval',$allSbIds));
        $nameMap  = [];
        foreach ($pdo->query("SELECT id,name FROM storyboards WHERE id IN ($in)")->fetchAll(PDO::FETCH_ASSOC) as $sb)
            $nameMap[(int)$sb['id']]=$sb['name'];
        $cids = $btf[$target]??[];
        foreach (array_slice(array_keys($sims),0,5) as $sid) $cids=array_merge($cids,$btf[$sid]??[]);
        $cids = array_unique($cids);
        $info = fetchFramesFull($pdo,$cids);
        $result = [];
        foreach ($cids as $fid) {
            $fi=$info[$fid]??null; if(!$fi) continue;
            if($minRating>0 && (int)($fi['rating']??0)<$minRating) continue;
            $fi['degree']=count($ftb[$fid]??[]); $fi['storyboards']=array_values($ftb[$fid]??[]);
            $result[]=$fi;
            if(count($result)>=$limit) break;
        }
        $simList=[];
        foreach (array_slice($sims,0,10,true) as $sid=>$sd)
            $simList[]=['id'=>$sid,'name'=>$nameMap[$sid]??"SB #$sid",'jaccard'=>$sd['jaccard'],'shared'=>$sd['shared']];
        echo json_encode(['success'=>true,'concept'=>'communities','target_sb'=>$target,
            'target_name'=>$nameMap[$target]??"SB #$target",'similar_boards'=>$simList,
            'frames'=>$result,'total_similar'=>count($sims)]);
        exit;
    }

    // ------------------------------------------------------------------
    // CO-OCCURRENCE
    // ------------------------------------------------------------------
    if ($action === 'cooccurrence') {
        [$ftb,$btf] = buildGraph($pdo);
        $limit     = min((int)($_POST['limit']??60),200);
        $minRating = (int)($_POST['min_rating']??0);
        $pivot     = (int)($_POST['pivot_frame']??0);
        if (!$pivot) { $mx=0; foreach($ftb as $fid=>$b) if(count($b)>$mx){$mx=count($b);$pivot=$fid;} }
        $pBoards   = array_keys($ftb[$pivot]??[]);
        $co        = [];
        foreach ($pBoards as $sid) foreach($btf[$sid]??[] as $fid) { if($fid===$pivot) continue; $co[$fid]=($co[$fid]??0)+1; }
        arsort($co);
        $ids       = array_slice(array_keys($co),0,$limit*3,true);
        $info      = fetchFramesFull($pdo,array_merge([$pivot],$ids));
        $result    = [];
        foreach ($ids as $fid) {
            $fi=$info[$fid]??null; if(!$fi) continue;
            if($minRating>0 && (int)($fi['rating']??0)<$minRating) continue;
            $fi['degree']=count($ftb[$fid]??[]); $fi['co_score']=$co[$fid];
            $fi['co_pct']=$pBoards?round($co[$fid]/count($pBoards)*100):0;
            $fi['storyboards']=array_values($ftb[$fid]??[]);
            $result[]=$fi;
            if(count($result)>=$limit) break;
        }
        echo json_encode(['success'=>true,'concept'=>'cooccurrence','pivot_frame'=>$pivot,
            'pivot_info'=>$info[$pivot]??null,'pivot_boards'=>count($pBoards),'frames'=>$result]);
        exit;
    }

    // ------------------------------------------------------------------
    // CENTRALITY
    // ------------------------------------------------------------------
    if ($action === 'centrality') {
        [$ftb,] = buildGraph($pdo);
        $limit     = min((int)($_POST['limit']??60),200);
        $minRating = (int)($_POST['min_rating']??0);
        $scores    = [];
        foreach ($ftb as $fid=>$boards) {
            $cats=[];
            foreach($boards as $sid=>$si) $cats[$si['cat_code']??'misc']=true;
            $scores[$fid]=count($cats)*count($boards);
        }
        arsort($scores);
        $ids       = array_slice(array_keys($scores),0,$limit*3,true);
        $info      = fetchFramesFull($pdo,$ids);
        $result    = [];
        foreach ($ids as $fid) {
            $fi=$info[$fid]??null; if(!$fi) continue;
            if($minRating>0 && (int)($fi['rating']??0)<$minRating) continue;
            $boards=$ftb[$fid]; $cats=[];
            foreach($boards as $sid=>$si) { $c=$si['cat_code']??'misc'; $cats[$c]=$si['cat_name']??ucfirst($c); }
            $fi['degree']=count($boards); $fi['centrality']=$scores[$fid];
            $fi['categories']=$cats; $fi['storyboards']=array_values($boards);
            $result[]=$fi;
            if(count($result)>=$limit) break;
        }
        echo json_encode(['success'=>true,'concept'=>'centrality','frames'=>$result,'total'=>count($scores)]);
        exit;
    }

    // ------------------------------------------------------------------
    // STATS
    // ------------------------------------------------------------------
    if ($action === 'stats') {
        [$ftb,] = buildGraph($pdo);
        $totalFrames = (int)$pdo->query("SELECT COUNT(*) FROM frames")->fetchColumn();
        $totalLinks  = (int)$pdo->query("SELECT COUNT(*) FROM storyboard_frames")->fetchColumn();
        $totalSBs    = (int)$pdo->query("SELECT COUNT(*) FROM storyboards WHERE is_archived=0")->fetchColumn();
        $linked      = count($ftb);
        $degrees     = array_map('count',$ftb);
        $maxDeg      = $degrees ? max($degrees) : 0;
        $avgDeg      = $degrees ? round(array_sum($degrees)/count($degrees),2) : 0;
        $dist        = [];
        foreach ($degrees as $d) { $b=$d>=10?'10+':(string)$d; $dist[$b]=($dist[$b]??0)+1; }
        ksort($dist);
        echo json_encode(['success'=>true,'total_frames'=>$totalFrames,'linked_frames'=>$linked,
            'unlinked_frames'=>$totalFrames-$linked,'total_links'=>$totalLinks,
            'total_sbs'=>$totalSBs,'max_degree'=>$maxDeg,'avg_degree'=>$avgDeg,'degree_dist'=>$dist]);
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Unknown action: '.$action]);
    exit;
}

// ---------------------------------------------------------------------------
// Normal page load
// ---------------------------------------------------------------------------
$entities_with_menu = ['characters','character_poses','animas','locations','backgrounds',
    'artifacts','vehicles','scene_parts','controlnet_maps','spawns','generatives',
    'sketches','prompt_matrix_blueprints','composites'];

$registry = ModuleRegistry::getInstance();
$gearMenu = $registry->create('gear_menu', [
    'position'=>'top-right','icon'=>'&#9881;','icon_size'=>'1.5em',
    'show_for_entities'=>$entities_with_menu,
]);
foreach ($entities_with_menu as $en) $gearMenu->addStandardActions($en);
$imageEditor = $registry->create('image_editor');

ob_start(); require __DIR__.'/modal_frame_details.php'; $frameDetailsModal = ob_get_clean();

$storyboards = $pdo->query(
    "SELECT s.id, s.name, sc.name as cat_name, sc.code as cat_code
     FROM storyboards s LEFT JOIN storyboard_categories sc ON sc.id = s.category_id
     WHERE s.is_archived = 0 ORDER BY sc.sort_order ASC, s.name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$iconChar = $entityIcons['sketches'] ?? '🪄';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.7">
    <title>Storyboard · Data Mining</title>
    <script>
      (function(){try{var t=localStorage.getItem('spw_theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}}());
    </script>
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css"/>
    <style>
        .dm-header{display:flex;align-items:center;gap:14px;padding:20px 20px 0 60px;margin-bottom:6px;}
        .dm-header h2{margin:0;font-size:1.2rem;color:var(--text);}
        .dm-subtitle{font-size:.78rem;color:var(--text-muted);margin:0 0 20px 60px;}
        .concept-bar{display:flex;gap:8px;flex-wrap:wrap;padding:0 20px 0 60px;margin-bottom:16px;}
        .concept-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:20px;border:1px solid var(--border);background:var(--card);color:var(--text-muted);cursor:pointer;font-size:.82rem;font-weight:500;transition:all .18s;user-select:none;}
        .concept-btn:hover{border-color:var(--accent);color:var(--text);}
        .concept-btn.active{background:var(--accent);color:#fff;border-color:var(--accent);box-shadow:0 2px 8px rgba(0,0,0,.15);}
        .stats-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:12px;background:var(--card);border:1px solid var(--border);font-size:.75rem;color:var(--text-muted);cursor:pointer;}
        .stats-pill:hover{border-color:var(--accent);}
        .params-panel{display:none;margin:0 20px 14px 60px;padding:12px 16px;background:var(--card);border:1px solid var(--border);border-radius:8px;font-size:.83rem;}
        .params-panel.visible{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;}
        .param-group{display:flex;flex-direction:column;gap:4px;}
        .param-group label{font-size:.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;}
        .param-input{padding:5px 8px;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text);font-size:.83rem;width:140px;}
        .param-input:focus{outline:none;border-color:var(--accent);}
        select.param-input{width:220px;}
        .run-btn{padding:6px 18px;background:var(--accent);color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:.83rem;font-weight:700;transition:opacity .15s;}
        .run-btn:hover{opacity:.85;}

        /* Rating filter param */
        .param-group.param-rating label::before{content:'⭐ ';}
        .rating-select{width:120px;}

        /* Rating stars on card */
        .card-rating{display:inline-flex;align-items:center;gap:2px;font-size:.78rem;line-height:1;}
        .card-rating .star{color:#e5e7eb;font-size:.85rem;}
        .card-rating .star.on{color:#f59e0b;}
        .card-rating-wrap{margin-top:4px;margin-bottom:2px;}

        .result-header{padding:0 20px 10px 60px;display:none;align-items:center;gap:10px;flex-wrap:wrap;}
        .result-header.visible{display:flex;}
        .result-title{font-size:.9rem;font-weight:700;color:var(--text);margin:0;}
        .result-meta{font-size:.78rem;color:var(--text-muted);}
        .result-badge{padding:2px 8px;border-radius:10px;font-size:.72rem;font-weight:700;background:rgba(59,130,246,.12);color:var(--accent);border:1px solid rgba(59,130,246,.2);}
        .community-sidebar{margin:0 20px 12px 60px;display:none;gap:8px;flex-wrap:wrap;}
        .community-sidebar.visible{display:flex;}
        .sb-sim-chip{padding:3px 10px;border-radius:6px;font-size:.75rem;background:var(--card);border:1px solid var(--border);color:var(--text-muted);}
        .sb-sim-chip strong{color:var(--text);}
        .dm-loading{display:none;text-align:center;padding:40px;color:var(--text-muted);}
        .dm-loading.visible{display:block;}
        .dm-empty{text-align:center;padding:60px 20px;color:var(--text-muted);}
        .dm-empty .dei{font-size:3rem;margin-bottom:12px;}
        .spinner{display:inline-block;width:28px;height:28px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;}
        @keyframes spin{to{transform:rotate(360deg);}}

        /* Cards — mirrors view_map_runs_sketches exactly */
        .dm-swiper-section{padding:8px 0 28px;border-bottom:1px solid var(--border);}
        .frame-chain-swiper{width:100%;padding:16px 0;}
        .swiper-slide{width:300px;display:flex;align-items:center;position:relative;}
        .swiper-slide:not(:last-child)::after{content:'→';font-size:20px;color:var(--text-muted);position:absolute;right:-22px;top:50%;transform:translateY(-50%);z-index:1;}
        .chain-card{background:var(--card);border:1px solid var(--border);border-radius:8px;overflow:hidden;box-shadow:var(--card-elevation,0 1px 4px rgba(0,0,0,.08));width:100%;display:flex;flex-direction:column;transition:transform .2s;}
        .chain-card:hover{transform:translateY(-4px);}
        .chain-card-thumbnail{position:relative;width:100%;padding-top:100%;background:var(--bg);}
        .chain-card-thumbnail img{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;}
        .chain-card-body{padding:12px;flex-grow:1;font-size:13px;}
        .chain-card-title{font-weight:600;color:var(--text);margin:0 0 8px;font-size:15px;}
        .chain-card-prompt{color:var(--text-muted);display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;cursor:pointer;}
        .chain-card-meta{border-top:1px solid var(--border);padding-top:8px;display:flex;justify-content:space-between;font-size:12px;}

        .metric-badge{position:absolute;top:6px;right:6px;padding:2px 7px;border-radius:10px;font-size:.7rem;font-weight:800;backdrop-filter:blur(4px);pointer-events:none;}
        .m-degree{background:rgba(59,130,246,.82);color:#fff;}
        .m-central{background:rgba(168,85,247,.82);color:#fff;}
        .m-cluster{background:rgba(16,185,129,.82);color:#fff;}
        .m-unlinked{background:rgba(239,68,68,.82);color:#fff;}
        .m-singleton{background:rgba(245,159,11,.82);color:#fff;}
        .m-co{background:rgba(236,72,153,.82);color:#fff;}

        /* Badges — identical to original */
        .badge{display:inline-block;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:600;margin-right:4px;margin-bottom:4px;border:1px solid transparent;}
        .badge-gray{background:rgba(100,100,100,.1);color:var(--text-muted);border-color:var(--border);}
        .badge-blue{background:rgba(59,130,246,.1);color:#3b82f6;border-color:rgba(59,130,246,.2);}
        .badge-orange{background:rgba(245,159,11,.1);color:#f59e0b;border-color:rgba(245,159,11,.2);}
        .badge-meta{background:linear-gradient(135deg,rgba(99,102,241,.1),rgba(168,85,247,.1));color:#8b5cf6;border:1px solid rgba(139,92,246,.3);cursor:pointer;}
        .badge-meta:hover{border-color:#8b5cf6;background:rgba(139,92,246,.15);}
        .badge-curator{background:linear-gradient(135deg,rgba(16,185,129,.1),rgba(52,211,153,.1));color:#10b981;border:1px solid rgba(16,185,129,.3);cursor:pointer;}
        .badge-curator:hover{background:rgba(16,185,129,.15);}

        /* Storyboard mini-tags */
        .sb-tags{display:flex;flex-wrap:wrap;gap:3px;margin-top:5px;}
        .sb-tag{padding:1px 5px;border-radius:4px;font-size:10px;background:rgba(100,100,100,.08);color:var(--text-muted);border:1px solid var(--border);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:110px;}
        .sb-tag.cat-editorial{border-color:rgba(59,130,246,.3);color:#3b82f6;background:rgba(59,130,246,.07);}
        .sb-tag.cat-location{border-color:rgba(16,185,129,.3);color:#10b981;background:rgba(16,185,129,.07);}
        .sb-tag.cat-character{border-color:rgba(245,159,11,.3);color:#f59e0b;background:rgba(245,159,11,.07);}
        .sb-tag.cat-assets{border-color:rgba(168,85,247,.3);color:#a855f7;background:rgba(168,85,247,.07);}
        .sb-tag.cat-narrative{border-color:rgba(239,68,68,.3);color:#ef4444;background:rgba(239,68,68,.07);}
        .sb-tag.cat-pr{border-color:rgba(236,72,153,.3);color:#ec4899;background:rgba(236,72,153,.07);}
        .sb-tag.cat-drafts{border-color:rgba(107,114,128,.3);color:#6b7280;background:rgba(107,114,128,.07);}
        .sb-tag.cat-shotdrafts{border-color:rgba(20,184,166,.3);color:#14b8a6;background:rgba(20,184,166,.07);}

        /* Modals */
        .pill{display:inline-block;padding:2px 8px;background:rgba(0,0,0,.05);border-radius:12px;font-size:.8rem;margin-right:4px;margin-bottom:4px;color:var(--text);border:1px solid transparent;}
        .pill-theme{border-color:var(--accent);color:var(--accent);background:rgba(59,130,246,.1);}
        .pill-char{border-color:#f59e0b;color:#f59e0b;background:rgba(245,159,11,.1);}
        .modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.7);z-index:9999;justify-content:center;align-items:center;}
        .modal-overlay.open{display:flex;}
        .modal-content{background:var(--card);padding:24px;border-radius:8px;max-width:600px;width:90%;max-height:80vh;overflow-y:auto;position:relative;color:var(--text);border:1px solid var(--border);}
        .modal-close{position:absolute;top:16px;right:16px;font-size:24px;cursor:pointer;background:none;border:none;color:var(--text-muted);line-height:1;}
        .modal-close:hover{color:var(--text);}
        .modal-row{margin-bottom:12px;border-bottom:1px dashed var(--border);padding-bottom:8px;display:flex;align-items:flex-start;}
        .modal-icon{font-size:1.5em;margin-right:12px;min-width:30px;text-align:center;}
        .modal-info{flex:1;}
        .modal-label{font-weight:bold;color:var(--text-muted);font-size:.85rem;display:block;margin-bottom:4px;}
        .modal-value{font-size:.95rem;display:block;}
        .modal-detail{font-size:.85rem;color:var(--text-muted);margin-top:2px;font-style:italic;}

        /* Stats modal */
        .stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:16px 0;}
        .stat-block{padding:12px;background:var(--bg);border-radius:6px;border:1px solid var(--border);text-align:center;}
        .stat-val{font-size:1.6rem;font-weight:800;color:var(--text);}
        .stat-lbl{font-size:.72rem;color:var(--text-muted);text-transform:uppercase;margin-top:2px;}
        .deg-bar-row{display:flex;align-items:center;gap:8px;margin-bottom:5px;font-size:.78rem;}
        .deg-key{width:28px;text-align:right;color:var(--text-muted);flex-shrink:0;}
        .deg-bar-outer{flex:1;background:var(--border);border-radius:3px;height:8px;}
        .deg-bar-inner{height:100%;border-radius:3px;background:var(--accent);}
        .deg-bar-count{color:var(--text-muted);width:40px;flex-shrink:0;}
        .sb-menu{position:absolute!important;}
    </style>
</head>
<body>

<div class="dm-header">
    <a href="view_map_runs_sketches.php" style="font-size:1.5rem;text-decoration:none;color:var(--text-muted);" title="Sketches Runs">🪄</a>
    <h2>Storyboard · Data Mining</h2>
    <button class="stats-pill" id="statsBtn"><i class="bi bi-bar-chart-line"></i> Graph Stats</button>
</div>
<p class="dm-subtitle">Bipartite graph analysis — frames × storyboards</p>

<div class="concept-bar">
    <button class="concept-btn" data-concept="degree"><span>📊</span> Degree <span style="font-size:.7rem;opacity:.6">frequency</span></button>
    <button class="concept-btn" data-concept="centrality"><span>⭐</span> Centrality <span style="font-size:.7rem;opacity:.6">bridges</span></button>
    <button class="concept-btn" data-concept="clustering"><span>🔵</span> Clustering <span style="font-size:.7rem;opacity:.6">dense groups</span></button>
    <button class="concept-btn" data-concept="communities"><span>🏝️</span> Communities <span style="font-size:.7rem;opacity:.6">islands</span></button>
    <button class="concept-btn" data-concept="cooccurrence"><span>🔗</span> Co-occurrence <span style="font-size:.7rem;opacity:.6">always together</span></button>
    <button class="concept-btn" data-concept="orphans"><span>👻</span> Orphans <span style="font-size:.7rem;opacity:.6">isolated</span></button>
</div>

<?php
// Helper to render the shared global min-rating param group used in every params panel
function ratingParamGroup(string $id): string {
    return '
    <div class="param-group param-rating">
        <label>Min rating</label>
        <select class="param-input rating-select" id="'.$id.'-min-rating">
            <option value="0">Any</option>
            <option value="1">★ 1+</option>
            <option value="2">★★ 2+</option>
            <option value="3">★★★ 3+</option>
            <option value="4">★★★★ 4+</option>
            <option value="5">★★★★★ 5 only</option>
        </select>
    </div>';
}
?>

<div class="params-panel" id="params-degree">
    <div class="param-group"><label>Min storyboards</label><input type="number" class="param-input" id="degree-min" value="2" min="1"></div>
    <div class="param-group"><label>Max storyboards</label><input type="number" class="param-input" id="degree-max" value="999"></div>
    <div class="param-group"><label>Show up to</label><input type="number" class="param-input" id="degree-limit" value="60" min="10" max="200"></div>
    <?= ratingParamGroup('degree') ?>
    <button class="run-btn" data-concept="degree">Run</button>
</div>
<div class="params-panel" id="params-centrality">
    <div class="param-group"><label>Show up to</label><input type="number" class="param-input" id="centrality-limit" value="60" min="10" max="200"></div>
    <?= ratingParamGroup('centrality') ?>
    <button class="run-btn" data-concept="centrality">Run</button>
</div>
<div class="params-panel" id="params-clustering">
    <div class="param-group"><label>Pivot storyboard</label>
        <select class="param-input" id="clustering-pivot">
            <option value="0">Auto (largest)</option>
            <?php foreach($storyboards as $sb): ?><option value="<?=$sb['id']?>">[<?=htmlspecialchars($sb['cat_code']??'misc')?>] <?=htmlspecialchars($sb['name'])?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="param-group"><label>Show up to</label><input type="number" class="param-input" id="clustering-limit" value="60" min="10" max="200"></div>
    <?= ratingParamGroup('clustering') ?>
    <button class="run-btn" data-concept="clustering">Run</button>
</div>
<div class="params-panel" id="params-communities">
    <div class="param-group"><label>Target storyboard</label>
        <select class="param-input" id="communities-target">
            <option value="0">Auto (most connected)</option>
            <?php foreach($storyboards as $sb): ?><option value="<?=$sb['id']?>">[<?=htmlspecialchars($sb['cat_code']??'misc')?>] <?=htmlspecialchars($sb['name'])?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="param-group"><label>Min Jaccard (0–1)</label><input type="number" class="param-input" id="communities-threshold" value="0.1" min="0.01" max="1" step="0.01"></div>
    <div class="param-group"><label>Show up to</label><input type="number" class="param-input" id="communities-limit" value="60" min="10" max="200"></div>
    <?= ratingParamGroup('communities') ?>
    <button class="run-btn" data-concept="communities">Run</button>
</div>
<div class="params-panel" id="params-cooccurrence">
    <div class="param-group"><label>Pivot frame ID (0 = auto)</label><input type="number" class="param-input" id="cooccurrence-pivot" value="0" min="0"></div>
    <div class="param-group"><label>Show up to</label><input type="number" class="param-input" id="cooccurrence-limit" value="60" min="10" max="200"></div>
    <?= ratingParamGroup('cooccurrence') ?>
    <button class="run-btn" data-concept="cooccurrence">Run</button>
</div>
<div class="params-panel" id="params-orphans">
    <div class="param-group"><label>Show up to</label><input type="number" class="param-input" id="orphans-limit" value="60" min="10" max="200"></div>
    <?= ratingParamGroup('orphans') ?>
    <button class="run-btn" data-concept="orphans">Run</button>
</div>

<div class="result-header" id="resultHeader">
    <h3 class="result-title" id="resultTitle">—</h3>
    <span class="result-badge" id="resultCount">0 frames</span>
    <span class="result-meta" id="resultMeta"></span>
</div>
<div class="community-sidebar" id="communitySidebar"></div>
<div class="dm-loading" id="dmLoading"><div class="spinner"></div><p style="margin-top:10px;font-size:.85rem;">Computing graph metrics…</p></div>

<div class="pswp-gallery" id="dmOutput">
    <div class="dm-empty"><div class="dei">🔬</div><p>Select a concept above and click Run</p></div>
</div>

<!-- Modals — identical to view_map_runs_sketches -->
<div id="meta-modal" class="modal-overlay">
    <div class="modal-content">
        <button class="modal-close">&times;</button>
        <h3 style="margin-top:0;border-bottom:1px solid var(--border);padding-bottom:10px;">Recipe Ingredients</h3>
        <div id="meta-modal-body"></div>
    </div>
</div>
<div id="curation-modal" class="modal-overlay">
    <div class="modal-content" style="max-width:700px;">
        <button class="modal-close">&times;</button>
        <h3 style="margin-top:0;border-bottom:1px solid var(--border);padding-bottom:10px;">Narrative Analysis</h3>
        <div id="curation-modal-body"></div>
    </div>
</div>
<div id="desc-modal" class="modal-overlay">
    <div class="modal-content">
        <button class="modal-close">&times;</button>
        <h3 style="margin-top:0;">Full Description</h3>
        <div id="desc-modal-body" style="white-space:pre-wrap;font-family:monospace;font-size:.9rem;"></div>
    </div>
</div>
<div id="statsModal" class="modal-overlay">
    <div class="modal-content" style="max-width:480px;">
        <button class="modal-close">&times;</button>
        <h3 style="margin:0 0 4px;font-size:1rem;">Graph Overview</h3>
        <p style="margin:0 0 12px;font-size:.78rem;color:var(--text-muted);">Bipartite: frames × storyboards</p>
        <div id="statsBody"><div style="text-align:center;padding:20px;"><div class="spinner"></div></div></div>
    </div>
</div>

<?= $eruda ?? '' ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/js/gear_menu_globals.js"></script>
<?= $gearMenu->render() ?>
<?= $imageEditor->render() ?>
<?= $frameDetailsModal ?>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

<script type="module">
import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
const lb = new PhotoSwipeLightbox({
    gallery:'#dmOutput', children:'.pswp-gallery-item',
    pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js')
});
lb.init();
</script>

<script>
const CAT_CLASS = {editorial:'cat-editorial',location:'cat-location',character:'cat-character',
    assets:'cat-assets',narrative:'cat-narrative',pr:'cat-pr',drafts:'cat-drafts',shotdrafts:'cat-shotdrafts',misc:''};

function esc(s){if(!s)return'';return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function ingIcon(t){if(t.includes('character'))return'🦸';if(t.includes('location'))return'🗺️';if(t.includes('template'))return'🎬';if(t.includes('interaction'))return'🤝';if(t.includes('style'))return'🎨';if(t.includes('generator'))return'⚡';if(t.includes('anivoc'))return'📘';return'📦';}

// Build star rating HTML (5 stars, filled up to rating value)
function buildStars(rating) {
    const r = parseInt(rating) || 0;
    if (r === 0) return ''; // no stars if unrated
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        stars += `<span class="star${i <= r ? ' on' : ''}">★</span>`;
    }
    return `<div class="card-rating-wrap"><span class="card-rating" title="${r}/5 stars">${stars}</span></div>`;
}

// ── The AJAX endpoint URL (this file itself)
const ENDPOINT = '<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>';

let activeConcept=null, activeSwipers=[];

// Concept buttons
document.querySelectorAll('.concept-btn').forEach(btn=>{
    btn.addEventListener('click',function(){
        const c=this.dataset.concept;
        document.querySelectorAll('.concept-btn').forEach(b=>b.classList.remove('active'));
        document.querySelectorAll('.params-panel').forEach(p=>p.classList.remove('visible'));
        if(activeConcept===c){activeConcept=null;return;}
        this.classList.add('active');
        activeConcept=c;
        document.getElementById('params-'+c).classList.add('visible');
    });
});

// Run buttons
document.querySelectorAll('.run-btn').forEach(btn=>{
    btn.addEventListener('click',function(){runAnalysis(this.dataset.concept);});
});

function gatherParams(c){
    const p={action:c};
    // Global rating filter
    const ratingEl = document.getElementById(c+'-min-rating');
    if(ratingEl) p.min_rating = ratingEl.value;

    if(c==='degree'){p.min_degree=document.getElementById('degree-min').value;p.max_degree=document.getElementById('degree-max').value;p.limit=document.getElementById('degree-limit').value;}
    else if(c==='centrality'){p.limit=document.getElementById('centrality-limit').value;}
    else if(c==='clustering'){p.pivot_sb=document.getElementById('clustering-pivot').value;p.limit=document.getElementById('clustering-limit').value;}
    else if(c==='communities'){p.target_sb=document.getElementById('communities-target').value;p.threshold=document.getElementById('communities-threshold').value;p.limit=document.getElementById('communities-limit').value;}
    else if(c==='cooccurrence'){p.pivot_frame=document.getElementById('cooccurrence-pivot').value;p.limit=document.getElementById('cooccurrence-limit').value;}
    else if(c==='orphans'){p.limit=document.getElementById('orphans-limit').value;}
    return p;
}

function runAnalysis(concept){
    const params=gatherParams(concept);
    document.getElementById('resultHeader').classList.remove('visible');
    document.getElementById('communitySidebar').classList.remove('visible');
    document.getElementById('dmLoading').classList.add('visible');
    destroySwipers();
    document.getElementById('dmOutput').innerHTML='';

    $.ajax({
        url: ENDPOINT,
        type:'POST',
        data: params,
        headers:{'X-Requested-With':'XMLHttpRequest'},
        dataType:'json',
        success:function(res){
            document.getElementById('dmLoading').classList.remove('visible');
            if(!res||!res.success){showError((res&&res.error)||'Unknown error');return;}
            renderResult(res);
        },
        error:function(xhr){
            document.getElementById('dmLoading').classList.remove('visible');
            showError('Server error ('+xhr.status+'). See browser console for details.');
            console.error('Response body:',xhr.responseText);
        }
    });
}

function renderResult(res){
    const concept=res.concept, frames=res.frames||[];
    const titles={degree:'📊 High-Degree Frames',centrality:'⭐ Central Frames',
        clustering:'🔵 Cluster · '+esc(res.pivot_name||''),communities:'🏝️ Community · '+esc(res.target_name||''),
        cooccurrence:'🔗 Co-occurrence · Frame #'+(res.pivot_frame||''),orphans:'👻 Orphans & Singletons'};
    const metas={degree:(res.total||0)+' frames qualify',centrality:(res.total||0)+' frames scored',
        clustering:(res.co_occurring_boards||0)+' co-occurring storyboards',
        communities:(res.total_similar||0)+' similar storyboards found',
        cooccurrence:'Pivot in '+(res.pivot_boards||0)+' storyboards',
        orphans:res.stats?(res.stats.unlinked+' unlinked · '+res.stats.singletons+' singletons'):''};

    document.getElementById('resultTitle').textContent=titles[concept]||concept;
    document.getElementById('resultCount').textContent=frames.length+' frames';
    document.getElementById('resultMeta').textContent=metas[concept]||'';
    document.getElementById('resultHeader').classList.add('visible');

    if(concept==='communities'&&res.similar_boards&&res.similar_boards.length){
        const sb=document.getElementById('communitySidebar');
        sb.innerHTML=res.similar_boards.map(b=>`<span class="sb-sim-chip"><strong>${esc(b.name)}</strong> · ${Math.round(b.jaccard*100)}% · ${b.shared} shared</span>`).join('');
        sb.classList.add('visible');
    }

    if(!frames.length){
        document.getElementById('dmOutput').innerHTML='<div class="dm-empty"><div class="dei">🌑</div><p>No frames returned — adjust parameters.</p></div>';
        return;
    }

    const sid='sw-'+concept+'-'+Date.now();
    document.getElementById('dmOutput').innerHTML=`
        <div class="dm-swiper-section">
            <div class="swiper frame-chain-swiper" id="${sid}">
                <div class="swiper-wrapper">${frames.map(f=>buildSlide(f,concept)).join('')}</div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-scrollbar"></div>
            </div>
        </div>`;

    const el=document.getElementById(sid);
    const sw=new Swiper(el,{slidesPerView:'auto',spaceBetween:40,freeMode:true,
        navigation:{nextEl:el.querySelector('.swiper-button-next'),prevEl:el.querySelector('.swiper-button-prev')},
        scrollbar:{el:el.querySelector('.swiper-scrollbar'),hide:true},
        slidesOffsetBefore:60,slidesOffsetAfter:20});
    activeSwipers.push(sw);

    if(window.GearMenu&&typeof window.GearMenu.attach==='function') window.GearMenu.attach(el);
}

function buildSlide(f,concept){
    const imgPath=f.filename?esc(f.filename.replace(/^\//,'')):'' ;
    const eType=esc(f.entity_type||'sketches');
    const eId=parseInt(f.entity_id)||0;
    const fId=parseInt(f.frame_id)||0;

    // Metric overlay
    let metric='';
    if(concept==='degree')      metric=`<span class="metric-badge m-degree">×${f.degree}</span>`;
    else if(concept==='centrality') metric=`<span class="metric-badge m-central">C:${f.centrality}</span>`;
    else if(concept==='clustering') metric=`<span class="metric-badge m-cluster">S:${f.cluster_score}</span>`;
    else if(concept==='orphans')    metric=`<span class="metric-badge ${f.orphan_type==='unlinked'?'m-unlinked':'m-singleton'}">${f.orphan_type==='unlinked'?'✗ unlinked':'① singleton'}</span>`;
    else if(concept==='cooccurrence') metric=`<span class="metric-badge m-co">${f.co_pct}%</span>`;
    else if(concept==='communities')  metric=`<span class="metric-badge m-degree">×${f.degree}</span>`;

    // Method badge
    let bc='badge-gray', ml='Original';
    if(f.edit_tool){bc='badge-orange';ml='Edit';}else if(f.img2img_frame_id){bc='badge-blue';ml='Img2Img';}

    // Ingredients badge
    const ings=f.normalized_ingredients||[];
    const ingBadge=ings.length?`<span class="badge badge-meta meta-pill-trigger" data-ingredients="${btoa(unescape(encodeURIComponent(JSON.stringify(ings))))}" data-b64="1">Ingredients (${ings.length})</span>`:'';

    // Curation badge
    const cur=f.curation;
    const curBadge=cur?`<span class="badge badge-curator curation-pill-trigger" data-curation="${btoa(unescape(encodeURIComponent(JSON.stringify(cur))))}" data-b64="1" title="Quality: ${cur.score}">🕵️ Analysis (${cur.score})</span>`:'';

    // ── Star rating
    const starsHtml = buildStars(f.rating);

    // Prompt
    const prompt=esc(f.prompt||'No prompt');
    const fullDesc=esc(f.full_sketch_desc||f.prompt||'');

    // SB tags
    const sbs=f.storyboards||[];
    const sbHtml=sbs.slice(0,4).map(sb=>{const cls=CAT_CLASS[sb.cat_code]||'';return`<span class="sb-tag ${cls}" title="${esc(sb.name)}">${esc(sb.name)}</span>`;}).join('')+(sbs.length>4?`<span class="sb-tag">+${sbs.length-4}</span>`:'');

    return`<div class="swiper-slide">
        <div class="chain-card" data-entity="${eType}" data-entity-id="${eId}" data-frame-id="${fId}">
            <div class="chain-card-thumbnail">
                <a href="${imgPath}" class="pswp-gallery-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                    <img src="${imgPath}" alt="Frame #${fId}" loading="lazy">
                </a>
                ${metric}
            </div>
            <div class="chain-card-body">
                <h3 class="chain-card-title">Frame #${fId}</h3>
                <div>
                    <span class="badge ${bc}">${ml}</span>
                    ${ingBadge}
                    ${curBadge}
                </div>
                ${starsHtml}
                <p class="chain-card-prompt full-desc-trigger" data-full-desc="${fullDesc}">${prompt}</p>
                <div class="sb-tags">${sbHtml}</div>
                <div class="chain-card-meta">
                    <span class="badge badge-gray">${eType} #${eId}</span>
                </div>
            </div>
        </div>
    </div>`;
}

function destroySwipers(){activeSwipers.forEach(sw=>{try{sw.destroy(true,true);}catch(e){}});activeSwipers=[];}
function showError(msg){document.getElementById('dmOutput').innerHTML=`<div class="dm-empty"><div class="dei">⚠️</div><p>${esc(msg)}</p></div>`;}

// Modal handlers
function closeModal(m){m.classList.remove('open');}
document.querySelectorAll('.modal-close').forEach(btn=>btn.addEventListener('click',function(){closeModal(this.closest('.modal-overlay'));}));
window.addEventListener('click',e=>{if(e.target.classList.contains('modal-overlay'))closeModal(e.target);});

$(document).on('click','.meta-pill-trigger',function(e){
    e.stopPropagation();
    const ings=JSON.parse(decodeURIComponent(escape(atob(this.dataset.ingredients||'W10='))));
    document.getElementById('meta-modal-body').innerHTML=ings.map(ing=>`<div class="modal-row"><div class="modal-icon">${ingIcon(ing.type)}</div><div class="modal-info"><span class="modal-label">${esc(ing.label)}</span>${ing.detail?`<span class="modal-detail">${esc(ing.detail).substring(0,150)}${ing.detail.length>150?'…':''}</span>`:''}</div></div>`).join('');
    document.getElementById('meta-modal').classList.add('open');
});

$(document).on('click','.curation-pill-trigger',function(e){
    e.stopPropagation();
    const d=JSON.parse(decodeURIComponent(escape(atob(this.dataset.curation||'e30='))));
    let h=`<div style="margin-bottom:15px;"><div style="display:inline-block;padding:4px 10px;background:#10b981;color:white;border-radius:6px;font-weight:800;font-size:1.2em;margin-right:10px;">${d.score}</div><strong style="font-size:1.1em;">Overall Quality</strong></div>`;
    if(d.class){if(d.class.narrative_function)h+=`<div class="modal-row"><span class="modal-label">Function</span><span class="modal-value">${esc(d.class.narrative_function)}</span></div>`;if(d.class.emotional_tone)h+=`<div class="modal-row"><span class="modal-label">Tone</span><span class="modal-value">${esc(d.class.emotional_tone)}</span></div>`;}
    if(d.themes&&d.themes.primary_themes){const th=Array.isArray(d.themes.primary_themes)?d.themes.primary_themes:[d.themes.primary_themes];h+=`<div class="modal-row"><span class="modal-label">Themes</span><div style="margin-top:4px;">${th.map(t=>`<span class="pill pill-theme">${esc(t)}</span>`).join(' ')}</div></div>`;}
    if(d.entities&&d.entities.characters&&d.entities.characters.length)h+=`<div class="modal-row"><span class="modal-label">Characters</span><div style="margin-top:4px;">${d.entities.characters.map(c=>`<span class="pill pill-char">${esc(c)}</span>`).join(' ')}</div></div>`;
    if(d.recs&&d.recs.potential_use)h+=`<div style="margin-top:15px;background:rgba(245,159,11,.1);padding:10px;border-radius:6px;border:1px dashed rgba(245,159,11,.4);"><span class="modal-label" style="color:#f59e0b;">Suggestion</span><div style="font-style:italic;margin-top:4px;">${esc(d.recs.potential_use)}</div></div>`;
    if(d.score_breakdown)h+=`<div style="margin-top:15px;border-top:1px dashed var(--border);padding-top:10px;"><span class="modal-label">Score Breakdown</span><div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:.9em;margin-top:5px;"><div>Narrative: <b>${d.score_breakdown.narrative_completeness||'-'}</b></div><div>Visual: <b>${d.score_breakdown.visual_impact||'-'}</b></div><div>Production: <b>${d.score_breakdown.production_readiness||'-'}</b></div><div>Distinctiveness: <b>${d.score_breakdown.visual_distinctiveness||'-'}</b></div></div></div>`;
    document.getElementById('curation-modal-body').innerHTML=h;
    document.getElementById('curation-modal').classList.add('open');
});

$(document).on('click','.full-desc-trigger',function(e){
    e.stopPropagation();
    document.getElementById('desc-modal-body').textContent=this.dataset.fullDesc;
    document.getElementById('desc-modal').classList.add('open');
});

// Stats modal
let statsLoaded=false;
document.getElementById('statsBtn').addEventListener('click',function(){
    document.getElementById('statsModal').classList.add('open');
    if(!statsLoaded) loadStats();
});
function loadStats(){
    $.ajax({url:ENDPOINT,type:'POST',data:{action:'stats'},headers:{'X-Requested-With':'XMLHttpRequest'},dataType:'json',
        success:function(res){
            statsLoaded=true;
            const mx=res.degree_dist?Math.max(...Object.values(res.degree_dist)):1;
            const dist=Object.entries(res.degree_dist||{}).map(([k,v])=>`<div class="deg-bar-row"><span class="deg-key">${k}</span><div class="deg-bar-outer"><div class="deg-bar-inner" style="width:${Math.round(v/mx*100)}%"></div></div><span class="deg-bar-count">${v.toLocaleString()}</span></div>`).join('');
            document.getElementById('statsBody').innerHTML=`<div class="stat-grid"><div class="stat-block"><div class="stat-val">${res.total_frames.toLocaleString()}</div><div class="stat-lbl">Total Frames</div></div><div class="stat-block"><div class="stat-val">${res.total_sbs.toLocaleString()}</div><div class="stat-lbl">Active Storyboards</div></div><div class="stat-block"><div class="stat-val">${res.total_links.toLocaleString()}</div><div class="stat-lbl">Total Links</div></div><div class="stat-block"><div class="stat-val">${res.unlinked_frames.toLocaleString()}</div><div class="stat-lbl">Unlinked Frames</div></div><div class="stat-block"><div class="stat-val">${res.max_degree}</div><div class="stat-lbl">Max Degree</div></div><div class="stat-block"><div class="stat-val">${res.avg_degree}</div><div class="stat-lbl">Avg Degree</div></div></div><div><p style="font-size:.75rem;color:var(--text-muted);margin:0 0 8px;text-transform:uppercase;font-weight:600;">Degree Distribution</p>${dist}</div>`;
        },
        error:function(){document.getElementById('statsBody').innerHTML='<p>Error loading stats.</p>';}
    });
}

if(window.GearMenu&&typeof window.GearMenu.attach==='function')
    window.GearMenu.attach(document.getElementById('dmOutput'));
</script>
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>
<?php require_once "forge_tool.php"; ?>
</body>
</html>
