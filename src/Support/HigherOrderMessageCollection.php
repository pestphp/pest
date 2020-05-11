<?php

declare(strict_types=1);

namespace Pest\Support;

use ReflectionClass;
use Throwable;

/**
 * @internal
 */
final class HigherOrderMessageCollection
{
    public const UNDEFINED_METHOD = 'Method %s does not exist';

    /**
     * @var array<int, HigherOrderMessage>
     */
    private $messages = [];

    /**
     * Adds a new higher order message to the collection.
     *
     * @param array<int, mixed> $arguments
     */
    public function add(string $filename, int $line, string $methodName, array $arguments): void
    {
        $this->messages[] = new HigherOrderMessage($filename, $line, $methodName, $arguments);
    }

    /**
     * Proxy all the messages starting from the target.
     */
    public function chain(object $target): void
    {
        foreach ($this->messages as $message) {
            $target = $this->attempt($target, $message);
        }
    }

    /**
     * Proxy all the messages to the target.
     */
    public function proxy(object $target): void
    {
        foreach ($this->messages as $message) {
            $this->attempt($target, $message);
        }
    }

    /**
     * Re-throws the given `$throwable` with the good line and filename.
     *
     * @return mixed
     */
    private function attempt(object $target, HigherOrderMessage $message)
    {
        try {
            return Reflection::call($target, $message->methodName, $message->arguments);
        } catch (Throwable $throwable) {
            Reflection::setPropertyValue($throwable, 'file', $message->filename);
            Reflection::setPropertyValue($throwable, 'line', $message->line);

            if ($throwable->getMessage() === sprintf(self::UNDEFINED_METHOD, $message->methodName)) {
                /** @var \ReflectionClass $reflection */
                $reflection = (new ReflectionClass($target))->getParentClass();
                Reflection::setPropertyValue($throwable, 'message', sprintf('Call to undefined method %s::%s()', $reflection->getName(), $message->methodName));
            }

            throw $throwable;
        }
    }
}
