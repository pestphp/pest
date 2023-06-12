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
     * Contains a global before each hook closure to be executed.
     *
     * Array indices here matter. They are mapped as follows:
     *
     * - `0` => `beforeAll`
     * - `1` => `beforeEach`
     * - `2` => `afterEach`
     * - `3` => `afterAll`
     *
     * @var array<int, Closure>
     */
    private array $hooks = [];

    /**
     * Holds the targets of the uses.
     *
     * @var array<int, string>
     */
    private array $targets;

    /**
     * Holds the groups of the uses.
     *
     * @var array<int, string>
     */
    private array $groups = [];

    /**
     * Creates a new Pending Call.
     *
     * @param  array<int, string>  $classAndTraits
     */
    public function __construct(
        private readonly string $filename,
        private readonly array $classAndTraits
    ) {
        $this->targets = [$filename];
    }

    public function compact(): self
    {
        DefaultPrinter::compact(true);

        return $this;
    }

    /**
     * The directories or file where the
     * class or traits should be used.
     */
    public function in(string ...$targets): void
    {
        $targets = array_map(function ($path): string {
            $startChar = DIRECTORY_SEPARATOR;

            if ('\\' === DIRECTORY_SEPARATOR || preg_match('~\A[A-Z]:(?![^/\\\\])~i', $path) > 0) {
                $path = (string) preg_replace_callback('~^(?P<drive>[a-z]+:\\\)~i', fn ($match): string => strtolower($match['drive']), $path);

                $startChar = strtolower((string) preg_replace('~^([a-z]+:\\\).*$~i', '$1', __DIR__));
            }

            return str_starts_with($path, $startChar)
                ? $path
                : implode(DIRECTORY_SEPARATOR, [
                    dirname($this->filename),
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
    }

    /**
     * Sets the test group(s).
     */
    public function group(string ...$groups): self
    {
        $this->groups = array_values($groups);

        return $this;
    }

    /**
     * Sets the global beforeAll test hook.
     */
    public function beforeAll(Closure $hook): self
    {
        $this->hooks[0] = $hook;

        return $this;
    }

    /**
     * Sets the global beforeEach test hook.
     */
    public function beforeEach(Closure $hook): self
    {
        $this->hooks[1] = $hook;

        return $this;
    }

    /**
     * Sets the global afterEach test hook.
     */
    public function afterEach(Closure $hook): self
    {
        $this->hooks[2] = $hook;

        return $this;
    }

    /**
     * Sets the global afterAll test hook.
     */
    public function afterAll(Closure $hook): self
    {
        $this->hooks[3] = $hook;

        return $this;
    }

    /**
     * Creates the Call.
     */
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
