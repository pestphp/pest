<?php

declare(strict_types=1);

use Pest\Support\Coverage;
use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

function tiaRecordWithSourceReadingTest(Project $project, string ...$arguments): void
{
    $project->write('tests/Unit/GreeterSourceTest.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        test('keeps the greeting format', function (): void {
            expect(file_get_contents(__DIR__.'/../../app/Greeter.php'))->toContain("'Hello, %s!'");
        });
        PHP);
    $project->git()->commit('add a test that reads the greeter');

    $project->pest('--tia', ...$arguments);
}

/**
 * @return array<int, string>
 */
function tiaEdgeFiles(Project $project, string $testFile): array
{
    $graph = $project->graph() ?? [];

    return array_map(
        fn (int $id): string => $graph['files'][$id],
        $graph['edges'][$testFile] ?? [],
    );
}

test('a test that reads a file records an edge to it', function (array $arguments): void {
    $project = Project::make('master');

    tiaRecordWithSourceReadingTest($project, ...$arguments);

    $graph = $project->graph() ?? [];
    $files = array_map(
        fn (int $id): string => $graph['files'][$id],
        $graph['edges']['tests/Unit/GreeterSourceTest.php'] ?? [],
    );

    expect($files)->toContain('app/Greeter.php');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('a changed file selects the test that reads it', function (array $arguments): void {
    $project = Project::make('master');

    tiaRecordWithSourceReadingTest($project, ...$arguments);

    $project->write('app/Greeter.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace Fixture\App;

        final class Greeter
        {
            public function greet(string $name): string
            {
                return sprintf('Hello, %s!', $name);
            }

            public function shout(string $name): string
            {
                return strtoupper($this->greet($name));
            }
        }
        PHP);

    $result = $project->pest('--tia', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->affected())->toBe(3, $result->describe())
        ->and($result->replayed())->toBe(4, $result->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('a file a test reads does not select it when another file changes', function (): void {
    $project = Project::make('master');

    tiaRecordWithSourceReadingTest($project);

    $project->write('app/Calculator.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace Fixture\App;

        final class Calculator
        {
            public function add(int $a, int $b): int
            {
                return $a + $b;
            }

            public function subtract(int $a, int $b): int
            {
                return $a - $b;
            }

            public function multiply(int $a, int $b): int
            {
                return $a * $b;
            }
        }
        PHP);

    $result = $project->pest('--tia');

    expect($result->affected())->toBe(4, $result->describe())
        ->and($result->replayed())->toBe(3, $result->describe());
})->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('a test that reads an excluded file records no edge to it', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('tests/Pest.php', file_get_contents($project->path('tests/Pest.php')).
        "\npest()->tia()->withoutReadCapture(['app/Greeter.php']);\n");

    tiaRecordWithSourceReadingTest($project, ...$arguments);

    expect(tiaEdgeFiles($project, 'tests/Unit/GreeterTest.php'))->toContain('app/Greeter.php')
        ->and(tiaEdgeFiles($project, 'tests/Unit/GreeterSourceTest.php'))->not->toContain('app/Greeter.php');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('a test that reads a file outside the excluded globs still records an edge to it', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('tests/Pest.php', file_get_contents($project->path('tests/Pest.php')).
        "\npest()->tia()->withoutReadCapture(['lang/*.json']);\n");

    tiaRecordWithSourceReadingTest($project, ...$arguments);

    expect(tiaEdgeFiles($project, 'tests/Unit/GreeterSourceTest.php'))->toContain('app/Greeter.php');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('no read records an edge when read capture is off', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('tests/Pest.php', file_get_contents($project->path('tests/Pest.php')).
        "\npest()->tia()->withoutReadCapture();\n");

    tiaRecordWithSourceReadingTest($project, ...$arguments);

    expect(tiaEdgeFiles($project, 'tests/Unit/GreeterTest.php'))->toContain('app/Greeter.php')
        ->and(tiaEdgeFiles($project, 'tests/Unit/GreeterSourceTest.php'))->not->toContain('app/Greeter.php');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');
