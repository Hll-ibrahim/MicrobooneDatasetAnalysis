<?php

namespace App\Models;

/** opflash_table — optik flaşlar */
class OpFlash extends EventRecord
{
    protected $table = 'opflashes';

    protected function casts(): array
    {
        return [
            'flash_id' => 'integer',
            'time' => 'float',
            'totalpe' => 'float',
        ];
    }
}
