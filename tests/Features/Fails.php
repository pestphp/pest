<?php

it('may fail', function () {
    $this->fail();
})->fails();

it('may fail with the given message', function () {
    $this->fail('this is a failure');
})->fails('this is a failure');
