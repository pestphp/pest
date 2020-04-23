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
    private array $classAndTraits;

    /**
     * Holds the base dirname here the uses call was performed.
     */
    private string $filename;

    /**
     * Holds the targets of the uses.
     *
     * @var array<int, string>
     */
    private array $targets;

    /**
     * Creates a new instance of a pending test uses.
     *
     * @var array<int, string>
     * @var array<int, string>
     */
    public function __construct(string $filename, array $classAndTraits)
    {
        $this->classAndTraits = $classAndTraits;
        $this->filename       = $filename;
        $this->targets        = [$filename];
    }

    /**
     * @var array<int, string> ...$targets
     *
     * @todo Consider using Symfony's finder component here.
     */
    public function in(string ...$targets): void
    {
        $targets = array_map(fn ($path) => $path[0] === DIRECTORY_SEPARATOR ? $path : implode(DIRECTORY_SEPARATOR, [
            dirname($this->filename),
            $path,
        ]), $targets);

        $this->targets = array_map(function ($target): string {
            $realTarget = realpath($target);
            if ($realTarget === false) {
                throw new InvalidUsesPath($target);
            }

            return $realTarget;
        }, $targets);
    }

    /**
     * Dispatch the creation of uses.
     */
    public function __destruct()
    {
        TestSuite::getInstance()->tests->use($this->classAndTraits, $this->targets);
    }
}
