<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * @internal
 */
final class TableExtractor
{
    /**
     * DML prefixes we accept. DDL (`CREATE`, `ALTER`, `DROP`,
     * `TRUNCATE`, `RENAME`) is deliberately excluded — those come
     * from migrations fired by `RefreshDatabase`, and capturing them
     * here would attribute every migration table to every test.
     */
    private const array DML_PREFIXES = ['select', 'insert', 'update', 'delete'];

    /**
     * @return list<string> Sorted, deduped table names referenced by the
     *                      SQL statement. Empty when the statement is
     *                      DDL, empty, or unparseable.
     */
    public static function fromSql(string $sql): array
    {
        $trimmed = ltrim($sql);

        if ($trimmed === '') {
            return [];
        }

        $prefix = strtolower(substr($trimmed, 0, 6));

        $matched = false;
        foreach (self::DML_PREFIXES as $dml) {
            if (str_starts_with($prefix, $dml)) {
                $matched = true;

                break;
            }
        }

        if (! $matched) {
            return [];
        }

        // Match `from`, `into`, `update`, `join` and capture the
        // following identifier, tolerating the common quoting
        // styles: "double", `back`, [bracket], or bare.
        $pattern = '/(?:\bfrom|\binto|\bupdate|\bjoin)\s+(?:"([^"]+)"|`([^`]+)`|\[([^\]]+)\]|(\w+))/i';

        if (preg_match_all($pattern, $sql, $matches) === false) {
            return [];
        }

        $tables = [];

        for ($i = 0, $n = count($matches[0]); $i < $n; $i++) {
            $name = $matches[1][$i] !== ''
                ? $matches[1][$i]
                : ($matches[2][$i] !== ''
                    ? $matches[2][$i]
                    : ($matches[3][$i] !== ''
                        ? $matches[3][$i]
                        : $matches[4][$i]));
            if ($name === '') {
                continue;
            }
            if (self::isSchemaMeta($name)) {
                continue;
            }

            $tables[strtolower($name)] = true;
        }

        $out = array_keys($tables);
        sort($out);

        return $out;
    }

    /**
     * @return list<string> Table names referenced by `Schema::` calls,
     *                      raw DDL, or DML inside the given migration
     *                      file contents. Empty when nothing matches —
     *                      callers treat that as "fall back to the
     *                      broad watch pattern".
     *
     * Three passes:
     *  1. `Schema::create|table|drop|dropIfExists|dropColumn[s]|rename`
     *     captures the conventional Laravel migration shape.
     *  2. Raw DDL fallback: scans for `CREATE / ALTER / DROP /
     *     TRUNCATE / RENAME TABLE <name>` patterns inside string
     *     literals (i.e. `DB::statement('CREATE TABLE …')`,
     *     `DB::unprepared('ALTER TABLE …')`).
     *  3. DML inside migration bodies — `INSERT INTO`, `UPDATE … SET`,
     *     `DELETE FROM`, and Laravel's fluent `DB::table('foo')`.
     *     Catches the seeded-lookup-table case where a migration
     *     populates rows that tests later read.
     *
     * False positives possible when the same syntax appears in a
     * comment or unrelated string, but over-attribution is
     * correctness-safe.
     */
    public static function fromMigrationSource(string $php): array
    {
        $tables = [];

        // Pass 1: Schema:: calls. `dropColumn` (singular) covers
        // `Schema::table('users', fn ($t) => $t->dropColumn('foo'))`
        // — the closure body's column op is on Blueprint, but the
        // outer `Schema::table('users', …)` is what we capture here.
        $schemaPattern = '/Schema::\s*(?:create|table|drop|dropIfExists|dropColumn|dropColumns|rename)\s*\(\s*[\'"]([^\'"]+)[\'"](?:\s*,\s*[\'"]([^\'"]+)[\'"])?/';

        if (preg_match_all($schemaPattern, $php, $matches) !== false) {
            foreach ($matches[1] as $i => $primary) {
                $tables[strtolower($primary)] = true;

                $secondary = $matches[2][$i] ?? '';
                if ($secondary !== '') {
                    $tables[strtolower($secondary)] = true;
                }
            }
        }

        // Pass 2: raw DDL fallback. Matches the table name following
        // `CREATE/ALTER/DROP/TRUNCATE/RENAME TABLE` (plus Postgres'
        // `IF EXISTS` / `IF NOT EXISTS` variants), with optional
        // ANSI / MySQL / SQL Server quoting.
        $ddlPattern = '/(?:CREATE|ALTER|DROP|TRUNCATE|RENAME)\s+TABLE(?:\s+IF\s+(?:NOT\s+)?EXISTS)?\s+["`\[]?(\w+)["`\]]?/i';

        if (preg_match_all($ddlPattern, $php, $matches) !== false) {
            foreach ($matches[1] as $primary) {
                $lower = strtolower($primary);
                if (! self::isSchemaMeta($lower)) {
                    $tables[$lower] = true;
                }
            }
        }

        // Pass 3: DML inside migration bodies. Migrations that seed
        // lookup tables via `DB::statement('INSERT INTO roles …')`,
        // `DB::table('statuses')->insert(…)`, `UPDATE foo SET …`, or
        // `DELETE FROM bar` are common in Laravel. Without picking
        // these up, an edit to the seed payload would route through
        // only the schema'd tables and silently skip every test that
        // reads from the populated table. Fluent-builder calls
        // (`DB::table('x')`) and raw SQL strings are both covered.
        $dmlPatterns = [
            '/INSERT\s+(?:IGNORE\s+)?INTO\s+["`\[]?(\w+)["`\]]?/i',
            '/UPDATE\s+["`\[]?(\w+)["`\]]?\s+SET\b/i',
            '/DELETE\s+FROM\s+["`\[]?(\w+)["`\]]?/i',
            '/DB::table\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
        ];

        foreach ($dmlPatterns as $pattern) {
            if (preg_match_all($pattern, $php, $matches) === false) {
                continue;
            }
            foreach ($matches[1] as $name) {
                $lower = strtolower($name);
                if (! self::isSchemaMeta($lower)) {
                    $tables[$lower] = true;
                }
            }
        }

        $out = array_keys($tables);
        sort($out);

        return $out;
    }

    /**
     * Filters out driver-internal tables that show up as DB::listen
     * targets without representing user schema: SQLite's master
     * catalogue, Laravel's own `migrations` metadata.
     */
    private static function isSchemaMeta(string $name): bool
    {
        $lower = strtolower($name);

        return in_array($lower, ['sqlite_master', 'sqlite_sequence', 'migrations'], true)
            || str_starts_with($lower, 'pg_')
            || str_starts_with($lower, 'information_schema');
    }
}
