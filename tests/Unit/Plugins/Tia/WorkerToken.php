<?php

use Pest\Plugins\Tia;

function workerToken(): string
{
    // workerToken() only reads the environment, so skip Tia's constructor
    // rather than wiring its dependencies.
    $tia = (new ReflectionClass(Tia::class))->newInstanceWithoutConstructor();

    return (new ReflectionMethod($tia, 'workerToken'))->invoke($tia);
}

beforeEach(function (): void {
    unset(
        $_SERVER['TEST_TOKEN'],
        $_ENV['TEST_TOKEN'],
        $_SERVER['UNIQUE_TEST_TOKEN'],
        $_ENV['UNIQUE_TEST_TOKEN'],
    );
});

test('prefers UNIQUE_TEST_TOKEN so recycled workers in the same slot do not collide', function (): void {
    // TEST_TOKEN is stable per worker slot; UNIQUE_TEST_TOKEN is unique per
    // worker process. Under --max-batch-size ParaTest recycles the process in
    // each slot, so keying partials by TEST_TOKEN clobbers earlier batches.
    $_SERVER['TEST_TOKEN'] = '2';

    $_SERVER['UNIQUE_TEST_TOKEN'] = '2_6a690d236824c';
    $processA = workerToken();

    $_SERVER['UNIQUE_TEST_TOKEN'] = '2_9f31be04117a';
    $processB = workerToken();

    expect($processA)->toBe('2_6a690d236824c')
        ->and($processB)->toBe('2_9f31be04117a')
        ->and($processA)->not->toBe($processB);
});

test('falls back to TEST_TOKEN when UNIQUE_TEST_TOKEN is absent', function (): void {
    $_SERVER['TEST_TOKEN'] = '3';

    expect(workerToken())->toBe('3');
});
