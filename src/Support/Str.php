<?php

declare(strict_types=1);

namespace Pest\Support;

/**
 * @internal
 */
final class Str
{
    private const string POOL = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private const string PREFIX = '__pest_evaluable_';

    /**
     * @see https://github.com/php/php-src/blob/master/Zend/zend_compile.c
     *
     * @var array<int, string>
     */
    private const array RESERVED_CLASS_NAMES = [
        'array',
        'bool',
        'callable',
        'false',
        'float',
        'int',
        'iterable',
        'mixed',
        'never',
        'null',
        'object',
        'parent',
        'self',
        'static',
        'string',
        'true',
        'void',
    ];

    /**
     * @param  int  $length  the length of the resulting randomized string
     *
     * @see https://github.com/laravel/framework/blob/4.2/src/Illuminate/Support/Str.php#L240-L242
     */
    public static function random(int $length = 16): string
    {
        return substr(str_shuffle(str_repeat(self::POOL, 5)), 0, $length);
    }

    public static function startsWith(string $target, string $search): bool
    {
        return str_starts_with($target, $search);
    }

    public static function endsWith(string $target, string $search): bool
    {
        $length = strlen($search);
        if ($length === 0) {
            return true;
        }

        return $search === substr($target, -$length);
    }

    public static function evaluable(string $code): string
    {
        $code = str_replace('_', '__', $code);

        $code = self::PREFIX.str_replace(' ', '_', $code);

        return (string) preg_replace('/[^a-zA-Z0-9_\x80-\xff]/', '_', $code);
    }

    public static function isValidIdentifier(string $name): bool
    {
        return preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $name) === 1;
    }

    public static function isValidClassName(string $name): bool
    {
        if (! self::isValidIdentifier($name)) {
            return false;
        }

        if (in_array(strtolower($name), self::RESERVED_CLASS_NAMES, true)) {
            return false;
        }

        $tokens = token_get_all(sprintf('<?php %s;', $name));

        return is_array($tokens[1] ?? null) && $tokens[1][0] === T_STRING;
    }

    public static function beforeLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = mb_strrpos($subject, $search);

        if ($pos === false) {
            return $subject;
        }

        return mb_substr($subject, 0, $pos);
    }

    public static function after(string $subject, string $search): string
    {
        return $search === '' ? $subject : array_reverse(explode($search, $subject, 2))[0];
    }

    public static function isUuid(string $value): bool
    {
        return preg_match('/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/iD', $value) > 0;
    }

    public static function isUlid(string $value): bool
    {
        return preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $value) > 0;
    }

    /**
     * @param  array<int, Description>  $describeDescriptions
     */
    public static function describe(array $describeDescriptions, string $testDescription): string
    {
        $descriptionComponents = [...$describeDescriptions, $testDescription];

        return sprintf(str_repeat('`%s` → ', count($describeDescriptions)).'%s', ...$descriptionComponents);
    }

    public static function isEmail(string $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    public static function isUrl(string $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_URL);
    }

    public static function slugify(string $target): string
    {
        $target = preg_replace('/[^a-zA-Z0-9]+/', '-', $target);

        return strtolower(trim((string) $target, '-'));
    }
}
