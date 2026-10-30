<?php
// src/Service/SqlishEngine.php
// ─────────────────────────────────────────────────────────────────────────────
// SQLISH — Structured SQL-Op Extraction Engine
//
// Encapsulated, WTF-agnostic module (same relationship to WTF as Wrish has:
// zero dependency on WTF, usable entirely standalone via public/sqlish.php,
// and only later wired in as a WTF step handler in a separate thread).
//
// DESIGN PHILOSOPHY — directly modeled on WrishEngine's proven division of
// labor, carried one level further:
//
//   1. The AI NEVER decides what table, column, or database operation is
//      being performed. That is 100% human-configured in advance via a
//      sqlish_op_templates row (created through public/sqlish.php's UI).
//      The engine only ever asks the AI to fill in VALUES for a fixed,
//      pre-approved set of fields — never structure, never identifiers.
//
//   2. The AI is never asked to write SQL. Ever. There is no code path in
//      this file that takes AI output and treats it as executable SQL
//      syntax. The AI's entire contribution is a JSON array of plain
//      values; every actual database write is built by THIS engine from
//      prepared statements using column names that were validated against
//      INFORMATION_SCHEMA.COLUMNS at template-save time, never at
//      extraction time and never from AI output.
//
//   3. Exactly ONE extraction call per run (not per item, not per column).
//      Mirrors WrishEngine's single-title-extraction-call-per-run design.
//      The JSON schema for that call is built mechanically from the
//      template's sqlish_op_template_columns rows — never hand-authored
//      per use case, never touched by AI judgment about shape.
//
//   4. NO RETRY on malformed/invalid extraction output — same as Wrish's
//      title extraction. If the AI's JSON doesn't parse, or an individual
//      item fails schema validation, that item is logged to
//      sqlish_run_items with status='failed' and the run continues with
//      whatever items DID validate. A run is not an all-or-nothing
//      transaction across items — partial success is the normal case, not
//      an error state. This mirrors the "simple chain of cheap steps,
//      escalate to human on real failure" philosophy: failed items surface
//      in the UI for a human to fix or re-run, rather than the engine
//      attempting increasingly elaborate self-repair.
//
//   5. System columns (user_id, created_at, foreign keys carried over from
//      run context such as a prior stage's writeback) are NEVER supplied
//      by the AI. They come from sqlish_op_template_columns.default_value
//      or .context_source_key, resolved entirely by this engine.
//
// Every class in this file follows the same singleton convention as the
// rest of the app (see WrishEngine, WorkflowExecutor): no $pdo or $spw is
// ever passed through a constructor. global $spw; $this->pdo =
// $spw->getPDO(); — every time, everywhere. Callers must have already
// required bootstrap.php + env_locals.php before constructing this class.
// ─────────────────────────────────────────────────────────────────────────────
namespace App\Service;

use PDO;
use Exception;

class SqlishEngine
{
    const EXTRACTOR_CONFIG_ID = 'sqlish_extractor_v1';
    const MAX_ITEMS_PER_RUN = 500; // sanity ceiling, mirrors WrishEngine::HARD_MAX_STEPS in spirit

    /** @var string[] operation types the UI is allowed to configure a template as */
    const ALLOWED_OPERATION_TYPES = ['insert', 'update', 'select'];

    private PDO $pdo;
    private $spw;
    private GeneratorRepository $repo;

    public function __construct()
    {
        global $spw;
        $this->spw = $spw;
        $this->pdo = $spw->getPDO();
        $this->repo = new GeneratorRepository($this->pdo);
    }

