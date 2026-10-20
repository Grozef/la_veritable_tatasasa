<?php

use App\Http\Controllers\Api\PigmentController;
use App\Http\Controllers\Api\SeriesController;
use App\Http\Controllers\Api\Studio;
use App\Http\Controllers\Api\WorkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * Portfolio public — aucune authentification, lecture seule.
 * Le binding se fait sur le slug (attribut RouteKey des modeles).
 */
Route::get('/works', [WorkController::class, 'index']);
Route::get('/works/{work}', [WorkController::class, 'show']);
Route::get('/pigments', [PigmentController::class, 'index']);
Route::get('/series/{series}', [SeriesController::class, 'show']);

/*
 * Atelier — l'app studio, authentifiee par token Sanctum.
 * Les oeuvres y sont adressees par ULID et non par slug : le slug suit le titre,
 * que l'artiste peut changer, l'ULID ne bouge jamais.
 */
/*
 * Emission du jeton : la SEULE route d'atelier ouverte, puisqu'on ne peut pas presenter
 * un jeton pour en demander un. Limitee en cadence, c'est le seul endpoint ou une
 * adresse et un mot de passe sont soumis.
 */
Route::post('/studio/token', [Studio\TokenController::class, 'store'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->prefix('studio')->group(function (): void {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::delete('/token', [Studio\TokenController::class, 'destroy']);

    Route::get('/works', [Studio\WorkController::class, 'index']);
    Route::post('/works', [Studio\WorkController::class, 'store']);
    Route::patch('/works/{work:ulid}', [Studio\WorkController::class, 'update']);
    Route::delete('/works/{work:ulid}', [Studio\WorkController::class, 'destroy']);

    Route::post('/works/{work:ulid}/media', [Studio\WorkMediaController::class, 'store']);
    Route::delete('/works/{work:ulid}/media/{media}', [Studio\WorkMediaController::class, 'destroy']);

    // L'image principale servie par l'API et non par `storage` : c'est la seule facon
    // d'obtenir les en-tetes CORS sans lesquels le canvas du visuel de partage serait
    // souille et son export leverait.
    Route::get('/works/{work:ulid}/apercu', [Studio\ApercuController::class, 'show']);

    // Trace d'un partage. Rien n'est publie par l'API : la feuille de partage native
    // fait le travail, cette route ne fait qu'en garder la memoire.
    Route::post('/works/{work:ulid}/social-posts', [Studio\SocialPostController::class, 'store']);

    Route::get('/pigments', [Studio\PigmentController::class, 'index']);
    Route::post('/pigments', [Studio\PigmentController::class, 'store']);
});
