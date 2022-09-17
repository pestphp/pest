<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Support\View;
use function Pest\version;
use PHPUnit\TextUI\Help as PHPUnitHelp;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Help implements HandlesArguments
{
    use Concerns\HandleArguments;

    /**
     * Creates a new Plugin instance.
     */
    public function __construct(
        private readonly OutputInterface $output
    ) {
        // ..
    }

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        if ($this->hasArgument('--help', $arguments)) {
            View::render('version', [
                'version' => version(),
            ]);

            View::render('usage');

            foreach ($this->getContent() as $title => $options) {
                if ($title === 'Usage') {
                    continue;
                }

                $this->output->writeln([
                    '',
                    sprintf('  <fg=blue;options=bold>%s:</>', mb_strtoupper($title)),
                ]);

                foreach ($options as $option) {
                    if (! array_key_exists('arg', $option)) {
                        continue;
                    }

                    [
                        'arg' => $argument,
                        'desc' => $description,
                    ] = $option;

                    View::render('components.two-column-detail', [
                        'left' => $argument,
                        'right' => $description,
                    ]);
                }
            }

            $this->output->write('', true);

            exit(0);
        }

        return $arguments;
    }

    /**
     * @return array<string, array<int, array{arg?: string, desc: string}>>
     */
    private function getContent(): array
    {
        // Access the PHPUnit help class's private const HELP
        $helpReflection = new \ReflectionClass(PHPUnitHelp::class);

        /** @var array<string, array<int, array{arg: string, desc: string}>> $content */
        $content = $helpReflection->getConstant('HELP_TEXT');

        $content['Configuration'] = [[
            'arg' => '--init',
            'desc' => 'Initialise a standard Pest configuration',
        ]] + $content['Configuration'];

        $content['Code Coverage'] = [
            [
                'arg' => '--coverage ',
                'desc' => 'Generate code coverage report and output to standard output',
            ],
            [
                'arg' => '--coverage --min',
                'desc' => 'Set the minimum required coverage percentage, and fail if not met',
            ],
        ] + $content['Code Coverage'];

        return $content;
    }
}
