<?php

declare(strict_types=1);

namespace Pest\Actions;

use Illuminate\Support\Str;
use Pest\Console\Coverage;
use Pest\TestSuite;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * @internal
 */
final class AddsCoverage
{
    /**
     * Holds the coverage related options.
     *
     * @var array<int, string>
     */
    private const OPTIONS = ['coverage', 'min'];

    /**
     * If any, adds the coverage params to the given original arguments.
     *
     * @param array<int, string> $originals
     *
     * @return array<int, string>
     */
    public static function from(TestSuite $testSuite, array $originals): array
    {
        $arguments = array_merge([''], array_values(array_filter($originals, function ($original): bool {
            foreach (self::OPTIONS as $option) {
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
        $inputs[] = new InputOption('coverage', null, InputOption::VALUE_NONE);
        $inputs[] = new InputOption('min', null, InputOption::VALUE_REQUIRED);

        $input = new ArgvInput($arguments, new InputDefinition($inputs));
        if ((bool) $input->getOption('coverage')) {
            $testSuite->coverage = true;
            $originals[]         = '--coverage-php';
            $originals[]         = Coverage::getPath();
        }

        if ($input->getOption('min') !== null) {
            /* @phpstan-ignore-next-line */
            $testSuite->coverageMin = (float) $input->getOption('min');
        }

        return $originals;
    }
}
