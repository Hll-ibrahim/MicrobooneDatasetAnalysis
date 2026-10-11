<?php

namespace App\Models;

use App\Support\Pdg;

/** particle_table — Geant4 parçacıkları */
class Particle extends EventRecord
{
    protected $table = 'particles';

    protected function casts(): array
    {
        return [
            'g4_id' => 'integer',
            'parent_id' => 'integer',
            'g4_pdg' => 'integer',
            'category' => 'integer',
            'instance' => 'integer',
            'momentum' => 'float',
        ];
    }

    public function pdgName(): string
    {
        return Pdg::name($this->g4_pdg);
    }

    /** Ana parçacığı olmayan (doğrudan nötrino etkileşiminden çıkan) parçacık mı? */
    public function isPrimary(): bool
    {
        return $this->start_process === 'primary' || (int) $this->parent_id <= 0;
    }
}
