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
     * Whether should show the coverage or not.
     *
     * @var bool
     */
    public $coverage = false;

    /**
     * The minimum coverage.
     *
     * @var float
     */
    public $coverageMin = 0.0;

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function handleArguments(array $originals): array
    {
        $arguments = array_merge([''], array_values(array_filter($originals, function ($original): bool {
            foreach ([self::COVERAGE_OPTION, self::MIN_OPTION] as $option) {
                if ($original === sprintf('--%s', $option) || Str::startsWith($original, sprintf('--%s=', $option))) {
                    return true;
                }
            }

            return false;
        })));

        $originals = array_flip($originals);
        foreach ($arguments as $argument) {
            unset($originals[$argument]);
        }
        $originals = array_flip($originals);

        $inputs   = [];
        $inputs[] = new InputOption(self::COVERAGE_OPTION, null, InputOption::VALUE_NONE);
        $inputs[] = new InputOption(self::MIN_OPTION, null, InputOption::VALUE_REQUIRED);

        $input = new ArgvInput($arguments, new InputDefinition($inputs));
        if ((bool) $input->getOption(self::COVERAGE_OPTION)) {
            $this->coverage      = true;
            $originals[]         = '--coverage-php';
            $originals[]         = \Pest\Support\Coverage::getPath();
        }

        if ($input->getOption(self::MIN_OPTION) !== null) {
            $this->coverageMin = (float) $input->getOption(self::MIN_OPTION);
        }

        return $originals;
    }

    /**
     * Allows to add custom output after the test suite was executed.
     */
    public function addOutput(int $result): int
    {
        if ($result === 0 && $this->coverage) {
            if (!\Pest\Support\Coverage::isAvailable()) {
                $this->output->writeln(
                    "\n  <fg=white;bg=red;options=bold> ERROR </> No code coverage driver is available.</>",
                );
                exit(1);
            }

            $coverage = \Pest\Support\Coverage::report($this->output);

            $result = (int) ($coverage < $this->coverageMin);

            if ($result === 1) {
                $this->output->writeln(sprintf(
                    "\n  <fg=white;bg=red;options=bold> FAIL </> Code coverage below expected:<fg=red;options=bold> %s %%</>. Minimum:<fg=white;options=bold> %s %%</>.",
                    number_format($coverage, 1),
                    number_format($this->coverageMin, 1)
                ));
            }
        }

        return $result;
    }
}
