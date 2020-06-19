<?php

use Pest\Datasets;
use Pest\Exceptions\DatasetAlreadyExist;
use Pest\Exceptions\DatasetDoesNotExist;
use Pest\Plugin;

it('throws exception if dataset does not exist', function () {
    $this->expectException(DatasetDoesNotExist::class);
    $this->expectExceptionMessage("A dataset with the name `first` does not exist. You can create it using `dataset('first', ['a', 'b']);`.");
    Datasets::get('first');
});

it('throws exception if dataset already exist', function () {
    Datasets::set('second', [[]]);
    $this->expectException(DatasetAlreadyExist::class);
    $this->expectExceptionMessage('A dataset with the name `second` already exist.');
    Datasets::set('second', [[]]);
});

it('sets closures', function () {
    Datasets::set('foo', function () {
        yield [1];
    });

    assertEquals([[1]], iterator_to_array(Datasets::get('foo')()));
});

it('sets arrays', function () {
    Datasets::set('bar', [[2]]);

    assertEquals([[2]], Datasets::get('bar'));
});

it('gets bound to test case object', function () {
    $this->assertTrue(true);
})->with([['a'], ['b']]);

test('it truncates the description', function () {
    assertTrue(true);
    // it gets tested by the integration test
})->with([str_repeat('Fooo', 10000000)]);

$state       = new stdClass();
$state->text = '';

$datasets = [[1], [2]];

test('lazy datasets', function ($text) use ($state, $datasets) {
    $state->text .= $text;
    assertTrue(in_array([$text], $datasets));
})->with($datasets);

test('lazy datasets did the job right', function () use ($state) {
    assertEquals('12', $state->text);
});

$state->text = '';

test('eager datasets', function ($text) use ($state, $datasets) {
    $state->text .= $text;
    assertTrue(in_array([$text], $datasets));
})->with(function () use ($datasets) {
    return $datasets;
});

test('eager datasets did the job right', function () use ($state) {
    assertEquals('1212', $state->text);
});

test('lazy registered datasets', function ($text) use ($state, $datasets) {
    $state->text .= $text;
    assertTrue(in_array([$text], $datasets));
})->with('numbers.array');

test('lazy registered datasets did the job right', function () use ($state) {
    assertEquals('121212', $state->text);
});

test('eager registered datasets', function ($text) use ($state, $datasets) {
    $state->text .= $text;
    assertTrue(in_array([$text], $datasets));
})->with('numbers.closure');

test('eager registered datasets did the job right', function () use ($state) {
    assertEquals('12121212', $state->text);
});

test('eager wrapped registered datasets', function ($text) use ($state, $datasets) {
    $state->text .= $text;
    assertTrue(in_array([$text], $datasets));
})->with('numbers.closure.wrapped');

test('eager registered wrapped datasets did the job right', function () use ($state) {
    assertEquals('1212121212', $state->text);
});

class Bar
{
    public $name = 1;
}

$namedDatasets = [
    new Bar(),
];

test('lazy named datasets', function ($text) use ($state, $datasets) {
    assertTrue(true);
})->with($namedDatasets);

$counter = 0;

it('creates unique test case names', function (string $name, Plugin $plugin, bool $bool) use (&$counter) {
    assertTrue(true);
    $counter++;
})->with([
    ['Name 1', new Plugin(), true],
    ['Name 1', new Plugin(), true],
    ['Name 1', new Plugin(), false],
]);

it('creates unique test case names - count', function () use (&$counter) {
    assertEquals(3, $counter);
});
