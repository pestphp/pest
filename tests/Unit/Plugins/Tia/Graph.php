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
    beforeEach(function (): void {
        $this->projectRoot = sys_get_temp_dir().'/pest-tia-rerun-'.bin2hex(random_bytes(4));
        mkdir($this->projectRoot.'/tests/Feature', 0755, true);

        touch($this->projectRoot.'/tests/Feature/FooTest.php');
        touch($this->projectRoot.'/tests/Feature/BarTest.php');
    });

    afterEach(function (): void {
        @unlink($this->projectRoot.'/tests/Feature/FooTest.php');
        @unlink($this->projectRoot.'/tests/Feature/BarTest.php');
        @rmdir($this->projectRoot.'/tests/Feature');
        @rmdir($this->projectRoot.'/tests');
        @rmdir($this->projectRoot);
    });

    it('reruns cached failures via their file', function (): void {
        $graph = new Graph($this->projectRoot);
        $graph->setResult('main', 'Tests\FooTest::it fails', 7, 'boom', 0.1, 1, 'tests/Feature/FooTest.php');
        $graph->setResult('main', 'Tests\BarTest::it passes', 0, '', 0.1, 1, 'tests/Feature/BarTest.php');

        expect($graph->testFilesToRerun('main'))->toBe(['tests/Feature/FooTest.php'])
            ->and($graph->hasUnlocatedTestsToRerun('main'))->toBeFalse();
    });

    it('flags cached failures whose file is unknown', function (): void {
        $graph = new Graph($this->projectRoot);
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

describe('Livewire component views', function (): void {
    beforeEach(function (): void {
        $this->projectRoot = sys_get_temp_dir().'/pest-tia-livewire-sfc-'.bin2hex(random_bytes(4));
        mkdir($this->projectRoot, 0755, true);

        $this->watchPatterns = new WatchPatterns;
        $this->watchPatterns->add(['resources/views/**' => 'tests/Feature']);
        Container::getInstance()->add(WatchPatterns::class, $this->watchPatterns);
    });

    afterEach(function (): void {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->projectRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            assert($file instanceof SplFileInfo);

            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        @rmdir($this->projectRoot);

        Container::getInstance()->add(WatchPatterns::class, new WatchPatterns);
    });

    it('maps a changed component to generated views from different workers', function (): void {
        $ordersPath = 'resources/views/components/orders.blade.php';
        $usersPath = 'resources/views/components/admin/⚡users.blade.php';
        $ordersHash = substr(md5(DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $ordersPath)), 0, 8);
        $usersHash = substr(md5(DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $usersPath)), 0, 8);

        mkdir(dirname($this->projectRoot.'/'.$ordersPath), 0755, true);
        mkdir(dirname($this->projectRoot.'/'.$usersPath), 0755, true);
        file_put_contents($this->projectRoot.'/'.$ordersPath, '<div>Orders</div>');
        file_put_contents($this->projectRoot.'/'.$usersPath, '<div>Users</div>');

        $graph = new Graph($this->projectRoot);
        $graph->link('tests/Feature/OrdersTest.php', 'storage/framework/views/test_1/livewire/views/'.$ordersHash.'.blade.php');
        $graph->link('tests/Feature/UsersTest.php', 'storage/framework/views/test_2/livewire/views/'.$usersHash.'.blade.php');

        expect($graph->affected([$ordersPath]))->toBe(['tests/Feature/OrdersTest.php']);
    });

    it('maps documented SFC locations', function (string $sourcePath): void {
        $hash = substr(md5(DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $sourcePath)), 0, 8);

        mkdir(dirname($this->projectRoot.'/'.$sourcePath), 0755, true);
        file_put_contents($this->projectRoot.'/'.$sourcePath, '<div>Component</div>');

        $graph = new Graph($this->projectRoot);
        $graph->link('tests/Feature/ComponentTest.php', 'storage/framework/views/test_3/livewire/views/'.$hash.'.blade.php');
        $graph->link('tests/Feature/UnrelatedTest.php', 'storage/framework/views/test_4/livewire/views/deadbeef.blade.php');

        expect($graph->affected([$sourcePath]))->toBe(['tests/Feature/ComponentTest.php']);
    })->with([
        'default pages namespace' => ['resources/views/pages/post/⚡create.blade.php'],
        'default layouts namespace without emoji' => ['resources/views/layouts/app.blade.php'],
        'additional component location' => ['resources/views/widgets/orders.blade.php'],
    ]);

    it('maps documented MFC locations using the component directory hash', function (string $componentDirectory, string $viewPath): void {
        $hash = substr(md5(DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $componentDirectory)), 0, 8);
        $classPath = $componentDirectory.'/'.basename($viewPath, '.blade.php').'.php';

        mkdir($this->projectRoot.'/'.$componentDirectory, 0755, true);
        file_put_contents($this->projectRoot.'/'.$viewPath, '<div>Component</div>');
        file_put_contents($this->projectRoot.'/'.$classPath, '<?php');

        $graph = new Graph($this->projectRoot);
        $graph->link('tests/Feature/ComponentTest.php', 'storage/framework/views/test_5/livewire/views/'.$hash.'.blade.php');
        $graph->link('tests/Feature/UnrelatedTest.php', 'storage/framework/views/test_6/livewire/views/deadbeef.blade.php');

        expect($graph->affected([$viewPath]))->toBe(['tests/Feature/ComponentTest.php']);
    })->with([
        'default component location' => ['resources/views/components/post/⚡create', 'resources/views/components/post/⚡create/create.blade.php'],
        'default component location without emoji' => ['resources/views/components/post/create', 'resources/views/components/post/create/create.blade.php'],
        'index convention' => ['resources/views/components/post/⚡index', 'resources/views/components/post/⚡index/index.blade.php'],
    ]);

    it('preserves direct view edges for class-based components', function (): void {
        $viewPath = 'resources/views/livewire/create-post.blade.php';

        mkdir(dirname($this->projectRoot.'/'.$viewPath), 0755, true);
        file_put_contents($this->projectRoot.'/'.$viewPath, '<div>Create post</div>');

        $graph = new Graph($this->projectRoot);
        $graph->link('tests/Feature/CreatePostTest.php', $viewPath);

        expect($graph->affected([$viewPath]))->toBe(['tests/Feature/CreatePostTest.php']);
    });

    it('falls back to watch patterns when no generated view matches', function (): void {
        $ordersPath = 'resources/views/components/orders.blade.php';
        $ordersHash = substr(md5(DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $ordersPath)), 0, 8);
        $unmatchedPath = 'resources/views/components/unmatched.blade.php';

        mkdir(dirname($this->projectRoot.'/'.$ordersPath), 0755, true);
        file_put_contents($this->projectRoot.'/'.$ordersPath, '<div>Orders</div>');
        file_put_contents($this->projectRoot.'/'.$unmatchedPath, '<div>Unmatched</div>');

        $graph = new Graph($this->projectRoot);
        $graph->link('tests/Feature/OrdersTest.php', 'storage/framework/views/test_1/livewire/views/'.$ordersHash.'.blade.php');
        $graph->link('tests/Feature/UsersTest.php', 'storage/framework/views/test_2/livewire/views/deadbeef.blade.php');

        expect($graph->affected([$unmatchedPath]))
            ->toBe(['tests/Feature/OrdersTest.php', 'tests/Feature/UsersTest.php']);
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
