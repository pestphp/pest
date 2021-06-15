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

test('lazy named datasets', function ($text) {
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

$datasets_a = [[1], [2]];
$datasets_b = [[3], [4]];

test('lazy multiple datasets', function ($text_a, $text_b) use ($state, $datasets_a, $datasets_b) {
    $state->text .= $text_a . $text_b;
    expect($datasets_a)->toContain([$text_a]);
    expect($datasets_b)->toContain([$text_b]);
})->with($datasets_a, $datasets_b);

test('lazy multiple datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('12121212121213142324');
});

$state->text = '';

test('eager multiple datasets', function ($text_a, $text_b) use ($state, $datasets_a, $datasets_b) {
    $state->text .= $text_a . $text_b;
    expect($datasets_a)->toContain([$text_a]);
    expect($datasets_b)->toContain([$text_b]);
})->with(function () use ($datasets_a) {
    return $datasets_a;
})->with(function () use ($datasets_b) {
    return $datasets_b;
});

test('eager multiple datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('1212121212121314232413142324');
});

test('lazy registered multiple datasets', function ($text_a, $text_b) use ($state, $datasets) {
    $state->text .= $text_a . $text_b;
    expect($datasets)->toContain([$text_a]);
    expect($datasets)->toContain([$text_b]);
})->with('numbers.array')->with('numbers.array');

test('lazy registered multiple datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('121212121212131423241314232411122122');
});

test('eager registered multiple datasets', function ($text_a, $text_b) use ($state, $datasets) {
    $state->text .= $text_a . $text_b;
    expect($datasets)->toContain([$text_a]);
    expect($datasets)->toContain([$text_b]);
})->with('numbers.array')->with('numbers.closure');

test('eager registered multiple datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('12121212121213142324131423241112212211122122');
});

test('eager wrapped registered multiple datasets', function ($text_a, $text_b) use ($state, $datasets) {
    $state->text .= $text_a . $text_b;
    expect($datasets)->toContain([$text_a]);
    expect($datasets)->toContain([$text_b]);
})->with('numbers.closure.wrapped')->with('numbers.closure');

test('eager wrapped registered multiple datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('1212121212121314232413142324111221221112212211122122');
});

test('named multiple datasets', function ($text_a, $text_b) use ($state, $datasets_a, $datasets_b) {
    $state->text .= $text_a . $text_b;
    expect($datasets_a)->toContain([$text_a]);
    expect($datasets_b)->toContain([$text_b]);
})->with([
    'one' => [1],
    'two' => [2],
])->with([
    'three' => [3],
    'four'  => [4],
]);

test('named multiple datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('121212121212131423241314232411122122111221221112212213142324');
});

test('more than two datasets', function ($text_a, $text_b, $text_c) use ($state, $datasets_a, $datasets_b) {
    $state->text .= $text_a . $text_b . $text_c;
    expect($datasets_a)->toContain([$text_a]);
    expect($datasets_b)->toContain([$text_b]);
    expect([5, 6])->toContain($text_c);
})->with($datasets_a, $datasets_b)->with([5, 6]);

test('more than two datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('121212121212131423241314232411122122111221221112212213142324135136145146235236245246');
});

$people = [
    'Nuno'   => [
        'name'    => 'Nuno',
        'country' => 'Portugal',
        'role'    => 'Owner',
    ],
    'Fabio'  => [
        'name'    => 'Fabio',
        'country' => 'Italy',
        'role'    => 'Member',
    ],
    'Oliver' => [
        'name'    => 'Oliver',
        'country' => 'Denmark',
        'role'    => 'Maintainer',
    ],
];
dataset('people', $people);

it('can generate test with extracted dataset', function ($role, $name) use ($people) {
    expect($people)->toHaveKey($name);
    expect($name)->toEqual($people[$name]['name']);
    expect($role)->toEqual($people[$name]['role']);
})->with('people:role,name');

it('extracts keys from lazy dataset', function () {
    Datasets::set('dataset', [
        [
            'foo' => 1,
            'bar' => 2,
            'baz' => 3,
        ],
        [
            'foo' => 4,
            'bar' => 5,
            'baz' => 6,
        ],
    ]);

    expect(Datasets::get('dataset:baz,bar'))->toMatchArray([
        [
            'baz' => 3,
            'bar' => 2,
        ],
        [
            'baz' => 6,
            'bar' => 5,
        ],
    ]);

    Datasets::unset('dataset');
});

it('extracts keys from eager dataset', function () {
    Datasets::set('dataset', function () {
        return [
            [
                'foo' => 1,
                'bar' => 2,
                'baz' => 3,
            ],
            [
                'foo' => 4,
                'bar' => 5,
                'baz' => 6,
            ],
        ];
    });

    expect(Datasets::get('dataset:baz,foo'))->toMatchArray([
        [
            'baz' => 3,
            'foo' => 1,
        ],
        [
            'baz' => 6,
            'foo' => 4,
        ],
    ]);

    Datasets::unset('dataset');
});

it('trims extract dataset keys', function () {
    Datasets::set('dataset', [
        [
            'foo' => 1,
            'bar' => 2,
            'baz' => 3,
        ],
        [
            'foo' => 4,
            'bar' => 5,
            'baz' => 6,
        ],
    ]);

    expect(Datasets::get(' dataset : baz , bar '))->toMatchArray([
        [
            'baz' => 3,
            'bar' => 2,
        ],
        [
            'baz' => 6,
            'bar' => 5,
        ],
    ]);

    Datasets::unset('dataset');
});

it('replaces missing keys with null', function () {
    Datasets::set('dataset', [
        [
            'foo' => 1,
            'bar' => 2,
            'baz' => 3,
        ],
        [
            'foo' => 4,
            'bar' => 5,
            'baz' => 6,
        ],
    ]);

    expect(Datasets::get('dataset:baz,bar,wee'))->toMatchArray([
        [
            'baz' => 3,
            'bar' => 2,
            'wee' => null,
        ],
        [
            'baz' => 6,
            'bar' => 5,
            'wee' => null,
        ],
    ]);

    Datasets::unset('dataset');
});

it('ignores empty keys', function () {
    Datasets::set('dataset', [
        [
            'foo' => 1,
            'bar' => 2,
            'baz' => 3,
        ],
        [
            'foo' => 4,
            'bar' => 5,
            'baz' => 6,
        ],
    ]);

    expect(Datasets::get('dataset:baz, ,bar'))->toMatchArray([
        [
            'baz' => 3,
            'bar' => 2,
        ],
        [
            'baz' => 6,
            'bar' => 5,
        ],
    ]);

    Datasets::unset('dataset');
});
