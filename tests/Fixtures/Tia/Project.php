<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tia;

use FilesystemIterator;
use Pest\Plugins\Tia;
use Pest\Plugins\Tia\ChangedFiles;
use Pest\Plugins\Tia\FileState;
use Pest\Plugins\Tia\Fingerprint;
use Pest\Plugins\Tia\Graph;
use Pest\Plugins\Tia\Storage;
use Pest\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
final class Project
{
    /**
     * @var array<string, array<int, string>>
     */
    public const array EDGES = [
        'tests/Unit/CalculatorTest.php' => ['app/Calculator.php'],
        'tests/Unit/GreeterTest.php' => ['app/Greeter.php'],
        'tests/Feature/CoversCalculatorTest.php' => ['app/Calculator.php'],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    public const array TESTS = [
        'tests/Unit/CalculatorTest.php' => ['adds two numbers', 'subtracts two numbers'],
        'tests/Unit/GreeterTest.php' => ['greets a person', 'greets the world'],
        'tests/Feature/CoversCalculatorTest.php' => ['adds within a feature test', 'subtracts within a feature test'],
    ];

    public const int TOTAL_TESTS = 6;

    /**
     * A dataset for the rule that TIA must reach the same outcome sequentially
     * and in parallel: the same command, run both ways, must leave the same graph.
     *
     * @var array<string, array<int, array<int, string>>>
     */
    public const array SEQUENTIAL_AND_PARALLEL = [
        'sequential' => [[]],
        'parallel' => [['--parallel', '--processes=2']],
    ];

    /**
     * @var array<int, self>
     */
    private static array $created = [];

    private ?GitRepo $repo = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $snapshot = null;

    /**
     * @var array<int, string>
     */
    private array $extraPaths = [];

    private string $graphRoot;

    private function __construct(public readonly string $path)
    {
        $this->graphRoot = $path;
    }

    public static function make(string $branch = 'master', ?string $overlay = null): self
    {
        $project = self::scaffold($overlay);

        $project->git()->init($branch);
        $project->git()->addOrigin();
        $project->git()->setOriginHead($branch);

        return $project;
    }

    public static function destroyAll(): void
    {
        while (self::$created !== []) {
            array_pop(self::$created)->destroy();
        }
    }

    public static function withoutGit(?string $overlay = null): self
    {
        return self::scaffold($overlay);
    }

    public function git(): GitRepo
    {
        return $this->repo ??= new GitRepo($this->path);
    }

    public function path(string $relative = ''): string
    {
        return $relative === '' ? $this->path : $this->path.DIRECTORY_SEPARATOR.$relative;
    }

    public function write(string $relative, string $contents): void
    {
        $path = $this->path($relative);
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create [%s].', $directory));
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Unable to write [%s].', $path));
        }
    }

    /**
     * A second copy of the fixture app in a subdirectory of this project, so a
     * run can be started from a root that sits *below* the git repository root.
     *
     * @return string The nested project's absolute path.
     */
    public function nested(string $directory = 'nested'): string
    {
        $path = $this->path($directory);

        if (! is_dir($path) && ! @mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create [%s].', $path));
        }

        self::copy(__DIR__.'/app', $path);
        $this->scaffoldVendor($path);

        return $path;
    }

    public function worktree(string $branch): string
    {
        $path = $this->path.'-worktree-'.preg_replace('/[^a-z0-9]+/i', '-', $branch);

        $this->git()->worktree($path, $branch);
        $this->scaffoldVendor($path);
        $this->extraPaths[] = $path;

        return $path;
    }

    public function pest(string ...$arguments): PestResult
    {
        return $this->pestIn($this->path, ...$arguments);
    }

    public function pestIn(string $directory, string ...$arguments): PestResult
    {
        return $this->pestWithEnvironment($directory, [], ...$arguments);
    }

    /**
     * @param  array<string, string>  $environment
     */
    public function pestWithEnvironment(string $directory, array $environment, string ...$arguments): PestResult
    {
        $process = new Process(
            [PHP_BINARY, $directory.'/vendor/pestphp/pest/bin/pest', ...$arguments],
            $directory,
            [
                ...GitRepo::ENV,
                'COLLISION_PRINTER' => 'DefaultPrinter',
                'COLLISION_IGNORE_DURATION' => 'true',
                'PARATEST' => '0',
                'PAO_DISABLE' => '1',
                // Recording is what needs a driver, and recording happens here,
                // in the subprocess — never in the process running these rows.
                // Asking for coverage mode only here lets a CI job leave xdebug
                // off for the suite it is running (whose collection under xdebug
                // costs more than every scenario put together) and still record.
                'XDEBUG_MODE' => 'coverage',
                'HOME' => $this->home(),
                'GITHUB_EVENT_PATH' => '',
                'CI_DEFAULT_BRANCH' => '',
                ...$environment,
            ],
        );

        $process->setTimeout(180.0);
        $process->run();

        return new PestResult(
            array_values($arguments),
            $process->getOutput().$process->getErrorOutput(),
            (int) $process->getExitCode(),
        );
    }

