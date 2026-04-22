<?php

declare(strict_types=1);

namespace Pest\TestsTia\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Throw-away sandbox for a TIA end-to-end scenario.
 *
 * On first call in a test run, a shared "template" sandbox is created
 * under the system temp dir and composer-installed against the host
 * Pest source. Subsequent `::create()` calls clone the template — cheap
 * (rcopy + git init) vs. running composer install per test.
 *
 * Each test owns its own clone; no cross-test state.
 *
 * Set `PEST_TIA_KEEP=1` to skip teardown so a failing scenario can be
 * reproduced manually — the path is emitted to STDERR.
 *
 * @internal
 */
final class Sandbox
{
    private static ?string $templatePath = null;

    private function __construct(private readonly string $path) {}

    /**
     * Eagerly provision the shared template. Call once from the harness
     * bootstrap so parallel workers don't race on first `create()`.
     */
    public static function warmTemplate(): void
    {
        self::ensureTemplate();
    }

    public static function create(): self
    {
        $template = self::ensureTemplate();

        $path = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'pest-tia-sandbox-'
            .bin2hex(random_bytes(4));

        self::rcopy($template, $path);
        self::bootstrapGit($path);

        return new self($path);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function write(string $relative, string $content): void
    {
        $absolute = $this->path.DIRECTORY_SEPARATOR.$relative;
        $dir = dirname($absolute);

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Cannot create {$dir}");
        }

        if (@file_put_contents($absolute, $content) === false) {
            throw new RuntimeException("Cannot write {$absolute}");
        }
    }

