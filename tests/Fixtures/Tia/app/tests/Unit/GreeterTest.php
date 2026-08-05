<?php

declare(strict_types=1);

use Fixture\App\Greeter;

test('greets a person', function (): void {
    expect((new Greeter)->greet('Nuno'))->toBe('Hello, Nuno!');
});

test('greets the world', function (): void {
    expect((new Greeter)->greet('world'))->toBe('Hello, world!');
});
