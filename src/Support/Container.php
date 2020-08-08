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
    /**
     * @var self
     */
    private static $instance;

    /**
     * @var array<string, mixed>
     */
    private $instances = [];

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
     * @return object
     */
    public function get(string $id)
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        $this->instances[$id] = $this->build($id);

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
     */
    private function build(string $id): object
    {
        /** @phpstan-ignore-next-line */
        $reflectionClass = new ReflectionClass($id);

        if ($reflectionClass->isInstantiable()) {
            $constructor = $reflectionClass->getConstructor();

            if ($constructor !== null) {
                $params = array_map(
                    function (ReflectionParameter $param) use ($id) {
                        $candidate = Reflection::getParameterClassName($param);

                        if ($candidate === null) {
                            $type = $param->getType();
                            if ($type !== null && $type->isBuiltin()) {
                                $candidate = $param->getName();
                            } else {
                                throw ShouldNotHappen::fromMessage(sprintf('The type of `$%s` in `%s` cannot be determined.', $id, $param->getName()));
                            }
                        }

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
