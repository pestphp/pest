<?php

declare(strict_types=1);

use Pest\Plugins\Tia\SourceScope;

describe('contains()', function (): void {
    it('excludes only the files matching the suffix of an excluded directory', function (): void {
        $scope = new SourceScope(
            ['/project/src'],
            [['path' => '/project/src', 'prefix' => '', 'suffix' => 'Test.php']],
        );

        expect($scope->contains('/project/src/Calculator.php'))->toBeTrue()
            ->and($scope->contains('/project/src/CalculatorTest.php'))->toBeFalse();
    });

    it('excludes only the files matching the prefix of an excluded directory', function (): void {
        $scope = new SourceScope(
            ['/project/src'],
            [['path' => '/project/src', 'prefix' => 'Legacy', 'suffix' => '.php']],
        );

        expect($scope->contains('/project/src/Calculator.php'))->toBeTrue()
            ->and($scope->contains('/project/src/LegacyCalculator.php'))->toBeFalse();
    });

    it('excludes every file of an excluded directory without a prefix or suffix', function (): void {
        $scope = new SourceScope(
            ['/project'],
            [['path' => '/project/storage/framework', 'prefix' => '', 'suffix' => '']],
        );

        expect($scope->contains('/project/app/Calculator.php'))->toBeTrue()
            ->and($scope->contains('/project/storage/framework/views/compiled.php'))->toBeFalse();
    });
});
