<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pandoraHit_table  ->  pandora_hits
 *
 * Her hit'in hangi Pandora dilimine ve PFP'sine atandığı.
 * hit_table ile satır satır hizalıdır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pandora_hits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('h5_file_id')->index()->comment('Kaynak dosya (hızlı filtre için; bütünlük event_id üzerinden)');
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->integer('hit_id')->comment('hits.hit_id');
            $table->integer('pfp_id')->nullable()->comment('pandora_pfps.pfp_id (-1 = yok)');
            $table->integer('slice_id')->nullable()->comment('pandora_primaries.slice_id (-1 = yok)');

            $table->index(['event_id', 'hit_id']);
            $table->index(['event_id', 'slice_id']);

            $table->comment('pandoraHit_table: hit → Pandora dilim/PFP ataması');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pandora_hits');
    }
};
