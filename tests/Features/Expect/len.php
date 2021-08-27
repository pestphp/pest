<?php

it('returns uncountable BadMethodCallException', function ($value) {
    expect($value)->len()->toEqual(1);
})->with([1, 1.5, true, null])->throws(BadMethodCallException::class, "Expectation value's length is uncountable");

it('properly returns string length', function ($value) {
    expect($value)->len()->toEqual(9);
})->with(['Sollefteå', 'Guimarães', 'Ιεράπετρα', 'PT-BR 🇵🇹🇧🇷😎']);

it('properly returns an array length', function () {
    expect(['pest', 'is', 'the', 'best'])->len()->toEqual(4);
});

it('properly returns an Eloquent collection length', function () {
    expect(collect(['pest', 'is', 'the', 'best']))->len()->toEqual(4);
});

test('properly returns an object length', function () {
    expect((object) ['pest', 'is', 'the', 'best'])->len()->toEqual(4);
});
