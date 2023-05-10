<?php

function my_environment($app)
{
}

test('it will add a define-env annotation', function () {
    $phpDoc = (new ReflectionClass($this))->getMethod($this->name());
    expect(str_contains($phpDoc->getDocComment(), '* @define-env my_environment'))->toBeTrue();
})->environment('my_environment');
