<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * hit_table  ->  hits
 *
 * Bir "hit", tek bir teldeki Gauss biçimli sinyal darbesidir (rekonstrüksiyon).
 * En büyük tablodur: olay başına ~16 000 satır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('h5_file_id')->index()->comment('Kaynak dosya (hızlı filtre için; bütünlük event_id üzerinden)');
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->integer('hit_id')->comment('Olay içi hit indeksi (0..n-1)');
            $table->float('integral', 24)->nullable()->comment('Gauss genliği/integrali [ADC×tick], biriken yükle orantılı');
            $table->smallInteger('local_plane')->nullable()->comment('0 = U, 1 = V (indüksiyon), 2 = Y (toplama)');
            $table->float('local_time', 24)->nullable()->comment('Tepe zamanı [tick, 0.5 µs]');
            $table->integer('local_wire')->nullable()->comment('Düzlem içi tel numarası');
            $table->float('rms', 24)->nullable()->comment('Darbe genişliği [tick]');
            $table->smallInteger('tpc')->nullable()->comment('TPC kimliği (MicroBooNE: hep 0)');

            $table->index(['event_id', 'hit_id']);
            $table->index(['event_id', 'local_plane']);

            $table->comment('hit_table: tel düzlemlerindeki rekonstrükte darbeler');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hits');
    }
};
