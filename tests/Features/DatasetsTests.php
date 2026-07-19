<?php

use Pest\Exceptions\DatasetAlreadyExists;
use Pest\Exceptions\DatasetDoesNotExist;
use Pest\Plugin;
use Pest\Repositories\DatasetsRepository;

beforeEach(function (): void {
    $this->foo = 'bar';
});

it('throws exception if dataset does not exist', function (): void {
    expect(fn () => DatasetsRepository::resolve(['first'], __FILE__))->toThrow(DatasetDoesNotExist::class, "A dataset with the name `first` does not exist. You can create it using `dataset('first', ['a', 'b']);`.");
});

it('throws exception if dataset already exist', function (): void {
    DatasetsRepository::set('second', [[]], __DIR__);
    expect(fn () => DatasetsRepository::set('second', [[]], __DIR__))->toThrow(DatasetAlreadyExists::class, 'A dataset with the name `second` already exists in scope ['.__DIR__.'].');
});

it('sets closures', function (): void {
    DatasetsRepository::set('foo', function () {
        yield [1];
    }, __DIR__);

    expect(DatasetsRepository::resolve(['foo'], __FILE__))->toBe(['(1)' => [1]]);
});

it('sets arrays', function (): void {
    DatasetsRepository::set('bar', [[2]], __DIR__);

    expect(DatasetsRepository::resolve(['bar'], __FILE__))->toBe(['(2)' => [2]]);
});

it('gets bound to test case object', function ($value): void {
    expect(true)->toBeTrue();
})->with([['a'], ['b']]);

test('it truncates the description', function (): void {
    expect(true)->toBeTrue();
    // it gets tested by the integration test
})->with([str_repeat('Fooo', 10)]);

$state = new stdClass;
$state->text = '';

$datasets = [[1], [2]];

test('lazy datasets', function ($text) use ($state, $datasets): void {
    $state->text .= $text;

    expect([$text])->toBeIn($datasets);
})->with($datasets);

test('lazy datasets did the job right', function () use ($state): void {
    expect($state->text)->toBe('12');
});

test('interpolated :dataset lazy datasets', function ($text): void {
    expect(true)->toBeTrue();
})->with($datasets);

$state->text = '';

test('eager datasets', function ($text) use ($state, $datasets): void {
    $state->text .= $text;
    expect($datasets)->toContain([$text]);
})->with(fn (): array => $datasets);

test('eager datasets did the job right', function () use ($state): void {
    expect($state->text)->toBe('1212');
});

test('lazy registered datasets', function ($text) use ($state, $datasets): void {
    $state->text .= $text;
    expect($datasets)->toContain([$text]);
})->with('numbers.array');

test('lazy registered datasets did the job right', function () use ($state): void {
    expect($state->text)->toBe('121212');
});

test('eager registered datasets', function ($text) use ($state, $datasets): void {
    $state->text .= $text;
    expect($datasets)->toContain([$text]);
})->with('numbers.closure');

test('eager registered datasets did the job right', function () use ($state): void {
    expect($state->text)->toBe('12121212');
});

test('eager wrapped registered datasets', function ($text) use ($state, $datasets): void {
    $state->text .= $text;
    expect($datasets)->toContain([$text]);
})->with('numbers.closure.wrapped');

test('eager registered wrapped datasets did the job right', function () use ($state): void {
    expect($state->text)->toBe('1212121212');
});

test('named datasets', function ($text) use ($state, $datasets): void {
    $state->text .= $text;
    expect($datasets)->toContain([$text]);
})->with([
    'one' => [1],
    'two' => [2],
]);

test('interpolated :dataset named datasets', function ($text): void {
    expect(true)->toBeTrue();
})->with([
    'one' => [1],
    'two' => [2],
]);

test('named datasets did the job right', function () use ($state): void {
    expect($state->text)->toBe('121212121212');
});

class Bar
{
    public $name = 1;
}

$namedDatasets = [
    new Bar,
];

test('lazy named datasets', function ($text): void {
    expect(true)->toBeTrue();
})->with($namedDatasets);

$counter = 0;

