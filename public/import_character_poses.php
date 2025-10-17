<?php 
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// view_import_character_poses.php
// Import tool with dry-run and force-update options

// Recommended PDO flags to avoid emulated prepares and ensure exceptions
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pageTitle = "Import Character Poses";
$content = "";

$messages = [
    'errors' => [],
    'success' => []
];

// simple CSRF token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['import_token'])) {
    $_SESSION['import_token'] = bin2hex(random_bytes(16));
}
$token = $_SESSION['import_token'];

$previewRows = [];
$previewCount = 0;
$totalMatches = 0;
$insertableMatches = 0;
$updateMatches = 0;

// Dry-run pagination limits
$DRY_RUN_PAGE_SIZE = 200; // max rows to show per page for insert/update lists

// read pagination params (GET) for dry-run lists
$page_insert = isset($_GET['page_insert']) ? max(1, intval($_GET['page_insert'])) : 1;
$page_update = isset($_GET['page_update']) ? max(1, intval($_GET['page_update'])) : 1;
$offset_insert = ($page_insert - 1) * $DRY_RUN_PAGE_SIZE;
$offset_update = ($page_update - 1) * $DRY_RUN_PAGE_SIZE;

$dryRunInsertRows = [];
$dryRunUpdateRows = [];

// ------------------------------------------------------------------
// Keep form state between POST and pagination clicks (read from GET/POST)
// ------------------------------------------------------------------
$cur_char_from = isset($_REQUEST['character_from']) ? intval($_REQUEST['character_from']) : 1;
$cur_char_to   = isset($_REQUEST['character_to'])   ? intval($_REQUEST['character_to'])   : 5;
$cur_pose_from = isset($_REQUEST['pose_from'])      ? intval($_REQUEST['pose_from'])      : 1;
$cur_pose_to   = isset($_REQUEST['pose_to'])        ? intval($_REQUEST['pose_to'])        : 999;
$cur_dry_run_flag = isset($_REQUEST['dry_run']) && (string)$_REQUEST['dry_run'] === '1';
$cur_force_update_flag = isset($_REQUEST['force_update']) && (string)$_REQUEST['force_update'] === '1';

