<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Handlers;

class HbMessageHandler extends MessageHandler implements IMessageHandler
{
    public function handle($message): void
    {
        $this->kernel->sendHbMessage($message);
    }
}
