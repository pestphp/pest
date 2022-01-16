<?php

use Pest\Contracts\IsHigherOrderCallable;

class HigherOrderCallable implements IsHigherOrderCallable
{
    public function __invoke()
    {
        return 'foo';
    }
}
class HigherOrderCallableReturningArguments implements IsHigherOrderCallable
{
    public function __invoke(...$args)
    {
        return $args;
    }
}
class RandomInvokableClass
{
    public function __invoke()
    {
        return 'foo';
    }
}

beforeEach()->assertTrue(true);

it('treats higher order callables as callables')
    ->expect(new HigherOrderCallable())
    ->toBe('foo');

it('can pass datasets to higher order callables')
    ->with([[1, 2, 3]])
    ->expect(new HigherOrderCallableReturningArguments())
    ->toBe([1, 2, 3]);

it('does not treat all invokable classes as callables')
    ->expect(new RandomInvokableClass())
    ->toBeInstanceOf(RandomInvokableClass::class);

afterEach()->assertTrue(true);
