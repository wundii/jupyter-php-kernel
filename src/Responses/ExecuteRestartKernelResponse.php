<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Responses;

use Wundii\JupyterPhpKernel\Requests\Request;

class ExecuteRestartKernelResponse extends Response
{
    public function __construct(Request $request)
    {
        $content = [
            'restart' => true,
        ];

        parent::__construct(self::EXECUTE_RESULT, $request, $content);
    }
}
