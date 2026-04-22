<?php

declare(strict_types=1);

use App\Math;

test('math add', function () {
    expect(Math::add(2, 3))->toBe(5);
});

test('math add negative', function () {
    expect(Math::add(-1, 1))->toBe(0);
});
