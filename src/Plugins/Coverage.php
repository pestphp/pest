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
    private const string COVERAGE_OPTION = 'coverage';

    private const string MIN_OPTION = 'min';

    private const string EXACTLY_OPTION = 'exactly';

    /**
     * Whether it should show the coverage or not.
     */
    public bool $coverage = false;

    /**
     * Whether it should show the coverage or not.
     */
    public bool $compact = false;

    /**
     * The minimum coverage.
     */
    public float $coverageMin = 0.0;

    /**
     * The exactly coverage.
     */
    public ?float $coverageExactly = null;

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
            foreach ([self::COVERAGE_OPTION, self::MIN_OPTION, self::EXACTLY_OPTION] as $option) {
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
        $inputs[] = new InputOption(self::EXACTLY_OPTION, null, InputOption::VALUE_REQUIRED);

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

        if ($input->getOption(self::EXACTLY_OPTION) !== null) {
            /** @var int|float $exactlyOption */
            $exactlyOption = $input->getOption(self::EXACTLY_OPTION);

            $this->coverageExactly = (float) $exactlyOption;
        }

        if ($_SERVER['COLLISION_PRINTER_COMPACT'] ?? false) {
            $this->compact = true;
        }

        return $originals;
    }

    /**
     * {@inheritdoc}
     */
    public function addOutput(int $exitCode): int
    {
        if (Parallel::isWorker()) {
            return $exitCode;
        }

        if ($exitCode === 0 && $this->coverage) {
            if (! \Pest\Support\Coverage::isAvailable()) {
                $this->output->writeln(
                    "\n  <fg=white;bg=red;options=bold> ERROR </> No code coverage driver is available.</>",
                );
                exit(1);
            }

            $coverage = \Pest\Support\Coverage::report($this->output, $this->compact);
            $exitCode = (int) ($coverage < $this->coverageMin);

            if ($exitCode === 0 && $this->coverageExactly !== null) {
                $comparableCoverage = $this->computeComparableCoverage($coverage);
                $comparableCoverageExactly = $this->computeComparableCoverage($this->coverageExactly);

                $exitCode = $comparableCoverage === $comparableCoverageExactly ? 0 : 1;

                if ($exitCode === 1) {
                    $this->output->writeln(sprintf(
                        "\n  <fg=white;bg=red;options=bold> FAIL </> Code coverage not exactly <fg=white;options=bold> %s %%</>, currently <fg=red;options=bold> %s %%</>.",
                        number_format($this->coverageExactly, 1),
                        number_format(floor($coverage * 10) / 10, 1),
                    ));
                }
            } elseif ($exitCode === 1) {
                $this->output->writeln(sprintf(
                    "\n  <fg=white;bg=red;options=bold> FAIL </> Code coverage below expected <fg=white;options=bold> %s %%</>, currently <fg=red;options=bold> %s %%</>.",
                    number_format($this->coverageMin, 1),
                    number_format(floor($coverage * 10) / 10, 1)
                ));
            }

            $this->output->writeln(['']);
        }

        return $exitCode;
    }

    /**
     * Computes the comparable coverage to a percentage with one decimal.
     */
    private function computeComparableCoverage(float $coverage): float
    {
        return floor($coverage * 10) / 10;
    }
}
