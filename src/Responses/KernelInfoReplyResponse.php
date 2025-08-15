<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Responses;

use Wundii\JupyterPhpKernel\Requests\Request;

class KernelInfoReplyResponse extends Response
{
    public function __construct(Request $request)
    {
        $content = [
            'protocol_version' => '5.3',
            'implementation' => 'jupyter-php',
            'implementation_version' => '1.0.0',
            'banner' => 'Jupyter-PHP Kernel',
            'language_info' => [
                'name' => 'php',
                'version' => PHP_VERSION,
                'mimetype' => 'text/x-php',
                'file_extension' => '.php',
                'pygments_lexer' => 'php',
                'codemirror_mode' => 'php',
                'nbconvert_exporter' => 'php',
            ],
            'help_links' => [
                [
                    'text' => 'PHP Documentation',
                    'url' => 'https://www.php.net/docs.php',
                ],
                [
                    'text' => 'PHP Manual',
                    'url' => 'https://www.php.net/manual/de/',
                ],
            ],
            'status' => 'ok',
        ];

        parent::__construct(self::KERNEL_INFO_REPLY, $request, $content);
    }
}
