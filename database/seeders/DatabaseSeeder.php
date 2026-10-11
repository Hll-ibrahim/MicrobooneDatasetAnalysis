<?php

namespace Database\Seeders;

use App\Models\H5File;
use App\Services\H5Importer;
use App\Services\H5Tool;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Demo verisi: küçük bir SENTETİK NoWire örneği üretir ve içe aktarır.
 *
 *     php artisan db:seed
 *
 * Gerçek veriyle çalışırken gerekli değildir.
 */
class DatabaseSeeder extends Seeder
{
    public function run(H5Tool $tool, H5Importer $importer): void
    {
        $dir = config('h5.data_dir');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/ornek_sentetik_nowire.h5';

        if (! is_file($path)) {
            $this->command->info('Sentetik örnek üretiliyor (60 olay)…');
            Process::timeout(300)->run([config('h5.python'), base_path('scripts/make_sample_h5.py'), $path, '--events', '60'])->throw();
        }

        $schema = $tool->inspect($path);
        $file = H5File::create([
            'name' => 'ornek_sentetik_nowire.h5',
            'path' => realpath($path),
            'size_bytes' => filesize($path),
            'description' => 'SENTETİK demo verisi — bilimsel analizde kullanmayın.',
            'schema' => $schema,
            'file_events' => $schema['n_events'] ?? null,
            'import_offset' => 0,
            'import_max_events' => null,
            'import_groups' => null,
            'status' => H5File::QUEUED,
            'queued_at' => now(),
        ]);

        $this->command->info('İçe aktarılıyor…');
        $importer->run($file);
        $file->refresh();
        $this->command->info($file->status_message);
    }
}
