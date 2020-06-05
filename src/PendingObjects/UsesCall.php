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

        $this->targets = array_map(function ($target): string {
            $isValid = is_dir($target) || file_exists($target);
            if (!$isValid) {
                throw new InvalidUsesPath($target);
            }

            return (string) realpath($target);
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
