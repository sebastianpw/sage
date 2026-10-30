<?php
/**
 * cli_ingest_swimlanes.php
 *
 * One-time initial population of merm_threads / merm_beats /
 * merm_beat_participants / merm_beat_links from the Pirate Wannabe
 * phase-data JSON export + shortname registry.
 *
 * Usage:
 *   php cli_ingest_swimlanes.php <phase_data.json> <shortnames.json> [--commit]
 *
 * Mode:
 *   Default            -> DRY RUN (transaction rolled back, nothing written)
 *   --commit           -> real ingest (transaction committed)
 *   --dry-run=0        -> alias for --commit
 *
 * Story time:
 *   Days are assigned from NARRATIVE ORDER, not file position within section.
 *   Every beat is seeded with its natural position in the corpus (file order),
 *   then relaxed against the resolved requires / sets_up edges so that every
 *   successor's day is strictly greater than its predecessors'. The result
 *   is a sequential day axis that respects the causal graph the corpus
 *   already encodes.
 *
 *   RECURRING SYSTEMS beats are left with NULL story_time_dt so they appear
 *   in the "Unassigned" bucket rather than being pinned to an arbitrary day.
 */

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');

// ---------------------------------------------------------------------
// 0. CLI args
// ---------------------------------------------------------------------
$args = $argv;
array_shift($args);

$dryRun = true;
$positional = [];
foreach ($args as $a) {
    if ($a === '--commit') { $dryRun = false; continue; }
    if ($a === '--dry-run') { $dryRun = true; continue; }
    if (preg_match('/^--dry-run=(\d+)$/', $a, $m)) {
        $dryRun = ((int)$m[1] === 0) ? false : true;
        continue;
    }
    $positional[] = $a;
}

if (count($positional) < 2) {
    fwrite(STDERR, "Usage: php cli_ingest_swimlanes.php <phase_data.json> <shortnames.json> [--commit | --dry-run=0]\n");
    fwrite(STDERR, "Default is DRY RUN (nothing written). Pass --commit to actually ingest.\n");
    exit(1);
}

// ---------------------------------------------------------------------
// 1. Resolve input filenames against the script directory
// ---------------------------------------------------------------------
function resolve_input_file(string $nameOrPath, string $scriptDir): ?string {
    if (preg_match('#^/#', $nameOrPath)
        || preg_match('#^[A-Za-z]:[\\\\/]#', $nameOrPath)
        || strpos($nameOrPath, '/') !== false
        || strpos($nameOrPath, '\\') !== false) {
        return is_file($nameOrPath) ? $nameOrPath : null;
    }
    $candidate = rtrim($scriptDir, '/') . '/' . $nameOrPath;
    return is_file($candidate) ? $candidate : null;
}

$scriptDir = __DIR__;
$phaseDataPath  = resolve_input_file($positional[0], $scriptDir);
$shortnamesPath = resolve_input_file($positional[1], $scriptDir);

if ($phaseDataPath === null) {
    fwrite(STDERR, "Not found: {$positional[0]} (looked in {$scriptDir} too)\n");
    exit(1);
}
if ($shortnamesPath === null) {
    fwrite(STDERR, "Not found: {$positional[1]} (looked in {$scriptDir} too)\n");
    exit(1);
}

// ---------------------------------------------------------------------
// 2. Bootstrap the app's DB connection
// ---------------------------------------------------------------------
$bootstrapCandidates = [
    __DIR__ . '/../public/bootstrap.php',
    __DIR__ . '/public/bootstrap.php',
    __DIR__ . '/bootstrap.php',
];
$bootstrapped = false;
foreach ($bootstrapCandidates as $cand) {
    if (is_file($cand)) {
        require_once $cand;
        require_once dirname($cand) . '/env_locals.php';
        $bootstrapped = true;
        break;
    }
}
if (!$bootstrapped) {
    fwrite(STDERR, "Could not locate bootstrap.php — edit \$bootstrapCandidates in this script.\n");
    exit(1);
}

/** @var PDO $pdo */
global $pdo;
if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "\$pdo not available after bootstrap — check env_locals.php\n");
    exit(1);
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ---------------------------------------------------------------------
// 3. Config
// ---------------------------------------------------------------------
const SERIES_KEY = 'PIRATES';
const SEASON_KEY = 'PIR01';

