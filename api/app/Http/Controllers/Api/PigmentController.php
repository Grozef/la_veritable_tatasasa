<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PigmentResource;
use App\Models\Pigment;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PigmentController extends Controller
{
    /**
     * Le nuancier, dans l'ordre voulu par l'artiste : c'est la navigation du site.
     *
     * Seuls les pigments effectivement portes par une oeuvre publiee sont renvoyes —
     * une pastille qui ne filtre rien est un cul-de-sac pour le visiteur.
     */
    public function index(): AnonymousResourceCollection
    {
        $pigments = Pigment::query()
            ->whereHas('works', fn ($query) => $query->published())
            ->orderBy('position')
            ->get();

        return PigmentResource::collection($pigments);
    }
}
