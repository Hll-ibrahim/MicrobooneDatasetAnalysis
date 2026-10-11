<?php

namespace App\Models;

use App\Support\DataTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Sisteme kaydedilmiş bir .h5 dosyası ve içe aktarma durumu.
 */
class H5File extends Model
{
    public const REGISTERED = 'registered';

    public const QUEUED = 'queued';

    public const EXPORTING = 'exporting';

    public const IMPORTING = 'importing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public const STATUS_LABELS = [
        self::REGISTERED => ['Kayıtlı', 'secondary', 'bi-file-earmark'],
        self::QUEUED => ['Kuyrukta', 'info', 'bi-hourglass-split'],
        self::EXPORTING => ['h5 okunuyor', 'warning', 'bi-arrow-repeat'],
        self::IMPORTING => ['Veritabanına yazılıyor', 'warning', 'bi-database-up'],
        self::COMPLETED => ['Tamamlandı', 'success', 'bi-check2-circle'],
        self::FAILED => ['Hata', 'danger', 'bi-exclamation-octagon'],
    ];

    protected $table = 'h5_files';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'import_groups' => 'array',
            'row_counts' => 'array',
            'unmapped_columns' => 'array',
            'size_bytes' => 'integer',
            'file_events' => 'integer',
            'import_offset' => 'integer',
            'import_max_events' => 'integer',
            'progress' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function isBusy(): bool
    {
        return in_array($this->status, [self::QUEUED, self::EXPORTING, self::IMPORTING], true);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUS_LABELS[$this->status][1] ?? 'secondary';
    }

    public function statusIcon(): string
    {
        return self::STATUS_LABELS[$this->status][2] ?? 'bi-question';
    }

    public function fileExists(): bool
    {
        return is_file($this->path);
    }

    public function humanSize(): string
    {
        return self::bytes($this->size_bytes);
    }

    public static function bytes(?int $b): string
    {
        if ($b === null) {
            return '—';
        }
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $v = (float) $b;
        while ($v >= 1024 && $i < count($u) - 1) {
            $v /= 1024;
            $i++;
        }

        return number_format($v, $i ? 1 : 0, ',', '.').' '.$u[$i];
    }

    /** inspect çıktısındaki bir grubun bilgisi */
    public function schemaGroup(string $name): ?array
    {
        foreach ($this->schema['groups'] ?? [] as $g) {
            if ($g['name'] === $name) {
                return $g;
            }
        }

        return null;
    }

    /** Toplam aktarılan satır */
    public function totalRows(): int
    {
        return array_sum($this->row_counts ?? []);
    }

    /**
     * Bu dosyaya ait tüm verileri siler. Büyük tablolarda zincirleme (cascade)
     * silmeden çok daha hızlıdır.
     */
    public function purgeData(): void
    {
        foreach (array_keys(DataTables::children()) as $table) {
            DB::table($table)->where('h5_file_id', $this->id)->delete();
        }
        DB::table('events')->where('h5_file_id', $this->id)->delete();
    }
}
