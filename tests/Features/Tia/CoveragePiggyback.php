<?php

declare(strict_types=1);

use Pest\Support\Coverage;
use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

function tiaGreeter(string $before, string $return = "return sprintf('Hello, %s!', \$name);"): string
{
    return <<<PHP_WRAP
    <?php

    declare(strict_types=1);

    namespace Fixture\App;

    final class Greeter
    {
        public function greet(string \$name): string
        {
            {$before}

            {$return}
        }
    }
    PHP_WRAP;
}

function tiaGreeterTestUsingCalculator(): string
{
    return <<<'PHP_WRAP'
    <?php

    declare(strict_types=1);

    use Fixture\App\Calculator;
    use Fixture\App\Greeter;

    test('greets a person', function (): void {
        expect((new Greeter)->greet('Nuno'))->toBe('Hello, Nuno!')
            ->and((new Calculator)->add(1, 1))->toBe(2);
    });

    test('greets the world', function (): void {
        expect((new Greeter)->greet('world'))->toBe('Hello, world!');
    });
    PHP_WRAP;
}

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

test('a coverage report adds a dependency a test gained since the graph was recorded', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('app/Greeter.php', <<<'PHP_WRAP'
    <?php

    declare(strict_types=1);

    namespace Fixture\App;

    final class Greeter
    {
        public function greet(string $name): string
        {
            (new Calculator)->add(1, 1);

            return sprintf('Hello, %s!', $name);
        }
    }
    PHP_WRAP);

    $result = $project->pest('--tia', '--coverage', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($project->edgesOf('tests/Unit/GreeterTest.php'))->toContain('app/Greeter.php', 'app/Calculator.php');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('a coverage report drops a dependency a test no longer has', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('app/Greeter.php', tiaGreeter('(new Calculator)->add(1, 1);'));

    $project->pest('--tia');
    $recorded = $project->edgesOf('tests/Unit/GreeterTest.php');

    $project->pest('--tia', '--coverage', ...$arguments);
    $project->write('app/Greeter.php', tiaGreeter('$name = trim($name);'));

    $result = $project->pest('--tia', '--coverage', ...$arguments);

    expect($recorded)->toContain('app/Calculator.php')
        ->and($result->exitCode)->toBe(0, $result->describe())
        ->and($project->edgesOf('tests/Unit/GreeterTest.php'))->toContain('app/Greeter.php')
        ->and($project->edgesOf('tests/Unit/GreeterTest.php'))->not->toContain('app/Calculator.php');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('a coverage report keeps a dependency outside the coverage source', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('support/exclaim.php', <<<'PHP_WRAP'
    <?php

    declare(strict_types=1);

    namespace Fixture\Support;

    function exclaim(string $text): string
    {
        return $text.'!';
    }
    PHP_WRAP);
    $project->write('app/Greeter.php', tiaGreeter('require_once __DIR__.\'/../support/exclaim.php\';', 'return \\Fixture\\Support\\exclaim(sprintf(\'Hello, %s\', $name));'));

    $project->pest('--tia');
    $recorded = $project->edgesOf('tests/Unit/GreeterTest.php');

    $project->write('app/Greeter.php', tiaGreeter('require_once __DIR__.\'/../support/exclaim.php\'; $name = trim($name);', 'return \\Fixture\\Support\\exclaim(sprintf(\'Hello, %s\', $name));'));

    $result = $project->pest('--tia', '--coverage', ...$arguments);

    expect($recorded)->toContain('support/exclaim.php')
        ->and($result->exitCode)->toBe(0, $result->describe())
        ->and($project->edgesOf('tests/Unit/GreeterTest.php'))->toContain('app/Greeter.php', 'support/exclaim.php');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('a coverage report drops a view a test no longer renders', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('resources/views/greeting.blade.php', 'Hello, {{ $name }}!');

    $greeterTest = (string) file_get_contents($project->path('tests/Unit/GreeterTest.php'));
    $project->write('tests/Unit/GreeterTest.php', $greeterTest.<<<'PHP_WRAP'

    test('renders the greeting', function (): void {
        Pest\Support\Container::getInstance()->get(Pest\Plugins\Tia\Recorder::class)
            ->linkSource(__DIR__.'/../../resources/views/greeting.blade.php');

        expect(true)->toBeTrue();
    });
    PHP_WRAP);

    $project->pest('--tia');
    $recorded = $project->edgesOf('tests/Unit/GreeterTest.php');

    $project->write('tests/Unit/GreeterTest.php', $greeterTest);

    $result = $project->pest('--tia', '--coverage', ...$arguments);

    expect($recorded)->toContain('resources/views/greeting.blade.php')
        ->and($result->exitCode)->toBe(0, $result->describe())
        ->and($project->edgesOf('tests/Unit/GreeterTest.php'))->toContain('app/Greeter.php')
        ->and($project->edgesOf('tests/Unit/GreeterTest.php'))->not->toContain('resources/views/greeting.blade.php');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('a filtered coverage run keeps the edges of the tests it did not run', function (): void {
    $project = Project::make('master');
    $project->write('tests/Unit/GreeterTest.php', tiaGreeterTestUsingCalculator());

    $project->pest('--tia');
    $project->write('app/Greeter.php', tiaGreeter('$name = trim($name);'));

    $result = $project->pest('--tia', '--coverage', '--filter=greets the world');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($project->edgesOf('tests/Unit/GreeterTest.php'))->toContain('app/Calculator.php');
})->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('a coverage report keeps the edges of a test file it replayed in part', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('tests/Unit/GreeterTest.php', tiaGreeterTestUsingCalculator());

    $project->pest('--tia');
    $project->pest('--tia', '--coverage', ...$arguments);

    $project->mutateGraph(function (array $graph): array {
        $graph['baselines']['master']['results'][Project::testId('tests/Unit/GreeterTest.php', 'greets the world')]['status'] = 7;

        return $graph;
    });
    $project->write('tests/Unit/CalculatorTest.php', str_replace('toBe(3)', 'toEqual(3)', (string) file_get_contents($project->path('tests/Unit/CalculatorTest.php'))));

    $result = $project->pest('--tia', '--coverage', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->affected())->toBeGreaterThan(0, $result->describe())
        ->and($result->replayed())->toBeGreaterThan(0, $result->describe())
        ->and($project->edgesOf('tests/Unit/GreeterTest.php'))->toContain('app/Greeter.php', 'app/Calculator.php');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('a coverage report keeps the edges its coverage did not record', function (string $test, array $arguments): void {
    $project = Project::make('master');
    $project->write('tests/Unit/GreeterTest.php', file_get_contents($project->path('tests/Unit/GreeterTest.php'))."\n".$test);

    $project->pest('--tia');
    $recorded = $project->edgesOf('tests/Unit/GreeterTest.php');

    $project->write('app/Greeter.php', tiaGreeter('$name = trim($name);'));

    $result = $project->pest('--tia', '--coverage', ...$arguments);

    expect($recorded)->toContain('app/Calculator.php')
        ->and($result->exitCode)->toBe(0, $result->describe())
        ->and($project->edgesOf('tests/Unit/GreeterTest.php'))->toContain('app/Greeter.php', 'app/Calculator.php');
})->with([
    'covers' => <<<'PHP_WRAP'
    covers(Greeter::class);

    test('adds', function (): void {
        expect((new Fixture\App\Calculator)->add(1, 1))->toBe(2);
    });
    PHP_WRAP,
    'skipped' => <<<'PHP_WRAP'
    test('adds', function (): void {
        (new Fixture\App\Calculator)->add(1, 1);

        $this->markTestSkipped('Not yet.');
    });
    PHP_WRAP,
])->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('a coverage report keeps a dependency on code its coverage ignores', function (array $arguments): void {
    $project = Project::make('master');
    $project->write('app/Calculator.php', str_replace(
        '    public function add(',
        "    /** @codeCoverageIgnore */\n    public function add(",
        (string) file_get_contents($project->path('app/Calculator.php')),
    ));
    $project->write('app/Greeter.php', tiaGreeter('(new Calculator)->add(1, 1);'));

    $project->pest('--tia');
    $recorded = $project->edgesOf('tests/Unit/GreeterTest.php');

    $project->write('app/Greeter.php', tiaGreeter('(new Calculator)->add(1, 2);'));

    $result = $project->pest('--tia', '--coverage', ...$arguments);

    expect($recorded)->toContain('app/Calculator.php')
        ->and($result->exitCode)->toBe(0, $result->describe())
        ->and($project->edgesOf('tests/Unit/GreeterTest.php'))->toContain('app/Greeter.php', 'app/Calculator.php');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');
