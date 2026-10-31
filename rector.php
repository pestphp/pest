<?php

declare(strict_types=1);

use Pest\Rector\Rules\UseToMatchArrayRector;
use Pest\Rector\Set\PestSetList;
use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;
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
        __DIR__.'/tests/Fixtures/Suites',
        ReturnNeverTypeRector::class,
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class,
        NarrowObjectReturnTypeRector::class,
        RemoveParentDelegatingConstructorRector::class,
        RemoveDuplicatedReturnSelfDocblockRector::class,
        RemoveUselessUnionReturnDocblockRector::class,
        RemoveReturnTagIncompatibleWithNativeTypeRector::class => [
            __DIR__.'/src/Expectations/HigherOrderExpectation.php',
        ],
        UseToMatchArrayRector::class,
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
        IssetOnPropertyObjectToPropertyExistsRector::class => [
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
