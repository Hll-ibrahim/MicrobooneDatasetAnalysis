<?php

namespace App\Http\Controllers;

use App\Models\H5File;
use App\Services\Charts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Grafikler sayfası: veritabanındaki tüm (ya da seçili dosyadaki) veriden
 * fizik dağılımları.
 */
class AnalysisController extends Controller
{
    public function __invoke(Request $request): View
    {
        $fileId = $request->filled('file') ? (int) $request->input('file') : null;
        $scope = fn (string $table) => DB::table($table)->when($fileId, fn ($q) => $q->where('h5_file_id', $fileId));

        $nEvents = $scope('events')->count();
        $c = [];

        if ($nEvents > 0) {
            // ---------------------------------------------------- olay düzeyi (gerçek bilgi)
            $c['energy'] = Charts::histogram($scope('events'), 'nu_energy', 30, 0.0);
            $c['lep_vs_nu'] = Charts::scatter($scope('events'), 'nu_energy', 'lep_energy', 2500);
            $c['vtx_zx'] = Charts::scatter($scope('events'), 'nu_vtx_z', 'nu_vtx_x', 2500);
            $c['vtx_zy'] = Charts::scatter($scope('events'), 'nu_vtx_z', 'nu_vtx_y', 2500);
            $c['hits'] = Charts::histogram($scope('events'), 'hit_count', 30, 0.0, Charts::percentile($scope('events'), 'hit_count', 0.99));
            $c['nufrac'] = Charts::histogram(
                $scope('events')->where('hit_count', '>', 0),
                'CAST(nu_hit_count AS DOUBLE PRECISION) / hit_count', 25, 0.0, 1.0
            );

            // ---------------------------------------------------- Pandora
            $c['nu_score'] = Charts::histogram($scope('pandora_primaries')->where('nu_score', '>', -900), 'nu_score', 25, 0.0, 1.0);
            $fm = $scope('pandora_primaries')->where('flashmatch_score', '>=', 0);
            $c['flashmatch'] = Charts::histogram($fm, 'flashmatch_score', 30, 0.0, Charts::percentile($fm, 'flashmatch_score', 0.98));
            $c['pfp_pdg'] = Charts::countBy($scope('pandora_pfps'), 'pfp_pdg', 5, fn ($v) => match ((int) $v) {
                11 => 'Sağanak benzeri (11)', 13 => 'İz benzeri (13)', default => (string) $v,
            });
            $c['sentinel'] = [
                'labels' => ['Geçerli nu_score', 'Tanımsız (-999)'],
                'data' => [
                    $scope('pandora_primaries')->where('nu_score', '>', -900)->count(),
                    $scope('pandora_primaries')->where('nu_score', '<=', -900)->count(),
                ],
            ];

            // ---------------------------------------------------- parçacıklar
            $c['particle_pdg'] = Charts::pdgCounts($scope('particles'), 'g4_pdg', 12);
            $c['category'] = Charts::countBy($scope('particles'), 'category', 12, null, orderByValue: true);
            $c['process'] = Charts::countBy($scope('particles'), 'start_process', 10);

            // ---------------------------------------------------- optik
            $c['flash_time'] = Charts::histogram($scope('opflashes'), 'time', 40);
            $c['flash_pe'] = Charts::histogram($scope('opflashes'), 'totalpe', 30, 0.0, Charts::percentile($scope('opflashes'), 'totalpe', 0.98));
            $pmt = $scope('opflash_sumpes')->selectRaw('pmt_channel AS ch, SUM(sumpe) AS pe')
                ->groupBy('pmt_channel')->orderBy('pmt_channel')->get();
            $c['pmt'] = [
                'labels' => $pmt->pluck('ch')->map(fn ($v) => (string) $v)->all(),
                'data' => $pmt->pluck('pe')->map(fn ($v) => round((float) $v, 1))->all(),
            ];

            // ---------------------------------------------------- hit düzeyi (örneklem)
            $sample = $scope('events')->orderBy('id')->limit((int) config('h5.hit_chart_events', 200))->pluck('id');
            foreach ([0 => 'U', 1 => 'V', 2 => 'Y'] as $p => $name) {
                $c['integral'][$name] = Charts::histogram(
                    DB::table('hits')->whereIn('event_id', $sample)->where('local_plane', $p), 'integral', 40, 0.0, 800.0
                );
                $c['hit_time'][$name] = Charts::histogram(
                    DB::table('hits')->whereIn('event_id', $sample)->where('local_plane', $p), 'local_time', 32, 0.0, 6400.0
                );
            }
            $c['sample_events'] = $sample->count();
        }

        return view('analysis.index', [
            'files' => H5File::orderBy('name')->get(['id', 'name']),
            'fileId' => $fileId,
            'nEvents' => $nEvents,
            'c' => $c,
            'detector' => config('h5.detector'),
        ]);
    }
}
