<?php

namespace Tests\Fixtures\Snapshot;

class Color
{
    public function __construct(
        public int $red,
        public int $green,
        public int $blue,
    ) {
    }

    public function getStyle(): string
    {
        return 'rgba('.$this->red.', '.$this->green.', '.$this->blue.', 1)';
    }
}
