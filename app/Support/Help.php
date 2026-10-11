<?php

namespace App\Support;

use App\Models\GlossaryTerm;
use Illuminate\Support\HtmlString;

/**
 * Arayüzdeki tüm açıklama metinleri: sayfa tanıtımları, tablo açıklamaları,
 * sütun sözlüğü ve grafik açıklamaları.
 *
 * Metinlerde [[slug]] ya da [[slug|görünen metin]] yazımı, terimler
 * sözlüğüne giden ve üzerine gelince kısa tanımı gösteren bir bağlantıya
 * dönüşür (bkz. self::render).
 */
final class Help
{
    // ===================================================================== yardımcılar

    /** [[slug|metin]] işaretlerini sözlük bağlantılarına çevirir. Metin güvenilir (koddan) kabul edilir. */
    public static function render(?string $text): HtmlString
    {
        if ($text === null || $text === '') {
            return new HtmlString('');
        }
        $html = preg_replace_callback('/\[\[([a-z0-9\-]+)(?:\|([^\]]+))?\]\]/u', function ($m) {
            $term = GlossaryTerm::lookup($m[1]);
            $label = $m[2] ?? self::defaultLabel($m[1], $term?->term);

            return self::termLink($m[1], $label, $term?->short);
        }, $text);

        return new HtmlString($html);
    }

    /** Etiketsiz [[slug]] için cümle içinde okunur ad: parantez kısmı atılır, özel adlar dışında küçük harfle başlar */
    private static function defaultLabel(string $slug, ?string $term): string
    {
        if ($term === null) {
            return $slug;
        }
        $label = trim(preg_replace('/\s*\(.*\)\s*$/u', '', $term));
        $keep = ['microboone', 'lartpc', 'pandora', 'geant4', 'zenodo', 'laravel', 'postgresql', 'hdf5', 'pmt', 'adc', 'pdg', 'pfp', 'bnb', 'copy', 'gev', 'nowire'];
        $first = mb_substr($label, 0, 1);
        $rest = mb_substr($label, 1);
        if (! in_array($slug, $keep, true) && mb_strtolower(mb_substr($rest, 0, 3)) === mb_substr($rest, 0, 3)) {
            $first = ['İ' => 'i', 'I' => 'ı'][$first] ?? mb_strtolower($first);
        }

        return $first.$rest;
    }

    public static function termLink(string $slug, string $label, ?string $short = null): string
    {
        $url = route('glossary.index').'#'.$slug;
        $tip = $short ? ' data-bs-toggle="tooltip" data-bs-title="'.e($short).'"' : '';

        return '<a href="'.e($url).'" class="term-link"'.$tip.'>'.e($label).'</a>';
    }

    public static function page(string $key): ?array
    {
        return self::PAGES[$key] ?? null;
    }

    public static function table(string $table): ?string
    {
        return self::TABLES[$table] ?? null;
    }

    /** Sütun açıklaması; _x/_y/_z/_u/_v/_y son ekli sütunlar için temel adın açıklamasını kullanır */
    public static function column(string $table, string $col): ?string
    {
        $map = self::COLUMNS[$table] ?? [];
        if (isset($map[$col])) {
            return $map[$col];
        }
        if (preg_match('/^(.*)_(x|y|z|u|v)$/', $col, $m) && isset($map[$m[1]])) {
            $axis = [
                'x' => 'x bileşeni (sürüklenme yönü)', 'y' => str_contains($m[1], 'wire') ? 'Y (toplama) düzlemi' : 'y bileşeni (dikey)',
                'z' => 'z bileşeni (demet yönü)', 'u' => 'U düzlemi', 'v' => 'V düzlemi',
            ][$m[2]];

            return $map[$m[1]].' — '.$axis.'.';
        }

        return null;
    }

    public static function chart(string $key): ?array
    {
        return self::CHARTS[$key] ?? null;
    }

    // ===================================================================== sayfa tanıtımları

