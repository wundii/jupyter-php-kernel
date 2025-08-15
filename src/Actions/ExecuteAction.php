<?php

namespace Wundii\JupyterPhpKernel\Actions;

use Exception;
use Wundii\JupyterPhpKernel\Requests\Request;
use Wundii\JupyterPhpKernel\Responses\ExecuteReplyResponse;
use Wundii\JupyterPhpKernel\Responses\ExecuteResultResponse;
use Symfony\Component\Console\Output\StreamOutput;

class ExecuteAction extends Action
{
    protected function run(Request $request)
    {
        $output = $this->getOutput();
        $stream = $output->getStream();
        $this->kernel->shell->setOutput($output);
        try {
            $code = $this->cleanCode($request->content['code']);
            $code = preg_replace('/<!--.*?-->/', '', $code);
            if (str_starts_with($code, '!composer')) {
                if (preg_match('/^!composer\b(?!.*\bglobal\b)/i', $code)) {
                    $code = preg_replace('/^!composer(\s+)/i', '!composer global$1', $code);
                }
                #$output = $code;
                $output = shell_exec(preg_replace('/^!/', '', $code) . ' 2>&1');
                $this->kernel->shell->writeReturnValue($output, true);
                rewind($stream);
                $output = stream_get_contents($stream);

            } else {
                #$code = 'try { ' . $code . ' } catch (\Exception $e) { echo "shell Execute Exception: " . $e->getMessage(); }';

                $ret = $this->kernel->shell->execute($code);
                $this->kernel->shell->writeReturnValue($ret, true);
                rewind($stream);
                $output = stream_get_contents($stream);
                #$output = $code;
            }
            #$output = $code;

        } catch (Exception $e) {
            $output = $e->getMessage();
        }
        $this->kernel->execution_count += 1;
        $this->kernel->sendIOPubMessage(
            new ExecuteResultResponse($this->kernel->execution_count, $output, $request)
        );
        $this->kernel->sendShellMessage(new ExecuteReplyResponse($this->kernel->execution_count, 'ok', $request));
    }

    private function getOutput()
    {
        $stream = fopen('php://memory', 'w+');
        return new StreamOutput($stream, StreamOutput::VERBOSITY_NORMAL, false);
    }

    private function cleanCode(string $code)
    {
        return preg_replace('/^(<\?(php)?)*(\?>)*/m', '', trim($code));
    }
}
