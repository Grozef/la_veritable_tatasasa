<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('principal')->comment('principal|detail|verso|in_situ');
            $table->string('disk')->default('public');
            $table->string('path_original')->comment('Fichier tel que televerse, jamais reencode');
            $table->string('path_web')->nullable()->comment('Prefixe des derivees ; largeur et format se suffixent (…-800.avif)');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('mime', 100);

            // dominantColor() de Laravel : moyenne de l'image, donc delavee sur une
            // aquarelle. Sert de fond pendant le chargement, ce n'est PAS un pigment.
            $table->char('lqip_hex', 7)->nullable();

            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['work_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_media');
    }
};
