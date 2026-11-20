<?php

declare(strict_types=1);

function tia_shared_source_boot_path(): int
{
    $value = 100;
    $value++;

    return $value;
}

function tia_shared_source_test_path(): int
{
    $value = 200;
    $value++;

    return $value;
}
