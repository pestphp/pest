<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Support\Container;

/**
 * @internal
 */
final class Configuration
{
    /**
     * @return $this
     */
    public function directory(string $directory): self
    {
        Storage::useDirectory($directory);

        return $this;
    }

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
    public function baselined(?string $workflow = null): self
    {
        /** @var WatchPatterns $watchPatterns */
        $watchPatterns = Container::getInstance()->get(WatchPatterns::class);
        $watchPatterns->markBaselined();

        if ($workflow !== null) {
            $watchPatterns->setBaselineWorkflow($workflow);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function defaultBranch(string $branch): self
    {
        /** @var WatchPatterns $watchPatterns */
        $watchPatterns = Container::getInstance()->get(WatchPatterns::class);
        $watchPatterns->setDefaultBranch($branch);

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
