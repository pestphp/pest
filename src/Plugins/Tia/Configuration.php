<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Support\Container;

/**
 * User-facing TIA configuration, returned by `pest()->tia()`.
 *
 * Usage in `tests/Pest.php`:
 *
 *     pest()->tia()->watch([
 *         'resources/js/**\/*.tsx' => 'tests/Browser',
 *         'public/build/**\/*'     => 'tests/Browser',
 *     ]);
 *
 * Patterns are merged with the built-in defaults (config, routes, views,
 * frontend assets, migrations). Duplicate glob keys overwrite the default
 * mapping so users can redirect a pattern to a narrower directory.
 *
 * @internal
 */
final class Configuration
{
    /**
     * Activates TIA for every run without requiring the `--tia` CLI flag.
     *
     * @return $this
     */
    public function always(): self
    {
        /** @var WatchPatterns $watchPatterns */
        $watchPatterns = Container::getInstance()->get(WatchPatterns::class);
        $watchPatterns->markAlways();

        return $this;
    }

    /**
     * Restricts the `always()` activation to local environments only.
     * On CI (`--ci` flag or `CI` env var), TIA is skipped even if `always()` is set.
     * Explicit `--tia` on the CLI always takes effect regardless.
     *
     * @return $this
     */
    public function locally(): self
    {
        /** @var WatchPatterns $watchPatterns */
        $watchPatterns = Container::getInstance()->get(WatchPatterns::class);
        $watchPatterns->markLocally();

        return $this;
    }

    /**
     * In replay mode, instead of short-circuiting cached results for unaffected
     * tests, narrows PHPUnit to only the affected files — unaffected tests are
     * never loaded. Can also be enabled with the `--filtered` CLI flag.
     *
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
     * Adds watch-pattern → test-directory mappings that supplement (or
     * override) the built-in defaults.
     *
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
