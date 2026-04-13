<?php

namespace BitrixCdd\Tools\Export;

/**
 * Рендеринг PHP-массивов в файлы конфигурации
 */
class PhpConfigRenderer
{
    public function render(array $config): string
    {
        $config = $this->cleanServiceKeys($config);
        return "<?php\nreturn " . $this->varExport($config) . ";\n";
    }

    /**
     * Убрать служебные ключи (_bitrix_type и т.д.)
     */
    private function cleanServiceKeys(array $config): array
    {
        if (isset($config['properties'])) {
            foreach ($config['properties'] as &$prop) {
                unset($prop['_bitrix_type']);
            }
            unset($prop);
        }
        return $config;
    }

    private function varExport($value, int $indent = 0): string
    {
        $pad = str_repeat('    ', $indent);
        $padInner = str_repeat('    ', $indent + 1);

        if (is_array($value)) {
            if (empty($value)) return '[]';

            $isSequential = array_keys($value) === range(0, count($value) - 1);
            $lines = [];

            foreach ($value as $k => $v) {
                $exportedValue = $this->varExport($v, $indent + 1);
                if ($isSequential) {
                    $lines[] = $padInner . $exportedValue;
                } else {
                    $key = is_int($k) ? $k : "'" . addslashes($k) . "'";
                    $lines[] = $padInner . $key . ' => ' . $exportedValue;
                }
            }

            return "[\n" . implode(",\n", $lines) . ",\n" . $pad . ']';
        }

        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_int($value)) return (string)$value;
        if (is_float($value)) return (string)$value;
        if (is_null($value)) return 'null';

        return "'" . addslashes((string)$value) . "'";
    }
}
