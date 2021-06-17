<?php

it('can access methods and properties', function () {
    expect(new HasMethodsAndProperties())
        ->name->toEqual('Has Methods and Properties')->not()->toEqual('bar')
        ->multiply(3, 4)->not->toBeString->toEqual(12)
        ->posts->each(function ($post) {
            $post->is_published->toBeTrue;
        })->books()->toBeArray()
        ->posts->toBeArray->each->not->toBeEmpty
        ->books()->sequence(
            function ($book) { $book->title->toEqual('Foo')->cost->toEqual(20); },
            function ($book) { $book->title->toEqual('Bar')->cost->toEqual(30); },
        );
});

class HasMethodsAndProperties
{
    public $name = 'Has Methods and Properties';

    public $posts = [
        [
            'is_published' => true,
            'title'        => 'Foo',
        ],
        [
            'is_published' => true,
            'title'        => 'Bar',
        ],
    ];

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

    public function multiply($x, $y)
    {
        return $x * $y;
    }
}
