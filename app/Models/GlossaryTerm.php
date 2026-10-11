<?php

namespace App\Models;

use App\Support\GlossaryData;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Terimler sözlüğündeki bir terim.
 */
class GlossaryTerm extends Model
{
    protected $table = 'glossary_terms';

    protected $guarded = ['id'];

    /** @var array<string, self>|null istek başına önbellek */
    private static ?array $bySlug = null;

    protected function casts(): array
    {
        return ['related' => 'array'];
    }

    /** URL'lerde id yerine slug kullanılır: /sozluk/hit/duzenle */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function categoryLabel(): string
    {
        return GlossaryData::CATEGORIES[$this->category] ?? $this->category;
    }

    /** Baş harf (A–Z, Türkçe harfler dahil) — sözlük dizini için */
    public function initial(): string
    {
        return mb_strtoupper(mb_substr(ltrim($this->term, '0123456789 ('), 0, 1));
    }

    /** slug → terim (tek sorguyla, istek boyunca önbellekte) */
    public static function lookup(string $slug): ?self
    {
        if (self::$bySlug === null) {
            try {
                self::$bySlug = self::query()->get()->keyBy('slug')->all();
            } catch (Throwable) {
                self::$bySlug = [];   // tablo henüz yoksa (migrate edilmemişse) sessizce geç
            }
        }

        return self::$bySlug[$slug] ?? null;
    }

    public static function flushLookup(): void
    {
        self::$bySlug = null;
    }
}
