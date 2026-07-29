<?php

declare(strict_types=1);

use Pest\Plugins\Tia\Configuration;
use Pest\Plugins\Tia\Storage;

afterEach(function (): void {
    Storage::useDirectory(null);
});

it('uses a project-relative configured directory', function (): void {
    (new Configuration)->directory('.pest/tia');

    expect(Storage::tempDir('/project'))->toBe('/project'.DIRECTORY_SEPARATOR.'.pest/tia');
});

it('uses an absolute configured directory', function (): void {
    (new Configuration)->directory('/tmp/pest-tia');

    expect(Storage::tempDir('/project'))->toBe('/tmp/pest-tia');
});
