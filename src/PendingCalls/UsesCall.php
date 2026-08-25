<?php

declare(strict_types=1);

namespace Pest\PendingCalls;

use Closure;
use NunoMaduro\Collision\Adapters\Phpunit\Printers\DefaultPrinter;
use Pest\TestSuite;

/**
 * @internal
 */
final class UsesCall
{
    /**
     * @var array<int, Closure>
     */
    private array $hooks = [];

    /**
     * @var array<int, string>
     */
    private array $targets;

    /**
     * @var array<int, string>
     */
    private array $groups = [];

    /**
     * @param  array<int, string>  $classAndTraits
     */
    public function __construct(
        private readonly string $filename,
        private array $classAndTraits
    ) {
        $this->targets = [$filename];
    }

    #[\Deprecated(message: 'Use `pest()->printer()->compact()` instead.')]
    public function compact(): self
    {
        DefaultPrinter::compact(true);

        return $this;
    }

    /**
     * @alias extend
     */
    public function use(string ...$classAndTraits): self
    {
        return $this->extend(...$classAndTraits);
    }

    public function extend(string ...$classAndTraits): self
    {
        $this->classAndTraits = array_merge($this->classAndTraits, array_values($classAndTraits));

        return $this;
    }

    public function in(string ...$targets): self
    {
        $targets = array_map(function (string $path): string {
            $startChar = DIRECTORY_SEPARATOR;

            if ('\\' === DIRECTORY_SEPARATOR || preg_match('~\A[A-Z]:(?![^/\\\\])~i', $path) > 0) {
                $path = (string) preg_replace_callback('~^(?P<drive>[a-z]+:\\\)~i', fn (array $match): string => strtolower($match['drive']), $path);

                $startChar = strtolower((string) preg_replace('~^([a-z]+:\\\).*$~i', '$1', __DIR__));
            }

            return str_starts_with($path, $startChar)
                ? $path
                : implode(DIRECTORY_SEPARATOR, [
                    is_dir($this->filename) ? $this->filename : dirname($this->filename),
                    $path,
                ]);
        }, $targets);

        $this->targets = array_reduce($targets, function (array $accumulator, string $target): array {
            if (($matches = glob($target)) !== false) {
                foreach ($matches as $file) {
                    $accumulator[] = (string) realpath($file);
                }
            }

            return $accumulator;
        }, []);

        return $this;
    }

    public function group(string ...$groups): self
    {
        $this->groups = array_values($groups);

        return $this;
    }

    public function beforeAll(Closure $hook): self
    {
        $this->hooks[0] = $hook;

        return $this;
    }

    public function beforeEach(Closure $hook): self
    {
        $this->hooks[1] = $hook;

        return $this;
    }

    public function afterEach(Closure $hook): self
    {
        $this->hooks[2] = $hook;

        return $this;
    }

    public function afterAll(Closure $hook): self
    {
        $this->hooks[3] = $hook;

        return $this;
    }

    public function __destruct()
    {
        TestSuite::getInstance()->tests->use(
            $this->classAndTraits,
            $this->groups,
            $this->targets,
            $this->hooks,
        );
    }
}
