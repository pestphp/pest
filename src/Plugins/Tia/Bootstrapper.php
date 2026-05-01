<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Contracts\Bootstrapper as BootstrapperContract;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Support\Container;
use Pest\TestSuite;

/**
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
