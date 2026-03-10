<?php

use Pest\Plugin;

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

Plugin::uses(PluginTrait::class);
Plugin::uses(SecondPluginTrait::class);

function _assertThat()
{
    expect(true)->toBeTrue();
}
