<?php

declare(strict_types=1);

use Pest\Plugins\Tia;
use Pest\Plugins\Tia\FileState;
use Pest\Plugins\Tia\Graph;
use Pest\Plugins\Tia\Recorder;
use Pest\Plugins\Tia\TableExtractor;
use Pest\Plugins\Tia\WatchPatterns;
use Pest\Support\Container;

it('exports numeric table dependencies from the recorder as strings', function (): void {
    $recorder = new Recorder;
    $recorder->activateLinkTracking();
    $recorder->beginTest('Some\Missing\TestClass', 'queries numeric tables', '/project/tests/Unit/NumericTest.php');

    foreach (['select substring(name from 1 for 3) from users', 'select * from "123"', 'select * from "0"', 'select * from "001"', 'select * from USERS join "123" on true'] as $query) {
        foreach (TableExtractor::fromSql($query) as $table) {
            $recorder->linkTable($table);
        }
    }

    $recorder->endTest();
    $tables = $recorder->perTestTables();
    sort($tables['/project/tests/Unit/NumericTest.php'], SORT_STRING);

    expect($tables)->toBe([
        '/project/tests/Unit/NumericTest.php' => ['0', '001', '1', '123', 'users'],
    ]);
});

it('persists numeric tables as JSON strings and selects only their migration readers after reload', function (): void {
    $root = tiaNumericTableProject();

    try {
        tiaAssertNumericTableGraph($root, [], [
            $root.'/tests/Unit/NumericTest.php' => ['123', '0', '001', '123'],
            $root.'/tests/Unit/ControlTest.php' => ['USERS', 'users'],
        ], [
            'tests/Unit/NumericTest.php' => ['0', '001', '123'],
            'tests/Unit/ControlTest.php' => ['users'],
        ], [
            ['123', ['tests/Unit/NumericTest.php']],
            ['0', ['tests/Unit/NumericTest.php']],
            ['001', ['tests/Unit/NumericTest.php']],
            ['users', ['tests/Unit/ControlTest.php']],
            ['orders', []],
        ]);
    } finally {
        tiaRemoveNumericTableProject($root);
    }
});

