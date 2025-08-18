<?php

declare(strict_types=1);

namespace Wundii\JupyterPhpKernel\Responses;

use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;
use Wundii\JupyterPhpKernel\Requests\Request;

class InspectReplyResponse extends Response
{
    public function __construct(Request $request)
    {
        $code = $request->content['code'] ?? '';
        $cursor_pos = $request->content['cursor_pos'] ?? 0;
        $detail_level = $request->content['detail_level'] ?? 0;

        $inspection_data = $this->inspectCode($code, $cursor_pos, $detail_level);

        $content = [
            'status' => $inspection_data !== [] ? 'ok' : 'error',
            'found' => $inspection_data !== [],
            'data' => $inspection_data,
            'metadata' => new \stdClass(),
        ];

        parent::__construct(self::INSPECT_REPLY, $request, $content);
    }

    private function inspectCode(string $code, int $cursor_pos, int $detail_level): array
    {
        $symbol = $this->extractSymbol($code, $cursor_pos);

        if ($symbol === '' || $symbol === '0') {
            return [];
        }

        return match (true) {
            function_exists($symbol) => $this->inspectFunction($symbol, $detail_level),
            class_exists($symbol) => $this->inspectClass($symbol, $detail_level),
            defined($symbol) => $this->inspectConstant($symbol),
            $this->isMethodCall($code, $cursor_pos) => $this->inspectMethod($this->extractMethodCall($code, $cursor_pos)),
            $this->isPropertyAccess($code, $cursor_pos) => $this->inspectProperty($this->extractPropertyAccess($code, $cursor_pos)),
            default => $this->inspectKeyword($symbol),
        };
    }

    private function extractSymbol(string $code, int $cursor_pos): string
    {
        $length = strlen($code);

        if ($cursor_pos < 0) {
            $cursor_pos = 0;
        }
        if ($cursor_pos >= $length) {
            $cursor_pos = $length - 1;
        }

        if ($length === 0 || $cursor_pos < 0) {
            return '';
        }

        $start = $cursor_pos;
        $end = $cursor_pos;

        // Gehe rückwärts zum Wortanfang
        while ($start > 0 && isset($code[$start - 1]) && (ctype_alnum($code[$start - 1]) || $code[$start - 1] === '_' || $code[$start - 1] === '\\')) {
            --$start;
        }

        // Gehe vorwärts zum Wortende
        while ($end < $length && isset($code[$end]) && (ctype_alnum($code[$end]) || $code[$end] === '_' || $code[$end] === '\\')) {
            ++$end;
        }

        return substr($code, $start, $end - $start);

    }

    private function inspectFunction(string $function_name, int $detail_level): array
    {
        try {
            $reflectionFunction = new ReflectionFunction($function_name);

            $data = [
                'text/plain' => $this->formatFunctionInfo($reflectionFunction, $detail_level),
                'text/html' => $this->formatFunctionInfoHtml($reflectionFunction, $detail_level),
            ];

            return $data;
        } catch (ReflectionException $reflectionException) {
            return [];
        }
    }

    private function inspectClass(string $class_name, int $detail_level): array
    {
        try {
            $reflectionClass = new ReflectionClass($class_name);

            $data = [
                'text/plain' => $this->formatClassInfo($reflectionClass, $detail_level),
                'text/html' => $this->formatClassInfoHtml($reflectionClass, $detail_level),
            ];

            return $data;
        } catch (ReflectionException) {
            return [];
        }
    }

    private function inspectConstant(string $constant_name): array
    {
        $value = constant($constant_name);
        $type = gettype($value);

        $info = "Konstante: {$constant_name}\n";
        $info .= "Typ: {$type}\n";
        $info .= 'Wert: ' . var_export($value, true) . "\n";

        return [
            'text/plain' => $info,
            'text/html' => "<strong>Konstante:</strong> {$constant_name}<br><strong>Typ:</strong> {$type}<br><strong>Wert:</strong> " . htmlspecialchars(var_export($value, true)),
        ];
    }

