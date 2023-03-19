<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;

/**
 * @internal
 */
final class Bail implements HandlesArguments
{
    use HandleArguments;

    /**
     * Handles the arguments, adding the `--stop-on-defect` when the `--bail` argument is present.
     */
    public function handleArguments(array $arguments): array
    {
        if ($this->hasArgument('--bail', $arguments)) {
            $arguments = $this->popArgument('--bail', $arguments);

            $arguments = $this->pushArgument('--stop-on-failure', $arguments);
            $arguments = $this->pushArgument('--stop-on-error', $arguments);
        }

        return $arguments;
    }
}
