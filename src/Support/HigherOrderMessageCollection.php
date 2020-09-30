<?php

declare(strict_types=1);

namespace Pest\Support;

/**
 * @internal
 */
final class HigherOrderMessageCollection
{
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
            $target = $message->call($target) ?? $target;
        }
    }

    /**
     * Proxy all the messages to the target.
     */
    public function proxy(object $target): void
    {
        foreach ($this->messages as $message) {
            $message->call($target);
        }
    }
}
