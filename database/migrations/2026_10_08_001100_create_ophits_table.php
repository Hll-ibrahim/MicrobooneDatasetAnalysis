<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ophit_table  ->  ophits
 *
 * PMT dalga formlarındaki tekil darbeler.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ophits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('h5_file_id')->index()->comment('Kaynak dosya (hızlı filtre için; bütünlük event_id üzerinden)');
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->integer('hit_id')->comment('Olay içi optik hit indeksi');
            $table->integer('hit_channel')->nullable()->comment('PMT kanalı');
            $table->float('amplitude', 24)->nullable()->comment('[ADC]');
            $table->float('area', 24)->nullable();
            $table->float('pe', 24)->nullable()->comment('Fotoelektron (kalibrasyon öncesi)');
            $table->float('peaktime', 24)->nullable();
            $table->float('width', 24)->nullable();
            $table->integer('sumpe_id')->nullable()->comment('opflash_sumpes.sumpe_id (-1 = yok)');
            foreach (['u', 'v', 'y'] as $p) {
                $table->float("wire_pos_{$p}", 24)->nullable()->comment('PMT konumu (tel koordinatı)');
            }

            $table->index(['event_id', 'hit_id']);

            $table->comment('ophit_table: PMT darbeleri');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ophits');
    }
};
