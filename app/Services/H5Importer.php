<?php

namespace App\Services;

use App\Models\H5File;
use App\Support\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * .h5  ->  PostgreSQL içe aktarma hattı
 *
 *   1) h5_tool.py export : seçilen olay aralığı her grup için TSV'ye yazılır
 *   2) event_table       : events tablosuna COPY ile yazılır
 *   3) diğer gruplar     : (run, subrun, event) -> events.id eşlenerek COPY
 *   4) özet sayılar      : events.hit_count vb. SQL ile hesaplanır
 *
 * İlerleme h5_files.progress / status_message alanlarına yazılır; web
 * arayüzü bunu birkaç saniyede bir okur.
 */
class H5Importer
{
    /** Veritabanında olup h5'ten DOLDURULMAYAN sütunlar */
    private const RESERVED = [
        'id', 'h5_file_id', 'event_id', 'created_at', 'updated_at',
        'hit_count', 'hit_count_u', 'hit_count_v', 'hit_count_y', 'nu_hit_count',
        'particle_count', 'slice_count', 'flash_count', 'label', 'notes',
    ];

    private const KEY_COLUMNS = ['run', 'subrun', 'event'];

    private float $lastSave = 0.0;

    /** @var callable|null */
    private $reporter = null;

    public function __construct(private readonly H5Tool $tool) {}

    /**
     * @param  callable(int $percent, string $message): void|null  $reporter  (konsol için)
     */
    public function run(H5File $file, ?callable $reporter = null): void
    {
        @set_time_limit(0);
        $this->reporter = $reporter;
        $tmp = rtrim(config('h5.tmp_dir'), '/').'/'.$file->id.'-'.Str::lower(Str::random(6));

        try {
            if (! $file->fileExists()) {
                throw new RuntimeException("Dosya bulunamadı: {$file->path}");
            }

            $this->save($file, [
                'status' => H5File::EXPORTING,
                'progress' => 1,
                'status_message' => 'Eski kayıtlar temizleniyor…',
                'started_at' => now(),
                'finished_at' => null,
                'error' => null,
                'row_counts' => null,
                'unmapped_columns' => null,
            ], force: true);

            $file->purgeData();

            // ---------------------------------------------------------- 1) h5 -> TSV
            $groups = $file->import_groups ?: array_keys(DataTables::groupMap());
            File::ensureDirectoryExists($tmp);

            $manifest = $this->tool->export(
                $file->path, $tmp, (int) $file->import_offset, $file->import_max_events, $groups,
                function (array $m) use ($file) {
                    if (($m['event'] ?? null) === 'progress') {
                        $inGroup = $m['rows_total'] > 0 ? $m['rows_done'] / $m['rows_total'] : 1;
                        $frac = ($m['group_index'] + $inGroup) / max(1, $m['group_count']);
                        $this->save($file, [
                            'progress' => 1 + (int) floor(44 * $frac),
                            'status_message' => "h5 okunuyor: {$m['group']} (".number_format($m['rows_done'], 0, ',', '.').' / '.number_format($m['rows_total'], 0, ',', '.').' satır)',
                        ]);
                    } elseif (($m['event'] ?? null) === 'start') {
                        $this->save($file, [
                            'file_events' => $m['file_events'],
                            'status_message' => "{$m['selected_events']} olay seçildi, h5 okunuyor…",
                        ], force: true);
                    }
                }
            );

            // ---------------------------------------------------------- 2-3) TSV -> PostgreSQL
            $this->save($file, [
                'status' => H5File::IMPORTING,
                'progress' => 45,
                'file_events' => $manifest['file_events'],
                'status_message' => 'Veritabanına yazılıyor…',
            ], force: true);

            $totalRows = max(1, array_sum(array_column($manifest['groups'], 'rows')));
            $doneRows = 0;
            $counts = [];
            $unmapped = [];
            $eventMap = [];

            foreach ($manifest['groups'] as $group => $info) {
                $table = DataTables::tableForGroup($group);
                if ($table === null) {
                    $unmapped[$group] = ['(bu grup için veritabanı tablosu yok)'];

                    continue;
                }

                [$written, $skipped, $extra] = $this->loadTsv(
                    $file, $table, $tmp.'/'.$info['file'], $info['kinds'] ?? [], $eventMap,
                    function (int $n) use ($file, $table, $doneRows, $totalRows) {
                        $this->save($file, [
                            'progress' => 45 + (int) floor(50 * ($doneRows + $n) / $totalRows),
                            'status_message' => "{$table} tablosuna yazılıyor: ".number_format($n, 0, ',', '.').' satır',
                        ]);
                    }
                );

                $counts[$table] = $written;
                if ($extra !== []) {
                    $unmapped[$group] = $extra;
                }
                if ($skipped > 0) {
                    $unmapped[$group][] = "({$skipped} satır eşleşen olay bulunamadığı/yinelendiği için atlandı)";
                }
                $doneRows += $info['rows'];

                if ($table === 'events') {
                    $eventMap = $this->eventMap($file->id);
                }
            }

            foreach ($manifest['missing_groups'] ?? [] as $g) {
                $unmapped[$g] = ['(grup bu dosyada yok)'];
            }

            // ---------------------------------------------------------- 4) özetler
            $this->save($file, ['progress' => 96, 'status_message' => 'Olay özetleri hesaplanıyor…'], force: true);
            EventStats::forFile($file->id);

            // Sorgu planlayıcısı için istatistikleri tazele (büyük COPY sonrası önemli)
            if (DB::connection()->getDriverName() === 'pgsql') {
                foreach (array_keys($counts) as $table) {
                    DB::statement('ANALYZE "'.$table.'"');
                }
            }

            $this->save($file, [
                'status' => H5File::COMPLETED,
                'progress' => 100,
                'row_counts' => $counts,
                'unmapped_columns' => $unmapped ?: null,
                'finished_at' => now(),
                'status_message' => number_format($counts['events'] ?? 0, 0, ',', '.').' olay ve toplam '
                    .number_format(array_sum($counts), 0, ',', '.').' satır aktarıldı.',
            ], force: true);
        } catch (Throwable $e) {
            try {
                $file->purgeData();
            } catch (Throwable) {
                // temizlik hatası asıl hatayı gizlemesin
            }
            $this->save($file, [
                'status' => H5File::FAILED,
                'error' => Str::limit($e->getMessage(), 4000),
                'finished_at' => now(),
                'status_message' => 'İçe aktarma başarısız oldu.',
            ], force: true);

            throw $e;
        } finally {
            File::deleteDirectory($tmp);
        }
    }

