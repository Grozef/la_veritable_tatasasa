<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Pigment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un pigment tel qu'il apparait DANS une oeuvre : la pastille plus la part de
 * surface calculee par PaletteExtractor. Suppose la relation `pigments` chargee.
 *
 * @mixin Pigment
 */
class WorkPigmentResource extends JsonResource
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
            'weight' => (float) $this->pivot->weight,
            'isDominant' => (bool) $this->pivot->is_dominant,
        ];
    }
}
