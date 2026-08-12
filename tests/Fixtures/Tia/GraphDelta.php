<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tia;

/**
 * @internal
 */
final readonly class GraphDelta
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function __construct(
        private ?array $before,
        private ?array $after,
    ) {}

    public function graphWasCreated(): bool
    {
        return $this->before === null && $this->after !== null;
    }

    public function graphWasDeleted(): bool
    {
        return $this->before !== null && $this->after === null;
    }

    public function writtenCount(): int
    {
        $written = 0;

        foreach ($this->branchKeys() as $branch) {
            $before = $this->results($this->before, $branch);
            $after = $this->results($this->after, $branch);

            foreach ($before as $testId => $entry) {
                if (! isset($after[$testId])) {
                    continue;
                }

                foreach (['status', 'time', 'assertions', 'message'] as $field) {
                    if (($entry[$field] ?? null) !== ($after[$testId][$field] ?? null)) {
                        $written++;

                        break;
                    }
                }
            }
        }

        return $written;
    }

    public function added(): int
    {
        $added = 0;

        foreach ($this->branchKeys() as $branch) {
            $added += count(array_diff(
                array_keys($this->results($this->after, $branch)),
                array_keys($this->results($this->before, $branch)),
            ));
        }

        return $added;
    }

    public function removed(): int
    {
        $removed = 0;

        foreach ($this->branchKeys() as $branch) {
            $removed += count(array_diff(
                array_keys($this->results($this->before, $branch)),
                array_keys($this->results($this->after, $branch)),
            ));
        }

        return $removed;
    }

    /**
     * @return array<int, string>
     */
    public function branchKeys(): array
    {
        return array_map(strval(...), array_keys($this->baselines($this->after)));
    }

    /**
     * @return array<int, string>
     */
    public function branchKeysBefore(): array
    {
        return array_map(strval(...), array_keys($this->baselines($this->before)));
    }

    public function branchKeysMoved(): bool
    {
        return $this->branchKeysBefore() !== $this->branchKeys();
    }

    public function baselineUntouched(string $branch): bool
    {
        return ($this->baselines($this->before)[$branch] ?? null)
            === ($this->baselines($this->after)[$branch] ?? null);
    }

    public function shaMoved(): bool
    {
        return array_any($this->branchKeys(), fn (string $branch) => $this->baselineField($branch, 'sha', $this->before) !== $this->baselineField($branch, 'sha', $this->after));
    }

    public function treeMoved(): bool
    {
        return array_any($this->branchKeys(), fn (string $branch) => $this->baselineField($branch, 'tree', $this->before) !== $this->baselineField($branch, 'tree', $this->after));
    }

    public function edgesMoved(): bool
    {
        return $this->edgeSets($this->before) !== $this->edgeSets($this->after);
    }

    public function filesMoved(): bool
    {
        return $this->section($this->before, 'files') !== $this->section($this->after, 'files');
    }

    public function fingerprintMoved(): bool
    {
        return $this->section($this->before, 'fingerprint') !== $this->section($this->after, 'fingerprint');
    }

    public function structureMoved(): bool
    {
        if ($this->edgesMoved()) {
            return true;
        }
        if ($this->filesMoved()) {
            return true;
        }
        if ($this->fingerprintMoved()) {
            return true;
        }

        return $this->branchKeysMoved();
    }

    public function isHardSuppressed(): bool
    {
        return $this->before === $this->after;
    }

    public function isResultsOnly(): bool
    {
        return ! $this->graphWasCreated()
            && ! $this->graphWasDeleted()
            && ! $this->structureMoved()
            && ! $this->shaMoved()
            && ! $this->treeMoved()
            && $this->removed() === 0
            && $this->added() === 0;
    }

    public function summary(): string
    {
        if ($this->graphWasCreated()) {
            return 'graph created';
        }

        if ($this->graphWasDeleted()) {
            return 'graph deleted';
        }

        $moved = [];

        foreach (['edges', 'files', 'fingerprint'] as $section) {
            if ($this->{$section.'Moved'}()) {
                $moved[] = $section;
            }
        }

        if ($this->branchKeysMoved()) {
            $moved[] = sprintf(
                'branchkeys(%s->%s)',
                implode('|', $this->branchKeysBefore()),
                implode('|', $this->branchKeys()),
            );
        }

        return sprintf(
            'w=%d +%d -%d %s%s%s',
            $this->writtenCount(),
            $this->added(),
            $this->removed(),
            $moved === [] ? 'struct:ok' : 'STRUCT:'.implode(',', $moved),
            $this->shaMoved() ? ' sha:changed' : '',
            $this->treeMoved() ? ' tree:changed' : '',
        );
    }

    /**
     * @param  array<string, mixed>|null  $graph
     * @return array<string, array<int, string>>
     */
    private function edgeSets(?array $graph): array
    {
        $files = $this->section($graph, 'files');
        $sets = [];

        foreach ($this->section($graph, 'edges') as $test => $ids) {
            if (! is_string($test)) {
                continue;
            }
            if (! is_array($ids)) {
                continue;
            }
            $paths = array_map(
                fn (mixed $id): string => is_int($id) && isset($files[$id]) && is_string($files[$id])
                    ? $files[$id]
                    : '?'.json_encode($id),
                $ids,
            );

            sort($paths);
            $sets[$test] = $paths;
        }

        ksort($sets);

        return $sets;
    }

    /**
     * @param  array<string, mixed>|null  $graph
     * @return array<mixed>
     */
    private function section(?array $graph, string $key): array
    {
        $section = $graph[$key] ?? null;

        return is_array($section) ? $section : [];
    }

    /**
     * @param  array<string, mixed>|null  $graph
     * @return array<string, mixed>
     */
    private function baselines(?array $graph): array
    {
        $baselines = [];

        foreach ($this->section($graph, 'baselines') as $branch => $baseline) {
            $baselines[(string) $branch] = $baseline;
        }

        return $baselines;
    }

    /**
     * @param  array<string, mixed>|null  $graph
     * @return array<string, array<string, mixed>>
     */
    private function results(?array $graph, string $branch): array
    {
        $results = $this->baselines($graph)[$branch]['results'] ?? null;

        if (! is_array($results)) {
            return [];
        }

        $entries = [];

        foreach ($results as $testId => $entry) {
            if (is_string($testId) && is_array($entry)) {
                $entries[$testId] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>|null  $graph
     */
    private function baselineField(string $branch, string $field, ?array $graph): mixed
    {
        return $this->baselines($graph)[$branch][$field] ?? null;
    }
}
