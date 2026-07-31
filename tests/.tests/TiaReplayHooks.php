<?php

function tiaReplayHooksLog(string $hook): void
{
    $log = getenv('TIA_REPLAY_HOOKS_LOG');

    if (is_string($log) && $log !== '') {
        file_put_contents($log, $hook.PHP_EOL, FILE_APPEND);
    }
}

beforeEach(function (): void {
    tiaReplayHooksLog('beforeEach');
});

afterEach(function (): void {
    tiaReplayHooksLog('afterEach');

    throw new RuntimeException('The afterEach hook must not run for replayed tests.');
});

test('replayed pass', function (): void {
    expect(true)->toBeTrue();
});

test('replayed skip', function (): void {
    expect(true)->toBeTrue();
});

test('replayed incomplete', function (): void {
    expect(true)->toBeTrue();
});
