<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Support\Str;
use Pest\TestSuite;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @internal
 */
final class Coverage implements AddsOutput, HandlesArguments
{
    private const string COVERAGE_OPTION = 'coverage';

    private const string MIN_OPTION = 'min';

    private const string EXACTLY_OPTION = 'exactly';

    private const string ONLY_COVERED_OPTION = 'only-covered';

    private const string SHARDS_COVERAGE_OPTION = 'shards-coverage';

    private const string CLEAN_OPTION = 'clean';

    /**
     * PHPUnit coverage report flags that produce output and must be suppressed during sharded runs.
     *
     * @var array<string, string>
     */
    private const array SHARD_BLOCKED_REPORT_FLAGS = [
        '--coverage-html' => 'coverage-html',
        '--coverage-clover' => 'coverage-clover',
        '--coverage-text' => 'coverage-text',
        '--coverage-xml' => 'coverage-xml',
        '--coverage-cobertura' => 'coverage-cobertura',
        '--coverage-crap4j' => 'coverage-crap4j',
        '--coverage-openclover' => 'coverage-openclover',
    ];

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
     * Whether it should show only covered files.
     */
    public bool $showOnlyCovered = false;

    /**
     * The shard index when running in sharded coverage mode.
     */
    private ?int $shardIndex = null;

    /**
     * The total number of shards when running in sharded coverage mode.
     */
    private ?int $shardTotal = null;

    /**
     * Whether to merge shard .cov files and generate a coverage report.
     */
    private bool $shardsCoverage = false;

    /**
     * Whether to delete .cov files after generating the shards coverage report.
     */
    private bool $shardsCoverageClean = false;

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
        if ($this->hasShardsCoverageFlag($originals)) {
            $originals = $this->popShardsCoverageFlags($originals);
            $this->shardsCoverage = true;

            return $originals;
        }

