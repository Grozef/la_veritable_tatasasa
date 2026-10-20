<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Conversion sRGB -> CIELAB et distance perceptuelle.
 *
 * Pourquoi pas de distance RGB : dans l'espace RGB, deux bleus visuellement proches
 * peuvent etre plus « loin » qu'un bleu et un gris. Le mapping d'un centroide de
 * couleur vers un pigment du nuancier n'a de sens qu'en espace perceptuel.
 *
 * Illuminant D65, observateur 2 degres — l'espace de reference de sRGB.
 */
final class Lab
{
    private const WHITE_X = 0.95047;

    private const WHITE_Y = 1.0;

    private const WHITE_Z = 1.08883;

    /**
     * @return array{0: float, 1: float, 2: float} [L, a, b]
     */
    public static function fromHex(string $hex): array
    {
        $clean = ltrim($hex, '#');

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $clean)) {
            throw new InvalidArgumentException("Couleur hexadecimale invalide : {$hex}");
        }

        return self::fromRgb(
            (int) hexdec(substr($clean, 0, 2)),
            (int) hexdec(substr($clean, 2, 2)),
            (int) hexdec(substr($clean, 4, 2)),
        );
    }

    /**
     * @return array{0: float, 1: float, 2: float} [L, a, b]
     */
    public static function fromRgb(int $r, int $g, int $b): array
    {
        $lr = self::linearize($r);
        $lg = self::linearize($g);
        $lb = self::linearize($b);

        $x = ($lr * 0.4124564 + $lg * 0.3575761 + $lb * 0.1804375) / self::WHITE_X;
        $y = ($lr * 0.2126729 + $lg * 0.7151522 + $lb * 0.0721750) / self::WHITE_Y;
        $z = ($lr * 0.0193339 + $lg * 0.1191920 + $lb * 0.9503041) / self::WHITE_Z;

        $fx = self::pivot($x);
        $fy = self::pivot($y);
        $fz = self::pivot($z);

        return [
            116.0 * $fy - 16.0,
            500.0 * ($fx - $fy),
            200.0 * ($fy - $fz),
        ];
    }

    /**
     * deltaE CIE76. Suffisant ici : on classe des pigments nettement distincts,
     * pas des nuances a la limite du discernable ou CIEDE2000 s'imposerait.
     *
     * @param  array{0: float, 1: float, 2: float}  $a
     * @param  array{0: float, 1: float, 2: float}  $b
     */
    public static function distance(array $a, array $b): float
    {
        return sqrt(
            ($a[0] - $b[0]) ** 2
            + ($a[1] - $b[1]) ** 2
            + ($a[2] - $b[2]) ** 2
        );
    }

    /**
     * Annule la correction gamma de sRGB : les coefficients XYZ ci-dessus
     * s'appliquent a une intensite lineaire, pas a la valeur encodee.
     */
    private static function linearize(int $channel): float
    {
        $c = $channel / 255.0;

        return $c <= 0.04045
            ? $c / 12.92
            : (($c + 0.055) / 1.055) ** 2.4;
    }

    private static function pivot(float $t): float
    {
        return $t > 0.008856
            ? $t ** (1 / 3)
            : (7.787 * $t) + (16 / 116);
    }
}
