<?php

it('allows properties to be accessed from the value', function (): void {
    expect(['foo' => 1])->foo->toBeInt()->toEqual(1);
});

it('can access multiple properties from the value', function (): void {
    expect(['foo' => 'bar', 'hello' => 'world'])
        ->foo->toBeString()->toEqual('bar')
        ->hello->toBeString()->toEqual('world');
});

it('works with not', function (): void {
    expect(['foo' => 'bar', 'hello' => 'world'])
        ->foo->not->not->toEqual('bar')
        ->foo->not->toEqual('world')->toEqual('bar')
        ->hello->toEqual('world')->not()->toEqual('bar')->not->toBeNull;
});

it('works with each', function (): void {
    expect(['numbers' => [1, 2, 3, 4], 'words' => ['hey', 'there']])
        ->numbers->toEqual([1, 2, 3, 4])->each->toBeInt->toBeLessThan(5)
        ->words->each(function ($word): void {
            $word->toBeString()->not->toBeInt();
        });
});

it('works inside of each', function (): void {
    expect(['books' => [['title' => 'Foo', 'cost' => 20], ['title' => 'Bar', 'cost' => 30]]])
        ->books->each(function ($book): void {
            $book->title->not->toBeNull->cost->toBeGreaterThan(19);
        });
});

it('works with sequence', function (): void {
    expect(['books' => [['title' => 'Foo', 'cost' => 20], ['title' => 'Bar', 'cost' => 30]]])
        ->books->sequence(
            function ($book): void {
                $book->title->toEqual('Foo')->cost->toEqual(20);
            },
            function ($book): void {
                $book->title->toEqual('Bar')->cost->toEqual(30);
            },
        );
});

it('can compose complex expectations', function (): void {
    expect(['foo' => 'bar', 'numbers' => [1, 2, 3, 4]])
        ->toContain('bar')->toBeArray()
        ->numbers->toEqual([1, 2, 3, 4])->not()->toEqual('bar')->each->toBeInt
        ->foo->not->toEqual('world')->toEqual('bar')
        ->numbers->toBeArray();
});

it('works with objects', function (): void {
    expect(new HasProperties)
        ->name->toEqual('foo')->not->toEqual('world')
        ->posts->toHaveCount(2)->each(function ($post): void {
            $post->is_published->toBeTrue();
        })
        ->posts->sequence(
            function ($post): void {
                $post->title->toEqual('Foo');
            },
            function ($post): void {
                $post->title->toEqual('Bar');
            },
        );
});

it('works with nested properties', function (): void {
    expect(new HasProperties)
        ->nested->foo->bar->toBeString()->toEqual('baz')
        ->posts->toBeArray()->toHaveCount(2);
});

it('works with higher order tests')
    ->expect(new HasProperties)
    ->nested->foo->bar->toBeString()->toEqual('baz')
    ->posts->toBeArray()->toHaveCount(2);

class HasProperties
{
    public $name = 'foo';

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

    public $nested = [
        'foo' => ['bar' => 'baz'],
    ];
}
