<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * opflashsumpe_table  ->  opflash_sumpes
 *
 * Her flaş için PMT başına toplam fotoelektron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opflash_sumpes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('h5_file_id')->index()->comment('Kaynak dosya (hızlı filtre için; bütünlük event_id üzerinden)');
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->integer('sumpe_id')->comment('Olay içi kayıt indeksi');
            $table->integer('flash_id')->nullable()->comment('opflashes.flash_id');
            $table->integer('pmt_channel')->nullable()->comment('PMT kanalı (0–31)');
            $table->double('sumpe')->nullable()->comment('Toplam PE');

            $table->index(['event_id', 'flash_id']);

            $table->comment('opflashsumpe_table: flaş × PMT fotoelektron toplamları');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opflash_sumpes');
    }
};
