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
     */
    public string $filename;

    /**
     * The line where the function was originally called.
     *
     * @readonly
     */
    public int $line;

    /**
     * The method name.
     *
     * @readonly
     */
    public string $methodName;

    /**
     * The arguments.
     *
     * @var array<int, mixed>
     *
     * @readonly
     */
    public array $arguments;

    /**
     * Creates a new higher order message.
     */
    public function __construct(string $filename, int $line, string $methodName, array $arguments)
    {
        $this->filename   = $filename;
        $this->line       = $line;
        $this->methodName = $methodName;
        $this->arguments  = $arguments;
    }
}
