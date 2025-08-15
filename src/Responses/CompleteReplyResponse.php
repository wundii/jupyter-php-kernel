<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Responses;

use ReflectionClass;
use Wundii\JupyterPhpKernel\Requests\Request;

class CompleteReplyResponse extends Response
{
    // public function __construct(Request $request)
    // {
    //     $code = $request->content['code'] ?? '';
    //     $cursor_pos = $request->content['cursor_pos'] ?? 0;
    //
    //     // Einfache PHP-Autovervollständigung - hier können Sie eine
    //     // erweiterte Implementierung mit Reflection oder PHPStan hinzufügen
    //     $matches = $this->getCompletions($code, $cursor_pos);
    //
    //     $content = [
    //         'matches' => $matches,
    //         'cursor_start' => max(0, $cursor_pos - 10), // Vereinfacht
    //         'cursor_end' => $cursor_pos,
    //         'metadata' => new \stdClass(),
    //         'status' => 'ok',
    //     ];
    //
    //     parent::__construct(self::COMPLETE_REPLY, $request, $content);
    // }
    //
    // private function getCompletions(string $code, int $cursor_pos): array
    // {
    //     // Basis-PHP-Funktionen für Autovervollständigung
    //     $php_functions = [
    //         'array_map', 'array_filter', 'array_merge', 'count', 'strlen',
    //         'substr', 'explode', 'implode', 'trim', 'strtolower', 'strtoupper'
    //     ];
    //
    //     // Extrahieren Sie das aktuelle Wort unter dem Cursor
    //     $word_start = $cursor_pos;
    //     while ($word_start > 0 && ctype_alnum($code[$word_start - 1])) {
    //         $word_start--;
    //     }
    //
    //     $current_word = substr($code, $word_start, $cursor_pos - $word_start);
    //
    //     // Filtern Sie passende Funktionen
    //     return array_filter($php_functions, function($func) use ($current_word) {
    //         return empty($current_word) || str_starts_with($func, $current_word);
    //     });
    // }
    private array $builtInFunctions;
    private array $builtInFunctionsUser;
    private array $builtInClasses;


    public function __construct(Request $request)
    {
        $this->initializeBuiltIns();

        $code = $request->content['code'] ?? '';
        $cursor_pos = $request->content['cursor_pos'] ?? 0;

        $matches = $this->getCompletions($code, $cursor_pos);
        $cursor_bounds = $this->getCursorBounds($code, $cursor_pos);

        $content = [
            'matches' => $matches,
            'cursor_start' => $cursor_bounds['start'],
            'cursor_end' => $cursor_bounds['end'],
            'metadata' => new \stdClass(),
            'status' => 'ok',
        ];

        parent::__construct(self::COMPLETE_REPLY, $request, $content);
    }

    private function initializeBuiltIns(): void
    {
        $this->builtInFunctions = get_defined_functions()['internal'];
        $this->builtInFunctionsUser = get_defined_functions()['user'];
        $this->builtInClasses = get_declared_classes();
        $this->builtInClasses = array_filter($this->builtInClasses, function($class) {
            $reflection = new ReflectionClass($class);
            return $reflection->isInternal();
        });
    }


    private function getCompletions(string $code, int $cursor_pos): array
    {
        $context = $this->analyzeContext($code, $cursor_pos);
        $partial = $context['partial'];
        $type = $context['type'];

        switch ($type) {
            // case 'method':
            //     $completions = $this->getMethodCompletions($context['object'], $partial);
            //     break;
            // case 'static_method':
            //     $completions = $this->getStaticMethodCompletions($context['class'], $partial);
            //     break;
            // case 'property':
            //     $completions = $this->getPropertyCompletions($context['object'], $partial);
            //     break;
            // case 'class':
            //     $completions = $this->getClassCompletions($partial);
            //     break;
            // case 'function':
            //     $completions = $this->getFunctionCompletions($partial);
            //     break;
            case 'variable':
                $completions = $this->getVariableCompletions($partial);
                break;
            default:
                $completions = $this->getFunctionCompletions($partial);
        }

        return $completions;

    }

    private function getCursorBounds(string $code, int $cursor_pos): array
    {
        // Finde Start des aktuellen Wortes
        $start = $cursor_pos;
        while ($start > 0 && (ctype_alnum($code[$start - 1]) || in_array($code[$start - 1], ['_', '$', '\\']))) {
            $start--;
        }

        // Finde Ende des aktuellen Wortes
        $end = $cursor_pos;
        $code_len = strlen($code);
        while ($end < $code_len && (ctype_alnum($code[$end]) || in_array($code[$end], ['_', '$', '\\']))) {
            $end++;
        }

        return ['start' => $start, 'end' => $end];
    }

