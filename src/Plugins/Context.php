<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;

/**
 * @internal
 */
final class Context implements HandlesArguments
{
    public const ENV_CI    = 'ci';
    public const ENV_LOCAL = 'local';

    /**
     * @var \Pest\Plugins\Context
     */
    private static $instance;

    /**
     * @var string
     */
    public $env = 'local';

    public static function getInstance(): Context
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function handleArguments(array $arguments): array
    {
        foreach ($arguments as $index => $argument) {
            if ($argument === '--ci') {
                unset($arguments[$index]);
                self::getInstance()->env = 'ci';
            }
        }

        return array_values($arguments);
    }
}
