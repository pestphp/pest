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

    expect(iterator_to_array(Datasets::get('foo')()))->toBe([[1]]);
});

it('sets arrays', function () {
    Datasets::set('bar', [[2]]);

    expect(Datasets::get('bar'))->toBe([[2]]);
});

it('gets bound to test case object', function () {
    $this->assertTrue(true);
})->with([['a'], ['b']]);

test('it truncates the description', function () {
    expect(true)->toBe(true);
    // it gets tested by the integration test
})->with([str_repeat('Fooo', 10000000)]);

$state       = new stdClass();
$state->text = '';

$datasets = [[1], [2]];

test('lazy datasets', function ($text) use ($state, $datasets) {
    $state->text .= $text;
    expect(in_array([$text], $datasets))->toBe(true);
})->with($datasets);

test('lazy datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('12');
});

$state->text = '';

test('eager datasets', function ($text) use ($state, $datasets) {
    $state->text .= $text;
    expect($datasets)->toContain([$text]);
})->with(function () use ($datasets) {
    return $datasets;
});

test('eager datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('1212');
});

test('lazy registered datasets', function ($text) use ($state, $datasets) {
    $state->text .= $text;
    expect($datasets)->toContain([$text]);
})->with('numbers.array');

test('lazy registered datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('121212');
});

test('eager registered datasets', function ($text) use ($state, $datasets) {
    $state->text .= $text;
    expect($datasets)->toContain([$text]);
})->with('numbers.closure');

test('eager registered datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('12121212');
});

test('eager wrapped registered datasets', function ($text) use ($state, $datasets) {
    $state->text .= $text;
    expect($datasets)->toContain([$text]);
})->with('numbers.closure.wrapped');

test('eager registered wrapped datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('1212121212');
});

test('named datasets', function ($text) use ($state, $datasets) {
    $state->text .= $text;
    expect($datasets)->toContain([$text]);
})->with([
    'one' => [1],
    'two' => [2],
]);

test('named datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('121212121212');
});

class Bar
{
    public $name = 1;
}

$namedDatasets = [
    new Bar(),
];

test('lazy named datasets', function ($text) use ($state, $datasets) {
    expect(true)->toBeTrue();
})->with($namedDatasets);

$counter = 0;

it('creates unique test case names', function (string $name, Plugin $plugin, bool $bool) use (&$counter) {
    expect(true)->toBeTrue();
    $counter++;
})->with([
    ['Name 1', new Plugin(), true],
    ['Name 1', new Plugin(), true],
    ['Name 1', new Plugin(), false],
    ['Name 2', new Plugin(), false],
    ['Name 2', new Plugin(), true],
    ['Name 1', new Plugin(), true],
]);

it('creates unique test case names - count', function () use (&$counter) {
    expect($counter)->toBe(6);
});
