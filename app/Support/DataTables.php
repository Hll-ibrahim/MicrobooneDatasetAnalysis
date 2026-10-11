<?php

namespace App\Support;

use App\Models\Edep;
use App\Models\Event;
use App\Models\Hit;
use App\Models\OpFlash;
use App\Models\OpFlashSumPe;
use App\Models\OpHit;
use App\Models\PandoraHit;
use App\Models\PandoraPfp;
use App\Models\PandoraPrimary;
use App\Models\Particle;

/**
 * HDF5 grubu  <->  PostgreSQL tablosu  <->  Eloquent modeli eşlemesinin
 * tek kaynağı. Sütun etiketleri, türleri ve birimleri arayüzde (tablo
 * tarayıcı, düzenleme formları, CSV) buradan okunur.
 *
 * Sütun tanımı: 'kolon' => [etiket, tür, birim?, azami uzunluk?]
 * Türler: int | float | bool | string | text
 */
final class DataTables
{
    /** @var array<string, array>|null */
    private static ?array $cache = null;

    /** @return array<string, array> db_tablo => tanım */
    public static function all(): array
    {
        return self::$cache ??= self::build();
    }

    public static function get(string $table): array
    {
        $all = self::all();
        abort_unless(isset($all[$table]), 404, 'Bilinmeyen tablo');

        return $all[$table] + ['table' => $table];
    }

    /** Olay dışındaki (olaya bağlı) tablolar */
    public static function children(): array
    {
        return array_filter(self::all(), fn ($d, $t) => $t !== 'events', ARRAY_FILTER_USE_BOTH);
    }

    /** h5 grup adı => db tablo adı */
    public static function groupMap(): array
    {
        $map = [];
        foreach (self::all() as $table => $def) {
            $map[$def['group']] = $table;
        }

        return $map;
    }

    public static function tableForGroup(string $group): ?string
    {
        return self::groupMap()[$group] ?? null;
    }

    private static function vec(string $prefix, string $label, ?string $unit = null, string $type = 'float'): array
    {
        $axes = str_contains($prefix, 'wire') ? ['u' => 'U', 'v' => 'V', 'y' => 'Y'] : ['x' => 'x', 'y' => 'y', 'z' => 'z'];
        $out = [];
        foreach ($axes as $suffix => $name) {
            $out["{$prefix}_{$suffix}"] = ["{$label} ({$name})", $type, $unit];
        }

        return $out;
    }

