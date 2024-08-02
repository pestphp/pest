<?php

it('can access methods', function () {
    expect(new HasMethods)
        ->name()->toBeString()->toEqual('Has Methods');
});

it('can access multiple methods', function () {
    expect(new HasMethods)
        ->name()->toBeString()->toEqual('Has Methods')
        ->quantity()->toBeInt()->toEqual(20);
});

it('works with not', function () {
    expect(new HasMethods)
        ->name()->not->toEqual('world')->toEqual('Has Methods')
        ->quantity()->toEqual(20)->not()->toEqual('bar')->not->toBeNull;
});

it('can accept arguments', function () {
    expect(new HasMethods)
        ->multiply(5, 4)->toBeInt->toEqual(20);
});

it('works with each', function () {
    expect(new HasMethods)
        ->attributes()->toBeArray->each->not()->toBeNull
        ->attributes()->each(function ($attribute) {
            $attribute->not->toBeNull();
        });
});

it('works inside of each', function () {
    expect(new HasMethods)
        ->books()->each(function ($book) {
            $book->title->not->toBeNull->cost->toBeGreaterThan(19);
        });
});

it('works with sequence', function () {
    expect(new HasMethods)
        ->books()->sequence(
            function ($book) {
                $book->title->toEqual('Foo')->cost->toEqual(20);
            },
            function ($book) {
                $book->title->toEqual('Bar')->cost->toEqual(30);
            },
        );
});

it('can compose complex expectations', function () {
    expect(new HasMethods)
        ->toBeObject()
        ->name()->toEqual('Has Methods')->not()->toEqual('bar')
        ->quantity()->not->toEqual('world')->toEqual(20)->toBeInt
        ->multiply(3, 4)->not->toBeString->toEqual(12)
        ->attributes()->toBeArray()
        ->books()->toBeArray->each->not->toBeEmpty
        ->books()->sequence(
            function ($book) {
                $book->title->toEqual('Foo')->cost->toEqual(20);
            },
            function ($book) {
                $book->title->toEqual('Bar')->cost->toEqual(30);
            },
        );
});

it('can handle nested method calls', function () {
    expect(new HasMethods)
        ->newInstance()->newInstance()->name()->toEqual('Has Methods')->toBeString()
        ->newInstance()->name()->toEqual('Has Methods')->not->toBeInt
        ->name()->toEqual('Has Methods')
        ->books()->each->toBeArray();
});

it('works with higher order tests')
    ->expect(new HasMethods)
    ->newInstance()->newInstance()->name()->toEqual('Has Methods')->toBeString()
    ->newInstance()->name()->toEqual('Has Methods')->not->toBeArray
    ->name()->toEqual('Has Methods')
    ->books()->each->toBeArray;

it('can use the scoped method to lock into the given level for expectations', function () {
    expect(new HasMethods)
        ->attributes()->scoped(fn ($attributes) => $attributes
        ->name->toBe('Has Methods')
        ->quantity->toBe(20)
        )
        ->name()->toBeString()->toBe('Has Methods')
        ->newInstance()->newInstance()->scoped(fn ($instance) => $instance
        ->name()->toBe('Has Methods')
        ->quantity()->toBe(20)
        ->attributes()->scoped(fn ($attributes) => $attributes
        ->name->toBe('Has Methods')
        ->quantity->toBe(20)
        )
        );
});

it('works consistently with the json expectation method', function () {
    expect(new HasMethods)
        ->jsonString()->json()->id->toBe(1)
        ->jsonString()->json()->name->toBe('Has Methods')->toBeString()
        ->jsonString()->json()->quantity->toBe(20)->toBeInt();
});

class HasMethods
{
    public function jsonString(): string
    {
        return '{ "id": 1, "name": "Has Methods", "quantity": 20 }';
    }

    public function name()
    {
        return 'Has Methods';
    }

    public function quantity()
    {
        return 20;
    }

    public function multiply($x, $y)
    {
        return $x * $y;
    }

    public function attributes()
    {
        return [
            'name' => $this->name(),
            'quantity' => $this->quantity(),
        ];
    }

    public function books()
    {
        return [
            [
                'title' => 'Foo',
                'cost' => 20,
            ],
            [
                'title' => 'Bar',
                'cost' => 30,
            ],
        ];
    }

    public function newInstance()
    {
        return new static;
    }
}
