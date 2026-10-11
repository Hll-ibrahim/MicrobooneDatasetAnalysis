<?php

namespace App\Jobs;

use App\Models\H5File;
use App\Services\H5Importer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Bir .h5 dosyasını arka planda PostgreSQL'e aktarır.
 * Çalışması için: php artisan queue:work --timeout=0
 */
class ImportH5File implements ShouldQueue
{
    use Queueable;

    /** Uzun içe aktarmalar yeniden denenmez; hata arayüzde gösterilir. */
    public $tries = 1;

    /** Saniye (6 saat) */
    public $timeout = 21600;

    public $failOnTimeout = true;

    public function __construct(public int $fileId) {}

    public function handle(H5Importer $importer): void
    {
        $file = H5File::find($this->fileId);

        // Silinmiş ya da başka bir işçi tarafından alınmışsa hiçbir şey yapma
        if ($file === null || $file->status !== H5File::QUEUED) {
            return;
        }

        $importer->run($file);
    }

    public function failed(?Throwable $e): void
    {
        H5File::whereKey($this->fileId)
            ->whereIn('status', [H5File::QUEUED, H5File::EXPORTING, H5File::IMPORTING])
            ->update([
                'status' => H5File::FAILED,
                'error' => $e?->getMessage() ?? 'Kuyruk işi başarısız oldu.',
                'status_message' => 'İçe aktarma başarısız oldu.',
                'finished_at' => now(),
            ]);
    }
}
