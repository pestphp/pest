<?php

declare(strict_types=1);

namespace Pest\Support;

use ReflectionClass;
use Throwable;

/**
 * @internal
 */
final class HigherOrderMessage
{
    public const UNDEFINED_METHOD = 'Method %s does not exist';

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

    /**
     * Re-throws the given `$throwable` with the good line and filename.
     *
     * @return mixed
     */
    public function call(object $target)
    {
        try {
            return Reflection::call($target, $this->methodName, $this->arguments);
        } catch (Throwable $throwable) {
            Reflection::setPropertyValue($throwable, 'file', $this->filename);
            Reflection::setPropertyValue($throwable, 'line', $this->line);

            if ($throwable->getMessage() === self::getUndefinedMethodMessage($target, $this->methodName)) {
                /** @var ReflectionClass $reflection */
                $reflection = new ReflectionClass($target);
                /* @phpstan-ignore-next-line */
                $reflection = $reflection->getParentClass() ?: $reflection;
                Reflection::setPropertyValue($throwable, 'message', sprintf('Call to undefined method %s::%s()', $reflection->getName(), $this->methodName));
            }

            throw $throwable;
        }
    }

    private static function getUndefinedMethodMessage(object $target, string $methodName): string
    {
        if (\PHP_MAJOR_VERSION >= 8) {
            return sprintf(sprintf(self::UNDEFINED_METHOD, sprintf('%s::%s()', get_class($target), $methodName)));
        }

        return sprintf(self::UNDEFINED_METHOD, $methodName);
    }
}
