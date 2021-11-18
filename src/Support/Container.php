<?php

declare(strict_types=1);

namespace Pest\Support;

use Pest\Exceptions\ShouldNotHappen;
use ReflectionClass;
use ReflectionParameter;

/**
 * @internal
 */
final class Container
{
    private static ?Container $instance = null;

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Gets a new or already existing container.
     */
    public static function getInstance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Gets a dependency from the container.
     *
     * @param class-string $id
     *
     * @return object
     */
    public function get(string $id)
    {
        if (!array_key_exists($id, $this->instances)) {
            $this->instances[$id] = $this->build($id);
        }

        if (!is_object($this->instances[$id])) {
            throw ShouldNotHappen::fromMessage('Cannot resolve a non-object from container');
        }

        return $this->instances[$id];
    }

    /**
     * Adds the given instance to the container.
     *
     * @param mixed $instance
     */
    public function add(string $id, $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * Tries to build the given instance.
     *
     * @param class-string $id
     */
    private function build(string $id): object
    {
        $reflectionClass = new ReflectionClass($id);

        if ($reflectionClass->isInstantiable()) {
            $constructor = $reflectionClass->getConstructor();

            if ($constructor !== null) {
                $params = array_map(
                    function (ReflectionParameter $param) use ($id) {
                        $candidate = Reflection::getParameterClassName($param);

                        if ($candidate === null) {
                            $type = $param->getType();
                            /* @phpstan-ignore-next-line */
                            if ($type !== null && $type->isBuiltin()) {
                                $candidate = $param->getName();
                            } else {
                                throw ShouldNotHappen::fromMessage(sprintf('The type of `$%s` in `%s` cannot be determined.', $id, $param->getName()));
                            }
                        }

                        //@phpstan-ignore-next-line
                        return $this->get($candidate);
                    },
                    $constructor->getParameters()
                );

                return $reflectionClass->newInstanceArgs($params);
            }

            return $reflectionClass->newInstance();
        }

        throw ShouldNotHappen::fromMessage(sprintf('A dependency with the name `%s` cannot be resolved.', $id));
    }
}
