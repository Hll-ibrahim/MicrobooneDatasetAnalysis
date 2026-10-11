<?php

namespace App\Models;

/** opflashsumpe_table — flaş × PMT PE toplamları */
class OpFlashSumPe extends EventRecord
{
    protected $table = 'opflash_sumpes';

    protected function casts(): array
    {
        return [
            'sumpe_id' => 'integer',
            'flash_id' => 'integer',
            'pmt_channel' => 'integer',
            'sumpe' => 'float',
        ];
    }
}
