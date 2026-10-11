<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * edep_table  ->  edeps
 *
 * Simüle parçacıkların hit'lere bıraktığı gerçek enerji. hit_id ile hits'e,
 * g4_id ile particles'a bağlanır. Overlay örneğinde yalnızca simüle nötrino
 * parçacıkları edep üretir; bu yüzden edep'i olan hit = nötrino hit'i.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edeps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('h5_file_id')->index()->comment('Kaynak dosya (hızlı filtre için; bütünlük event_id üzerinden)');
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->integer('hit_id')->nullable()->comment('Katkı yapılan hit (hits.hit_id)');
            $table->integer('g4_id')->nullable()->comment('Enerjiyi bırakan parçacık (particles.g4_id)');
            $table->float('energy', 24)->nullable()->comment('Bırakılan enerji [MeV]');
            $table->float('energy_fraction', 24)->nullable()->comment('Hit enerjisindeki payı (0–1)');

            $table->index(['event_id', 'hit_id']);
            $table->index(['event_id', 'g4_id']);

            $table->comment('edep_table: hit ↔ parçacık gerçek enerji bağları');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edeps');
    }
};
