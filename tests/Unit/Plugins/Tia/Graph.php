<?php

declare(strict_types=1);

use Pest\Plugins\Tia\Graph;
use Pest\Plugins\Tia\WatchPatterns;
use Pest\Support\Container;
use PHPUnit\Framework\TestStatus\TestStatus;

describe('shouldRerunStatus()', function (): void {
    it('re-runs failures and errors, replays successes', function (): void {
        $graph = new Graph(sys_get_temp_dir());

        expect($graph->shouldRerunStatus(TestStatus::failure('boom')))->toBeTrue()
            ->and($graph->shouldRerunStatus(TestStatus::error('boom')))->toBeTrue()
            ->and($graph->shouldRerunStatus(TestStatus::success()))->toBeFalse();
    });
});

describe('applyMigrationChanges()', function (): void {
    beforeEach(function (): void {
        $this->projectRoot = sys_get_temp_dir().'/pest-tia-graph-'.bin2hex(random_bytes(4));
        mkdir($this->projectRoot.'/database/migrations', 0755, true);
        file_put_contents(
            $this->projectRoot.'/database/migrations/2024_01_01_000000_create_orders_table.php',
            "<?php Schema::create('orders', function () {});",
        );

        $this->watchPatterns = new WatchPatterns;
        Container::getInstance()->add(WatchPatterns::class, $this->watchPatterns);
    });

    afterEach(function (): void {
        @unlink($this->projectRoot.'/database/migrations/2024_01_01_000000_create_orders_table.php');
        @rmdir($this->projectRoot.'/database/migrations');
        @rmdir($this->projectRoot.'/database');
        @rmdir($this->projectRoot);

        Container::getInstance()->add(WatchPatterns::class, new WatchPatterns);
    });

    it('selects tests whose recorded tables intersect the changed migration', function (): void {
        $graph = new Graph($this->projectRoot);
        $graph->link('tests/Feature/OrderTest.php', 'app/Models/Order.php');
        $graph->link('tests/Feature/UserTest.php', 'app/Models/User.php');
        $graph->replaceTestTables([
            'tests/Feature/OrderTest.php' => ['orders'],
            'tests/Feature/UserTest.php' => ['users'],
        ]);

        $affected = $graph->affected(['database/migrations/2024_01_01_000000_create_orders_table.php']);

        expect($affected)->toBe(['tests/Feature/OrderTest.php']);
    });

    it('falls back to watch patterns when no table usage was recorded at all', function (): void {
        $this->watchPatterns->add(['database/migrations/**' => 'tests/Feature']);

        $graph = new Graph($this->projectRoot);
        $graph->link('tests/Feature/OrderTest.php', 'app/Models/Order.php');

        $affected = $graph->affected(['database/migrations/2024_01_01_000000_create_orders_table.php']);

        expect($affected)->toBe(['tests/Feature/OrderTest.php']);
    });
});

describe('rerun tracking', function (): void {
    it('reruns cached failures via their file', function (): void {
        $graph = new Graph(sys_get_temp_dir());
        $graph->setResult('main', 'Tests\FooTest::it fails', 7, 'boom', 0.1, 1, 'tests/Feature/FooTest.php');
        $graph->setResult('main', 'Tests\BarTest::it passes', 0, '', 0.1, 1, 'tests/Feature/BarTest.php');

        expect($graph->testFilesToRerun('main'))->toBe(['tests/Feature/FooTest.php'])
            ->and($graph->hasUnlocatedTestsToRerun('main'))->toBeFalse();
    });

    it('flags cached failures whose file is unknown', function (): void {
        $graph = new Graph(sys_get_temp_dir());
        $graph->setResult('main', 'Tests\EvalTest::it fails', 7, 'boom', 0.1, 1);

        expect($graph->testFilesToRerun('main'))->toBeEmpty()
            ->and($graph->hasUnlocatedTestsToRerun('main'))->toBeTrue();
    });
});

describe('applyBladeStaticChanges()', function (): void {
    beforeEach(function (): void {
        $this->projectRoot = sys_get_temp_dir().'/pest-tia-blade-'.bin2hex(random_bytes(4));
        mkdir($this->projectRoot.'/resources/views/components/card', 0755, true);

        file_put_contents($this->projectRoot.'/resources/views/page.blade.php', '<div><x-card /></div>');
        file_put_contents($this->projectRoot.'/resources/views/components/card/index.blade.php', '<div>{{ $slot }}</div>');
        file_put_contents($this->projectRoot.'/resources/views/components/unused.blade.php', '<div>never referenced</div>');

        $this->watchPatterns = new WatchPatterns;
        Container::getInstance()->add(WatchPatterns::class, $this->watchPatterns);
    });

    afterEach(function (): void {
        @unlink($this->projectRoot.'/resources/views/page.blade.php');
        @unlink($this->projectRoot.'/resources/views/components/card/index.blade.php');
        @unlink($this->projectRoot.'/resources/views/components/unused.blade.php');
        @rmdir($this->projectRoot.'/resources/views/components/card');
        @rmdir($this->projectRoot.'/resources/views/components');
        @rmdir($this->projectRoot.'/resources/views');
        @rmdir($this->projectRoot.'/resources');
        @rmdir($this->projectRoot);

        Container::getInstance()->add(WatchPatterns::class, new WatchPatterns);
    });

    it('maps an anonymous index component to the views that render it', function (): void {
        $graph = new Graph($this->projectRoot);
        $graph->link('tests/Feature/PageTest.php', 'resources/views/page.blade.php');

        $affected = $graph->affected(['resources/views/components/card/index.blade.php']);

        expect($affected)->toBe(['tests/Feature/PageTest.php']);
    });

    it('falls back to watch patterns for components with no matched usage', function (): void {
        $this->watchPatterns->add(['resources/views/**' => 'tests/Feature']);

        $graph = new Graph($this->projectRoot);
        $graph->link('tests/Feature/PageTest.php', 'resources/views/page.blade.php');

        $affected = $graph->affected(['resources/views/components/unused.blade.php']);

        expect($affected)->toBe(['tests/Feature/PageTest.php']);
    });
});

describe('markKnownTestFiles()', function (): void {
    it('makes a test file with no edges known', function (): void {
        $graph = new Graph(sys_get_temp_dir());

        expect($graph->knowsTest('tests/Unit/ExampleTest.php'))->toBeFalse();

        $graph->markKnownTestFiles(['tests/Unit/ExampleTest.php']);

        expect($graph->knowsTest('tests/Unit/ExampleTest.php'))->toBeTrue();
    });

    it('does not clobber edges of an already-known test file', function (): void {
        $graph = new Graph(sys_get_temp_dir());
        $graph->link('tests/Feature/UserTest.php', 'app/Models/User.php');

        $graph->markKnownTestFiles(['tests/Feature/UserTest.php']);

        // The pre-existing edge survives — the test still depends on the source file.
        $affected = $graph->affected(['app/Models/User.php']);

        expect($affected)->toContain('tests/Feature/UserTest.php');
    });

    it('ignores paths outside the project root', function (): void {
        $graph = new Graph(sys_get_temp_dir());

        $graph->markKnownTestFiles(['/somewhere/else/tests/FooTest.php']);

        expect($graph->knowsTest('/somewhere/else/tests/FooTest.php'))->toBeFalse();
    });
});
