<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * event_table  ->  events
 *
 * Her satır bir dedektör okuması + simüle nötrino etkileşimidir.
 * Tüm diğer tablolar events.id üzerinden bu tabloya bağlanır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('h5_file_id')->constrained('h5_files')->cascadeOnDelete();

            // event_id = (run, subrun, event)
            $table->integer('run');
            $table->integer('subrun');
            $table->integer('event');

            $table->boolean('is_cc')->nullable()->comment('1 = yüklü akım (CC), 0 = nötr akım (NC)');
            $table->integer('nu_pdg')->nullable()->comment('Nötrino PDG kodu (12 = νe, -12 = ν̄e)');
            $table->double('nu_energy')->nullable()->comment('Nötrino enerjisi [GeV]');
            $table->double('lep_energy')->nullable()->comment('Çıkan leptonun enerjisi [GeV]');

            foreach (['x', 'y', 'z'] as $a) {
                $table->double("nu_dir_{$a}")->nullable()->comment('Nötrino yön birim vektörü');
            }
            foreach (['x', 'y', 'z'] as $a) {
                $table->double("nu_vtx_{$a}")->nullable()->comment('Etkileşim noktası [cm]');
            }
            foreach (['x', 'y', 'z'] as $a) {
                $table->double("nu_vtx_corr_{$a}")->nullable()->comment('Uzay yükü düzeltmeli etkileşim noktası [cm]');
            }
            foreach (['u', 'v', 'y'] as $p) {
                $table->double("nu_vtx_wire_pos_{$p}")->nullable()->comment('Köşenin tel koordinatı (düzlem başına)');
            }
            $table->double('nu_vtx_wire_time')->nullable()->comment('Köşenin zaman koordinatı');

            // İçe aktarma sonunda hesaplanan özet sayılar
            $table->unsignedInteger('hit_count')->default(0);
            $table->unsignedInteger('hit_count_u')->default(0);
            $table->unsignedInteger('hit_count_v')->default(0);
            $table->unsignedInteger('hit_count_y')->default(0);
            $table->unsignedInteger('nu_hit_count')->default(0)->comment('edep kaydı olan (nötrino kökenli) hit sayısı');
            $table->unsignedInteger('particle_count')->default(0);
            $table->unsignedInteger('slice_count')->default(0);
            $table->unsignedInteger('flash_count')->default(0);

            // Araştırmacı alanları (web arayüzünden düzenlenir)
            $table->string('label', 100)->nullable()->comment('Serbest etiket (ör. "ilginç", "kalite sorunu")');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['h5_file_id', 'run', 'subrun', 'event']);
            $table->index(['run', 'subrun', 'event']);
            $table->index('nu_energy');

            $table->comment('event_table: olay başına gerçek (simülasyon) bilgisi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
