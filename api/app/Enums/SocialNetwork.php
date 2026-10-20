<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ou un partage a ete envoye.
 *
 * La feuille de partage native ne dit PAS ou l'utilisateur a finalement depose le
 * visuel : c'est l'artiste qui le declare. D'ou `Autre`, qui n'est pas un fourre-tout
 * mais le cas honnete par defaut — mieux vaut un partage trace « autre » qu'un partage
 * range de force sous Instagram.
 */
enum SocialNetwork: string
{
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case Whatsapp = 'whatsapp';
    case Autre = 'autre';
}
