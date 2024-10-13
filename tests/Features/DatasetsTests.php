<?php

use Pest\Exceptions\DatasetAlreadyExists;
use Pest\Exceptions\DatasetDoesNotExist;
use Pest\Plugin;
use Pest\Repositories\DatasetsRepository;

beforeEach(function () {
    $this->foo = 'bar';
});

it('throws exception if dataset does not exist', function () {
    $this->expectException(DatasetDoesNotExist::class);
    $this->expectExceptionMessage("A dataset with the name `first` does not exist. You can create it using `dataset('first', ['a', 'b']);`.");

    DatasetsRepository::resolve(['first'], __FILE__);
});

it('throws exception if dataset already exist', function () {
    DatasetsRepository::set('second', [[]], __DIR__);
    $this->expectException(DatasetAlreadyExists::class);
    $this->expectExceptionMessage('A dataset with the name `second` already exists in scope ['.__DIR__.'].');
    DatasetsRepository::set('second', [[]], __DIR__);
});

it('sets closures', function () {
    DatasetsRepository::set('foo', function () {
        yield [1];
    }, __DIR__);

    expect(DatasetsRepository::resolve(['foo'], __FILE__))->toBe(['(1)' => [1]]);
});

it('sets arrays', function () {
    DatasetsRepository::set('bar', [[2]], __DIR__);

    expect(DatasetsRepository::resolve(['bar'], __FILE__))->toBe(['(2)' => [2]]);
});

it('gets bound to test case object', function ($value) {
    $this->assertTrue(true);
})->with([['a'], ['b']]);

test('it truncates the description', function () {
    expect(true)->toBe(true);
    // it gets tested by the integration test
})->with([str_repeat('Fooo', 10)]);

$state = new stdClass;
$state->text = '';

$datasets = [[1], [2]];

test('lazy datasets', function ($text) use ($state, $datasets) {
    $state->text .= $text;

    expect(in_array([$text], $datasets))->toBe(true);
})->with($datasets);

test('lazy datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('12');
});

test('interpolated :dataset lazy datasets', function ($text) {
    expect(true)->toBeTrue();
})->with($datasets);

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

test('interpolated :dataset named datasets', function ($text) {
    expect(true)->toBeTrue();
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
    new Bar,
];

test('lazy named datasets', function ($text) {
    expect(true)->toBeTrue();
})->with($namedDatasets);

$counter = 0;

it('creates unique test case names', function (string $name, Plugin $plugin, bool $bool) use (&$counter) {
    expect(true)->toBeTrue();
    $counter++;
})->with([
    ['Name 1', new Plugin, true],
    ['Name 1', new Plugin, true],
    ['Name 1', new Plugin, false],
    ['Name 2', new Plugin, false],
    ['Name 2', new Plugin, true],
    ['Name 1', new Plugin, true],
]);

it('creates unique test case names - count', function () use (&$counter) {
    expect($counter)->toBe(6);
});

$datasets_a = [[1], [2]];
$datasets_b = [[3], [4]];

test('lazy multiple datasets', function ($text_a, $text_b) use ($state, $datasets_a, $datasets_b) {
    $state->text .= $text_a.$text_b;
    expect($datasets_a)->toContain([$text_a]);
    expect($datasets_b)->toContain([$text_b]);
})->with($datasets_a, $datasets_b);

test('lazy multiple datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('12121212121213142324');
});

$state->text = '';

test('eager multiple datasets', function ($text_a, $text_b) use ($state, $datasets_a, $datasets_b) {
    $state->text .= $text_a.$text_b;
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
    $state->text .= $text_a.$text_b;
    expect($datasets)->toContain([$text_a]);
    expect($datasets)->toContain([$text_b]);
})->with('numbers.array')->with('numbers.array');

test('lazy registered multiple datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('121212121212131423241314232411122122');
});

test('eager registered multiple datasets', function ($text_a, $text_b) use ($state, $datasets) {
    $state->text .= $text_a.$text_b;
    expect($datasets)->toContain([$text_a]);
    expect($datasets)->toContain([$text_b]);
})->with('numbers.array')->with('numbers.closure');

test('eager registered multiple datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('12121212121213142324131423241112212211122122');
});

test('eager wrapped registered multiple datasets', function ($text_a, $text_b) use ($state, $datasets) {
    $state->text .= $text_a.$text_b;
    expect($datasets)->toContain([$text_a]);
    expect($datasets)->toContain([$text_b]);
})->with('numbers.closure.wrapped')->with('numbers.closure');

test('eager wrapped registered multiple datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('1212121212121314232413142324111221221112212211122122');
});

test('named multiple datasets', function ($text_a, $text_b) use ($state, $datasets_a, $datasets_b) {
    $state->text .= $text_a.$text_b;
    expect($datasets_a)->toContain([$text_a]);
    expect($datasets_b)->toContain([$text_b]);
})->with([
    'one' => [1],
    'two' => [2],
])->with([
    'three' => [3],
    'four' => [4],
]);

test('named multiple datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('121212121212131423241314232411122122111221221112212213142324');
});

test('more than two datasets', function ($text_a, $text_b, $text_c) use ($state, $datasets_a, $datasets_b) {
    $state->text .= $text_a.$text_b.$text_c;
    expect($datasets_a)->toContain([$text_a]);
    expect($datasets_b)->toContain([$text_b]);
    expect([5, 6])->toContain($text_c);
})->with($datasets_a, $datasets_b)->with([5, 6]);

