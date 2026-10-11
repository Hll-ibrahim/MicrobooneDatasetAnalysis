<?php

namespace App\Models;

/** pandoraPfp_table — iz/sağanak nesneleri */
class PandoraPfp extends EventRecord
{
    protected $table = 'pandora_pfps';

    protected function casts(): array
    {
        return [
            'pfp_id' => 'integer',
            'pfp_pdg' => 'integer',
            'trkshr_score' => 'float',
        ];
    }
}
