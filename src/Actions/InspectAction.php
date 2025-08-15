<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Actions;

use Wundii\JupyterPhpKernel\Requests\Request;
use Wundii\JupyterPhpKernel\Responses\InspectReplyResponse;

class InspectAction extends Action
{
    protected function run(Request $request): void
    {
        $inspectReplyResponse = new InspectReplyResponse($request);
        $this->kernel->sendShellMessage($inspectReplyResponse);
    }
}
