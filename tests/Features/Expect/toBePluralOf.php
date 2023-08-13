<?php

use PHPUnit\Framework\ExpectationFailedException;

test('passes', function (string $pluralizedWord, string $singularWord) {
    expect($pluralizedWord)->toBePluralOf($singularWord);
})->with([
    ['schools', 'school'],
    ['quizzes', 'quiz'],
    ['tomatoes', 'tomato'],
    ['aliases', 'alias'],
    ['buses', 'bus'],
    ['loaves', 'loaf'],
    ['potatoes', 'potato'],
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

test('failures', function (string $pluralizedWord, string $singularWord) {
    expect($pluralizedWord)->toBePluralOf($singularWord);
})->with([
    ['school', 'school'],
    ['quiz', 'quiz'],
    ['tomato', 'tomato'],
    ['alias', 'alias'],
    ['bus', 'bus'],
    ['loaf', 'loaf'],
    ['potato', 'potato'],
    ['child', 'child'],
    ['person', 'person'],
    ['tooth', 'tooth'],
    ['man', 'man'],
    ['nucleus', 'nucleus'],
    ['vertex', 'vertex'],
    ['dog', 'dog'],
    ['relation', 'relation'],
    ['shine', 'shine'],
    ['command', 'command'],
])->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect('dog')->toBePluralOf('dogs', 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect('dogs')->not->toBePluralOf('dog');
})->throws(ExpectationFailedException::class);
