<?php

declare(strict_types=1);

use Pest\TestsTia\Support\Sandbox;

/*
 * Mutating a source file should narrow replay to the tests that depend
 * on it. Untouched areas of the suite keep cache-hitting.
 */

test('editing a source file marks only its dependents as affected', function () {
    tiaScenario(function (Sandbox $sandbox) {
        $sandbox->pest(['--tia']);

        $sandbox->write('src/Math.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

final class Math
{
    public static function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public static function sub(int $a, int $b): int
    {
        return $a - $b;
    }
}
PHP);

        $process = $sandbox->pest(['--tia']);

        expect($process->isSuccessful())->toBeTrue(tiaOutput($process));
        expect(tiaOutput($process))->toMatch('/2 affected,\s*1 replayed/');
    });
});

test('adding a new test file runs the new test + replays the rest', function () {
    tiaScenario(function (Sandbox $sandbox) {
        $sandbox->pest(['--tia']);

        $sandbox->write('tests/ExtraTest.php', <<<'PHP'
<?php

declare(strict_types=1);

test('extra smoke', function () {
    expect(true)->toBeTrue();
});
PHP);

        $process = $sandbox->pest(['--tia']);

        expect($process->isSuccessful())->toBeTrue(tiaOutput($process));
        expect(tiaOutput($process))->toMatch('/1 affected,\s*3 replayed/');
    });
});
