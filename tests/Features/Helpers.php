<?php

function addUser()
{
    test()->user = 'nuno';
}

it('can set/get properties on $this', function () {
    addUser();
    expect($this->user)->toBe('nuno');
});

it('throws error if property do not exist', function () {
    test()->user;
})->throws(\Whoops\Exception\ErrorException::class, 'Undefined property PHPUnit\Framework\TestCase::$user');

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
    expect($user->getName())->toBe('maduro');
});

it('throws error if method do not exist', function () {
    test()->name();
})->throws(\ReflectionException::class, 'Call to undefined method PHPUnit\Framework\TestCase::name()');
