<?php

if (class_exists(NunoMaduro\Collision\Provider::class)) {
    (new NunoMaduro\Collision\Provider())->register();
}

trait PluginTrait
{
    function assertPluginTraitGotRegistered(): void
    {
        assertTrue(true);
    }
}

Pest\Plugin::uses(PluginTrait::class);
