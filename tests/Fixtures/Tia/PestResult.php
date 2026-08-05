<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tia;

/**
 * The outcome of one `pest` invocation against a fixture project.
 *
 * @internal
 */
final readonly class PestResult
{
    /**
     * The run's output, with the terminal's escape sequences taken back out.
     */
    public string $output;

    /**
     * @param  array<int, string>  $arguments
     */
    public function __construct(
        public array $arguments,
        string $output,
        public int $exitCode,
    ) {
        $this->output = (string) preg_replace([
            '#\x1b[[][^A-Za-z]*[A-Za-z]#',                  // colours, cursor moves
            '#\x1b\]8;[^\x1b\x07]*(?:\x1b\\\\|\x07)#',      // hyperlinks
        ], '', $output);
    }

    /**
     * Tests whose cached result was replayed instead of executed.
     */
    public function replayed(): int
    {
        return $this->recapFragment('replayed');
    }

    /**
     * Tests that ran because the graph held nothing for them — the count that
     * betrays a fallback which never resolved.
     */
    public function uncached(): int
    {
        return $this->recapFragment('uncached');
    }

    /**
     * Tests that ran because a file they depend on changed.
     */
    public function affected(): int
    {
        return $this->recapFragment('affected');
    }

    /**
     * The `Tests:` summary line, without its label or leading whitespace.
     */
    public function tally(): string
    {
        if (preg_match('/^\s*Tests:\s+(.+)$/m', $this->output, $matches) !== 1) {
            return '';
        }

        return trim($matches[1]);
    }

    public function contains(string $needle): bool
    {
        return str_contains($this->output, $needle);
    }

    /**
     * A description of the run, for failure messages that would otherwise say
     * only that 0 !== 6.
     */
    public function describe(): string
    {
        return sprintf(
            "pest %s exited %d:\n%s",
            implode(' ', $this->arguments),
            $this->exitCode,
            $this->output,
        );
    }

    /**
     * Read off the `Tests:` line rather than the whole output: the TIA headline
     * counts affected *files*, and matching that instead would be a quietly
     * wrong number.
     */
    private function recapFragment(string $label): int
    {
        if (preg_match('/(\d+) '.preg_quote($label, '/').'/', $this->tally(), $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }
}
