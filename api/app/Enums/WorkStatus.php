<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkStatus: string
{
    case Atelier = 'atelier';
    case Exposee = 'exposee';
    case Vendue = 'vendue';
    case Privee = 'privee';

    /**
     * Une oeuvre privee ne s'affiche jamais sur le portfolio public, meme publiee.
     */
    public function isPublic(): bool
    {
        return $this !== self::Privee;
    }
}
