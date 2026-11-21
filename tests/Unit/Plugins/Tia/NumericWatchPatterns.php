<?php

declare(strict_types=1);

use Pest\Plugins\Tia\WatchPatterns;

it('matches a watch pattern whose key is a number', function (): void {
    $watchPatterns = new WatchPatterns;
    $watchPatterns->add(['123' => 'tests/Feature']);

    expect($watchPatterns->matchedDirectories(sys_get_temp_dir(), ['123']))->toBe(['tests/Feature']);
});

it('selects the tests under a watch target whose name is a number', function (): void {
    $watchPatterns = new WatchPatterns;
    $watchPatterns->add(['config.txt' => '123']);

    $directories = $watchPatterns->matchedDirectories(sys_get_temp_dir(), ['config.txt']);

    expect($directories)->toBe(['123'])
        ->and($watchPatterns->testsUnderDirectories($directories, ['123/ConfigTest.php', 'tests/OtherTest.php']))
        ->toBe(['123/ConfigTest.php']);
});
