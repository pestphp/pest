<?php

declare(strict_types=1);

namespace Pest\Plugins;

use DateTimeImmutable;
use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Exceptions\InvalidOption;
use Pest\Support\Coverage as SupportCoverage;
use Pest\Support\Str;
use PHPUnit\Runner\Version;
use SebastianBergmann\CodeCoverage\Report\Facade;
use SebastianBergmann\CodeCoverage\Report\Thresholds;
use SebastianBergmann\CodeCoverage\Serialization\Serializer;
use SebastianBergmann\CodeCoverage\Serialization\Unserializer;
use SebastianBergmann\Environment\Runtime;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @internal
 *
 * @phpstan-import-type SerializedCoverage from Serializer
 */
final class Coverage implements AddsOutput, HandlesArguments
{
    use Concerns\HandleArguments;

    private const string COVERAGE_OPTION = 'coverage';

    private const string MIN_OPTION = 'min';

    private const string EXACTLY_OPTION = 'exactly';

    private const string ONLY_COVERED_OPTION = 'only-covered';

    private const string SHARDS_COVERAGE_OPTION = 'shards-coverage';

    private const string CLEAN_OPTION = 'clean';

    /**
     * @var array<string, bool> Each key is a report flag that a sharded run refuses, each value tells whether it reads its value from the next argument.
     */
    private const array SHARD_BLOCKED_REPORT_FLAGS = [
        '--coverage-clover' => true,
        '--coverage-cobertura' => true,
        '--coverage-crap4j' => true,
        '--coverage-html' => true,
        '--coverage-jsonl' => true,
        '--coverage-openclover' => true,
        '--coverage-text' => false,
        '--coverage-xml' => true,
    ];

    public bool $coverage = false;

    public bool $compact = false;

    public float $coverageMin = 0.0;

    public ?float $coverageExactly = null;

    public bool $showOnlyCovered = false;

    private ?int $shardIndex = null;

    private ?int $shardTotal = null;

    private bool $shardsCoverageClean = false;

