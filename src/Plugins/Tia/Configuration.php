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
