<?php

namespace App\Console\Commands;

use App\Models\H5File;
use App\Services\H5Tool;
use App\Support\DataTables;
use Illuminate\Console\Command;

class H5Inspect extends Command
{
    protected $signature = 'h5:inspect {path : .h5 dosyasının yolu}
                            {--columns : Her grubun sütunlarını da listele}';

    protected $description = 'Bir .h5 dosyasının grup/sütun şemasını gösterir (veritabanına yazmaz)';

    public function handle(H5Tool $tool): int
    {
        $info = $tool->inspect($this->argument('path'));

        $this->info($info['file'].'  ('.H5File::bytes($info['size_bytes']).', '.($info['n_events'] ?? '?').' olay)');

        $rows = [];
        foreach ($info['groups'] as $g) {
            $rows[] = [
                $g['name'],
                DataTables::tableForGroup($g['name']) ?? '—',
                number_format($g['rows'], 0, ',', '.'),
                $g['events'] ?? '—',
                count($g['columns']),
            ];
        }
        $this->table(['HDF5 grubu', 'Veritabanı tablosu', 'Satır', 'Olay', 'Dataset'], $rows);

        if ($this->option('columns')) {
            foreach ($info['groups'] as $g) {
                $this->newLine();
                $this->comment($g['name']);
                $this->table(
                    ['Sütun', 'Şekil', 'Tür'],
                    array_map(fn ($c) => [$c['name'], '('.implode(', ', $c['shape']).')', $c['dtype']], $g['columns'])
                );
            }
        }

        return self::SUCCESS;
    }
}