        $arguments = [...[''], ...array_values(array_filter($originals, function (string $original): bool {
            foreach ([self::COVERAGE_OPTION, self::MIN_OPTION, self::EXACTLY_OPTION, self::ONLY_COVERED_OPTION] as $option) {
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
        $inputs[] = new InputOption(self::ONLY_COVERED_OPTION, null, InputOption::VALUE_NONE);

        $input = new ArgvInput($arguments, new InputDefinition($inputs));
        if ((bool) $input->getOption(self::COVERAGE_OPTION)) {
            $this->coverage = true;

            $shard = $this->detectShard($originals);

            if ($shard !== null) {
                [$this->shardIndex, $this->shardTotal] = $shard;

                $coverageDir = $this->getCoverageDir();
                if (! is_dir($coverageDir)) {
                    mkdir($coverageDir, 0755, true);
                }

                $originals = $this->stripShardBlockedReportFlags($originals);

                $originals[] = '--coverage-php';
                $originals[] = $coverageDir.DIRECTORY_SEPARATOR.$this->shardIndex.'.cov';
            } else {
                $originals[] = '--coverage-php';
                $originals[] = \Pest\Support\Coverage::getPath();
            }

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
     * {@inheritdoc}
     */
    public function addOutput(int $exitCode): int
    {
        if (Parallel::isWorker()) {
            return $exitCode;
        }

        if ($this->shardsCoverage) {
            $this->mergeAndReportShardsCoverage();

            return $exitCode;
        }

        if ($this->shardIndex !== null) {
            $this->output->writeln([
                '',
                sprintf(
                    '  <fg=gray>Coverage:</>  Coverage stored for shard %d/%d.',
                    $this->shardIndex,
                    $this->shardTotal,
                ),
                '  Run: <fg=cyan>pest --shards-coverage</>',
                '',
            ]);

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

    /**
     * Computes the comparable coverage to a percentage with one decimal.
     */
    private function computeComparableCoverage(float $coverage): float
    {
        return floor($coverage * 10) / 10;
    }

    /**
     * Detects --shard=X/Y in the arguments and returns [index, total], or null if not present.
     *
     * @param  array<int, string>  $arguments
     * @return array{int, int}|null
     */
    private function detectShard(array $arguments): ?array
    {
        foreach ($arguments as $i => $arg) {
            if (str_starts_with($arg, '--shard=')) {
                $value = substr($arg, strlen('--shard='));
            } elseif ($arg === '--shard' && isset($arguments[$i + 1])) {
                $value = $arguments[$i + 1];
            } else {
                continue;
            }

            if (preg_match('/^(\d+)\/(\d+)$/', $value, $m)) {
                return [(int) $m[1], (int) $m[2]];
            }
        }

        return null;
    }

    /**
     * Returns the path to the .pest/coverage directory.
     */
    private function getCoverageDir(): string
    {
        return implode(DIRECTORY_SEPARATOR, [
            TestSuite::getInstance()->rootPath,
            '.pest',
            'coverage',
        ]);
    }

    /**
     * Removes PHPUnit coverage report flags from the arguments during sharded runs,
     * and warns the user if any were found.
     *
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function stripShardBlockedReportFlags(array $arguments): array
    {
        $blockedFlags = self::SHARD_BLOCKED_REPORT_FLAGS;
        $firstHint = null;
        $skipNext = false;
        $filtered = [];

        foreach ($arguments as $arg) {
            if ($skipNext) {
                $skipNext = false;
                continue;
            }

            $matched = false;
            foreach ($blockedFlags as $flag => $hint) {
                if ($arg === $flag) {
                    $firstHint ??= $hint;
                    $skipNext = true;
                    $matched = true;
                    break;
                }
                if (str_starts_with($arg, $flag.'=')) {
                    $firstHint ??= $hint;
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                $filtered[] = $arg;
            }
        }

        if ($firstHint !== null) {
            $this->output->writeln([
                '',
                '  <fg=yellow;options=bold> WARN </> Coverage reports are disabled during sharded runs.',
                sprintf('  Run: <fg=cyan>pest --shards-coverage --%s</>', $firstHint),
                '',
            ]);
        }

        return $filtered;
    }

    /**
     * Detects whether --shards-coverage is present in the arguments.
     *
     * @param  array<int, string>  $arguments
     */
    private function hasShardsCoverageFlag(array $arguments): bool
    {
        foreach ($arguments as $arg) {
            if ($arg === '--'.self::SHARDS_COVERAGE_OPTION) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes --shards-coverage and --clean from the arguments and records --clean state.
     *
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function popShardsCoverageFlags(array $arguments): array
    {
        $filtered = [];
        foreach ($arguments as $arg) {
            if ($arg === '--'.self::SHARDS_COVERAGE_OPTION) {
                continue;
            }
            if ($arg === '--'.self::CLEAN_OPTION) {
                $this->shardsCoverageClean = true;
                continue;
            }
            $filtered[] = $arg;
        }

        return $filtered;
    }

    /**
     * Merges all shard .cov files and generates the requested coverage reports.
     */
    private function mergeAndReportShardsCoverage(): void
    {
        $coverageDir = $this->getCoverageDir();
        $files = glob($coverageDir.DIRECTORY_SEPARATOR.'*.cov');

        if ($files === false || $files === []) {
            $this->output->writeln([
                '',
                '  <fg=white;bg=red;options=bold> ERROR </> No coverage files found in .pest/coverage.',
                '  Run tests with --shard=X/Y --coverage first.',
                '',
            ]);

            return;
        }

        $count = count($files);
        $this->output->writeln([
            '',
            sprintf(
                '  <fg=gray>Merging coverage from %d shard%s...</>',
                $count,
                $count === 1 ? '' : 's',
            ),
        ]);

        $merged = null;
        foreach ($files as $file) {
            try {
                /** @var CodeCoverage $coverage */
                $coverage = require $file;
                if ($merged === null) {
                    $merged = $coverage;
                } else {
                    $merged->merge($coverage);
                }
            } catch (Throwable $e) {
                $this->output->writeln(sprintf(
                    '  <fg=yellow;options=bold> WARN </> Skipping invalid coverage file: %s (%s)',
                    basename($file),
                    $e->getMessage(),
                ));
            }
        }

        if ($merged === null) {
            $this->output->writeln([
                '',
                '  <fg=white;bg=red;options=bold> ERROR </> No valid coverage files could be loaded.',
                '',
            ]);

            return;
        }

        \Pest\Support\Coverage::render($merged, $this->output, $this->compact, $this->showOnlyCovered);

        if ($this->shardsCoverageClean) {
            foreach ($files as $file) {
                @unlink($file);
            }
            $this->output->writeln('  <fg=gray>Coverage files cleaned.</>');
        }

        $this->output->writeln('');
    }
}
