<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;

/**
 * Deplacement d'un fichier entre deux disques, partage par `works:originaux-prives` et
 * la corbeille des medias : copie, verification, puis suppression de la source.
 */
final class Deplacement
{
    /**
     * Renvoie la raison de l'echec, ou null. La source n'est supprimee que si l'ecriture
     * a reussi et que les deux checksums sont lisibles et egaux : `false === false` ne
     * prouve rien.
     */
    public static function entreDisques(FilesystemAdapter $source, string $de, FilesystemAdapter $cible, string $vers): ?string
    {
        $flux = $source->readStream($de);

        if (! is_resource($flux)) {
            return 'lecture de la source impossible, rien supprime.';
        }

        try {
            $ecrit = $cible->writeStream($vers, $flux);
        } finally {
            if (is_resource($flux)) {
                fclose($flux);
            }
        }

        // Une copie fautive (partielle ou differente) est retiree de la CIBLE ; la source,
        // seule copie sure, n'est jamais touchee.
        if ($ecrit !== true) {
            $cible->delete($vers);

            return 'ecriture de la copie impossible, copie retiree, source intacte.';
        }

        $attendu = $source->checksum($de);

        if (! is_string($attendu) || $attendu === '' || $attendu !== $cible->checksum($vers)) {
            $cible->delete($vers);

            return 'copie differente de la source (ou checksum illisible), copie retiree, source intacte.';
        }

        if (! $source->delete($de)) {
            return 'copie verifiee mais suppression de la source impossible.';
        }

        return null;
    }
}