    /**
     * @param  array<int, string>  $failing
     */
    public function seed(string $branch, bool $sentinel = true, array $failing = []): void
    {
        $this->seedFor($this->path, $branch, $sentinel, $failing);
    }

    /**
     * @param  array<int, string>  $failing
     */
    public function seedFor(string $root, string $branch, bool $sentinel = true, array $failing = []): void
    {
        $this->graphRoot = $root;

        $changedFiles = new ChangedFiles($root);
        $sha = new GitRepo($root)->sha();

        $graph = new Graph($root);
        $graph->setFingerprint(Fingerprint::compute($root));
        $graph->setRecordedAtSha($branch, $sha);

        $graph->setLastRunTree($branch, $changedFiles->snapshotTree($changedFiles->since($sha) ?? []));

        $graph->markKnownTestFiles(array_keys(self::EDGES));

        foreach (self::EDGES as $testFile => $sourceFiles) {
            $graph->link($testFile, $testFile);

            foreach ($sourceFiles as $sourceFile) {
                $graph->link($testFile, $sourceFile);
            }
        }

        foreach (self::TESTS as $testFile => $descriptions) {
            foreach ($descriptions as $description) {
                $failed = in_array($description, $failing, true);

                $graph->setResult(
                    $branch,
                    self::testId($testFile, $description),
                    $failed ? 7 : 0,
                    $failed ? 'cached failure' : '',
                    0.05,
                    1,
                    $testFile,
                );
            }
        }

        $json = $graph->encode();

        if ($json === null) {
            throw new RuntimeException('Unable to encode the seeded graph.');
        }

        if (! $this->state()->write(Tia::KEY_GRAPH, $json)) {
            throw new RuntimeException('Unable to persist the seeded graph.');
        }

        $sentinel ? $this->sentinel() : $this->snapshot();
    }

    /**
     * Take the graph out of the state dir and hand back its JSON, so it can be
     * served as the artifact a remote baseline fetch downloads.
     */
    public function detachGraph(): string
    {
        $json = $this->state()->read(Tia::KEY_GRAPH);

        if ($json === null) {
            throw new RuntimeException('There is no graph to detach.');
        }

        if (! $this->state()->delete(Tia::KEY_GRAPH)) {
            throw new RuntimeException('Unable to remove the detached graph.');
        }

        $this->snapshot();

        return $json;
    }

    /**
     * Install a stand-in for the GitHub CLI and return the environment that
     * points a run at it. `$mode` names the failure it should serve (see
     * `stubs/gh`); `$payload` is the graph.json its artifact carries.
     *
     * @return array<string, string>
     */
    public function gh(string $mode = 'ok', string $payload = '{}'): array
    {
        self::mirror(__DIR__.'/stubs/gh', $this->path('stub/gh'));
        chmod($this->path('stub/gh'), 0755);

        $this->write('payload/graph.json', $payload);

        return [
            'PATH' => $this->path('stub').PATH_SEPARATOR.getenv('PATH'),
            'GH_STUB_MODE' => $mode,
            'GH_STUB_PAYLOAD' => $this->path('payload/graph.json'),
        ];
    }

    public static function testId(string $testFile, string $description): string
    {
        $basename = basename($testFile, '.php');
        $dotPosition = strpos($basename, '.');

        if ($dotPosition !== false) {
            $basename = substr($basename, 0, $dotPosition);
        }

        $relative = dirname(ucfirst($testFile)).DIRECTORY_SEPARATOR.$basename;

        return 'P\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative).'::'.Str::evaluable($description);
    }

    public function sentinel(): void
    {
        $this->mutateGraph(function (array $graph): array {
            foreach ($graph['baselines'] ?? [] as $branch => $baseline) {
                foreach (array_keys($baseline['results'] ?? []) as $testId) {
                    $graph['baselines'][$branch]['results'][$testId]['time'] = 9.999;

                    if ((int) ($baseline['results'][$testId]['assertions'] ?? 0) > 0) {
                        $graph['baselines'][$branch]['results'][$testId]['assertions'] = 42;
                    }
                }
            }

            return $graph;
        });
    }

