<?php

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

function _assertThat()
{
    expect(true)->toBeTrue();
}
