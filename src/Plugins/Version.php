<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;
use Symfony\Component\Console\Output\OutputInterface;

use function Pest\version;

/**
 * @internal
 */
final class Version implements HandlesArguments
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * Creates a new instance of the plugin.
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function handleArguments(array $arguments): array
    {
        if (in_array('--version', $arguments, true)) {
            $this->output->writeln(
                sprintf('Pest    %s', version()),
            );
        }

        return $arguments;
    }
}
