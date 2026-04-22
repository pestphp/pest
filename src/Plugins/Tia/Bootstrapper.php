<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Contracts\Bootstrapper as BootstrapperContract;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Support\Container;
use Pest\TestSuite;

/**
 * Plugin-level container registrations for TIA. Runs as part of Kernel's
 * bootstrapper chain so Tia's own service graph is set up without Kernel
 * having to know about any of its internals.
 *
 * Most Tia services (`Recorder`, `CoverageCollector`, `WatchPatterns`,
 * `ResultCollector`, `BaselineSync`) are auto-buildable — Pest's container
 * resolves them lazily via constructor reflection. The only service that
 * requires an explicit binding is the `State` contract, because the
 * filesystem implementation needs a root-directory string that reflection
 * can't infer.
 *
 * @internal
 */
final readonly class Bootstrapper implements BootstrapperContract
{
    public function __construct(private Container $container) {}

    public function boot(): void
    {
        $this->container->add(State::class, new FileState($this->tempDir()));
    }

    /**
     * TIA's per-project state directory. Default layout is
     * `~/.pest/tia/<project-key>/` so the graph survives `composer
     * install`, stays out of the project tree, and is naturally shared
     * across worktrees of the same repo. See {@see Storage} for the key
     * derivation and the home-dir-missing fallback.
     */
    private function tempDir(): string
    {
        $testSuite = $this->container->get(TestSuite::class);
        assert($testSuite instanceof TestSuite);

        return Storage::tempDir($testSuite->rootPath);
    }
}
