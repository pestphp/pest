<?php

if (class_exists(NunoMaduro\Collision\Provider::class)) {
    (new NunoMaduro\Collision\Provider())->register();
}

trait PluginTrait
{
    public function assertPluginTraitGotRegistered(): void
    {
        $this->assertTrue(true);
    }
}

trait SecondPluginTrait
{
    public function assertSecondPluginTraitGotRegistered(): void
    {
        $this->assertTrue(true);
    }
}

Pest\Plugin::uses(PluginTrait::class);
Pest\Plugin::uses(SecondPluginTrait::class);