test('more than two datasets did the job right', function () use ($state) {
    expect($state->text)->toBe('121212121212131423241314232411122122111221221112212213142324135136145146235236245246');
});

$wrapped_generator_state = new stdClass;
$wrapped_generator_state->text = '';
$wrapped_generator_function_datasets = [1, 2, 3, 4];

test(
    'eager registered wrapped datasets with Generator functions',
    function (int $text) use (
        $wrapped_generator_state,
        $wrapped_generator_function_datasets
    ) {
        $wrapped_generator_state->text .= $text;
        expect(in_array($text, $wrapped_generator_function_datasets))->toBe(true);
    }
)->with('numbers.generators.wrapped');

test('eager registered wrapped datasets with Generator functions did the job right', function () use ($wrapped_generator_state) {
    expect($wrapped_generator_state->text)->toBe('1234');
});

test('eager registered wrapped datasets with Generator functions display description', function ($wrapped_generator_state_with_description) {
    expect($wrapped_generator_state_with_description)->not->toBeEmpty();
})->with(function () {
    yield 'taylor' => 'taylor@laravel.com';
    yield 'james' => 'james@laravel.com';
});

it('can resolve a dataset after the test case is available', function ($result) {
    expect($result)->toBe('bar');
})->with([
    function () {
        return $this->foo;
    },
    [
        function () {
            return $this->foo;
        },
    ],
]);

it('can resolve a dataset after the test case is available with multiple datasets', function (string $result, string $result2) {
    expect($result)->toBe('bar');
})->with([
    function () {
        return $this->foo;
    },
    [
        function () {
            return $this->foo;
        },
    ],
], [
    function () {
        return $this->foo;
    },
    [
        function () {
            return $this->foo;
        },
    ],
]);

it('can resolve a dataset after the test case is available with shared yield sets', function ($result) {
    expect($result)->toBeInt()->toBeLessThan(3);
})->with('bound.closure');

it('can resolve a dataset after the test case is available with shared array sets', function ($result) {
    expect($result)->toBeInt()->toBeLessThan(3);
})->with('bound.array');

it('resolves a potential bound dataset logically', function ($foo, $bar) {
    expect($foo)->toBe('foo');
    expect($bar())->toBe('bar');
})->with([
    [
        'foo',
        function () {
            return 'bar';
        },
    ], // This should be passed as a closure because we've passed multiple arguments
]);

it('resolves a potential bound dataset logically even when the closure comes first', function ($foo, $bar) {
    expect($foo())->toBe('foo');
    expect($bar)->toBe('bar');
})->with([
    [
        function () {
            return 'foo';
        }, 'bar',
    ], // This should be passed as a closure because we've passed multiple arguments
]);

it('will not resolve a closure if it is type hinted as a closure', function (Closure $data) {
    expect($data())->toBeString();
})->with([
    function () {
        return 'foo';
    },
    function () {
        return 'bar';
    },
]);

it('will not resolve a closure if it is type hinted as a callable', function (callable $data) {
    expect($data())->toBeString();
})->with([
    function () {
        return 'foo';
    },
    function () {
        return 'bar';
    },
]);

it('can correctly resolve a bound dataset that returns an array', function (array $data) {
    expect($data)->toBe(['foo', 'bar', 'baz']);
})->with([
    function () {
        return ['foo', 'bar', 'baz'];
    },
]);

it('can correctly resolve a bound dataset that returns an array but wants to be spread', function (string $foo, string $bar, string $baz) {
    expect([$foo, $bar, $baz])->toBe(['foo', 'bar', 'baz']);
})->with([
    function () {
        return ['foo', 'bar', 'baz'];
    },
]);

todo('forbids to define tests in Datasets dirs and Datasets.php files');

dataset('greeting-string', [
    'formal' => 'Evening',
    'informal' => 'yo',
]);

it('may be used with high order')
    ->with('greeting-string')
    ->expect(fn (string $greeting) => $greeting)
    ->throwsNoExceptions();

dataset('greeting-bound', [
    'formal' => fn () => 'Evening',
    'informal' => fn () => 'yo',
]);

it('may be used with high order even when bound')
    ->with('greeting-bound')
    ->expect(fn (string $greeting) => $greeting)
    ->throws(InvalidArgumentException::class);

describe('with on nested describe', function () {
    describe('nested', function () {
        test('before inner describe block', function (...$args) {
            expect($args)->toBe([1]);
        });

        describe('describe', function () {
            it('should include the with value from all parent describe blocks', function (...$args) {
                expect($args)->toBe([1, 2]);
            });

            test('should include the with value from all parent describe blocks and the test', function (...$args) {
                expect($args)->toBe([1, 2, 3]);
            })->with([3]);
        })->with([2]);

        test('after inner describe block', function (...$args) {
            expect($args)->toBe([1]);
        });
    })->with([1]);
});

test('after describe block', function (...$args) {
    expect($args)->toBe([5]);
})->with([5]);

it('may be used with high order after describe block')
    ->with('greeting-string')
    ->expect(fn (string $greeting) => $greeting)
    ->throwsNoExceptions();

dataset('after-describe', ['after']);

test('after describe block with named dataset', function (...$args) {
    expect($args)->toBe(['after']);
})->with('after-describe');
