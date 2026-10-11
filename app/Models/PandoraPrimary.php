<?php

namespace App\Models;

/** pandoraPrimary_table — Pandora dilimleri */
class PandoraPrimary extends EventRecord
{
    protected $table = 'pandora_primaries';

    protected function casts(): array
    {
        return [
            'slice_id' => 'integer',
            'slice_pdg' => 'integer',
            'nu_score' => 'float',
            'flashmatch_score' => 'float',
        ];
    }
}
