<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;

use function Pest\version;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Version implements HandlesArguments
{
    use Concerns\HandleArguments;

    /**
     * Creates a new Plugin instance.
     */
    public function __construct(
        private OutputInterface $output
    ) {
        // ..
    }

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        if ($this->hasArgument('--version', $arguments)) {
            $this->output->writeln(
                sprintf('Pest    %s', version()),
            );
        }

        return $arguments;
    }
}
