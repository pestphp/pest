<?php

return static fn (int $severity): Closure => function () use ($severity): void {
    trigger_error('shared issue', $severity);

    expect(true)->toBeTrue();
};