    public function __construct(private readonly OutputInterface $output)
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function handleArguments(array $originals): array
    {
        if ($this->hasShardsCoverageFlag($originals)) {
            $originals = $this->popShardsCoverageFlags($originals);
            $this->parseThresholdOptions($originals);

            $this->handleShardsCoverageMerge($originals);
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

        foreach ($arguments as $argument) {
            $originals = $this->popArgument($argument, $originals);
        }

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
                $this->shardIndex = $shard['index'];
                $this->shardTotal = $shard['total'];

                $coverageDir = $this->getCoverageDir();
                if (! is_dir($coverageDir) && ! mkdir($coverageDir, 0755, true) && ! is_dir($coverageDir)) {
                    $this->output->writeln([
                        '',
                        sprintf(
                            '  <fg=white;bg=red;options=bold> ERROR </> Could not create coverage directory: %s',
                            $coverageDir,
                        ),
                        '',
                    ]);

                    exit(1);
                }

                $originals = $this->stripShardBlockedReportFlags($originals);

                $originals[] = '--coverage-php';
                $originals[] = $coverageDir.DIRECTORY_SEPARATOR.$this->shardIndex.'-of-'.$this->shardTotal.'.cov';
            } else {
                $originals[] = '--coverage-php';
                $originals[] = SupportCoverage::getPath();
            }

            if (! SupportCoverage::isAvailable()) {
                if (SupportCoverage::usingXdebug()) {
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

        $this->parseThresholdOptions($arguments);

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
            if (! SupportCoverage::isAvailable()) {
                $this->output->writeln(
                    "\n  <fg=white;bg=red;options=bold> ERROR </> No code coverage driver is available.</>",
                );

                exit(1);
            }

            $coverage = SupportCoverage::report($this->output, $this->compact, $this->showOnlyCovered);
            $exitCode = $this->applyThresholds($coverage);

            $this->output->writeln(['']);
        }

        return $exitCode;
    }

    /**
     * @param  array<int, string>  $originals
     */
    private function parseThresholdOptions(array $originals): void
    {
        $args = [...[''], ...array_values(array_filter($originals, function (string $original): bool {
            foreach ([self::MIN_OPTION, self::EXACTLY_OPTION, self::ONLY_COVERED_OPTION] as $option) {
                if ($original === sprintf('--%s', $option)) {
                    return true;
                }

                if (Str::startsWith($original, sprintf('--%s=', $option))) {
                    return true;
                }
            }

            return false;
        }))];

        $input = new ArgvInput($args, new InputDefinition([
            new InputOption(self::MIN_OPTION, null, InputOption::VALUE_REQUIRED),
            new InputOption(self::EXACTLY_OPTION, null, InputOption::VALUE_REQUIRED),
            new InputOption(self::ONLY_COVERED_OPTION, null, InputOption::VALUE_NONE),
        ]));

        if ($input->getOption(self::MIN_OPTION) !== null) {
            $this->coverageMin = (float) $input->getOption(self::MIN_OPTION);
        }

        if ($input->getOption(self::EXACTLY_OPTION) !== null) {
            $this->coverageExactly = (float) $input->getOption(self::EXACTLY_OPTION);
        }

        if ((bool) $input->getOption(self::ONLY_COVERED_OPTION)) {
            $this->showOnlyCovered = true;
        }
    }

    private function applyThresholds(float $coverage): int
    {
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

        return $exitCode;
    }

    private function computeComparableCoverage(float $coverage): float
    {
        return floor($coverage * 10) / 10;
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array{index: int, total: int}|null
     */
    private function detectShard(array $arguments): ?array
    {
        $input = new ArgvInput(['', ...$arguments]);

        if (! $input->hasParameterOption('--shard')) {
            return null;
        }

        return Shard::getShard($input);
    }

    private function getCoverageDir(): string
    {
        return SupportCoverage::getShardsDirectory();
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function stripShardBlockedReportFlags(array $arguments): array
    {
        $firstHint = null;
        $skipNext = false;
        $filtered = [];

        foreach ($arguments as $arg) {
            if ($skipNext) {
                $skipNext = false;

                continue;
            }

            $matched = false;
            foreach (self::SHARD_BLOCKED_REPORT_FLAGS as $flag => $readsNextArgument) {
                if ($arg === $flag) {
                    $firstHint ??= ltrim($flag, '-');
                    $skipNext = $readsNextArgument;
                    $matched = true;
                    break;
                }
                if (str_starts_with($arg, $flag.'=')) {
                    $firstHint ??= ltrim($flag, '-');
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
     * @param  array<int, string>  $arguments
     */
    private function hasShardsCoverageFlag(array $arguments): bool
    {
        return in_array('--'.self::SHARDS_COVERAGE_OPTION, $arguments, true);
    }

    /**
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
     * @param  array<int, string>  $arguments
     */
    private function handleShardsCoverageMerge(array $arguments): never
    {
        $coverage = $this->mergeAndReportShardsCoverage(null, $arguments);

        if ($coverage === null) {
            exit(1);
        }

        $exitCode = $this->applyThresholds($coverage);

        $this->output->writeln(['']);

        exit($exitCode);
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function mergeAndReportShardsCoverage(?string $coverageDir = null, array $arguments = []): ?float
    {
        $coverageDir ??= $this->getCoverageDir();

        $found = glob($coverageDir.DIRECTORY_SEPARATOR.'*-of-*.cov');

        if ($found === false || $found === []) {
            $files = [];
        } else {
            $files = array_values(array_filter($found, static fn (string $file): bool => $file !== ''));
        }

        if ($files === []) {
            $this->output->writeln([
                '',
                '  <fg=white;bg=red;options=bold> ERROR </> No coverage files found.',
                sprintf('  Expected .cov files in: %s', $coverageDir),
                '  Run tests with --shard=X/Y --coverage first.',
                '',
            ]);

            return null;
        }

        $total = $this->countShardsInSuite($files);

        if ($total === null) {
            return null;
        }

        $this->output->writeln([
            '',
            sprintf('  <fg=gray>Merging coverage from %d shards...</>', $total),
        ]);

        $merged = $this->mergeShards($files);

        if ($merged === null) {
            return null;
        }

        $coverage = SupportCoverage::render($merged, $this->output, $this->compact, $this->showOnlyCovered);

        $this->renderShardReports($merged, $arguments);

        if ($this->shardsCoverageClean) {
            $this->cleanShards($files);
        }

        return $coverage;
    }

    /**
     * @param  array<int, string>  $files
     */
    private function countShardsInSuite(array $files): ?int
    {
        $indexes = [];
        $total = null;

        foreach ($files as $file) {
            if (preg_match('/^(\d+)-of-(\d+)\.cov$/', basename($file), $matches) !== 1) {
                $this->reportIncompleteSuite(sprintf('- %s does not name a shard', basename($file)));

                return null;
            }

            $index = (int) $matches[1];
            $shardTotal = (int) $matches[2];

            if ($total !== null && $shardTotal !== $total) {
                $this->reportIncompleteSuite(sprintf('- %s belongs to a run of %d shards, not %d', basename($file), $shardTotal, $total));

                return null;
            }

            $total = $shardTotal;
            $indexes[] = $index;
        }

        if ($total === null) {
            return null;
        }

        $missing = array_diff(range(1, $total), $indexes);

        if ($missing !== []) {
            $this->reportIncompleteSuite(sprintf(
                '- shard %s of %d wrote no coverage file',
                implode(', ', $missing),
                $total,
            ));

            return null;
        }

        return $total;
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<string, non-empty-string>
     */
    private function findReportTargets(array $arguments): array
    {
        $targets = [];

        foreach (array_keys(self::SHARD_BLOCKED_REPORT_FLAGS) as $flag) {
            $target = $this->findReportTarget($arguments, $flag);

            if ($target !== null) {
                $targets[$flag] = $target;
            }
        }

        return $targets;
    }

    /**
     * @param  array<int, string>  $arguments
     * @return non-empty-string|null
     */
    private function findReportTarget(array $arguments, string $flag): ?string
    {
        foreach ($arguments as $index => $argument) {
            if (str_starts_with($argument, $flag.'=')) {
                $target = substr($argument, strlen($flag) + 1);
            } elseif ($argument === $flag) {
                $target = self::SHARD_BLOCKED_REPORT_FLAGS[$flag]
                    ? ($arguments[$index + 1] ?? throw new InvalidOption(sprintf('The [%s] option requires a value.', $flag)))
                    : 'php://stdout';
            } else {
                continue;
            }

            return $target === ''
                ? throw new InvalidOption(sprintf('The [%s] option requires a value.', $flag))
                : $target;
        }

        return null;
    }

    /**
     * @param  SerializedCoverage  $merged
     * @param  array<int, string>  $arguments
     */
    private function renderShardReports(array $merged, array $arguments): void
    {
        $targets = $this->findReportTargets($arguments);

        if ($targets === []) {
            return;
        }

        $facade = Facade::fromSerializedData($merged);

        foreach ($targets as $flag => $target) {
            match ($flag) {
                '--coverage-clover' => $facade->renderClover($target, 'Clover Coverage'),
                '--coverage-openclover' => $facade->renderOpenClover($target, 'OpenClover Coverage'),
                '--coverage-cobertura' => $facade->renderCobertura($target),
                '--coverage-crap4j' => $facade->renderCrap4j($target),
                '--coverage-jsonl' => $facade->renderJsonl($target),
                '--coverage-html' => $facade->renderHtml($target),
                '--coverage-xml' => $this->renderShardXmlReport($facade, $target, $merged),
                '--coverage-text' => $this->renderShardTextReport($facade, $target, $arguments),
                default => null,
            };

            $this->output->writeln(sprintf('  <fg=gray>Coverage:</>  Wrote %s.', $target));
        }
    }

    /**
     * @param  non-empty-string  $target
     * @param  SerializedCoverage  $merged
     */
    private function renderShardXmlReport(Facade $facade, string $target, array $merged): void
    {
        $driverInformation = $merged['buildInformation']['phpCodeCoverage']['driverInformation'];

        $facade->renderXml(
            $target,
            true,
            new Runtime,
            new DateTimeImmutable,
            Version::id(),
            $merged['buildInformation']['phpCodeCoverage']['version'],
            $driverInformation['name'],
            $driverInformation['version'],
        );
    }

    /**
     * @param  non-empty-string  $target
     * @param  array<int, string>  $arguments
     */
    private function renderShardTextReport(Facade $facade, string $target, array $arguments): void
    {
        $text = $facade->renderText(
            $target === 'php://stdout' ? null : $target,
            Thresholds::default(),
            in_array('--show-uncovered-for-coverage-text', $arguments, true),
            in_array('--only-summary-for-coverage-text', $arguments, true),
            $this->output->isDecorated(),
        );

        if ($target !== 'php://stdout') {
            return;
        }

        $this->output->writeln($text);
    }

    private function reportIncompleteSuite(string $reason): void
    {
        $this->output->writeln([
            '',
            '  <fg=white;bg=red;options=bold> ERROR </> The coverage files do not hold the whole suite, so the total is not known.',
            '  Run: <fg=cyan>pest --shard=X/Y --coverage</> for every shard, then merge again.',
            '',
            '  '.$reason,
            '',
        ]);
    }

    /**
     * @param  array<int, non-empty-string>  $files
     * @return SerializedCoverage|null
     */
    private function mergeShards(array $files): ?array
    {
        sort($files);

        $merged = null;
        $unreadable = [];

        foreach ($files as $file) {
            try {
                $shard = (new Unserializer)->unserialize($file);
            } catch (Throwable $e) {
                $unreadable[] = sprintf('- %s (%s)', basename($file), $e->getMessage());

                continue;
            }

            if ($merged === null) {
                $merged = $shard;

                continue;
            }

            $merged['codeCoverage']->merge($shard['codeCoverage']);
            $merged['testResults'] = array_merge($merged['testResults'], $shard['testResults']);
        }

        if ($unreadable !== []) {
            $this->reportIncompleteSuite(implode(PHP_EOL.'  ', $unreadable));

            return null;
        }

        return $merged;
    }

    /**
     * @param  array<int, string>  $files
     */
    private function cleanShards(array $files): void
    {
        foreach ($files as $file) {
            if (file_exists($file) && ! unlink($file)) {
                $this->output->writeln(sprintf(
                    '  <fg=yellow;options=bold> WARN </> Could not delete coverage file: %s',
                    basename($file),
                ));
            }
        }

        $this->output->writeln('  <fg=gray>Coverage files cleaned.</>');
    }
}
