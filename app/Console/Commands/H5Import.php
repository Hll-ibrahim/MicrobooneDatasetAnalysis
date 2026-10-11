<?php

namespace App\Console\Commands;

use App\Models\H5File;
use App\Services\H5Importer;
use App\Services\H5Tool;
use App\Support\DataTables;
use Illuminate\Console\Command;
use Throwable;

class H5Import extends Command
{
    protected $signature = 'h5:import {path : .h5 dosyasının yolu}
                            {--offset=0 : Atlanacak olay sayısı}
                            {--max-events= : Aktarılacak olay sayısı (varsayılan: config h5.default_max_events)}
                            {--all : Dosyadaki tüm olayları aktar}
                            {--groups= : Virgülle ayrılmış HDF5 grupları (varsayılan: hepsi)}
                            {--name= : Arayüzde görünecek ad}';

    protected $description = '.h5 dosyasını PostgreSQL\'e aktarır (kuyruk kullanmadan, terminalde)';

    public function handle(H5Tool $tool, H5Importer $importer): int
    {
        $path = realpath($this->argument('path'));
        if ($path === false || ! is_file($path)) {
            $this->error('Dosya bulunamadı: '.$this->argument('path'));

            return self::FAILURE;
        }

        $this->info("Şema okunuyor: {$path}");
        $schema = $tool->inspect($path);

        $groups = $this->option('groups')
            ? array_map('trim', explode(',', $this->option('groups')))
            : array_keys(DataTables::groupMap());

        $file = H5File::create([
            'name' => $this->option('name') ?: basename($path),
            'path' => $path,
            'size_bytes' => filesize($path),
            'schema' => $schema,
            'file_events' => $schema['n_events'] ?? null,
            'import_offset' => (int) $this->option('offset'),
            'import_max_events' => $this->option('all') ? null : (int) ($this->option('max-events') ?: config('h5.default_max_events')),
            'import_groups' => $groups,
            'status' => H5File::QUEUED,
            'queued_at' => now(),
        ]);

        $this->line("Kayıt #{$file->id} oluşturuldu. Olay: "
            .($file->import_max_events ?? 'tümü').", başlangıç: {$file->import_offset}");

        $bar = $this->output->createProgressBar(100);
        $bar->setFormat(' %current%% [%bar%] %message%');
        $bar->setMessage('başlıyor…');
        $bar->start();

        try {
            $importer->run($file, function (int $p, string $msg) use ($bar) {
                $bar->setProgress(min(100, $p));
                $bar->setMessage(mb_strimwidth($msg, 0, 70, '…'));
            });
        } catch (Throwable $e) {
            $bar->clear();
            $this->error('Hata: '.$e->getMessage());

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        $file->refresh();
        $this->table(
            ['Tablo', 'Satır'],
            collect($file->row_counts)->map(fn ($n, $t) => [$t, number_format($n, 0, ',', '.')])->values()->all()
        );
        foreach ($file->unmapped_columns ?? [] as $g => $cols) {
            $this->warn("{$g}: veritabanında karşılığı olmayan sütunlar → ".implode(', ', $cols));
        }
        $this->info('Bitti. Web arayüzünde: '.route('files.show', $file));

        return self::SUCCESS;
    }
}
