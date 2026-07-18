<?php

use Pest\Plugins\Tia\Lockfiles\PackageLock;

$lock = (fn (array $packages, ?string $name = null): string => json_encode([
    'name' => $name ?? 'app',
    'lockfileVersion' => 3,
    'packages' => $packages,
]));

it('applies only to package-lock.json', function (): void {
    $handler = new PackageLock;

    expect($handler->applies('package-lock.json'))->toBeTrue()
        ->and($handler->applies('pnpm-lock.yaml'))->toBeFalse()
        ->and($handler->applies('yarn.lock'))->toBeFalse();
});

it('returns null for contents that are not an npm lockfile', function (): void {
    $handler = new PackageLock;

    expect($handler->fingerprint('not json'))->toBeNull()
        ->and($handler->fingerprint('{"lockfileVersion":1}'))->toBeNull();
});

it('is stable when the directory-derived top-level name changes', function () use ($lock): void {
    $handler = new PackageLock;
    $packages = ['node_modules/react' => ['version' => '19.0.0']];

    expect($handler->fingerprint($lock($packages, 'app-a')))
        ->toBe($handler->fingerprint($lock($packages, 'app-b')));
});

it('ignores platform-specific native binaries added or dropped per OS', function () use ($lock): void {
    $handler = new PackageLock;

    $mac = $lock([
        'node_modules/react' => ['version' => '19.0.0'],
        'node_modules/@rollup/rollup-darwin-arm64' => [
            'version' => '4.9.5', 'os' => ['darwin'], 'cpu' => ['arm64'], 'optional' => true,
        ],
    ], 'app');

    $linux = $lock([
        'node_modules/react' => ['version' => '19.0.0'],
        'node_modules/@rollup/rollup-linux-x64-gnu' => [
            'version' => '4.52.1', 'os' => ['linux'], 'cpu' => ['x64'], 'optional' => true,
        ],
    ], 'app');

    expect($handler->fingerprint($mac))->toBe($handler->fingerprint($linux));
});

it('ignores libc-constrained variants', function () use ($lock): void {
    $handler = new PackageLock;

    $gnu = $lock([
        'node_modules/lightningcss-linux-x64-gnu' => [
            'version' => '1.32.0', 'os' => ['linux'], 'cpu' => ['x64'], 'libc' => ['glibc'], 'optional' => true,
        ],
    ]);

    expect($handler->fingerprint($gnu))->toBe($handler->fingerprint($lock([])));
});

it('ignores runtime deps bundled beneath a platform-specific package', function () use ($lock): void {
    $handler = new PackageLock;

    $withoutBundled = $lock([
        'node_modules/react' => ['version' => '19.0.0'],
        'node_modules/@rolldown/binding-wasm32-wasi' => [
            'version' => '1.0.0', 'cpu' => ['wasm32'], 'optional' => true,
        ],
    ]);

    $withBundled = $lock([
        'node_modules/react' => ['version' => '19.0.0'],
        'node_modules/@rolldown/binding-wasm32-wasi' => [
            'version' => '1.0.0', 'cpu' => ['wasm32'], 'optional' => true,
        ],
        'node_modules/@rolldown/binding-wasm32-wasi/node_modules/@emnapi/core' => ['version' => '1.4.0'],
    ]);

    expect($handler->fingerprint($withoutBundled))->toBe($handler->fingerprint($withBundled));
});

it('detects a real dependency version change', function () use ($lock): void {
    $handler = new PackageLock;

    $before = $lock(['node_modules/react' => ['version' => '19.0.0']]);
    $after = $lock(['node_modules/react' => ['version' => '19.1.0']]);

    expect($handler->fingerprint($before))->not->toBe($handler->fingerprint($after));
});

it('detects an added or removed non-platform dependency', function () use ($lock): void {
    $handler = new PackageLock;

    $before = $lock(['node_modules/react' => ['version' => '19.0.0']]);
    $after = $lock([
        'node_modules/react' => ['version' => '19.0.0'],
        'node_modules/vue' => ['version' => '3.5.0'],
    ]);

    expect($handler->fingerprint($before))->not->toBe($handler->fingerprint($after));
});

it('is independent of package ordering', function () use ($lock): void {
    $handler = new PackageLock;

    $a = $lock([
        'node_modules/a' => ['version' => '1.0.0'],
        'node_modules/b' => ['version' => '2.0.0'],
    ]);
    $b = $lock([
        'node_modules/b' => ['version' => '2.0.0'],
        'node_modules/a' => ['version' => '1.0.0'],
    ]);

    expect($handler->fingerprint($a))->toBe($handler->fingerprint($b));
});
