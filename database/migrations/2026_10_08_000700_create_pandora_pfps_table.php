<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pandoraPfp_table  ->  pandora_pfps
 *
 * Nötrino dilimindeki rekonstrükte parçacıklar (PFParticle): iz veya sağanak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pandora_pfps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('h5_file_id')->index()->comment('Kaynak dosya (hızlı filtre için; bütünlük event_id üzerinden)');
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->integer('pfp_id')->comment('PFP kimliği (olay içi)');
            $table->integer('pfp_pdg')->nullable()->comment('11 = sağanak benzeri, 13 = iz benzeri');
            $table->double('trkshr_score')->nullable()->comment('İz/sağanak skoru (>0.5 iz)');
            foreach (['x', 'y', 'z'] as $a) {
                $table->double("vtx_{$a}")->nullable()->comment('Başlangıç noktası [cm]');
            }
            foreach (['u', 'v', 'y'] as $p) {
                $table->double("vtx_wire_pos_{$p}")->nullable();
            }
            $table->double('vtx_wire_time')->nullable();

            $table->index(['event_id', 'pfp_id']);

            $table->comment('pandoraPfp_table: Pandora iz/sağanak nesneleri');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pandora_pfps');
    }
};
