<?php

declare(strict_types=1);

namespace Pest\Factories\Annotations;

use Pest\Factories\TestCaseMethodFactory;

/**
 * @internal
 */
final class Groups
{
    /**
     * Adds annotations regarding the "groups" feature.
     */
    public function add(TestCaseMethodFactory $method, array $annotations): array
    {
        foreach ($method->groups as $group) {
            $annotations[] = "@group $group";
        }

        return $annotations;
    }
}
