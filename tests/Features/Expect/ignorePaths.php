<?php

declare(strict_types=1);

use Pest\Arch\Exceptions\ArchExpectationFailedException;

test('single ignored path', function () {
    expect('Tests\Fixtures\Arch\IgnorePaths\Single')
        ->ignorePaths(['database/migrations'])
        ->toUseStrictTypes();
});

test('multiple ignored paths', function () {
    expect('Tests\Fixtures\Arch\IgnorePaths\Multiple')
        ->ignorePaths(['database/migrations', 'vendor'])
        ->toUseStrictTypes();
});

test('nested directory ignored', function () {
    expect('Tests\Fixtures\Arch\IgnorePaths\Single')
        ->ignorePaths(['database'])
        ->toUseStrictTypes();
});

test('partial path matching', function () {
    expect('Tests\Fixtures\Arch\IgnorePaths\Single')
        ->ignorePaths(['migrations'])
        ->toUseStrictTypes();
});

test('windows path separators', function () {
    expect('Tests\Fixtures\Arch\IgnorePaths\Single')
        ->ignorePaths(['database\\migrations'])
        ->toUseStrictTypes();
});

test('non-ignored files still analyzed', function () {
    expect('Tests\Fixtures\Arch\IgnorePaths\Dirty')
        ->ignorePaths(['database/migrations'])
        ->toUseStrictTypes();
})->throws(ArchExpectationFailedException::class);

test('violations caught in all paths without ignorePaths', function () {
    expect('Tests\Fixtures\Arch\IgnorePaths\Single')
        ->toUseStrictTypes();
})->throws(ArchExpectationFailedException::class);

test('chainable with not', function () {
    expect('Tests\Fixtures\Arch\IgnorePaths\Single')
        ->ignorePaths(['database/migrations'])
        ->not->toBeAbstract();
});
