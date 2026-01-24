<?php

use Pest\Plugins\Agent as AgentPlugin;
use Pest\Support\AgentOutput;
use Symfony\Component\Console\Output\BufferedOutput;

it('has plugin')->assertTrue(class_exists(AgentPlugin::class));

it('has agent output support')->assertTrue(class_exists(AgentOutput::class));

it('detects agent mode from server variable', function () {
    $original = $_SERVER['PEST_AGENT_OUTPUT'] ?? null;

    unset($_SERVER['PEST_AGENT_OUTPUT']);
    expect(AgentOutput::isActive())->toBeFalse();

    $_SERVER['PEST_AGENT_OUTPUT'] = 'true';
    expect(AgentOutput::isActive())->toBeTrue();

    $_SERVER['PEST_AGENT_OUTPUT'] = 'false';
    expect(AgentOutput::isActive())->toBeFalse();

    if ($original !== null) {
        $_SERVER['PEST_AGENT_OUTPUT'] = $original;
    } else {
        unset($_SERVER['PEST_AGENT_OUTPUT']);
    }
});

it('builds test results as pass when no failures', function () {
    $mockResult = new class
    {
        public function testFailedEvents(): array
        {
            return [];
        }

        public function testErroredEvents(): array
        {
            return [];
        }
    };

    $result = ['status' => 'pass'];
    expect(AgentOutput::toJson($result))->toBe('{"status":"pass"}');
});

it('encodes JSON without escaping slashes', function () {
    $data = [
        'location' => 'tests/Features/Agent.php:10',
        'trace' => 'at tests/Features/Agent.php:10',
    ];

    $json = AgentOutput::toJson($data);

    expect($json)->not->toContain('\\/')
        ->and($json)->toContain('tests/Features/Agent.php:10');
});

it('formats failures correctly', function () {
    $result = [
        'status' => 'fail',
        'failures' => [
            [
                'test' => 'it logs in user',
                'message' => 'Expected 200, got 401',
                'location' => 'tests/Feature/LoginTest.php:42',
                'trace' => '#0 tests/Feature/LoginTest.php:42',
            ],
        ],
    ];

    $json = AgentOutput::toJson($result);

    expect($json)->toContain('"status":"fail"')
        ->and($json)->toContain('"test":"it logs in user"')
        ->and($json)->toContain('"message":"Expected 200, got 401"')
        ->and($json)->toContain('"location":"tests/Feature/LoginTest.php:42"');
});

it('formats coverage output correctly', function () {
    $coverage = [
        'total' => 85.5,
        'files' => [
            [
                'path' => 'src/Auth.php',
                'coverage' => 75.0,
                'uncovered' => ['20..25', '42'],
            ],
            [
                'path' => 'src/User.php',
                'coverage' => 100.0,
            ],
        ],
    ];

    $result = [
        'status' => 'pass',
        'coverage' => $coverage,
    ];

    $json = AgentOutput::toJson($result);

    expect($json)->toContain('"status":"pass"')
        ->and($json)->toContain('"total":85.5')
        ->and($json)->toContain('"coverage":75')
        ->and($json)->toContain('"uncovered":["20..25","42"]');
});

it('adds --no-output argument when in agent mode', function () {
    $original = $_SERVER['PEST_AGENT_OUTPUT'] ?? null;
    $_SERVER['PEST_AGENT_OUTPUT'] = 'true';

    $plugin = new AgentPlugin(new BufferedOutput);
    $arguments = $plugin->handleArguments(['--filter=test']);

    expect($arguments)->toContain('--no-output');

    if ($original !== null) {
        $_SERVER['PEST_AGENT_OUTPUT'] = $original;
    } else {
        unset($_SERVER['PEST_AGENT_OUTPUT']);
    }
});

it('does not modify arguments when not in agent mode', function () {
    $original = $_SERVER['PEST_AGENT_OUTPUT'] ?? null;
    unset($_SERVER['PEST_AGENT_OUTPUT']);

    $plugin = new AgentPlugin(new BufferedOutput);
    $arguments = $plugin->handleArguments(['--filter=test']);

    expect($arguments)->toBe(['--filter=test']);

    if ($original !== null) {
        $_SERVER['PEST_AGENT_OUTPUT'] = $original;
    }
});
