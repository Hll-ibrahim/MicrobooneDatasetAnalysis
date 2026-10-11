<?php

namespace App\Models;

/** pandoraHit_table — hit → dilim/PFP ataması */
class PandoraHit extends EventRecord
{
    protected $table = 'pandora_hits';

    protected function casts(): array
    {
        return [
            'hit_id' => 'integer',
            'slice_id' => 'integer',
            'pfp_id' => 'integer',
        ];
    }
}
