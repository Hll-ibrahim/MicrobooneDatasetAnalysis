<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\H5File;
use App\Models\Particle;
use App\Services\Charts;
use App\Support\DataTables;
use App\Support\FormRules;
use App\Support\Pdg;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventController extends Controller
{
    private const SORTABLE = [
        'id', 'run', 'subrun', 'event', 'nu_pdg', 'nu_energy', 'lep_energy',
        'nu_vtx_x', 'nu_vtx_y', 'nu_vtx_z', 'hit_count', 'nu_hit_count', 'particle_count', 'slice_count', 'label',
    ];

    public function index(Request $request): View
    {
        $sort = in_array($request->input('sort'), self::SORTABLE, true) ? $request->input('sort') : 'id';
        $dir = $request->input('dir') === 'desc' ? 'desc' : 'asc';

        $base = Event::query()->filter($request);
        $summary = (clone $base)->toBase()
            ->selectRaw('COUNT(*) AS n, AVG(nu_energy) AS e, AVG(hit_count) AS h, COUNT(DISTINCT run) AS runs')
            ->first();

        $events = (clone $base)->with('h5File:id,name')
            ->orderBy($sort, $dir)->orderBy('id')
            ->paginate(min(200, max(10, (int) $request->input('per_page', 25))))
            ->withQueryString();

        $charts = [];
        if ($summary->n > 0) {
            $charts['energy'] = Charts::histogram((clone $base), 'nu_energy', 24, 0.0);
        }

        return view('events.index', [
            'events' => $events,
            'files' => H5File::orderBy('name')->get(['id', 'name']),
            'summary' => $summary,
            'charts' => $charts,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function show(Event $event): View
    {
        $event->load('h5File');

        // ------------------------------------------------ olay görüntüsü verisi
        $hits = DB::table('hits')->where('event_id', $event->id)
            ->get(['hit_id', 'local_plane', 'local_wire', 'local_time', 'integral']);
        $nuHits = DB::table('edeps')->where('event_id', $event->id)->distinct()->pluck('hit_id')->flip();
        $best = $event->bestSlice();
        $sliceHits = $best
            ? DB::table('pandora_hits')->where('event_id', $event->id)->where('slice_id', $best->slice_id)->pluck('hit_id')->flip()
            : collect();

        // [tel, zaman, integral, ν-hit mi, en iyi dilimde mi]
        $display = [0 => [], 1 => [], 2 => []];
        foreach ($hits as $h) {
            $p = (int) $h->local_plane;
            if (! isset($display[$p])) {
                continue;
            }
            $display[$p][] = [
                (int) $h->local_wire,
                round((float) $h->local_time, 1),
                round((float) $h->integral, 1),
                isset($nuHits[$h->hit_id]) ? 1 : 0,
                isset($sliceHits[$h->hit_id]) ? 1 : 0,
            ];
        }

        // ------------------------------------------------ tablolar
        $particles = $event->particles()
            ->orderByRaw("CASE WHEN start_process = 'primary' THEN 0 ELSE 1 END")
            ->orderByDesc('momentum')
            ->limit(60)->get();
        $slices = $event->pandoraPrimaries()->orderByDesc('nu_score')->limit(40)->get();
        $pfps = $event->pandoraPfps()->orderBy('pfp_id')->get();
        $flashes = $event->opflashes()->orderByDesc('totalpe')->get();

        // ------------------------------------------------ grafikler
        $charts = [
            'integral' => [],
            'pdg' => Charts::pdgCounts(Particle::where('event_id', $event->id), 'g4_pdg', 10),
        ];
        // PMT başına toplam PE (tüm flaşlar)
        $pmt = DB::table('opflash_sumpes')->where('event_id', $event->id)
            ->selectRaw('pmt_channel AS c, SUM(sumpe) AS pe')->groupBy('pmt_channel')->orderBy('pmt_channel')->get();
        $charts['pmt'] = [
            'labels' => $pmt->pluck('c')->map(fn ($c) => (string) $c)->all(),
            'data' => $pmt->pluck('pe')->map(fn ($v) => round((float) $v, 1))->all(),
        ];

        foreach ([0 => 'U', 1 => 'V', 2 => 'Y'] as $p => $name) {
            $charts['integral'][$name] = Charts::histogram(
                DB::table('hits')->where('event_id', $event->id)->where('local_plane', $p), 'integral', 30, 0.0, 800.0
            );
        }

        $neighbors = [
            'prev' => Event::where('h5_file_id', $event->h5_file_id)->where('id', '<', $event->id)->orderByDesc('id')->value('id'),
            'next' => Event::where('h5_file_id', $event->h5_file_id)->where('id', '>', $event->id)->orderBy('id')->value('id'),
        ];

        return view('events.show', compact('event', 'display', 'best', 'particles', 'slices', 'pfps', 'flashes', 'charts', 'neighbors'));
    }

    public function create(): View
    {
        return view('events.form', [
            'event' => new Event(['is_cc' => true, 'nu_pdg' => 12]),
            'columns' => DataTables::get('events')['columns'],
            'files' => H5File::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request), [], $this->attributes());
        $event = Event::create($data);

        return redirect()->route('events.show', $event)->with('success', 'Yeni olay eklendi.');
    }

    public function edit(Event $event): View
    {
        return view('events.form', [
            'event' => $event,
            'columns' => DataTables::get('events')['columns'],
            'files' => H5File::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate($this->rules($request, $event), [], $this->attributes());
        $event->update($data);

        return redirect()->route('events.show', $event)->with('success', 'Olay güncellendi.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $key = $event->key();
        // Bağlı satırlar yabancı anahtar (ON DELETE CASCADE) ile silinir
        $event->delete();

        return redirect()->route('events.index')->with('success', "Olay {$key} ve ona bağlı tüm satırlar silindi.");
    }

    private function attributes(): array
    {
        return FormRules::attributes('events') + ['h5_file_id' => 'Dosya'];
    }

    private function rules(Request $request, ?Event $event = null): array
    {
        $rules = FormRules::for('events');
        $rules['h5_file_id'] = ['required', 'integer', 'exists:h5_files,id'];
        $rules['event'][] = Rule::unique('events', 'event')
            ->where('h5_file_id', (int) $request->input('h5_file_id'))
            ->where('run', (int) $request->input('run'))
            ->where('subrun', (int) $request->input('subrun'))
            ->ignore($event?->id);

        return $rules;
    }
}
