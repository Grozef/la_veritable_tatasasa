<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Pigment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un pigment du nuancier, hors contexte d'une oeuvre.
 *
 * Les valeurs CIELAB restent cote serveur : elles servent au mapping des centroides,
 * les fronts n'affichent que la pastille.
 *
 * @mixin Pigment
 */
class PigmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'hex' => $this->hex,
            'brand' => $this->brand,
        ];
    }
}
