<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Memory implements AddsOutput, HandlesArguments
{
    use Concerns\HandleArguments;

    /**
     * If memory should be displayed.
     */
    private bool $enabled = false;

    /**
     * Creates a new Plugin instance.
     */
    public function __construct(
        private readonly OutputInterface $output
    ) {
        // ..
    }

    /**
     * {@inheritdoc}
     */
    public function handleArguments(array $arguments): array
    {
        $this->enabled = $this->hasArgument('--memory', $arguments);

        return $this->popArgument('--memory', $arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function addOutput(int $exitCode): int
    {
        if ($this->enabled) {
            $this->output->writeln(sprintf(
                '  <fg=gray>Memory:</>   <fg=default>%s MB</>',
                round(memory_get_usage(true) / 1000 ** 2, 3)
            ));
        }

        return $exitCode;
    }
}
