<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Studio;

use App\Enums\SocialNetwork;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSocialPostRequest;
use App\Http\Resources\SocialPostResource;
use App\Models\Work;
use Illuminate\Http\JsonResponse;

class SocialPostController extends Controller
{
    /**
     * Trace un partage.
     *
     * Rien n'est publie ici : la v1 passe par la feuille de partage native du telephone,
     * qui ne demande aucune validation Meta et ne rend aucun identifiant. Cette route ne
     * fait que garder la memoire de ce qui est sorti de l'atelier, pour que l'artiste ne
     * repartage pas deux fois la meme aquarelle sans le savoir.
     */
    public function store(StoreSocialPostRequest $request, Work $work): JsonResponse
    {
        $this->authorize('update', $work);

        $post = $work->socialPosts()->create([
            'network' => $request->enum('network', SocialNetwork::class),
            'posted_at' => $request->date('posted_at') ?? now(),
            'caption' => $request->string('caption')->trim()->value() ?: null,
        ]);

        return (new SocialPostResource($post))->response()->setStatusCode(201);
    }
}
