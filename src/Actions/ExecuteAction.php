<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Actions;

use Exception;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Wundii\JupyterPhpKernel\Requests\Request;
use Wundii\JupyterPhpKernel\Responses\ExecuteReplyResponse;
use Wundii\JupyterPhpKernel\Responses\ExecuteResultResponse;

class ExecuteAction extends Action
{
    protected function run(Request $request): void
    {
        $streamOutput = $this->getStreamOutput();
        $this->kernel->shell->setOutput($streamOutput);
        try {
            $code = $this->cleanCode($request->content['code']);

            $output = match (true) {
                str_starts_with($code, '!composer') => $this->composerRun($code),
                default => $this->defaultRun($streamOutput, $code),
            };

        } catch (Exception $exception) {
            $output = $exception->getMessage();
        }

        ++$this->kernel->execution_count;
        $this->kernel->sendIOPubMessage(
            new ExecuteResultResponse($this->kernel->execution_count, $output, $request)
        );
        $this->kernel->sendShellMessage(new ExecuteReplyResponse($this->kernel->execution_count, 'ok', $request));
    }

    private function composerRun(string $code): string
    {
        if (preg_match('/^!composer\b(?!.*\bglobal\b)/i', $code)) {
            $code = preg_replace('/^!composer(\s+)/i', '!composer global$1', $code);
        }

        $shellResponse = shell_exec(preg_replace('/^!/', '', $code) . ' 2>&1');
        if (!is_string($shellResponse)) {
            return 'Shell execution failed or returned no output.';
        }

        return $shellResponse;
    }

    private function defaultRun(StreamOutput $streamOutput, string $code): string
    {
        $stream = $streamOutput->getStream();

        $ret = $this->kernel->shell->execute($code);
        $this->kernel->shell->writeReturnValue($ret, true);
        rewind($stream);
        $output = stream_get_contents($stream);

        if (str_starts_with($output, 'PHP Error:')) {
            $code = 'try { ' . $code . ' }' .
                'catch (\Exception $e) { ' .
                'echo "Execute Exception: " . $e->getMessage(); ' .
                'echo "<br>File: " . $e->getFile(); ' .
                'echo "<br>Line: " . $e->getLine(); ' .
                ' }';
            $ret = $this->kernel->shell->execute($code);
            $this->kernel->shell->writeReturnValue($ret, true);
            rewind($stream);
            $output = stream_get_contents($stream);
        }

        return $output;
    }

    private function getStreamOutput(): StreamOutput
    {
        $stream = fopen('php://memory', 'w+');
        return new StreamOutput($stream, OutputInterface::VERBOSITY_NORMAL, false);
    }

    private function cleanCode(string $code): ?string
    {
        $code = preg_replace('/^(<\?(php)?)*(\?>)*/m', '', trim($code));
        return preg_replace('/<!--.*?-->/', '', $code);
    }
}