it('preserves numeric worker tables through payload cleaning union and graph reload', function (): void {
    $root = tiaNumericTableProject();

    try {
        $state = new FileState($root.'/state');
        $tia = new ReflectionClass(Tia::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(Tia::class, 'state')->setValue($tia, $state);
        $files = [
            $root.'/tests/Unit/NumericTest.php' => [$root.'/app/Subject.php'],
            $root.'/tests/Unit/ControlTest.php' => [$root.'/app/Subject.php'],
        ];
        $workers = [
            [
                $root.'/tests/Unit/NumericTest.php' => ['123', 'users'],
                $root.'/tests/Unit/ControlTest.php' => ['orders'],
            ],
            [$root.'/tests/Unit/NumericTest.php' => ['0', '001', '123', 'users']],
        ];

        foreach ($workers as $slot => $tables) {
            $before = new ReflectionMethod(Tia::class, 'collectWorkerEdgesPartials')->invoke($tia);
            new ReflectionMethod(Tia::class, 'flushWorkerPartial')->invoke($tia, $files, $tables, []);
            $keys = new ReflectionMethod(Tia::class, 'collectWorkerEdgesPartials')->invoke($tia);
            $written = array_values(array_diff($keys, $before));
            expect($written)->toHaveCount(1);
            $key = $written[0];
            $raw = $state->read($key);
            expect($raw)->toBeString();
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            expect($payload)->toBe(['files' => $files, 'tables' => $tables, 'inertia' => []])
                ->and(new ReflectionMethod(Tia::class, 'readPartial')->invoke($tia, $key))->toBe($payload)
                ->and(rename($state->pathFor($key), $state->pathFor('worker-edges-fixture-'.$slot.'.json')))->toBeTrue();
        }

        $keys = new ReflectionMethod(Tia::class, 'collectWorkerEdgesPartials')->invoke($tia);
        expect($keys)->toHaveCount(2);
        [$mergedFiles, $mergedTables, $inertia] = new ReflectionMethod(Tia::class, 'consumePartials')->invoke($tia, $keys);

        expect($mergedFiles)->toBe($files)
            ->and($mergedTables)->toBe([
                $root.'/tests/Unit/NumericTest.php' => ['123', 'users', '0', '001'],
                $root.'/tests/Unit/ControlTest.php' => ['orders'],
            ])
            ->and($inertia)->toBe([])
            ->and(new ReflectionMethod(Tia::class, 'collectWorkerEdgesPartials')->invoke($tia))->toBe([]);

        tiaAssertNumericTableGraph($root, $mergedFiles, $mergedTables, [
            'tests/Unit/NumericTest.php' => ['0', '001', '123', 'users'],
            'tests/Unit/ControlTest.php' => ['orders'],
        ], [
            ['123', ['tests/Unit/NumericTest.php']],
            ['0', ['tests/Unit/NumericTest.php']],
            ['001', ['tests/Unit/NumericTest.php']],
            ['users', ['tests/Unit/NumericTest.php']],
            ['orders', ['tests/Unit/ControlTest.php']],
        ]);
    } finally {
        tiaRemoveNumericTableProject($root);
    }
});

it('preserves observed and migration added numeric tables when augmenting database tests', function (): void {
    $root = tiaNumericTableProject();

    try {
        file_put_contents($root.'/database/migrations/create_numeric.php', "<?php Schema::create('456', function (): void {});");
        $tia = new ReflectionClass(Tia::class)->newInstanceWithoutConstructor();
        $tables = new ReflectionMethod(Tia::class, 'augmentDatabaseTestTables')->invoke($tia, [
            $root.'/tests/Unit/NumericTest.php' => ['123', '0', '001', 'users'],
            $root.'/tests/Unit/ControlTest.php' => ['orders'],
        ], [$root.'/tests/Unit/NumericTest.php' => true], $root);

        expect($tables)->toBe([
            $root.'/tests/Unit/NumericTest.php' => ['0', '001', '123', '456', 'users'],
            $root.'/tests/Unit/ControlTest.php' => ['orders'],
        ]);

        tiaAssertNumericTableGraph($root, [], $tables, [
            'tests/Unit/NumericTest.php' => ['0', '001', '123', '456', 'users'],
            'tests/Unit/ControlTest.php' => ['orders'],
        ], [
            ['123', ['tests/Unit/NumericTest.php']],
            ['0', ['tests/Unit/NumericTest.php']],
            ['001', ['tests/Unit/NumericTest.php']],
            ['456', ['tests/Unit/NumericTest.php']],
            ['users', ['tests/Unit/NumericTest.php']],
            ['orders', ['tests/Unit/ControlTest.php']],
        ]);
    } finally {
        tiaRemoveNumericTableProject($root);
    }
});

function tiaNumericTableProject(): string
{
    $root = dirname(__DIR__, 4).'/.temp/tia-numeric-'.bin2hex(random_bytes(8));
    mkdir($root.'/tests/Unit', 0755, true);
    mkdir($root.'/app', 0755, true);
    mkdir($root.'/database/migrations', 0755, true);
    file_put_contents($root.'/tests/Unit/NumericTest.php', '<?php');
    file_put_contents($root.'/tests/Unit/ControlTest.php', '<?php');
    file_put_contents($root.'/app/Subject.php', '<?php');

    return $root;
}

/**
 * @param  array<string, list<string>>  $files
 * @param  array<string, list<string>>  $tables
 * @param  array<string, list<string>>  $expected
 * @param  list<array{string, list<string>}>  $selections
 */
function tiaAssertNumericTableGraph(string $root, array $files, array $tables, array $expected, array $selections): void
{
    $graph = new Graph($root);
    $graph->replaceEdges($files);
    $graph->markKnownTestFiles(array_keys($tables));
    $graph->replaceTestTables($tables);
    $json = $graph->encode();
    expect($json)->toBeString();
    $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    expect($payload['test_tables'])->toBe($expected);
    $reloaded = Graph::decode($json, $root);
    expect($reloaded)->toBeInstanceOf(Graph::class);

    $container = Container::getInstance();
    $previous = $container->get(WatchPatterns::class);
    $patterns = new WatchPatterns;
    $container->add(WatchPatterns::class, $patterns);

    try {
        foreach ($selections as [$table, $readers]) {
            $migration = 'database/migrations/change_'.$table.'.php';
            file_put_contents($root.'/'.$migration, "<?php Schema::table('{$table}', function (): void {});");

            expect($reloaded->affected([$migration]))->toBe($readers);
        }
    } finally {
        $container->add(WatchPatterns::class, $previous);
    }
}

function tiaRemoveNumericTableProject(string $root): void
{
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($entries as $entry) {
        if ($entry->isDir()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }

    rmdir($root);
}
