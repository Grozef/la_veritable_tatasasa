<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `disk` reste le disque des derivees publiques ; l'original a desormais le sien.
     * Les lignes existantes gardent leur original la ou il est (a cote des derivees) :
     * c'est `works:originaux-prives` qui deplace les fichiers et met cette colonne a jour.
     */
    public function up(): void
    {
        Schema::table('work_media', function (Blueprint $table) {
            $table->string('disk_original')->default('originaux')->after('disk')
                ->comment('Disque prive de l original et de la brute, jamais servi');
        });

        DB::table('work_media')->update(['disk_original' => DB::raw('disk')]);
    }

    /**
     * Refuse tant qu'un original n'est pas sur le disque de ses derivees : le code
     * d'avant le chercherait sur `disk` et derivation, palette et apercu echoueraient
     * sans bruit. `works:originaux-prives --retour` les ramene (fichiers puis base).
     */
    public function down(): void
    {
        $horsPlace = DB::table('work_media')->whereColumn('disk_original', '!=', 'disk')->count();

        if ($horsPlace > 0) {
            throw new RuntimeException(
                "{$horsPlace} media(s) ont leur original hors du disque de leurs derivees. "
                .'Lancer `php artisan works:originaux-prives --retour` avant ce rollback.'
            );
        }

        Schema::table('work_media', function (Blueprint $table) {
            $table->dropColumn('disk_original');
        });
    }
};
