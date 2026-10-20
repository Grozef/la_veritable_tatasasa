<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Pigment;
use App\Services\PaletteExtractor;
use Database\Seeders\PigmentSeeder;
use GdImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaletteExtractionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Le piege du projet : le papier occupe la majorite d'une aquarelle. Non ecarte, il
     * dicterait le nuancier — et #fcfaf5 tombe sur « Terre verte » a deltaE 51, une
     * pastille qui n'a jamais touche la feuille.
     */
    public function test_paper_reserve_yields_no_pigment(): void
    {
        $this->seed(PigmentSeeder::class);

        $palette = $this->extract(['#2846a0' => 0.15]);

        $this->assertSame(['outremer'], $this->slugs($palette));
        $this->assertEqualsWithDelta(1.0, array_sum($palette), 0.01, 'Le poids se rapporte a la surface PEINTE');
    }

    /**
     * Deux lavis : les poids doivent refleter leurs surfaces respectives, papier exclu.
     */
    public function test_weights_split_the_painted_surface(): void
    {
        $this->seed(PigmentSeeder::class);

        $palette = $this->extract(['#2846a0' => 0.30, '#c88a2e' => 0.15]);

        $this->assertSame(['outremer', 'ocre-jaune'], $this->slugs($palette));

        // Deux tiers attendus, a la tolerance du reechantillonnage : les bandes tombent
        // sur 21 et 10 lignes d'une image reduite a 33, et la ligne de jointure melange
        // les deux lavis. Mesure : 0.636.
        $this->assertEqualsWithDelta(0.667, reset($palette), 0.05);
    }

    /**
     * Une feuille vierge n'a pas de nuancier. Renvoyer le pigment le plus proche du
     * papier serait pire que ne rien renvoyer.
     */
    public function test_a_blank_sheet_has_no_palette(): void
    {
        $this->seed(PigmentSeeder::class);

        $this->assertSame([], $this->extract([]));
    }

    /**
     * @param  array<string, float>  $washes  hex => part de la hauteur de la feuille
     * @return array<int, float>
     */
    private function extract(array $washes): array
    {
        Storage::fake('public');

        Storage::disk('public')->put('sonde.png', $this->aquarelle($washes));

        return app(PaletteExtractor::class)->extract(Storage::disk('public')->path('sonde.png'));
    }

    /**
     * Fausse aquarelle : des bandes de lavis pleines sur une reserve de papier. Generee
     * et non versionnee — un fichier binaire de reference cacherait ce qu'il contient.
     *
     * @param  array<string, float>  $washes
     */
    private function aquarelle(array $washes): string
    {
        $image = imagecreatetruecolor(400, 300);
        imagefill($image, 0, 0, $this->allocate($image, '#fcfaf5'));

        $top = 0;

        foreach ($washes as $hex => $part) {
            $height = (int) round(300 * $part);
            imagefilledrectangle($image, 0, $top, 399, $top + $height - 1, $this->allocate($image, $hex));
            $top += $height;
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function allocate(GdImage $image, string $hex): int
    {
        [$r, $g, $b] = sscanf($hex, '#%2x%2x%2x');

        return (int) imagecolorallocate($image, (int) $r, (int) $g, (int) $b);
    }

    /**
     * @param  array<int, float>  $palette
     * @return array<int, string>
     */
    private function slugs(array $palette): array
    {
        return Pigment::findMany(array_keys($palette))
            ->sortBy(fn ($pigment) => array_search($pigment->id, array_keys($palette), true))
            ->pluck('slug')
            ->values()
            ->all();
    }
}
