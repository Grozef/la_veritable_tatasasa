<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pigment;
use App\Support\Lab;
use Intervention\Image\Colors\Rgb\Channels\Blue;
use Intervention\Image\Colors\Rgb\Channels\Green;
use Intervention\Image\Colors\Rgb\Channels\Red;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Nuancier d'une oeuvre : quels pigments la couvrent, et dans quelle proportion.
 *
 * Ce que `dominantColor()` de Laravel ne peut pas faire (mesure du 2026-08-10 : sur un
 * lavis #2846a0 couvrant 14% d'un papier #fcfaf5 elle rend #dee0e9, un gris delave) :
 * ecarter la reserve blanche, puis distinguer PLUSIEURS teintes au lieu d'en moyenner une.
 *
 * L'acces pixel passe par Intervention et non par la facade Laravel, qui ne l'expose pas.
 */
final class PaletteExtractor
{
    /**
     * ~96 px de large, soit une dizaine de milliers de pixels : bien assez pour la
     * couleur, et le k-means reste sous la seconde en PHP.
     */
    private const SAMPLE_WIDTH = 96;

    /**
     * Seuils de rejet du papier, en CIELAB. Un pixel n'est ecarte que s'il est A LA FOIS
     * clair et peu colore : un jaune de cadmium est tres clair mais tres chrome, un lavis
     * gris tres pale est peu chrome mais sombre. Une seule des deux conditions mangerait
     * de vrais pigments.
     */
    private const PAPER_LIGHTNESS = 88.0;

    private const PAPER_CHROMA = 10.0;

    private const CLUSTERS = 5;

    private const MAX_ITERATIONS = 20;

    /**
     * Sous 5% de la surface peinte, la teinte est une transition ou une salissure de
     * numerisation, pas un pigment que l'artiste a pose. La laisser passer remplirait
     * le nuancier de pastilles fausses.
     */
    private const MIN_WEIGHT = 0.05;

    /**
     * @return array<int, float> pigment_id => part de la surface PEINTE, du plus couvrant
     *                           au moins. Vide si l'image est une page blanche ou si le
     *                           nuancier n'est pas seede.
     */
    public function extract(string $absolutePath): array
    {
        $pixels = $this->paintedPixels($absolutePath);

        if ($pixels === []) {
            return [];
        }

        return $this->mapToPigments($this->cluster($pixels), count($pixels));
    }

    /**
     * Pixels de l'image reduite, en CIELAB, reserve du papier exclue.
     *
     * @return array<int, array{0: float, 1: float, 2: float}>
     */
    private function paintedPixels(string $absolutePath): array
    {
        $image = ImageManager::usingDriver(Driver::class)
            ->decodePath($absolutePath)
            ->scaleDown(width: self::SAMPLE_WIDTH);

        $pixels = [];

        for ($y = 0; $y < $image->height(); $y++) {
            for ($x = 0; $x < $image->width(); $x++) {
                $color = $image->colorAt($x, $y);

                if ($color->isClear()) {
                    continue;
                }

                $lab = Lab::fromRgb(
                    (int) $color->channel(Red::class)->value(),
                    (int) $color->channel(Green::class)->value(),
                    (int) $color->channel(Blue::class)->value(),
                );

                if ($this->isPaper($lab)) {
                    continue;
                }

                $pixels[] = $lab;
            }
        }

        return $pixels;
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $lab
     */
    private function isPaper(array $lab): bool
    {
        $chroma = sqrt($lab[1] ** 2 + $lab[2] ** 2);

        return $lab[0] > self::PAPER_LIGHTNESS && $chroma < self::PAPER_CHROMA;
    }

    /**
     * k-means en CIELAB. L'initialisation est DETERMINISTE (point le plus proche de la
     * moyenne, puis a chaque fois le pixel le plus eloigne des centroides deja retenus) :
     * une extraction rejouee sur la meme image doit rendre le meme nuancier, sinon la
     * commande `works:repalette` reclasserait les oeuvres a chaque passage.
     *
     * @param  array<int, array{0: float, 1: float, 2: float}>  $pixels
     * @return array<int, array{centroid: array{0: float, 1: float, 2: float}, mass: int}>
     */
    private function cluster(array $pixels): array
    {
        $centroids = $this->seedCentroids($pixels);
        $assignments = [];

        for ($iteration = 0; $iteration < self::MAX_ITERATIONS; $iteration++) {
            $next = [];

            foreach ($pixels as $index => $pixel) {
                $next[$index] = $this->nearest($pixel, $centroids);
            }

            if ($next === $assignments) {
                break;
            }

            $assignments = $next;
            $centroids = $this->recenter($pixels, $assignments, $centroids);
        }

        $clusters = [];

        foreach ($centroids as $key => $centroid) {
            $mass = count(array_keys($assignments, $key, true));

            if ($mass > 0) {
                $clusters[] = ['centroid' => $centroid, 'mass' => $mass];
            }
        }

        return $clusters;
    }

    /**
     * @param  array<int, array{0: float, 1: float, 2: float}>  $pixels
     * @return array<int, array{0: float, 1: float, 2: float}>
     */
    private function seedCentroids(array $pixels): array
    {
        // `nearest` cherche le plus proche parmi une liste de points : les pixels en
        // forment une aussi bien que les centroides.
        $centroids = [$pixels[$this->nearest($this->mean($pixels), $pixels)]];

        while (count($centroids) < self::CLUSTERS) {
            $farthest = null;
            $record = 0.0;

            foreach ($pixels as $pixel) {
                $distance = min(array_map(
                    fn (array $centroid): float => Lab::distance($pixel, $centroid),
                    $centroids,
                ));

                if ($distance > $record) {
                    $record = $distance;
                    $farthest = $pixel;
                }
            }

            // Moins de teintes distinctes que de clusters demandes : un lavis uni n'a
            // qu'un pigment, inutile d'en inventer cinq.
            if ($farthest === null) {
                break;
            }

            $centroids[] = $farthest;
        }

        return $centroids;
    }

    /**
     * @param  array<int, array{0: float, 1: float, 2: float}>  $pixels
     * @param  array<int, int>  $assignments
     * @param  array<int, array{0: float, 1: float, 2: float}>  $centroids
     * @return array<int, array{0: float, 1: float, 2: float}>
     */
    private function recenter(array $pixels, array $assignments, array $centroids): array
    {
        foreach ($centroids as $key => $centroid) {
            $members = array_intersect_key($pixels, array_flip(array_keys($assignments, $key, true)));

            // Un cluster vide garde son centroide : le retirer decalerait les cles et
            // fausserait les affectations de l'iteration suivante.
            $centroids[$key] = $members === [] ? $centroid : $this->mean($members);
        }

        return $centroids;
    }

    /**
     * @param  array<int, array{0: float, 1: float, 2: float}>  $pixels
     * @return array{0: float, 1: float, 2: float}
     */
    private function mean(array $pixels): array
    {
        $total = count($pixels);
        $sum = [0.0, 0.0, 0.0];

        foreach ($pixels as $pixel) {
            $sum[0] += $pixel[0];
            $sum[1] += $pixel[1];
            $sum[2] += $pixel[2];
        }

        return [$sum[0] / $total, $sum[1] / $total, $sum[2] / $total];
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $pixel
     * @param  array<int, array{0: float, 1: float, 2: float}>  $centroids
     */
    private function nearest(array $pixel, array $centroids): int
    {
        $nearest = 0;
        $record = INF;

        foreach ($centroids as $key => $centroid) {
            $distance = Lab::distance($pixel, $centroid);

            if ($distance < $record) {
                $record = $distance;
                $nearest = $key;
            }
        }

        return $nearest;
    }

    /**
     * Chaque centroide rejoint le pigment le plus proche du nuancier. Deux centroides
     * peuvent tomber sur le meme pigment (un lavis et sa reprise plus dense) : leurs
     * masses s'additionnent.
     *
     * @param  array<int, array{centroid: array{0: float, 1: float, 2: float}, mass: int}>  $clusters
     * @return array<int, float>
     */
    private function mapToPigments(array $clusters, int $painted): array
    {
        $pigments = Pigment::all(['id', 'lab_l', 'lab_a', 'lab_b']);
        $weights = [];

        foreach ($clusters as $cluster) {
            $closest = null;
            $record = INF;

            foreach ($pigments as $pigment) {
                $distance = Lab::distance(
                    $cluster['centroid'],
                    [$pigment->lab_l, $pigment->lab_a, $pigment->lab_b],
                );

                if ($distance < $record) {
                    $record = $distance;
                    $closest = $pigment->id;
                }
            }

            if ($closest === null) {
                continue;
            }

            $weights[$closest] = ($weights[$closest] ?? 0.0) + $cluster['mass'] / $painted;
        }

        $weights = array_filter($weights, fn (float $weight): bool => $weight >= self::MIN_WEIGHT);

        arsort($weights);

        return $weights;
    }
}
