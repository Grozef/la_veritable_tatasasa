<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaRole: string
{
    case Principal = 'principal';
    case Detail = 'detail';
    case Verso = 'verso';
    case InSitu = 'in_situ';

    /**
     * La photo telle que sortie de l'appareil, avant redressement et balance des blancs.
     * Archive d'atelier : elle permet de refaire la correction sans rephotographier
     * l'aquarelle, et ne s'affiche nulle part.
     */
    case Brute = 'brute';

    /** Une archive n'a rien a faire sur le portfolio, ni dans le nuancier. */
    public function isPublic(): bool
    {
        return $this !== self::Brute;
    }
}
