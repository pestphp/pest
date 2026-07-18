<?php

declare(strict_types=1);

dataset('bound.closure', function () {
    yield fn (): int => 1;
    yield fn (): int => 2;
});

dataset('bound.array', [
    fn (): int => 1,
    fn (): int => 2,
]);