    // ═════════════════════════════════════════════════════════════════════
    // TEMPLATE MANAGEMENT
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Lists real tables in the current database via INFORMATION_SCHEMA, for
     * the template-builder UI's table picker. Never accepts a table name
     * from the client without checking it against this list at save time.
     */
    public function listAvailableTables(): array
    {
        $dbName = $this->spw->getDbName();
        $stmt = $this->pdo->prepare("
            SELECT TABLE_NAME AS name
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME ASC
        ");
        $stmt->execute([$dbName]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
    }

    /**
     * Lists columns for a given table via INFORMATION_SCHEMA, for the
     * template-builder UI's column picker. This is the ONLY source of
     * truth for what columns exist — never trust a column name that
     * didn't come from this call at template-save time.
     */
    public function listTableColumns(string $tableName): array
    {
        $this->assertRealTable($tableName);
        $dbName = $this->spw->getDbName();
        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME AS name, DATA_TYPE AS data_type, IS_NULLABLE AS is_nullable,
                   COLUMN_DEFAULT AS column_default, COLUMN_KEY AS column_key, EXTRA AS extra
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION ASC
        ");
        $stmt->execute([$dbName, $tableName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Validates that $tableName is a real table in this database. Throws
     * if not. This is the single choke point every write/select path in
     * this engine routes through before ever interpolating a table name
     * into SQL — table names cannot be parameterized as bind values in
     * PDO, so validating against INFORMATION_SCHEMA first and then
     * identifier-quoting (backticks) is the correct, standard-safe
     * approach, not string-escaping the name.
     */
    private function assertRealTable(string $tableName): void
    {
        $tables = $this->listAvailableTables();
        if (!in_array($tableName, $tables, true)) {
            throw new Exception("'{$tableName}' is not a real table in this database — refusing to use it.");
        }
    }

    /**
     * Validates that every name in $columnNames is a real column of
     * $tableName. Same rationale as assertRealTable() — column names are
     * identifiers, not values, and must be checked against
     * INFORMATION_SCHEMA before ever being backtick-quoted into SQL.
     */
    private function assertRealColumns(string $tableName, array $columnNames): void
    {
        $cols = array_column($this->listTableColumns($tableName), 'name');
        foreach ($columnNames as $c) {
            if (!in_array($c, $cols, true)) {
                throw new Exception("'{$c}' is not a real column of '{$tableName}' — refusing to use it.");
            }
        }
    }



       public function listTemplates(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sqlish_op_templates WHERE user_id = ? ORDER BY updated_at DESC");
        $stmt->execute([$userId]);
        return array_map([$this, 'hydrateTemplate'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Paginated variant of listTemplates(). Returns a page envelope
     * ({items, page, limit, total, total_pages}) rather than a bare array,
     * so the UI can render prev/next controls and a jump-to-page input
     * without needing a separate count query of its own.
     */
    public function listTemplatesPaged(int $userId, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM sqlish_op_templates WHERE user_id = ?");
        $countStmt->execute([$userId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT * FROM sqlish_op_templates WHERE user_id = ? ORDER BY updated_at DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = array_map([$this, 'hydrateTemplate'], $stmt->fetchAll(PDO::FETCH_ASSOC));

        return [
            'templates' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => max(1, (int)ceil($total / $limit)),
        ];
    }

    public function getTemplate(int $userId, int $templateId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sqlish_op_templates WHERE id = ? AND user_id = ?");
        $stmt->execute([$templateId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrateTemplate($row) : null;
    }

    private function hydrateTemplate(array $row): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sqlish_op_template_columns WHERE template_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$row['id']]);
        $row['columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $row;
    }

    /**
     * Creates or updates a template. This is the ONLY place table/column
     * identity and operation type are ever decided — always by a human
     * through the UI, never by AI, never at run time. Every table/column
     * name is validated against INFORMATION_SCHEMA here, once, at save
     * time — not re-validated at every run (re-validating here on every
     * save is enough; the schema doesn't change between runs of the same
     * template in normal operation, and if it does, the next save attempt
     * will catch a now-invalid column).
     */
    public function saveTemplate(int $userId, array $tpl): array
    {
        $targetTable = trim($tpl['target_table'] ?? '');
        $operationType = $tpl['operation_type'] ?? 'insert';
        $keyColumn = trim($tpl['key_column'] ?? '') ?: null;
        $columns = $tpl['columns'] ?? [];

        if ($targetTable === '') {
            throw new Exception('target_table is required.');
        }
        if (!in_array($operationType, self::ALLOWED_OPERATION_TYPES, true)) {
            throw new Exception("operation_type must be one of: " . implode(', ', self::ALLOWED_OPERATION_TYPES));
        }
        if (in_array($operationType, ['update', 'select'], true) && !$keyColumn) {
            throw new Exception("key_column is required for operation_type '{$operationType}'.");
        }
        if (empty($columns)) {
            throw new Exception('At least one column is required.');
        }

        $this->assertRealTable($targetTable);
        $columnNames = array_map(fn($c) => $c['column_name'] ?? '', $columns);
        $this->assertRealColumns($targetTable, array_filter($columnNames));
        if ($keyColumn) {
            $this->assertRealColumns($targetTable, [$keyColumn]);
        }

        $this->pdo->beginTransaction();
        try {
            if (!empty($tpl['id'])) {
                $stmt = $this->pdo->prepare("
                    UPDATE sqlish_op_templates
                    SET name=?, description=?, target_table=?, operation_type=?, key_column=?,
                        ai_instructions=?, writeback_context_key=?, is_active=?
                    WHERE id=? AND user_id=?
                ");
                $stmt->execute([
                    $tpl['name'] ?? 'Untitled Template',
                    $tpl['description'] ?? '',
                    $targetTable,
                    $operationType,
                    $keyColumn,
                    $tpl['ai_instructions'] ?? '',
                    $tpl['writeback_context_key'] ?? null,
                    !empty($tpl['is_active']) ? 1 : 1,
                    $tpl['id'],
                    $userId,
                ]);
                $templateId = (int)$tpl['id'];
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO sqlish_op_templates
                        (user_id, name, description, target_table, operation_type, key_column, ai_instructions, writeback_context_key, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $userId,
                    $tpl['name'] ?? 'Untitled Template',
                    $tpl['description'] ?? '',
                    $targetTable,
                    $operationType,
                    $keyColumn,
                    $tpl['ai_instructions'] ?? '',
                    $tpl['writeback_context_key'] ?? null,
                ]);
                $templateId = (int)$this->pdo->lastInsertId();
            }

            $this->pdo->prepare("DELETE FROM sqlish_op_template_columns WHERE template_id = ?")->execute([$templateId]);

            $cStmt = $this->pdo->prepare("
                INSERT INTO sqlish_op_template_columns
                    (template_id, column_name, data_type, is_ai_fillable, is_required, default_value, context_source_key, description_for_ai, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($columns as $i => $c) {
                if (empty($c['column_name'])) continue;
                $cStmt->execute([
                    $templateId,
                    $c['column_name'],
                    $c['data_type'] ?? null,
                    !empty($c['is_ai_fillable']) ? 1 : 0,
                    !empty($c['is_required']) ? 1 : 0,
                    $c['default_value'] ?? null,
                    $c['context_source_key'] ?? null,
                    $c['description_for_ai'] ?? '',
                    $i,
                ]);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->getTemplate($userId, $templateId);
    }

    public function deleteTemplate(int $userId, int $templateId): void
    {
        $this->pdo->prepare("DELETE FROM sqlish_op_template_columns WHERE template_id = ?")->execute([$templateId]);
        $this->pdo->prepare("DELETE FROM sqlish_op_templates WHERE id=? AND user_id=?")->execute([$templateId, $userId]);
    }



   
    // ═════════════════════════════════════════════════════════════════════
    // RUNS
    // ═════════════════════════════════════════════════════════════════════

    public function listRuns(int $userId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, t.name AS template_name, t.target_table, t.operation_type
            FROM sqlish_runs r
            JOIN sqlish_op_templates t ON t.id = r.template_id
            WHERE r.user_id = ?
            ORDER BY r.updated_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Paginated variant of listRuns(). Returns a page envelope
     * ({items, page, limit, total, total_pages}) rather than a bare array,
     * so the UI can render prev/next controls and a jump-to-page input
     * without needing a separate count query of its own.
     */
    public function listRunsPaged(int $userId, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM sqlish_runs WHERE user_id = ?");
        $countStmt->execute([$userId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT r.*, t.name AS template_name, t.target_table, t.operation_type
            FROM sqlish_runs r
            JOIN sqlish_op_templates t ON t.id = r.template_id
            WHERE r.user_id = ?
            ORDER BY r.updated_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'runs' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => max(1, (int)ceil($total / $limit)),
        ];
    }

    public function getRun(int $userId, int $runId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, t.name AS template_name, t.target_table, t.operation_type
            FROM sqlish_runs r
            JOIN sqlish_op_templates t ON t.id = r.template_id
            WHERE r.id = ? AND r.user_id = ?
        ");
        $stmt->execute([$runId, $userId]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$run) return null;

        $run['context'] = json_decode($run['context_json'] ?? '{}', true) ?: [];

        $stmt = $this->pdo->prepare("SELECT * FROM sqlish_run_items WHERE run_id = ? ORDER BY item_index ASC");
        $stmt->execute([$runId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$it) {
            $it['extracted_values'] = json_decode($it['extracted_values_json'] ?? 'null', true);
            $it['final_values'] = json_decode($it['final_values_json'] ?? 'null', true);
        }
        unset($it);
        $run['items'] = $items;

        return $run;
    }

    /**
     * Runs one complete extraction+write pass for a template against a
     * chunk of source text. This is the entire lifecycle of a "run" in
     * this module — unlike Wrish, there is no multi-step battle-plan
     * concept here; a Sqlish run is a single bounded unit of work (extract
     * N items, write what validates), which is what makes it a good fit
     * for a single WTF step later. Per the "simple chain over loops"
     * decision, retrying a failed run is just calling this method again
     * (e.g. as the next step in a WTF workflow), not something this
     * engine loops on internally.
     *
     * @param array $extraContext optional key/value data merged into this
     *   run's context BEFORE column resolution — this is how a later
     *   template's context_source_key columns can reference an earlier
     *   run's writeback (e.g. {"episode_id": 42} passed in explicitly by
     *   the caller, such as a WTF step reading a prior step's result).
     */
    public function runOnce(int $userId, int $templateId, string $sourceText, array $extraContext = [], ?string $title = null): array
    {
        $template = $this->getTemplate($userId, $templateId);
        if (!$template) {
            throw new Exception("Template {$templateId} not found.");
        }
        if (empty($template['columns'])) {
            throw new Exception("Template '{$template['name']}' has no columns configured.");
        }

        // Re-validate identifiers at run time too — cheap, and protects
        // against the underlying table having been altered/dropped since
        // the template was last saved.
        $this->assertRealTable($template['target_table']);
        $aiColumns = array_values(array_filter($template['columns'], fn($c) => !empty($c['is_ai_fillable'])));
        $systemColumns = array_values(array_filter($template['columns'], fn($c) => empty($c['is_ai_fillable'])));

        $runTitle = $title ?: ($template['name'] . ' — ' . date('Y-m-d H:i'));
        $stmt = $this->pdo->prepare("
            INSERT INTO sqlish_runs (user_id, template_id, title, source_text, status, context_json)
            VALUES (?, ?, ?, ?, 'running', ?)
        ");
        $stmt->execute([$userId, $templateId, $runTitle, $sourceText, json_encode($extraContext, JSON_UNESCAPED_UNICODE)]);
        $runId = (int)$this->pdo->lastInsertId();

        try {
            $items = $this->extractItemsNoRetry($userId, $runId, $templateId, $template, $sourceText, $aiColumns);
        } catch (Exception $e) {
            $this->pdo->prepare("UPDATE sqlish_runs SET status='failed', last_error=?, finished_at=NOW() WHERE id=?")
                ->execute([$e->getMessage(), $runId]);
            return $this->getRun($userId, $runId);
        }

        $writtenCount = 0;
        $failedCount = 0;
        $writebackIds = [];

        foreach ($items as $index => $aiValues) {
            $itemResult = $this->writeOneItem($userId, $runId, $index, $template, $aiColumns, $systemColumns, $aiValues, $extraContext);
            if ($itemResult['status'] === 'written') {
                $writtenCount++;
                if ($itemResult['row_id'] !== null) {
                    $writebackIds[] = $itemResult['row_id'];
                }
            } else {
                $failedCount++;
            }
        }

        $newContext = $extraContext;
        if (!empty($template['writeback_context_key']) && !empty($writebackIds)) {
            $newContext[$template['writeback_context_key']] = [
                'min_id' => min($writebackIds),
                'max_id' => max($writebackIds),
                'count' => count($writebackIds),
                'ids' => $writebackIds,
            ];
        }

        $finalStatus = ($failedCount > 0 && $writtenCount === 0) ? 'failed' : 'done';

        $stmt = $this->pdo->prepare("
            UPDATE sqlish_runs
            SET status=?, items_written=?, items_failed=?, context_json=?, finished_at=NOW()
            WHERE id=?
        ");
        $stmt->execute([$finalStatus, $writtenCount, $failedCount, json_encode($newContext, JSON_UNESCAPED_UNICODE), $runId]);

        return $this->getRun($userId, $runId);
    }

    public function cancelRun(int $userId, int $runId): void
    {
        $this->pdo->prepare("UPDATE sqlish_runs SET status='cancelled', finished_at=NOW() WHERE id=? AND user_id=?")->execute([$runId, $userId]);
    }



   
    // ═════════════════════════════════════════════════════════════════════
    // EXTRACTION (the one and only AI call per run)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Builds a JSON schema mechanically from the template's AI-fillable
     * columns, calls the extractor generator exactly once, parses the
     * result. NO RETRY on failure — mirrors WrishEngine's title
     * extraction exactly. On any failure (call error, unparseable output,
     * empty result), returns an empty array; the caller (runOnce) treats
     * that as "zero items produced," which is a valid, non-halting
     * outcome, not an exception that stops the run from being recorded.
     */
    private function extractItemsNoRetry(int $userId, int $runId, int $templateId, array $template, string $sourceText, array $aiColumns): array
    {
        $schema = $this->buildJsonSchema($aiColumns);
        $prompt = $this->buildExtractionPrompt($template, $aiColumns, $schema, $sourceText);

        $raw = '';
        try {
            $raw = $this->callGenerator($userId, self::EXTRACTOR_CONFIG_ID, [
                ['role' => 'user', 'content' => $prompt],
            ]);
        } catch (Exception $e) {
            $this->logExtractionAttempt($runId, $userId, $templateId, $sourceText, $schema, '', false, 0, $e->getMessage());
            return [];
        }

        $items = $this->parseExtractionOutput($raw);
        $ok = $items !== null;
        $count = $ok ? count($items) : 0;

        $this->logExtractionAttempt(
            $runId, $userId, $templateId, $sourceText, $schema, $raw, $ok, $count,
            $ok ? null : 'Could not parse a JSON array from the extractor output.'
        );

        if (!$ok) {
            return [];
        }

        if (count($items) > self::MAX_ITEMS_PER_RUN) {
            $items = array_slice($items, 0, self::MAX_ITEMS_PER_RUN);
        }

        return $items;
    }

    /**
     * Builds a minimal JSON Schema (draft-agnostic, just enough structure
     * to describe an array of flat objects) from a template's AI-fillable
     * columns. This schema is generated PURELY from
     * sqlish_op_template_columns rows — the AI never sees the underlying
     * database column names' full metadata, table name, or operation
     * type; it only sees the field name + type + the human-authored
     * description_for_ai hint. This is the mechanism that keeps the AI's
     * entire surface area scoped to "fill in these values," nothing more.
     */
    private function buildJsonSchema(array $aiColumns): array
    {
        $properties = [];
        $required = [];
        foreach ($aiColumns as $c) {
            $jsonType = $this->mapSqlTypeToJsonType($c['data_type'] ?? '');
            $properties[$c['column_name']] = [
                'type' => $jsonType,
                'description' => $c['description_for_ai'] ?? '',
            ];
            if (!empty($c['is_required'])) {
                $required[] = $c['column_name'];
            }
        }
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
            ],
        ];
    }

    private function mapSqlTypeToJsonType(string $sqlType): string
    {
        $sqlType = strtolower($sqlType);
        if (in_array($sqlType, ['int', 'bigint', 'smallint', 'tinyint', 'mediumint'], true)) {
            return 'integer';
        }
        if (in_array($sqlType, ['decimal', 'float', 'double'], true)) {
            return 'number';
        }
        return 'string';
    }

    private function buildExtractionPrompt(array $template, array $aiColumns, array $schema, string $sourceText): string
    {
        $schemaJson = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $fieldList = implode(', ', array_map(fn($c) => $c['column_name'], $aiColumns));

        $parts = [];
        $parts[] = "You are extracting structured records from the source text below.";
        $parts[] = "FIELDS TO EXTRACT PER RECORD: {$fieldList}";
        if (!empty($template['ai_instructions'])) {
            $parts[] = "SPECIFIC INSTRUCTIONS FOR THIS EXTRACTION:\n{$template['ai_instructions']}";
        }
        $parts[] = "JSON SCHEMA (respond with an array of objects matching this):\n```json\n{$schemaJson}\n```";
        $parts[] = "═══ SOURCE TEXT ═══\n{$sourceText}\n═══ END SOURCE TEXT ═══";

        return implode("\n\n", $parts);
    }

    /**
     * Parses the extractor's raw output into an array of associative
     * arrays. Returns null (not an empty array) specifically when parsing
     * failed outright, so the caller can distinguish "AI said there are
     * genuinely zero records" (valid empty array) from "we couldn't
     * understand the response at all" (null) — both end up producing zero
     * items either way per the no-retry philosophy, but the log entry
     * records the distinction for human diagnosis.
     */
    private function parseExtractionOutput(string $raw): ?array
    {
        $cleaned = trim($raw);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        if ($cleaned === '') {
            return null;
        }

        $decoded = json_decode($cleaned, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Attempt to locate the first '[' ... last ']' as a fallback,
            // in case the model wrapped valid JSON in stray prose despite
            // instructions not to.
            $start = strpos($cleaned, '[');
            $end = strrpos($cleaned, ']');
            if ($start !== false && $end !== false && $end > $start) {
                $slice = substr($cleaned, $start, $end - $start + 1);
                $decoded = json_decode($slice, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return null;
                }
            } else {
                return null;
            }
        }

        if (!is_array($decoded)) {
            return null;
        }

        // A single object instead of an array of one — accept it rather
        // than failing, since this is a very plausible, harmless deviation.
        if (isset($decoded['0']) === false && array_keys($decoded) !== range(0, count($decoded) - 1)) {
            // Associative array (single object) detected.
            return [$decoded];
        }

        return $decoded;
    }

    private function logExtractionAttempt(
        int $runId, int $userId, int $templateId, string $sourceText, array $schema,
        string $rawOutput, bool $ok, int $itemsFound, ?string $error
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO sqlish_extraction_log
                (run_id, user_id, template_id, source_text, schema_used_json, raw_extraction_output, parsed_ok, items_found, parse_error)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $runId, $userId, $templateId, $sourceText,
            json_encode($schema, JSON_UNESCAPED_UNICODE), $rawOutput,
            $ok ? 1 : 0, $itemsFound, $error,
        ]);
    }

    // ═════════════════════════════════════════════════════════════════════
    // WRITE PATH — the only place any SQL is actually built and executed.
    // Every identifier here (table name, column names) was already
    // validated against INFORMATION_SCHEMA at template-save time (see
    // saveTemplate()) and is re-validated once per run in runOnce(). Only
    // VALUES are ever bound as parameters — never identifiers, and never
    // anything from raw AI text used as a fragment of SQL syntax.
    // ═════════════════════════════════════════════════════════════════════

    private function writeOneItem(
        int $userId, int $runId, int $index, array $template,
        array $aiColumns, array $systemColumns, array $aiValues, array $context
    ): array {
        // Validate the AI's item against required fields before touching
        // the database at all.
        $missing = [];
        foreach ($aiColumns as $c) {
            if (!empty($c['is_required']) && (!array_key_exists($c['column_name'], $aiValues) || $aiValues[$c['column_name']] === '' || $aiValues[$c['column_name']] === null)) {
                $missing[] = $c['column_name'];
            }
        }
        if (!empty($missing)) {
            $this->recordItem($runId, $userId, $index, $aiValues, null, 'failed', null, 'Missing required field(s): ' . implode(', ', $missing));
            return ['status' => 'failed', 'row_id' => null];
        }

        // Merge system/default/context columns — never from AI text.
        $finalValues = [];
        foreach ($aiColumns as $c) {
            if (array_key_exists($c['column_name'], $aiValues)) {
                $finalValues[$c['column_name']] = $aiValues[$c['column_name']];
            }
        }
        foreach ($systemColumns as $c) {
            $colName = $c['column_name'];
            if (!empty($c['context_source_key']) && array_key_exists($c['context_source_key'], $context)) {
                $ctxVal = $context[$c['context_source_key']];
                // If the context value is itself a writeback bundle
                // (min_id/max_id/count/ids), a bare column reference isn't
                // meaningful — require the human to have set
                // context_source_key to a scalar-producing key, or fall
                // back to default_value if the referenced value is an array.
                $finalValues[$colName] = is_array($ctxVal) ? ($c['default_value'] ?? null) : $ctxVal;
            } else {
                $finalValues[$colName] = $c['default_value'];
            }
        }
        // Always stamp user_id if the target table has that column and no
        // explicit config already claimed it — a light, safe convenience,
        // not a hidden override: only applied if 'user_id' is one of this
        // template's configured columns already (never silently added).
        if (array_key_exists('user_id', $finalValues) && ($finalValues['user_id'] === null || $finalValues['user_id'] === '')) {
            $finalValues['user_id'] = $userId;
        }

        try {
            $rowId = $this->executeWrite($template, $finalValues, $context);
            $this->recordItem($runId, $userId, $index, $aiValues, $finalValues, 'written', $rowId, null);
            return ['status' => 'written', 'row_id' => $rowId];
        } catch (Exception $e) {
            $this->recordItem($runId, $userId, $index, $aiValues, $finalValues, 'failed', null, $e->getMessage());
            return ['status' => 'failed', 'row_id' => null];
        }
    }




   
    /**
     * Executes exactly one write against target_table, using ONLY
     * validated column identifiers (backtick-quoted) and bound parameter
     * values. Returns the new row's auto-increment id for insert
     * operations (or null if the table has no such column / operation is
     * not insert).
     */
    private function executeWrite(array $template, array $values, array $context): ?int
    {
        $table = $template['target_table'];
        $op = $template['operation_type'];

        // Re-check every column name against INFORMATION_SCHEMA one more
        // time right before building SQL — belt-and-suspenders, since this
        // is the actual write path and the cost of one more metadata query
        // per item is trivial next to the cost of ever being wrong here.
        $this->assertRealColumns($table, array_keys($values));

        if ($op === 'insert') {
            $cols = array_keys($values);
            $colSql = implode(', ', array_map(fn($c) => "`{$c}`", $cols));
            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $sql = "INSERT INTO `{$table}` ({$colSql}) VALUES ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($values));
            return (int)$this->pdo->lastInsertId() ?: null;
        }

        if ($op === 'update') {
            $keyColumn = $template['key_column'];
            $this->assertRealColumns($table, [$keyColumn]);
            $keyValue = $context[$keyColumn] ?? null;
            if ($keyValue === null || (is_array($keyValue))) {
                throw new Exception("update requires a scalar value for key_column '{$keyColumn}' in run context, none found.");
            }
            $setCols = array_keys($values);
            $setSql = implode(', ', array_map(fn($c) => "`{$c}` = ?", $setCols));
            $sql = "UPDATE `{$table}` SET {$setSql} WHERE `{$keyColumn}` = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([...array_values($values), $keyValue]);
            return null;
        }

        // 'select' operation type: reserved for future read-only templates
        // (e.g. "fetch character records matching these values" for a
        // downstream continuity-lookup use case). Not yet implemented as a
        // write path because it isn't a write — no-op here by design.
        throw new Exception("operation_type '{$op}' has no write path (this may be intentional for read-only templates — see SqlishEngine notes).");
    }

    private function recordItem(int $runId, int $userId, int $index, array $extractedValues, ?array $finalValues, string $status, ?int $rowId, ?string $error): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO sqlish_run_items
                (run_id, user_id, item_index, extracted_values_json, final_values_json, status, written_row_id, error_text)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $runId, $userId, $index,
            json_encode($extractedValues, JSON_UNESCAPED_UNICODE),
            $finalValues !== null ? json_encode($finalValues, JSON_UNESCAPED_UNICODE) : null,
            $status, $rowId, $error,
        ]);
    }

    // ═════════════════════════════════════════════════════════════════════
    // AI CALL PLUMBING — identical shape to WrishEngine::callGenerator()
    // ═════════════════════════════════════════════════════════════════════

    private function callGenerator(int $userId, string $configId, array $conversationMessages): string
    {
        $rec = $this->repo->findActiveForUser($configId, $userId);
        if (!$rec) {
            throw new Exception("Generator config '{$configId}' not found or inactive for user {$userId}.");
        }

        $sysParts = [];
        if ($rec->systemRole) {
            $sysParts[] = $rec->systemRole;
        }
        if (!empty($rec->instructions)) {
            $sysParts[] = "═══ RULES & PROTOCOLS ═══\n" . implode("\n\n", $rec->instructions);
        }

        $messages = [['role' => 'system', 'content' => implode("\n\n", $sysParts)]];
        foreach ($conversationMessages as $msg) {
            $messages[] = [
                'role' => ($msg['role'] === 'user') ? 'user' : 'assistant',
                'content' => $msg['content'],
            ];
        }

        $aiOptions = [];
        if (isset($rec->parameters['temperature'])) {
            $aiOptions['temperature'] = (float)$rec->parameters['temperature']['default'];
        }
        $aiOptions['max_tokens'] = isset($rec->parameters['max_tokens'])
            ? (int)$rec->parameters['max_tokens']['default']
            : 16384;

        return $this->spw->getAIProvider()->sendMessage($rec->model, $messages, $aiOptions);
    }
}
