<?php

declare(strict_types=1);

use Pest\Support\Coverage;
use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('a coverage report does not found a dependency graph', function (array $arguments): void {
    $project = Project::make('master');

    $project->pest('--tia', ...$arguments);

    expect($project->graphExists())->toBeFalse();
})->with([
    'pest coverage' => [['--coverage']],
    'phpunit coverage report' => [['--coverage-text']],
    'parallel' => [['--coverage', '--parallel', '--processes=2']],
])->skipOnWindows();

test('a plain run after a coverage run records the whole project scope', function (): void {
    $project = Project::make('master');

    $project->pest('--tia', '--coverage');
    $project->pest('--tia');

    $graph = $project->graph();

    if ($graph === null) {
        expect($project->graphExists())->toBeFalse();

        return;
    }

    expect(array_keys($graph['edges']))->toEqualCanonicalizing(array_keys(Project::EDGES))
        ->and($graph['files'])->toContain('tests/Unit/CalculatorTest.php')
        ->and($graph['files'])->toContain('app/Calculator.php');
})->skipOnWindows();

test('xml-configured coverage does not prevent tia recording', function (): void {
    $project = Project::make('master');

    $project->write('phpunit.xml', <<<'XML_WRAP'
    <?xml version="1.0" encoding="UTF-8"?>
    <phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
             xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.0/phpunit.xsd"
             bootstrap="vendor/autoload.php"
             cacheDirectory=".phpunit.cache"
             colors="true"
             failOnRisky="true"
             failOnWarning="false"
    >
      <testsuites>
        <testsuite name="default">
          <directory suffix="Test.php">./tests</directory>
        </testsuite>
      </testsuites>
      <coverage>
        <report>
          <clover outputFile="coverage/clover.xml" />
        </report>
      </coverage>
      <source>
        <include>
          <directory suffix=".php">./app</directory>
        </include>
      </source>
    </phpunit>
    XML_WRAP);

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($project->graphExists())->toBeTrue()
        ->and(array_keys($project->graph()['edges']))->toEqualCanonicalizing(array_keys(Project::EDGES));
})->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('a coverage report leaves the edges of an existing graph alone', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->pest('--tia', '--coverage');
    $delta = $project->delta();

    expect($delta->edgesMoved())->toBeFalse($delta->summary())
        ->and($delta->filesMoved())->toBeFalse($delta->summary())
        ->and($delta->removed())->toBe(0, $delta->summary())
        ->and($delta->added())->toBe(0, $delta->summary());
})->skipOnWindows();
