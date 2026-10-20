<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Studio;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    /**
     * Emission du jeton de l'app atelier.
     *
     * Jusqu'ici les jetons n'existaient qu'au travers de `tinker` : l'app n'avait aucun
     * moyen de se connecter. Sanctum ne fournit PAS cette route, elle se declare.
     *
     * L'erreur est la meme pour un compte inconnu et pour un mot de passe faux — sans
     * quoi la reponse dit au visiteur quelles adresses existent.
     */
    public function store(StoreTokenRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Ces identifiants ne correspondent a aucun compte.',
            ]);
        }

        // Un jeton par appareil : reconnecter le meme telephone remplace le sien et ne
        // touche pas aux autres.
        $appareil = $request->string('device_name')->toString();
        $user->tokens()->where('name', $appareil)->delete();

        return response()->json([
            'data' => ['token' => $user->createToken($appareil)->plainTextToken],
        ], 201);
    }

    /** Deconnexion : seul le jeton courant tombe, les autres appareils restent connectes. */
    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
