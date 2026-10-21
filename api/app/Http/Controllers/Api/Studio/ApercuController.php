<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Studio;

use App\Enums\MediaRole;
use App\Http\Controllers\Controller;
use App\Models\Work;
use App\Support\Derivatives;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApercuController extends Controller
{
    /**
     * Sert l'image principale d'une oeuvre a l'app atelier.
     *
     * Pourquoi une route plutot que les URL de `sources` : les fichiers de `storage` sont
     * servis en dehors du framework et ne portent AUCUN en-tete CORS (verifie : la
     * reponse d'une derivee n'a pas d'`Access-Control-Allow-Origin`, celle d'une route
     * `api/*` en a un). Une image d'une autre origine SOUILLE le canvas qui la dessine :
     * le visuel de partage se composerait sans erreur visible, puis `toBlob` leverait au
     * moment d'exporter. Le probleme n'est pas propre au developpement — sur un appareil,
     * l'app tourne sur `capacitor://` et l'API sur un autre hote.
     */
    public function show(Work $work): StreamedResponse
    {
        $this->authorize('view', $work);

        $media = $work->media()->where('role', MediaRole::Principal)->first()
            ?? throw new NotFoundHttpException('Cette oeuvre n a pas d image principale');

        $disque = Storage::disk($media->disk);

        // La plus grande derivee que l'original a pu nourrir, sinon l'original lui-meme :
        // le visuel de partage sort en 1080 px, une derivee de 400 px y serait molle.
        if ($media->path_web !== null) {
            foreach (array_reverse(Derivatives::WIDTHS) as $largeur) {
                $candidat = "{$media->path_web}-{$largeur}.webp";

                if ($largeur <= $media->width && $disque->exists($candidat)) {
                    return $disque->response($candidat);
                }
            }
        }

        // L'original vit sur un disque prive : seule cette route authentifiee le lit,
        // pour l'artiste elle-meme, le temps que le job ait produit les derivees.
        return Storage::disk($media->disk_original)->response($media->path_original);
    }
}
