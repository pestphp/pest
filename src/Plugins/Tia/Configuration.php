<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Closure;
use Pest\Support\Container;

/**
 * @internal
 */
final class Configuration
{
    /**
     * @return $this
     */
    public function always(): self
    {
        /** @var WatchPatterns $watchPatterns */
        $watchPatterns = Container::getInstance()->get(WatchPatterns::class);
        $watchPatterns->markEnabled();

        return $this;
    }

    /**
     * @return $this
     */
    public function locally(): self
    {
        /** @var WatchPatterns $watchPatterns */
        $watchPatterns = Container::getInstance()->get(WatchPatterns::class);
        $watchPatterns->markEnabled();
        $watchPatterns->markLocally();

        return $this;
    }

    /**
     * @return $this
     */
    public function filtered(): self
    {
        /** @var WatchPatterns $watchPatterns */
        $watchPatterns = Container::getInstance()->get(WatchPatterns::class);
        $watchPatterns->markFiltered();

        return $this;
    }

    /**
     * @return $this
     */
    public function baselined(): self
    {
        /** @var WatchPatterns $watchPatterns */
        $watchPatterns = Container::getInstance()->get(WatchPatterns::class);
        $watchPatterns->markBaselined();

        return $this;
    }

    /**
     * Register a warm-up callback whose executed lines are treated as
     * framework bootstrap noise: it runs once per process under the coverage
     * driver before the first test, and every line it executes is excluded
     * from each test's recorded dependency edges.
     *
     * Intended for frameworks that boot inside every test (e.g. Laravel):
     *
     * ```php
     * pest()->tia()->warmupUsing(function (): void {
     *     $app = require __DIR__.'/../bootstrap/app.php';
     *     $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
     *
     *     // Undo global state the boot mutated (container, facades,
     *     // environment variables, error handlers) before returning.
     * });
     * ```
     *
     * @return $this
     */
    public function warmupUsing(Closure $callback): self
    {
        /** @var Recorder $recorder */
        $recorder = Container::getInstance()->get(Recorder::class);
        $recorder->warmupUsing($callback);

        return $this;
    }

    /**
     * @param  array<string, string>  $patterns  glob → project-relative test dir
     * @return $this
     */
    public function watch(array $patterns): self
    {
        /** @var WatchPatterns $watchPatterns */
        $watchPatterns = Container::getInstance()->get(WatchPatterns::class);
        $watchPatterns->add($patterns);

        return $this;
    }
}
