<?php

namespace App\Models;

/** ophit_table — PMT darbeleri */
class OpHit extends EventRecord
{
    protected $table = 'ophits';

    protected function casts(): array
    {
        return [
            'hit_id' => 'integer',
            'hit_channel' => 'integer',
            'sumpe_id' => 'integer',
        ];
    }
}
