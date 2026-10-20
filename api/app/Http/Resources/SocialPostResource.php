<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SocialPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SocialPost
 */
class SocialPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'network' => $this->network->value,
            'postedAt' => $this->posted_at->toIso8601String(),
            'caption' => $this->caption,

            // Null tant que l'auto-publication Graph API n'existe pas. Le front doit
            // distinguer « partage a la main » de « publie par l'API ».
            'externalId' => $this->external_id,
        ];
    }
}
