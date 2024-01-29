<?php

declare(strict_types=1);

use Pest\Bootstrappers\BootOverrides;

test('versions', function (string $vendorPath, string $expectedHash) {
    expect(hash_file('sha256', $vendorPath))->toBe($expectedHash);
})->with(function () {
    foreach (BootOverrides::FILES as $hash => $file) {
        $path = implode(DIRECTORY_SEPARATOR, [
            dirname(__DIR__, 2),
            'vendor/phpunit/phpunit/src',
            $file,
        ]);
        yield $file => [$path, $hash];
    }
});
