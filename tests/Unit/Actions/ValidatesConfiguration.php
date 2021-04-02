<?php

use Pest\Actions\ValidatesConfiguration;
use Pest\Exceptions\FileOrFolderNotFound;

it('throws exception when configuration not found', function () {
    $this->expectException(FileOrFolderNotFound::class);

    ValidatesConfiguration::in([
        'configuration' => 'foo',
    ]);
});

it('do not throws exception when `process isolation` is true', function () {
    $filename = implode(DIRECTORY_SEPARATOR, [
        dirname(__DIR__, 2),
        'Fixtures',
        'phpunit-in-isolation.xml',
    ]);

    ValidatesConfiguration::in([
        'configuration' => $filename,
    ]);

    expect(true)->toBeTrue();
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

    expect(true)->toBeTrue();
});
