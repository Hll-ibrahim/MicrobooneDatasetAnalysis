<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * particle_table  ->  particles
 *
 * Geant4 ile simüle edilmiş parçacıklar (gerçek bilgi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('particles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('h5_file_id')->index()->comment('Kaynak dosya (hızlı filtre için; bütünlük event_id üzerinden)');
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->integer('g4_id')->comment('Geant4 parçacık kimliği (olay içi)');
            $table->integer('parent_id')->nullable()->comment('Ana parçacığın g4_id değeri; -1/0 = birincil');
            $table->integer('g4_pdg')->nullable()->comment('PDG kodu');
            $table->integer('category')->nullable()->comment('Anlamsal parçacık etiketi');
            $table->integer('instance')->nullable()->comment('Kategori içi örnek sayacı');
            $table->double('momentum')->nullable()->comment('Momentum [GeV/c]');
            $table->string('start_process', 64)->nullable()->comment('Parçacığı üreten süreç');
            $table->string('end_process', 64)->nullable()->comment('Parçacığı sonlandıran süreç');

            foreach (['start_position', 'start_position_corr', 'end_position', 'end_position_corr'] as $p) {
                foreach (['x', 'y', 'z'] as $a) {
                    $table->double("{$p}_{$a}")->nullable();
                }
            }
            foreach (['start_wire_pos', 'end_wire_pos'] as $p) {
                foreach (['u', 'v', 'y'] as $pl) {
                    $table->double("{$p}_{$pl}")->nullable();
                }
            }
            $table->double('start_wire_time')->nullable()->comment('[tick]');
            $table->double('end_wire_time')->nullable()->comment('[tick]');

            $table->index(['event_id', 'g4_id']);
            $table->index('g4_pdg');

            $table->comment('particle_table: Geant4 parçacıkları');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('particles');
    }
};