    private const PAGES = [
        'dashboard' => [
            'title' => 'Bu portal ne işe yarar?',
            'body' => 'Bu portal, [[microboone]] deneyinin herkese açık [[hdf5|.h5 (HDF5)]] veri dosyalarını bir [[postgresql]] veritabanına aktarır ve web sayfalarında tablolar ve grafiklerle gösterir. Her kayıt bir [[notrino]] etkileşiminin dedektörde bıraktığı izlere aittir. Bu sayfa veritabanındaki tüm verinin kısa bir özetidir: üstteki kartlar toplam sayıları, grafikler ise genel dağılımları gösterir. Altı noktalı terimlerin üzerine gelince kısa tanımı görünür; tıklayınca menüdeki <strong>Sözlük</strong> sayfasında ayrıntısına gidersiniz.',
        ],
        'files' => [
            'title' => 'H5 dosyaları nasıl eklenir?',
            'body' => 'Veri seti, [[zenodo]] üzerinde yayımlanmış büyük [[hdf5]] dosyalarından oluşur (her biri ~2 GB). Dosyayı veri klasörüne koyup <strong>Kaydet</strong> dediğinizde portal yalnızca dosyanın yapısını ([[grup-dataset|gruplar ve sütunlar]]) okur; veritabanına henüz bir şey yazılmaz. Asıl aktarım dosya sayfasında, kaç olay alınacağını seçtiğinizde başlar ve arka planda bir [[kuyruk|kuyruk işçisi]] tarafından yapılır.',
        ],
        'file' => [
            'title' => 'Bu sayfada ne yapılır?',
            'body' => 'Üstteki çubuk aktarımın durumunu canlı gösterir. Soldaki formda dosyanın kaçıncı olayından başlanacağını, kaç olay alınacağını ve hangi tabloların aktarılacağını seçersiniz. Her olay ortalama ~16 800 [[hit]] içerdiği için tüm dosyayı aktarmak onlarca GB yer tutar; önce birkaç yüz olayla başlamak önerilir. En altta, dosyanın [[hdf5|HDF5]] içindeki her sütunun hangi [[postgresql|PostgreSQL]] sütununa dönüştüğü listelenir.',
        ],
        'events' => [
            'title' => 'Olay nedir?',
            'body' => 'Bir olay, dedektörün yaklaşık 3,2 milisaniyelik bir kaydıdır. Bu veri setinde her olayda, gerçek dedektör kaydının üzerine bilgisayarda simüle edilmiş bir [[elektron-notrinosu|elektron nötrinosu]] etkileşimi eklenmiştir ([[overlay]]). Tablodaki her satır bir olaydır: nötrinonun türü ([[pdg|PDG kodu]]), enerjisi, [[kose|etkileşim noktası]] ve olayda kaç [[hit]] ve parçacık bulunduğu. Satırlara etiket ve not ekleyerek ilginç olayları işaretleyebilirsiniz.',
        ],
        'event' => [
            'title' => 'Bu olayı nasıl incelerim?',
            'body' => 'Üstteki kartlar nötrinonun türünü, enerjisini ve olaydaki hit sayısını özetler. <strong>Olay görüntüsü</strong> dedektörün üç [[tel-duzlemi|tel düzleminden]] çekilmiş birer "fotoğraf" gibidir. Altındaki grafikler hit\'lerin yük dağılımını, simüle parçacık türlerini ve ışık algılayıcılarının ([[pmt|PMT]]) gördüğü ışığı gösterir. En alttaki sekmelerde bu olaya bağlı tüm kayıtlar listelenir ve düzenlenebilir.',
        ],
        'tables' => [
            'title' => 'Tablolar nasıl kullanılır?',
            'body' => 'Her HDF5 grubu veritabanında bir tabloya dönüşmüştür ve her satır bir [[event-id|olaya]] bağlıdır. Üstteki filtrelerle dosyaya, olaya, run numarasına ya da herhangi bir sütunun değerine göre süzebilir, sütun başlıklarına tıklayarak sıralayabilirsiniz. Kalem simgesi satırı düzenler, çöp kutusu siler; değişiklikler yalnızca veritabanındaki kopyayı etkiler, orijinal .h5 dosyası değişmez. Aşağıdaki "Sütun sözlüğü" her sütunun anlamını açıklar.',
        ],
        'analysis' => [
            'title' => 'Grafikler neyi gösterir?',
            'body' => 'Bu sayfadaki grafikler veritabanındaki tüm verinin (ya da seçtiğiniz dosyanın) fizik dağılımlarıdır. Çoğu bir [[histogram]] ya da [[dagilim-grafigi|dağılım grafiğidir]]. Grafikler dört bölümdedir: simülasyondan gelen [[gercek-bilgi|gerçek bilgi]], dedektörün ölçtüğü [[hit|hit\'ler]], [[pandora]] yazılımının [[rekonstruksiyon|yeniden kurduğu]] nesneler ve ışık sistemi. Her grafiğin altında ne gösterdiği ve nasıl okunacağı yazılıdır.',
        ],
        'glossary' => [
            'title' => 'Sözlük nasıl kullanılır?',
            'body' => 'Portalda geçen fizik, dedektör, veri ve yazılım terimleri burada açıklanır. Sayfalardaki altı noktalı bağlantılar buraya gelir. Terimler veritabanında (<span class="font-mono">glossary_terms</span> tablosu) saklanır; kalem simgesiyle düzenleyebilir, <strong>Yeni terim</strong> ile ekleyebilirsiniz. Bir açıklamada başka bir terime bağlantı vermek için <span class="font-mono">&#91;&#91;slug&#93;&#93;</span> yazmanız yeterlidir.',
        ],
    ];

    // ===================================================================== tablo açıklamaları

