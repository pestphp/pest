<?php

declare(strict_types=1);

namespace Pest\Support;

use PHPUnit\Util\Exception;
use PHPUnit\Util\Filesystem;

abstract class Printer implements \PHPUnit\Util\Printer
{
    /** @var resource|false */
    private $stream;

    private bool $isPhpStream;

    private bool $isOpen;

    private function __construct(string $out)
    {
        if (str_starts_with($out, 'socket://')) {
            $tmp = explode(':', str_replace('socket://', '', $out));

            if (count($tmp) !== 2) {
                throw new Exception(sprintf('"%s" does not match "socket://hostname:port" format', $out));
            }

            $this->stream = fsockopen($tmp[0], (int) $tmp[1]);
            $this->isOpen = true;

            return;
        }

        $this->isPhpStream = str_starts_with($out, 'php://');

        if (! $this->isPhpStream && ! Filesystem::createDirectory(dirname($out))) {
            throw new Exception(sprintf('Directory "%s" was not created', dirname($out)));
        }

        $this->stream = fopen($out, 'wb');
        $this->isOpen = true;
    }

    final public function print(string $buffer): void
    {
        assert($this->isOpen);
        assert($this->stream !== false);

        fwrite($this->stream, $buffer);
    }

    final public function flush(): void
    {
        if ($this->isOpen && $this->isPhpStream && $this->stream !== false) {
            fclose($this->stream);

            $this->isOpen = false;
        }
    }
}
