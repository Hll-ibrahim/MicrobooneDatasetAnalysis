# ⚜ MicroBooNE H5 Portalı

MicroBooNE açık veri setinin (**Zenodo 7261921 — BNB νe Overlay, No Wire Info**) `.h5`
dosyalarını **PostgreSQL**'e aktaran, verileri **web arayüzünden düzenlemeyi** sağlayan ve
**tablo + grafiklerle** gösteren bir Laravel uygulaması.

- Arka uç: **Laravel 12/13** (PHP ≥ 8.2), **PostgreSQL**
- Ön yüz: **Blade** + **Bootstrap 5.3** + **Chart.js 4** — bordo / eski altın / fildişi "kraliyet" teması
- HDF5 okuma: `scripts/h5_tool.py` (**h5py**) — PHP'nin olgun bir HDF5 kütüphanesi olmadığı için
- Tüm CSS/JS/yazı tipleri `public/vendor` altında yereldir; internet bağlantısı gerekmez

```
 .h5 dosyası ──► scripts/h5_tool.py (h5py) ──► olay aralığı TSV ──► Laravel (COPY) ──► PostgreSQL
                    inspect / export                                  H5Importer         │
                                                                                         ▼
                                     Blade + Bootstrap + Chart.js  ◄──  Eloquent / SQL histogramları
```

---

## 1. Ekranlar

| Sayfa | Adres | İçerik |
|---|---|---|
| Gösterge paneli | `/` | Özet kartlar, Eν histogramı, ν türü, düzlem başına hit, run başına olay |
| H5 Dosyaları | `/dosyalar` | Veri klasöründeki .h5 dosyaları, tarayıcıdan yükleme, kayıtlı dosyalar |
| Dosya ayrıntısı | `/dosyalar/{id}` | **HDF5 şeması** (grup → sütun → PostgreSQL sütunu), içe aktarma formu (başlangıç olayı, olay sayısı, grup seçimi, tahmini satır), canlı ilerleme çubuğu, sonuç tablosu, **.sql indir** |
| Olaylar | `/olaylar` | Filtrelenebilir/sıralanabilir olay tablosu + Eν histogramı; **ekle / düzenle / sil** |
| Olay ayrıntısı | `/olaylar/{id}` | **Olay görüntüsü** (U/V/Y düzlemleri, tel × zaman; renk: gerçek ν/kozmik, Pandora en iyi dilim, yük), hit integral dağılımı, parçacık türleri, PMT başına PE, bağlı tablolar |
| Tablolar | `/tablolar/{tablo}` | hits, edeps, particles, pandora_*, op* için genel tarayıcı: dosya/olay/run/sütun koşulu filtresi, sıralama, **düzenleme formu**, CSV indir |
| Grafikler | `/grafikler` | 18 fizik grafiği (enerji, köşe dağılımları, ν hit oranı, nu_score, flashmatch, PDG, süreç, flaş zamanı/PE, PMT …), dosyaya göre filtre |

`docs/ekranlar/` klasöründe sentetik örnek veriyle alınmış ekran görüntüleri vardır.

---

## 2. Veritabanı şeması (migration'lar)

Her HDF5 grubu bir tabloya dönüşür. Ortak anahtar `event_id = (run, subrun, event)`,
veritabanında `events.id` yabancı anahtarına çevrilir. `(N, 3)` şekilli sütunlar üç sütuna
açılır: adında `wire` geçenler `_u/_v/_y` (düzlem), diğerleri `_x/_y/_z` (uzay).

| HDF5 grubu | Tablo | Bir satır | Olay başına (gerçek veri) |
|---|---|---|---|
| — | `h5_files` | kayıtlı dosya, şema (jsonb), içe aktarma durumu | — |
| `event_table` | `events` | olay + simüle νe etkileşimi (+ `label`, `notes`, özet sayılar) | 1 |
| `hit_table` | `hits` | tel düzlemindeki darbe | ≈ 16 800 |
| `edep_table` | `edeps` | hit ↔ parçacık gerçek enerji bağı | ≈ binler |
| `particle_table` | `particles` | Geant4 parçacığı | ≈ 800 |
| `pandoraPrimary_table` | `pandora_primaries` | Pandora dilimi (nu_score, flashmatch) | ≈ 23 |
| `pandoraPfp_table` | `pandora_pfps` | iz/sağanak nesnesi | birkaç |
| `pandoraHit_table` | `pandora_hits` | hit → dilim/PFP ataması | ≈ 16 800 |
| `opflash_table` | `opflashes` | optik flaş | birkaç |
| `opflashsumpe_table` | `opflash_sumpes` | flaş × PMT toplam PE | ≈ 32 × flaş |
| `ophit_table` | `ophits` | PMT darbesi | yüzlerce |