    private const TABLES = [
        'events' => 'Simülasyondan gelen [[gercek-bilgi|gerçek bilgi]]. Her olay için tek satır vardır ve diğer tüm tablolar bu tabloya bağlanır. Nötrinonun türü, enerjisi, yönü ve dedektörde çarptığı nokta burada saklanır. Bu bilgiler gerçek bir deneyde bilinemez; yalnızca simülasyonda vardır ve modellerin doğruluğunu ölçmek için "doğru cevap" olarak kullanılır. Sona eklenen hit_count, particle_count gibi sütunlar içe aktarma sırasında portal tarafından hesaplanır; label ve notes ise sizin notlarınız içindir.',
        'hits' => 'Dedektörün asıl ölçümü. Sıvı argonda serbest kalan elektronlar bir tele ulaştığında oluşan her sinyal darbesi bir [[hit]] olarak kaydedilir. Bir hit\'i hangi [[tel-duzlemi|düzlemde]] ve hangi [[tel|telde]] görüldüğü, ne zaman geldiği ([[tick]]) ve ne kadar yük taşıdığı ([[integral]]) tanımlar. Olay başına ~16 800 hit olduğu için en büyük tablodur. Hit\'lerin çoğu [[kozmik-isin|kozmik ışınlardan]] gelir; hangilerinin nötrinodan geldiği "Enerji birikimleri" tablosundan anlaşılır. Derin öğrenme modellerinin girdisi bu tablodur.',
        'edeps' => 'Simülasyonun hit\'lerle parçacıkları birbirine bağladığı köprü tablo ([[edep]]). Her satır "şu parçacık, şu hit\'e, şu kadar enerji bıraktı" der. Bir hit\'e birden fazla parçacık katkı yapmışsa birden fazla satırı olur; energy_fraction her birinin payını gösterir. [[overlay|Overlay]] örneğinde yalnızca simüle nötrino parçacıkları kayıt üretir. Bu yüzden bu tabloda kaydı olan hit nötrinodan, olmayan hit kozmik ışından ya da gürültüden gelir. Bu, hit sınıflandırma modelleri için etiket kaynağıdır.',
        'particles' => 'Nötrino etkileşiminden çıkan ve ardından oluşan tüm parçacıkların [[geant4|Geant4]] simülasyon kaydı. Her satır bir parçacıktır: türü ([[pdg|PDG kodu]]), [[momentum|momentumu]], hangi süreçle doğup hangi süreçle sona erdiği ([[surec|süreç]]) ve başlangıç/bitiş noktaları. parent_id ile parçacıkların soy ağacı kurulabilir: start_process = "primary" olanlar doğrudan nötrino etkileşiminden çıkanlardır, diğerleri onların ürettiği ikincil parçacıklardır. Olay başına yüzlerce satır olmasının nedeni, [[elektromanyetik-saganak|sağanaklardaki]] çok sayıda foton ve elektrondur.',
        'pandora_primaries' => '[[pandora]] yazılımının her olayı ayırdığı [[dilim|dilimler]]. Bir olayda ortalama ~23 dilim vardır; çoğu tek tek [[kozmik-isin|kozmik müonlardır]], genellikle biri nötrino etkileşimidir. Her dilim için iki önemli skor tutulur: [[nu-score|nu_score]] (dilimin nötrino olma olasılığı, 1\'e yakın = nötrino) ve [[flash-eslestirme|flashmatch_score]] (dilimin ışık sinyaline uyumu, küçük = iyi). Hesaplanamayan skorlar −999 ile işaretlenir ([[sentinel|sentinel değer]]). Bu tablo, "nötrinoyu kozmiklerden ayırma" probleminin rekonstrüksiyon tarafındaki cevabıdır.',
        'pandora_pfps' => 'Pandora\'nın nötrino dilimi içinde yeniden kurduğu tek tek parçacık adayları ([[pfp|PFP]]). Her biri ya ince bir [[iz]] (müon, proton, pion gibi; pfp_pdg = 13) ya da dağınık bir [[elektromanyetik-saganak|sağanak]] (elektron, foton; pfp_pdg = 11) olarak sınıflandırılır; trkshr_score bu kararın güvenidir. Bir νe olayında beklenen tablo: bir sağanak (elektron) ve birkaç iz (protonlar). Bazı olaylarda bu tablo boştur, çünkü Pandora o olayda bir nötrino dilimi kuramamıştır.',
        'pandora_hits' => 'Her hit\'in Pandora tarafından hangi [[dilim|dilime]] ve hangi [[pfp|PFP\'ye]] atandığını gösterir. hits tablosuyla satır satır hizalıdır: aynı olayda aynı hit_id aynı hit\'tir. −1 değeri hit\'in hiçbir dilime ya da parçacığa atanmadığını gösterir. Bu tablo ile "Enerji birikimleri" birlikte kullanıldığında Pandora\'nın başarısı ölçülebilir: gerçek nötrino hit\'lerinin ne kadarı doğru dilime düştü ([[tamlik-saflik|tamlık]]), seçilen dilimdeki hit\'lerin ne kadarı gerçekten nötrino ([[tamlik-saflik|saflık]]).',
        'opflashes' => 'Dedektörün arkasındaki 32 ışık algılayıcısının ([[pmt|PMT]]) birlikte gördüğü ışık parlamaları ([[optik-flas|optik flaş]]). Sıvı argonda ışık neredeyse anında oluştuğu için flaşlar olayın tam zamanını verir. Tel sinyalleri ise milisaniyeler süren [[surukleme|sürüklenme]] yüzünden geç gelir. Her flaşın zamanı, toplam ışık miktarı ([[fotoelektron|PE]]) ve ışığın geldiği bölgenin merkezi saklanır. Nötrino demetinin geldiği kısa pencerede (~3–5 µs) parlak bir flaş görülmesi, olayda nötrino etkileşimi olduğunun en güçlü işaretidir.',
        'opflash_sumpes' => 'Her optik flaş için 32 PMT\'nin her birinin ne kadar ışık ([[fotoelektron|PE]]) gördüğü. Yani flaşın "ışık haritası"dır. Işığın hangi PMT\'lerde yoğunlaştığı, kaynağın dedektörün neresinde olduğunu gösterir. Pandora\'nın [[flash-eslestirme|flaş eşleştirme]] skoru, bir dilimden beklenen ışık haritasının bu ölçülen haritayla karşılaştırılmasıyla hesaplanır.',
        'ophits' => 'Tek tek PMT dalga formlarında görülen ışık darbeleri ([[ophit|optik hit]]). Flaşlar bu darbelerin zaman içinde gruplanmasıyla oluşur. Her darbenin hangi PMT\'de görüldüğü (hit_channel), zamanı, genliği ve ışık miktarı (pe) saklanır; sumpe_id hangi flaş toplamına katkı yaptığını gösterir (−1 = hiçbir flaşa girmedi). Işık sisteminin en ayrıntılı ham bilgisidir.',
    ];

