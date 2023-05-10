<?php

function my_environment($app)
{
}

test('it will add a define-env annotation', function () {
    $phpDoc = (new ReflectionClass($this))->getMethod($this->name());
    expect($phpDoc->getDocComment())->toContain('* @define-env my_environment');
})->environment('my_environment');
