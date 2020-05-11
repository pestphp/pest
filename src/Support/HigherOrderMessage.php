<?php

declare(strict_types=1);

namespace Pest\Support;

/**
 * @internal
 */
final class HigherOrderMessage
{
    /**
     * The filename where the function was originally called.
     *
     * @readonly
     *
     * @var string
     */
    public $filename;

    /**
     * The line where the function was originally called.
     *
     * @readonly
     *
     * @var int
     */
    public $line;

    /**
     * The method name.
     *
     * @readonly
     *
     * @var string
     */
    public $methodName;

    /**
     * The arguments.
     *
     * @var array<int, mixed>
     *
     * @readonly
     */
    public $arguments;

    /**
     * Creates a new higher order message.
     *
     * @param array<int, mixed> $arguments
     */
    public function __construct(string $filename, int $line, string $methodName, array $arguments)
    {
        $this->filename   = $filename;
        $this->line       = $line;
        $this->methodName = $methodName;
        $this->arguments  = $arguments;
    }
}
