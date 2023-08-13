<?php

use PHPUnit\Framework\ExpectationFailedException;

it('passes', function (string $pluralizedWord, string $singularWord) {
    expect($pluralizedWord)->toBePluralOf($singularWord);
})->with([
    ['schools', 'school'],
    ['quizzes', 'quiz'],
    ['tomatoes', 'tomato'],
    ['aliases', 'alias'],
    ['buses', 'bus'],
    ['loaves', 'loaf'],
    ['parentheses', 'parenthesis'],
    ['children', 'child'],
    ['people', 'person'],
    ['teeth', 'tooth'],
    ['men', 'man'],
    ['audio', 'audio'],
    ['traffic', 'traffic'],
    ['money', 'money'],
    ['plankton', 'plankton'],
    ['nuclei', 'nucleus'],
    ['vertices', 'vertex'],
    ['sheep', 'sheep'],
    ['dogs', 'dog'],
    ['relations', 'relation'],
    ['shines', 'shine'],
    ['commands', 'command'],
]);

test('failures', function () {
    expect('dog')->toBePluralOf('dogs');
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('dog')->toBePluralOf('dogs', 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('dogs')->not->toBePluralOf('dog');
})->throws(ExpectationFailedException::class);
