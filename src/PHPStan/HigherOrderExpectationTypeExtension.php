<?php

declare(strict_types=1);

namespace Pest\PHPStan;

use Pest\Expectations\HigherOrderExpectation;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Prevents native declared properties of HigherOrderExpectation (like $original,
 * $expectation, $opposite, $shouldReset) from being incorrectly resolved as
 * higher-order value property accesses by downstream ExpressionTypeResolverExtensions.
 *
 * This extension must be registered BEFORE the peststan HigherOrderExpectationTypeExtension.
 *
 * @internal
 */
final readonly class HigherOrderExpectationTypeExtension implements ExpressionTypeResolverExtension
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

    public function getType(Expr $expr, Scope $scope): ?Type
    {
        if (! $expr instanceof PropertyFetch || ! $expr->name instanceof Identifier) {
            return null;
        }

        $varType = $scope->getType($expr->var);

        if (! (new ObjectType(HigherOrderExpectation::class))->isSuperTypeOf($varType)->yes()) {
            return null;
        }

        if (! $this->reflectionProvider->hasClass(HigherOrderExpectation::class)) {
            return null;
        }

        $propertyName = $expr->name->name;
        $classReflection = $this->reflectionProvider->getClass(HigherOrderExpectation::class);

        if (! $classReflection->hasNativeProperty($propertyName)) {
            return null;
        }

        return $varType->getProperty($propertyName, $scope)->getReadableType();
    }
}