// Story-time anchor. Day 1 = this date. Day N = anchor + (N-1) days.
const STORY_TIME_ANCHOR = '1717-03-01 09:00:00';

const SECTION_COLORS = [
    'Spine — Phase Goals' => '#0969da',
    'Beats'               => '#238636',
    'Relocated Beats'     => '#f59e0b',
];
const ROUTED_IN_COLOR = '#8957e5';
const RECURRING_COLOR = '#57606a';

// ---------------------------------------------------------------------
// 4. Load & decode JSON inputs
// ---------------------------------------------------------------------
function load_json(string $path): array {
    $raw = file_get_contents($path);
    if ($raw === false) {
        fwrite(STDERR, "Failed to read $path\n");
        exit(1);
    }
    $data = json_decode($raw, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        fwrite(STDERR, "JSON parse error in $path: " . json_last_error_msg() . "\n");
        exit(1);
    }
    return $data;
}

$phaseData = load_json($phaseDataPath);
$registry  = load_json($shortnamesPath);

if (!is_array($phaseData)) {
    fwrite(STDERR, "phase_data.json root must be an array of phase objects.\n");
    exit(1);
}

// ---------------------------------------------------------------------
// 5. Shortname -> kg_node_id resolver (participants)
// ---------------------------------------------------------------------
function resolve_shortname(string $shortname, array $registry, array &$unresolvedShortnames): array {
    if (isset($registry['clusters'][$shortname]['members'])) {
        return array_map('intval', $registry['clusters'][$shortname]['members']);
    }
    if (isset($registry['compound_shortnames'][$shortname]['members'])) {
        return array_map(fn($m) => (int)$m['id'], $registry['compound_shortnames'][$shortname]['members']);
    }
    if (isset($registry['shortnames'][$shortname]['id'])) {
        return [(int)$registry['shortnames'][$shortname]['id']];
    }
    if (isset($registry['collectives'][$shortname]['resolved_to'])) {
        return [(int)$registry['collectives'][$shortname]['resolved_to']];
    }
    if (isset($registry['collectives'][$shortname]['members'])) {
        return array_map('intval', $registry['collectives'][$shortname]['members']);
    }
    $unresolvedShortnames[$shortname] = true;
    return [];
}

// ---------------------------------------------------------------------
// 6. Prefix alias map for beat-title link resolution
// ---------------------------------------------------------------------
function build_prefix_alias_map(array $registry): array {
    $map = [];
    foreach ($registry['shortnames'] ?? [] as $sn => $info) {
        $full = $info['full_name'] ?? '';
        $full = preg_replace('/\s*\([^)]*\)\s*$/', '', $full);
        $full = preg_replace('/^The\s+/i', '', $full);
        $map[$sn] = trim($full);
    }
    $overrides = [
        'MU'       => 'Mainis Underworld',
        'CV'       => 'Contrasting Voice',
        'WF'       => 'Warning Figure',
        'HIA'      => 'High-Impact Ally',
        'Fixer'    => 'Reptilian Fixer',
        'JAT'      => 'Jack of All Trades',
        'CA'       => 'Mainis Civil Authority',
        'ST'       => 'Saint Toupee Commanders',
        'BoneArch' => 'Bone Archipelago',
        'Woody'    => 'Woody',
    ];
    foreach ($overrides as $k => $v) {
        $map[$k] = $v;
    }
    return $map;
}

// ---------------------------------------------------------------------
// 7. Canonicalization helper
// ---------------------------------------------------------------------
function kg_canonicalize(string $s): string {
    $s = mb_strtolower($s);
    $s = preg_replace('/\b(a|an|the)\s+/iu', ' ', $s);
    $s = preg_replace('/[^a-z0-9]+/u', '', $s);
    return (string)$s;
}

