<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Series;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Series
 */
class SeriesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'year' => $this->year,
            'notes' => $this->notes,
            'works' => WorkResource::collection($this->whenLoaded('works')),
        ];
    }
}
