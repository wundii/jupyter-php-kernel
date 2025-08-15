<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Actions;

use Wundii\JupyterPhpKernel\Requests\Request;
use Wundii\JupyterPhpKernel\Responses\InspectReplyResponse;

class InspectAction extends Action
{
    public function run(Request $request): void
    {
        $kernelInfoReplyResponse = new InspectReplyResponse($request);
        $this->kernel->sendShellMessage($kernelInfoReplyResponse);
    }
}
