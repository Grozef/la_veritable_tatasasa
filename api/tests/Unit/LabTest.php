<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Lab;
use PHPUnit\Framework\TestCase;

class LabTest extends TestCase
{
    /**
     * Valeurs de reference calculees hors de ce code (convertisseurs sRGB D65 usuels).
     * Sans reference externe, un test de conversion ne fait que constater ce que le
     * code produit — il ne verifie rien.
     */
    public function test_converts_reference_colours_to_cielab(): void
    {
        $cases = [
            // [hex, L, a, b]
            ['#ffffff', 100.0, 0.0, 0.0],
            ['#000000', 0.0, 0.0, 0.0],
            ['#ff0000', 53.24, 80.09, 67.20],
            ['#00ff00', 87.73, -86.18, 83.18],
            ['#0000ff', 32.30, 79.19, -107.86],
        ];

        foreach ($cases as [$hex, $l, $a, $b]) {
            [$gotL, $gotA, $gotB] = Lab::fromHex($hex);

            $this->assertEqualsWithDelta($l, $gotL, 0.05, "L de {$hex}");
            $this->assertEqualsWithDelta($a, $gotA, 0.05, "a de {$hex}");
            $this->assertEqualsWithDelta($b, $gotB, 0.05, "b de {$hex}");
        }
    }

    public function test_accepts_hex_with_or_without_prefix(): void
    {
        $this->assertSame(Lab::fromHex('#2e4a9b'), Lab::fromHex('2e4a9b'));
    }

    public function test_rejects_malformed_hex(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Lab::fromHex('#12345');
    }

    /**
     * Le point de tout ce fichier : en RGB, ce bleu marine est plus « proche » du gris
     * que de l'outremer. En Lab, l'ordre s'inverse et colle a ce que l'oeil voit.
     * C'est la raison d'etre du mapping perceptuel de PaletteExtractor.
     */
    public function test_lab_distance_orders_colours_the_way_the_eye_does(): void
    {
        $outremer = Lab::fromHex('#2e4a9b');
        $bleuMarine = Lab::fromHex('#1b3a5c');
        $gris = Lab::fromHex('#808080');

        $this->assertLessThan(
            Lab::distance($bleuMarine, $gris),
            Lab::distance($bleuMarine, $outremer),
            'Un bleu doit etre plus proche d\'un autre bleu que d\'un gris neutre',
        );
    }

    public function test_distance_between_identical_colours_is_zero(): void
    {
        $lab = Lab::fromHex('#c13b5e');

        $this->assertSame(0.0, Lab::distance($lab, $lab));
    }
}
