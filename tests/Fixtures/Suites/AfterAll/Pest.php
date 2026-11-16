<?php

declare(strict_types=1);

pest()->afterAll(function (): void {
    file_put_contents((string) getenv('PEST_AFTER_ALL_MARKER'), 'pest()->afterAll'.PHP_EOL, FILE_APPEND);
});
