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
    use Concerns\HandleArguments;

    private const string COVERAGE_OPTION = 'coverage';

    private const string MIN_OPTION = 'min';

    private const string EXACTLY_OPTION = 'exactly';

    private const string ONLY_COVERED_OPTION = 'only-covered';

    /**
     * @var array<string, bool>
     */
    private const array OPTIONS = [
        self::COVERAGE_OPTION => false,
        self::MIN_OPTION => true,
        self::EXACTLY_OPTION => true,
        self::ONLY_COVERED_OPTION => false,
    ];

    public bool $coverage = false;

    public bool $compact = false;

    public float $coverageMin = 0.0;

    public ?float $coverageExactly = null;

    public bool $showOnlyCovered = false;

    public function __construct(private readonly OutputInterface $output)
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function handleArguments(array $originals): array
    {
        [$arguments, $originals] = $this->extractOwnArguments($originals);

        $inputs = [];
        $inputs[] = new InputOption(self::COVERAGE_OPTION, null, InputOption::VALUE_NONE);
        $inputs[] = new InputOption(self::MIN_OPTION, null, InputOption::VALUE_REQUIRED);
        $inputs[] = new InputOption(self::EXACTLY_OPTION, null, InputOption::VALUE_REQUIRED);
        $inputs[] = new InputOption(self::ONLY_COVERED_OPTION, null, InputOption::VALUE_NONE);

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

        if ((bool) $input->getOption(self::ONLY_COVERED_OPTION)) {
            $this->showOnlyCovered = true;
        }

        if ($_SERVER['COLLISION_PRINTER_COMPACT'] ?? false) {
            $this->compact = true;
        }

        return $originals;
    }

    /**
     * @param  array<int, string>  $originals
     * @return array{0: list<string>, 1: list<string>}
     */
    private function extractOwnArguments(array $originals): array
    {
        $originals = array_values($originals);

        $owned = [];
        $forwarded = [];

        for ($index = 0, $total = count($originals); $index < $total; $index++) {
            $original = $originals[$index];

            foreach (self::OPTIONS as $option => $requiresValue) {
                if (Str::startsWith($original, sprintf('--%s=', $option))) {
                    $owned[] = $original;

                    continue 2;
                }

                if ($original !== sprintf('--%s', $option)) {
                    continue;
                }

                $owned[] = $original;

                $value = $originals[$index + 1] ?? null;

                if ($requiresValue && $value !== null && ! Str::startsWith($value, '-')) {
                    $owned[] = $value;
                    $index++;
                }

                continue 2;
            }

            $forwarded[] = $original;
        }

        return [['', ...$owned], $forwarded];
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

            $coverage = \Pest\Support\Coverage::report($this->output, $this->compact, $this->showOnlyCovered);
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

    private function computeComparableCoverage(float $coverage): float
    {
        return floor($coverage * 10) / 10;
    }
}