    /**
     * Adds a second, empty baseline key, so a lone recorded baseline can no
     * longer stand in for the default branch.
     */
    public function addBaseline(string $branch): void
    {
        $this->mutateGraph(function (array $graph) use ($branch): array {
            $graph['baselines'][$branch] = ['sha' => null, 'tree' => [], 'results' => []];

            return $graph;
        });
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $callback
     */
    public function mutateGraph(callable $callback): void
    {
        $graph = $this->graph();

        if ($graph === null) {
            throw new RuntimeException('There is no graph to mutate.');
        }

        $this->state()->write(Tia::KEY_GRAPH, (string) json_encode($callback($graph), JSON_UNESCAPED_SLASHES));

        $this->snapshot();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function graph(): ?array
    {
        $json = $this->state()->read(Tia::KEY_GRAPH);

        if ($json === null) {
            return null;
        }

        $graph = json_decode($json, true);

        return is_array($graph) ? $graph : null;
    }

    /**
     * @return array<int, string>
     */
    public function branchKeys(): array
    {
        $baselines = $this->graph()['baselines'] ?? [];

        // A branch named `12345` comes back from json_decode as an integer key.
        return is_array($baselines) ? array_map(strval(...), array_keys($baselines)) : [];
    }

    public function graphDir(): string
    {
        return $this->withHome(fn (): string => Storage::tempDir($this->graphRoot));
    }

    public function graphExists(): bool
    {
        return is_file($this->graphDir().DIRECTORY_SEPARATOR.Tia::KEY_GRAPH);
    }

    public function snapshot(): void
    {
        $this->snapshot = $this->graph();
    }

    public function delta(): GraphDelta
    {
        return new GraphDelta($this->snapshot, $this->graph());
    }

    public function scaffoldVendor(string $directory): void
    {
        $pestRoot = dirname(__DIR__, 3);
        $pest = $directory.'/vendor/pestphp/pest';

        foreach (['src', 'overrides', 'resources', 'stubs'] as $tree) {
            self::mirror($pestRoot.'/'.$tree, $pest.'/'.$tree);
        }

        foreach (['pest', 'worker.php'] as $binary) {
            self::mirror($pestRoot.'/bin/'.$binary, $pest.'/bin/'.$binary);
        }

        self::mirror($pestRoot.'/composer.json', $pest.'/composer.json');

        file_put_contents($directory.'/vendor/autoload.php', sprintf(
            "<?php\n\n\$loader = require %s;\n\$loader->addPsr4('Pest\\\\', __DIR__.'/pestphp/pest/src', true);\n\nreturn \$loader;\n",
            var_export($pestRoot.'/vendor/autoload.php', true),
        ));

        if (! is_dir($directory.'/vendor/bin') && ! @mkdir($directory.'/vendor/bin', 0755, true)) {
            throw new RuntimeException(sprintf('Unable to create [%s].', $directory.'/vendor/bin'));
        }

        self::mirror($pestRoot.'/vendor/pest-plugins.json', $directory.'/vendor/pest-plugins.json');
    }

    public function destroy(): void
    {
        foreach ([...$this->extraPaths, $this->path] as $path) {
            $this->remove($path);
        }
    }

    private function home(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.'.home';
    }

    private function state(): FileState
    {
        return new FileState($this->graphDir());
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function withHome(callable $callback): mixed
    {
        $original = getenv('HOME');

        putenv('HOME='.$this->home());

        try {
            return $callback();
        } finally {
            putenv($original === false ? 'HOME' : 'HOME='.$original);
        }
    }

    private static function scaffold(?string $overlay): self
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-tia-'.bin2hex(random_bytes(8));

        if (! @mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create [%s].', $path));
        }

        $real = realpath($path);

        $project = new self($real === false ? $path : $real);

        self::$created[] = $project;

        self::copy(__DIR__.'/app', $project->path);

        if ($overlay !== null) {
            self::copy(__DIR__.'/overlays/'.$overlay, $project->path);
        }

        $project->write('.gitignore', implode("\n", ['/vendor/', '/.home/', '/.phpunit.cache/', '']));

        $project->scaffoldVendor($project->path);
        @mkdir($project->home(), 0755, true);

        return $project;
    }

    private static function mirror(string $from, string $to): void
    {
        if (is_dir($from)) {
            foreach (self::contentsOf($from) as $path) {
                self::mirror($path->getPathname(), $to.DIRECTORY_SEPARATOR.substr($path->getPathname(), strlen($from) + 1));
            }

            return;
        }

        $directory = dirname($to);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create [%s].', $directory));
        }

        if (@link($from, $to) || @copy($from, $to)) {
            return;
        }

        throw new RuntimeException(sprintf('Unable to mirror [%s] into [%s].', $from, $to));
    }

    /**
     * @return iterable<\SplFileInfo>
     */
    private static function contentsOf(string $directory): iterable
    {
        $paths = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($paths as $path) {
            if (! $path->isDir()) {
                yield $path;
            }
        }
    }

    private static function copy(string $from, string $to): void
    {
        $paths = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($paths as $path) {
            $target = $to.DIRECTORY_SEPARATOR.substr($path->getPathname(), strlen($from) + 1);

            if ($path->isDir()) {
                if (! is_dir($target) && ! @mkdir($target, 0755, true) && ! is_dir($target)) {
                    throw new RuntimeException(sprintf('Unable to create [%s].', $target));
                }

                continue;
            }

            if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0755, true) && ! is_dir(dirname($target))) {
                throw new RuntimeException(sprintf('Unable to create [%s].', dirname($target)));
            }

            if (! @copy($path->getPathname(), $target)) {
                throw new RuntimeException(sprintf('Unable to copy [%s].', $path->getPathname()));
            }
        }
    }

    private function remove(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $paths = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($paths as $entry) {
            $entry->isDir() && ! $entry->isLink() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }

        @rmdir($path);
    }
}
