<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Support\View;
use PHPUnit\TextUI\Help as PHPUnitHelp;
use Symfony\Component\Console\Output\OutputInterface;

use function Pest\version;

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
                    sprintf('  <fg=yellow;options=bold>%s OPTIONS:</>', mb_strtoupper($title)),
                ]);

                foreach ($options as $option) {
                    if (! array_key_exists('arg', $option)) {
                        continue;
                    }

                    [
                        'arg' => $argument,
                        'desc' => $description,
                    ] = $option;

                    assert(is_string($argument));

                    if (trim($argument) === '--process-isolation') {
                        continue;
                    }

                    View::render('components.two-column-detail', [
                        'left' => $this->colorizeOptions($argument),
                        'right' => preg_replace(['/</', '/>/'], ['[', ']'], $description),
                    ]);
                }
            }

            $this->output->write('', true);

            exit(0);
        }

        return $arguments;
    }

    /**
     * Colorizes the given string options.
     */
    private function colorizeOptions(string $argument): string
    {
        return (string) preg_replace(
            ['/</', '/>/', '/(-+[\w-]+)/'],
            ['[', ']', '<fg=blue;options=bold>$1</>'],
            $argument
        );
    }

    /**
     * @return array<string, array<int, array<'arg'|'desc'|int, array{arg: string, desc: string}|string>>>
     */
    private function getContent(): array
    {
        $helpReflection = new PHPUnitHelp();

        $content = (fn (): array => $this->elements())->call($helpReflection);

        $content['Configuration'] = [...[[
            'arg' => '--init',
            'desc' => 'Initialise a standard Pest configuration',
        ]], ...$content['Configuration']];

        $content['Execution'] = [...[
            [
                'arg' => '--parallel',
                'desc' => 'Run tests in parallel',
            ],
            [
                'arg' => '--update-snapshots',
                'desc' => 'Update snapshots for tests using the "toMatchSnapshot" expectation',
            ],
        ], ...$content['Execution']];

        $content['Selection'] = [[
            'arg' => '--bail',
            'desc' => 'Stop execution upon first not-passed test',
        ], [
            'arg' => '--todos',
            'desc' => 'Output to standard output the list of todos',
        ], [
            'arg' => '--retry',
            'desc' => 'Run non-passing tests first and stop execution upon first error or failure',
        ], ...$content['Selection']];

        $content['Reporting'] = [...$content['Reporting'], ...[
            [
                'arg' => '--compact',
                'desc' => 'Replace default result output with Compact format',
            ],
        ]];

        $content['Code Coverage'] = [[
            'arg' => '--coverage ',
            'desc' => 'Generate code coverage report and output to standard output',
        ], [
            'arg' => '--coverage --min',
            'desc' => 'Set the minimum required coverage percentage, and fail if not met',
        ], ...$content['Code Coverage']];

        $content['Profiling'] = [
            [
                'arg' => '--profile ',
                'desc' => 'Output to standard output the top ten slowest tests',
            ],
        ];

        unset($content['Miscellaneous']);

        return $content;
    }
}
