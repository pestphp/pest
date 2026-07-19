<?php

declare(strict_types=1);

use Pest\Rector\Rules\UseToMatchArrayRector;
use Pest\Rector\Set\PestSetList;
use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveDuplicatedReturnSelfDocblockRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveParentDelegatingConstructorRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveReturnTagIncompatibleWithNativeTypeRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedConstructorParamRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessUnionReturnDocblockRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\NarrowObjectReturnTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnNeverTypeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSets([
        PestSetList::CODING_STYLE,
    ])
    ->withSkip([
        __DIR__.'/src/Plugins/Parallel/Paratest/WrapperRunner.php',
        __DIR__.'/tests/Fixtures/Arch',
        ReturnNeverTypeRector::class,
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class,
        NarrowObjectReturnTypeRector::class,
        RemoveParentDelegatingConstructorRector::class,
        RemoveDuplicatedReturnSelfDocblockRector::class,
        RemoveUselessUnionReturnDocblockRector::class,
        RemoveReturnTagIncompatibleWithNativeTypeRector::class => [
            __DIR__.'/src/Expectations/HigherOrderExpectation.php',
        ],
        // Merges unrelated expectations into a single `toMatchArray()`, turning
        // `toContain()` into exact matches, dropping `->not`, and mistaking a
        // `toBeTrue()` failure message for an expected value. Unsafe here.
        UseToMatchArrayRector::class,
        // Test fixtures rely on "unused" constructors, params and properties
        // (resolved via the container or read through reflection), so the
        // dead-code and return-type rules below must not touch the test suite.
        RemoveEmptyClassMethodRector::class => [
            __DIR__.'/tests',
        ],
        RemoveUnusedConstructorParamRector::class => [
            __DIR__.'/tests',
        ],
        RemoveUnusedPrivatePropertyRector::class => [
            __DIR__.'/tests',
        ],
        AddArrowFunctionReturnTypeRector::class => [
            __DIR__.'/tests',
        ],
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withPhpSets();
