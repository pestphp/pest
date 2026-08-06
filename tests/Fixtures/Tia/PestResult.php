<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tia;

/**
 * @internal
 */
final readonly class PestResult
{
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
            '#\x1b[[][^A-Za-z]*[A-Za-z]#',
            '#\x1b\]8;[^\x1b\x07]*(?:\x1b\\\\|\x07)#',
        ], '', $output);
    }

    public function replayed(): int
    {
        return $this->recapFragment('replayed');
    }

    public function uncached(): int
    {
        return $this->recapFragment('uncached');
    }

    public function affected(): int
    {
        return $this->recapFragment('affected');
    }

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

    public function describe(): string
    {
        return sprintf(
            "pest %s exited %d:\n%s",
            implode(' ', $this->arguments),
            $this->exitCode,
            $this->output,
        );
    }

    private function recapFragment(string $label): int
    {
        if (preg_match('/(\d+) '.preg_quote($label, '/').'/', $this->tally(), $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }
}