// ---------------------------------------------------------------------
// 8. Link target resolver — ten cascading strategies
// ---------------------------------------------------------------------
function try_resolve_link_target(
    string $target,
    array  $titleToBeatId,
    array  $prefixAliasMap,
    array  $phaseAnchors
): ?int {
    $t = trim($target);
    if ($t === '') return null;

    if (isset($titleToBeatId[$t])) return $titleToBeatId[$t];

    $stripParen  = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $t));
    $stripPhase  = trim(preg_replace('/^Phase\s+[IVXLCDM]+\s*[—\-–]\s*/iu', '', $t));
    $stripPhase2 = trim(preg_replace('/^Phase\s+[IVXLCDM]+\s+/iu', '', $t));
    $stripThe    = trim(preg_replace('/^The\s+/i', '', $t));

    if ($stripParen !== '' && $stripParen !== $t && isset($titleToBeatId[$stripParen])) {
        return $titleToBeatId[$stripParen];
    }

    foreach ([$stripPhase, $stripPhase2] as $n) {
        if ($n !== '' && $n !== $t && isset($titleToBeatId[$n])) {
            return $titleToBeatId[$n];
        }
    }

    if ($stripThe !== '' && $stripThe !== $t && isset($titleToBeatId[$stripThe])) {
        return $titleToBeatId[$stripThe];
    }

    foreach ([$t, $stripParen, $stripPhase, $stripPhase2] as $probe) {
        if (preg_match('/^(.+?)\s+[—\-–]\s+(.+)$/u', $probe, $m)) {
            $prefix = trim($m[1]);
            $suffix = trim($m[2]);
            if (isset($prefixAliasMap[$prefix])) {
                $candidate = $prefixAliasMap[$prefix] . ' — ' . $suffix;
                if (isset($titleToBeatId[$candidate])) return $titleToBeatId[$candidate];
            }
        }
    }

    if (!preg_match('/^The\s+/i', $t) && isset($titleToBeatId['The ' . $t])) {
        return $titleToBeatId['The ' . $t];
    }

    foreach ([$stripPhase, $stripPhase2, $stripParen] as $probe) {
        if ($probe === '' || $probe === $t) continue;
        $suffix = ' — ' . $probe;
        $matches = [];
        foreach ($titleToBeatId as $title => $bid) {
            if (substr($title, -strlen($suffix)) === $suffix) {
                $matches[] = $bid;
            }
        }
        if (count($matches) === 1) return $matches[0];
    }

    $canon = kg_canonicalize($t);
    if ($canon !== '') {
        foreach ($titleToBeatId as $title => $bid) {
            if (kg_canonicalize($title) === $canon) return $bid;
        }
    }

    foreach ($prefixAliasMap as $sn => $full) {
        if (stripos($t, $sn . ' ') === 0) {
            $expanded = $full . substr($t, strlen($sn));
            $expandedCanon = kg_canonicalize($expanded);
            foreach ($titleToBeatId as $title => $bid) {
                if (kg_canonicalize($title) === $expandedCanon) return $bid;
            }
        }
    }

    if (preg_match('/\bPhase\s+([IVXLCDM]+)\b/i', $t, $m)) {
        $roman = strtoupper($m[1]);
        if (isset($phaseAnchors[$roman])) {
            return $phaseAnchors[$roman];
        }
    }

    return null;
}

// ---------------------------------------------------------------------
// 9. episode_key derivation
// ---------------------------------------------------------------------
function derive_episode_key(string $phaseName): string {
    if (stripos($phaseName, 'RECURRING') !== false) return 'RECURRING';
    if (preg_match('/^PHASE\s+([IVXLCDM]+)\b/i', trim($phaseName), $m)) {
        $num = roman_to_int(strtoupper($m[1]));
        if ($num > 0) return 'PHASE' . str_pad((string)$num, 2, '0', STR_PAD_LEFT);
    }
    $slug = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', trim($phaseName)));
    return substr($slug, 0, 40);
}

function roman_to_int(string $roman): int {
    $map = ['I'=>1,'V'=>5,'X'=>10,'L'=>50,'C'=>100,'D'=>500,'M'=>1000];
    $total = 0; $len = strlen($roman);
    for ($i = 0; $i < $len; $i++) {
        $val  = $map[$roman[$i]] ?? 0;
        $next = $map[$roman[$i+1] ?? ''] ?? 0;
        $total += ($val < $next) ? -$val : $val;
    }
    return $total;
}

function map_beat_status(?string $s): string {
    return ($s === 'yes') ? 'planned' : 'draft';
}

function section_color(string $sectionName, string $phaseName): ?string {
    if (isset(SECTION_COLORS[$sectionName])) return SECTION_COLORS[$sectionName];
    if (stripos($sectionName, 'Routed In —') === 0 || stripos($sectionName, 'Routed In -') === 0) return ROUTED_IN_COLOR;
    if (stripos($sectionName, 'Relocated Beats') === 0) return SECTION_COLORS['Relocated Beats'];
    if (stripos($phaseName, 'RECURRING') !== false) return RECURRING_COLOR;
    return null;
}

