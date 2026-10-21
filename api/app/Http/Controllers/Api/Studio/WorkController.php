<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Studio;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkRequest;
use App\Http\Requests\UpdateWorkRequest;
use App\Http\Resources\WorkResource;
use App\Models\Work;
use App\Services\CorbeilleMedias;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class WorkController extends Controller
{
    // Pas de `authorizeResource()` dans le constructeur : il passe par
    // `$this->middleware()`, methode retiree des controllers depuis Laravel 11
    // (verifie — « Call to undefined method ... ::middleware() » en HTTP 500).
    // L'autorisation est donc posee explicitement dans chaque action.

    /**
     * Vue atelier : TOUTES les oeuvres, brouillons et privees comprises, contrairement
     * a la route publique.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Work::class);

        $works = Work::query()
            ->with(['series', 'media', 'pigments', 'socialPosts'])
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->get();

        return WorkResource::collection($works);
    }

    public function store(StoreWorkRequest $request): JsonResponse
    {
        $this->authorize('create', Work::class);

        $data = $request->validated();
        $data['slug'] ??= Slug::uniqueFor(Work::class, $data['title']);

        $work = Work::create($data);

        return (new WorkResource($work->load(['series', 'media', 'pigments'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateWorkRequest $request, Work $work): WorkResource
    {
        $this->authorize('update', $work);

        $work->update($request->validated());

        return new WorkResource($work->load(['series', 'media', 'pigments']));
    }

    /**
     * Les medias et les liaisons de pigments partent en cascade (contraintes de
     * schema). L'original de la numerisation n'est jamais detruit par une suppression
     * d'oeuvre : `CorbeilleMedias` le deplace dans la corbeille privee (`corbeille/` du
     * disque `originaux`, recuperable a la main), tout ou rien ; seules les derivees
     * publiques sont effacees.
     */
    public function destroy(Work $work, CorbeilleMedias $corbeille): Response
    {
        $this->authorize('delete', $work);

        $corbeille->supprimerOeuvre($work);

        return response()->noContent();
    }
}
