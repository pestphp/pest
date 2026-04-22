<?php

declare(strict_types=1);

use App\Greeter;

test('greeter greets', function () {
    expect(Greeter::greet('Nuno'))->toBe('Hello, Nuno!');
});
