<?php

declare(strict_types=1);

namespace Pest\Support;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class JsonOutput implements OutputInterface
{
    /**
     * The buffered output text.
     *
     * @var list<string>
     */
    private array $buffer = [];

    /**
     * Creates a new JsonOutput instance.
     */
    public function __construct(private readonly ConsoleOutput $decorated)
    {
        ob_start();
        register_shutdown_function($this->shutdownHandler(...));
    }

    /**
     * Whether JSON output mode is active.
     */
    public static function isActive(): bool
    {
        return ($_SERVER['PEST_JSON_OUTPUT'] ?? '') === 'true';
    }

    /**
     * Writes a JSON string directly to the real output.
     */
    public function writeJson(string $json): void
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        $this->buffer = [];
        $this->decorated->writeln($json);
    }

    /**
     * {@inheritDoc}
     *
     * @param  iterable<string>|string  $messages
     */
    public function write(string|iterable $messages, bool $newline = false, int $options = 0): void
    {
        $this->bufferMessages($messages);
    }

    /**
     * {@inheritDoc}
     *
     * @param  iterable<string>|string  $messages
     */
    public function writeln(string|iterable $messages, int $options = 0): void
    {
        $this->bufferMessages($messages);
    }

    /**
     * {@inheritDoc}
     */
    public function setVerbosity(int $level): void
    {
        $this->decorated->setVerbosity($level);
    }

    /**
     * {@inheritDoc}
     */
    public function getVerbosity(): int
    {
        return $this->decorated->getVerbosity();
    }

    /**
     * {@inheritDoc}
     */
    public function isQuiet(): bool
    {
        return $this->decorated->isQuiet();
    }

    /**
     * {@inheritDoc}
     */
    public function isVerbose(): bool
    {
        return $this->decorated->isVerbose();
    }

    /**
     * {@inheritDoc}
     */
    public function isVeryVerbose(): bool
    {
        return $this->decorated->isVeryVerbose();
    }

    /**
     * {@inheritDoc}
     */
    public function isDebug(): bool
    {
        return $this->decorated->isDebug();
    }

    /**
     * {@inheritDoc}
     */
    public function setDecorated(bool $decorated): void
    {
        $this->decorated->setDecorated($decorated);
    }

    /**
     * {@inheritDoc}
     */
    public function isDecorated(): bool
    {
        return $this->decorated->isDecorated();
    }

    /**
     * {@inheritDoc}
     */
    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        $this->decorated->setFormatter($formatter);
    }

    /**
     * {@inheritDoc}
     */
    public function getFormatter(): OutputFormatterInterface
    {
        return $this->decorated->getFormatter();
    }

    /**
     * Appends messages to the internal buffer.
     *
     * @param  iterable<string>|string  $messages
     */
    private function bufferMessages(string|iterable $messages): void
    {
        if (is_iterable($messages)) {
            foreach ($messages as $message) {
                $this->buffer[] = (string) $message;
            }
        } else {
            $this->buffer[] = $messages;
        }
    }

    /**
     * Shutdown handler that emits error JSON if no output was buffered after the last emission.
     */
    private function shutdownHandler(): void
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        $buffered = implode("\n", array_filter($this->buffer));
        $message = preg_replace('/\x1b\[[0-9;]*m/', '', $buffered) ?? $buffered;
        $message = trim($message);

        if ($message === '') {
            return;
        }

        $this->writeJson(json_encode([
            'status' => 'error',
            'message' => $message,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
