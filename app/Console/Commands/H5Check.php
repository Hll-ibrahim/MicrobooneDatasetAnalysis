<?php

namespace App\Console\Commands;

use App\Services\H5Tool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class H5Check extends Command
{
    protected $signature = 'h5:check';

    protected $description = 'Python/h5py ortamını, veri klasörünü ve PostgreSQL bağlantısını denetler';

    public function handle(H5Tool $tool): int
    {
        [$ok, $msg] = $tool->check();
        $this->line(($ok ? '<info>✔</info>' : '<error>✘</error>').' Python  : '.$msg);

        $dir = config('h5.data_dir');
        $dirOk = is_dir($dir) && is_writable($dir);
        $this->line(($dirOk ? '<info>✔</info>' : '<error>✘</error>')." Klasör  : {$dir}".($dirOk ? '' : ' (yok ya da yazılamıyor)'));

        try {
            $ver = DB::selectOne('select version() as v')->v ?? '?';
            $driver = DB::connection()->getDriverName();
            $this->line("<info>✔</info> Veritabanı: {$driver} — ".strtok($ver, ','));
            $dbOk = true;
        } catch (Throwable $e) {
            $this->line('<error>✘</error> Veritabanı: '.$e->getMessage());
            $dbOk = false;
        }

        return $ok && $dirOk && $dbOk ? self::SUCCESS : self::FAILURE;
    }
}