    private function isMethodCall(string $code, int $cursor_pos): bool
    {
        if ($cursor_pos > strlen($code)) {
            return false;
        }

        $before_cursor = substr($code, 0, $cursor_pos);
        return preg_match('/(\$\w+|[\w\\\\]+)->[\w]*$/', $before_cursor) === 1;
    }

    private function extractMethodCall(string $code, int $cursor_pos): ?array
    {
        if ($cursor_pos > strlen($code)) {
            return null;
        }

        $before_cursor = substr($code, 0, $cursor_pos);
        if (preg_match('/(\$\w+|\w+)->(\w*)$/', $before_cursor, $matches)) {
            return [
                'object' => $matches[1],
                'method' => $matches[2],
            ];
        }
        return null;
    }

    private function inspectMethod(?array $method_info): array
    {
        if ($method_info === null) {
            return [];
        }

        $info = "Methode: {$method_info['method']}\n";
        $info .= "Objekt: {$method_info['object']}\n";
        $info .= "Hinweis: Detaillierte Methodeninspektion erfordert Typisierung\n";

        return [
            'text/plain' => $info,
            'text/html' => "<strong>Methode:</strong> {$method_info['method']}<br><strong>Objekt:</strong> {$method_info['object']}<br><em>Hinweis: Detaillierte Methodeninspektion erfordert Typisierung</em>",
        ];
    }

    private function isPropertyAccess(string $code, int $cursor_pos): bool
    {
        if ($cursor_pos > strlen($code)) {
            return false;
        }

        $before_cursor = substr($code, 0, $cursor_pos);
        return preg_match('/\$\w+->\w*$/', $before_cursor) === 1;
    }

    private function extractPropertyAccess(string $code, int $cursor_pos): ?array
    {
        if ($cursor_pos > strlen($code)) {
            return null;
        }

        $before_cursor = substr($code, 0, $cursor_pos);
        if (preg_match('/(\$\w+)->(\w*)$/', $before_cursor, $matches)) {
            return [
                'object' => $matches[1],
                'property' => $matches[2],
            ];
        }
        return null;
    }

    private function inspectProperty(?array $property_info): array
    {
        if ($property_info === null) {
            return [];
        }

        $info = "Property: {$property_info['property']}\n";
        $info .= "Objekt: {$property_info['object']}\n";
        $info .= "Hinweis: Detaillierte Property-Inspektion erfordert Typisierung\n";

        return [
            'text/plain' => $info,
            'text/html' => "<strong>Property:</strong> {$property_info['property']}<br><strong>Objekt:</strong> {$property_info['object']}<br><em>Hinweis: Detaillierte Property-Inspektion erfordert Typisierung</em>",
        ];
    }

    private function inspectKeyword(string $keyword): array
    {
        $keywords_info = [
            'class' => 'PHP-Schlüsselwort zum Definieren einer Klasse',
            'function' => 'PHP-Schlüsselwort zum Definieren einer Funktion',
            'if' => 'Bedingte Anweisung für Kontrollfluss',
            'else' => 'Alternative Anweisung für if-Bedingungen',
            'foreach' => 'Schleife zum Durchlaufen von Arrays',
            'while' => 'Schleife mit Vorbedingung',
            'for' => 'Zählschleife',
            'return' => 'Rückgabe eines Wertes aus einer Funktion',
            'echo' => 'Ausgabe von Text oder Variablen',
            'print' => 'Ausgabe von Text (gibt immer 1 zurück)',
            'var_dump' => 'Debugging-Funktion für Variable',
            'isset' => 'Prüft ob Variable gesetzt ist',
            'empty' => 'Prüft ob Variable leer ist',
            'array' => 'Erstellt ein Array',
            'new' => 'Erstellt eine neue Instanz einer Klasse',
            'extends' => 'Klassenvererbung',
            'implements' => 'Interface-Implementierung',
            'public' => 'Öffentliche Sichtbarkeit',
            'private' => 'Private Sichtbarkeit',
            'protected' => 'Geschützte Sichtbarkeit',
            'static' => 'Statische Methoden/Properties',
            'const' => 'Klassenkonstante',
            'namespace' => 'Namespace-Deklaration',
            'use' => 'Import von Namespaces/Klassen',
            'try' => 'Exception-Handling Block',
            'catch' => 'Exception-Behandlung',
            'finally' => 'Code der immer ausgeführt wird',
            'throw' => 'Exception werfen',
        ];

        if (isset($keywords_info[$keyword])) {
            return [
                'text/plain' => "PHP-Schlüsselwort: {$keyword}\n\n{$keywords_info[$keyword]}",
                'text/html' => "<strong>PHP-Schlüsselwort:</strong> {$keyword}<br><br>{$keywords_info[$keyword]}",
            ];
        }

        return [];
    }

