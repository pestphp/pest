<?php

declare(strict_types=1);

namespace Pest\PendingObjects;

use Closure;
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
    private $hooks = [];

    /**
     * Holds the class and traits.
     *
     * @var array<int, string>
     */
    private $classAndTraits;

    /**
     * Holds the base dirname here the uses call was performed.
     *
     * @var string
     */
    private $filename;

    /**
     * Holds the targets of the uses.
     *
     * @var array<int, string>
     */
    private $targets;

    /**
     * Holds the groups of the uses.
     *
     * @var array<int, string>
     */
    private $groups = [];

    /**
     * Creates a new instance of a pending test uses.
     *
     * @param array<int, string> $classAndTraits
     */
    public function __construct(string $filename, array $classAndTraits)
    {
        $this->classAndTraits = $classAndTraits;
        $this->filename       = $filename;
        $this->targets        = [$filename];
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
                $path = (string) preg_replace_callback('~^(?P<drive>[a-z]+:\\\)~i', function ($match): string {
                    return strtolower($match['drive']);
                }, $path);

                $startChar = strtolower((string) preg_replace('~^([a-z]+:\\\).*$~i', '$1', __DIR__));
            }

            return 0 === strpos($path, $startChar)
                ? $path
                : implode(DIRECTORY_SEPARATOR, [
                    dirname($this->filename),
                    $path,
                ]);
        }, $targets);

        $this->targets = array_reduce($targets, function (array $accumulator, string $target): array {
            if (is_dir($target) || file_exists($target)) {
                $accumulator[] = (string) realpath($target);
            }

            return $accumulator;
        }, []);
    }

    /**
     * Sets the test group(s).
     */
    public function group(string ...$groups): UsesCall
    {
        $this->groups = $groups;

        return $this;
    }

    /**
     * Sets the global beforeAll test hook.
     */
    public function beforeAll(Closure $hook): UsesCall
    {
        $this->hooks[0] = $hook;

        return $this;
    }

    /**
     * Sets the global beforeEach test hook.
     */
    public function beforeEach(Closure $hook): UsesCall
    {
        $this->hooks[1] = $hook;

        return $this;
    }

    /**
     * Sets the global afterEach test hook.
     */
    public function afterEach(Closure $hook): UsesCall
    {
        $this->hooks[2] = $hook;

        return $this;
    }

    /**
     * Sets the global afterAll test hook.
     */
    public function afterAll(Closure $hook): UsesCall
    {
        $this->hooks[3] = $hook;

        return $this;
    }

    /**
     * Dispatch the creation of uses.
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
