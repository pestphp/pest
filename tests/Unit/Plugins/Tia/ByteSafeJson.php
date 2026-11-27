<?php

declare(strict_types=1);

use Pest\Plugins\Parallel;
use Pest\Plugins\Tia\ByteSafeJson;

it('round-trips keys and values that are not valid UTF-8', function (): void {
    $value = [
        "app/caf\xe9.php" => ["tests/caf\xe9Test.php", 'tests/PlainTest.php'],
        'plain' => "message \xff\xfe",
        'count' => 3,
        'list' => [1, 2.5, true, null],
    ];

    expect(ByteSafeJson::decode(ByteSafeJson::encode($value)))->toBe($value);
});

it('keeps valid UTF-8 as plain JSON', function (): void {
    expect(ByteSafeJson::encode(['app/café.php' => ['tests/a/b.php']]))
        ->toBe('{"app/caf\\u00e9.php":["tests/a/b.php"]}');
});

it('does not confuse a real string with an escaped one', function (): void {
    $lookalike = "\0base64:".base64_encode("caf\xe9");
    $value = [$lookalike => $lookalike, "caf\xe9" => "caf\xe9"];

    expect(ByteSafeJson::decode(ByteSafeJson::encode($value)))->toBe($value);
});

it('leaves a malformed escape untouched', function (): void {
    expect(ByteSafeJson::decode('["\u0000base64:!!"]'))->toBe(["\0base64:!!"]);
});

it('returns null for invalid JSON', function (): void {
    expect(ByteSafeJson::decode('{'))->toBeNull();
});

it('throws with the reason when a value cannot be encoded', function (): void {
    ByteSafeJson::encode(['time' => INF]);
})->throws(JsonException::class, 'Inf and NaN cannot be JSON encoded');

it('carries a string that is not valid UTF-8 through a parallel global', function (): void {
    Parallel::setGlobal('BYTE_SAFE_JSON_TEST', ByteSafeJson::encode("feature/caf\xe9"));

    try {
        expect(ByteSafeJson::decode((string) Parallel::getGlobal('BYTE_SAFE_JSON_TEST')))->toBe("feature/caf\xe9");
    } finally {
        unset($_ENV['PEST_PARALLEL_GLOBAL_BYTE_SAFE_JSON_TEST']);
    }
});