$prefixAliasMap = build_prefix_alias_map($registry);

// =======================================================================
// PASS 1 + PASS 2 + PASS 3
// =======================================================================
$titleToBeatId        = [];
$titleCollisions      = [];
$threadsReport        = [];
$beatQueue            = [];
$unresolvedShortnames = [];
$phaseAnchors         = [];

$phaseOf    = [];   // beat_id => phase index
$filePosOf  = [];   // beat_id => file position (1-based)
$isRecurringBeat = []; // beat_id => bool

$countThreads = 0;
$countBeats   = 0;

$countParticipants  = 0;
$countLinksResolved = 0;
$countLinksPending  = 0;
$pendingReport      = [];

$reportWritten = false;
$fatalError    = null;

$anchorDate = new DateTime(STORY_TIME_ANCHOR);

$pdo->beginTransaction();

try {
    $insThread = $pdo->prepare("
        INSERT INTO merm_threads
            (name, series_key, season_key, episode_key, description, color, sort_order, status)
        VALUES
            (:name, :series_key, :season_key, :episode_key, :description, :color, :sort_order, 'active')
    ");

    $insBeat = $pdo->prepare("
        INSERT INTO merm_beats
            (thread_id, title, summary, content, beat_type, rhythm, story_time_dt, seq_order, status)
        VALUES
            (:thread_id, :title, :summary, :content, 'event', :rhythm, NULL, :seq_order, :status)
    ");

    // ---------------- PASS 1 ----------------
    foreach ($phaseData as $phaseIdx => $phase) {
        $phaseName  = $phase['name'] ?? ('UNNAMED_PHASE_' . $phaseIdx);
        $episodeKey = derive_episode_key($phaseName);
        $signal     = $phase['signal'] ?? null;
        $isRecurringPhase = (stripos($phaseName, 'RECURRING') !== false);

        $sections = $phase['sections'] ?? [];
        if (!is_array($sections)) continue;

        $phaseOffset = $phaseIdx * 1000;
        $firstBeatOfPhase = null;

        foreach ($sections as $sectionIdx => $section) {
            $sectionName = $section['name'] ?? ('Section ' . $sectionIdx);
            $items       = $section['items'] ?? [];
            if (!is_array($items)) $items = [];

            $threadDescParts = [];
            if ($signal) $threadDescParts[] = "Phase signal: {$signal}";
            $threadDescParts[] = "Auto-ingested from \"{$phaseName}\" / \"{$sectionName}\".";
            $threadDesc = implode(' ', $threadDescParts);

            $insThread->execute([
                ':name'        => $sectionName,
                ':series_key'  => SERIES_KEY,
                ':season_key'  => SEASON_KEY,
                ':episode_key' => $episodeKey,
                ':description' => $threadDesc,
                ':color'       => section_color($sectionName, $phaseName),
                ':sort_order'  => $phaseOffset + $sectionIdx,
            ]);
            $threadId = (int)$pdo->lastInsertId();
            $countThreads++;

            $threadReport = [
                'thread_id_shadow' => $threadId,
                'phase'            => $phaseName,
                'episode_key'      => $episodeKey,
                'section'          => $sectionName,
                'color'            => section_color($sectionName, $phaseName),
                'sort_order'       => $phaseOffset + $sectionIdx,
                'beat_count'       => 0,
                'beats'            => [],
            ];

            foreach ($items as $itemIdx => $item) {
                $title = trim($item['title'] ?? '');
                if ($title === '') {
                    fwrite(STDERR, "WARN: empty title in {$phaseName} / {$sectionName} at index {$itemIdx}, skipping.\n");
                    continue;
                }
                $summary = $item['description'] ?? null;
                $status  = map_beat_status($item['status'] ?? null);
                $overlay = $item['overlay'] ?? [];
                $rhythm  = $overlay['rhythm'] ?? null;
                $seqOrder = $itemIdx + 1;

                // Story-time is left NULL here. Pass 3 computes it.
                $insBeat->execute([
                    ':thread_id' => $threadId,
                    ':title'     => $title,
                    ':summary'   => $summary,
                    ':content'   => null,
                    ':rhythm'    => $rhythm,
                    ':seq_order' => $seqOrder,
                    ':status'    => $status,
                ]);
                $beatId = (int)$pdo->lastInsertId();
                $countBeats++;

                if ($firstBeatOfPhase === null) {
                    $firstBeatOfPhase = $beatId;
                }

                if (isset($titleToBeatId[$title])) {
                    $titleCollisions[$title][] = [
                        'shadow_id'  => $beatId,
                        'phase'      => $phaseName,
                        'section'    => $sectionName,
                        'seq_order'  => $seqOrder,
                    ];
                } else {
                    $titleToBeatId[$title] = $beatId;
                }

                $beatQueue[] = [
                    'beat_id' => $beatId,
                    'title'   => $title,
                    'overlay' => $overlay,
                ];

                $phaseOf[$beatId]   = $phaseIdx;
                $filePosOf[$beatId] = count($beatQueue);
                $isRecurringBeat[$beatId] = $isRecurringPhase;

                $threadReport['beat_count']++;
                $threadReport['beats'][] = [
                    'beat_id_shadow' => $beatId,
                    'seq'            => $seqOrder,
                    'title'          => $title,
                    'rhythm'         => $rhythm,
                    'status'         => $status,
                    'story_time_dt'  => null,
                ];
            }

            $threadsReport[] = $threadReport;
        }

        if ($firstBeatOfPhase !== null && preg_match('/^PHASE\s+([IVXLCDM]+)\b/i', trim($phaseName), $mm)) {
            $phaseAnchors[strtoupper($mm[1])] = $firstBeatOfPhase;
        }
    }

    // ---------------- PASS 2 ----------------
    $insParticipant = $pdo->prepare("
        INSERT INTO merm_beat_participants (beat_id, kg_node_id, role, sort_order)
        VALUES (:beat_id, :kg_node_id, NULL, :sort_order)
    ");

    $insLink = $pdo->prepare("
        INSERT INTO merm_beat_links
            (from_beat_id, to_beat_id, relationship, label, pending_resolution, sort_order)
        VALUES
            (:from_beat_id, :to_beat_id, :relationship, NULL, :pending_resolution, :sort_order)
    ");

    $resolvedLinks = [];   // list of ['from' => beatId, 'to' => beatId]

    foreach ($beatQueue as $entry) {
        $beatId  = $entry['beat_id'];
        $title   = $entry['title'];
        $overlay = $entry['overlay'];

        // --- participants ---
        $present = $overlay['present'] ?? [];
        if (is_array($present)) {
            $sortOrder = 0;
            $seen = [];
            foreach ($present as $shortname) {
                $nodeIds = resolve_shortname($shortname, $registry, $unresolvedShortnames);
                foreach ($nodeIds as $nodeId) {
                    if (isset($seen[$nodeId])) continue;
                    $seen[$nodeId] = true;
                    $insParticipant->execute([
                        ':beat_id'    => $beatId,
                        ':kg_node_id' => $nodeId,
                        ':sort_order' => $sortOrder++,
                    ]);
                    $countParticipants++;
                }
            }
        }

        // --- requires ---
        $requires = $overlay['requires'] ?? [];
        if (is_array($requires)) {
            $sortOrder = 0;
            foreach ($requires as $reqTitle) {
                $reqTitle = trim((string)$reqTitle);
                if ($reqTitle === '') continue;

                $resolvedId = try_resolve_link_target($reqTitle, $titleToBeatId, $prefixAliasMap, $phaseAnchors);

                if ($resolvedId !== null) {
                    $insLink->execute([
                        ':from_beat_id'       => $resolvedId,
                        ':to_beat_id'         => $beatId,
                        ':relationship'       => 'requires',
                        ':pending_resolution' => null,
                        ':sort_order'         => $sortOrder++,
                    ]);
                    $countLinksResolved++;
                    if ($resolvedId !== $beatId) {
                        $resolvedLinks[] = ['from' => $resolvedId, 'to' => $beatId];
                    }
                } else {
                    $insLink->execute([
                        ':from_beat_id'       => $beatId,
                        ':to_beat_id'         => 0,
                        ':relationship'       => 'requires',
                        ':pending_resolution' => $reqTitle,
                        ':sort_order'         => $sortOrder++,
                    ]);
                    $countLinksPending++;
                    $pendingReport[$reqTitle][] = [
                        'beat_id_shadow' => $beatId,
                        'beat_title'     => $title,
                        'direction'      => 'requires',
                    ];
                }
            }
        }

        // --- sets_up ---
        $setsUp = $overlay['sets_up'] ?? [];
        if (is_array($setsUp)) {
            $sortOrder = 0;
            foreach ($setsUp as $targetTitle) {
                $targetTitle = trim((string)$targetTitle);
                if ($targetTitle === '') continue;

                $resolvedId = try_resolve_link_target($targetTitle, $titleToBeatId, $prefixAliasMap, $phaseAnchors);

                if ($resolvedId !== null) {
                    $insLink->execute([
                        ':from_beat_id'       => $beatId,
                        ':to_beat_id'         => $resolvedId,
                        ':relationship'       => 'sets_up',
                        ':pending_resolution' => null,
                        ':sort_order'         => $sortOrder++,
                    ]);
                    $countLinksResolved++;
                    if ($resolvedId !== $beatId) {
                        $resolvedLinks[] = ['from' => $beatId, 'to' => $resolvedId];
                    }
                } else {
                    $insLink->execute([
                        ':from_beat_id'       => $beatId,
                        ':to_beat_id'         => 0,
                        ':relationship'       => 'sets_up',
                        ':pending_resolution' => $targetTitle,
                        ':sort_order'         => $sortOrder++,
                    ]);
                    $countLinksPending++;
                    $pendingReport[$targetTitle][] = [
                        'beat_id_shadow' => $beatId,
                        'beat_title'     => $title,
                        'direction'      => 'sets_up',
                    ];
                }
            }
        }
    }

    // ---------------- PASS 3: story-order day assignment ----------------
    //
    // Every beat is seeded with its natural position in the corpus (file
    // order = spine first, then Beats, then Routed In sections, phase by
    // phase). Then we relax along the resolved edges so that every successor
    // sits strictly after its predecessors. The result is a sequential
    // day axis that respects the causal graph.
    //
    // RECURRING beats are cleared back to NULL afterwards so they show up
    // in the "Unassigned" bucket rather than being pinned to an arbitrary day.

    $day = $filePosOf;   // seed with file position

    $N = count($beatQueue);
    $maxIter = $N + 5;
    for ($iter = 0; $iter < $maxIter; $iter++) {
        $changed = false;
        foreach ($resolvedLinks as $link) {
            $from = $link['from'];
            $to   = $link['to'];
            if ($from === $to) continue;
            if (!isset($day[$from]) || !isset($day[$to])) continue;
            if ($day[$to] < $day[$from] + 1) {
                $day[$to] = $day[$from] + 1;
                $changed = true;
            }
        }
        if (!$changed) break;
    }

    // Clear RECURRING beats back to NULL
    foreach ($beatQueue as $entry) {
        $bid = $entry['beat_id'];
        if (!empty($isRecurringBeat[$bid])) {
            $day[$bid] = null;
        }
    }

    // Persist days to merm_beats
    $updStmt = $pdo->prepare("UPDATE merm_beats SET story_time_dt = ? WHERE id = ?");
    $dayToDt = function ($d) use ($anchorDate) {
        if ($d === null) return null;
        $dt = clone $anchorDate;
        if ($d > 1) $dt->modify('+' . ($d - 1) . ' days');
        return $dt->format('Y-m-d H:i:s');
    };
    foreach ($day as $bid => $d) {
        if ($d === null) continue;
        $updStmt->execute([$dayToDt($d), $bid]);
    }

    // Reflect computed days back into the report
    foreach ($threadsReport as &$tr) {
        foreach ($tr['beats'] as &$b) {
            $bid = $b['beat_id_shadow'];
            $b['story_time_dt'] = $dayToDt($day[$bid] ?? null);
        }
        unset($b);
    }
    unset($tr);

    if ($dryRun) {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $fatalError = $e->getMessage();
    fwrite(STDERR, "FATAL: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
}

// =======================================================================
// JSON report
// =======================================================================
$timestamp = date('Ymd_His');
$modeSlug  = $dryRun ? 'dryrun' : 'commit';

$report = [
    'meta' => [
        'generated_at'      => date('c'),
        'mode'              => $dryRun ? 'dry-run' : 'commit',
        'script'            => basename(__FILE__),
        'phase_data_file'   => basename($phaseDataPath),
        'shortnames_file'   => basename($shortnamesPath),
        'series_key'        => SERIES_KEY,
        'season_key'        => SEASON_KEY,
        'story_time_anchor' => STORY_TIME_ANCHOR,
        'fatal_error'       => $fatalError,
        'note'              => 'Days are assigned from NARRATIVE ORDER. Each beat starts at its file position, '
                             . 'then is relaxed along the resolved requires / sets_up edges so every successor sits '
                             . 'strictly after its predecessors. RECURRING beats keep NULL story_time_dt and render '
                             . 'in the "Unassigned" bucket.',
    ],
    'counts' => [
        'phases'            => count($phaseData),
        'threads'           => $countThreads,
        'beats'             => $countBeats,
        'participants'      => $countParticipants,
        'links_resolved'    => $countLinksResolved,
        'links_pending'     => $countLinksPending,
        'phase_anchors'     => $phaseAnchors,
        'max_day'           => empty($day) ? 0 : (int)max(array_filter($day, fn($v) => $v !== null)),
    ],
    'threads'               => $threadsReport,
    'title_collisions'      => $titleCollisions,
    'unresolved_shortnames' => array_keys($unresolvedShortnames),
    'pending_links'         => $pendingReport,
];

$reportPath = rtrim($scriptDir, '/') . "/ingest_{$modeSlug}_{$timestamp}.json";
$reportJson = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (file_put_contents($reportPath, $reportJson) === false) {
    fwrite(STDERR, "WARN: could not write report to {$reportPath}\n");
} else {
    $reportWritten = true;
}

// =======================================================================
// STDOUT summary
// =======================================================================
$maxDay = empty($day) ? 0 : (int)max(array_filter($day, fn($v) => $v !== null));

fwrite(STDOUT, "==================== INGEST SUMMARY ====================\n");
fwrite(STDOUT, "Mode:                  " . ($dryRun ? "DRY RUN (nothing written)" : "COMMIT") . "\n");
fwrite(STDOUT, "Story-time anchor:     " . STORY_TIME_ANCHOR . "\n");
fwrite(STDOUT, "Phases processed:      " . count($phaseData) . "\n");
fwrite(STDOUT, "Threads inserted:      {$countThreads}\n");
fwrite(STDOUT, "Beats inserted:        {$countBeats}\n");
fwrite(STDOUT, "Participants inserted: {$countParticipants}\n");
fwrite(STDOUT, "Links resolved:        {$countLinksResolved}\n");
fwrite(STDOUT, "Links pending:         {$countLinksPending}\n");
fwrite(STDOUT, "Max day assigned:      {$maxDay}\n");
if (!empty($phaseAnchors)) {
    fwrite(STDOUT, "Phase anchors:         ");
    $parts = [];
    foreach ($phaseAnchors as $roman => $bid) $parts[] = "$roman=$bid";
    fwrite(STDOUT, implode(', ', $parts) . "\n");
}
fwrite(STDOUT, "==========================================================\n\n");

if ($titleCollisions) {
    fwrite(STDOUT, "--- TITLE COLLISIONS (first occurrence wins) ---\n");
    foreach ($titleCollisions as $t => $dups) {
        $parts = array_map(fn($d) => "{$d['phase']}/{$d['section']}#{$d['seq_order']}", $dups);
        fwrite(STDOUT, "  \"$t\" also at: " . implode(', ', $parts) . "\n");
    }
    fwrite(STDOUT, "\n");
}

if ($unresolvedShortnames) {
    fwrite(STDOUT, "--- UNRESOLVED SHORTNAMES ---\n");
    foreach (array_keys($unresolvedShortnames) as $sn) {
        fwrite(STDOUT, "  \"$sn\"\n");
    }
    fwrite(STDOUT, "\n");
}

if ($pendingReport) {
    fwrite(STDOUT, "--- PENDING LINK TITLES (author notes / unmet targets) ---\n");
    foreach ($pendingReport as $wantedTitle => $refs) {
        fwrite(STDOUT, "  \"{$wantedTitle}\" referenced by " . count($refs) . " beat(s)\n");
    }
    fwrite(STDOUT, "\n");
}

if ($reportWritten) {
    fwrite(STDOUT, "Report written: {$reportPath}\n");
}
fwrite(STDOUT, $dryRun
    ? "Dry run complete. Nothing was written to the database.\n"
    : "Ingest complete.\n");

if ($fatalError !== null) exit(1);