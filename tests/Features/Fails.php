<?php

it('may fail', function (): void {
    $this->fail();
})->fails();

it('may fail with the given message', function (): void {
    $this->fail('this is a failure');
})->fails('this is a failure');
