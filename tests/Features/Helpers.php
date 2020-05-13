<?php

function addName()
{
    test()->user = 'nuno';
}

it('can set/get properties on $this', function () {
    addName();
    assertEquals('nuno', $this->name);
});

it('throws error if property do not exist', function () {
    test()->user;
})->throws(\Whoops\Exception\ErrorException::class, 'Undefined property PHPUnit\Framework\TestCase::$name');

class User
{
    public function getName()
    {
        return 'nuno';
    }
}

function mockUser()
{
    $mock = test()->createMock(User::class);

    $mock->method('getName')
         ->willReturn('maduro');

    return $mock;
}

it('allows to call underlying protected/private methods', function () {
    $user = mockUser();

    assertEquals('maduro', $user->getName());
});

it('throws error if method do not exist', function () {
    test()->name();
})->throws(\ReflectionException::class, 'Call to undefined method PHPUnit\Framework\TestCase::name()');
