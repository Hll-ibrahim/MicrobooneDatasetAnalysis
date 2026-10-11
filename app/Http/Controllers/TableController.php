<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\H5File;
use App\Services\EventStats;
use App\Support\DataTables;
use App\Support\FormRules;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Olaya bağlı tüm HDF5 tabloları (hits, particles, pandora_*, op*) için
 * genel tablo tarayıcı ve düzenleyici.
 */
class TableController extends Controller
{
    private const OPS = ['=' => '=', 'ne' => '<>', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='];

    public function index(Request $request, string $table): View
    {
        $def = DataTables::get($table);
        $query = $this->filtered($request, $table, $def);

        $sort = array_key_exists($request->input('sort'), $def['columns']) ? $request->input('sort') : 'id';
        $dir = $request->input('dir') === 'desc' ? 'desc' : 'asc';
        $query->orderBy("t.{$sort}", $dir)->orderBy('t.id');

        // Her tabloda numaralı sayfalama. Büyük tablolarda (hits, edeps, ...) filtre
        // yokken COUNT(*) milyonlarca satırı tarar; bu durumda toplam olarak
        // PostgreSQL'in tuttuğu satır tahmini kullanılır (sayfa altında "≈" ile gösterilir).
        $perPage = min(200, max(10, (int) $request->input('per_page', 50)));
        $page = max(1, (int) $request->input('page', 1));
        $filtered = collect(['file', 'event', 'run', 'val'])->contains(fn ($k) => $request->filled($k));
        $estimate = $this->estimate($table);
        $approx = $def['large'] && ! $filtered && $estimate !== null && $estimate > 0;
        $total = $approx ? $estimate : (clone $query)->reorder()->count();

        $rows = new LengthAwarePaginator(
            (clone $query)->forPage($page, $perPage)->get(),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('tables.index', [
            'table' => $table,
            'def' => $def,
            'rows' => $rows,
            'files' => H5File::orderBy('name')->get(['id', 'name']),
            'estimate' => $estimate,
            'approx' => $approx,
            'event' => $request->filled('event') ? Event::find((int) $request->input('event')) : null,
            'sort' => $sort,
            'dir' => $dir,
            'ops' => self::OPS,
        ]);
    }

    public function create(Request $request, string $table): View
    {
        $def = DataTables::get($table);

        return view('tables.form', [
            'table' => $table,
            'def' => $def,
            'row' => null,
            'values' => [],
            'eventId' => $request->input('event'),
        ]);
    }

    public function store(Request $request, string $table): RedirectResponse
    {
        $def = DataTables::get($table);
        $data = $request->validate(
            FormRules::for($table) + ['event_id' => ['required', 'integer', 'exists:events,id']],
            [],
            FormRules::attributes($table) + ['event_id' => 'Olay']
        );
        $data['h5_file_id'] = Event::whereKey($data['event_id'])->value('h5_file_id');

        $model = $def['model']::create($data);
        EventStats::forEvents([$model->event_id]);

        return redirect()->route('tables.index', ['table' => $table, 'event' => $model->event_id])
            ->with('success', "{$def['singular']} #{$model->id} eklendi.");
    }

    public function edit(string $table, int $id): View
    {
        $def = DataTables::get($table);
        $row = $def['model']::findOrFail($id);

        return view('tables.form', [
            'table' => $table,
            'def' => $def,
            'row' => $row,
            'values' => $row->getAttributes(),
            'eventId' => $row->event_id,
        ]);
    }

    public function update(Request $request, string $table, int $id): RedirectResponse
    {
        $def = DataTables::get($table);
        $row = $def['model']::findOrFail($id);
        $data = $request->validate(FormRules::for($table), [], FormRules::attributes($table));
        $row->update($data);
        EventStats::forEvents([$row->event_id]);

        $back = $request->input('back');
        if (! is_string($back) || ! str_starts_with($back, url('/'))) {
            $back = route('tables.index', ['table' => $table, 'event' => $row->event_id]);
        }

        return redirect()->to($back)
            ->with('success', "{$def['singular']} #{$row->id} güncellendi.");
    }

    public function destroy(Request $request, string $table, int $id): RedirectResponse
    {
        $def = DataTables::get($table);
        $row = $def['model']::findOrFail($id);
        $eventId = $row->event_id;
        $row->delete();
        EventStats::forEvents([$eventId]);

        // Düzenleme sayfasından silindiyse o sayfa artık yok: tabloya dön
        if (str_contains(url()->previous(), '/duzenle')) {
            return redirect()->route('tables.index', ['table' => $table, 'event' => $eventId])
                ->with('success', "{$def['singular']} #{$id} silindi.");
        }

        return back()->with('success', "{$def['singular']} #{$id} silindi.");
    }

    /** Filtrelenmiş satırları CSV olarak indir */
    public function export(Request $request, string $table): StreamedResponse
    {
        $def = DataTables::get($table);
        $cols = array_keys($def['columns']);
        $query = $this->filtered($request, $table, $def);

        return response()->streamDownload(function () use ($query, $cols) {
            @set_time_limit(0);
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'h5_file_id', 'event_id', 'run', 'subrun', 'event', ...$cols], ',', '"', '');
            $query->chunkById(5000, function ($rows) use ($out, $cols) {
                foreach ($rows as $r) {
                    $line = [$r->id, $r->h5_file_id, $r->event_id, $r->ev_run, $r->ev_subrun, $r->ev_event];
                    foreach ($cols as $c) {
                        $line[] = is_bool($r->{$c}) ? (int) $r->{$c} : $r->{$c};
                    }
                    fputcsv($out, $line, ',', '"', '');
                }
                flush();
            }, 't.id', 'id');
            fclose($out);
        }, "{$table}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Ortak filtreler: dosya, olay, run, sütun koşulu */
    private function filtered(Request $request, string $table, array $def): Builder
    {
        $q = DB::table("{$table} as t")
            ->join('events as ev', 'ev.id', '=', 't.event_id')
            ->select('t.*', 'ev.run as ev_run', 'ev.subrun as ev_subrun', 'ev.event as ev_event');

        if ($request->filled('file')) {
            $q->where('t.h5_file_id', (int) $request->input('file'));
        }
        if ($request->filled('event')) {
            $q->where('t.event_id', (int) $request->input('event'));
        }
        if ($request->filled('run')) {
            $q->where('ev.run', (int) $request->input('run'));
        }

        $col = $request->input('col');
        $op = self::OPS[$request->input('op', '=')] ?? '=';
        $val = $request->input('val');
        if ($col !== null && $val !== null && $val !== '' && array_key_exists($col, $def['columns'])) {
            $type = $def['columns'][$col][1];
            if (in_array($type, ['int', 'float'], true) && is_numeric($val)) {
                $q->where("t.{$col}", $op, $type === 'int' ? (int) $val : (float) $val);
            } elseif ($type === 'bool') {
                $q->where("t.{$col}", filter_var($val, FILTER_VALIDATE_BOOLEAN));
            } elseif (in_array($type, ['string', 'text'], true)) {
                $op === '=' ? $q->where("t.{$col}", 'ilike', '%'.$val.'%') : $q->where("t.{$col}", $op, $val);
            }
        }

        return $q;
    }

    /** PostgreSQL istatistiklerinden yaklaşık satır sayısı (anında) */
    private function estimate(string $table): ?int
    {
        try {
            $n = DB::selectOne('SELECT reltuples::bigint AS n FROM pg_class WHERE relname = ?', [$table])?->n;

            return $n !== null && $n >= 0 ? (int) $n : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
