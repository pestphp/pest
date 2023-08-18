<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Support\Str;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Coverage implements AddsOutput, HandlesArguments
{
    /**
     * @var string
     */
    private const COVERAGE_OPTION = 'coverage';

    /**
     * @var string
     */
    private const MIN_OPTION = 'min';

    /**
     * @var string
     */
    private const ERRORS_ONLY_OPTION = 'errors-only';

    /**
     * @var string[]
     */
    private const ALLOWED_OPTIONS = [
        self::COVERAGE_OPTION,
        self::MIN_OPTION,
        self::ERRORS_ONLY_OPTION,
    ];

    /**
     * Whether it should show the coverage or not.
     */
    public bool $coverage = false;

    /**
     * The minimum coverage.
     */
    public float $coverageMin = 0.0;

    /**
     * Whether it should show only errors or not.
     */
    public bool $errorsOnly = false;

    /**
     * Creates a new Plugin instance.
     */
    public function __construct(private readonly OutputInterface $output)
    {
        // ..
    }

    /**
     * {@inheritdoc}
     */
    public function handleArguments(array $originals): array
    {
        $arguments = [...[''], ...array_values(array_filter($originals, function (string $original): bool {
            foreach (self::ALLOWED_OPTIONS as $option) {
                if ($original === sprintf('--%s', $option)) {
                    return true;
                }

                if (Str::startsWith($original, sprintf('--%s=', $option))) {
                    return true;
                }
            }

            return false;
        }))];

        $originals = array_flip($originals);
        foreach ($arguments as $argument) {
            unset($originals[$argument]);
        }
        $originals = array_flip($originals);

        $inputs = [];
        $inputs[] = new InputOption(self::COVERAGE_OPTION, null, InputOption::VALUE_NONE);
        $inputs[] = new InputOption(self::MIN_OPTION, null, InputOption::VALUE_REQUIRED);
        $inputs[] = new InputOption(self::ERRORS_ONLY_OPTION, null, InputOption::VALUE_NONE);

        $input = new ArgvInput($arguments, new InputDefinition($inputs));
        if ((bool) $input->getOption(self::COVERAGE_OPTION)) {
            $this->coverage = true;
            $originals[] = '--coverage-php';
            $originals[] = \Pest\Support\Coverage::getPath();

            if (! \Pest\Support\Coverage::isAvailable()) {
                if (\Pest\Support\Coverage::usingXdebug()) {
                    $this->output->writeln([
                        '',
                        "  <fg=default;bg=red;options=bold> ERROR </> Unable to get coverage using Xdebug. Did you set <href=https://xdebug.org/docs/code_coverage#mode>Xdebug's coverage mode</>?</>",
                        '',
                    ]);
                } else {
                    $this->output->writeln([
                        '',
                        '  <fg=default;bg=red;options=bold> ERROR </> No code coverage driver is available.</>',
                        '',
                    ]);
                }

                exit(1);
            }
        }

        if ($input->getOption(self::MIN_OPTION) !== null) {
            /** @var int|float $minOption */
            $minOption = $input->getOption(self::MIN_OPTION);

            $this->coverageMin = (float) $minOption;
        }

        if ((bool) $input->getOption(self::ERRORS_ONLY_OPTION)) {
            $this->errorsOnly = true;
        }

        return $originals;
    }

    /**
     * {@inheritdoc}
     */
    public function addOutput(int $exitCode): int
    {
        if ($exitCode === 0 && $this->coverage) {
            if (! \Pest\Support\Coverage::isAvailable()) {
                $this->output->writeln(
                    "\n  <fg=white;bg=red;options=bold> ERROR </> No code coverage driver is available.</>",
                );
                exit(1);
            }

            $coverage = \Pest\Support\Coverage::report($this->output, $this->coverageMin, $this->errorsOnly);

            $exitCode = (int) ($coverage < $this->coverageMin);

            if ($exitCode === 1) {
                $this->output->writeln(sprintf(
                    "\n  <fg=white;bg=red;options=bold> FAIL </> Code coverage below expected:<fg=red;options=bold> %s %%</>. Minimum:<fg=white;options=bold> %s %%</>.",
                    number_format($coverage, 1),
                    number_format($this->coverageMin, 1)
                ));
            }

            $this->output->writeln(['']);
        }

        return $exitCode;
    }
}
