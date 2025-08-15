<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Actions;

use Wundii\JupyterPhpKernel\Requests\Request;
use Wundii\JupyterPhpKernel\Responses\CompleteReplyResponse;

class CompleteAction extends Action
{
    public function run(Request $request): void
    {
        $kernelInfoReplyResponse = new CompleteReplyResponse($request);
        $this->kernel->sendShellMessage($kernelInfoReplyResponse);
    }
}
