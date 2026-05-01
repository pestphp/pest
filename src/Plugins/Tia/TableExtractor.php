<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * @internal
 */
final class TableExtractor
{
    private const array DML_PREFIXES = ['select', 'insert', 'update', 'delete'];

    /**
     * @return list<string> Sorted, deduped table names referenced by the
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
     */
    public static function fromMigrationSource(string $php): array
    {
        $tables = [];

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

        $ddlPattern = '/(?:CREATE|ALTER|DROP|TRUNCATE|RENAME)\s+TABLE(?:\s+IF\s+(?:NOT\s+)?EXISTS)?\s+["`\[]?(\w+)["`\]]?/i';

        if (preg_match_all($ddlPattern, $php, $matches) !== false) {
            foreach ($matches[1] as $primary) {
                $lower = strtolower($primary);
                if (! self::isSchemaMeta($lower)) {
                    $tables[$lower] = true;
                }
            }
        }

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

    private static function isSchemaMeta(string $name): bool
    {
        $lower = strtolower($name);

        return in_array($lower, ['sqlite_master', 'sqlite_sequence', 'migrations'], true)
            || str_starts_with($lower, 'pg_')
            || str_starts_with($lower, 'information_schema');
    }
}
