<?php

declare(strict_types=1);

it('can fail with comparison', function () {
    expect(true)->toEqual(false);
});

it('can be ignored because of no assertions', function () {

});

it('can be ignored because it is skipped', function () {
    expect(true)->toBeTrue();
})->skip("this is why");

it('can fail', function () {
    $this->fail("oh noo");
});

it('throws exception', function () {
    throw new Exception('test error');
});

it('is not done yet', function () {

})->todo();

todo("build this one.");

it('is passing', function () {
    expect(true)->toEqual(true);
});

