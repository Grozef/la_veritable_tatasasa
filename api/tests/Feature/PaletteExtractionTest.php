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
     * Une feuille PHOTOGRAPHIEE n'est pas blanche : sous la lampe, le papier sort gris
     * (#c4c5c7, L 80, mesure sur img_49 : L 79, chroma 1.9). Au-dessus du seuil absolu
     * de 88, il etait pris pour un pigment et tombait sur « Terre verte » a 66 %.
     */
    public function test_photographed_grey_paper_is_still_paper(): void
    {
        $this->seed(PigmentSeeder::class);

        $palette = $this->extract(['#2846a0' => 0.15], paper: '#c4c5c7');

        $this->assertSame(['outremer'], $this->slugs($palette));
        // Le papier gris ne compte pas comme surface peinte : sans la reference de la
        // feuille, l'outremer ne pesait que ses 15 % de la page.
        $this->assertEqualsWithDelta(1.0, array_sum($palette), 0.01);
    }

    /**
     * Le papier se reconnait par rapport a la feuille, pas dans l'absolu — mais une
     * oeuvre sans papier clair (un lavis gris NEUTRE pleine page, #a4a4a4, L 67.4) ne doit
     * pas voir son gris avale comme une reserve : sa propre reference tomberait a L 67.4 et
     * tout serait ecarte sans le plancher (L 69). Le gris de Payne lui-meme ne prouverait
     * rien ici : sa chroma (12.1) le met hors de toute reserve.
     */
    public function test_a_full_page_neutral_grey_wash_is_not_taken_for_paper(): void
    {
        $this->seed(PigmentSeeder::class);

        $this->assertSame(['gris-de-payne'], $this->slugs($this->extract(['#a4a4a4' => 1.0])));
    }

    /**
     * Un gris est un gris : il ne peut rejoindre qu'un pigment neutre. Au plus proche en
     * distance brute, un gris clair #a8a8a8 (L 68.9, celui du lavis gris du DJ d'img_43)
     * tombait sur « Terre verte » (deltaE 28.41) avant « Gris de Payne » (28.64) — c'est
     * par la que l'ombre du papier et les gris colorient tout le nuancier en vert.
     * Marge etroite assumee : Gris de Payne est a 28.64, sous MAX_DELTA (30) de 1.36 ;
     * retoucher son hex ou baisser MAX_DELTA impose de revoir ce gris.
     */
    public function test_a_neutral_grey_joins_a_neutral_pigment(): void
    {
        $this->seed(PigmentSeeder::class);

        $this->assertSame(['gris-de-payne'], $this->slugs($this->extract(['#a8a8a8' => 0.3])));
    }

    /**
     * Une teinte qu'aucun tube du nuancier n'approche (vert d'eau tres pale, deltaE > 30
     * de tout pigment) ne recoit pas le pigment le moins eloigne : ne rien dire vaut mieux
     * qu'une pastille fausse.
     */
    public function test_a_tint_far_from_every_pigment_gets_none(): void
    {
        $this->seed(PigmentSeeder::class);

        $this->assertSame([], $this->extract(['#b4e6cd' => 0.3]));
    }

    /**
     * @param  array<string, float>  $washes  hex => part de la hauteur de la feuille
     * @return array<int, float>
     */
    private function extract(array $washes, string $paper = '#fcfaf5'): array
    {
        Storage::fake('public');

        Storage::disk('public')->put('sonde.png', $this->aquarelle($washes, $paper));

        return app(PaletteExtractor::class)->extract(Storage::disk('public')->path('sonde.png'));
    }

    /**
     * Fausse aquarelle : des bandes de lavis pleines sur une reserve de papier. Generee
     * et non versionnee — un fichier binaire de reference cacherait ce qu'il contient.
     *
     * @param  array<string, float>  $washes
     */
    private function aquarelle(array $washes, string $paper): string
    {
        $image = imagecreatetruecolor(400, 300);
        imagefill($image, 0, 0, $this->allocate($image, $paper));

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
