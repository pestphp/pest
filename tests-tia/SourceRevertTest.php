<?php

declare(strict_types=1);

use Pest\TestsTia\Support\Sandbox;

/*
 * Edit a source file, run TIA (tests re-run), revert to the original
 * bytes, run again — the revert is itself a change vs the previous
 * snapshot, so the affected tests re-execute rather than replaying the
 * stale bad-version cache.
 */

test('reverting a modified file re-triggers its affected tests', function () {
    tiaScenario(function (Sandbox $sandbox) {
        $sandbox->pest(['--tia']);

        $original = (string) file_get_contents($sandbox->path().'/src/Math.php');

        $sandbox->write('src/Math.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

final class Math
{
    public static function add(int $a, int $b): int
    {
        return 999; // broken
    }
}
PHP);

        $broken = $sandbox->pest(['--tia']);
        expect($broken->isSuccessful())->toBeFalse();

        $sandbox->write('src/Math.php', $original);

        $recovered = $sandbox->pest(['--tia']);

        expect($recovered->isSuccessful())->toBeTrue(tiaOutput($recovered));
        expect(tiaOutput($recovered))->toMatch('/2 affected,\s*1 replayed/');
    });
});
