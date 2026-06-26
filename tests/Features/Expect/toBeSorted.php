<?php

use Pest\Exceptions\InvalidExpectationValue;
use PHPUnit\Framework\ExpectationFailedException;

test('pass with no $by delegates to ascending', function () {
    expect([1, 2, 3])->toBeSorted();
});

test('pass with no $by delegates to descending', function () {
    expect([3, 2, 1])->toBeSorted(direction: 'desc');
});

test('pass with $by on associative arrays ascending', function () {
    $users = [
        ['name' => 'Anna', 'age' => 20],
        ['name' => 'Ben', 'age' => 25],
        ['name' => 'Cara', 'age' => 30],
    ];
    expect($users)->toBeSorted(by: 'age');
});

test('pass with $by on associative arrays descending', function () {
    $users = [
        ['name' => 'Cara', 'age' => 30],
        ['name' => 'Ben', 'age' => 25],
        ['name' => 'Anna', 'age' => 20],
    ];
    expect($users)->toBeSorted(by: 'age', direction: 'desc');
});

test('pass with $by on object properties', function () {
    $users = [
        (object) ['name' => 'Anna'],
        (object) ['name' => 'Ben'],
        (object) ['name' => 'Cara'],
    ];
    expect($users)->toBeSorted(by: 'name');
});

test('pass with $by on DateTime values', function () {
    $posts = [
        ['created_at' => new DateTime('2024-01-01')],
        ['created_at' => new DateTime('2024-06-01')],
        ['created_at' => new DateTime('2024-12-01')],
    ];
    expect($posts)->toBeSorted(by: 'created_at');
});

test('pass with single element', function () {
    expect([['age' => 25]])->toBeSorted(by: 'age');
});

test('pass with empty array', function () {
    expect([])->toBeSorted(by: 'age');
});

test('failures with $by', function () {
    $users = [
        ['age' => 30],
        ['age' => 20],
    ];
    expect($users)->toBeSorted(by: 'age');
})->throws(ExpectationFailedException::class, 'Array is not sorted by [age] in ascending order.');

test('failures with $by descending', function () {
    $users = [
        ['age' => 20],
        ['age' => 30],
    ];
    expect($users)->toBeSorted(by: 'age', direction: 'desc');
})->throws(ExpectationFailedException::class, 'Array is not sorted by [age] in descending order.');

test('failures with custom message', function () {
    expect([['age' => 30], ['age' => 20]])->toBeSorted(by: 'age', message: 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('failures with invalid direction', function () {
    expect([1, 2, 3])->toBeSorted(direction: 'sideways');
})->throws(InvalidArgumentException::class, 'Direction must be "asc" or "desc", got "sideways".');

test('failures with invalid type', function () {
    expect('not an array')->toBeSorted();
})->throws(InvalidExpectationValue::class, 'Invalid expectation value type. Expected [array]');

test('failures with missing array key', function () {
    expect([['name' => 'Anna']])->toBeSorted(by: 'age');
})->throws(InvalidArgumentException::class, 'Array key [age] does not exist.');

test('failures with missing object property', function () {
    expect([(object) ['name' => 'Anna']])->toBeSorted(by: 'age');
})->throws(InvalidArgumentException::class, 'Property [age] does not exist.');

test('not pass', function () {
    $users = [
        ['age' => 30],
        ['age' => 20],
    ];
    expect($users)->not->toBeSorted(by: 'age');
});

test('not failures', function () {
    $users = [
        ['age' => 20],
        ['age' => 30],
    ];
    expect($users)->not->toBeSorted(by: 'age');
})->throws(ExpectationFailedException::class);
