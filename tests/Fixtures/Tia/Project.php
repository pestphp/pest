<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tia;

use FilesystemIterator;
use Pest\Factories\TestCaseFactory;
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
 * A throwaway Pest project the TIA scenario tests drive.
 *
 * Two facts about Pest shape everything here:
 *
 * 1. `bin/pest` derives the project root from **the autoloader it finds**, not
 *    from the working directory — `dirname($autoloadPath, 2)`. So the project
 *    owns a real `vendor/autoload.php` and a real copy of `bin/pest` at the path
 *    a composer install would have put them. A symlinked `vendor` would resolve
 *    `__DIR__` straight back to the Pest repository, and every scenario would
 *    silently measure the wrong project.
 * 2. TIA cannot *record* without pcov or Xdebug, and CI has neither. So a
 *    scenario never records: {@see self::seed()} writes the graph a recording
 *    run would have written, and the run under test exercises the read path.
 *
 * @internal
 */
final class Project
{
    /**
     * Test file → the source files a recording run would have linked it to. The
     * self-edge every test file gets is added on top of these.
     *
     * @var array<string, array<int, string>>
     */
    public const array EDGES = [
        'tests/Unit/CalculatorTest.php' => ['app/Calculator.php'],
        'tests/Unit/GreeterTest.php' => ['app/Greeter.php'],
        'tests/Feature/CoversCalculatorTest.php' => ['app/Calculator.php'],
    ];

    /**
     * Test file → the descriptions it declares, in declaration order.
     *
     * @var array<string, array<int, string>>
     */
    public const array TESTS = [
        'tests/Unit/CalculatorTest.php' => ['adds two numbers', 'subtracts two numbers'],
        'tests/Unit/GreeterTest.php' => ['greets a person', 'greets the world'],
        'tests/Feature/CoversCalculatorTest.php' => ['adds within a feature test', 'subtracts within a feature test'],
    ];

    /**
     * Every test in the fixture suite.
     */
    public const int TOTAL_TESTS = 6;

    /**
     * Every project scaffolded so far, so a row cannot leak one by failing
     * before its own cleanup.
     *
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

    /**
     * The root whose graph this project reads and writes.
     *
     * Its own, except where a row seeds a worktree: a worktree's `.git` is a
     * file rather than a directory, so {@see Storage} cannot read the remote
     * from it and resolves a storage key of its own.
     */
    private string $graphRoot;

    private function __construct(public readonly string $path)
    {
        $this->graphRoot = $path;
    }

    /**
     * Scaffolds a project whose default branch is `$branch`, and hands it back
     * checked out there.
     *
     * The repository is realistic on purpose: it has an `origin`, and an
     * `origin/HEAD` naming `$branch`, which is what a checkout of a real project
     * looks like and what the default branch is autodetected from.
     */
    public static function make(string $branch = 'master', ?string $overlay = null): self
    {
        $project = self::scaffold($overlay);

        $project->git()->init($branch);
        $project->git()->addOrigin();
        $project->git()->setOriginHead($branch);

        return $project;
    }

    /**
     * Destroys every project scaffolded so far. Belongs in an `afterEach`.
     */
    public static function destroyAll(): void
    {
        while (self::$created !== []) {
            array_pop(self::$created)->destroy();
        }
    }

    /**
     * A project that is not a git repository at all — for the rows that assert
     * TIA still demands git, and that a plain run does not care.
     *
     * Lives in the system temp directory, so it is outside any enclosing
     * repository, and owns a real `vendor` rather than a symlinked one, so its
     * baseline key cannot collide with another fixture's.
     */
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

    /**
     * Overwrites a file in the project.
     *
     * Edits must be semantic: TIA hashes PHP at the AST level, so a
     * comment-only change is not a change at all.
     */
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
     * Adds a worktree for a new branch, scaffolded so `pest` can run in it, and
     * returns its path.
     *
     * The graph is shared with the main checkout, which is the whole point: both
     * resolve the same storage key, because {@see Storage} prefers the `origin`
     * identity over the path.
     */
    public function worktree(string $branch): string
    {
        $path = $this->path.'-worktree-'.preg_replace('/[^a-z0-9]+/i', '-', $branch);

        $this->git()->worktree($path, $branch);
        $this->scaffoldVendor($path);
        $this->extraPaths[] = $path;

        return $path;
    }

    /**
     * Runs `pest` in the project and returns what happened.
     */
    public function pest(string ...$arguments): PestResult
    {
        return $this->pestIn($this->path, ...$arguments);
    }

    /**
     * Runs `pest` in `$directory` — a worktree, say — against this project's
     * graph.
     */
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
                'HOME' => $this->home(),
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
     * Writes the graph a clean, green recording run on `$branch` would have
     * written, and remembers it as the snapshot the next {@see self::delta()}
     * compares against.
     *
     * Sentinelled by default: a row almost always wants to know which entries
     * were written, and only a real recording run's values can answer that.
     *
     * `$failing` names descriptions to record as failures — a clean green run
     * can never cache one, so a row that needs a cached failure to re-run has to
     * be handed it.
     *
     * @param  array<int, string>  $failing
     */
    public function seed(string $branch, bool $sentinel = true, array $failing = []): void
    {
        $this->seedFor($this->path, $branch, $sentinel, $failing);
    }

