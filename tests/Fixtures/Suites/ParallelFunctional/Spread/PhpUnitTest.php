<?php

declare(strict_types=1);

namespace Tests\Fixtures\Suites\ParallelFunctional\Spread;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhpUnitTest extends TestCase
{
    public function test_it_runs_the_first_test(): void
    {
        $this->assertTrue(true);
    }

    #[DataProvider('values')]
    public function test_it_runs_a_data_set(int $value): void
    {
        $this->assertIsInt($value);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function values(): array
    {
        return ['one' => [1], 'two' => [2]];
    }
}