    private function formatFunctionInfo(ReflectionFunction $reflectionFunction, int $detail_level): string
    {
        $info = 'Funktion: ' . $reflectionFunction->getName() . "\n";
        $info .= 'Signatur: ' . $this->buildFunctionSignature($reflectionFunction) . "\n\n";

        if ($reflectionFunction->getDocComment()) {
            $info .= "Dokumentation:\n" . $this->cleanDocComment($reflectionFunction->getDocComment()) . "\n\n";
        }

        if ($detail_level > 0) {
            $info .= 'Datei: ' . ($reflectionFunction->getFileName() ?: 'Built-in') . "\n";
            if ($reflectionFunction->getStartLine()) {
                $info .= 'Zeile: ' . $reflectionFunction->getStartLine() . "\n";
            }

            $parameters = $reflectionFunction->getParameters();
            if ($parameters) {
                $info .= "\nParameter:\n";
                foreach ($parameters as $parameter) {
                    $info .= "  - \${$parameter->getName()}";
                    if ($parameter->hasType()) {
                        $info .= ' (' . $parameter->getType() . ')';
                    }
                    if ($parameter->isDefaultValueAvailable()) {
                        $info .= ' = ' . var_export($parameter->getDefaultValue(), true);
                    }
                    $info .= "\n";
                }
            }
        }

        return $info;
    }

    private function formatFunctionInfoHtml(ReflectionFunction $reflectionFunction, int $detail_level): string
    {
        $html = '<h3>Funktion: ' . htmlspecialchars($reflectionFunction->getName()) . '</h3>';
        $html .= '<p><strong>Signatur:</strong> <code>' . htmlspecialchars($this->buildFunctionSignature($reflectionFunction)) . '</code></p>';

        if ($reflectionFunction->getDocComment()) {
            $html .= '<h4>Dokumentation:</h4>';
            $html .= '<pre>' . htmlspecialchars($this->cleanDocComment($reflectionFunction->getDocComment())) . '</pre>';
        }

        if ($detail_level > 0) {
            $html .= '<p><strong>Datei:</strong> ' . htmlspecialchars($reflectionFunction->getFileName() ?: 'Built-in') . '</p>';
            if ($reflectionFunction->getStartLine()) {
                $html .= '<p><strong>Zeile:</strong> ' . $reflectionFunction->getStartLine() . '</p>';
            }

            $parameters = $reflectionFunction->getParameters();
            if ($parameters) {
                $html .= '<h4>Parameter:</h4><ul>';
                foreach ($parameters as $parameter) {
                    $html .= "<li><code>\${$parameter->getName()}</code>";
                    if ($parameter->hasType()) {
                        $html .= ' (' . htmlspecialchars((string) $parameter->getType()) . ')';
                    }
                    if ($parameter->isDefaultValueAvailable()) {
                        $html .= ' = ' . htmlspecialchars(var_export($parameter->getDefaultValue(), true));
                    }
                    $html .= '</li>';
                }
                $html .= '</ul>';
            }
        }

        return $html;
    }

