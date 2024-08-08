<?php

declare(strict_types=1);

namespace Tests\Fixtures\Arch\ToHavePublicMethodsBesides;

class UserController
{
    public function publicMethod(): string
    {
        return '';
    }

    protected function protectedMethod(): string
    {
        return '';
    }

    private function privateMethod(): string
    {
        return '';
    }
}
