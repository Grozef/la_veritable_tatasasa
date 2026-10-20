<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('works', function (Blueprint $table) {
            $table->id();

            // L'app studio cree l'oeuvre puis televerse les medias : elle a besoin d'un
            // identifiant opaque et stable des la creation, sans exposer l'auto-increment.
            $table->ulid('ulid')->unique();

            $table->foreignId('series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('year');
            $table->string('medium')->default('aquarelle');
            $table->string('paper_grain')->default('torchon')->comment('torchon|satine|fin');
            $table->unsignedSmallInteger('width_mm')->comment('Largeur du papier en millimetres');
            $table->unsignedSmallInteger('height_mm')->comment('Hauteur du papier en millimetres');
            $table->string('status')->default('atelier')->comment('atelier|exposee|vendue|privee');
            $table->text('notes')->nullable()->comment('Note d\'atelier en markdown, affichee en marge du carnet');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamp('published_at')->nullable()->comment('Null = brouillon, invisible du portfolio public');
            $table->timestamps();

            // Le carnet public liste les oeuvres publiees dans l'ordre voulu par l'artiste.
            $table->index(['published_at', 'position']);
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('works');
    }
};