    // ===================================================================== sütun sözlüğü

    private const COLUMNS = [
        'events' => [
            'run' => 'Verinin alındığı uzun veri alma oturumunun numarası ([[run]]). Zamanla artar.',
            'subrun' => 'Run içindeki alt bölümün numarası.',
            'event' => 'Subrun içindeki olay numarası. run:subrun:event üçlüsü olayı benzersiz tanımlar ([[event-id]]).',
            'is_cc' => 'Etkileşim [[yuklu-akim|yüklü akım]] mı (Evet) yoksa [[notr-akim|nötr akım]] mı (Hayır)?',
            'nu_pdg' => 'Nötrinonun türü ([[pdg|PDG kodu]]): 12 = νe, −12 = ν̄e.',
            'nu_energy' => 'Gelen nötrinonun gerçek enerjisi, [[gev|GeV]].',
            'lep_energy' => 'Etkileşimden çıkan yüklü [[lepton|leptonun]] (elektronun) enerjisi, GeV.',
            'nu_dir' => 'Nötrinonun geliş yönünü gösteren birim vektör (uzunluğu 1). Demet z yönünde geldiği için z bileşeni ~1\'dir',
            'nu_vtx' => 'Nötrinonun çarptığı noktanın ([[kose|köşe]]) gerçek konumu, cm',
            'nu_vtx_corr' => '[[uzay-yuku|Uzay yükü]] düzeltmesi uygulanmış köşe konumu, cm. Rekonstrüksiyonla karşılaştırırken bu kullanılır',
            'nu_vtx_wire_pos' => 'Köşenin her tel düzlemindeki tel koordinatı (olay görüntüsünde köşenin yatay konumu)',
            'nu_vtx_wire_time' => 'Köşenin zaman koordinatı (olay görüntüsünde köşenin dikey konumu), [[tick]].',
            'label' => 'Sizin verdiğiniz serbest etiket (ör. "ilginç", "kalite sorunu"). Olaylar listesinde filtrelenebilir.',
            'notes' => 'Bu olay hakkındaki serbest notlarınız.',
        ],
        'hits' => [
            'hit_id' => 'Hit\'in olay içindeki sıra numarası (0\'dan başlar). Diğer tablolar hit\'e bu numarayla bağlanır.',
            'local_plane' => '[[tel-duzlemi|Tel düzlemi]]: 0 = U, 1 = V ([[induksiyon-duzlemi|indüksiyon]]), 2 = Y ([[toplama-duzlemi|toplama]]).',
            'local_wire' => 'Sinyalin görüldüğü [[tel|telin]] numarası (U, V: 0–2399; Y: 0–3455). Konum bilgisini verir.',
            'local_time' => 'Sinyalin tepe zamanı, [[tick]] (0–6400). Tele uzaklığı (x konumunu) verir.',
            'integral' => 'Sinyalin altındaki alan, ADC×tick ([[integral]]). Bırakılan enerjiyle orantılıdır.',
            'rms' => 'Sinyalin genişliği, tick ([[rms]]).',
            'tpc' => 'TPC numarası; MicroBooNE\'de tek TPC olduğu için hep 0.',
        ],
        'edeps' => [
            'hit_id' => 'Enerjinin bırakıldığı hit (hits.hit_id ile aynı olay içinde eşleşir).',
            'g4_id' => 'Enerjiyi bırakan simüle parçacık (particles.g4_id). −1 = etiketsiz.',
            'energy' => 'Bu parçacığın bu hit\'e bıraktığı enerji, MeV.',
            'energy_fraction' => 'Hit\'teki toplam gerçek enerjinin bu parçacıktan gelen payı (0–1).',
        ],
        'particles' => [
            'g4_id' => 'Parçacığın [[geant4|Geant4]] kimlik numarası (olay içinde benzersiz).',
            'parent_id' => 'Bu parçacığı üreten ana parçacığın g4_id\'si. 0 ya da −1: doğrudan nötrino etkileşiminden çıktı.',
            'g4_pdg' => 'Parçacık türü ([[pdg|PDG kodu]]): 11 elektron, 22 foton, 2212 proton, 2112 nötron, 211 pion…',
            'category' => 'Anlamsal sınıf etiketi ([[kategori]]); derin öğrenme modellerinin hedef sınıfı.',
            'instance' => 'Aynı sınıftaki parçacıkları birbirinden ayıran sıra numarası.',
            'momentum' => 'Parçacığın başlangıç [[momentum|momentumu]], GeV/c.',
            'start_process' => 'Parçacığı üreten süreç ([[surec]]); "primary" = nötrino etkileşiminin doğrudan ürünü.',
            'end_process' => 'Parçacığın yolculuğunu sonlandıran süreç (bozunma, soğurulma, durma…).',
            'start_position' => 'Parçacığın doğduğu nokta, cm',
            'start_position_corr' => 'Başlangıç noktası, uzay yükü düzeltmeli, cm',
            'end_position' => 'Parçacığın yolculuğunun bittiği nokta, cm',
            'end_position_corr' => 'Bitiş noktası, uzay yükü düzeltmeli, cm',
            'start_wire_pos' => 'Başlangıç noktasının tel koordinatı',
            'end_wire_pos' => 'Bitiş noktasının tel koordinatı',
            'start_wire_time' => 'Başlangıç noktasının zaman koordinatı, tick.',
            'end_wire_time' => 'Bitiş noktasının zaman koordinatı, tick.',
        ],
        'pandora_primaries' => [
            'slice_id' => 'Dilimin olay içindeki numarası ([[dilim]]).',
            'slice_pdg' => 'Pandora\'nın dilim için seçtiği hipotez: 12/14 = nötrino, 13 = kozmik müon.',
            'nu_score' => 'Dilimin nötrino olma skoru, 0–1 ([[nu-score]]). −999 = hesaplanamadı.',
            'flashmatch_score' => 'Dilimin optik flaşla uyumu ([[flash-eslestirme]]); küçük değer iyi eşleşme.',
            'vtx' => 'Pandora\'nın bulduğu etkileşim noktası (köşe), cm',
            'vtx_wire_pos' => 'Bulunan köşenin tel koordinatı',
            'vtx_wire_time' => 'Bulunan köşenin zaman koordinatı, tick.',
        ],
        'pandora_pfps' => [
            'pfp_id' => 'Parçacık adayının olay içindeki numarası ([[pfp|PFP]]).',
            'pfp_pdg' => 'Sınıflandırma: 13 = [[iz]] benzeri, 11 = [[elektromanyetik-saganak|sağanak]] benzeri.',
            'trkshr_score' => 'İz/sağanak skoru: 0,5\'ten büyükse iz, küçükse sağanak.',
            'vtx' => 'Parçacığın başladığı nokta, cm',
            'vtx_wire_pos' => 'Başlangıç noktasının tel koordinatı',
            'vtx_wire_time' => 'Başlangıç noktasının zaman koordinatı, tick.',
        ],
        'pandora_hits' => [
            'hit_id' => 'Hit numarası (hits tablosundaki aynı olay ve aynı hit_id).',
            'slice_id' => 'Hit\'in atandığı dilim (pandora_primaries.slice_id). −1 = hiçbir dilime atanmadı.',
            'pfp_id' => 'Hit\'in atandığı parçacık adayı (pandora_pfps.pfp_id). −1 = atanmadı.',
        ],
        'opflashes' => [
            'flash_id' => 'Flaşın olay içindeki numarası.',
            'time' => 'Flaşın zamanı, µs. Demet penceresi yaklaşık 3–5 µs arasıdır.',
            'time_width' => 'Flaşın zaman genişliği, µs.',
            'totalpe' => 'Tüm PMT\'lerin gördüğü toplam ışık ([[fotoelektron|PE]]).',
            'wire_pos' => 'Işığın ağırlık merkezinin tel koordinatı',
            'y_center' => 'Işığın dikey (y) ağırlık merkezi, cm.',
            'y_width' => 'Işık dağılımının dikey genişliği, cm.',
            'z_center' => 'Işığın demet yönündeki (z) ağırlık merkezi, cm. Nötrino köşesinin z\'si ile karşılaştırılır.',
            'z_width' => 'Işık dağılımının z yönündeki genişliği, cm.',
        ],
        'opflash_sumpes' => [
            'sumpe_id' => 'Kaydın olay içindeki numarası.',
            'flash_id' => 'Ait olduğu flaş (opflashes.flash_id).',
            'pmt_channel' => 'PMT\'nin numarası (0–31).',
            'sumpe' => 'Bu PMT\'nin bu flaşta gördüğü toplam ışık, PE.',
        ],
        'ophits' => [
            'hit_id' => 'Optik hit\'in olay içindeki numarası.',
            'hit_channel' => 'Darbenin görüldüğü PMT (0–31).',
            'amplitude' => 'Darbenin tepe yüksekliği, ADC.',
            'area' => 'Darbenin altındaki alan.',
            'pe' => 'Darbenin ışık miktarı ([[fotoelektron|PE]]), kalibrasyon öncesi.',
            'peaktime' => 'Darbenin tepe zamanı.',
            'width' => 'Darbenin genişliği.',
            'sumpe_id' => 'Katkı yaptığı PMT toplamı kaydı (opflash_sumpes.sumpe_id); −1 = hiçbir flaşa girmedi.',
            'wire_pos' => 'PMT\'nin konumunun tel koordinatı',
        ],
    ];

