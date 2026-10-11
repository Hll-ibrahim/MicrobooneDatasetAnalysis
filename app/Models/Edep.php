<?php

namespace App\Models;

/** edep_table — hit ↔ parçacık gerçek enerji bağları */
class Edep extends EventRecord
{
    protected $table = 'edeps';

    protected function casts(): array
    {
        return [
            'hit_id' => 'integer',
            'g4_id' => 'integer',
            'energy' => 'float',
            'energy_fraction' => 'float',
        ];
    }
}
