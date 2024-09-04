<?php

pest()->presets()->custom('myFramework', function (array $userNamespaces) {
    return [
        expect($userNamespaces)->toBe(['Pest']),
    ];
});

test('preset invalid name', function () {
    $this->preset()->myAnotherFramework();
})->throws(InvalidArgumentException::class, 'The preset [myAnotherFramework] does not exist. The available presets are [php, laravel, strict, security, relaxed, myFramework].');

arch()->preset()->myFramework();
