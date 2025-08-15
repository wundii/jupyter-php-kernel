<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Handlers;

interface IMessageHandler
{
    public function handle($message);
}
