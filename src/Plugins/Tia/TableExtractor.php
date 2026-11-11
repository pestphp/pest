<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * @internal
 */
final class TableExtractor
{
    private const array DML_PREFIXES = ['select', 'insert', 'update', 'delete', 'with', 'replace'];

    private const string IDENTIFIER = '(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)';

    /**
     * @return list<string> Sorted, deduped table names referenced by the
     */
    public static function fromSql(string $sql): array
    {
        $trimmed = ltrim($sql);

        if ($trimmed === '') {
            return [];
        }

        if (preg_match('/^[a-zA-Z]+/', $trimmed, $prefixMatch) !== 1) {
            return [];
        }

        if (! in_array(strtolower($prefixMatch[0]), self::DML_PREFIXES, true)) {
            return [];
        }

        $pattern = '/\b(?:from|into|update|join)\s+('.self::IDENTIFIER.'(?:\s*\.\s*'.self::IDENTIFIER.')*)/i';

        if (preg_match_all($pattern, $sql, $matches) === false) {
            return [];
        }

        $tables = [];

        foreach ($matches[1] as $qualified) {
            $name = self::unqualified($qualified);

            if ($name === '') {
                continue;
            }
            if (self::isSchemaMeta($name)) {
                continue;
            }

            $tables[strtolower($name)] = true;
        }

        $out = array_map(strval(...), array_keys($tables));
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
                $tables[strtolower(self::lastDottedSegment($primary))] = true;

                $secondary = $matches[2][$i] ?? '';
                if ($secondary !== '') {
                    $tables[strtolower(self::lastDottedSegment($secondary))] = true;
                }
            }
        }

        $qualified = '('.self::IDENTIFIER.'(?:\s*\.\s*'.self::IDENTIFIER.')*)';

        $sqlPatterns = [
            '/(?:CREATE|ALTER|DROP|TRUNCATE|RENAME)\s+TABLE(?:\s+IF\s+(?:NOT\s+)?EXISTS)?\s+'.$qualified.'/i',
            '/INSERT\s+(?:IGNORE\s+)?INTO\s+'.$qualified.'/i',
            '/UPDATE\s+'.$qualified.'\s+SET\b/i',
            '/DELETE\s+FROM\s+'.$qualified.'/i',
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match_all($pattern, $php, $matches) === false) {
                continue;
            }
            foreach ($matches[1] as $name) {
                $lower = strtolower(self::unqualified($name));
                if ($lower !== '' && ! self::isSchemaMeta($lower)) {
                    $tables[$lower] = true;
                }
            }
        }

        if (preg_match_all('/DB::table\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $php, $matches) !== false) {
            foreach ($matches[1] as $name) {
                $lower = strtolower(self::lastDottedSegment($name));
                if ($lower !== '' && ! self::isSchemaMeta($lower)) {
                    $tables[$lower] = true;
                }
            }
        }

        $out = array_map(strval(...), array_keys($tables));
        sort($out);

        return $out;
    }

    private static function unqualified(string $qualified): string
    {
        $name = '';

        foreach (explode('.', $qualified) as $segment) {
            $segment = trim($segment, " \t\n\r\"`[]");

            if ($segment === '') {
                continue;
            }

            if (self::isSchemaMeta($segment)) {
                return '';
            }

            $name = $segment;
        }

        return $name;
    }

    private static function lastDottedSegment(string $name): string
    {
        $position = strrpos($name, '.');

        return $position === false ? $name : substr($name, $position + 1);
    }

    private static function isSchemaMeta(string $name): bool
    {
        $lower = strtolower($name);

        return in_array($lower, ['sqlite_master', 'sqlite_sequence', 'migrations'], true)
            || str_starts_with($lower, 'pg_')
            || str_starts_with($lower, 'information_schema');
    }
}
