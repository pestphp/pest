<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Concerns\Retrievable;
use Pest\Expectations\EachExpectation;
use Pest\Expectations\HigherOrderExpectation;
use Tests\Fixtures\Arch\ToUseTrait\HasInheritedTrait\ChildClassExtendingParent;
use Tests\Fixtures\Arch\ToUseTrait\HasNestedTrait\NestedTrait;
use Tests\Fixtures\Arch\ToUseTrait\HasTrait\ParentClassWithTrait;
use Tests\Fixtures\Arch\ToUseTrait\HasTrait\TestTraitForInheritance;

test('pass', function (): void {
    expect(HigherOrderExpectation::class)->toUseTrait(Retrievable::class)
        ->and(EachExpectation::class)->not->toUseTrait(Retrievable::class);
});

test('failures', function (): void {
    expect(EachExpectation::class)->toUseTrait('Pest\Concerns\Foo');
})->throws(ArchExpectationFailedException::class);

test('not failures', function (): void {
    expect(HigherOrderExpectation::class)->not->toUseTrait(Retrievable::class);
})->throws(ArchExpectationFailedException::class);

test('trait inheritance - direct usage', function (): void {
    expect(ParentClassWithTrait::class)->toUseTrait(TestTraitForInheritance::class);
});

test('trait inheritance - inherited usage', function (): void {
    expect(ChildClassExtendingParent::class)->toUseTrait(TestTraitForInheritance::class);
});

test('trait inheritance - negative case', function (): void {
    expect(ChildClassExtendingParent::class)->not->toUseTrait('NonExistentTrait');
});

test('nested trait inheritance', function (): void {
    expect(ChildClassExtendingParent::class)->toUseTrait(NestedTrait::class);
});
