<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Handlers;

use Wundii\JupyterPhpKernel\Requests\Request;

interface IRequestHandler
{
    public function handle(Request $request);
}
