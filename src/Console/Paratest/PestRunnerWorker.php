<?php

declare(strict_types=1);

namespace Pest\Console\Paratest;

use Symfony\Component\Console\Output\OutputInterface;
use function array_merge;
use const DIRECTORY_SEPARATOR;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\WorkerCrashedException;
use RuntimeException;
use function strlen;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * @internal
 */
final class PestRunnerWorker
{
    /** @var ExecutableTest */
    private $executableTest;

    /** @var Process */
    private $process;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var array<string>
     */
    public static $additionalOutput = [];

    public function __construct(OutputInterface $output, ExecutableTest $executableTest, Options $options, int $token)
    {
        $this->output = $output;
        $this->executableTest = $executableTest;

        $phpFinder = new PhpExecutableFinder();
        $args      = [$phpFinder->find(false)];
        $args      = array_merge($args, $phpFinder->findArguments());

        if (($passthruPhp = $options->passthruPhp()) !== null) {
            $args = array_merge($args, $passthruPhp);
        }

        $args = array_merge(
            $args,
            $this->executableTest->commandArguments(
                $this->getPestBinary($options),
                $options->filtered(),
                $options->passthru()
            ),
            ['--isInParallel'],
        );

        $this->process = new Process($args, $options->cwd(), $options->fillEnvWithTokens($token));

        $cmd = $this->process->getCommandLine();
        $this->assertValidCommandLineLength($cmd);
        $this->executableTest->setLastCommand($cmd);
    }

    public function getExecutableTest(): ExecutableTest
    {
        return $this->executableTest;
    }

    /**
     * Executes the test by creating a separate process.
     */
    public function run(): void
    {
        $this->process->start();
    }

    /**
     * Check if the process has terminated.
     */
    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    /**
     * Stop the process and return it's
     * exit code.
     */
    public function stop(): ?int
    {
        $exitCode = $this->process->stop();
        $this->handleOutput($this->process->getOutput());
        return $exitCode;
    }

    private function handleOutput(string $output)
    {
        $matches = [];
        preg_match_all("/^\\n/m", $output, $matches, PREG_OFFSET_CAPTURE);

        $overview = substr($output, 0, $matches[0][1][1]);
        $this->output->write($overview);

        if (count($matches[0]) > 3) {
            $summarySectionIndex = count($matches[0]) - 2;

            static::$additionalOutput[] = substr(
                $output,
                $matches[0][1][1],
                $matches[0][$summarySectionIndex][1] - $matches[0][1][1],
            );
        }
    }

    /**
     * Assert that command line length is valid.
     *
     * In some situations process command line can became too long when combining different test
     * cases in single --filter arguments so it's better to show error regarding that to user
     * and propose him to decrease max batch size.
     *
     * @param string $cmd Command line
     *
     * @throws RuntimeException on too long command line
     *
     * @codeCoverageIgnore
     */
    private function assertValidCommandLineLength(string $cmd): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return;
        }

        // symfony's process wrapper
        $cmd = 'cmd /V:ON /E:ON /C "(' . $cmd . ')';
        if (strlen($cmd) > 32767) {
            throw new RuntimeException('Command line is too long, try to decrease max batch size');
        }
    }

    private function getPestBinary(Options $options): string
    {
        $paths = [
            implode(DIRECTORY_SEPARATOR, [$options->cwd(), 'bin', 'pest']),
            implode(DIRECTORY_SEPARATOR, [$options->cwd(), 'vendor', 'bin', 'pest']),
        ];

        return file_exists($paths[0]) ? $paths[0] : $paths[1];
    }

    public function getWorkerCrashedException(?Throwable $previousException = null): WorkerCrashedException
    {
        return WorkerCrashedException::fromProcess(
            $this->process,
            $this->process->getCommandLine(),
            $previousException
        );
    }
}
