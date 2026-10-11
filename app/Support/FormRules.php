<?php

namespace App\Support;

/**
 * DataTables sütun tanımlarından Laravel doğrulama kuralları üretir.
 */
final class FormRules
{
    public static function for(string $table): array
    {
        $def = DataTables::get($table);
        $required = $def['required'] ?? [];
        $rules = [];

        foreach ($def['columns'] as $col => $meta) {
            $type = $meta[1];
            $rules[$col] = array_merge(
                in_array($col, $required, true) ? ['required'] : ['nullable'],
                match ($type) {
                    'int' => ['integer', 'between:-2147483648,2147483647'],
                    'float' => ['numeric'],
                    'bool' => ['boolean'],
                    'string' => ['string', 'max:'.($meta[3] ?? 64)],
                    'text' => ['string', 'max:20000'],
                    default => [],
                }
            );
        }

        return $rules;
    }

    /** Doğrulama mesajlarında görünecek Türkçe alan adları */
    public static function attributes(string $table): array
    {
        return array_map(fn ($m) => $m[0], DataTables::get($table)['columns']);
    }
}
