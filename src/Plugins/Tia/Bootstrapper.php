<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Contracts\Bootstrapper as BootstrapperContract;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Support\Container;

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
     * TIA's own subdirectory under Pest's `.temp/`. Keeping every TIA blob
     * in a single folder (`.temp/tia/`) avoids the `tia-`-prefix salad
     * alongside PHPUnit's unrelated files (coverage.php, test-results,
     * code-coverage/) and makes the CI artifact-upload path a single
     * directory instead of a list of individual files.
     */
    private function tempDir(): string
    {
        return __DIR__
            .DIRECTORY_SEPARATOR.'..'
            .DIRECTORY_SEPARATOR.'..'
            .DIRECTORY_SEPARATOR.'..'
            .DIRECTORY_SEPARATOR.'.temp'
            .DIRECTORY_SEPARATOR.'tia';
    }
}