    /**
     * Bir TSV dosyasını tabloya yazar.
     *
     * @return array{0:int,1:int,2:list<string>} [yazılan, atlanan, eşlenmeyen sütunlar]
     */
    private function loadTsv(H5File $file, string $table, string $path, array $kinds, array $eventMap, callable $tick): array
    {
        $fh = fopen($path, 'r');
        if ($fh === false) {
            throw new RuntimeException("Ara dosya açılamadı: {$path}");
        }

        $header = explode("\t", rtrim((string) fgets($fh), "\r\n"));
        $dbTypes = $this->columnTypes($table);
        $isEvents = $table === 'events';

        $out = $isEvents ? ['h5_file_id', 'created_at', 'updated_at'] : ['h5_file_id', 'event_id'];
        $pick = [];      // TSV indeksi => dönüşüm kodu (null = olduğu gibi)
        $unmapped = [];

        foreach ($header as $i => $col) {
            if (in_array($col, self::KEY_COLUMNS, true)) {
                if ($isEvents) {
                    $out[] = $col;
                    $pick[$i] = null;
                }

                continue;
            }
            if (! isset($dbTypes[$col]) || in_array($col, self::RESERVED, true)) {
                $unmapped[] = $col;

                continue;
            }
            $out[] = $col;
            $pick[$i] = $this->castFor($dbTypes[$col], $kinds[$col] ?? null);
        }

        $writer = new PgCopyWriter($table, $out, (int) config('h5.copy_batch', 20000));
        $now = now()->format('Y-m-d H:i:s');
        $fid = (string) $file->id;
        $seen = [];
        $n = 0;
        $skipped = 0;

        while (($line = fgets($fh)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }
            $v = explode("\t", $line);
            $key = $v[0].':'.$v[1].':'.$v[2];

            if ($isEvents) {
                if (isset($seen[$key])) {   // yinelenen olay kimliği
                    $skipped++;

                    continue;
                }
                $seen[$key] = true;
                $row = [$fid, $now, $now];
            } else {
                $eid = $eventMap[$key] ?? null;
                if ($eid === null) {
                    $skipped++;

                    continue;
                }
                $row = [$fid, $eid];
            }

            foreach ($pick as $i => $cast) {
                $x = $v[$i] ?? '\N';
                if ($cast !== null && $x !== '\N') {
                    $x = $this->applyCast($cast, $x);
                }
                $row[] = $x;
            }

            $writer->add(implode("\t", $row));
            if (++$n % 50000 === 0) {
                $tick($n);
            }
        }

        $writer->flush();
        fclose($fh);

        return [$writer->written(), $skipped, $unmapped];
    }

