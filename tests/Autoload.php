<?php

declare(strict_types=1);

use Pest\Plugin;

trait PluginTrait
{
    public function assertPluginTraitGotRegistered(): void
    {
        expect(true)->toBeTrue();
    }
}

trait SecondPluginTrait
{
    public function assertSecondPluginTraitGotRegistered(): void
    {
        expect(true)->toBeTrue();
    }
}

Plugin::uses(PluginTrait::class);
Plugin::uses(SecondPluginTrait::class);

function _assertThat(): void
{
    expect(true)->toBeTrue();
}
