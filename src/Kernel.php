<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel;

use Psy\Configuration;
use Psy\Shell;
use Ramsey\Uuid\Uuid;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\ZMQ\Context;
use React\ZMQ\SocketWrapper;
use Wundii\JupyterPhpKernel\Handlers\HbMessageHandler;
use Wundii\JupyterPhpKernel\Handlers\IMessageHandler;
use Wundii\JupyterPhpKernel\Handlers\IRequestHandler;
use Wundii\JupyterPhpKernel\Handlers\ShellMessageHandler;
use Wundii\JupyterPhpKernel\Requests\Request;
use Wundii\JupyterPhpKernel\Responses\Response;
use Wundii\JupyterPhpKernel\Responses\StatusResponse;
use ZMQ;

class Kernel
{
    public ConnectionDetails $connection_details;
    public string $session_id;

    public int $execution_count = 0;
    public Shell $shell;
    private LoopInterface $loop;
    private Context $context;

    private SocketWrapper $iopub_socket;
    private SocketWrapper $shell_socket;
    private SocketWrapper $hb_socket;

    public function __construct(ConnectionDetails $connectionDetails)
    {
        $this->connection_details = $connectionDetails;
        $this->session_id = Uuid::uuid4()->toString();
        $this->shell = new Shell($this->getConfig());
    }

    public function run(): void
    {
        $this->loop = Factory::create();
        $this->context = new Context($this->loop);

        $this->shell_socket = $this->createSocket(
            ZMQ::SOCKET_ROUTER,
            $this->connection_details->shell_address,
            new ShellMessageHandler($this)
        );
        $this->iopub_socket = $this->createSocket(ZMQ::SOCKET_PUB, $this->connection_details->iopub_address);
        $this->hb_socket = $this->createSocket(
            ZMQ::SOCKET_REP,
            $this->connection_details->hb_address,
            new HbMessageHandler($this)
        );
        $this->createSocket(ZMQ::SOCKET_ROUTER, $this->connection_details->stdin_address);
        $this->createSocket(ZMQ::SOCKET_ROUTER, $this->connection_details->control_address);

        $this->loop->run();
    }

    public function sendStatusMessage(string $status, Request $request): void
    {
        $this->sendIOPubMessage(new StatusResponse($status, $request));
    }

    public function sendIOPubMessage(Response $response): void
    {
        $message = $response->toMessage($this->connection_details->key, $this->connection_details->signature_scheme);
        /** @phpstan-ignore argument.type */
        $this->iopub_socket->send($message);
    }

    public function sendShellMessage(Response $response): void
    {
        $message = $response->toMessage($this->connection_details->key, $this->connection_details->signature_scheme);
        /** @phpstan-ignore argument.type */
        $this->shell_socket->send($message);
    }

    public function sendHbMessage($message): void
    {
        $this->hb_socket->send($message);
    }

    protected function createSocket($type, $address, $handler = null)
    {
        $socketWrapper = $this->context->getSocket($type);
        $socketWrapper->bind("tcp://{$address}");

        $socketWrapper->on('error', function ($e): void {
            echo $e->getMessage();
        });

        $socketWrapper->on('messages', function ($message) use ($handler): void {
            if ($handler === null) {
                return;
            }

            if ($handler instanceof IMessageHandler) {
                $handler->handle($message);
            } elseif ($handler instanceof IRequestHandler) {
                $handler->handle(new Request($message, $this->session_id));
            }
        });

        return $socketWrapper;
    }

    private function getConfig(array $config = []): \Psy\Configuration
    {
        $dir = tempnam(\sys_get_temp_dir(), 'jupyter_php_kernel');
        unlink($dir);

        $defaults = [
            'configDir' => $dir,
            'dataDir' => $dir,
            'runtimeDir' => $dir,
            'colorMode' => Configuration::COLOR_MODE_FORCED,
            'rawOutput' => true,
        ];

        return new Configuration(\array_merge($defaults, $config));
    }
}
