<?php

function addUser()
{
    test()->user = 'nuno';
}

it('can set/get properties on $this', function () {
    addUser();
    expect($this->user)->toBe('nuno');
});

it('gets null if property do not exist', function () {
    expect(test()->wqdwqdqw)->toBe(null);
});

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
    test()->foo();
})->throws(\ReflectionException::class, 'Call to undefined method PHPUnit\Framework\TestCase::foo()');

it('can forward unexpected calls to any global function')->_assertThat();

it('can use helpers from helpers file')->myAssertTrue(true);

it('can use helpers from helpers directory')->myDirectoryAssertTrue(true);
