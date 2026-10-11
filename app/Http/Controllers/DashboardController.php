<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\H5File;
use App\Services\Charts;
use App\Support\Pdg;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $sum = DB::table('events')->selectRaw(
            'COUNT(*) AS events, COALESCE(SUM(hit_count),0) AS hits, COALESCE(SUM(hit_count_u),0) AS hu,
             COALESCE(SUM(hit_count_v),0) AS hv, COALESCE(SUM(hit_count_y),0) AS hy,
             COALESCE(SUM(nu_hit_count),0) AS nuhits, COALESCE(SUM(particle_count),0) AS particles,
             COALESCE(SUM(slice_count),0) AS slices, COALESCE(SUM(flash_count),0) AS flashes,
             AVG(nu_energy) AS e_mean, COUNT(DISTINCT run) AS runs'
        )->first();

        $stats = [
            'files' => H5File::count(),
            'completed' => H5File::where('status', H5File::COMPLETED)->count(),
            'events' => (int) $sum->events,
            'runs' => (int) $sum->runs,
            'hits' => (int) $sum->hits,
            'particles' => (int) $sum->particles,
            'slices' => (int) $sum->slices,
            'flashes' => (int) $sum->flashes,
            'e_mean' => $sum->e_mean !== null ? (float) $sum->e_mean : null,
            'nu_frac' => $sum->hits > 0 ? $sum->nuhits / $sum->hits : null,
        ];

        $charts = [];
        if ($stats['events'] > 0) {
            $charts['energy'] = Charts::histogram(Event::query(), 'nu_energy', 30, 0.0);
            $charts['planes'] = ['labels' => ['U (indüksiyon)', 'V (indüksiyon)', 'Y (toplama)'], 'data' => [(int) $sum->hu, (int) $sum->hv, (int) $sum->hy]];
            $charts['pdg'] = Charts::countBy(Event::query(), 'nu_pdg', 6, fn ($v) => Pdg::label((int) $v));
            $charts['runs'] = Charts::countBy(Event::query(), 'run', 400, null, orderByValue: true);
        }

        $files = H5File::latest()->take(6)->get();

        return view('dashboard.index', compact('stats', 'charts', 'files'));
    }
}
