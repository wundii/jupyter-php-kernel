<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel;

use Psy\Configuration;
use Psy\Shell;
use Ramsey\Uuid\Uuid;
use Throwable;
use Wundii\JupyterPhpKernel\Handlers\ShellMessageHandler;
use Wundii\JupyterPhpKernel\Requests\Request;
use Wundii\JupyterPhpKernel\Responses\Response;
use Wundii\JupyterPhpKernel\Responses\StatusResponse;

class Kernel
{
    public ConnectionDetails $connection_details;
    public string $session_id;

    public int $execution_count = 0;
    public Shell $shell;

    private ShellMessageHandler $shellMessageHandler;
    private string $currentShellReplyChannel = 'shell';

    public function __construct(ConnectionDetails $connectionDetails)
    {
        $this->connection_details = $connectionDetails;
        $this->session_id = Uuid::uuid4()->toString();
        $this->shell = new Shell($this->getConfig());
        $this->shellMessageHandler = new ShellMessageHandler($this);
    }

    public function run(): void
    {
        $stdin = fopen('php://stdin', 'r');
        if ($stdin === false) {
            fwrite(STDERR, "Could not open stdin\n");
            return;
        }

        while (($line = fgets($stdin)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $this->handleBridgeMessage($line);
        }
    }

    public function sendStatusMessage(string $status, Request $request): void
    {
        $this->sendIOPubMessage(new StatusResponse($status, $request));
    }

    public function sendIOPubMessage(Response $response): void
    {
        $message = $response->toMessage($this->connection_details->key, $this->connection_details->signature_scheme);
        $this->writeBridgeMessage('iopub', $message);
    }

    public function sendShellMessage(Response $response): void
    {
        $message = $response->toMessage($this->connection_details->key, $this->connection_details->signature_scheme);
        $this->writeBridgeMessage($this->currentShellReplyChannel, $message);
    }

    public function sendHbMessage($message): void
    {
        if (is_array($message)) {
            $frames = array_map(static fn ($frame): string => (string) $frame, $message);
            $this->writeBridgeMessage('hb', $frames);
            return;
        }

        $this->writeBridgeMessage('hb', [(string) $message]);
    }

    private function handleBridgeMessage(string $jsonLine): void
    {
        $message = json_decode($jsonLine, true);
        if (!is_array($message) || ($message['event'] ?? '') !== 'request') {
            return;
        }

        $channel = $message['channel'] ?? null;
        if (!is_string($channel)) {
            return;
        }

        $frames = $this->decodeFrames($message['frames_b64'] ?? null);
        if ($frames === []) {
            return;
        }

        if ($channel !== 'shell' && $channel !== 'control') {
            return;
        }

        $this->currentShellReplyChannel = $channel;
        try {
            $this->shellMessageHandler->handle(new Request($frames, $this->session_id));
        } catch (Throwable $throwable) {
            fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
        } finally {
            $this->currentShellReplyChannel = 'shell';
        }
    }

    /**
     * @return string[]
     */
    private function decodeFrames(mixed $encodedFrames): array
    {
        if (!is_array($encodedFrames)) {
            return [];
        }

        $frames = [];
        foreach ($encodedFrames as $encodedFrame) {
            if (!is_string($encodedFrame)) {
                return [];
            }

            $frame = base64_decode($encodedFrame, true);
            if ($frame === false) {
                return [];
            }

            $frames[] = $frame;
        }

        return $frames;
    }

    /**
     * @param string[] $frames
     */
    private function writeBridgeMessage(string $channel, array $frames): void
    {
        $message = [
            'event' => 'response',
            'channel' => $channel,
            'frames_b64' => array_map(static fn (string $frame): string => base64_encode($frame), $frames),
        ];

        $encodedMessage = json_encode($message, JSON_UNESCAPED_SLASHES);
        if (!is_string($encodedMessage)) {
            return;
        }

        fwrite(STDOUT, $encodedMessage . "\n");
        fflush(STDOUT);
    }

    private function getConfig(array $config = []): Configuration
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
