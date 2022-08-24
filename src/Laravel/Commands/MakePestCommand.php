<?php

declare(strict_types=1);

namespace Pest\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Pest\Exceptions\InvalidConsoleArgument;
use Pest\Support\Str;

use function Pest\testDirectory;

use Pest\TestSuite;

/**
 * @internal
 */
final class MakePestCommand extends PestTestCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'make:pest {name : The name of the file} {--unit : Create a unit test} {--dusk : Create a Dusk test} {--test-directory=tests : The name of the tests directory} {--force : Overwrite the existing test file with the same name}';


}
