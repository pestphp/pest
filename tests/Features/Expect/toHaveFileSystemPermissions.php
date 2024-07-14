<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;

test('pass', function () {
    expect(Pest\Preset::class)->toHaveFileSystemPermissions('0644')
        ->and('Pest')->not->toHaveFileSystemPermissions('0777');
});

test('failures', function () {
    expect(Pest\Preset::class)->toHaveFileSystemPermissions('0755');
})->throws(ArchExpectationFailedException::class, "Expecting 'src/Preset.php' permissions to be [0755].");

test('not failures', function () {
    expect(Pest\Preset::class)->not->toHaveFileSystemPermissions('0644');
})->throws(ArchExpectationFailedException::class, "Expecting 'src/Preset.php' permissions not to be [0644].");
