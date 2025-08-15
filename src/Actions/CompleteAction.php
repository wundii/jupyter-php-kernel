<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Actions;

use Wundii\JupyterPhpKernel\Requests\Request;
use Wundii\JupyterPhpKernel\Responses\CompleteReplyResponse;

class CompleteAction extends Action
{
    protected function run(Request $request): void
    {
        $completeReplyResponse = new CompleteReplyResponse($request);
        $this->kernel->sendShellMessage($completeReplyResponse);
    }
}
