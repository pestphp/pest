<?php

declare(strict_types=1);

use Pest\Plugins\Tia\TableExtractor;

describe('fromSql()', function (): void {
    it('extracts tables from plain DML', function (): void {
        expect(TableExtractor::fromSql('select * from users'))->toBe(['users'])
            ->and(TableExtractor::fromSql('INSERT INTO orders (id) VALUES (1)'))->toBe(['orders'])
            ->and(TableExtractor::fromSql('UPDATE posts SET title = ?'))->toBe(['posts'])
            ->and(TableExtractor::fromSql('DELETE FROM sessions WHERE id = ?'))->toBe(['sessions']);
    });

    it('extracts tables from joins', function (): void {
        expect(TableExtractor::fromSql('select * from orders join users on users.id = orders.user_id'))
            ->toBe(['orders', 'users']);
    });

    it('extracts tables from CTE queries', function (): void {
        $sql = 'WITH recent AS (SELECT * FROM orders WHERE created_at > ?) SELECT * FROM recent JOIN users ON users.id = recent.user_id';

        expect(TableExtractor::fromSql($sql))->toBe(['orders', 'recent', 'users']);
    });

    it('extracts tables from REPLACE INTO', function (): void {
        expect(TableExtractor::fromSql('REPLACE INTO settings (key, value) VALUES (?, ?)'))
            ->toBe(['settings']);
    });

    it('records the table, not the schema, for qualified identifiers', function (): void {
        expect(TableExtractor::fromSql('select * from public.users'))->toBe(['users'])
            ->and(TableExtractor::fromSql('select * from "public"."users"'))->toBe(['users'])
            ->and(TableExtractor::fromSql('select * from `analytics`.`events`'))->toBe(['events'])
            ->and(TableExtractor::fromSql('UPDATE public.posts SET title = ?'))->toBe(['posts']);
    });

    it('handles quoted identifiers', function (): void {
        expect(TableExtractor::fromSql('select * from "users"'))->toBe(['users'])
            ->and(TableExtractor::fromSql('select * from `users`'))->toBe(['users'])
            ->and(TableExtractor::fromSql('select * from [users]'))->toBe(['users']);
    });

    it('ignores schema metadata tables', function (): void {
        expect(TableExtractor::fromSql("select * from sqlite_master where type = 'table'"))->toBeEmpty()
            ->and(TableExtractor::fromSql('select * from pg_catalog.pg_tables'))->toBeEmpty()
            ->and(TableExtractor::fromSql('select * from information_schema.tables'))->toBeEmpty();
    });

    it('does not leak int keys for numeric identifiers', function (): void {
        // `substring(x FROM 1 FOR 3)` is standard SQL, and the `1` matches the
        // FROM pattern. Collecting names as array keys makes PHP coerce the
        // numeric string to an int, which then violates the declared
        // list<string> and blows up Recorder::linkTable(string).
        expect(TableExtractor::fromSql('select substring(name from 1 for 3) from users'))
            ->each->toBeString();
    });

    it('returns nothing for non-DML statements', function (): void {
        expect(TableExtractor::fromSql('PRAGMA foreign_keys = ON'))->toBeEmpty()
            ->and(TableExtractor::fromSql(''))->toBeEmpty()
            ->and(TableExtractor::fromSql('   '))->toBeEmpty();
    });
});

describe('fromMigrationSource()', function (): void {
    it('extracts tables from Schema builder calls', function (): void {
        $php = <<<'PHP'
        Schema::create('users', function (Blueprint $table) {});
        Schema::table('orders', function (Blueprint $table) {});
        Schema::rename('old_posts', 'posts');
        Schema::dropIfExists('sessions');
        PHP;

        expect(TableExtractor::fromMigrationSource($php))
            ->toBe(['old_posts', 'orders', 'posts', 'sessions', 'users']);
    });

    it('extracts tables from raw DDL statements', function (): void {
        $php = <<<'PHP'
        DB::statement('ALTER TABLE users ADD COLUMN age INT');
        DB::statement('CREATE TABLE IF NOT EXISTS invoices (id INT)');
        PHP;

        expect(TableExtractor::fromMigrationSource($php))->toBe(['invoices', 'users']);
    });

    it('records the table, not the schema, in qualified DDL and DML', function (): void {
        $php = <<<'PHP'
        DB::statement('ALTER TABLE public.users ADD COLUMN age INT');
        DB::statement('CREATE TABLE "analytics"."events" (id INT)');
        DB::statement('INSERT INTO public.settings (key) VALUES (1)');
        DB::statement('DELETE FROM `public`.`sessions`');
        DB::table('public.audits')->delete();
        PHP;

        expect(TableExtractor::fromMigrationSource($php))
            ->toBe(['audits', 'events', 'sessions', 'settings', 'users']);
    });

    it('does not leak int keys for numeric table names', function (): void {
        // A table named `123` is a legal quoted identifier. Collecting names as
        // array keys makes PHP coerce it to an int, breaking the declared
        // list<string>, so it must survive as a string rather than be dropped.
        expect(TableExtractor::fromMigrationSource("DB::table('123')->insert([]);"))
            ->toBe(['123']);
    });

    it('extracts tables from DB::table calls', function (): void {
        expect(TableExtractor::fromMigrationSource("DB::table('permissions')->insert([]);"))
            ->toBe(['permissions']);
    });
});
