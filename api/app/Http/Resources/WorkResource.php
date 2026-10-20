<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Work;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contrat consomme par le portfolio public et par l'app studio.
 * Toute modification ici doit etre repercutee dans `shared/types.ts`.
 *
 * @mixin Work
 */
class WorkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'slug' => $this->slug,
            'title' => $this->title,
            'year' => $this->year,
            'medium' => $this->medium,
            'paperGrain' => $this->paper_grain->value,
            'widthMm' => $this->width_mm,
            'heightMm' => $this->height_mm,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'series' => new SeriesResource($this->whenLoaded('series')),
            'pigments' => WorkPigmentResource::collection($this->whenLoaded('pigments')),
            'media' => MediaResource::collection($this->whenLoaded('media')),

            // Charge par l'atelier seulement. Les routes publiques ne chargent pas la
            // relation, la cle est donc ABSENTE de leur reponse : ou une aquarelle a ete
            // partagee ne regarde pas le visiteur.
            'socialPosts' => SocialPostResource::collection($this->whenLoaded('socialPosts')),

            'publishedAt' => $this->published_at?->toIso8601String(),
        ];
    }
}
