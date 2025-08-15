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
            'status' => $inspection_data ? 'ok' : 'error',
            'found' => !empty($inspection_data),
            'data' => $inspection_data,
            'metadata' => new \stdClass()
        ];

        parent::__construct(self::INSPECT_REPLY, $request, $content);
    }

    private function inspectCode(string $code, int $cursor_pos, int $detail_level): array
    {
        $symbol = $this->extractSymbol($code, $cursor_pos);

        if (empty($symbol)) {
            return [];
        }

        // Versuche verschiedene Arten von Symbolen zu identifizieren
        $inspection_data = [];

        // 1. PHP-Funktionen
        if (function_exists($symbol)) {
            $inspection_data = $this->inspectFunction($symbol, $detail_level);
        }
        // 2. PHP-Klassen
        elseif (class_exists($symbol)) {
            $inspection_data = $this->inspectClass($symbol, $detail_level);
        }
        // 3. PHP-Konstanten
        elseif (defined($symbol)) {
            $inspection_data = $this->inspectConstant($symbol, $detail_level);
        }
        // 4. Methoden-Aufrufe analysieren
        elseif ($this->isMethodCall($code, $cursor_pos)) {
            $method_info = $this->extractMethodCall($code, $cursor_pos);
            if ($method_info) {
                $inspection_data = $this->inspectMethod($method_info, $detail_level);
            }
        }
        // 5. Properties analysieren
        elseif ($this->isPropertyAccess($code, $cursor_pos)) {
            $property_info = $this->extractPropertyAccess($code, $cursor_pos);
            if ($property_info) {
                $inspection_data = $this->inspectProperty($property_info, $detail_level);
            }
        }
        // 6. PHP-Keywords und Sprachkonstrukte
        else {
            $inspection_data = $this->inspectKeyword($symbol, $detail_level);
        }

        return $inspection_data;
    }

    private function extractSymbol(string $code, int $cursor_pos): string
    {
        $length = strlen($code);

        // Validiere cursor_pos
        if ($cursor_pos < 0) {
            $cursor_pos = 0;
        }
        if ($cursor_pos >= $length) {
            $cursor_pos = $length - 1;
        }

        // Bei leerem Code oder ungültiger Position
        if ($length === 0 || $cursor_pos < 0) {
            return '';
        }

        // Finde das Wort unter dem Cursor
        $start = $cursor_pos;
        $end = $cursor_pos;

        // Gehe rückwärts zum Wortanfang
        while ($start > 0 && isset($code[$start - 1]) && (ctype_alnum($code[$start - 1]) || $code[$start - 1] === '_' || $code[$start - 1] === '\\')) {
            $start--;
        }

        // Gehe vorwärts zum Wortende
        while ($end < $length && isset($code[$end]) && (ctype_alnum($code[$end]) || $code[$end] === '_' || $code[$end] === '\\')) {
            $end++;
        }

        return substr($code, $start, $end - $start);

    }

    private function inspectFunction(string $function_name, int $detail_level): array
    {
        try {
            $reflection = new ReflectionFunction($function_name);

            $data = [
                'text/plain' => $this->formatFunctionInfo($reflection, $detail_level),
                'text/html' => $this->formatFunctionInfoHtml($reflection, $detail_level)
            ];

            return $data;
        } catch (ReflectionException $e) {
            return [];
        }
    }

    private function inspectClass(string $class_name, int $detail_level): array
    {
        try {
            $reflection = new ReflectionClass($class_name);

            $data = [
                'text/plain' => $this->formatClassInfo($reflection, $detail_level),
                'text/html' => $this->formatClassInfoHtml($reflection, $detail_level)
            ];

            return $data;
        } catch (ReflectionException $e) {
            return [];
        }
    }

    private function inspectConstant(string $constant_name, int $detail_level): array
    {
        $value = constant($constant_name);
        $type = gettype($value);

        $info = "Konstante: {$constant_name}\n";
        $info .= "Typ: {$type}\n";
        $info .= "Wert: " . var_export($value, true) . "\n";

        return [
            'text/plain' => $info,
            'text/html' => "<strong>Konstante:</strong> {$constant_name}<br><strong>Typ:</strong> {$type}<br><strong>Wert:</strong> " . htmlspecialchars(var_export($value, true))
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
                'method' => $matches[2] ?? ''
            ];
        }
        return null;
    }


    private function inspectMethod(array $method_info, int $detail_level): array
    {
        // Vereinfachte Implementierung - in der Praxis würden Sie
        // den Objekttyp durch statische Analyse bestimmen
        $info = "Methode: {$method_info['method']}\n";
        $info .= "Objekt: {$method_info['object']}\n";
        $info .= "Hinweis: Detaillierte Methodeninspektion erfordert Typisierung\n";

        return [
            'text/plain' => $info,
            'text/html' => "<strong>Methode:</strong> {$method_info['method']}<br><strong>Objekt:</strong> {$method_info['object']}<br><em>Hinweis: Detaillierte Methodeninspektion erfordert Typisierung</em>"
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
                'property' => $matches[2] ?? ''
            ];
        }
        return null;
    }

    private function inspectProperty(array $property_info, int $detail_level): array
    {
        $info = "Property: {$property_info['property']}\n";
        $info .= "Objekt: {$property_info['object']}\n";
        $info .= "Hinweis: Detaillierte Property-Inspektion erfordert Typisierung\n";

        return [
            'text/plain' => $info,
            'text/html' => "<strong>Property:</strong> {$property_info['property']}<br><strong>Objekt:</strong> {$property_info['object']}<br><em>Hinweis: Detaillierte Property-Inspektion erfordert Typisierung</em>"
        ];
    }

    private function inspectKeyword(string $keyword, int $detail_level): array
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
            'throw' => 'Exception werfen'
        ];

        if (isset($keywords_info[$keyword])) {
            return [
                'text/plain' => "PHP-Schlüsselwort: {$keyword}\n\n{$keywords_info[$keyword]}",
                'text/html' => "<strong>PHP-Schlüsselwort:</strong> {$keyword}<br><br>{$keywords_info[$keyword]}"
            ];
        }

        return [];
    }

    private function formatFunctionInfo(ReflectionFunction $reflection, int $detail_level): string
    {
        $info = "Funktion: " . $reflection->getName() . "\n";
        $info .= "Signatur: " . $this->buildFunctionSignature($reflection) . "\n\n";

        if ($reflection->getDocComment()) {
            $info .= "Dokumentation:\n" . $this->cleanDocComment($reflection->getDocComment()) . "\n\n";
        }

        if ($detail_level > 0) {
            $info .= "Datei: " . ($reflection->getFileName() ?: 'Built-in') . "\n";
            if ($reflection->getStartLine()) {
                $info .= "Zeile: " . $reflection->getStartLine() . "\n";
            }

            $parameters = $reflection->getParameters();
            if ($parameters) {
                $info .= "\nParameter:\n";
                foreach ($parameters as $param) {
                    $info .= "  - \${$param->getName()}";
                    if ($param->hasType()) {
                        $info .= " (" . $param->getType() . ")";
                    }
                    if ($param->isDefaultValueAvailable()) {
                        $info .= " = " . var_export($param->getDefaultValue(), true);
                    }
                    $info .= "\n";
                }
            }
        }

        return $info;
    }

    private function formatFunctionInfoHtml(ReflectionFunction $reflection, int $detail_level): string
    {
        $html = "<h3>Funktion: " . htmlspecialchars($reflection->getName()) . "</h3>";
        $html .= "<p><strong>Signatur:</strong> <code>" . htmlspecialchars($this->buildFunctionSignature($reflection)) . "</code></p>";

        if ($reflection->getDocComment()) {
            $html .= "<h4>Dokumentation:</h4>";
            $html .= "<pre>" . htmlspecialchars($this->cleanDocComment($reflection->getDocComment())) . "</pre>";
        }

        if ($detail_level > 0) {
            $html .= "<p><strong>Datei:</strong> " . htmlspecialchars($reflection->getFileName() ?: 'Built-in') . "</p>";
            if ($reflection->getStartLine()) {
                $html .= "<p><strong>Zeile:</strong> " . $reflection->getStartLine() . "</p>";
            }

            $parameters = $reflection->getParameters();
            if ($parameters) {
                $html .= "<h4>Parameter:</h4><ul>";
                foreach ($parameters as $param) {
                    $html .= "<li><code>\${$param->getName()}</code>";
                    if ($param->hasType()) {
                        $html .= " (" . htmlspecialchars($param->getType()->getName()) . ")";
                    }
                    if ($param->isDefaultValueAvailable()) {
                        $html .= " = " . htmlspecialchars(var_export($param->getDefaultValue(), true));
                    }
                    $html .= "</li>";
                }
                $html .= "</ul>";
            }
        }

        return $html;
    }

    private function formatClassInfo(ReflectionClass $reflection, int $detail_level): string
    {
        $info = "Klasse: " . $reflection->getName() . "\n";

        if ($reflection->getParentClass()) {
            $info .= "Erbt von: " . $reflection->getParentClass()->getName() . "\n";
        }

        $interfaces = $reflection->getInterfaces();
        if ($interfaces) {
            $info .= "Implementiert: " . implode(', ', array_keys($interfaces)) . "\n";
        }

        if ($reflection->getDocComment()) {
            $info .= "\nDokumentation:\n" . $this->cleanDocComment($reflection->getDocComment()) . "\n";
        }

        if ($detail_level > 0) {
            $info .= "\nDatei: " . ($reflection->getFileName() ?: 'Built-in') . "\n";

            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
            if ($methods) {
                $info .= "\nÖffentliche Methoden:\n";
                foreach ($methods as $method) {
                    $info .= "  - " . $method->getName() . "()\n";
                }
            }

            $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
            if ($properties) {
                $info .= "\nÖffentliche Properties:\n";
                foreach ($properties as $property) {
                    $info .= "  - \$" . $property->getName() . "\n";
                }
            }
        }

        return $info;
    }

    private function formatClassInfoHtml(ReflectionClass $reflection, int $detail_level): string
    {
        $html = "<h3>Klasse: " . htmlspecialchars($reflection->getName()) . "</h3>";

        if ($reflection->getParentClass()) {
            $html .= "<p><strong>Erbt von:</strong> " . htmlspecialchars($reflection->getParentClass()->getName()) . "</p>";
        }

        $interfaces = $reflection->getInterfaces();
        if ($interfaces) {
            $html .= "<p><strong>Implementiert:</strong> " . htmlspecialchars(implode(', ', array_keys($interfaces))) . "</p>";
        }

        if ($reflection->getDocComment()) {
            $html .= "<h4>Dokumentation:</h4>";
            $html .= "<pre>" . htmlspecialchars($this->cleanDocComment($reflection->getDocComment())) . "</pre>";
        }

        if ($detail_level > 0) {
            $html .= "<p><strong>Datei:</strong> " . htmlspecialchars($reflection->getFileName() ?: 'Built-in') . "</p>";

            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
            if ($methods) {
                $html .= "<h4>Öffentliche Methoden:</h4><ul>";
                foreach ($methods as $method) {
                    $html .= "<li><code>" . htmlspecialchars($method->getName()) . "()</code></li>";
                }
                $html .= "</ul>";
            }

            $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
            if ($properties) {
                $html .= "<h4>Öffentliche Properties:</h4><ul>";
                foreach ($properties as $property) {
                    $html .= "<li><code>\$" . htmlspecialchars($property->getName()) . "</code></li>";
                }
                $html .= "</ul>";
            }
        }

        return $html;
    }

    private function buildFunctionSignature(ReflectionFunction $reflection): string
    {
        $params = [];
        foreach ($reflection->getParameters() as $param) {
            $paramStr = '';
            if ($param->hasType()) {
                $paramStr .= $param->getType() . ' ';
            }
            $paramStr .= '$' . $param->getName();
            if ($param->isDefaultValueAvailable()) {
                $paramStr .= ' = ' . var_export($param->getDefaultValue(), true);
            }
            $params[] = $paramStr;
        }

        $returnType = '';
        if ($reflection->hasReturnType()) {
            $returnType = ': ' . $reflection->getReturnType();
        }

        return $reflection->getName() . '(' . implode(', ', $params) . ')' . $returnType;
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
