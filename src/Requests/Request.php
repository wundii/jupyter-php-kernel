<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Requests;

use InvalidArgumentException;

class Request
{
    public array $ids;
    public string $hmac;
    public array $header;
    public array $parent_header;
    public array $metadata;
    public array $content;

    public string $session_id;

    public function __construct(array $message, string $session_id)
    {
        $this->session_id = $session_id;
        $this->ids = $this->getIds($message);
        [
            // TODO: Verify HMAC
            $this->hmac,
            $header,
            $parent_header,
            $metadata,
            $content
        ] = $this->getData($message);

        $this->header = json_decode($header, true);
        $this->parent_header = json_decode($parent_header, true);
        $this->metadata = json_decode($metadata, true);
        $this->content = json_decode($content, true);
    }

    /**
     * @param string[] $message
     * @return string[]
     */
    protected function getIds(array $message): array
    {
        return array_splice($message, 0, $this->getDelimiterIndex($message));
    }

    /**
     * @param string[] $message
     * @return string[]
     */
    protected function getData(array $message): array
    {
        return array_splice($message, 2);
    }

    /**
     * @param string[] $message
     */
    protected function getDelimiterIndex(array $message): int|string|false
    {
        $key = array_search('<IDS|MSG>', $message, true);
        if ($key === false) {
            throw new InvalidArgumentException('Invalid message format: <IDS|MSG> delimiter not found.');
        }

        return $key;
    }
}