    /** sütun => postgres tür adı (int4, float8, bool, varchar, ...) */
    private function columnTypes(string $table): array
    {
        $types = [];
        foreach (Schema::getColumns($table) as $c) {
            $types[$c['name']] = strtolower($c['type_name'] ?? $c['type'] ?? '');
            if (preg_match('/\((\d+)\)/', (string) ($c['type'] ?? ''), $m) && str_contains($types[$c['name']], 'char')) {
                $types[$c['name']] .= ':'.$m[1];
            }
        }

        return $types;
    }

    /** Kaynak (h5) türü ile hedef sütun türü uyuşmuyorsa dönüşüm kodu */
    private function castFor(string $dbType, ?string $kind): ?string
    {
        $base = explode(':', $dbType)[0];

        if (in_array($base, ['bool', 'boolean'], true)) {
            return 'bool';
        }
        if (preg_match('/^(int|smallint|bigint|integer)/', $base) && $kind === 'float') {
            return 'int';
        }
        if (str_contains($base, 'char') && str_contains($dbType, ':')) {
            return 'str:'.explode(':', $dbType)[1];
        }

        return null;
    }

    private function applyCast(string $cast, string $x): string
    {
        if ($cast === 'bool') {
            $lx = strtolower($x);
            if (in_array($lx, ['t', 'true', 'y', 'yes'], true)) {
                return '1';
            }
            if (in_array($lx, ['f', 'false', 'n', 'no'], true)) {
                return '0';
            }

            return is_numeric($x) ? ((float) $x != 0.0 ? '1' : '0') : '\N';
        }
        if ($cast === 'int') {
            return is_numeric($x) && is_finite((float) $x) ? (string) (int) round((float) $x) : '\N';
        }
        if (str_starts_with($cast, 'str:')) {
            return mb_substr($x, 0, (int) substr($cast, 4));
        }

        return $x;
    }

    /** "run:subrun:event" => events.id */
    private function eventMap(int $fileId): array
    {
        $map = [];
        foreach (DB::table('events')->where('h5_file_id', $fileId)->select('id', 'run', 'subrun', 'event')->cursor() as $e) {
            $map[$e->run.':'.$e->subrun.':'.$e->event] = (string) $e->id;
        }

        return $map;
    }

    /** İlerlemeyi en fazla saniyede bir kez yazar (force=true her zaman yazar). */
    private function save(H5File $file, array $attrs, bool $force = false): void
    {
        if (isset($attrs['progress'], $this->reporter)) {
            ($this->reporter)((int) $attrs['progress'], (string) ($attrs['status_message'] ?? $file->status_message));
        }
        $now = microtime(true);
        if (! $force && $now - $this->lastSave < 1.0) {
            return;
        }
        $this->lastSave = $now;
        $file->forceFill($attrs)->save();
    }
}
