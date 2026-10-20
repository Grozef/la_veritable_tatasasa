<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkResource;
use App\Models\Work;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WorkController extends Controller
{
    /**
     * Le carnet public. Renvoie toutes les oeuvres publiees, sans pagination : le
     * portfolio d'une artiste se compte en centaines, et le front filtre ensuite le
     * nuancier cote client sans aller-retour reseau.
     *
     * Le filtre `?pigments=outremer,garance` reste servi ici pour les liens profonds,
     * en INTERSECTION — une oeuvre doit porter tous les pigments demandes.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $works = Work::query()
            ->published()
            ->with(['series', 'media' => fn ($query) => $query->publics(), 'pigments'])
            ->when($request->string('pigments')->isNotEmpty(), function ($query) use ($request): void {
                foreach (explode(',', $request->string('pigments')->toString()) as $slug) {
                    $slug = trim($slug);

                    if ($slug === '') {
                        continue;
                    }

                    $query->whereHas('pigments', fn ($q) => $q->where('slug', $slug));
                }
            })
            ->when($request->filled('year'), fn ($query) => $query->where('year', $request->integer('year')))
            ->orderBy('position')
            ->orderByDesc('published_at')
            ->get();

        return WorkResource::collection($works);
    }

    /**
     * Le binding se fait sur le slug (RouteKey du modele). Une oeuvre non publiee ou
     * privee doit rendre 404 et non 403 : l'existence meme d'un brouillon ne regarde
     * pas le visiteur.
     */
    public function show(Work $work): WorkResource
    {
        if ($work->published_at === null || $work->published_at->isFuture() || ! $work->status->isPublic()) {
            throw new NotFoundHttpException;
        }

        return new WorkResource($work->load(['series', 'media' => fn ($query) => $query->publics(), 'pigments']));
    }
}