Sütun açıklamaları PostgreSQL'e `COMMENT` olarak da yazılır (DataGrip/psql `\d+ hits` ile görünür).
Olay silindiğinde bağlı tüm satırlar `ON DELETE CASCADE` ile silinir.

> **Boyut uyarısı:** gerçek veride bir olay PostgreSQL'de yaklaşık **7 MB** tutar
> (çoğu `hits` + `pandora_hits`). 200 olay ≈ 1,4 GB, 1 000 olay ≈ 7 GB, bir dosyanın
> tamamı (~6 000 olay) ≈ 40 GB. Bu yüzden içe aktarma formunda olay sayısı seçilir
> (varsayılan 200). İhtiyacınız yoksa `pandoraHit_table` ve `edep_table` gruplarını kapatın.

---

## 3. Kurulum (macOS)

### 3.1 Gereksinimler

```bash
brew install php composer postgresql@16
brew services start postgresql@16
createdb microboone
```

> Homebrew PostgreSQL'de varsayılan kullanıcı **macOS kullanıcı adınızdır** (ör. `halil`) ve
> parolası yoktur. `.env` içindeki `DB_USERNAME` değerini buna göre ayarlayın.

Python tarafı (h5py) — proje içinde bir sanal ortam önerilir:

```bash
cd microboone-h5-portal
python3 -m venv .venv
.venv/bin/pip install -r scripts/requirements.txt
```

### 3.2 Laravel

```bash
composer install
cp .env.example .env
php artisan key:generate
```

`.env` dosyasında şunları düzenleyin:

```dotenv
DB_DATABASE=microboone
DB_USERNAME=halil            # Homebrew'de macOS kullanıcı adınız
DB_PASSWORD=

H5_PYTHON=/Users/halil/.../microboone-h5-portal/.venv/bin/python   # mutlak yol
H5_DATA_DIR=/Users/halil/.../dataset_microboone                    # .h5 dosyalarınızın klasörü (boşsa storage/app/h5)
```

Sonra:

```bash
php artisan migrate      # tabloları oluşturur
php artisan h5:check     # Python/h5py, veri klasörü ve PostgreSQL bağlantısını denetler
```

### 3.3 Çalıştırma

İki terminal açın:

```bash
php artisan serve                       # 1) web sunucusu → http://localhost:8000
php artisan queue:work --timeout=0      # 2) içe aktarma işçisi (kuyruk)
```

(Node.js kuruluysa tek komut: `composer dev`)

### 3.4 Hızlı deneme (sentetik veri)

Gerçek dosyayı beklemeden arayüzü denemek için küçük bir **sentetik** NoWire örneği üretip aktarın:

```bash
php artisan db:seed
```

Bu, `storage/app/h5/ornek_sentetik_nowire.h5` dosyasını (60 olay) üretir ve içe aktarır.
Sayılar fiziksel olarak makul ama **rastgeledir — bilimsel analizde kullanmayın**.

---

## 4. Gerçek veriyle kullanım

1. Zenodo'dan indirdiğiniz `nue_NoWire_00.h5 … 04.h5` dosyalarını `H5_DATA_DIR` klasörüne koyun
   (ya da `H5_DATA_DIR` ile mevcut klasörünüzü gösterin).
2. **H5 Dosyaları** sayfasında dosyanın yanındaki **Kaydet** düğmesine basın → şema okunur.
3. Dosya sayfasında başlangıç olayı, olay sayısı ve grupları seçip **İçe aktarmayı başlat** deyin.
   İlerleme çubuğu canlı güncellenir (kuyruk işçisinin çalıştığından emin olun).
4. Bittiğinde **Olaylar**, **Tablolar** ve **Grafikler** sayfaları dolar.

