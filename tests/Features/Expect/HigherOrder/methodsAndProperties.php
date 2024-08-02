<?php

it('can access methods and properties', function () {
    expect(new HasMethodsAndProperties)
        ->name->toEqual('Has Methods and Properties')->not()->toEqual('bar')
        ->multiply(3, 4)->not->toBeString->toEqual(12)
        ->posts->each(function ($post) {
            $post->is_published->toBeTrue;
        })->books()->toBeArray()
        ->posts->toBeArray->each->not->toBeEmpty
        ->books()->sequence(
            function ($book) {
                $book->title->toEqual('Foo')->cost->toEqual(20);
            },
            function ($book) {
                $book->title->toEqual('Bar')->cost->toEqual(30);
            },
        );
});

it('can handle nested methods and properties', function () {
    expect(new HasMethodsAndProperties)
        ->meta->foo->bar->toBeString()->toEqual('baz')->not->toBeInt
        ->newInstance()->meta->foo->toBeArray()
        ->newInstance()->multiply(2, 2)->toEqual(4)->not->toEqual(5)
        ->newInstance()->books()->toBeArray();
});

it('works with higher order tests')
    ->expect(new HasMethodsAndProperties)
    ->meta->foo->bar->toBeString()->toEqual('baz')->not->toBeInt
    ->newInstance()->meta->foo->toBeArray
    ->newInstance()->multiply(2, 2)->toEqual(4)->not->toEqual(5)
    ->newInstance()->books()->toBeArray();

it('can start a new higher order expectation using the and syntax', function () {
    expect(new HasMethodsAndProperties)
        ->toBeInstanceOf(HasMethodsAndProperties::class)
        ->meta->toBeArray
        ->and(['foo' => 'bar'])
        ->toBeArray()
        ->foo->toEqual('bar');

    expect(static::getCount())->toEqual(4);
});

it('can start a new higher order expectation using the and syntax in higher order tests')
    ->expect(new HasMethodsAndProperties)
    ->toBeInstanceOf(HasMethodsAndProperties::class)
    ->meta->toBeArray
    ->and(['foo' => 'bar'])
    ->toBeArray()
    ->foo->toEqual('bar');

it('can start a new higher order expectation using the and syntax without nesting expectations', function () {
    expect(new HasMethodsAndProperties)
        ->toBeInstanceOf(HasMethodsAndProperties::class)
        ->meta
        ->sequence(
            function ($value, $key) {
                $value->toBeArray()->and($key)->toBe('foo');
            },
        );
});

class HasMethodsAndProperties
{
    public $name = 'Has Methods and Properties';

    public $meta = ['foo' => ['bar' => 'baz']];

    public $posts = [
        [
            'is_published' => true,
            'title' => 'Foo',
        ],
        [
            'is_published' => true,
            'title' => 'Bar',
        ],
    ];

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

    public function multiply($x, $y)
    {
        return $x * $y;
    }

    public function newInstance()
    {
        return new static;
    }
}
