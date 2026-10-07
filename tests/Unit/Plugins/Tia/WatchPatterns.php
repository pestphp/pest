<?php

declare(strict_types=1);

use Pest\Plugins\Tia\Configuration;
use Pest\Plugins\Tia\Graph;
use Pest\Plugins\Tia\WatchDefaults\Inertia;
use Pest\Plugins\Tia\WatchDefaults\Livewire;
use Pest\Plugins\Tia\WatchDefaults\Php;
use Pest\Plugins\Tia\WatchPatterns;
use Pest\Support\Container;
use Tests\Fixtures\Tia\Project;

beforeEach(function (): void {
    $this->watchPatterns = new WatchPatterns;
    $defaults = [];

    foreach ([new Php, new Inertia, new Livewire] as $provider) {
        foreach ($provider->defaults(getcwd(), 'tests') as $pattern => $directories) {
            $defaults[$pattern] = array_values(array_unique([
                ...($defaults[$pattern] ?? []),
                ...$directories,
            ]));
        }
    }

    new ReflectionProperty(WatchPatterns::class, 'defaultPatterns')->setValue($this->watchPatterns, $defaults);
    Container::getInstance()->add(WatchPatterns::class, $this->watchPatterns);
});

afterEach(function (): void {
    Project::destroyAll();
    Container::getInstance()->add(WatchPatterns::class, new WatchPatterns);
});

it('keeps the built-in fallbacks enabled by default', function (): void {
    expect($this->watchPatterns->matchedDirectories(getcwd(), [
        'resources/js/Pages/Enroll.vue',
        '.env.testing',
    ]))->toBe(['tests']);
});

it('excludes every overlapping default for a matching changed path', function (): void {
    (new Configuration)->withoutDefaultWatchPatterns(['resources/js/**']);

    expect($this->watchPatterns->matchedDirectories(getcwd(), [
        'resources/js/Pages/Enroll.vue',
        'resources/js/app.js',
    ]))->toBeEmpty()
        ->and($this->watchPatterns->matchedDirectories(getcwd(), ['.env.testing']))->toBe(['tests']);
});

it('retains custom watches when their matching defaults are excluded', function (): void {
    $configuration = new Configuration;

    expect($configuration->withoutDefaultWatchPatterns(['resources/js/**']))->toBe($configuration);

    $configuration->watch(['resources/js/Pages/Enroll.vue' => 'tests/Feature/Enrollment']);

    expect($this->watchPatterns->matchedDirectories(getcwd(), ['resources/js/Pages/Enroll.vue']))
        ->toBe(['tests/Feature/Enrollment']);
});

it('keeps watch additive when defaults are enabled', function (): void {
    (new Configuration)->watch(['resources/js/** !*.php' => 'tests/Browser']);

    expect($this->watchPatterns->matchedDirectories(getcwd(), ['resources/js/app.js']))
        ->toEqualCanonicalizing(['tests', 'tests/Browser']);
});

it('disables all defaults without disabling custom watches', function (): void {
    (new Configuration)
        ->watch(['resources/js/**' => 'tests/Browser'])
        ->withoutDefaultWatchPatterns();

    expect($this->watchPatterns->matchedDirectories(getcwd(), ['.env.testing']))->toBeEmpty()
        ->and($this->watchPatterns->matchedDirectories(getcwd(), ['resources/js/app.js']))
        ->toBe(['tests/Browser']);
});

it('retains exclusions when defaults are loaded after configuration', function (): void {
    (new Configuration)->withoutDefaultWatchPatterns();
    $this->watchPatterns->useDefaults(getcwd());

    expect($this->watchPatterns->matchedDirectories(getcwd(), ['.env.testing']))->toBeEmpty();
});

it('accumulates exclusions from repeated calls', function (): void {
    (new Configuration)
        ->withoutDefaultWatchPatterns(['resources/js/**'])
        ->withoutDefaultWatchPatterns(['.env.testing']);

    expect($this->watchPatterns->matchedDirectories(getcwd(), [
        'resources/js/app.js',
        '.env.testing',
    ]))->toBeEmpty()
        ->and($this->watchPatterns->matchedDirectories(getcwd(), ['docker-compose.yml']))->toBe(['tests']);
});

it('does not disable defaults for an empty exclusion list', function (): void {
    (new Configuration)->withoutDefaultWatchPatterns([]);

    expect($this->watchPatterns->matchedDirectories(getcwd(), ['.env.testing']))->toBe(['tests']);
});

