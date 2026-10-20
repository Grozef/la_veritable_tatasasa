<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_id')->constrained()->cascadeOnDelete();
            $table->string('network', 40)->comment('instagram|facebook|whatsapp|autre');

            // Null en v1 : la feuille de partage native ne rend aucun identifiant de
            // publication. Renseigne seulement si l'auto-publication Graph API arrive.
            $table->string('external_id')->nullable();

            $table->timestamp('posted_at');
            $table->text('caption')->nullable();
            $table->timestamps();

            $table->index(['work_id', 'posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
