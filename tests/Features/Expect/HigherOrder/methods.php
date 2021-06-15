<?php

it('can access methods', function () {
    expect(new HasMethods())
        ->name()->toBeString()->toEqual('Has Methods');
});

it('can access multiple methods', function () {
    expect(new HasMethods())
        ->name()->toBeString()->toEqual('Has Methods')
        ->quantity()->toBeInt()->toEqual(20);
});

it('works with not', function () {
    expect(new HasMethods())
        ->name()->not->toEqual('world')->toEqual('Has Methods')
        ->quantity()->toEqual(20)->not()->toEqual('bar')->not->toBeNull;
});

it('can accept arguments', function () {
    expect(new HasMethods())
        ->multiply(5, 4)->toBeInt->toEqual(20);
});

it('works with each', function () {
    expect(new HasMethods())
        ->attributes()->toBeArray->each->not()->toBeNull
        ->attributes()->each(function ($attribute) {
            $attribute->not->toBeNull();
        });
});

it('works inside of each', function () {
    expect(new HasMethods())
        ->books()->each(function ($book) {
            $book->title->not->toBeNull->cost->toBeGreaterThan(19);
        });
});

it('works with sequence', function () {
    expect(new HasMethods())
        ->books()->sequence(
            function ($book) { $book->title->toEqual('Foo')->cost->toEqual(20); },
            function ($book) { $book->title->toEqual('Bar')->cost->toEqual(30); },
        );
});

it('can compose complex expectations', function () {
    expect(new HasMethods())
        ->toBeObject()
        ->name()->toEqual('Has Methods')->not()->toEqual('bar')
        ->quantity()->not->toEqual('world')->toEqual(20)->toBeInt
        ->multiply(3, 4)->not->toBeString->toEqual(12)
        ->attributes()->toBeArray()
        ->books()->toBeArray->each->not->toBeEmpty
        ->books()->sequence(
            function ($book) { $book->title->toEqual('Foo')->cost->toEqual(20); },
            function ($book) { $book->title->toEqual('Bar')->cost->toEqual(30); },
        );
});

class HasMethods
{
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
            'name'     => $this->name(),
            'quantity' => $this->quantity(),
        ];
    }

    public function books()
    {
        return [
            [
                'title' => 'Foo',
                'cost'  => 20,
            ],
            [
                'title' => 'Bar',
                'cost'  => 30,
            ],
        ];
    }
}
