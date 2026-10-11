<?php

/*
| Görünümlerde kullanılan küçük biçimlendirme yardımcıları (Türkçe sayı biçimi).
| composer.json → autoload.files ile yüklenir.
*/

if (! function_exists('tr_num')) {
    /** 1234567.8 → "1.234.568" (ondalık sayısı verilebilir) */
    function tr_num(int|float|string|null $v, int $decimals = 0): string
    {
        if ($v === null || $v === '' || ! is_numeric($v)) {
            return '—';
        }

        return number_format((float) $v, $decimals, ',', '.');
    }
}

if (! function_exists('tr_float')) {
    /** Büyüklüğe göre anlamlı basamakla ondalık gösterim */
    function tr_float(int|float|string|null $v, int $sig = 4): string
    {
        if ($v === null || $v === '' || ! is_numeric($v)) {
            return '—';
        }
        $v = (float) $v;
        if ($v == 0.0) {
            return '0';
        }
        $abs = abs($v);
        if ($abs >= 1e6 || $abs < 1e-4) {
            return str_replace('.', ',', sprintf('%.'.($sig - 1).'e', $v));
        }
        $decimals = max(0, $sig - 1 - (int) floor(log10($abs)));

        return number_format($v, min($decimals, 6), ',', '.');
    }
}
