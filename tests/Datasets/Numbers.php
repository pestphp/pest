<?php

dataset('numbers.closure', function () {
    yield [1];
    yield [2];
});

dataset('numbers.closure.wrapped', function () {
    yield 1;
    yield 2;
});

dataset('numbers.array', [[1], [2]]);

dataset('numbers.array.wrapped', [1, 2]);
