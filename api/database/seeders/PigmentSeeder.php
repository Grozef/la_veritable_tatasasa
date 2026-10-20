<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Pigment;
use App\Support\Lab;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Nuancier de depart : pigments aquarelle courants.
 *
 * A REMPLACER par les vrais tubes de l'artiste (marque et nom exact) avant la mise
 * en ligne — c'est la premiere question a lui poser. Les teintes ci-dessous sont des
 * approximations d'affichage, pas des mesures colorimetriques de ses pigments.
 * Les valeurs CIELAB sont calculees, jamais saisies a la main.
 */
class PigmentSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, hex: string}>
     */
    private const NUANCIER = [
        ['name' => 'Jaune de cadmium', 'hex' => '#ffb000'],
        ['name' => 'Ocre jaune', 'hex' => '#c88a2e'],
        ['name' => 'Terre de Sienne naturelle', 'hex' => '#b0764f'],
        ['name' => 'Terre de Sienne brulee', 'hex' => '#8a3324'],
        ['name' => 'Rouge de cadmium', 'hex' => '#d32011'],
        ['name' => 'Garance rose', 'hex' => '#c13b5e'],
        ['name' => 'Violet de cobalt', 'hex' => '#7b4b94'],
        ['name' => 'Outremer', 'hex' => '#2e4a9b'],
        ['name' => 'Bleu de cobalt', 'hex' => '#0047ab'],
        ['name' => 'Bleu de Prusse', 'hex' => '#1b3a5c'],
        ['name' => 'Vert de vessie', 'hex' => '#4e7a31'],
        ['name' => 'Terre verte', 'hex' => '#6b7f5c'],
        ['name' => 'Terre d\'ombre brulee', 'hex' => '#6e4b3a'],
        ['name' => 'Sepia', 'hex' => '#704214'],
        ['name' => 'Gris de Payne', 'hex' => '#536878'],
        ['name' => 'Noir d\'ivoire', 'hex' => '#231f20'],
    ];

    public function run(): void
    {
        foreach (self::NUANCIER as $position => $pigment) {
            [$l, $a, $b] = Lab::fromHex($pigment['hex']);

            Pigment::updateOrCreate(
                ['slug' => Str::slug($pigment['name'])],
                [
                    'name' => $pigment['name'],
                    'hex' => $pigment['hex'],
                    'brand' => null,
                    'lab_l' => $l,
                    'lab_a' => $a,
                    'lab_b' => $b,
                    'position' => $position,
                ],
            );
        }
    }
}
