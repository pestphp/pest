<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Thanks implements HandlesArguments
{
    private const THANKS_OPTION = '--thanks';

    /** @var array<int, string> */
    private const FUNDING_MESSAGES = [
        "\n  Want to support Pest? Here are some ways you can help:",
        "\n    - Star or contribute to Pest on GitHub:\n      <options=bold>https://github.com/pestphp/pest</>",
        "\n    - Tweet about Pest on Twitter:\n      <options=bold>https://twitter.com/pestphp</>",
        "\n    - Sponsor Nuno Maduro on GitHub:\n      <options=bold>https://github.com/sponsors/nunomaduro</>",
        "\n    - Sponsor Nuno Maduro on Patreon:\n      <options=bold>https://patreon.com/nunomaduro</>",
    ];

    /** @var OutputInterface */
    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function handleArguments(array $arguments): array
    {
        if (!in_array(self::THANKS_OPTION, $arguments, true)) {
            return $arguments;
        }

        foreach (self::FUNDING_MESSAGES as $message) {
            $this->output->writeln($message);
        }

        exit(0);
    }
}
