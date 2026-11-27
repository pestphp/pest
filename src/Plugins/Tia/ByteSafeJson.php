<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use JsonException;

/**
 * @internal
 */
final class ByteSafeJson
{
    private const string ESCAPE_PREFIX = "\0base64:";

    private const string ENCODED_ESCAPE_PREFIX = '\u0000base64:';

    /**
     * @throws JsonException
     */
    public static function encode(mixed $value): string
    {
        $plain = json_encode($value, JSON_UNESCAPED_SLASHES);

        if ($plain !== false && ! self::holdsEscapes($plain)) {
            return $plain;
        }

        return json_encode(self::escape($value), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function decode(string $json): mixed
    {
        return self::restore(json_decode($json, true), $json);
    }

    public static function restore(mixed $decoded, string $json): mixed
    {
        return self::holdsEscapes($json) ? self::unescape($decoded) : $decoded;
    }

    private static function holdsEscapes(string $json): bool
    {
        return str_contains($json, self::ENCODED_ESCAPE_PREFIX);
    }

    private static function escape(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::escapeString($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $escaped = [];

        foreach ($value as $key => $item) {
            $escaped[is_string($key) ? self::escapeString($key) : $key] = self::escape($item);
        }

        return $escaped;
    }

    private static function unescape(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::unescapeString($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $unescaped = [];

        foreach ($value as $key => $item) {
            $unescaped[is_string($key) ? self::unescapeString($key) : $key] = self::unescape($item);
        }

        return $unescaped;
    }

    private static function escapeString(string $value): string
    {
        if (preg_match('//u', $value) === 1 && ! str_starts_with($value, self::ESCAPE_PREFIX)) {
            return $value;
        }

        return self::ESCAPE_PREFIX.base64_encode($value);
    }

    private static function unescapeString(string $value): string
    {
        if (! str_starts_with($value, self::ESCAPE_PREFIX)) {
            return $value;
        }

        $raw = base64_decode(substr($value, strlen(self::ESCAPE_PREFIX)), true);

        return $raw === false ? $value : $raw;
    }
}
