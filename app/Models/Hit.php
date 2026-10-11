<?php

namespace App\Models;

/** hit_table — tel düzlemlerindeki darbeler */
class Hit extends EventRecord
{
    protected $table = 'hits';

    protected function casts(): array
    {
        return [
            'hit_id' => 'integer',
            'local_plane' => 'integer',
            'local_wire' => 'integer',
            'local_time' => 'float',
            'integral' => 'float',
            'rms' => 'float',
            'tpc' => 'integer',
        ];
    }
}
