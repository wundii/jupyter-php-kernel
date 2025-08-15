<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Handlers;

use Wundii\JupyterPhpKernel\Kernel;

abstract class MessageHandler
{
    protected Kernel $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }
}
