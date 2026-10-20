<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_pigment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pigment_id')->constrained()->cascadeOnDelete();
            $table->float('weight')->comment('Part de la surface PEINTE, reserve blanche exclue. 0 a 1');
            $table->boolean('is_dominant')->default(false);
            $table->timestamps();

            $table->unique(['work_id', 'pigment_id']);

            // Le filtre du nuancier part du pigment : « montre-moi les outremer ».
            $table->index(['pigment_id', 'is_dominant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_pigment');
    }
};
