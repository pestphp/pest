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
    private array $messages = [];

    /**
     * Adds a new higher order message to the collection.
     *
     * @param  array<int, mixed>|null  $arguments
     */
    public function add(string $filename, int $line, string $name, ?array $arguments): void
    {
        $this->messages[] = new HigherOrderMessage($filename, $line, $name, $arguments);
    }

    /**
     * Adds a new higher order message to the collection if the callable condition is does not return false.
     *
     * @param  array<int, mixed>|null  $arguments
     */
    public function addWhen(callable $condition, string $filename, int $line, string $name, ?array $arguments): void
    {
        $this->messages[] = (new HigherOrderMessage($filename, $line, $name, $arguments))->when($condition);
    }

    /**
     * Proxy all the messages starting from the target.
     */
    public function chain(object $target): void
    {
        foreach ($this->messages as $message) {
            // @phpstan-ignore-next-line
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

    /**
     * Count the number of messages with the given name.
     *
     * @param  string  $name  A higher order message name (usually a method name)
     */
    public function count(string $name): int
    {
        return array_reduce(
            $this->messages,
            static fn (int $total, HigherOrderMessage $message): int => $total + (int) ($name === $message->name),
            0,
        );
    }
}
