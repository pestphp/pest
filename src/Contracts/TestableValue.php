<?php

declare(strict_types=1);

namespace Pest\Contracts;

interface TestableValue
{
    /**
     * @return mixed
     */
    public function origin();

    /**
     * @return mixed
     */
    public function expected();
}
