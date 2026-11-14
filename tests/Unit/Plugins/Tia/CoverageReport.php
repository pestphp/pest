<?php

declare(strict_types=1);

use Pest\Plugins\Tia\CoverageMerger;

test('the merged coverage report round-trips binary coverage without var_export', function (): void {
    $value = [
        'quote' => "a'\\b",
        'nul' => "\0hidden",
        'slash' => '\\',
    ];
    $report = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-coverage-report-'.bin2hex(random_bytes(4)).'.php';

    try {
        $method = new ReflectionMethod(CoverageMerger::class, 'writeUnserializeReport');
        $method->invoke(null, $report, serialize($value));

        $loaded = require $report;

        expect($report)->toBeFile()
            ->and(file_get_contents($report))->not->toContain('var_export')
            ->and($loaded)->toBe($value);
    } finally {
        if (is_file($report)) {
            unlink($report);
        }
    }
});

test('the merged coverage report encodes a payload larger than one base64 chunk', function (): void {
    $value = random_bytes((3 * 64 * 1024) + 5);
    $report = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-coverage-report-'.bin2hex(random_bytes(4)).'.php';

    try {
        $method = new ReflectionMethod(CoverageMerger::class, 'writeUnserializeReport');
        $method->invoke(null, $report, serialize($value));

        $loaded = require $report;

        expect(hash('xxh128', $loaded))->toBe(hash('xxh128', $value));
    } finally {
        if (is_file($report)) {
            unlink($report);
        }
    }
});
