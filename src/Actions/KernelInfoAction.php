<?php

namespace Wundii\JupyterPhpKernel\Actions;

use Wundii\JupyterPhpKernel\Requests\Request;
use Wundii\JupyterPhpKernel\Responses\KernelInfoReplyResponse;

class KernelInfoAction extends Action
{
    protected function run(Request $request)
    {
        $response = new KernelInfoReplyResponse($request);
        $this->kernel->sendShellMessage($response);
    }
}
