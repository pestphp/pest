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
            static::$instance = new self();
        }

        return static::$instance;
    }

    /**
     * Gets a dependency from the container.
     *
     * @template TObject of object
     *
     * @param  class-string<TObject>  $id
     * @return TObject
     */
    public function get(string $id): mixed
    {
        if (! array_key_exists($id, $this->instances)) {
            $this->instances[$id] = $this->build($id);
        }

        /** @var TObject $concrete */
        $concrete = $this->instances[$id];

        return $concrete;
    }

    /**
     * Adds the given instance to the container.
     */
    public function add(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * Tries to build the given instance.
     *
     * @template TObject of object
     *
     * @param  class-string<TObject>  $id
     * @return TObject
     */
    private function build(string $id): mixed
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

                        // @phpstan-ignore-next-line
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
