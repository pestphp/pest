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
     * @var array<string, object|string>
     */
    private array $instances = [];

    public static function getInstance(): self
    {
        if (! self::$instance instanceof Container) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function get(string $id): object|string
    {
        if (! array_key_exists($id, $this->instances)) {
            /** @var class-string $id */
            $this->instances[$id] = $this->build($id);
        }

        return $this->instances[$id];
    }

    /**
     * @return $this
     */
    public function add(string $id, object|string $instance): self
    {
        $this->instances[$id] = $instance;

        return $this;
    }

    /**
     * @template TObject of object
     *
     * @param  class-string<TObject>  $id
     * @return TObject
     */
    private function build(string $id): object
    {
        $reflectionClass = new ReflectionClass($id);

        if ($reflectionClass->isInstantiable()) {
            $constructor = $reflectionClass->getConstructor();

            if ($constructor instanceof \ReflectionMethod) {
                $params = array_map(
                    function (ReflectionParameter $param) use ($id): object|string {
                        $candidate = Reflection::getParameterClassName($param);

                        if ($candidate === null) {
                            $type = $param->getType();
                            /* @phpstan-ignore-next-line */
                            if ($type instanceof \ReflectionType && $type->isBuiltin()) {
                                $candidate = $param->getName();
                            } else {
                                throw ShouldNotHappen::fromMessage(sprintf('The type of [$%s] in [%s] cannot be determined.', $id, $param->getName()));
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

        throw ShouldNotHappen::fromMessage(sprintf('A dependency with the name [%s] cannot be resolved.', $id));
    }
}