// --------------------------------------------------------------------------------
// POST handling (import or POST-triggered dry-run). For pagination clicks use GET.
// --------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic server-side sanitization & validation
    $postedToken = $_POST['token'] ?? '';
    if (!hash_equals($_SESSION['import_token'], (string)$postedToken)) {
        $messages['errors'][] = "Invalid form token. Please reload the page and try again.";
    } else {
        // prefer POST values for authoritative operation
        $char_from = isset($_POST['character_from']) ? intval($_POST['character_from']) : null;
        $char_to   = isset($_POST['character_to'])   ? intval($_POST['character_to'])   : null;
        $pose_from = isset($_POST['pose_from'])      ? intval($_POST['pose_from'])      : null;
        $pose_to   = isset($_POST['pose_to'])        ? intval($_POST['pose_to'])        : null;

        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';
        $force_update = isset($_POST['force_update']) && $_POST['force_update'] === '1';

        // provide sensible server-side bounds
        $MAX_ID = 1000000;

        if ($char_from === null || $char_to === null || $pose_from === null || $pose_to === null) {
            $messages['errors'][] = "All fields are required.";
        } elseif ($char_from < 0 || $char_to < 0 || $pose_from < 0 || $pose_to < 0) {
            $messages['errors'][] = "IDs must be non-negative integers.";
        } elseif ($char_from > $MAX_ID || $char_to > $MAX_ID || $pose_from > $MAX_ID || $pose_to > $MAX_ID) {
            $messages['errors'][] = "Values too large.";
        } elseif ($char_from > $char_to) {
            $messages['errors'][] = "'Character from' must be <= 'Character to'.";
        } elseif ($pose_from > $pose_to) {
            $messages['errors'][] = "'Pose from' must be <= 'Pose to'.";
        }

        if (empty($messages['errors'])) {
            try {
                // 1) Total matching rows in the view (before duplicate exclusion)
                $countTotalSql = "SELECT COUNT(*) AS c
                    FROM v_character_pose_angle_combinations v
                    WHERE v.character_id BETWEEN ? AND ?
                      AND v.pose_id BETWEEN ? AND ?";
                $countStmt = $pdo->prepare($countTotalSql);
                $countStmt->execute([$char_from, $char_to, $pose_from, $pose_to]);
                $totalMatches = (int)$countStmt->fetchColumn();

                if ($totalMatches === 0) {
                    $messages['errors'][] = "No rows found for the selected ranges. Nothing to import.";
                } else {
                    // 2) Count how many would actually be inserted (exclude existing combos)
                    $countInsertableSql = "
                        SELECT COUNT(*) AS c
                        FROM v_character_pose_angle_combinations v
                        WHERE v.character_id BETWEEN ? AND ?
                          AND v.pose_id BETWEEN ? AND ?
                          AND NOT EXISTS (
                              SELECT 1 FROM character_poses cp
                              WHERE cp.character_id = v.character_id
                                AND cp.pose_id = v.pose_id
                                AND cp.angle_id = v.angle_id
                          )
                    ";
                    $countInsertableStmt = $pdo->prepare($countInsertableSql);
                    $countInsertableStmt->execute([$char_from, $char_to, $pose_from, $pose_to]);
                    $insertableMatches = (int)$countInsertableStmt->fetchColumn();

                    // 3) Count how many would be updates (existing combos)
                    $countUpdateSql = "
                        SELECT COUNT(*) AS c
                        FROM v_character_pose_angle_combinations v
                        WHERE v.character_id BETWEEN ? AND ?
                          AND v.pose_id BETWEEN ? AND ?
                          AND EXISTS (
                              SELECT 1 FROM character_poses cp
                              WHERE cp.character_id = v.character_id
                                AND cp.pose_id = v.pose_id
                                AND cp.angle_id = v.angle_id
                          )
                    ";
                    $countUpdateStmt = $pdo->prepare($countUpdateSql);
                    $countUpdateStmt->execute([$char_from, $char_to, $pose_from, $pose_to]);
                    $updateMatches = (int)$countUpdateStmt->fetchColumn();

                    // If dry-run requested (POST), fetch the row lists (paginated)
                    if ($dry_run) {
                        // insertable rows (not exists)
                        $fetchInsertableSql = "
                            SELECT
                                v.character_id, v.character_name,
                                v.pose_id, v.pose_name,
                                v.angle_id, v.angle_name,
                                v.description,
                                CONCAT(v.character_name, ' - ', v.pose_name, ' - ', v.angle_name) AS name
                            FROM v_character_pose_angle_combinations v
                            WHERE v.character_id BETWEEN ? AND ?
                              AND v.pose_id BETWEEN ? AND ?
                              AND NOT EXISTS (
                                  SELECT 1 FROM character_poses cp
                                  WHERE cp.character_id = v.character_id
                                    AND cp.pose_id = v.pose_id
                                    AND cp.angle_id = v.angle_id
                              )
                            ORDER BY v.character_id, v.pose_id, v.angle_id
                            LIMIT ? OFFSET ?
                        ";
                        $stmtIns = $pdo->prepare($fetchInsertableSql);
                        // bind with explicit integer types to avoid quoted numbers in SQL
                        $stmtIns->bindValue(1, $char_from, PDO::PARAM_INT);
                        $stmtIns->bindValue(2, $char_to,   PDO::PARAM_INT);
                        $stmtIns->bindValue(3, $pose_from, PDO::PARAM_INT);
                        $stmtIns->bindValue(4, $pose_to,   PDO::PARAM_INT);
                        $stmtIns->bindValue(5, $DRY_RUN_PAGE_SIZE, PDO::PARAM_INT);
                        $stmtIns->bindValue(6, $offset_insert,     PDO::PARAM_INT);
                        $stmtIns->execute();
                        $dryRunInsertRows = $stmtIns->fetchAll(\PDO::FETCH_ASSOC);

                        // updateable rows (exists)
                        $fetchUpdateSql = "
                            SELECT
                                v.character_id, v.character_name,
                                v.pose_id, v.pose_name,
                                v.angle_id, v.angle_name,
                                v.description,
                                CONCAT(v.character_name, ' - ', v.pose_name, ' - ', v.angle_name) AS name
                            FROM v_character_pose_angle_combinations v
                            WHERE v.character_id BETWEEN ? AND ?
                              AND v.pose_id BETWEEN ? AND ?
                              AND EXISTS (
                                  SELECT 1 FROM character_poses cp
                                  WHERE cp.character_id = v.character_id
                                    AND cp.pose_id = v.pose_id
                                    AND cp.angle_id = v.angle_id
                              )
                            ORDER BY v.character_id, v.pose_id, v.angle_id
                            LIMIT ? OFFSET ?
                        ";
                        $stmtUpd = $pdo->prepare($fetchUpdateSql);
                        // bind with explicit integer types to avoid quoted numbers in SQL
                        $stmtUpd->bindValue(1, $char_from, PDO::PARAM_INT);
                        $stmtUpd->bindValue(2, $char_to,   PDO::PARAM_INT);
                        $stmtUpd->bindValue(3, $pose_from, PDO::PARAM_INT);
                        $stmtUpd->bindValue(4, $pose_to,   PDO::PARAM_INT);
                        $stmtUpd->bindValue(5, $DRY_RUN_PAGE_SIZE, PDO::PARAM_INT);
                        $stmtUpd->bindValue(6, $offset_update,     PDO::PARAM_INT);
                        $stmtUpd->execute();
                        $dryRunUpdateRows = $stmtUpd->fetchAll(\PDO::FETCH_ASSOC);

                        $messages['success'][] = "Dry-run: matched {$totalMatches} rows (insertable: {$insertableMatches}, updateable: {$updateMatches}). Listing results (page controls shown if results exceed {$DRY_RUN_PAGE_SIZE}).";
                    } else {
                        // Not a dry-run => perform DB changes
                        if ($insertableMatches === 0 && (!$force_update || $updateMatches === 0)) {
                            $messages['errors'][] = "Nothing to insert or update for the selected ranges.";
                        } else {
                            $pdo->beginTransaction();

                            if ($force_update) {
                                // Use INSERT ... ON DUPLICATE KEY UPDATE
                                // NOTE: This requires a UNIQUE index on (character_id, pose_id, angle_id)
                                // If you don't have such an index, ON DUPLICATE KEY won't trigger updates.
                                $insertSql = "
                                    INSERT INTO `character_poses` (
                                        `name`,
                                        `order`,
                                        `description`,
                                        `character_id`,
                                        `pose_id`,
                                        `angle_id`
                                    )
                                    SELECT
                                        CONCAT(v.character_name, ' - ', v.pose_name, ' - ', v.angle_name) AS name,
                                        0 AS `order`,
                                        v.description,
                                        v.character_id,
                                        v.pose_id,
                                        v.angle_id
                                    FROM v_character_pose_angle_combinations v
                                    WHERE v.character_id BETWEEN ? AND ?
                                      AND v.pose_id BETWEEN ? AND ?
                                    ON DUPLICATE KEY UPDATE
                                        `name` = VALUES(`name`),
                                        `order` = VALUES(`order`),
                                        `description` = VALUES(`description`)
                                ";
                                $insertStmt = $pdo->prepare($insertSql);
                                $insertStmt->execute([$char_from, $char_to, $pose_from, $pose_to]);

                                // For reporting we rely on the precomputed counts:
                                $inserted = $insertableMatches;
                                $updated = $updateMatches;
                                $pdo->commit();

                                $messages['success'][] = "Force import completed. Inserted: {$inserted}. Updated: {$updated}.";
                            } else {
                                // Standard insert only for non-existing rows (keeps duplicate protection)
                                $insertSql = "
                                    INSERT INTO `character_poses` (
                                        `name`,
                                        `order`,
                                        `description`,
                                        `character_id`,
                                        `pose_id`,
                                        `angle_id`
                                    )
                                    SELECT
                                        CONCAT(v.character_name, ' - ', v.pose_name, ' - ', v.angle_name) AS name,
                                        0 AS `order`,
                                        v.description,
                                        v.character_id,
                                        v.pose_id,
                                        v.angle_id
                                    FROM v_character_pose_angle_combinations v
                                    WHERE v.character_id BETWEEN ? AND ?
                                      AND v.pose_id BETWEEN ? AND ?
                                      AND NOT EXISTS (
                                          SELECT 1 FROM character_poses cp
                                          WHERE cp.character_id = v.character_id
                                            AND cp.pose_id = v.pose_id
                                            AND cp.angle_id = v.angle_id
                                      )
                                ";
                                $insertStmt = $pdo->prepare($insertSql);
                                $insertStmt->execute([$char_from, $char_to, $pose_from, $pose_to]);

                                $inserted = $insertableMatches;
                                $pdo->commit();

                                $skipped = $totalMatches - $inserted;
                                if ($skipped < 0) $skipped = 0;

                                $messages['success'][] = "Import completed. {$inserted} rows inserted. {$skipped} rows skipped because they already existed.";
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $messages['errors'][] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// ------------------------------------------------------------------
// Handle dry-run via GET (pagination clicks) - reuse same logic but allow GET
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $cur_dry_run_flag) {
    // use the request-derived values
    $char_from = $cur_char_from;
    $char_to   = $cur_char_to;
    $pose_from = $cur_pose_from;
    $pose_to   = $cur_pose_to;
    $dry_run = true;
    $force_update = $cur_force_update_flag;

    // basic validation (non-exceptional)
    if ($char_from < 0 || $char_to < 0 || $pose_from < 0 || $pose_to < 0 || $char_from > $char_to || $pose_from > $pose_to) {
        // invalid ranges -> show nothing but a friendly message
        $messages['errors'][] = "Invalid range parameters in query string.";
    } else {
        try {
            $countTotalSql = "SELECT COUNT(*) AS c
                FROM v_character_pose_angle_combinations v
                WHERE v.character_id BETWEEN ? AND ?
                  AND v.pose_id BETWEEN ? AND ?";
            $countStmt = $pdo->prepare($countTotalSql);
            $countStmt->execute([$char_from, $char_to, $pose_from, $pose_to]);
            $totalMatches = (int)$countStmt->fetchColumn();

            $countInsertableSql = "
                SELECT COUNT(*) AS c
                FROM v_character_pose_angle_combinations v
                WHERE v.character_id BETWEEN ? AND ?
                  AND v.pose_id BETWEEN ? AND ?
                  AND NOT EXISTS (
                      SELECT 1 FROM character_poses cp
                      WHERE cp.character_id = v.character_id
                        AND cp.pose_id = v.pose_id
                        AND cp.angle_id = v.angle_id
                  )
            ";
            $countInsertableStmt = $pdo->prepare($countInsertableSql);
            $countInsertableStmt->execute([$char_from, $char_to, $pose_from, $pose_to]);
            $insertableMatches = (int)$countInsertableStmt->fetchColumn();

            $countUpdateSql = "
                SELECT COUNT(*) AS c
                FROM v_character_pose_angle_combinations v
                WHERE v.character_id BETWEEN ? AND ?
                  AND v.pose_id BETWEEN ? AND ?
                  AND EXISTS (
                      SELECT 1 FROM character_poses cp
                      WHERE cp.character_id = v.character_id
                        AND cp.pose_id = v.pose_id
                        AND cp.angle_id = v.angle_id
                  )
            ";
            $countUpdateStmt = $pdo->prepare($countUpdateSql);
            $countUpdateStmt->execute([$char_from, $char_to, $pose_from, $pose_to]);
            $updateMatches = (int)$countUpdateStmt->fetchColumn();

            // fetch paginated lists
            $fetchInsertableSql = "
                SELECT
                    v.character_id, v.character_name,
                    v.pose_id, v.pose_name,
                    v.angle_id, v.angle_name,
                    v.description,
                    CONCAT(v.character_name, ' - ', v.pose_name, ' - ', v.angle_name) AS name
                FROM v_character_pose_angle_combinations v
                WHERE v.character_id BETWEEN ? AND ?
                  AND v.pose_id BETWEEN ? AND ?
                  AND NOT EXISTS (
                      SELECT 1 FROM character_poses cp
                      WHERE cp.character_id = v.character_id
                        AND cp.pose_id = v.pose_id
                        AND cp.angle_id = v.angle_id
                  )
                ORDER BY v.character_id, v.pose_id, v.angle_id
                LIMIT ? OFFSET ?
            ";
            $stmtIns = $pdo->prepare($fetchInsertableSql);
            $stmtIns->bindValue(1, $char_from, PDO::PARAM_INT);
            $stmtIns->bindValue(2, $char_to,   PDO::PARAM_INT);
            $stmtIns->bindValue(3, $pose_from, PDO::PARAM_INT);
            $stmtIns->bindValue(4, $pose_to,   PDO::PARAM_INT);
            $stmtIns->bindValue(5, $DRY_RUN_PAGE_SIZE, PDO::PARAM_INT);
            $stmtIns->bindValue(6, $offset_insert,     PDO::PARAM_INT);
            $stmtIns->execute();
            $dryRunInsertRows = $stmtIns->fetchAll(\PDO::FETCH_ASSOC);

            $fetchUpdateSql = "
                SELECT
                    v.character_id, v.character_name,
                    v.pose_id, v.pose_name,
                    v.angle_id, v.angle_name,
                    v.description,
                    CONCAT(v.character_name, ' - ', v.pose_name, ' - ', v.angle_name) AS name
                FROM v_character_pose_angle_combinations v
                WHERE v.character_id BETWEEN ? AND ?
                  AND v.pose_id BETWEEN ? AND ?
                  AND EXISTS (
                      SELECT 1 FROM character_poses cp
                      WHERE cp.character_id = v.character_id
                        AND cp.pose_id = v.pose_id
                        AND cp.angle_id = v.angle_id
                  )
                ORDER BY v.character_id, v.pose_id, v.angle_id
                LIMIT ? OFFSET ?
            ";
            $stmtUpd = $pdo->prepare($fetchUpdateSql);
            $stmtUpd->bindValue(1, $char_from, PDO::PARAM_INT);
            $stmtUpd->bindValue(2, $char_to,   PDO::PARAM_INT);
            $stmtUpd->bindValue(3, $pose_from, PDO::PARAM_INT);
            $stmtUpd->bindValue(4, $pose_to,   PDO::PARAM_INT);
            $stmtUpd->bindValue(5, $DRY_RUN_PAGE_SIZE, PDO::PARAM_INT);
            $stmtUpd->bindValue(6, $offset_update,     PDO::PARAM_INT);
            $stmtUpd->execute();
            $dryRunUpdateRows = $stmtUpd->fetchAll(\PDO::FETCH_ASSOC);

            $messages['success'][] = "Dry-run: matched {$totalMatches} rows (insertable: {$insertableMatches}, updateable: {$updateMatches}).";
        } catch (\Exception $e) {
            $messages['errors'][] = "Could not perform dry-run: " . $e->getMessage();
        }
    }
}

// Provide a small preview (first 25 rows) of the view for user convenience
try {
    $previewSql = "
        SELECT
            character_id, character_name,
            pose_id, pose_name,
            angle_id, angle_name,
            description
        FROM v_character_pose_angle_combinations
        ORDER BY character_id, pose_id, angle_id
        LIMIT 25
    ";
    $previewStmt = $pdo->query($previewSql);
    $previewRows = $previewStmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    // ignore preview errors but note them
    $messages['errors'][] = "Could not fetch preview: " . $e->getMessage();
}

// render HTML using your pattern
ob_start();
?><style>
:root{
  --primary: #0d6efd;
  --primary-rgb: 13,110,253;
  --muted: #6c757d;
  --bg: #fbfdff;
  --card-bg: #ffffff;
  --glass: rgba(255,255,255,0.6);
  --shadow: 0 6px 18px rgba(20,24,28,0.06);
  --radius: 10px;
}

/* Page / container */
body {
  background: linear-gradient(180deg, #f7f9fc 0%, var(--bg) 100%);
  font-family: Inter, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  color: #222;
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
}

.container { 
  max-width: 1100px;
  margin: 1.5rem auto;
  padding-left: .75rem;
  padding-right: .75rem;
}

/* Cards */
.card {
  background: var(--card-bg);
  border: 0;
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
}

.card-header {
  background: transparent;
  border-bottom: 1px solid #eef3f7;
  font-weight: 600;
  font-size: 0.98rem;
  padding: 0.65rem 1rem;
}

/* Forms & inputs */
.form-label { 


  display: inline-block;
  width: 150px !important;

  flex: 0 0 150px; /* don’t let it shrink */
 font-weight:600;
  color: #333;
   font-size: .88rem; }
.form-control {
  border-radius: 5px;
  border: 1px solid #e6ebf0;
  padding: 5px;
  height: 10px;
  margin-bottom: 5px;
  box-shadow: none;
  transition: box-shadow .12s ease, border-color .12s ease, transform .06s ease;
}
.form-control:focus {
  outline: 0;
  border-color: rgba(var(--primary-rgb), 0.95);
  box-shadow: 0 6px 18px rgba(var(--primary-rgb), 0.08);
  transform: translateY(-1px);
}
.form-check {
    margin-bottom: 5px;
}
/* Buttons */
.btn {
  border-radius: 9px;
  padding: .45rem .8rem;
  font-weight: 600;
  letter-spacing: .2px;
}
.btn-primary {
  background: linear-gradient(180deg, var(--primary), #0b5ed7);
  border: 0;
  box-shadow: 0 6px 18px rgba(var(--primary-rgb), 0.12);
}
.btn-secondary {
  background: linear-gradient(180deg, #f6f7f9, #eef1f6);
  border: 1px solid #e3e7eb;
  color: #333;
}

/* Alerts */
.alert {
  border-radius: 8px;
  border: 1px solid rgba(0,0,0,0.03);
  box-shadow: none;
  padding: .6rem .9rem;
}
.alert-success { border-left: 4px solid #198754; }
.alert-danger  { border-left: 4px solid #dc3545; }
.alert-info    { border-left: 4px solid var(--primary); }

/* Tables */
.table {
  margin-bottom: 0;
  border-spacing: 0;
    align-self: flex-start;
    border-collapse: collapse !important; /* collapse spacing */

}
.table thead th {
  border-bottom: 1px solid #eef3f7;
  font-weight: 700;
  font-size: .65rem;
  color: #444;
}
.table tbody tr:hover {
  background: linear-gradient(90deg, rgba(13,110,253,0.02), rgba(13,110,253,0.01));
  transform: translateZ(0);
}
.table {
  border-collapse: collapse !important; /* collapse spacing */
}

.table tr {
  height: 10px !important;
  max-height: 10px !important;
}

.table td, .table th {
  font-size: .65rem;       /* shrink font */
  line-height: 10px !important;    /* match row height */
  height: 10px !important;
  max-height: 10px !important;
  padding: 5px 0 !important;
  margin: 0 !important;
  overflow: hidden !important;
  vertical-align: top !important;  /* top align avoids extra centering space */
  white-space: nowrap !important;
}

/* Truncate long descriptions in table cells */
.truncate {
  max-width: 26rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: inline-block;
  vertical-align: middle;
}

/* Tiny utilities */
.small-muted { color: var(--muted); font-size: .82rem; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, "Roboto Mono", monospace; font-size: .86rem; }
.count-bubble {
  display: inline-block;
  background: rgba(var(--primary-rgb), 0.12);
  color: var(--primary);
  border-radius: 999px;
  padding: .18rem .5rem;
  font-weight: 700;
  font-size: .82rem;
  margin-left: .5rem;
}

/* Pagination tweaks */
.pagination .page-item .page-link {
  border-radius: 7px;
  padding: .35rem .6rem;
}
.pagination .page-item.active .page-link {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
}

/* Dry-run tables slightly smaller and denser */
.dryrun-table .table td, .dryrun-table .table th { padding: .35rem .5rem; font-size: .82rem; }

/* Responsive niceties */
@media (max-width: 768px) {
  .truncate { max-width: 12rem; }
  .container { padding-left: .5rem; padding-right: .5rem; }
  .card-header { font-size: .95rem; }
}

/* Accessibility: keep a visible focus ring for keyboard users */
:focus {
  outline: 3px solid rgba(var(--primary-rgb), 0.12);
  outline-offset: 2px;
}
</style><div class="container my-4">
    <h1 class="mb-3"><?= htmlspecialchars($pageTitle) ?></h1><!-- messages -->
<?php foreach ($messages['errors'] as $err): ?>
    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>
<?php foreach ($messages['success'] as $s): ?>
    <div class="alert alert-success" role="alert"><?= htmlspecialchars($s) ?></div>
<?php endforeach; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="post" novalidate>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <div class="row gx-3">
                <div class="col-md-3 mb-3">
                    <label for="character_from" class="form-label">Character ID — From</label>
                    <input type="number" class="form-control" id="character_from" name="character_from"
                           required min="0" value="<?= htmlspecialchars($cur_char_from) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="character_to" class="form-label">Character ID — To</label>
                    <input type="number" class="form-control" id="character_to" name="character_to"
                           required min="0" value="<?= htmlspecialchars($cur_char_to) ?>">
                </div>

                <div class="col-md-3 mb-3">
                    <label for="pose_from" class="form-label">Pose ID — From</label>
                    <input type="number" class="form-control" id="pose_from" name="pose_from"
                           required min="0" value="<?= htmlspecialchars($cur_pose_from) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="pose_to" class="form-label">Pose ID — To</label>
                    <input type="number" class="form-control" id="pose_to" name="pose_to"
                           required min="0" value="<?= htmlspecialchars($cur_pose_to) ?>">
                </div>
            </div>

            <div class="row gx-3 mt-2">
                <div class="col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="dry_run" name="dry_run"
                            <?= ($cur_dry_run_flag) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="dry_run">
                            Dry-run
                        </label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="force_update" name="force_update"
                            <?= ($cur_force_update_flag) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="force_update">
                            Force re-insert / update
                        </label>
                    </div>
                </div>
                <div class="col-md-6 text-muted">
                    <small>
                        <!-- helper text if you want -->
                    </small>
                </div>
            </div>

            <div class="mt-3 d-flex align-items-center">
                <button type="submit" class="btn btn-primary me-2">Run Import</button>
                <button type="button" id="resetFormBtn" class="btn btn-secondary">Reset</button>
                <small class="text-muted ms-3">
            </div>
        </form>
    </div>
</div>

<?php if ($totalMatches !== 0): ?>
    <div class="alert alert-info mb-4">
        Total matching rows in view: <strong><?= intval($totalMatches) ?></strong><br>
        Rows not present in target (would be inserted): <strong><?= intval($insertableMatches) ?></strong><br>
        Rows already present in target (would be updated if force checked): <strong><?= intval($updateMatches) ?></strong>
    </div>
<?php endif; ?>

<!-- Dry-run lists -->
<?php if (!empty($dryRunInsertRows) || !empty($dryRunUpdateRows)): ?>
    <div class="card mb-4">
        <div class="card-header">
            Dry-run details (page sizes: <?= $DRY_RUN_PAGE_SIZE ?>)
        </div>
        <div class="card-body">
            <h5>Rows that would be <strong>inserted</strong> (page <?= $page_insert ?>)</h5>
            <?php if (!empty($dryRunInsertRows)): ?>
                <div class="table-responsive mb-3">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>character_id</th>
                                <th>character_name</th>
                                <th>pose_id</th>
                                <th>pose_name</th>
                                <th>angle_id</th>
                                <th>angle_name</th>
                                <th>name</th>
                                <th>description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = $offset_insert + 1; foreach ($dryRunInsertRows as $r): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($r['character_id']) ?></td>
                                <td><?= htmlspecialchars($r['character_name']) ?></td>
                                <td><?= htmlspecialchars($r['pose_id']) ?></td>
                                <td><?= htmlspecialchars($r['pose_name']) ?></td>
                                <td><?= htmlspecialchars($r['angle_id']) ?></td>
                                <td><?= htmlspecialchars($r['angle_name']) ?></td>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <td><?= htmlspecialchars(mb_strimwidth($r['description'], 0, 140, '...')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($insertableMatches > $DRY_RUN_PAGE_SIZE): ?>
                    <?php $max_pages = ceil($insertableMatches / $DRY_RUN_PAGE_SIZE); ?>
                    <?php
                    // build base query-string with current parameters (exclude token for safety)
                    $baseParams = [
                        'character_from' => $cur_char_from,
                        'character_to'   => $cur_char_to,
                        'pose_from'      => $cur_pose_from,
                        'pose_to'        => $cur_pose_to,
                        'dry_run'        => $cur_dry_run_flag ? 1 : 0,
                        'force_update'   => $cur_force_update_flag ? 1 : 0
                    ];
                    $qsBase = http_build_query($baseParams);
                    ?>
                    <nav aria-label="Insert pages">
                        <ul class="pagination">
                            <?php for ($p = 1; $p <= $max_pages && $p <= 20; $p++): ?>
                                <?php $href = '?' . $qsBase . '&page_insert=' . $p . '#dryrun'; ?>
                                <li class="page-item <?= $p === $page_insert ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= htmlspecialchars($href) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php if ($max_pages > 20): ?>
                        <div class="text-muted">Only first 20 pages shown in pagination. Use narrower ranges to inspect more precisely.</div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-muted">No insertable rows on this page.</div>
            <?php endif; ?>

            <hr>

            <h5>Rows that would be <strong>updated</strong> (page <?= $page_update ?>)</h5>
            <?php if (!empty($dryRunUpdateRows)): ?>
                <div class="table-responsive mb-3">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>character_id</th>
                                <th>character_name</th>
                                <th>pose_id</th>
                                <th>pose_name</th>
                                <th>angle_id</th>
                                <th>angle_name</th>
                                <th>name</th>
                                <th>description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $j = $offset_update + 1; foreach ($dryRunUpdateRows as $r): ?>
                            <tr>
                                <td><?= $j++ ?></td>
                                <td><?= htmlspecialchars($r['character_id']) ?></td>
                                <td><?= htmlspecialchars($r['character_name']) ?></td>
                                <td><?= htmlspecialchars($r['pose_id']) ?></td>
                                <td><?= htmlspecialchars($r['pose_name']) ?></td>
                                <td><?= htmlspecialchars($r['angle_id']) ?></td>
                                <td><?= htmlspecialchars($r['angle_name']) ?></td>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <td><?= htmlspecialchars(mb_strimwidth($r['description'], 0, 140, '...')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($updateMatches > $DRY_RUN_PAGE_SIZE): ?>
                    <?php $max_pages_u = ceil($updateMatches / $DRY_RUN_PAGE_SIZE); ?>
                    <nav aria-label="Update pages">
                        <ul class="pagination">
                            <?php for ($p = 1; $p <= $max_pages_u && $p <= 20; $p++): // limit pages shown to 20 ?>
                                <?php $href = '?' . $qsBase . '&page_update=' . $p . '#dryrun'; ?>
                                <li class="page-item <?= $p === $page_update ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= htmlspecialchars($href) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php if ($max_pages_u > 20): ?>
                        <div class="text-muted">Only first 20 pages shown in pagination. Use narrower ranges to inspect more precisely.</div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-muted">No updateable rows on this page.</div>
            <?php endif; ?>
        </div>
    </div>
    <script>
    (function(){
    const btn = document.getElementById('resetFormBtn');
    if (!btn) return;
    btn.addEventListener('click', function (e) {
        // Redirect to same path (no query string, no hash)
        const target = window.location.origin + window.location.pathname;
        window.location.href = target;
    });
    })();
    </script>
<?php endif; ?>

</div>
<?php
require "floatool.php";
$body = ob_get_clean();
$content = $body . $eruda;
$spw->renderLayout($content, $pageTitle);
