<?php

declare(strict_types=1);

namespace App;

final class Greeter
{
    public static function greet(string $name): string
    {
        return sprintf('Hello, %s!', $name);
    }
}
