<?php

declare(strict_types=1);

use Pest\Plugins\Tia\Graph;

describe('markKnownTestFiles()', function (): void {
    it('makes a test file with no edges known', function (): void {
        $graph = new Graph(sys_get_temp_dir());

        expect($graph->knowsTest('tests/Unit/ExampleTest.php'))->toBeFalse();

        $graph->markKnownTestFiles(['tests/Unit/ExampleTest.php']);

        expect($graph->knowsTest('tests/Unit/ExampleTest.php'))->toBeTrue();
    });

    it('does not clobber edges of an already-known test file', function (): void {
        $graph = new Graph(sys_get_temp_dir());
        $graph->link('tests/Feature/UserTest.php', 'app/Models/User.php');

        $graph->markKnownTestFiles(['tests/Feature/UserTest.php']);

        // The pre-existing edge survives — the test still depends on the source file.
        $affected = $graph->affected(['app/Models/User.php']);

        expect($affected)->toContain('tests/Feature/UserTest.php');
    });

    it('ignores paths outside the project root', function (): void {
        $graph = new Graph(sys_get_temp_dir());

        $graph->markKnownTestFiles(['/somewhere/else/tests/FooTest.php']);

        expect($graph->knowsTest('/somewhere/else/tests/FooTest.php'))->toBeFalse();
    });
});