    private static function build(): array
    {
        return [
            'events' => [
                'group' => 'event_table',
                'model' => Event::class,
                'label' => 'Olaylar',
                'singular' => 'Olay',
                'icon' => 'bi-bullseye',
                'required' => ['run', 'subrun', 'event'],
                'large' => false,
                'description' => 'Her satır bir dedektör okuması ve içindeki simüle nötrino etkileşimidir (gerçek bilgi).',
                'columns' => [
                    'run' => ['Run', 'int'],
                    'subrun' => ['Subrun', 'int'],
                    'event' => ['Event', 'int'],
                    'is_cc' => ['Yüklü akım (CC)', 'bool'],
                    'nu_pdg' => ['Nötrino PDG', 'int'],
                    'nu_energy' => ['Eν', 'float', 'GeV'],
                    'lep_energy' => ['E lepton', 'float', 'GeV'],
                ]
                + self::vec('nu_dir', 'Yön')
                + self::vec('nu_vtx', 'Köşe', 'cm')
                + self::vec('nu_vtx_corr', 'Düzeltilmiş köşe', 'cm')
                + self::vec('nu_vtx_wire_pos', 'Köşe tel konumu')
                + [
                    'nu_vtx_wire_time' => ['Köşe zamanı', 'float', 'tick'],
                    'label' => ['Etiket', 'string', null, 100],
                    'notes' => ['Notlar', 'text'],
                ],
                'list' => ['run', 'subrun', 'event', 'nu_pdg', 'nu_energy', 'lep_energy', 'nu_vtx_x', 'nu_vtx_y', 'nu_vtx_z', 'label'],
            ],

            'hits' => [
                'group' => 'hit_table',
                'model' => Hit::class,
                'label' => 'Hit\'ler',
                'singular' => 'Hit',
                'icon' => 'bi-activity',
                'required' => ['hit_id'],
                'large' => true,
                'description' => 'Tel düzlemlerinde rekonstrükte edilen Gauss darbeleri (U, V indüksiyon; Y toplama).',
                'columns' => [
                    'hit_id' => ['Hit no', 'int'],
                    'local_plane' => ['Düzlem', 'int'],
                    'local_wire' => ['Tel', 'int'],
                    'local_time' => ['Zaman', 'float', 'tick'],
                    'integral' => ['İntegral', 'float', 'ADC'],
                    'rms' => ['RMS', 'float', 'tick'],
                    'tpc' => ['TPC', 'int'],
                ],
                'list' => ['hit_id', 'local_plane', 'local_wire', 'local_time', 'integral', 'rms'],
            ],

            'edeps' => [
                'group' => 'edep_table',
                'model' => Edep::class,
                'label' => 'Enerji birikimleri',
                'singular' => 'Enerji birikimi',
                'icon' => 'bi-lightning-charge',
                'required' => [],
                'large' => true,
                'description' => 'Simüle parçacıkların hit\'lere bıraktığı gerçek enerji (hit ↔ parçacık bağı).',
                'columns' => [
                    'hit_id' => ['Hit no', 'int'],
                    'g4_id' => ['G4 parçacık', 'int'],
                    'energy' => ['Enerji', 'float', 'MeV'],
                    'energy_fraction' => ['Enerji payı', 'float'],
                ],
                'list' => ['hit_id', 'g4_id', 'energy', 'energy_fraction'],
            ],

            'particles' => [
                'group' => 'particle_table',
                'model' => Particle::class,
                'label' => 'Parçacıklar',
                'singular' => 'Parçacık',
                'icon' => 'bi-diagram-3',
                'required' => ['g4_id'],
                'large' => false,
                'description' => 'Geant4 ile simüle edilmiş parçacıklar: PDG kodu, süreç, momentum ve başlangıç/bitiş noktaları.',
                'columns' => [
                    'g4_id' => ['G4 no', 'int'],
                    'parent_id' => ['Ana parçacık', 'int'],
                    'g4_pdg' => ['PDG', 'int'],
                    'category' => ['Kategori', 'int'],
                    'instance' => ['Örnek', 'int'],
                    'momentum' => ['Momentum', 'float', 'GeV/c'],
                    'start_process' => ['Başlangıç süreci', 'string'],
                    'end_process' => ['Bitiş süreci', 'string'],
                ]
                + self::vec('start_position', 'Başlangıç', 'cm')
                + self::vec('start_position_corr', 'Başlangıç (düz.)', 'cm')
                + self::vec('end_position', 'Bitiş', 'cm')
                + self::vec('end_position_corr', 'Bitiş (düz.)', 'cm')
                + self::vec('start_wire_pos', 'Başlangıç teli')
                + self::vec('end_wire_pos', 'Bitiş teli')
                + [
                    'start_wire_time' => ['Başlangıç zamanı', 'float', 'tick'],
                    'end_wire_time' => ['Bitiş zamanı', 'float', 'tick'],
                ],
                'list' => ['g4_id', 'parent_id', 'g4_pdg', 'category', 'momentum', 'start_process', 'end_process'],
            ],

            'pandora_primaries' => [
                'group' => 'pandoraPrimary_table',
                'model' => PandoraPrimary::class,
                'label' => 'Pandora dilimleri',
                'singular' => 'Pandora dilimi',
                'icon' => 'bi-layers',
                'required' => ['slice_id'],
                'large' => false,
                'description' => 'Pandora\'nın olayı ayırdığı dilimler; nu_score ≈ 1 nötrino, -999 = tanımsız.',
                'columns' => [
                    'slice_id' => ['Dilim', 'int'],
                    'slice_pdg' => ['Dilim PDG', 'int'],
                    'nu_score' => ['ν skoru', 'float'],
                    'flashmatch_score' => ['Flaş eşleşme skoru', 'float'],
                ]
                + self::vec('vtx', 'Köşe', 'cm')
                + self::vec('vtx_wire_pos', 'Köşe teli')
                + ['vtx_wire_time' => ['Köşe zamanı', 'float', 'tick']],
                'list' => ['slice_id', 'slice_pdg', 'nu_score', 'flashmatch_score', 'vtx_x', 'vtx_y', 'vtx_z'],
            ],

            'pandora_pfps' => [
                'group' => 'pandoraPfp_table',
                'model' => PandoraPfp::class,
                'label' => 'Pandora PFP\'leri',
                'singular' => 'Pandora PFP',
                'icon' => 'bi-bezier2',
                'required' => ['pfp_id'],
                'large' => false,
                'description' => 'Nötrino dilimindeki rekonstrükte parçacıklar: 13 = iz benzeri, 11 = sağanak benzeri.',
                'columns' => [
                    'pfp_id' => ['PFP', 'int'],
                    'pfp_pdg' => ['PFP PDG', 'int'],
                    'trkshr_score' => ['İz/sağanak skoru', 'float'],
                ]
                + self::vec('vtx', 'Başlangıç', 'cm')
                + self::vec('vtx_wire_pos', 'Başlangıç teli')
                + ['vtx_wire_time' => ['Başlangıç zamanı', 'float', 'tick']],
                'list' => ['pfp_id', 'pfp_pdg', 'trkshr_score', 'vtx_x', 'vtx_y', 'vtx_z'],
            ],

            'pandora_hits' => [
                'group' => 'pandoraHit_table',
                'model' => PandoraHit::class,
                'label' => 'Pandora hit atamaları',
                'singular' => 'Pandora hit ataması',
                'icon' => 'bi-link-45deg',
                'required' => ['hit_id'],
                'large' => true,
                'description' => 'Her hit\'in atandığı Pandora dilimi ve PFP\'si (-1 = atanmamış).',
                'columns' => [
                    'hit_id' => ['Hit no', 'int'],
                    'slice_id' => ['Dilim', 'int'],
                    'pfp_id' => ['PFP', 'int'],
                ],
                'list' => ['hit_id', 'slice_id', 'pfp_id'],
            ],

            'opflashes' => [
                'group' => 'opflash_table',
                'model' => OpFlash::class,
                'label' => 'Optik flaşlar',
                'singular' => 'Optik flaş',
                'icon' => 'bi-brightness-high',
                'required' => ['flash_id'],
                'large' => false,
                'description' => '32 PMT\'nin birlikte gördüğü ışık flaşları: zaman, toplam PE ve konum.',
                'columns' => [
                    'flash_id' => ['Flaş', 'int'],
                    'time' => ['Zaman', 'float', 'µs'],
                    'time_width' => ['Zaman genişliği', 'float', 'µs'],
                    'totalpe' => ['Toplam PE', 'float'],
                ]
                + self::vec('wire_pos', 'Tel konumu')
                + [
                    'y_center' => ['y merkezi', 'float', 'cm'],
                    'y_width' => ['y genişliği', 'float', 'cm'],
                    'z_center' => ['z merkezi', 'float', 'cm'],
                    'z_width' => ['z genişliği', 'float', 'cm'],
                ],
                'list' => ['flash_id', 'time', 'totalpe', 'y_center', 'z_center', 'z_width'],
            ],

            'opflash_sumpes' => [
                'group' => 'opflashsumpe_table',
                'model' => OpFlashSumPe::class,
                'label' => 'PMT PE toplamları',
                'singular' => 'PMT PE toplamı',
                'icon' => 'bi-grid-3x3-gap',
                'required' => ['sumpe_id'],
                'large' => false,
                'description' => 'Her flaş için PMT başına toplam fotoelektron.',
                'columns' => [
                    'sumpe_id' => ['Kayıt', 'int'],
                    'flash_id' => ['Flaş', 'int'],
                    'pmt_channel' => ['PMT', 'int'],
                    'sumpe' => ['PE', 'float'],
                ],
                'list' => ['sumpe_id', 'flash_id', 'pmt_channel', 'sumpe'],
            ],

            'ophits' => [
                'group' => 'ophit_table',
                'model' => OpHit::class,
                'label' => 'Optik hit\'ler',
                'singular' => 'Optik hit',
                'icon' => 'bi-soundwave',
                'required' => ['hit_id'],
                'large' => true,
                'description' => 'PMT dalga formlarındaki tekil darbeler.',
                'columns' => [
                    'hit_id' => ['Hit no', 'int'],
                    'hit_channel' => ['PMT', 'int'],
                    'amplitude' => ['Genlik', 'float', 'ADC'],
                    'area' => ['Alan', 'float'],
                    'pe' => ['PE', 'float'],
                    'peaktime' => ['Tepe zamanı', 'float'],
                    'width' => ['Genişlik', 'float'],
                    'sumpe_id' => ['PE toplamı kaydı', 'int'],
                ]
                + self::vec('wire_pos', 'PMT tel konumu'),
                'list' => ['hit_id', 'hit_channel', 'amplitude', 'area', 'pe', 'peaktime', 'width'],
            ],
        ];
    }
}
