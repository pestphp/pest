<?php

it('compares a snapshot', function () {
    expect('after')->toMatchSnapshot();
})->flaky(tries: 3);
