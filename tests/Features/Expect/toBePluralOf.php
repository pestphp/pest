<?php

use PHPUnit\Framework\ExpectationFailedException;

it('passes', function (string $pluralizedWord, string $singularWord) {
    expect($pluralizedWord)->toBePluralOf($singularWord);
})->with([
    'school' => ['schools', 'school'],
    'quiz' => ['quizzes', 'quiz'],
    'tomato' => ['tomatoes', 'tomato'],
    'alias' => ['aliases', 'alias'],
    'bus' => ['buses', 'bus'],
    'loaf' => ['loaves', 'loaf'],
    'parenthesis' => ['parentheses', 'parenthesis'],
    'child' => ['children', 'child'],
    'person' => ['people', 'person'],
    'tooth' => ['teeth', 'tooth'],
    'man' => ['men', 'man'],
    'audio' => ['audio', 'audio'],
    'traffic' => ['traffic', 'traffic'],
    'money' => ['money', 'money'],
    'plankton' => ['plankton', 'plankton'],
    'nucleus' => ['nuclei', 'nucleus'],
    'vertex' => ['vertices', 'vertex'],
    'sheep' => ['sheep', 'sheep'],
    'dog' => ['dogs', 'dog'],
    'relation' => ['relations', 'relation'],
    'shine' => ['shines', 'shine'],
    'command' => ['commands', 'command']
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
