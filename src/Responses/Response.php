<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Responses;

use DateTime;
use Ramsey\Uuid\Uuid;
use Wundii\JupyterPhpKernel\Requests\Request;

abstract class Response
{
    public const COMPLETE_REPLY = 'complete_reply';
    public const EXECUTE_REPLY = 'execute_reply';
    public const EXECUTE_RESULT = 'execute_result';
    public const INSPECT_REPLY = 'inspect_reply';
    public const KERNEL_INFO_REPLY = 'kernel_info_reply';
    public const STATUS = 'status';


    protected string $type;
    protected array $content = [];
    protected array $header = [];
    protected array $parent_header = [];
    protected array $metadata = [];
    protected array $ids = [];

    public function __construct(string $type, Request $request, array $content = [], array $metadata = [])
    {
        $this->type = $type;
        $this->content = $content;
        $this->parent_header = $request->header;
        $this->metadata = $metadata;
        $this->ids = $request->ids;

        $this->header = [
            'date' => (new DateTime())->format('c'),
            'msg_id' => Uuid::uuid4()->toString(),
            'username' => 'kernel',
            'session' => $request->session_id,
            'msg_type' => $this->type,
            'version' => '5.3',
        ];
    }

    /**
     * @return string[]
     */
    public function toMessage(string $key, string $signature_scheme): array
    {
        $message = [
            json_encode((object) $this->header),
            json_encode((object) $this->parent_header),
            json_encode((object) $this->metadata),
            json_encode((object) $this->content),
        ];

        return [
            ...$this->ids,
            '<IDS|MSG>',
            $this->sign($message, $key, $signature_scheme),
            ...$message,
        ];
    }

    protected function sign(array $content, string $key, string $signature_scheme): string
    {
        $hashContext = hash_init(str_replace('hmac-', '', $signature_scheme), HASH_HMAC, $key);

        foreach ($content as $data) {
            hash_update($hashContext, $data);
        }

        return hash_final($hashContext);
    }
}
