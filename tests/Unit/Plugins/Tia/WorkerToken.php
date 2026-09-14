<?php

use Pest\Plugins\Tia;

function tiaWorkerToken(): string
{
    $tia = new ReflectionClass(Tia::class)->newInstanceWithoutConstructor();

    return new ReflectionMethod($tia, 'workerToken')->invoke($tia);
}

beforeEach(function (): void {
    unset(
        $_SERVER['TEST_TOKEN'],
        $_ENV['TEST_TOKEN'],
        $_SERVER['UNIQUE_TEST_TOKEN'],
        $_ENV['UNIQUE_TEST_TOKEN'],
    );
});

test('prefers the per-process UNIQUE_TEST_TOKEN over the per-slot TEST_TOKEN, so two workers recycled into the same slot get different tokens', function (): void {
    $_SERVER['TEST_TOKEN'] = '2';

    $_SERVER['UNIQUE_TEST_TOKEN'] = '2_6a690d236824c';
    $processA = tiaWorkerToken();

    $_SERVER['UNIQUE_TEST_TOKEN'] = '2_9f31be04117a';
    $processB = tiaWorkerToken();

    expect($processA)->toBe('2_6a690d236824c')
        ->and($processB)->toBe('2_9f31be04117a')
        ->and($processA)->not->toBe($processB);
});

test('falls back to TEST_TOKEN when UNIQUE_TEST_TOKEN is absent', function (): void {
    $_SERVER['TEST_TOKEN'] = '3';

    expect(tiaWorkerToken())->toBe('3');
});
