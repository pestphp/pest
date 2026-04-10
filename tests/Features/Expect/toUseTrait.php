<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;

test('pass', function () {
    expect('Pest\Expectations\HigherOrderExpectation')->toUseTrait('Pest\Concerns\Retrievable')
        ->and('Pest\Expectations\EachExpectation')->not->toUseTrait('Pest\Concerns\Retrievable');
});

test('failures', function () {
    expect('Pest\Expectations\EachExpectation')->toUseTrait('Pest\Concerns\Foo');
})->throws(ArchExpectationFailedException::class);

test('not failures', function () {
    expect('Pest\Expectations\HigherOrderExpectation')->not->toUseTrait('Pest\Concerns\Retrievable');
})->throws(ArchExpectationFailedException::class);

test('trait inheritance - direct usage', function () {
    expect('Tests\Fixtures\Arch\ToUseTrait\HasTrait\ParentClassWithTrait')->toUseTrait('Tests\Fixtures\Arch\ToUseTrait\HasTrait\TestTraitForInheritance');
});

test('trait inheritance - inherited usage', function () {
    expect('Tests\Fixtures\Arch\ToUseTrait\HasInheritedTrait\ChildClassExtendingParent')->toUseTrait('Tests\Fixtures\Arch\ToUseTrait\HasTrait\TestTraitForInheritance');
});

test('trait inheritance - negative case', function () {
    expect('Tests\Fixtures\Arch\ToUseTrait\HasInheritedTrait\ChildClassExtendingParent')->not->toUseTrait('NonExistentTrait');
});

test('nested trait inheritance', function () {
    expect('Tests\Fixtures\Arch\ToUseTrait\HasInheritedTrait\ChildClassExtendingParent')->toUseTrait('Tests\Fixtures\Arch\ToUseTrait\HasNestedTrait\NestedTrait');
});
