<?php

declare(strict_types=1);

namespace Responses;

use PHPUnit\Framework\TestCase;
use Wundii\JupyterPhpKernel\Requests\Request;
use Wundii\JupyterPhpKernel\Responses\CompleteReplyResponse;

final class CompleteReplyResponseTest extends TestCase
{
    public function testVariableCompletion(): void
    {
        $request = $this->makeRequest('$SE', 3);
        $completeReplyResponse = new CompleteReplyResponse($request);

        $matches = $completeReplyResponse->content['matches'];
        $this->assertContains('$_SERVER', $matches);
        $this->assertNotContains('echo', $matches);
    }

    public function testFunctionCompletion(): void
    {
        $request = $this->makeRequest('str', 3);
        $completeReplyResponse = new CompleteReplyResponse($request);

        $matches = $completeReplyResponse->content['matches'];
        $this->assertContains('strlen', $matches);
        $this->assertContains('str_replace', $matches);
    }

    public function testEmptyPartialReturnsFirst50(): void
    {
        $request = $this->makeRequest('', 0);
        $completeReplyResponse = new CompleteReplyResponse($request);

        $matches = $completeReplyResponse->content['matches'];
        $this->assertLessThanOrEqual(50, count($matches));
        $this->assertNotEmpty($matches);
    }

    public function testPartialZeroIsSpecialCase(): void
    {
        $request = $this->makeRequest('0', 1);
        $completeReplyResponse = new CompleteReplyResponse($request);

        $matches = $completeReplyResponse->content['matches'];
        $this->assertLessThanOrEqual(50, count($matches));
    }

    public function testCursorBoundsMiddleOfWord(): void
    {
        $request = $this->makeRequest('str_replace', 4);
        $completeReplyResponse = new CompleteReplyResponse($request);

        $this->assertSame(0, $completeReplyResponse->content['cursor_start']);
        $this->assertSame(11, $completeReplyResponse->content['cursor_end']);
    }

    public function testCursorBoundsVariable(): void
    {
        $request = $this->makeRequest('$myVar', 3);
        $completeReplyResponse = new CompleteReplyResponse($request);

        $this->assertSame(0, $completeReplyResponse->content['cursor_start']);
        $this->assertSame(6, $completeReplyResponse->content['cursor_end']);
    }

    public function testPhpKeywordCompletion(): void
    {
        $request = $this->makeRequest('ec', 2);
        $completeReplyResponse = new CompleteReplyResponse($request);

        $matches = $completeReplyResponse->content['matches'];
        $this->assertContains('echo', $matches);
    }

    public function testPhpConstantCompletion(): void
    {
        $request = $this->makeRequest('PHP_', 4);
        $completeReplyResponse = new CompleteReplyResponse($request);

        $matches = $completeReplyResponse->content['matches'];
        $this->assertContains('PHP_VERSION', $matches);
    }

    public function testFuzzyMatchPicksUpPartial(): void
    {
        $request = $this->makeRequest('srlen', 5); // missing 't'
        $completeReplyResponse = new CompleteReplyResponse($request);

        $matches = $completeReplyResponse->content['matches'];
        $this->assertContains('strlen', $matches);
    }

    public function testStatusOkIsAlwaysReturned(): void
    {
        $request = $this->makeRequest('test', 4);
        $completeReplyResponse = new CompleteReplyResponse($request);

        $this->assertSame('ok', $completeReplyResponse->content['status']);
    }
    private function makeRequest(string $code, int $cursorPos): Request
    {
        return new Request(
            [
                12345,
                '<IDS|MSG>',
                'hmac-signature',
                json_encode([
                    'msg_type' => 'complete_reply',
                    'username' => 'test_user',
                    'session' => 'session-id',
                    'version' => '5.3',
                ]),
                json_encode([]), // parent_header
                json_encode([]), // metadata
                json_encode([
                    'code' => $code,
                    'cursor_pos' => $cursorPos,
                ]),
            ],
            'session-id',
        );
    }
}
