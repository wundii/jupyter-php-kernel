<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Actions;

use Wundii\JupyterPhpKernel\Requests\Request;
use Wundii\JupyterPhpKernel\Responses\KernelInfoReplyResponse;

class KernelInfoAction extends Action
{
    protected function run(Request $request)
    {
        $kernelInfoReplyResponse = new KernelInfoReplyResponse($request);
        $this->kernel->sendShellMessage($kernelInfoReplyResponse);
    }
}
