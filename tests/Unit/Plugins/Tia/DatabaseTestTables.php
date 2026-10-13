<?php

declare(strict_types=1);

use Pest\Plugins\Tia\DatabaseTestTables;

describe('augment()', function (): void {
    beforeEach(function (): void {
        $this->projectRoot = sys_get_temp_dir().'/pest-tia-db-tables-'.bin2hex(random_bytes(4));
        mkdir($this->projectRoot.'/database/migrations', 0755, true);
        file_put_contents(
            $this->projectRoot.'/database/migrations/2024_01_01_000000_create_orders_table.php',
            "<?php Schema::create('orders', function () {});",
        );
        file_put_contents(
            $this->projectRoot.'/database/migrations/2024_01_02_000000_create_users_table.php',
            "<?php Schema::create('users', function () {});",
        );
    });

    afterEach(function (): void {
        foreach (glob($this->projectRoot.'/database/migrations/*.php') ?: [] as $migration) {
            unlink($migration);
        }

        @rmdir($this->projectRoot.'/database/migrations');
        @rmdir($this->projectRoot.'/database');
        @rmdir($this->projectRoot);
    });

    it('keeps the recorded tables of a database test that tracked its queries', function (): void {
        $augmented = DatabaseTestTables::augment(
            ['tests/Feature/OrderTest.php' => ['orders']],
            ['tests/Feature/OrderTest.php' => true],
            $this->projectRoot,
        );

        expect($augmented)->toBe(['tests/Feature/OrderTest.php' => ['orders']]);
    });

    it('links a database test with no recorded tables to every declared table', function (): void {
        $augmented = DatabaseTestTables::augment(
            ['tests/Feature/OrderTest.php' => ['orders']],
            ['tests/Feature/OrderTest.php' => true, 'tests/Feature/BootTest.php' => true],
            $this->projectRoot,
        );

        expect($augmented)->toBe([
            'tests/Feature/OrderTest.php' => ['orders'],
            'tests/Feature/BootTest.php' => ['orders', 'users'],
        ]);
    });

    it('leaves tests that do not use the database untouched', function (): void {
        $augmented = DatabaseTestTables::augment(
            ['tests/Unit/MathTest.php' => []],
            [],
            $this->projectRoot,
        );

        expect($augmented)->toBe(['tests/Unit/MathTest.php' => []]);
    });

    it('does nothing when the migrations declare no tables', function (): void {
        foreach (glob($this->projectRoot.'/database/migrations/*.php') ?: [] as $migration) {
            unlink($migration);
        }

        $augmented = DatabaseTestTables::augment(
            [],
            ['tests/Feature/BootTest.php' => true],
            $this->projectRoot,
        );

        expect($augmented)->toBeEmpty();
    });
});
