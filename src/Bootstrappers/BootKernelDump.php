<?php

declare(strict_types=1);

namespace Pest\Bootstrappers;

use Pest\Contracts\Bootstrapper;
use Pest\KernelDump;
use Pest\Support\Container;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final readonly class BootKernelDump implements Bootstrapper
{
    /**
     * Creates a new Boot Kernel Dump instance.
     */
    public function __construct(
        private OutputInterface $output,
    ) {
        // ...
    }

    /**
     * Boots the kernel dump.
     */
    public function boot(): void
    {
        Container::getInstance()->add(KernelDump::class, $kernelDump = new KernelDump(
            $this->output,
        ));

        $kernelDump->enable();
    }
}
