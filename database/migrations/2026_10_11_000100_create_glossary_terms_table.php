<?php

use App\Support\GlossaryData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Terimler sözlüğü: projede geçen fizik, dedektör, veri ve yazılım terimleri.
 * Tablo oluşturulurken varsayılan terimler (App\Support\GlossaryData) de yazılır;
 * sonrasında /sozluk sayfasından düzenlenebilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glossary_terms', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique()->comment('Bağlantılarda kullanılan kısa ad');
            $table->string('term', 150)->comment('Terim');
            $table->string('category', 40)->index()->comment('Kategori anahtarı');
            $table->string('short', 500)->comment('Tek cümlelik tanım (ipucu balonlarında)');
            $table->text('body')->nullable()->comment('Ayrıntılı açıklama');
            $table->jsonb('related')->nullable()->comment('İlgili terimlerin slug listesi');
            $table->timestamps();

            $table->comment('Terimler sözlüğü');
        });

        $now = now();
        DB::table('glossary_terms')->insert(array_map(fn ($t) => [
            'slug' => $t[0],
            'term' => $t[1],
            'category' => $t[2],
            'short' => $t[3],
            'body' => $t[4],
            'related' => json_encode($t[5] ?? [], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ], GlossaryData::all()));
    }

    public function down(): void
    {
        Schema::dropIfExists('glossary_terms');
    }
};
