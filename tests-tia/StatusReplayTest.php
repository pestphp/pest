<?php

declare(strict_types=1);

use Pest\TestsTia\Support\Sandbox;

/*
 * Cached statuses + assertion counts should survive replay.
 */

test('assertion counts survive replay', function () {
    tiaScenario(function (Sandbox $sandbox) {
        $sandbox->pest(['--tia']);

        $process = $sandbox->pest(['--tia']);
        $output = tiaOutput($process);

        // MathTest has 2 assertions, GreeterTest has 1 → 3 total.
        // The "Tests: … (N assertions, … replayed)" banner should show 3.
        expect($output)->toMatch('/\(3 assertions/');
    });
});

test('breaking a test replays as a failure on the next run', function () {
    tiaScenario(function (Sandbox $sandbox) {
        // Prime.
        $sandbox->pest(['--tia']);

        // Break the test. Its test file's edge map still points at
        // `src/Math.php`; editing the test file counts as a change
        // and the test re-executes.
        $sandbox->write('tests/MathTest.php', <<<'PHP'
<?php

declare(strict_types=1);

use App\Math;

test('math add', function () {
    expect(Math::add(2, 3))->toBe(999); // wrong
});

test('math add negative', function () {
    expect(Math::add(-1, 1))->toBe(0);
});
PHP);

        $process = $sandbox->pest(['--tia']);

        expect($process->isSuccessful())->toBeFalse();
        expect(tiaOutput($process))->toContain('math add');
    });
});
