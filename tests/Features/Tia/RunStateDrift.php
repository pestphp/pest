<?php

declare(strict_types=1);

use Pest\Plugins\Tia;
use Pest\Plugins\Tia\ChangedFiles;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Plugins\Tia\FileState;
use Pest\Support\Coverage;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\Tia\GitRepo;
use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('a source edit during replay cannot be stamped as tested', function (array $arguments, bool $dirtyBeforeRun): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/CalculatorTest.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Fixture\App\Calculator;

        test('adds two numbers', function (): void {
            $source = dirname(__DIR__, 2).'/app/Greeter.php';
            $contents = (string) file_get_contents($source);

            if (! str_contains($contents, 'function changedDuringTest')) {
                $contents = str_replace(
                    '    public function greet',
                    "    public function changedDuringTest(): int { return 42; }\n\n    public function greet",
                    $contents,
                );
                file_put_contents($source, $contents);
            }

            expect((new Calculator)->add(1, 2))->toBe(3);
        });

        test('subtracts two numbers', function (): void {
            expect((new Calculator)->subtract(3, 1))->toBe(2);
        });
        PHP);

    if ($dirtyBeforeRun) {
        $source = (string) file_get_contents($project->path('app/Greeter.php'));
        $project->write('app/Greeter.php', str_replace(
            '    public function greet',
            "    public function changedBeforeTest(): int { return 1; }\n\n    public function greet",
            $source,
        ));
    }

    $previous = $project->graph();
    $first = $project->pest('--tia', ...$arguments);

    expect($first->tally())->toContain('6 passed')
        ->and($first->exitCode)->toBe(1, $first->describe())
        ->and($first->output)->toContain('Project sources changed during the run')
        ->and($project->graph())->toEqual($previous);

    $second = $project->pest('--tia', ...$arguments);

    expect($second->exitCode)->toBe(0, $second->describe())
        ->and($second->tally())->toContain('6 passed')
        ->and($second->replayed())->toBeLessThan(Project::TOTAL_TESTS, $second->describe());
})->with([
    'sequential clean' => [[], false],
    'sequential dirty' => [[], true],
    'parallel clean' => [['--parallel', '--processes=2'], false],
    'parallel dirty' => [['--parallel', '--processes=2'], true],
])->skipOnWindows();

test('a PHPDoc-only edit cannot slip past the run integrity check', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/CalculatorTest.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Fixture\App\Calculator;

        test('adds two numbers', function (): void {
            $source = dirname(__DIR__, 2).'/app/Greeter.php';
            $contents = (string) file_get_contents($source);

            if (! str_contains($contents, '@Route("/changed")')) {
                file_put_contents($source, str_replace(
                    '    public function greet',
                    "    /** @Route(\"/changed\") */\n    public function greet",
                    $contents,
                ));
            }

            expect((new Calculator)->add(1, 2))->toBe(3);
        });

        test('subtracts two numbers', function (): void {
            expect((new Calculator)->subtract(3, 1))->toBe(2);
        });
        PHP);

    $previous = $project->graph();
    $first = $project->pest('--tia', ...$arguments);

    expect($first->tally())->toContain('6 passed')
        ->and($first->exitCode)->toBe(1, $first->describe())
        ->and($first->output)->toContain('Project sources changed during the run')
        ->and($project->graph())->toEqual($previous);

    $second = $project->pest('--tia', ...$arguments);

    expect($second->exitCode)->toBe(0, $second->describe())
        ->and($second->tally())->toContain('6 passed')
        ->and($second->replayed())->toBeLessThan(Project::TOTAL_TESTS, $second->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a PHPDoc-only change remains visible to TIA selection', function (): void {
    $project = Project::make('master');
    $changed = new ChangedFiles($project->path);
    $before = $changed->runSnapshot();

    $source = (string) file_get_contents($project->path('app/Greeter.php'));
    $project->write('app/Greeter.php', str_replace(
        '    public function greet',
        "    /** @Route(\"/changed\") */\n    public function greet",
        $source,
    ));

    $after = $changed->runSnapshot();

    expect($before)->not->toBeNull()
        ->and($after)->not->toBeNull();

    assert($before !== null && $after !== null);

    expect($after['sha'])->toBe($before['sha'])
        ->and($after['tree'])->toHaveKey('app/Greeter.php')
        ->and($after['raw'])->not->toBe($before['raw'])
        ->and($changed->since($before['sha']))->toContain('app/Greeter.php')
        ->and($changed->filterUnchangedSinceLastRun(['app/Greeter.php'], $after['tree']))->toBeEmpty();
})->skipOnWindows();

