<?php

namespace App\Http\Controllers;

use App\Jobs\ImportH5File;
use App\Models\Event;
use App\Models\H5File;
use App\Services\Charts;
use App\Services\H5Tool;
use App\Support\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class H5FileController extends Controller
{
    public function __construct(private readonly H5Tool $tool) {}

    /** Dosya listesi + yükleme + sunucu klasöründeki .h5 dosyaları */
    public function index(): View
    {
        $files = H5File::latest()->get();
        $registered = $files->pluck('path')->all();
        $dataDir = config('h5.data_dir');

        $available = collect(is_dir($dataDir) ? File::files($dataDir) : [])
            ->filter(fn ($f) => in_array(strtolower($f->getExtension()), ['h5', 'hdf5'], true))
            ->map(fn ($f) => [
                'name' => $f->getFilename(),
                'size' => $f->getSize(),
                'mtime' => $f->getMTime(),
                'registered' => in_array($f->getRealPath(), $registered, true),
            ])
            ->sortBy('name')
            ->values();

        $python = Cache::remember('h5.python-check', 300, fn () => $this->tool->check());

        return view('files.index', [
            'files' => $files,
            'available' => $available,
            'dataDir' => $dataDir,
            'python' => $python,
            'uploadLimit' => ini_get('upload_max_filesize'),
        ]);
    }

    /** Tarayıcıdan .h5 yükleme (küçük dosyalar için) */
    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'h5' => ['required', 'file'],
        ], [
            'h5.required' => 'Lütfen bir .h5 dosyası seçin.',
            'h5.uploaded' => 'Yükleme başarısız. Dosya PHP upload_max_filesize sınırını aşıyor olabilir; büyük dosyaları veri klasörüne kopyalayın.',
        ]);

        $upload = $request->file('h5');
        $ext = strtolower($upload->getClientOriginalExtension());
        if (! in_array($ext, ['h5', 'hdf5'], true)) {
            return back()->withErrors(['h5' => 'Yalnızca .h5 / .hdf5 uzantılı dosyalar kabul edilir.']);
        }

        $dir = config('h5.data_dir');
        File::ensureDirectoryExists($dir);
        $base = Str::slug(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME), '_') ?: 'dosya';
        $name = $base.'.'.$ext;
        for ($i = 2; file_exists($dir.'/'.$name); $i++) {
            $name = "{$base}_{$i}.{$ext}";
        }
        $upload->move($dir, $name);

        return $this->registerPath($dir.'/'.$name, $upload->getClientOriginalName());
    }

    /** Veri klasöründeki bir dosyayı sisteme kaydet */
    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate(['filename' => ['required', 'string']]);
        $dir = realpath(config('h5.data_dir'));
        $path = $dir ? realpath($dir.'/'.basename($data['filename'])) : false;

        if (! $path || ! str_starts_with($path, $dir.DIRECTORY_SEPARATOR) || ! is_file($path)) {
            return back()->withErrors(['filename' => 'Dosya veri klasöründe bulunamadı.']);
        }

        return $this->registerPath($path, basename($path));
    }

    private function registerPath(string $path, string $name): RedirectResponse
    {
        try {
            $schema = $this->tool->inspect($path);
        } catch (Throwable $e) {
            return back()->withErrors(['h5' => 'Dosya okunamadı: '.$e->getMessage()]);
        }

        if (! collect($schema['groups'])->contains('name', 'event_table')) {
            return back()->withErrors(['h5' => 'Bu dosyada event_table grubu yok; MicroBooNE NuML biçiminde değil gibi görünüyor.']);
        }

        $file = H5File::create([
            'name' => $name,
            'path' => realpath($path),
            'size_bytes' => filesize($path),
            'schema' => $schema,
            'file_events' => $schema['n_events'] ?? null,
            'import_max_events' => config('h5.default_max_events'),
            'status' => H5File::REGISTERED,
        ]);

        return redirect()->route('files.show', $file)
            ->with('success', 'Dosya kaydedildi ve şeması okundu. Aşağıdan içe aktarma ayarlarını seçebilirsiniz.');
    }

    public function show(H5File $file): View
    {
        $groupMap = DataTables::groupMap();
        $charts = [];

        if ($file->status === H5File::COMPLETED && ($file->row_counts['events'] ?? 0) > 0) {
            $events = Event::where('h5_file_id', $file->id);
            $charts['energy'] = Charts::histogram($events, 'nu_energy', 25, 0.0);
            $charts['hits'] = Charts::histogram(Event::where('h5_file_id', $file->id), 'hit_count', 25, 0.0,
                Charts::percentile(Event::where('h5_file_id', $file->id), 'hit_count', 0.99));
            $sum = DB::table('events')->where('h5_file_id', $file->id)
                ->selectRaw('SUM(hit_count_u) AS u, SUM(hit_count_v) AS v, SUM(hit_count_y) AS y')->first();
            $charts['planes'] = ['labels' => ['U', 'V', 'Y'], 'data' => [(int) $sum->u, (int) $sum->v, (int) $sum->y]];
        }

        $dbColumns = [];
        foreach (array_keys(DataTables::all()) as $table) {
            $dbColumns[$table] = Schema::getColumnListing($table);
        }

        return view('files.show', [
            'file' => $file,
            'groupMap' => $groupMap,
            'tables' => DataTables::all(),
            'dbColumns' => $dbColumns,
            'charts' => $charts,
        ]);
    }

    /** İlerleme çubuğu için JSON (sayfa birkaç saniyede bir sorar) */
    public function status(H5File $file): JsonResponse
    {
        return response()->json([
            'status' => $file->status,
            'label' => $file->statusLabel(),
            'color' => $file->statusColor(),
            'progress' => $file->progress,
            'message' => $file->status_message,
            'error' => $file->error,
            'busy' => $file->isBusy(),
            'stale' => $file->status === H5File::QUEUED && $file->queued_at?->lt(now()->subSeconds(15)),
        ]);
    }

    /** İçe aktarmayı kuyruğa al */
    public function import(Request $request, H5File $file): RedirectResponse
    {
        if ($file->isBusy()) {
            return back()->withErrors(['import' => 'Bu dosya için zaten bir içe aktarma sürüyor.']);
        }

        $data = $request->validate([
            'offset' => ['required', 'integer', 'min:0'],
            'max_events' => ['nullable', 'integer', 'min:1'],
            'all_events' => ['nullable', 'boolean'],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['string', Rule::in(array_keys(DataTables::groupMap()))],
        ]);

        $groups = array_values(array_unique(array_merge(['event_table'], $data['groups'] ?? [])));

        $file->forceFill([
            'import_offset' => (int) $data['offset'],
            'import_max_events' => $request->boolean('all_events') ? null : (int) ($data['max_events'] ?? config('h5.default_max_events')),
            'import_groups' => $groups,
            'status' => H5File::QUEUED,
            'progress' => 0,
            'status_message' => 'Kuyrukta bekliyor…',
            'error' => null,
            'queued_at' => now(),
        ])->save();

        ImportH5File::dispatch($file->id);

        return redirect()->route('files.show', $file)->with('success', 'İçe aktarma başlatıldı.');
    }

    public function update(Request $request, H5File $file): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $file->update($data);

        return back()->with('success', 'Dosya bilgileri güncellendi.');
    }

    public function destroy(Request $request, H5File $file): RedirectResponse
    {
        if ($file->isBusy() && $file->updated_at?->gt(now()->subMinutes(5))) {
            return back()->withErrors(['delete' => 'İçe aktarma sürerken dosya silinemez.']);
        }

        $file->purgeData();

        $dir = realpath(config('h5.data_dir'));
        if ($request->boolean('delete_physical') && $dir && str_starts_with((string) $file->path, $dir.DIRECTORY_SEPARATOR)) {
            File::delete($file->path);
        }
        $file->delete();

        return redirect()->route('files.index')->with('success', "“{$file->name}” ve tüm verileri silindi.");
    }

    /**
     * Dosyaya ait tüm satırları PostgreSQL'in yükleyebileceği bir .sql dosyası
     * olarak indirir (COPY ... FROM stdin blokları).
     */
    public function exportSql(H5File $file): StreamedResponse
    {
        $tables = ['h5_files', 'events', ...array_keys(DataTables::children())];
        $filename = (Str::slug(pathinfo($file->name, PATHINFO_FILENAME), '_') ?: 'h5_dosyasi').'.sql';

        return response()->streamDownload(function () use ($file, $tables) {
            @set_time_limit(0);
            $out = fopen('php://output', 'w');
            fwrite($out, "-- MicroBooNE H5 Portalı — SQL dışa aktarım\n");
            fwrite($out, "-- Kaynak dosya : {$file->name}\n");
            fwrite($out, '-- Oluşturma    : '.now()->format('Y-m-d H:i:s')."\n");
            fwrite($out, "-- Yükleme      : önce boş bir veritabanında `php artisan migrate`, sonra\n");
            fwrite($out, "--                psql -d VERITABANI -f bu_dosya.sql\n\n");
            fwrite($out, "BEGIN;\n\n");

            foreach ($tables as $table) {
                $cols = Schema::getColumnListing($table);
                $query = $table === 'h5_files'
                    ? DB::table($table)->where('id', $file->id)
                    : DB::table($table)->where('h5_file_id', $file->id);

                fwrite($out, "COPY \"{$table}\" (".implode(', ', array_map(fn ($c) => "\"{$c}\"", $cols)).") FROM stdin;\n");
                $query->chunkById(5000, function ($rows) use ($out, $cols) {
                    foreach ($rows as $r) {
                        $vals = [];
                        foreach ($cols as $c) {
                            $vals[] = self::copyValue($r->{$c});
                        }
                        fwrite($out, implode("\t", $vals)."\n");
                    }
                    flush();
                });
                fwrite($out, "\\.\n\n");
            }

            foreach ($tables as $table) {
                fwrite($out, "SELECT setval(pg_get_serial_sequence('\"{$table}\"', 'id'), COALESCE((SELECT MAX(id) FROM \"{$table}\"), 0) + 1, false);\n");
            }
            fwrite($out, "\nCOMMIT;\n");
            fclose($out);
        }, $filename, ['Content-Type' => 'application/sql; charset=UTF-8']);
    }

    private static function copyValue(mixed $v): string
    {
        if ($v === null) {
            return '\N';
        }
        if (is_bool($v)) {
            return $v ? 't' : 'f';
        }

        return str_replace(['\\', "\t", "\n", "\r"], ['\\\\', '\t', '\n', '\r'], (string) $v);
    }
}