    // ===================================================================== grafik açıklamaları
    // what = ne gösterir, read = nasıl okunur / ne beklenir

    private const CHARTS = [
        // ---- olay düzeyi
        'energy' => [
            'what' => 'Her olaydaki nötrinonun gerçek enerjisinin [[histogram|histogramı]]. Her çubuk o enerji aralığındaki olay sayısıdır.',
            'read' => 'Nötrino demetinin enerji tayfını yansıtır: çoğu olay 0,5–2 [[gev|GeV]] arasındadır, yüksek enerjiye doğru uzun bir kuyruk vardır. Gerçek veride ortalama ~1,6 GeV beklenir.',
        ],
        'pdg' => [
            'what' => 'Olayların nötrino türüne göre dağılımı ([[pdg|PDG kodu]]).',
            'read' => 'Bu veri seti elektron nötrinosu örneğidir: halkanın büyük bölümü νe (12), küçük bir dilimi antinötrinosu ν̄e (−12) olmalıdır (gerçek veride ~%97 / %3).',
        ],
        'planes' => [
            'what' => 'Tüm hit\'lerin üç [[tel-duzlemi|tel düzlemine]] göre sayısı.',
            'read' => 'Üç düzlem aynı parçacıkları farklı açıdan gördüğü için sayılar benzer olmalıdır. Gerçek veride Y (toplama) düzlemi biraz daha az hit verir, çünkü tel sayısı ve sinyal şekli farklıdır, ayrıca [[olu-kanal|ölü kanallar]] düzlemlere eşit dağılmaz.',
        ],
        'runs' => [
            'what' => 'Her [[run]] numarasında kaç olay bulunduğu.',
            'read' => 'Run numaraları zaman sırasıdır. Olayların runlara nasıl dağıldığı, zamana bağlı analizlerde (ör. kanal arızalarının zamanla değişimi) her dönemde yeterli istatistik olup olmadığını gösterir.',
        ],
        'file_hits' => [
            'what' => 'Olay başına toplam hit sayısının histogramı.',
            'read' => 'Bir olaydaki hit\'lerin çoğu [[kozmik-isin|kozmik ışınlardan]] geldiği için bu sayı nötrino enerjisinden çok o anda dedektörden geçen kozmik sayısına bağlıdır. Gerçek veride tipik değer ~16 800\'dür; çok düşük ya da çok yüksek değerler sorunlu okumaları işaret edebilir.',
        ],
        'events_energy' => [
            'what' => 'Yukarıdaki filtrelere uyan olayların nötrino enerjisi histogramı.',
            'read' => 'Filtreleri değiştirdikçe grafik güncellenir. Örneğin yalnızca etiketlediğiniz olayların enerji dağılımının genel dağılımdan farklı olup olmadığını buradan görebilirsiniz.',
        ],
        'lep_vs_nu' => [
            'what' => 'Her nokta bir olaydır: yatay eksen nötrino enerjisi, dikey eksen çıkan elektronun enerjisi ([[dagilim-grafigi|dağılım grafiği]]).',
            'read' => 'Elektron, nötrino enerjisinden fazlasını alamaz; bu yüzden tüm noktalar kesikli y = x çizgisinin altında kalır. Çizgiye yakın noktalar enerjinin çoğunun elektrona gittiği olaylardır; aşağıdakilerde enerjinin bir kısmı çekirdekten çıkan protonlara ve pionlara gitmiştir.',
        ],
        'vtx_zx' => [
            'what' => 'Nötrino etkileşim noktalarının dedektöre yukarıdan bakış ([[kose|köşe]] konumları; z demet yönü, x sürüklenme yönü).',
            'read' => 'Kesikli çerçeve dedektörün sınırıdır. Noktalar çerçevenin içine düzgün dağılmalıdır; çünkü nötrino argonun her yerinde eşit olasılıkla etkileşir. Bir bölgede seyreklik, simülasyon ya da seçim yanlılığına işaret eder.',
        ],
        'vtx_zy' => [
            'what' => 'Etkileşim noktalarının dedektöre yandan bakış (z demet yönü, y dikey).',
            'read' => 'Üstten bakışta olduğu gibi noktaların çerçeve içinde düzgün dağılması beklenir. y yönünde dedektör ±116,5 cm\'dir.',
        ],
        'nufrac' => [
            'what' => 'Her olayda hit\'lerin ne kadarının nötrinodan geldiği (edep kaydı olan hit / tüm hit).',
            'read' => 'Değerlerin çoğu 0\'a yakındır: gerçek veride ortalama ~%9. Bu, nötrino sinyalinin kozmik "gürültü" arasında ne kadar küçük kaldığını, yani ciddi bir [[dengesizlik|sınıf dengesizliğini]] gösterir. Hit sınıflandıran bir model bu dengesizliği hesaba katmalıdır.',
        ],
        'integral' => [
            'what' => 'Hit [[integral|integrallerinin]] (bırakılan yükün) dağılımı; her renk bir [[tel-duzlemi|tel düzlemi]].',
            'read' => 'Keskin bir tepe ve sağa uzanan uzun bir kuyruk beklenir. Tepe, dedektörü kesen kozmik müonların tipik yükünü gösterir; kuyruk yoğun iyonlaştıran protonlardan ve sağanaklardan gelir. 800 ADC\'nin üstü son kutuda toplanmıştır.',
        ],
        'hit_time' => [
            'what' => 'Hit\'lerin okuma penceresi içindeki zamanlarının dağılımı ([[tick]]).',
            'read' => 'Kozmik ışınlar pencerenin her anında geldiği için dağılım kabaca düzdür. Nötrino hit\'leri demet zamanına karşılık gelen bölgede toplanır. Belirgin çukurlar ya da sıçramalar okuma sorunlarına işaret edebilir.',
        ],
        'nu_score' => [
            'what' => 'Tüm Pandora dilimlerinin [[nu-score|nu_score]] değerlerinin histogramı (−999 hariç). Dikey eksen [[logaritmik-eksen|logaritmiktir]].',
            'read' => 'Çoğu dilim kozmiktir, bu yüzden 0\'a yakın büyük bir tepe görülür. 1\'e yakın küçük tepe, nötrino olarak tanınan dilimlerdir. İki tepenin iyi ayrılması, Pandora\'nın nötrinoyu kozmiklerden iyi ayırdığını gösterir.',
        ],
        'sentinel' => [
            'what' => 'Kaç dilimin geçerli bir nu_score\'u olduğu, kaçında skorun hesaplanamadığı (−999).',
            'read' => '−999 bir ölçüm değil, "değer yok" işaretidir ([[sentinel|sentinel değer]]). Ortalama ya da histogram hesaplanırken dışarıda bırakılmalıdır; aksi hâlde sonuçlar ciddi biçimde bozulur.',
        ],
        'pfp_pdg' => [
            'what' => 'Pandora\'nın nötrino dilimlerinde kurduğu parçacık adaylarının ([[pfp|PFP]]) iz/sağanak dağılımı.',
            'read' => 'νe olaylarında en az bir sağanak (elektron) beklenir; protonlar ve pionlar iz olarak görünür. Sağanak oranının beklenenden çok düşük olması elektronun yanlış sınıflandırıldığını düşündürebilir.',
        ],
        'flashmatch' => [
            'what' => 'Dilimlerin [[flash-eslestirme|flaş eşleştirme]] skorlarının histogramı.',
            'read' => 'Küçük değerler, dilimin konumundan beklenen ışığın ölçülen flaşa iyi uyduğunu gösterir. Nötrino dilimleri genellikle düşük skorlu bölgede yer alır. Grafik %98\'lik dilime kadar çizilir, üstü son kutudadır.',
        ],
        'particle_pdg' => [
            'what' => 'Simüle edilen tüm parçacıkların türe göre sayısı ([[pdg|PDG]]), en sık 12 tür.',
            'read' => 'Fotonlar (γ) ve elektronlar açık ara en çoktur, çünkü bir elektronun oluşturduğu [[elektromanyetik-saganak|sağanak]] yüzlerce foton ve elektron içerir. Protonlar ve nötronlar nötrinonun çarptığı çekirdekten gelir.',
        ],
        'process' => [
            'what' => 'Parçacıkların hangi fiziksel süreçle üretildiği ([[surec|start_process]]).',
            'read' => '"primary" doğrudan nötrino etkileşiminden çıkanlardır ve azdır. Geri kalanlar sağanak süreçleridir: conv (fotonun elektron-pozitron çiftine dönüşmesi), compt (Compton saçılması), eBrem (frenleme ışıması), phot (fotoelektrik olay).',
        ],
        'category' => [
            'what' => 'Parçacıkların anlamsal sınıf etiketine ([[kategori]]) göre dağılımı.',
            'read' => 'Bu etiketler, hit\'leri iz / yoğun iz / sağanak gibi sınıflara ayıran derin öğrenme modellerinin hedefleridir. Sınıflar arasındaki büyük sayı farkı, eğitimde ağırlıklandırma gerektirir.',
        ],
        'flash_time' => [
            'what' => 'Tüm [[optik-flas|optik flaşların]] zamanlarının histogramı, µs.',
            'read' => 'Kozmik ışınlar her an geldiği için flaşlar geniş bir zaman aralığına yayılır. Demet penceresinde (~3–5 µs) keskin bir tepe görülür: nötrino etkileşimlerinin flaşları buradadır. Bu tepe, ışık sisteminin nötrinoları zamanlamayla seçebildiğini gösterir.',
        ],
        'flash_pe' => [
            'what' => 'Flaş başına toplam ışık miktarının ([[fotoelektron|PE]]) histogramı.',
            'read' => 'Sönük flaşlar çoğunluktadır (dedektörün kenarından geçen kozmikler). Nötrino etkileşimleri genellikle yüzlerce–binlerce PE\'lik parlak flaşlar üretir. %98\'in üstündeki değerler son kutuda toplanmıştır.',
        ],
        'pmt' => [
            'what' => 'Her [[pmt|PMT\'nin]] tüm flaşlarda gördüğü toplam ışık.',
            'read' => 'PMT\'ler dedektörün arka yüzüne yayılmıştır; çok sayıda olay toplandığında hepsinin benzer düzeyde ışık görmesi beklenir. Belirgin biçimde düşük ya da yüksek bir kanal, o PMT\'nin arızalı ya da yanlış kalibre edilmiş olabileceğini gösterir.',
        ],
        // ---- tek olay
        'event_display' => [
            'what' => 'Bu olaydaki her [[hit]] bir noktadır: yatay eksen [[tel|tel numarası]] (konum), dikey eksen sinyalin geliş zamanı ([[tick]], tele uzaklık). Üç panel, aynı olayın üç [[tel-duzlemi|tel düzleminden]] görünüşüdür ([[olay-goruntusu]]).',
            'read' => '<strong>Gerçek</strong> modunda altın noktalar simüle nötrinonun bıraktığı hit\'lerdir ([[edep]] kaydı olanlar); gri noktalar gerçek veriden gelen [[kozmik-isin|kozmik ışınlar]] ve gürültüdür. Uzun düz gri çizgiler dedektörü kesen kozmik müonlardır; nötrino genellikle bir noktadan ([[kose|köşe]]) yıldız gibi dağılan kısa izler ve dağınık bir [[elektromanyetik-saganak|sağanak]] olarak görünür. <strong>Pandora</strong> modu, yazılımın nötrino sandığı dilimi gösterir; iki modun ne kadar örtüştüğü rekonstrüksiyonun başarısıdır. <strong>Yük</strong> modunda renk, hit\'in taşıdığı yüktür (koyu = az, açık = çok).',
        ],
        'event_integral' => [
            'what' => 'Bu olaydaki hit\'lerin [[integral]] (yük) dağılımı, düzlem başına.',
            'read' => 'Tepe genellikle kozmik müonların tipik yükündedir. Nötrinonun protonları ve sağanağı sağdaki kuyruğa katkı yapar. Üç düzlemin eğrilerinin benzer olması beklenir.',
        ],
        'event_pdg' => [
            'what' => 'Bu olayda simüle edilen parçacıkların türe göre sayısı.',
            'read' => 'Bir νe yüklü akım olayında bir elektron, onun sağanağından gelen çok sayıda foton ve elektron, ayrıca çekirdekten çıkan birkaç proton ve nötron beklenir.',
        ],
        'event_pmt' => [
            'what' => 'Bu olayda 32 [[pmt|PMT\'nin]] her birinin gördüğü toplam ışık.',
            'read' => 'Işık, etkileşime yakın PMT\'lerde daha yoğundur. Yüksek çubukların konumu, etkileşimin dedektörün hangi bölgesinde olduğuna dair kaba bir ipucu verir.',
        ],
    ];
}