it('creates unique test case names', function (string $name, Plugin $plugin, bool $bool) use (&$counter): void {
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

it('creates unique test case names - count', function () use (&$counter): void {
    expect($counter)->toBe(6);
});

$datasets_a = [[1], [2]];
$datasets_b = [[3], [4]];

test('lazy multiple datasets', function ($text_a, $text_b) use ($state, $datasets_a, $datasets_b): void {
    $state->text .= $text_a.$text_b;
    expect($datasets_a)->toContain([$text_a])
        ->and($datasets_b)->toContain([$text_b]);
})->with($datasets_a, $datasets_b);

test('lazy multiple datasets did the job right', function () use ($state): void {
    expect($state->text)->toBe('12121212121213142324');
});

$state->text = '';

test('eager multiple datasets', function ($text_a, $text_b) use ($state, $datasets_a, $datasets_b): void {
    $state->text .= $text_a.$text_b;
    expect($datasets_a)->toContain([$text_a])
        ->and($datasets_b)->toContain([$text_b]);
})->with(fn (): array => $datasets_a)->with(fn (): array => $datasets_b);

test('eager multiple datasets did the job right', function () use ($state): void {
    expect($state->text)->toBe('1212121212121314232413142324');
});

test('lazy registered multiple datasets', function ($text_a, $text_b) use ($state, $datasets): void {
    $state->text .= $text_a.$text_b;
    expect($datasets)->toContain([$text_a])
        ->toContain([$text_b]);
})->with('numbers.array')->with('numbers.array');

test('lazy registered multiple datasets did the job right', function () use ($state): void {
    expect($state->text)->toBe('121212121212131423241314232411122122');
});

test('eager registered multiple datasets', function ($text_a, $text_b) use ($state, $datasets): void {
    $state->text .= $text_a.$text_b;
    expect($datasets)->toContain([$text_a])
        ->toContain([$text_b]);
})->with('numbers.array')->with('numbers.closure');

test('eager registered multiple datasets did the job right', function () use ($state): void {
    expect($state->text)->toBe('12121212121213142324131423241112212211122122');
});

test('eager wrapped registered multiple datasets', function ($text_a, $text_b) use ($state, $datasets): void {
    $state->text .= $text_a.$text_b;
    expect($datasets)->toContain([$text_a])
        ->toContain([$text_b]);
})->with('numbers.closure.wrapped')->with('numbers.closure');

test('eager wrapped registered multiple datasets did the job right', function () use ($state): void {
    expect($state->text)->toBe('1212121212121314232413142324111221221112212211122122');
});

test('named multiple datasets', function ($text_a, $text_b) use ($state, $datasets_a, $datasets_b): void {
    $state->text .= $text_a.$text_b;
    expect($datasets_a)->toContain([$text_a])
        ->and($datasets_b)->toContain([$text_b]);
})->with([
    'one' => [1],
    'two' => [2],
])->with([
    'three' => [3],
    'four' => [4],
]);

test('named multiple datasets did the job right', function () use ($state): void {
    expect($state->text)->toBe('121212121212131423241314232411122122111221221112212213142324');
});

test('more than two datasets', function ($text_a, $text_b, $text_c) use ($state, $datasets_a, $datasets_b): void {
    $state->text .= $text_a.$text_b.$text_c;
    expect($datasets_a)->toContain([$text_a])
        ->and($datasets_b)->toContain([$text_b])
        ->and([5, 6])->toContain($text_c);
})->with($datasets_a, $datasets_b)->with([5, 6]);

test('more than two datasets did the job right', function () use ($state): void {
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
    ): void {
        $wrapped_generator_state->text .= $text;
        expect($text)->toBeIn($wrapped_generator_function_datasets);
    }
)->with('numbers.generators.wrapped');

test('eager registered wrapped datasets with Generator functions did the job right', function () use ($wrapped_generator_state): void {
    expect($wrapped_generator_state->text)->toBe('1234');
});

test('eager registered wrapped datasets with Generator functions display description', function ($wrapped_generator_state_with_description): void {
    expect($wrapped_generator_state_with_description)->not->toBeEmpty();
})->with(function () {
    yield 'taylor' => 'taylor@laravel.com';
    yield 'james' => 'james@laravel.com';
});

it('can resolve a dataset after the test case is available', function ($result): void {
    expect($result)->toBe('bar');
})->with([
    fn () => $this->foo,
    [
        fn () => $this->foo,
    ],
]);

it('can resolve a dataset after the test case is available with multiple datasets', function (string $result, string $result2): void {
    expect($result)->toBe('bar');
})->with([
    fn () => $this->foo,
    [
        fn () => $this->foo,
    ],
], [
    fn () => $this->foo,
    [
        fn () => $this->foo,
    ],
]);

it('can resolve a dataset after the test case is available with shared yield sets', function ($result): void {
    expect($result)->toBeInt()->toBeLessThan(3);
})->with('bound.closure');

it('can resolve a dataset after the test case is available with shared array sets', function ($result): void {
    expect($result)->toBeInt()->toBeLessThan(3);
})->with('bound.array');

it('resolves a potential bound dataset logically', function ($foo, $bar): void {
    expect($foo)->toBe('foo')
        ->and($bar())->toBe('bar');
})->with([
    [
        'foo',
        fn (): string => 'bar',
    ], // This should be passed as a closure because we've passed multiple arguments
]);

it('resolves a potential bound dataset logically even when the closure comes first', function ($foo, $bar): void {
    expect($foo())->toBe('foo')
        ->and($bar)->toBe('bar');
})->with([
    [
        fn (): string => 'foo', 'bar',
    ], // This should be passed as a closure because we've passed multiple arguments
]);

it('will not resolve a closure if it is type hinted as a closure', function (Closure $data): void {
    expect($data())->toBeString();
})->with([
    fn (): string => 'foo',
    fn (): string => 'bar',
]);

it('will not resolve a closure if it is type hinted as a callable', function (callable $data): void {
    expect($data())->toBeString();
})->with([
    fn (): string => 'foo',
    fn (): string => 'bar',
]);

it('can correctly resolve a bound dataset that returns an array', function (array $data): void {
    expect($data)->toBe(['foo', 'bar', 'baz']);
})->with([
    fn (): array => ['foo', 'bar', 'baz'],
]);

it('can correctly resolve a bound dataset that returns an array but wants to be spread', function (string $foo, string $bar, string $baz): void {
    expect([$foo, $bar, $baz])->toBe(['foo', 'bar', 'baz']);
})->with([
    fn (): array => ['foo', 'bar', 'baz'],
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

describe('with on nested describe', function (): void {
    describe('nested', function (): void {
        test('before inner describe block', function (...$args): void {
            expect($args)->toBe([1]);
        });

        describe('describe', function (): void {
            it('should include the with value from all parent describe blocks', function (...$args): void {
                expect($args)->toBe([1, 2]);
            });

            test('should include the with value from all parent describe blocks and the test', function (...$args): void {
                expect($args)->toBe([1, 2, 3]);
            })->with([3]);
        })->with([2]);

        test('after inner describe block', function (...$args): void {
            expect($args)->toBe([1]);
        });
    })->with([1]);
});

describe('matching describe block names', function (): void {
    describe('outer', function (): void {
        test('before inner describe block', function (...$args): void {
            expect($args)->toBe([1]);
        });

        describe('inner', function (): void {
            it('should include the with value from all parent describe blocks', function (...$args): void {
                expect($args)->toBe([1, 2]);
            });

            test('should include the with value from all parent describe blocks and the test', function (...$args): void {
                expect($args)->toBe([1, 2, 3]);
            })->with([3]);
        })->with([2]);

        describe('inner', function (): void {
            it('should not include the value from the other describe block with the same name', function (...$args): void {
                expect($args)->toBe([1]);
            });
        });

        test('after inner describe block', function (...$args): void {
            expect($args)->toBe([1]);
        });
    })->with([1]);
});

test('after describe block', function (...$args): void {
    expect($args)->toBe([5]);
})->with([5]);

it('may be used with high order after describe block')
    ->with('greeting-string')
    ->expect(fn (string $greeting) => $greeting)
    ->throwsNoExceptions();

dataset('after-describe', ['after']);

test('after describe block with named dataset', function (...$args): void {
    expect($args)->toBe(['after']);
})->with('after-describe');

test('named parameters match by parameter name', function (string $email, string $name): void {
    expect($name)->toBe('Taylor')
        ->and($email)->toBe('taylor@laravel.com');
})->with([
    ['name' => 'Taylor', 'email' => 'taylor@laravel.com'],
]);

test('named parameters work with multiple dataset items', function (string $email, string $name): void {
    expect($name)->toBeString()
        ->and($email)->toContain('@');
})->with([
    ['name' => 'Taylor', 'email' => 'taylor@laravel.com'],
    ['name' => 'James', 'email' => 'james@laravel.com'],
]);

test('named parameters work in different order than closure params', function (string $third, string $first, string $second): void {
    expect($first)->toBe('a')
        ->and($second)->toBe('b')
        ->and($third)->toBe('c');
})->with([
    ['first' => 'a', 'second' => 'b', 'third' => 'c'],
]);

test('named parameters work with named dataset keys', function (string $email, string $name): void {
    expect($name)->toBeString()
        ->and($email)->toContain('@');
})->with([
    'taylor' => ['name' => 'Taylor', 'email' => 'taylor@laravel.com'],
    'james' => ['name' => 'James', 'email' => 'james@laravel.com'],
]);

test('named parameters work with closures that should be resolved', function (string $email, string $name): void {
    expect($name)->toBe('bar')
        ->and($email)->toBe('bar@example.com');
})->with([
    [
        'name' => fn () => $this->foo,
        'email' => fn (): string => $this->foo.'@example.com',
    ],
]);

test('named parameters work with closure type hints', function (Closure $callback, string $name): void {
    expect($name)->toBe('Taylor')
        ->and($callback())->toBe('resolved');
})->with([
    [
        'name' => 'Taylor',
        'callback' => fn (): string => 'resolved',
    ],
]);

dataset('named-params-dataset', [
    ['name' => 'Taylor', 'email' => 'taylor@laravel.com'],
    ['name' => 'James', 'email' => 'james@laravel.com'],
]);

test('named parameters work with registered datasets', function (string $email, string $name): void {
    expect($name)->toBeString()
        ->and($email)->toContain('@');
})->with('named-params-dataset');

test('named parameters work with bound closure returning associative array', function (string $email, string $name): void {
    expect($name)->toBe('bar')
        ->and($email)->toBe('test@example.com');
})->with([
    fn (): array => ['name' => $this->foo, 'email' => 'test@example.com'],
]);

test('dataset items can mix named and sequential styles', function (string $name, string $email): void {
    expect($name)->toBeString()
        ->and($email)->toContain('@');
})->with([
    ['name' => 'Taylor', 'email' => 'taylor@laravel.com'],
    ['James', 'james@laravel.com'],
    ['James', 'email' => 'james@laravel.com'],
]);
