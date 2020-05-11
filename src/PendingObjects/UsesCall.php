<?php

declare(strict_types=1);

namespace Pest\PendingObjects;

use Pest\Exceptions\InvalidUsesPath;
use Pest\TestSuite;

/**
 * @internal
 */
final class UsesCall
{
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
     * class or trais should be used.
     */
    public function in(string ...$targets): void
    {
        $targets = array_map(function ($path): string {
            return $path[0] === DIRECTORY_SEPARATOR
                ? $path
                : implode(DIRECTORY_SEPARATOR, [
                    dirname($this->filename),
                    $path,
                ]);
        }, $targets);

        $this->targets = array_map(function ($target): string {
            $realTarget = realpath($target);
            if ($realTarget === false) {
                throw new InvalidUsesPath($target);
            }

            return $realTarget;
        }, $targets);
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
     * Dispatch the creation of uses.
     */
    public function __destruct()
    {
        TestSuite::getInstance()->tests->use($this->classAndTraits, $this->groups, $this->targets);
    }
}