GB'lık dosyaları tarayıcıdan yüklemeyin: PHP'nin `upload_max_filesize` / `post_max_size`
sınırları (Homebrew'de 2 MB / 8 MB) buna izin vermez. Klasör yöntemi bu yüzden vardır.

### Terminal komutları

```bash
php artisan h5:check
php artisan h5:inspect /yol/nue_NoWire_00.h5 --columns     # şema (veritabanına yazmaz)
php artisan h5:import  /yol/nue_NoWire_00.h5 --max-events=500
php artisan h5:import  /yol/nue_NoWire_00.h5 --offset=500 --max-events=500 --groups=event_table,hit_table,particle_table
php artisan h5:import  /yol/nue_NoWire_00.h5 --all         # tüm olaylar (çok büyük!)
```

`h5:import` kuyruk kullanmaz, terminalde ilerleme çubuğuyla çalışır.

---

## 5. Düzenleme ve dışa aktarma

- **Olaylar**: tüm fizik sütunları + araştırmacı alanları (`label`, `notes`) düzenlenebilir.
  Etiketle filtreleyebilirsiniz (ör. "ilginç", "kalite sorunu").
- **Diğer tablolar**: her satır düzenlenebilir/silinebilir/eklenebilir. Bir hit, parçacık, dilim
  veya flaş eklendiğinde/silindiğinde olayın özet sayıları (`hit_count`, `particle_count` …)
  otomatik yeniden hesaplanır.
- Değişiklikler yalnızca PostgreSQL kopyasını etkiler; orijinal `.h5` dosyası değişmez.
- **CSV**: her tablo sayfasında, mevcut filtreyle.
- **.sql**: dosya sayfasında — dosyaya ait tüm satırlar `COPY … FROM stdin` blokları olarak.
  Başka bir makinede: boş veritabanı → `php artisan migrate` → `psql -d microboone -f dosya.sql`.

---

## 6. Proje yapısı

```
app/
  Console/Commands/   H5Check, H5Inspect, H5Import (artisan h5:*)
  Http/Controllers/   Dashboard, H5File, Event (resource), Table (genel CRUD), Analysis
  Jobs/ImportH5File   kuyruk işi
  Models/             H5File, Event, Hit, Edep, Particle, PandoraPrimary, PandoraPfp, PandoraHit,
                      OpFlash, OpFlashSumPe, OpHit (EventRecord tabanı)
  Services/           H5Tool (Python sarmalayıcı), H5Importer (hat), PgCopyWriter (COPY),
                      EventStats (özet sayılar), Charts (SQL histogramları)
  Support/            DataTables (grup ↔ tablo ↔ sütun etiketleri), FormRules, Pdg
config/h5.php         Python yolu, klasörler, parti boyutları, dedektör sınırları
database/migrations/  11 tablo
resources/views/      layouts, components (page-header, stat-card, chart-card…), dashboard,
                      files, events, tables, analysis
public/css/royal.css  tema   ·   public/js/royal-charts.js  Chart.js kısayolları + olay görüntüsü
scripts/h5_tool.py    check / inspect / export (h5py)
scripts/make_sample_h5.py  sentetik NoWire örneği
```

Yeni bir HDF5 sütunu eklemek için: ilgili migration'a sütunu ekleyin, `DataTables.php`'ye
etiketini yazın, `php artisan migrate:fresh` (ya da yeni bir migration) ve dosyayı yeniden aktarın.
Veritabanında karşılığı olmayan h5 sütunları içe aktarma sonunda dosya sayfasında listelenir.

---

## 7. Sorun giderme

| Belirti | Çözüm |
|---|---|
| "Python/h5py bulunamadı" | `.env` → `H5_PYTHON` mutlak yol; `php artisan cache:clear` |
| İçe aktarma "Kuyrukta" kalıyor | ikinci terminalde `php artisan queue:work --timeout=0` |
| `SQLSTATE[08006]` bağlantı hatası | `brew services start postgresql@16`, `.env` → `DB_USERNAME` |
| Yükleme hatası / 413 | Dosyayı `H5_DATA_DIR` klasörüne kopyalayıp "Kaydet" kullanın |
| İçe aktarma yarıda kesildi | Dosya sayfasında "Yeniden içe aktar" — eski satırlar önce silinir |

---

## 8. Doğrulama notu

İçe aktarma hattı (Python `h5_tool.py` → TSV → `H5Importer`/`PgCopyWriter`/`EventStats` →
PostgreSQL 16) ve tüm migration'lar, NoWire şemasına birebir uyan sentetik dosyalarla
uçtan uca test edildi: tam/kısmi aktarım, `seq_cnt` olmayan dosya (tam tarama yolu),
yeniden aktarma, hata durumları, zincirleme silme. Grafik ve liste sorguları PostgreSQL'de,
Blade görünümleri gerçek model sınıflarıyla render edilerek denendi.

Veri kaynağı: MicroBooNE Collaboration, *BNB Electron Neutrino Overlay Sample (No Wire Info)*,
Zenodo, doi:10.5281/zenodo.7261921 (CC BY 4.0). Sütun tanımları:
[uboone/OpenSamples — file-content-hdf5.md](https://github.com/uboone/OpenSamples/blob/main/file-content-hdf5.md).
