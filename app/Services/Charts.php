<?php

namespace App\Services;

use App\Support\Pdg;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

/**
 * Chart.js için veri hazırlayan yardımcılar. Hesaplar SQL tarafında yapılır;
 * tarayıcıya yalnızca kutu (bin) sayıları gider.
 *
 * Not: $expr parametreleri kullanıcıdan DEĞİL, koddan gelir (ham SQL ifadesi).
 */
class Charts
{
    private static function base(Builder|EloquentBuilder $q): Builder
    {
        return $q instanceof EloquentBuilder ? $q->toBase() : $q;
    }

    /**
     * Sabit genişlikli histogram. Üst sınırı aşan değerler son kutuya eklenir.
     *
     * @return array{labels: list<string>, data: list<int>, lo: float, hi: float}
     */
    public static function histogram(Builder|EloquentBuilder $query, string $expr, int $bins = 30, ?float $lo = null, ?float $hi = null, bool $overflow = true): array
    {
        // Tamsayı sütunlarda PostgreSQL parametre türünü tamsayı sanmasın diye
        // ifade ve parametreler açıkça DOUBLE PRECISION'a çevrilir.
        $x = "CAST(({$expr}) AS DOUBLE PRECISION)";
        $q = (clone self::base($query))->whereRaw("({$expr}) IS NOT NULL");

        if ($lo === null || $hi === null) {
            $mm = (clone $q)->selectRaw("MIN({$x}) AS lo, MAX({$x}) AS hi")->first();
            if ($mm === null || $mm->lo === null) {
                return ['labels' => [], 'data' => [], 'lo' => 0, 'hi' => 0];
            }
            $lo ??= (float) $mm->lo;
            $hi ??= (float) $mm->hi;
        }
        if ($hi <= $lo) {
            $hi = $lo + 1;
        }
        $w = ($hi - $lo) / $bins;

        $q->whereRaw("{$x} >= CAST(? AS DOUBLE PRECISION)", [$lo]);
        if (! $overflow) {
            $q->whereRaw("{$x} <= CAST(? AS DOUBLE PRECISION)", [$hi]);
        }

        $counts = $q->selectRaw(
            "LEAST(CAST(FLOOR(({$x} - CAST(? AS DOUBLE PRECISION)) / CAST(? AS DOUBLE PRECISION)) AS INTEGER), CAST(? AS INTEGER)) AS b, COUNT(*) AS n",
            [$lo, $w, $bins - 1]
        )
            ->groupByRaw('1')
            ->pluck('n', 'b')
            ->all();

        $labels = [];
        $data = [];
        $dec = self::decimals($w);
        for ($i = 0; $i < $bins; $i++) {
            $labels[] = number_format($lo + ($i + 0.5) * $w, $dec, '.', '');
            $data[] = (int) ($counts[$i] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data, 'lo' => $lo, 'hi' => $hi];
    }

    /**
     * Yüzdelik değer (ör. 0.99) — aykırı değerler histogramı ezmesin diye
     * üst sınır olarak kullanılır. Yalnızca PostgreSQL.
     */
    public static function percentile(Builder|EloquentBuilder $query, string $expr, float $p): ?float
    {
        $row = (clone self::base($query))
            ->whereRaw("({$expr}) IS NOT NULL")
            ->selectRaw("PERCENTILE_CONT(CAST(? AS DOUBLE PRECISION)) WITHIN GROUP (ORDER BY {$expr}) AS v", [$p])
            ->first();

        return $row?->v === null ? null : (float) $row->v;
    }

    /**
     * Bir sütundaki değerlerin sayımı (en sık $limit değer).
     *
     * @return array{labels: list<string>, data: list<int>}
     */
    public static function countBy(Builder|EloquentBuilder $query, string $expr, int $limit = 15, ?callable $label = null, bool $orderByValue = false): array
    {
        $rows = (clone self::base($query))
            ->selectRaw("{$expr} AS v, COUNT(*) AS n")
            ->whereRaw("({$expr}) IS NOT NULL")
            ->groupByRaw('1')
            ->when($orderByValue, fn ($q) => $q->orderByRaw('1'), fn ($q) => $q->orderByRaw('2 DESC'))
            ->limit($limit)
            ->get();

        return [
            'labels' => $rows->map(fn ($r) => $label ? $label($r->v) : (string) $r->v)->all(),
            'data' => $rows->map(fn ($r) => (int) $r->n)->all(),
        ];
    }

    public static function pdgCounts(Builder|EloquentBuilder $query, string $column, int $limit = 12): array
    {
        return self::countBy($query, $column, $limit, fn ($v) => Pdg::label((int) $v));
    }

    /**
     * Dağılım grafiği için rastgele örnek: [[x, y], ...]
     */
    public static function scatter(Builder|EloquentBuilder $query, string $x, string $y, int $limit = 2500): array
    {
        return (clone self::base($query))
            ->selectRaw("{$x} AS x, {$y} AS y")
            ->whereRaw("({$x}) IS NOT NULL AND ({$y}) IS NOT NULL")
            ->inRandomOrder()
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['x' => round((float) $r->x, 4), 'y' => round((float) $r->y, 4)])
            ->all();
    }

    private static function decimals(float $w): int
    {
        if ($w >= 10) {
            return 0;
        }
        if ($w >= 1) {
            return 1;
        }
        if ($w >= 0.1) {
            return 2;
        }

        return 3;
    }
}