    private function formatClassInfo(ReflectionClass $reflectionClass, int $detail_level): string
    {
        $info = 'Klasse: ' . $reflectionClass->getName() . "\n";

        if ($reflectionClass->getParentClass()) {
            $info .= 'Erbt von: ' . $reflectionClass->getParentClass()->getName() . "\n";
        }

        $interfaces = $reflectionClass->getInterfaces();
        if ($interfaces) {
            $info .= 'Implementiert: ' . implode(', ', array_keys($interfaces)) . "\n";
        }

        if ($reflectionClass->getDocComment()) {
            $info .= "\nDokumentation:\n" . $this->cleanDocComment($reflectionClass->getDocComment()) . "\n";
        }

        if ($detail_level > 0) {
            $info .= "\nDatei: " . ($reflectionClass->getFileName() ?: 'Built-in') . "\n";

            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
            if ($methods) {
                $info .= "\nÖffentliche Methoden:\n";
                foreach ($methods as $method) {
                    $info .= '  - ' . $method->getName() . "()\n";
                }
            }

            $properties = $reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC);
            if ($properties) {
                $info .= "\nÖffentliche Properties:\n";
                foreach ($properties as $property) {
                    $info .= '  - $' . $property->getName() . "\n";
                }
            }
        }

        return $info;
    }

    private function formatClassInfoHtml(ReflectionClass $reflectionClass, int $detail_level): string
    {
        $html = '<h3>Klasse: ' . htmlspecialchars($reflectionClass->getName()) . '</h3>';

        if ($reflectionClass->getParentClass()) {
            $html .= '<p><strong>Erbt von:</strong> ' . htmlspecialchars($reflectionClass->getParentClass()->getName()) . '</p>';
        }

        $interfaces = $reflectionClass->getInterfaces();
        if ($interfaces) {
            $html .= '<p><strong>Implementiert:</strong> ' . htmlspecialchars(implode(', ', array_keys($interfaces))) . '</p>';
        }

        if ($reflectionClass->getDocComment()) {
            $html .= '<h4>Dokumentation:</h4>';
            $html .= '<pre>' . htmlspecialchars($this->cleanDocComment($reflectionClass->getDocComment())) . '</pre>';
        }

        if ($detail_level > 0) {
            $html .= '<p><strong>Datei:</strong> ' . htmlspecialchars($reflectionClass->getFileName() ?: 'Built-in') . '</p>';

            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
            if ($methods) {
                $html .= '<h4>Öffentliche Methoden:</h4><ul>';
                foreach ($methods as $method) {
                    $html .= '<li><code>' . htmlspecialchars($method->getName()) . '()</code></li>';
                }
                $html .= '</ul>';
            }

            $properties = $reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC);
            if ($properties) {
                $html .= '<h4>Öffentliche Properties:</h4><ul>';
                foreach ($properties as $property) {
                    $html .= '<li><code>$' . htmlspecialchars($property->getName()) . '</code></li>';
                }
                $html .= '</ul>';
            }
        }

        return $html;
    }

    private function buildFunctionSignature(ReflectionFunction $reflectionFunction): string
    {
        $params = [];
        foreach ($reflectionFunction->getParameters() as $parameter) {
            $paramStr = '';
            if ($parameter->hasType()) {
                $paramStr .= $parameter->getType() . ' ';
            }
            $paramStr .= '$' . $parameter->getName();
            if ($parameter->isDefaultValueAvailable()) {
                $paramStr .= ' = ' . var_export($parameter->getDefaultValue(), true);
            }
            $params[] = $paramStr;
        }

        $returnType = '';
        if ($reflectionFunction->hasReturnType()) {
            $returnType = ': ' . $reflectionFunction->getReturnType();
        }

        return $reflectionFunction->getName() . '(' . implode(', ', $params) . ')' . $returnType;
    }

    private function cleanDocComment(string $docComment): string
    {
        $lines = explode("\n", $docComment);
        $cleaned = [];

        foreach ($lines as $line) {
            $line = trim($line);
            $line = preg_replace('/^\s*\*\s?/', '', $line);
            $line = preg_replace('/^\s*\/\*\*?\s*/', '', $line);
            $line = preg_replace('/\s*\*\/\s*$/', '', $line);

            if (!empty($line)) {
                $cleaned[] = $line;
            }
        }

        return implode("\n", $cleaned);
    }
}
