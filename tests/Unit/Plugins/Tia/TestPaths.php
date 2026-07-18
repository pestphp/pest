<?php

declare(strict_types=1);

use Pest\Plugins\Tia\TestPaths;

describe('isTestFile()', function (): void {
    it('matches a standard test file under a configured directory', function (): void {
        $paths = new TestPaths(
            directories: ['tests/Unit', 'tests/Feature'],
            files: [],
            suffixes: ['Test.php'],
        );

        expect($paths->isTestFile('tests/Unit/ExampleTest.php'))->toBeTrue()
            ->and($paths->isTestFile('tests/Feature/UserTest.php'))->toBeTrue();
    });

    it('does not match a non-test file that shares the directory', function (): void {
        $paths = new TestPaths(
            directories: ['tests/Unit'],
            files: [],
            suffixes: ['Test.php'],
        );

        expect($paths->isTestFile('tests/Unit/Helper.php'))->toBeFalse();
    });

    it('does not match files outside the configured directories', function (): void {
        $paths = new TestPaths(
            directories: ['tests/Unit'],
            files: [],
            suffixes: ['Test.php'],
        );

        expect($paths->isTestFile('app/Models/UserTest.php'))->toBeFalse();
    });

    it('matches an explicitly listed file', function (): void {
        $paths = new TestPaths(
            directories: [],
            files: ['tests/Pest.php'],
            suffixes: ['Test.php'],
        );

        expect($paths->isTestFile('tests/Pest.php'))->toBeTrue();
    });

    it('honours a bare .php suffix', function (): void {
        $paths = new TestPaths(
            directories: ['tests'],
            files: [],
            suffixes: ['.php'],
        );

        expect($paths->isTestFile('tests/Unit/ExampleTest.php'))->toBeTrue()
            ->and($paths->isTestFile('tests/Unit/Helper.php'))->toBeTrue();
    });

    // Regression: a suffix of "Test.php" must match "ExampleTest.php". A previous
    // normalisation prepended a dot (".Test.php"), which matched nothing and left
    // edited tests looking unchanged to TIA, replaying stale results.
    it('does not require a dot before the suffix', function (): void {
        $paths = new TestPaths(
            directories: ['tests/Unit'],
            files: [],
            suffixes: ['.Test.php'],
        );

        expect($paths->isTestFile('tests/Unit/ExampleTest.php'))->toBeFalse();
    });
});
