<?php

declare(strict_types=1);

namespace Pest\Plugins\Actions;

use Pest\Contracts\Plugins;
use Pest\Plugin\Loader;

/**
 * @internal
 */
final class CallsHandleArguments
{
    /**
     * Executes the Plugin action.
     *
     * Transform the input arguments by passing it to the relevant plugins.
     *
     * @param  array<int, string>  $argv
     * @return array<int, string>
     */
    public static function execute(array $argv): array
    {
        $plugins = Loader::getPlugins(Plugins\HandlesArguments::class);

        /** @var Plugins\HandlesArguments $plugin */
        foreach ($plugins as $plugin) {
            //            $argvBackup = $argv;
            //            $before = array_filter($argv, fn($t) => $t === '--exclude-group' || $t === '--exclude-group=integration'|| $t === '--exclude-group=container');
            //            if (count($before) !== 2) {
            //                echo "You need to specify --exclude-group=groupname";
            //                echo $plugin::class;
            // //                var_dump($argv);
            //                exit('ASDFasdfasdfasfd');
            //            }
            $argv = $plugin->handleArguments($argv);
            //            $after = array_filter($argv, fn($t) => $t === '--exclude-group' || $t === '--exclude-group=integration'|| $t === '--exclude-group=container');
            //            if (count($before) !== count($after)) {
            //                echo sprintf("Plugin %s removed an argument illegally \r\n", $plugin::class) . PHP_EOL;
            //                echo $plugin::class;
            // //                var_dump($argv);
            // //                dd($argvBackup, $argv, $before, $after);
            // //                $argv = $argvBackup;
            //            }

        }

        return $argv;
    }
}
