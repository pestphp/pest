<?php

use Pest\Actions\ValidatesConfiguration;
use Pest\Exceptions\AttributeNotSupportedYet;
use Pest\Exceptions\FileOrFolderNotFound;

it('throws exception when configuration not found', function () {
    $this->expectException(FileOrFolderNotFound::class);

    ValidatesConfiguration::in([
        'configuration' => 'foo',
    ]);
});

it('throws exception when `process isolation` is true', function () {
    $this->expectException(AttributeNotSupportedYet::class);
    $this->expectExceptionMessage('The PHPUnit attribute `processIsolation` with value `true` is not supported yet.');

    $filename = implode(DIRECTORY_SEPARATOR, [
        dirname(__DIR__, 2),
        'Fixtures',
        'phpunit-in-isolation.xml',
    ]);

    ValidatesConfiguration::in([
        'configuration' => $filename,
    ]);
});

it('do not throws exception when `process isolation` is false', function () {
    $filename = implode(DIRECTORY_SEPARATOR, [
        dirname(__DIR__, 2),
        'Fixtures',
        'phpunit-not-in-isolation.xml',
    ]);

    ValidatesConfiguration::in([
        'configuration' => $filename,
    ]);

    assertTrue(true);
});
