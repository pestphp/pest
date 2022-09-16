<?php

declare(strict_types=1);

namespace Pest\Factories\Annotations;

use Pest\Factories\TestCaseMethodFactory;
use Pest\Support\Str;

/**
 * @internal
 */
final class Depends
{
    /**
     * Adds annotations regarding the "depends" feature.
     *
     * @param  array<int, string>  $annotations
     * @return array<int, string>
     */
    public function __invoke(TestCaseMethodFactory $method, array $annotations): array
    {
        foreach ($method->depends as $depend) {
            $depend = Str::evaluable($depend);

            $annotations[] = "@depends $depend";
        }

        return $annotations;
    }
}
