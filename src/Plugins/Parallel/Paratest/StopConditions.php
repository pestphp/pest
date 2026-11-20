<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Paratest;

use PHPUnit\Runner\DeprecationCollector\Facade as DeprecationCollector;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;
use PHPUnit\TestRunner\TestResult\Issues\Issue;
use PHPUnit\TestRunner\TestResult\TestResult;
use PHPUnit\TextUI\Configuration\Configuration;
use RuntimeException;

final class StopConditions
{
    /**
     * @var array<string, positive-int>
     */
    private readonly array $thresholds;

    /**
     * @var array<string, array<string, non-negative-int>>
     */
    private array $workerCounts = [];

    /**
     * @var array<string, non-negative-int>
     */
    private array $totals = [];

    /**
     * @var array<string, array<string, true>>
     */
    private array $issueIds = [];

    public function __construct(private readonly Configuration $configuration)
    {
        $this->thresholds = array_filter([
            'defect' => $configuration->stopOnDefectThreshold(),
            'error' => $configuration->stopOnErrorThreshold(),
            'failure' => $configuration->stopOnFailureThreshold(),
            'warning' => $configuration->stopOnWarningThreshold(),
            'risky' => $configuration->stopOnRiskyThreshold(),
            'deprecation' => $configuration->stopOnDeprecationThreshold(),
            'notice' => $configuration->stopOnNoticeThreshold(),
            'incomplete' => $configuration->stopOnIncompleteThreshold(),
            'skipped' => $configuration->stopOnSkippedThreshold(),
        ], static fn (int $threshold): bool => $threshold > 0);
    }

    public function write(string $filename): void
    {
        if ($this->thresholds === []) {
            return;
        }

        $result = TestResultFacade::result();
        $state = json_encode([
            'counts' => $this->counts($result),
            'issues' => $this->issues($result),
        ], JSON_THROW_ON_ERROR);

        if (file_put_contents($filename.'.tmp', $state) === false
            || ! rename($filename.'.tmp', $filename)) {
            throw new RuntimeException(sprintf('Unable to write the parallel stop counts to [%s].', $filename));
        }
    }

    public function read(string $filename): void
    {
        if ($this->thresholds === []) {
            return;
        }

        $contents = file_get_contents($filename);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read the parallel stop counts from [%s].', $filename));
        }

        $state = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($state)
            || ! isset($state['counts'], $state['issues'])
            || ! is_array($state['counts'])
            || ! is_array($state['issues'])) {
            throw new RuntimeException(sprintf('Invalid parallel stop counts in [%s].', $filename));
        }

        $this->addCounts($filename, $state['counts']);
        $this->addIssues($filename, $state['issues']);
    }

    public function shouldStop(): bool
    {
        return array_any($this->thresholds, function (int $threshold, string $outcome): bool {
            $issueType = $outcome === 'defect' ? 'warning' : $outcome;
            $count = ($this->totals[$outcome] ?? 0) + count($this->issueIds[$issueType] ?? []);

            return $count >= $threshold;
        });
    }

    /**
     * @return array<string, int>
     */
    private function counts(TestResult $result): array
    {
        $counts = [
            'error' => $result->numberOfTestErroredEvents(),
            'failure' => $result->numberOfTestFailedEvents(),
            'warning' => $result->numberOfPhpunitWarnings(),
            'risky' => $result->numberOfTestsWithTestConsideredRiskyEvents(),
            'deprecation' => $this->numberOfDeprecations(),
            'notice' => 0,
            'incomplete' => $result->numberOfTestMarkedIncompleteEvents(),
            'skipped' => $result->numberOfTestSkippedEvents(),
        ];
        $counts['defect'] = $counts['error'] + $counts['failure'] + $counts['warning'] + $counts['risky'];

        return $counts;
    }

    /**
     * @return array<'warning'|'notice', list<non-empty-string>>
     */
    private function issues(TestResult $result): array
    {
        $issues = [];

        if (isset($this->thresholds['warning']) || isset($this->thresholds['defect'])) {
            $issues['warning'] = array_merge(
                $this->issueKeys($result->warnings(), 'user'),
                $this->issueKeys($result->phpWarnings(), 'php'),
            );
        }

        if (isset($this->thresholds['notice'])) {
            $issues['notice'] = array_merge(
                $this->issueKeys($result->notices(), 'user'),
                $this->issueKeys($result->phpNotices(), 'php'),
            );
        }

        return $issues;
    }

    /**
     * @return non-negative-int
     */
    private function numberOfDeprecations(): int
    {
        $deprecations = DeprecationCollector::filteredDeprecations();

        if ($this->configuration->hasSpecificDeprecationToStopOn()) {
            $message = $this->configuration->specificDeprecationToStopOn();
            $deprecations = array_filter($deprecations, static fn (string $deprecation): bool => str_contains($deprecation, $message));
        }

        return count($deprecations);
    }

    /**
     * @param  array<array-key, mixed>  $counts
     */
    private function addCounts(string $filename, array $counts): void
    {
        foreach (array_keys($this->thresholds) as $outcome) {
            if (! isset($counts[$outcome]) || ! is_int($counts[$outcome]) || $counts[$outcome] < 0) {
                throw new RuntimeException(sprintf('Invalid parallel stop counts in [%s].', $filename));
            }

            $previous = $this->workerCounts[$filename][$outcome] ?? 0;
            $increase = $counts[$outcome] - $previous;

            if ($increase < 0) {
                throw new RuntimeException(sprintf('Parallel stop counts decreased in [%s].', $filename));
            }

            $this->totals[$outcome] = ($this->totals[$outcome] ?? 0) + $increase;
            $this->workerCounts[$filename][$outcome] = $counts[$outcome];
        }
    }

    /**
     * @param  array<array-key, mixed>  $issues
     */
    private function addIssues(string $filename, array $issues): void
    {
        foreach ($issues as $outcome => $ids) {
            if (! in_array($outcome, ['warning', 'notice'], true) || ! is_array($ids)) {
                throw new RuntimeException(sprintf('Invalid parallel stop issues in [%s].', $filename));
            }

            foreach ($ids as $id) {
                if (! is_string($id)) {
                    throw new RuntimeException(sprintf('Invalid parallel stop issue in [%s].', $filename));
                }

                $this->issueIds[$outcome][$id] = true;
            }
        }
    }

    /**
     * @param  list<Issue>  $issues
     * @return list<non-empty-string>
     */
    private function issueKeys(array $issues, string $origin): array
    {
        return array_map(
            static fn (Issue $issue): string => hash('sha256', implode(':', [$origin, $issue->file(), $issue->line(), $issue->description()])),
            $issues,
        );
    }
}