    public function delete(string $relative): void
    {
        $absolute = $this->path.DIRECTORY_SEPARATOR.$relative;

        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    /**
     * @param  array<int, string>  $flags
     */
    public function pest(array $flags = []): Process
    {
        // Invoke Pest's bin script through PHP directly rather than the
        // `vendor/bin/pest` symlink — `rcopy()` loses the `+x` bit when
        // cloning the template. Going through `php` bypasses the exec
        // check. Use `PHP_BINARY` (not a bare `php`) so the sandbox
        // executes under the same interpreter that launched the outer
        // test suite — otherwise macOS multi-version setups (Herd, brew,
        // asdf, …) fall back to the first `php` on `$PATH`, which often
        // lacks the coverage driver TIA's record mode needs.
        $process = new Process(
            [PHP_BINARY, 'vendor/pestphp/pest/bin/pest', ...$flags],
            $this->path,
            [
                // Strip any CI signal so TIA doesn't suppress instructions.
                'GITHUB_ACTIONS' => '',
                'GITLAB_CI' => '',
                'CIRCLECI' => '',
                // Force TIA's Storage to fall back to the sandbox-local
                // `.pest/tia/` layout. Without this, every sandbox run
                // would dump state into the developer's real home dir
                // (`~/.pest/tia/`), polluting it and making tests
                // non-hermetic.
                'HOME' => '',
                'USERPROFILE' => '',
            ],
        );
        $process->setTimeout(120.0);
        $process->run();

        return $process;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function graph(): ?array
    {
        $path = $this->path.'/.pest/tia/graph.json';

        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function hasGraph(): bool
    {
        return $this->graph() !== null;
    }

    /**
     * @param  array<int, string>  $args
     */
    public function git(array $args): Process
    {
        $process = new Process(['git', ...$args], $this->path);
        $process->setTimeout(30.0);
        $process->run();

        return $process;
    }

    public function destroy(): void
    {
        if (getenv('PEST_TIA_KEEP') === '1') {
            fwrite(STDERR, "[PEST_TIA_KEEP] sandbox: {$this->path}\n");

            return;
        }

        if (is_dir($this->path)) {
            self::rrmdir($this->path);
        }
    }

    /**
     * Lazily provisions a once-per-process template with composer already
     * installed against the host Pest source. Every sandbox clone copies
     * from here, avoiding a ~30s composer install per test.
     */
    private static function ensureTemplate(): string
    {
        if (self::$templatePath !== null && is_dir(self::$templatePath.'/vendor')) {
            return self::$templatePath;
        }

        // Cache key includes a fingerprint of the host Pest source tree —
        // when we edit Pest internals, the key changes, old templates
        // become orphaned, the new template rebuilds. Without this, a
        // stale template with yesterday's Pest code silently masks today's
        // code under test.
        $template = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'pest-tia-template-'
            .self::hostFingerprint();

        // Serialise template creation across parallel paratest workers.
        // Without the lock, three workers hitting `ensureTemplate()`
        // simultaneously each see "no vendor yet → rebuild", stomp on
        // each other's composer install, and produce half-written
        // fixtures. `flock` on a sibling lockfile keeps it to one
        // builder; the others block, then observe the finished
        // template and skip straight to the fast path.
        $lockPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-tia-template.lock';
        $lock = fopen($lockPath, 'c');

        if ($lock === false) {
            throw new RuntimeException('Cannot open template lock at '.$lockPath);
        }

        flock($lock, LOCK_EX);

        try {
            // Re-check after acquiring the lock — another worker may have
            // just finished the build while we were waiting.
            if (is_dir($template.'/vendor')) {
                self::$templatePath = $template;

                return $template;
            }

            // Garbage-collect every older template keyed by a different
            // fingerprint so /tmp doesn't accumulate a 200 MB graveyard
            // over a month of edits.
            foreach (glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-tia-template-*') ?: [] as $orphan) {
                if ($orphan !== $template) {
                    self::rrmdir($orphan);
                }
            }

            if (is_dir($template)) {
                self::rrmdir($template);
            }

            $fixture = __DIR__.'/../Fixtures/sample-project';

            if (! is_dir($fixture)) {
                throw new RuntimeException('Missing fixture at '.$fixture);
            }

            if (! @mkdir($template, 0755, true) && ! is_dir($template)) {
                throw new RuntimeException('Cannot create template at '.$template);
            }

            self::rcopy($fixture, $template);
            self::wireHostPest($template);
            self::composerInstall($template);

            self::$templatePath = $template;

            return $template;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function wireHostPest(string $path): void
    {
        $hostRoot = realpath(__DIR__.'/../..');

        if ($hostRoot === false) {
            throw new RuntimeException('Cannot resolve host Pest root');
        }

        $composerJson = $path.'/composer.json';
        $decoded = json_decode((string) file_get_contents($composerJson), true);

        $decoded['repositories'] = [
            ['type' => 'path', 'url' => $hostRoot, 'options' => ['symlink' => false]],
        ];
        $decoded['require']['pestphp/pest'] = '*@dev';

        file_put_contents(
            $composerJson,
            json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );
    }

    private static function composerInstall(string $path): void
    {
        // Invoke composer via the *same* PHP binary that's running this
        // process. On macOS multi-version setups (Herd, brew, asdf, etc.)
        // the `composer` shebang often points at the system PHP, which
        // may not match the version the test suite booted with — leading
        // to "your PHP version does not satisfy the requirement" errors
        // even when the interpreter in use would satisfy it. Going
        // through `PHP_BINARY` + the located composer binary/phar
        // sidesteps that entirely.
        $composer = self::locateComposer();
        $args = $composer === null
            ? ['composer', 'install']
            : [PHP_BINARY, $composer, 'install'];

        $process = new Process(
            [...$args, '--no-interaction', '--prefer-dist', '--no-progress', '--quiet'],
            $path,
        );
        $process->setTimeout(600.0);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                "composer install failed in template:\n".$process->getOutput().$process->getErrorOutput(),
            );
        }
    }

    /**
     * Resolves the composer binary to a real path PHP can execute. Returns
     * `null` when composer isn't findable, in which case the caller falls
     * back to invoking plain `composer` via `$PATH` (and hopes for the
     * best — usually fine on CI Linux runners).
     */
    private static function locateComposer(): ?string
    {
        $probe = new Process(['command', '-v', 'composer']);
        $probe->run();

        $path = trim($probe->getOutput());

        if ($path === '' || ! is_file($path)) {
            return null;
        }

        // `composer` may be a shell-script wrapper (Herd does this) —
        // resolve the actual phar it invokes. Heuristic: parse out the
        // last `.phar` argument from the wrapper, fall back to the file
        // itself if no wrapper is detected.
        $content = @file_get_contents($path);

        if ($content !== false && preg_match('/\S+\.phar/', $content, $m) === 1) {
            $phar = $m[0];

            if (is_file($phar)) {
                return $phar;
            }
        }

        return $path;
    }

    private static function bootstrapGit(string $path): void
    {
        // Each clone needs its own repo — TIA's SHA / branch / diff logic
        // all rely on `.git/`. The template has no git dir so clones start
        // from a clean slate.
        $run = function (array $args) use ($path): void {
            $process = new Process(['git', ...$args], $path);
            $process->setTimeout(30.0);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('git '.implode(' ', $args).' failed: '.$process->getErrorOutput());
            }
        };

        // `.git` may have been cloned from the template if we ever add one
        // there — nuke it just in case so every sandbox starts fresh.
        if (is_dir($path.'/.git')) {
            self::rrmdir($path.'/.git');
        }

        // Keep `vendor/` and composer lock out of the sandbox's git repo
        // entirely. With ~thousands of files `git add .` takes tens of
        // seconds; TIA also ignores vendor paths via `shouldIgnore()` so
        // tracking them buys nothing except slowness.
        file_put_contents($path.'/.gitignore', "vendor/\ncomposer.lock\n");

        $run(['init', '-q', '-b', 'main']);
        $run(['config', 'user.email', 'sandbox@pest.test']);
        $run(['config', 'user.name', 'Pest Sandbox']);
        $run(['config', 'commit.gpgsign', 'false']);
        $run(['add', '.']);
        $run(['commit', '-q', '-m', 'initial']);
    }

    /**
     * Short hash derived from the host Pest source that the template is
     * built against. Hashing the newest mtime across `src/`, `overrides/`,
     * and `composer.json` is cheap (one stat each) and catches every edit
     * that could alter TIA behaviour.
     */
    private static function hostFingerprint(): string
    {
        $hostRoot = realpath(__DIR__.'/../..');

        if ($hostRoot === false) {
            return 'unknown';
        }

        $newest = 0;

        foreach ([$hostRoot.'/src', $hostRoot.'/overrides'] as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iter as $file) {
                if ($file->isFile()) {
                    $newest = max($newest, $file->getMTime());
                }
            }
        }

        if (is_file($hostRoot.'/composer.json')) {
            $newest = max($newest, (int) filemtime($hostRoot.'/composer.json'));
        }

        return substr(sha1($hostRoot.'|'.PHP_VERSION.'|'.$newest), 0, 12);
    }

    private static function rcopy(string $src, string $dest): void
    {
        if (! is_dir($dest) && ! @mkdir($dest, 0755, true) && ! is_dir($dest)) {
            throw new RuntimeException("Cannot create {$dest}");
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iter as $item) {
            $target = $dest.DIRECTORY_SEPARATOR.$iter->getSubPathname();

            if ($item->isDir()) {
                @mkdir($target, 0755, true);
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private static function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        // `rm -rf` shells out but handles symlinks, read-only files, and
        // the composer-vendor quirks (lock files, .bin symlinks) that
        // PHP's own recursive delete stumbles on. Non-fatal on failure.
        $process = new Process(['rm', '-rf', $dir]);
        $process->setTimeout(60.0);
        $process->run();
    }
}
