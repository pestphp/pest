<?php

function my_environment($app)
{
}

test('it will add a define-env annotation', function () {
    $phpDoc = (new ReflectionClass($this))->getMethod($this->name());
    expect($phpDoc->getDocComment())->toContain('* @define-env my_environment');
})->environment('my_environment');

test('it will add the method', function () {
    $methods = (new ReflectionClass($this))->getMethods();
    //dump($methods);
    expect(array_column($methods, 'name'))->toContain('define_env_my_environment___pest_evaluable_it_will_add_the_method');
})->environment('my_environment');
