<?php

declare(strict_types=1);

namespace Tests\Fixtures\Arch\ToUseStrictEquality;

class NotStrictEquality
{
    public function test(): void
    {
        $a = 1;
        $b = '1';

        if ($a == $b) {
            echo 'Equal';
        }

        if ($a != $b) {
            echo 'Equal';
        }
    }
}
