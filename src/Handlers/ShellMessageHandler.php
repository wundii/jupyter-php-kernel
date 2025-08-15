<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Handlers;

use Wundii\JupyterPhpKernel\Actions\ExecuteAction;
use Wundii\JupyterPhpKernel\Actions\KernelInfoAction;
use Wundii\JupyterPhpKernel\Requests\Request;

class ShellMessageHandler extends MessageHandler implements IRequestHandler
{
    public const KERNEL_INFO_REQUEST = 'kernel_info_request';
    public const EXECUTE_REQUEST = 'execute_request';

    protected const ACTION_MAP = [
        self::KERNEL_INFO_REQUEST => KernelInfoAction::class,
        self::EXECUTE_REQUEST => ExecuteAction::class,
    ];

    public function handle(Request $request): void
    {
        $msg_type = $request->header['msg_type'];
        if (!isset(self::ACTION_MAP[$msg_type])) {
            return;
        }

        $action_class = self::ACTION_MAP[$msg_type];
        $action = new $action_class($this->kernel);
        $action->execute($request);
    }
}
