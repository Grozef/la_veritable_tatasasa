<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Studio;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePigmentRequest;
use App\Http\Resources\PigmentResource;
use App\Models\Pigment;
use App\Support\Lab;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PigmentController extends Controller
{
    /**
     * Nuancier complet cote atelier, y compris les pigments qu'aucune oeuvre ne porte
     * encore — l'artiste doit pouvoir preparer sa palette avant de peindre.
     */
    public function index(): AnonymousResourceCollection
    {
        return PigmentResource::collection(
            Pigment::query()->orderBy('position')->get()
        );
    }

    public function store(StorePigmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] ??= Slug::uniqueFor(Pigment::class, $data['name']);

        // Derive du hex, jamais recu du client : voir StorePigmentRequest.
        [$data['lab_l'], $data['lab_a'], $data['lab_b']] = Lab::fromHex($data['hex']);

        $pigment = Pigment::create($data);

        return (new PigmentResource($pigment))->response()->setStatusCode(201);
    }
}
