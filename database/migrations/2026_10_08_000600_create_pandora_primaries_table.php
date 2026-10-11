<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pandoraPrimary_table  ->  pandora_primaries
 *
 * Pandora'nın olayı böldüğü her "dilim" (slice) ve nötrino skoru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pandora_primaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('h5_file_id')->index()->comment('Kaynak dosya (hızlı filtre için; bütünlük event_id üzerinden)');
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->integer('slice_id')->comment('Dilim kimliği (olay içi)');
            $table->integer('slice_pdg')->nullable()->comment('12/14 = nötrino hipotezi, 13 = kozmik');
            $table->double('nu_score')->nullable()->comment('Nötrino/kozmik ayırt edici (1’e yakın = nötrino; -999 = tanımsız)');
            $table->double('flashmatch_score')->nullable()->comment('Işık eşleştirme skoru (küçük = iyi)');
            foreach (['x', 'y', 'z'] as $a) {
                $table->double("vtx_{$a}")->nullable()->comment('Reco köşe [cm]');
            }
            foreach (['u', 'v', 'y'] as $p) {
                $table->double("vtx_wire_pos_{$p}")->nullable();
            }
            $table->double('vtx_wire_time')->nullable();

            $table->index(['event_id', 'slice_id']);

            $table->comment('pandoraPrimary_table: Pandora dilimleri');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pandora_primaries');
    }
};
