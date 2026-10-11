<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sisteme kaydedilen her .h5 dosyası ve içe aktarma durumu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h5_files', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Görünen ad (düzenlenebilir)');
            $table->string('path', 1024)->comment('Sunucudaki mutlak dosya yolu');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('description')->nullable()->comment('Kullanıcı notu');

            // HDF5 şeması (grup -> sütunlar, satır sayıları) — h5_tool.py inspect çıktısı
            $table->jsonb('schema')->nullable();
            $table->unsignedInteger('file_events')->nullable()->comment('Dosyadaki toplam olay sayısı');

            // İçe aktarma seçenekleri
            $table->unsignedInteger('import_offset')->default(0);
            $table->unsignedInteger('import_max_events')->nullable()->comment('NULL = tüm olaylar');
            $table->jsonb('import_groups')->nullable()->comment('Aktarılan HDF5 grupları');

            // Durum: registered | queued | exporting | importing | completed | failed
            $table->string('status', 20)->default('registered')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('status_message')->nullable();
            $table->jsonb('row_counts')->nullable()->comment('Tablo başına aktarılan satır');
            $table->jsonb('unmapped_columns')->nullable()->comment('Veritabanında karşılığı olmayan h5 sütunları');
            $table->text('error')->nullable();

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->comment('İçe aktarılan MicroBooNE HDF5 dosyaları');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('h5_files');
    }
};
