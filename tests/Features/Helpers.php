<?php

function addUser(): void
{
    test()->user = 'nuno';
}

it('can set/get properties on $this', function (): void {
    addUser();
    expect($this->user)->toBe('nuno');
});

it('gets null if property do not exist', function (): void {
    expect(test()->wqdwqdqw)->toBeNull();
});

class User
{
    public function getName(): string
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

it('allows to call underlying protected/private methods', function (): void {
    $user = mockUser();
    expect($user->getName())->toBe('maduro');
});

it('throws error if method do not exist', function (): void {
    test()->foo();
})->throws(ReflectionException::class, 'Call to undefined method PHPUnit\Framework\TestCase::foo()');

it('can forward unexpected calls to any global function')->_assertThat();

it('can use helpers from helpers file')->myAssertTrue(true);

it('can use helpers from helpers directory')->myDirectoryAssertTrue(true);
