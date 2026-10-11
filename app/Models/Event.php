<?php

namespace App\Models;

use App\Support\Pdg;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

/**
 * event_table — bir olay: dedektör okuması + simüle nötrino etkileşimi.
 */
class Event extends Model
{
    protected $table = 'events';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'run' => 'integer',
            'subrun' => 'integer',
            'event' => 'integer',
            'is_cc' => 'boolean',
            'nu_pdg' => 'integer',
            'nu_energy' => 'float',
            'lep_energy' => 'float',
        ];
    }

    // ---------------------------------------------------------------- ilişkiler

    public function h5File(): BelongsTo
    {
        return $this->belongsTo(H5File::class);
    }

    public function hits(): HasMany
    {
        return $this->hasMany(Hit::class);
    }

    public function edeps(): HasMany
    {
        return $this->hasMany(Edep::class);
    }

    public function particles(): HasMany
    {
        return $this->hasMany(Particle::class);
    }

    public function pandoraPrimaries(): HasMany
    {
        return $this->hasMany(PandoraPrimary::class);
    }

    public function pandoraPfps(): HasMany
    {
        return $this->hasMany(PandoraPfp::class);
    }

    public function pandoraHits(): HasMany
    {
        return $this->hasMany(PandoraHit::class);
    }

    public function opflashes(): HasMany
    {
        return $this->hasMany(OpFlash::class);
    }

    public function opflashSumpes(): HasMany
    {
        return $this->hasMany(OpFlashSumPe::class);
    }

    public function ophits(): HasMany
    {
        return $this->hasMany(OpHit::class);
    }

    // ---------------------------------------------------------------- yardımcılar

    /** "run:subrun:event" biçiminde olay kimliği */
    public function key(): string
    {
        return "{$this->run}:{$this->subrun}:{$this->event}";
    }

    public function nuName(): string
    {
        return Pdg::name($this->nu_pdg);
    }

    /** Nötrino kökenli hit oranı (edep'i olan hit / tüm hit) */
    public function nuHitFraction(): ?float
    {
        return $this->hit_count > 0 ? $this->nu_hit_count / $this->hit_count : null;
    }

    /** nu_score'u en yüksek Pandora dilimi */
    public function bestSlice(): ?PandoraPrimary
    {
        return $this->pandoraPrimaries()
            ->where('nu_score', '>', -900)
            ->orderByDesc('nu_score')
            ->first();
    }

    /**
     * Liste sayfasındaki filtreler (dosya, run, enerji aralığı, PDG, etiket, arama).
     */
    public function scopeFilter(Builder $q, Request $r): Builder
    {
        return $q
            ->when($r->filled('file'), fn ($q) => $q->where('h5_file_id', (int) $r->input('file')))
            ->when($r->filled('run'), fn ($q) => $q->where('run', (int) $r->input('run')))
            ->when($r->filled('subrun'), fn ($q) => $q->where('subrun', (int) $r->input('subrun')))
            ->when($r->filled('pdg'), fn ($q) => $q->where('nu_pdg', (int) $r->input('pdg')))
            ->when(is_numeric($r->input('emin')), fn ($q) => $q->where('nu_energy', '>=', (float) $r->input('emin')))
            ->when(is_numeric($r->input('emax')), fn ($q) => $q->where('nu_energy', '<=', (float) $r->input('emax')))
            ->when($r->filled('label'), fn ($q) => $q->where('label', 'ilike', '%'.$r->input('label').'%'))
            ->when($r->input('labeled') === '1', fn ($q) => $q->whereNotNull('label'));
    }
}
