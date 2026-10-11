<?php

namespace App\Support;

/**
 * PDG parçacık kodları için okunabilir adlar.
 */
final class Pdg
{
    private const NAMES = [
        11 => 'e⁻', -11 => 'e⁺',
        12 => 'νe', -12 => 'ν̄e',
        13 => 'μ⁻', -13 => 'μ⁺',
        14 => 'νμ', -14 => 'ν̄μ',
        16 => 'ντ', -16 => 'ν̄τ',
        22 => 'γ',
        111 => 'π⁰', 211 => 'π⁺', -211 => 'π⁻',
        130 => 'K⁰L', 310 => 'K⁰S', 311 => 'K⁰',
        321 => 'K⁺', -321 => 'K⁻',
        2112 => 'n', -2112 => 'n̄',
        2212 => 'p', -2212 => 'p̄',
        3122 => 'Λ', 3222 => 'Σ⁺', 3112 => 'Σ⁻',
        1000010020 => 'd', 1000010030 => 't', 1000020030 => '³He', 1000020040 => 'α',
        1000180400 => '⁴⁰Ar',
    ];

    public static function name(?int $code): string
    {
        if ($code === null) {
            return '—';
        }
        if (isset(self::NAMES[$code])) {
            return self::NAMES[$code];
        }
        if ($code > 1000000000) {
            $z = intdiv($code % 10000000, 10000);
            $a = intdiv($code % 10000, 10);

            return "iyon Z={$z} A={$a}";
        }

        return (string) $code;
    }

    public static function label(?int $code): string
    {
        return $code === null ? '—' : self::name($code).' ('.$code.')';
    }

    public static function plane(?int $plane): string
    {
        return match ($plane) {
            0 => 'U',
            1 => 'V',
            2 => 'Y',
            default => (string) $plane,
        };
    }
}
