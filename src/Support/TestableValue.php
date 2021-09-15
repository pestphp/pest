<?php

declare(strict_types=1);

namespace Pest\Support;

use Pest\Contracts\TestableValue as TestableValueInterface;
use Pest\Exceptions\MissingExpectedValue;

final class TestableValue implements TestableValueInterface
{
    /**
     * @var array<mixed>
     */
    private $value = [];

    /**
     * @param mixed $origin
     */
    public function __construct($origin)
    {
        $this->value['origin'] = $origin;
    }

    /**
     * @return mixed
     */
    public function origin()
    {
        return $this->value['origin'];
    }

    /**
     * @return mixed
     */
    public function expected()
    {
        if (array_key_exists('expected', $this->value)) {
            return $this->value['expected'];
        }

        throw new MissingExpectedValue();
    }

    /**
     * @param mixed $value
     *
     * @return TestableValue
     */
    public function expect($value): TestableValueInterface
    {
        $this->value['expected'] = $value;

        return $this;
    }
}
