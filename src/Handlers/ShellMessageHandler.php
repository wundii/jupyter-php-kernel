<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Handlers;

use Wundii\JupyterPhpKernel\Actions\CompleteAction;
use Wundii\JupyterPhpKernel\Actions\ExecuteAction;
use Wundii\JupyterPhpKernel\Actions\KernelInfoAction;
use Wundii\JupyterPhpKernel\Requests\Request;

class ShellMessageHandler extends MessageHandler implements IRequestHandler
{
    public const COMPLETE_REQUEST = 'complete_request';
    public const EXECUTE_REQUEST = 'execute_request';
    public const KERNEL_INFO_REQUEST = 'kernel_info_request';


    protected const ACTION_MAP = [
        self::COMPLETE_REQUEST => CompleteAction::class,
        self::EXECUTE_REQUEST => ExecuteAction::class,
        self::KERNEL_INFO_REQUEST => KernelInfoAction::class,

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