test('an unchanged PHPDoc edit made before the run is not mistaken for run drift', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $source = (string) file_get_contents($project->path('app/Greeter.php'));
    $project->write('app/Greeter.php', str_replace(
        '    public function greet',
        "    /** @Route(\"/before\") */\n    public function greet",
        $source,
    ));

    $result = $project->pest('--tia', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->not->toContain('Project sources changed during the run');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a working tree that was dirty before the run remains valid if it stays unchanged', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $source = (string) file_get_contents($project->path('app/Greeter.php'));
    $project->write('app/Greeter.php', str_replace(
        '    public function greet',
        "    public function alreadyDirty(): bool { return true; }\n\n    public function greet",
        $source,
    ));

    $result = $project->pest('--tia', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->not->toContain('Project sources changed during the run');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('fresh recording rejects a new source file created during execution', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/CalculatorTest.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Fixture\App\Calculator;

        test('adds two numbers', function (): void {
            file_put_contents(dirname(__DIR__, 2).'/app/GeneratedDuringRun.php', "<?php\n// created during test\n");
            expect((new Calculator)->add(1, 2))->toBe(3);
        });

        test('subtracts two numbers', function (): void {
            expect((new Calculator)->subtract(3, 1))->toBe(2);
        });
        PHP);

    $previous = $project->graph();
    $result = $project->pest('--tia', '--fresh', ...$arguments);
    $graphAfter = $project->graph();

    expect($result->tally())->toContain('6 passed')
        ->and($result->exitCode)->toBe(1, $result->describe())
        ->and($result->output)->toContain('Project sources changed during the run')
        ->and($graphAfter === null || $graphAfter === $previous)->toBeTrue();
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('cleanup of a transient PHPUnit configuration cannot invalidate TIA recording', function (): void {
    $project = Project::make('master');
    $project->write('.pest.xml', '<phpunit/>');

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain('6 passed')
        ->and($project->graphExists())->toBeTrue()
        ->and($result->output)->not->toContain('Project sources changed during the run')
        ->and($project->path('.pest.xml'))->not->toBeFile();
})->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');

test('a PHPDoc-only edit actually executes its covering test rather than replaying its pass', function (array $arguments): void {
    $project = Project::make('master');
    $original = (string) file_get_contents($project->path('tests/Unit/GreeterTest.php'));
    $project->write('tests/Unit/GreeterTest.php', str_replace(
        "expect((new Greeter)->greet('Nuno'))->toBe('Hello, Nuno!');",
        "file_put_contents(dirname(__DIR__, 2).'/.home/phpdoc-probe', 'executed');\n    expect((new Greeter)->greet('Nuno'))->toBe('Hello, Nuno!');",
        $original,
    ));
    $project->git()->commit('add a real execution probe');
    $project->seed('master');

    $source = (string) file_get_contents($project->path('app/Greeter.php'));
    $project->write('app/Greeter.php', str_replace(
        '    public function greet',
        "    /** @Route(\"/changed\") */\n    public function greet",
        $source,
    ));

    $result = $project->pest('--tia', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($project->path('.home/phpdoc-probe'))->toBeFile()
        ->and($result->replayed())->toBeLessThan(Project::TOTAL_TESTS, $result->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('an untracked JUnit report written during tests does not count as source drift', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/CalculatorTest.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Fixture\App\Calculator;

        test('adds two numbers', function (): void {
            file_put_contents(dirname(__DIR__, 2).'/junit-output.xml', '<testsuites/>');
            expect((new Calculator)->add(1, 2))->toBe(3);
        });
        PHP);

    $result = $project->pest('--tia', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->not->toContain('Project sources changed during the run')
        ->and($project->path('junit-output.xml'))->toBeFile();
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('an untracked runtime CSV created during replay invalidates the run', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/CalculatorTest.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Fixture\App\Calculator;

        test('adds two numbers', function (): void {
            file_put_contents(dirname(__DIR__, 2).'/data.csv', "id,value\n1,changed\n");
            expect((new Calculator)->add(1, 2))->toBe(3);
        });

        test('subtracts two numbers', function (): void {
            expect((new Calculator)->subtract(3, 1))->toBe(2);
        });
        PHP);

    $previous = $project->graph();
    $first = $project->pest('--tia', ...$arguments);

    expect($project->path('data.csv'))->toBeFile()
        ->and($first->exitCode)->toBe(1, $first->describe())
        ->and($first->output)->toContain('Project sources changed during the run')
        ->and($project->graph())->toEqual($previous);

    $second = $project->pest('--tia', ...$arguments);

    expect($second->exitCode)->toBe(0, $second->describe())
        ->and($second->tally())->toContain('6 passed');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('a later Terminable plugin cannot approve a tree it changes after TIA output', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('vendor/pestphp/pest/src/Plugins/LateSourceMutation.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace Pest\Plugins;

        use Pest\Contracts\Plugins\Terminable;
        use Pest\TestSuite;

        final class LateSourceMutation implements Terminable
        {
            public function terminate(): void
            {
                if (Parallel::isWorker() || getenv('PEST_LATE_SOURCE_MUTATION') !== '1') {
                    return;
                }

                $source = TestSuite::getInstance()->rootPath.'/app/Greeter.php';
                $contents = (string) file_get_contents($source);

                if (! str_contains($contents, 'function changedByLaterPlugin')) {
                    file_put_contents($source, str_replace(
                        '    public function greet',
                        "    public function changedByLaterPlugin(): int { return 9; }\n\n    public function greet",
                        $contents,
                    ));
                }
            }
        }
        PHP);

    $pluginsFile = $project->path('vendor/pest-plugins.json');
    $plugins = json_decode((string) file_get_contents($pluginsFile), true);
    expect($plugins)->toBeArray()->toContain(Tia::class);

    unlink($pluginsFile);
    $project->write('vendor/pest-plugins.json', (string) json_encode([...$plugins, 'Pest\\Plugins\\LateSourceMutation']));

    $previous = $project->graph();
    $result = $project->pestWithEnvironment($project->path(), ['PEST_LATE_SOURCE_MUTATION' => '1'], '--tia', ...$arguments);

    expect($result->tally())->toContain('6 passed')
        ->and($result->exitCode)->toBe(1, $result->describe())
        ->and($result->output)->toContain('Project sources changed during the run')
        ->and($project->graph())->toEqual($previous);

    $next = $project->pest('--tia', ...$arguments);
    expect($next->exitCode)->toBe(0, $next->describe())
        ->and($next->replayed())->toBeLessThan(Project::TOTAL_TESTS, $next->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('publication rolls back the graph if its state write mutates a source', function (): void {
    $project = Project::make('master');
    $state = new class($project->path('app/Greeter.php')) implements State
    {
        private array $values = [Tia::KEY_GRAPH => 'previous graph'];

        private bool $mutated = false;

        public function __construct(private readonly string $source) {}

        public function read(string $key): ?string
        {
            return $this->values[$key] ?? null;
        }

        public function write(string $key, string $content): bool
        {
            $this->values[$key] = $content;

            if ($key === Tia::KEY_GRAPH && ! $this->mutated) {
                $this->mutated = true;
                $contents = (string) file_get_contents($this->source);
                file_put_contents($this->source, str_replace('return sprintf(', 'return sprintf(', $contents)."\n// source modified while graph was written\n");
            }

            return true;
        }

        public function delete(string $key): bool
        {
            unset($this->values[$key]);

            return true;
        }

        public function exists(string $key): bool
        {
            return isset($this->values[$key]);
        }

        public function keysWithPrefix(string $prefix): array
        {
            return array_values(array_filter(array_keys($this->values), fn (string $key): bool => str_starts_with($key, $prefix)));
        }
    };

    $start = new ChangedFiles($project->path)->runSnapshot();
    expect($start)->not->toBeNull();

    $tia = new ReflectionClass(Tia::class)->newInstanceWithoutConstructor();
    new ReflectionProperty(Tia::class, 'state')->setValue($tia, $state);
    new ReflectionProperty(Tia::class, 'output')->setValue($tia, new BufferedOutput);
    new ReflectionProperty(Tia::class, 'snapshotRoot')->setValue($tia, $project->path);
    new ReflectionProperty(Tia::class, 'startRunSnapshot')->setValue($tia, $start);
    new ReflectionProperty(Tia::class, 'stageGraphWrites')->setValue($tia, true);
    new ReflectionProperty(Tia::class, 'stagedGraphJson')->setValue($tia, 'candidate graph');

    $level = ob_get_level();

    try {
        $exitCode = $tia->finalizeOutput(0);
    } finally {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
    }

    expect($exitCode)->toBe(1)
        ->and($state->read(Tia::KEY_GRAPH))->toBe('previous graph');
})->skipOnWindows();

test('a dirty Git submodule safely falls back to a full suite', function (): void {
    $project = Project::make('master');
    $external = sys_get_temp_dir().'/pest-tia-gitlink-'.bin2hex(random_bytes(8));
    mkdir($external, 0755, true);

    try {
        file_put_contents($external.'/Service.php', "<?php\nclass Service {}\n");
        $submodule = new GitRepo($external);
        $submodule->init('main');
        $project->git()->run(['-c', 'protocol.file.allow=always', 'submodule', 'add', $external, 'vendor-module']);
        $project->git()->commit('add a tracked gitlink');
        $project->seed('master');

        $project->write('vendor-module/Service.php', "<?php\nclass Service { public function newer(): bool { return true; } }\n");

        expect(new ChangedFiles($project->path)->runSnapshot())->toBeNull();

        $result = $project->pest('--tia');

        expect($result->exitCode)->toBe(0, $result->describe())
            ->and($result->output)->toContain('Unable to snapshot project sources')
            ->and($result->tally())->toContain('6 passed')
            ->and($result->replayed())->toBe(0, $result->describe());
    } finally {
        $paths = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($external, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($paths as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($external);
    }
})->skipOnWindows();

test('an uncompleted graph publication cannot be replayed as certified', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $state = new FileState($project->graphDir());
    expect($state->write(Tia::KEY_RUN_PENDING, '{"pending":true}'))->toBeTrue();

    $result = $project->pest('--tia');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('earlier TIA run was not certified')
        ->and($result->replayed())->toBe(0)
        ->and($state->exists(Tia::KEY_RUN_PENDING))->toBeFalse();
})->skipOnWindows()->skip(! Coverage::isAvailable(), 'Coverage is not available');
