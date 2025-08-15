<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Responses;

use Wundii\JupyterPhpKernel\Requests\Request;

class CompleteReplyResponse extends Response
{
    public function __construct(Request $request)
    {
        $code = $request->content['code'] ?? '';
        $cursor_pos = $request->content['cursor_pos'] ?? 0;

        // Einfache PHP-Autovervollständigung - hier können Sie eine
        // erweiterte Implementierung mit Reflection oder PHPStan hinzufügen
        $matches = $this->getCompletions($code, $cursor_pos);

        $content = [
            'matches' => $matches,
            'cursor_start' => max(0, $cursor_pos - 10), // Vereinfacht
            'cursor_end' => $cursor_pos,
            'metadata' => new \stdClass(),
            'status' => 'ok',
        ];

        parent::__construct(self::COMPLETE_REPLY, $request, $content);
    }

    private function getCompletions(string $code, int $cursor_pos): array
    {
        // Basis-PHP-Funktionen für Autovervollständigung
        $php_functions = [
            'array_map', 'array_filter', 'array_merge', 'count', 'strlen',
            'substr', 'explode', 'implode', 'trim', 'strtolower', 'strtoupper'
        ];

        // Extrahieren Sie das aktuelle Wort unter dem Cursor
        $word_start = $cursor_pos;
        while ($word_start > 0 && ctype_alnum($code[$word_start - 1])) {
            $word_start--;
        }

        $current_word = substr($code, $word_start, $cursor_pos - $word_start);

        // Filtern Sie passende Funktionen
        return array_filter($php_functions, function($func) use ($current_word) {
            return empty($current_word) || str_starts_with($func, $current_word);
        });
    }

}
