<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * opflash_table  ->  opflashes
 *
 * 32 PMT'nin birlikte gördüğü ışık "flaşları".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opflashes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('h5_file_id')->index()->comment('Kaynak dosya (hızlı filtre için; bütünlük event_id üzerinden)');
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->integer('flash_id')->comment('Olay içi flaş indeksi');
            $table->double('time')->nullable()->comment('Flaş zamanı [µs]');
            $table->double('time_width')->nullable()->comment('[µs]');
            $table->double('totalpe')->nullable()->comment('Toplam fotoelektron');
            foreach (['u', 'v', 'y'] as $p) {
                $table->double("wire_pos_{$p}")->nullable()->comment('PE ağırlıklı merkez (tel koordinatı)');
            }
            $table->double('y_center')->nullable()->comment('[cm]');
            $table->double('y_width')->nullable()->comment('[cm]');
            $table->double('z_center')->nullable()->comment('[cm]');
            $table->double('z_width')->nullable()->comment('[cm]');

            $table->index(['event_id', 'flash_id']);

            $table->comment('opflash_table: optik flaşlar');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opflashes');
    }
};
