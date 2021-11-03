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
    /** @var OutputInterface */
    private $output;

    private bool $enabled = false;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function handleArguments(array $arguments): array
    {
        foreach ($arguments as $index => $argument) {
            if ($argument === '--memory') {
                unset($arguments[$index]);

                $this->enabled = true;
            }
        }

        return array_values($arguments);
    }

    public function addOutput(int $result): int
    {
        if ($this->enabled) {
            $this->output->writeln(sprintf(
                '  <fg=white;options=bold>Memory: </><fg=default>%s MB</>',
                round(memory_get_usage(true) / pow(1000, 2), 3)
            ));
        }

        return $result;
    }
}