it('supports exclusions within a changed-path pattern', function (): void {
    (new Configuration)->withoutDefaultWatchPatterns(['resources/js/** !resources/js/critical/**']);

    expect($this->watchPatterns->matchedDirectories(getcwd(), ['resources/js/Pages/Enroll.vue']))->toBeEmpty()
        ->and($this->watchPatterns->matchedDirectories(getcwd(), ['resources/js/critical/auth.js']))->toBe(['tests']);
});

it('restores default watches and removes custom watches on reset', function (): void {
    (new Configuration)
        ->withoutDefaultWatchPatterns()
        ->watch(['custom/**' => 'tests/Feature']);

    $this->watchPatterns->reset();
    $this->watchPatterns->useDefaults(getcwd());

    expect($this->watchPatterns->matchedDirectories(getcwd(), ['.env.testing']))->toBe(['tests'])
        ->and($this->watchPatterns->matchedDirectories(getcwd(), ['custom/file.txt']))->toBeEmpty();
});

it('does not select unrelated tests for an excluded unknown Vue page', function (): void {
    $project = Project::withoutGit();
    $project->write('resources/js/Pages/Enroll.vue', '<template>Enroll</template>');
    $graph = new Graph($project->path());
    $graph->replaceJsFileToComponents(['resources/js/Pages/Enroll.vue' => ['Enroll']]);
    $graph->link('tests/Unit/CalculatorTest.php', 'app/Calculator.php');
    $graph->link('tests/Unit/GreeterTest.php', 'app/Greeter.php');

    expect($graph->affected(['resources/js/Pages/Enroll.vue']))
        ->toBe(['tests/Unit/CalculatorTest.php', 'tests/Unit/GreeterTest.php']);

    (new Configuration)->withoutDefaultWatchPatterns(['resources/js/**']);

    expect($graph->affected(['resources/js/Pages/Enroll.vue']))->toBeEmpty();
});

it('retains recorded Inertia dependencies when default watches are disabled', function (): void {
    $project = Project::withoutGit();
    $project->write('resources/js/Pages/Enroll.vue', '<template>Enroll</template>');
    $graph = new Graph($project->path());
    $graph->link('tests/Unit/CalculatorTest.php', 'app/Calculator.php');
    $graph->link('tests/Unit/GreeterTest.php', 'app/Greeter.php');
    $graph->replaceTestInertiaComponents(['tests/Unit/CalculatorTest.php' => ['Enroll']]);

    (new Configuration)->withoutDefaultWatchPatterns();

    expect($graph->affected(['resources/js/Pages/Enroll.vue']))->toBe(['tests/Unit/CalculatorTest.php']);
});

it('retains recorded migration tables when default watches are disabled', function (): void {
    $project = Project::withoutGit();
    $project->write('database/migrations/create_orders.php', '<?php Schema::create("orders", function () {});');
    $graph = new Graph($project->path());
    $graph->link('tests/Unit/CalculatorTest.php', 'app/Calculator.php');
    $graph->link('tests/Unit/GreeterTest.php', 'app/Greeter.php');
    $graph->replaceTestTables([
        'tests/Unit/CalculatorTest.php' => ['orders'],
        'tests/Unit/GreeterTest.php' => ['users'],
    ]);

    (new Configuration)->withoutDefaultWatchPatterns();

    expect($graph->affected(['database/migrations/create_orders.php']))->toBe(['tests/Unit/CalculatorTest.php']);
});

it('uses custom mappings for an unknown Vue page with defaults disabled', function (): void {
    $project = Project::withoutGit();
    $project->write('resources/js/Pages/Enroll.vue', '<template>Enroll</template>');
    $graph = new Graph($project->path());
    $graph->replaceJsFileToComponents(['resources/js/Pages/Enroll.vue' => ['Enroll']]);
    $graph->link('tests/Unit/CalculatorTest.php', 'app/Calculator.php');
    $graph->link('tests/Unit/GreeterTest.php', 'app/Greeter.php');

    (new Configuration)
        ->withoutDefaultWatchPatterns(['resources/js/**'])
        ->watch(['resources/js/Pages/Enroll.vue' => 'tests/Unit/CalculatorTest.php']);

    expect($graph->affected(['resources/js/Pages/Enroll.vue']))->toBe(['tests/Unit/CalculatorTest.php']);
});
