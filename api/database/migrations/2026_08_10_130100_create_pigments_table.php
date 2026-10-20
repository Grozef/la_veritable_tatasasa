<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pigments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nom du pigment tel que l\'artiste le nomme, ex. Outremer');
            $table->string('slug')->unique();
            $table->char('hex', 7)->comment('Couleur de la pastille du nuancier, format #rrggbb');
            $table->string('brand')->nullable()->comment('Fabricant du tube, ex. Sennelier');

            // CIELAB precalcule : le mapping centroide -> pigment se fait par deltaE,
            // qui est une distance perceptuelle. La recalculer a chaque comparaison
            // couterait une conversion sRGB->XYZ->Lab par pigment et par oeuvre.
            $table->float('lab_l');
            $table->float('lab_a');
            $table->float('lab_b');

            $table->unsignedSmallInteger('position')->default(0)->comment('Ordre dans la bande du nuancier');
            $table->timestamps();

            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pigments');
    }
};
