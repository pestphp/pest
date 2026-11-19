<?php

return function (): void {
    $barrier = getenv('PEST_TEST_STOP_BARRIER');

    if ($barrier !== false) {
        touch($barrier.'/worker-'.getenv('TEST_TOKEN'));
        $deadline = microtime(true) + 10;

        while (count(glob($barrier.'/worker-*')) < 2) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('The second parallel worker did not reach the barrier.');
            }

            usleep(1000);
        }
    }

    expect(false)->toBeTrue();
};
