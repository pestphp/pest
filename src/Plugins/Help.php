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
final readonly class Help implements HandlesArguments
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
        $helpReflection = new PHPUnitHelp;

        // @phpstan-ignore-next-line
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
            'arg' => '--notes',
            'desc' => 'Output to standard output tests with notes',
        ], [
        ], [
            'arg' => '--issue',
            'desc' => 'Output to standard output tests with the given issue number',
        ], [
        ], [
            'arg' => '--pr',
            'desc' => 'Output to standard output tests with the given pull request number',
        ], [
        ], [
            'arg' => '--pull-request',
            'desc' => 'Output to standard output tests with the given pull request number (alias for --pr)',
        ], [
            'arg' => '--retry',
            'desc' => 'Run non-passing tests first and stop execution upon first error or failure',
        ], [
            'arg' => '--dirty',
            'desc' => 'Only run tests that have uncommitted changes according to Git',
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

        $content['Mutation Testing'] = [[
            'arg' => '--mutate ',
            'desc' => 'Runs mutation testing, to understand the quality of your tests',
        ], [
            'arg' => '--mutate --parallel',
            'desc' => 'Runs mutation testing in parallel',
        ], [
            'arg' => '--mutate --min',
            'desc' => 'Set the minimum required mutation score, and fail if not met',
        ], [
            'arg' => '--mutate --id',
            'desc' => 'Run only the mutation with the given ID. But E.g. --id=ecb35ab30ffd3491. Note, you need to provide the same options as the original run',
        ], [
            'arg' => '--mutate --covered-only',
            'desc' => 'Only generate mutations for classes that are covered by tests',
        ], [
            'arg' => '--mutate --bail',
            'desc' => 'Stop mutation testing execution upon first untested or uncovered mutation',
        ], [
            'arg' => '--mutate --class',
            'desc' => 'Generate mutations for the given class(es). E.g. --class=App\\\\Models',
        ], [
            'arg' => '--mutate --ignore',
            'desc' => 'Ignore the given class(es) when generating mutations. E.g. --ignore=App\\\\Http\\\\Requests',
        ], [
            'arg' => '--mutate --clear-cache',
            'desc' => 'Clear the mutation cache',
        ], [
            'arg' => '--mutate --no-cache',
            'desc' => 'Clear the mutation cache',
        ], [
            'arg' => '--mutate --ignore-min-score-on-zero-mutations',
            'desc' => 'Ignore the minimum score requirement when there are no mutations',
        ], [
            'arg' => '--mutate --covered-only',
            'desc' => 'Only generate mutations for classes that are covered by tests',
        ], [
            'arg' => '--mutate --everything',
            'desc' => 'Generate mutations for all classes, even if they are not covered by tests',
        ], [
            'arg' => '--mutate --profile',
            'desc' => 'Output to standard output the top ten slowest mutations',
        ], [
            'arg' => '--mutate --retry',
            'desc' => 'Run untested or uncovered mutations first and stop execution upon first error or failure',
        ], [
            'arg' => '--mutate --stop-on-uncovered',
            'desc' => 'Stop mutation testing execution upon first untested mutation',
        ], [
            'arg' => '--mutate --stop-on-untested',
            'desc' => 'Stop mutation testing execution upon first untested mutation',
        ]];

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
