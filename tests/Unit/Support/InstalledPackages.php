<?php

declare(strict_types=1);

use Pest\Support\InstalledPackages;

$project = [
    'root' => ['install_path' => '/project/vendor/composer/../../'],
    'versions' => [
        'pestphp/pest' => ['dev_requirement' => true],
        'symfony/console' => ['dev_requirement' => false],
    ],
];

$pharTool = [
    'root' => ['install_path' => 'phar:///tools/dump.phar/vendor/composer/../../'],
    'versions' => [
        'laravel/framework' => ['dev_requirement' => false],
    ],
];

it('finds a package that the project installs', function () use ($project, $pharTool): void {
    $packages = new InstalledPackages([$pharTool, $project]);

    expect($packages->contains('symfony/console'))->toBeTrue()
        ->and($packages->contains('pestphp/pest'))->toBeTrue();
});

it('ignores a package that only a tool bundled in a PHAR installs', function () use ($project, $pharTool): void {
    $packages = new InstalledPackages([$pharTool, $project]);

    expect($packages->contains('laravel/framework'))->toBeFalse()
        ->and($packages->contains('laravel/framework', false))->toBeFalse();
});

it('leaves out a development requirement when asked', function () use ($project): void {
    $packages = new InstalledPackages([$project]);

    expect($packages->contains('pestphp/pest', false))->toBeFalse()
        ->and($packages->contains('symfony/console', false))->toBeTrue();
});

it('reads an installed set that has no install path', function (): void {
    $packages = new InstalledPackages([
        ['root' => [], 'versions' => ['laravel/framework' => ['dev_requirement' => false]]],
    ]);

    expect($packages->contains('laravel/framework'))->toBeTrue()
        ->and($packages->contains('symfony/console'))->toBeFalse();
});

it('reads the packages that Composer loaded', function (): void {
    expect(InstalledPackages::has('pestphp/pest'))->toBeTrue()
        ->and(InstalledPackages::has('pestphp/pest-plugin-that-does-not-exist'))->toBeFalse();
});

it('is the only class of src that reads Composer packages', function (): void {
    $source = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'src';
    $readers = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() === 'php' && str_contains((string) file_get_contents($file->getPathname()), 'InstalledVersions')) {
            $readers[] = substr($file->getPathname(), strlen($source) + 1);
        }
    }

    expect($readers)->toBe(['Support'.DIRECTORY_SEPARATOR.'InstalledPackages.php']);
});