    private function getFunctionCompletions(string $partial): array
    {
        $all_functions = array_merge(
            $this->builtInFunctions,
            $this->builtInFunctionsUser,
            $this->builtInClasses,
            $this->getPhpKeywords(),
            $this->getPhpConstants()
        );

        return $this->filterMatches($all_functions, $partial);
    }

    private function getVariableCompletions(string $partial): array
    {
        $common_vars = [
            '$_GET', '$_POST', '$_SESSION', '$_COOKIE', '$_SERVER',
            '$_ENV', '$_FILES', '$GLOBALS', '$this'
        ];

        // $all_vars = array_merge($common_vars, $this->variables);
        return $this->filterMatches($common_vars, $partial);
    }

    private function getPhpKeywords(string $partial = ''): array
    {
        $keywords = [
            'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch',
            'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do',
            'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach',
            'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'false',
            'final', 'finally', 'for', 'foreach', 'function', 'global', 'goto',
            'if', 'implements', 'include', 'include_once', 'instanceof', 'insteadof',
            'interface', 'isset', 'list', 'match', 'namespace', 'new', 'null', 'or',
            'print', 'private', 'protected', 'public', 'require', 'require_once',
            'return', 'static', 'switch', 'throw', 'trait', 'true', 'try', 'unset',
            'use', 'var', 'while', 'xor', 'yield'
        ];

        return $this->filterMatches($keywords, $partial);
    }

    private function getPhpConstants(): array
    {
        return [
            'PHP_VERSION', 'PHP_MAJOR_VERSION', 'PHP_MINOR_VERSION', 'PHP_RELEASE_VERSION',
            'PHP_OS', 'PHP_SAPI', 'PHP_EOL', 'PHP_INT_MAX', 'PHP_INT_MIN',
            'PHP_FLOAT_MAX', 'PHP_FLOAT_MIN', 'E_ERROR', 'E_WARNING', 'E_NOTICE'
        ];
    }

    private function analyzeContext(string $code, int $cursor_pos): array
    {
        $before_cursor = substr($code, 0, $cursor_pos);

        if (preg_match('/(\$\w*)$/', $before_cursor, $matches)) {
            return [
                'type' => 'variable',
                'partial' => $matches[1] ?? ''
            ];
        }

        // Extrahiere partielles Wort
        $word_start = $cursor_pos;
        while ($word_start > 0 && (ctype_alnum($code[$word_start - 1]) || $code[$word_start - 1] === '_')) {
            $word_start--;
        }

        return [
            'type' => 'function',
            'partial' => substr($code, $word_start, $cursor_pos - $word_start)
        ];
    }


    private function filterMatches(array $candidates, string $partial): array
    {
        if (empty($partial)) {
            return array_slice($candidates, 0, 50);
        }

        $partial_lower = strtolower($partial);
        $matches = [];

        foreach ($candidates as $candidate) {
            $candidate_lower = strtolower($candidate);

            // Exakte Übereinstimmung am Anfang hat höchste Priorität
            if (str_starts_with($candidate_lower, $partial_lower)) {
                $matches[] = ['text' => $candidate, 'score' => 100];
            }
            // Substring-Übereinstimmung
            elseif (str_contains($candidate_lower, $partial_lower)) {
                $matches[] = ['text' => $candidate, 'score' => 50];
            }
            // Fuzzy-Matching (vereinfacht)
            elseif ($this->fuzzyMatch($candidate_lower, $partial_lower)) {
                $matches[] = ['text' => $candidate, 'score' => 25];
            }
        }

        // Sortiere nach Score
        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice(array_column($matches, 'text'), 0, 50);
    }

    private function fuzzyMatch(string $text, string $pattern): bool
    {
        $text_len = strlen($text);
        $pattern_len = strlen($pattern);

        if ($pattern_len === 0) return true;
        if ($text_len === 0) return false;

        $text_idx = 0;
        $pattern_idx = 0;

        while ($text_idx < $text_len && $pattern_idx < $pattern_len) {
            if ($text[$text_idx] === $pattern[$pattern_idx]) {
                $pattern_idx++;
            }
            $text_idx++;
        }

        return $pattern_idx === $pattern_len;
    }
}
