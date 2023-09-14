<?php

declare(strict_types=1);

namespace Tests\Fixtures\Arch\ToUseTrait;

/**
 * This class uses TraitTwo in this class itself, and uses TraitTwo
 * via the parent class that we're extending from.
 */
class UsesTraitOneAndTwo extends ParentClassUsingTrait
{
    use TraitTwo;
}