    /**
     * Seeds the graph belonging to `$root` — a worktree, say, which resolves a
     * storage key of its own.
     *
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

        // Hashes the tree as it stands, so the run under test sees nothing as
        // changed — the same call the recording path makes.
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
     * The id PHPUnit reports for a test in the fixture suite.
     *
     * Mirrors how Pest names generated test classes
     * ({@see TestCaseFactory}): a wrong id here shows up as
     * `0 replayed`, which every scenario asserts against.
     */
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

    /**
     * Falsifies every cached value, so the next {@see self::delta()} can tell
     * "wrote the same values back" from "wrote nothing".
     *
     * `assertions` is only ever falsified where it is already non-zero: risky,
     * skipped and incomplete statuses are *derived* from "performed no
     * assertions", so patching those would rewrite the status on replay and
     * destroy the very discriminator this exists to provide.
     */
    public function sentinel(): void
    {
        $graph = $this->graph();

        if ($graph === null) {
            throw new RuntimeException('There is no graph to sentinel.');
        }

        foreach ($graph['baselines'] ?? [] as $branch => $baseline) {
            foreach (array_keys($baseline['results'] ?? []) as $testId) {
                $graph['baselines'][$branch]['results'][$testId]['time'] = 9.999;

                if ((int) ($baseline['results'][$testId]['assertions'] ?? 0) > 0) {
                    $graph['baselines'][$branch]['results'][$testId]['assertions'] = 42;
                }
            }
        }

        $this->state()->write(Tia::KEY_GRAPH, (string) json_encode($graph, JSON_UNESCAPED_SLASHES));

        $this->snapshot();
    }

    /**
     * The decoded graph, or `null` when there is none.
     *
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

        return is_array($baselines) ? array_keys($baselines) : [];
    }

    public function graphDir(): string
    {
        return $this->withHome(fn (): string => Storage::tempDir($this->graphRoot));
    }

    public function graphExists(): bool
    {
        return is_file($this->graphDir().DIRECTORY_SEPARATOR.Tia::KEY_GRAPH);
    }

    /**
     * Remembers the graph as it stands now.
     */
    public function snapshot(): void
    {
        $this->snapshot = $this->graph();
    }

    /**
     * What has happened to the graph since the last snapshot.
     */
    public function delta(): GraphDelta
    {
        return new GraphDelta($this->snapshot, $this->graph());
    }

    /**
     * Writes the `vendor` a composer install would have produced.
     *
     * Pest is mirrored in at `vendor/pestphp/pest` rather than pointed at,
     * because Pest locates things from where its own files sit:
     * `bin/pest` finds the project root by walking up from the autoloader it
     * loads, and a parallel run picks its worker binary — and with it the
     * worker's project root — from the directory the runner class was loaded
     * from. Deferring to the repository's copy would resolve both back to the
     * Pest repository, and every scenario would quietly measure that instead.
     *
     * Hardlinked where the filesystem allows it, so the mirror costs almost
     * nothing and can never drift from the working tree.
     */
    public function scaffoldVendor(string $directory): void
    {
        $pestRoot = dirname(__DIR__, 3);
        $pest = $directory.'/vendor/pestphp/pest';

        // `overrides`, `resources` and `stubs` come along because Pest loads
        // them relative to `src` — the same list `BootExcludeList` walks.
        foreach (['src', 'overrides', 'resources', 'stubs'] as $tree) {
            self::mirror($pestRoot.'/'.$tree, $pest.'/'.$tree);
        }

        foreach (['pest', 'worker.php'] as $binary) {
            self::mirror($pestRoot.'/bin/'.$binary, $pest.'/bin/'.$binary);
        }

        self::mirror($pestRoot.'/composer.json', $pest.'/composer.json');

        // Pest's own autoloader, with the mirrored copy taking precedence: the
        // repository's `vendor` supplies PHPUnit, Symfony and the plugin
        // packages, none of which care where they are loaded from.
        file_put_contents($directory.'/vendor/autoload.php', sprintf(
            "<?php\n\n\$loader = require %s;\n\$loader->addPsr4('Pest\\\\', __DIR__.'/pestphp/pest/src', true);\n\nreturn \$loader;\n",
            var_export($pestRoot.'/vendor/autoload.php', true),
        ));

        // Invoking the binary directly skips composer's bin proxy, which is
        // what would otherwise define `$GLOBALS['_composer_bin_dir']`. Without
        // it `Pest\Plugin\Loader` looks for `vendor/bin/../pest-plugins.json`
        // relative to the working directory — so that is where the plugin list
        // goes, and mirroring the repository's keeps it in step with
        // composer.json. `vendor/bin` has to exist for the `..` in that path to
        // resolve, empty though it is.
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
     * `Storage` reads `HOME` from the environment, so the graph lands inside the
     * throwaway project instead of the developer's real `~/.pest`.
     *
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

        // Realpathed because Pest realpaths its own project root, and macOS
        // hands out a symlinked temp directory.
        $real = realpath($path);

        $project = new self($real === false ? $path : $real);

        self::$created[] = $project;

        self::copy(__DIR__.'/app', $project->path);

        if ($overlay !== null) {
            self::copy(__DIR__.'/overlays/'.$overlay, $project->path);
        }

        // `ChangedFiles` asks git what changed, so anything the scaffold writes
        // but the project does not own has to be invisible to it.
        $project->write('.gitignore', implode("\n", ['/vendor/', '/.home/', '/.phpunit.cache/', '']));

        $project->scaffoldVendor($project->path);
        @mkdir($project->home(), 0755, true);

        return $project;
    }

    /**
     * Mirrors a file or directory, hardlinking where the filesystem allows it
     * and copying where it does not.
     */
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
