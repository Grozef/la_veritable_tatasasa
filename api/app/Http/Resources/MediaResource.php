<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WorkMedia;
use App\Support\Derivatives;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkMedia
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'role' => $this->role->value,
            'width' => $this->width,
            'height' => $this->height,
            'lqipHex' => $this->lqip_hex,
            'sources' => $this->sources(),
            // Ajout posterieur a `sources`, qui reste inchange : la haute definition n'entre
            // pas dans le `srcset`, sinon un ecran dense la telechargerait des le chargement.
            'hd' => $this->path_web === null ? null : Derivatives::hd($this->disk, $this->path_web, $this->width),
        ];
    }

    /**
     * `null` tant que le job de derivation n'a pas tourne : le front doit pouvoir
     * distinguer « pas encore traite » de « traite sans resultat ». Renvoyer des
     * chaines vides ferait pointer des <img> vers des URL inexistantes.
     *
     * @return array<string, string>|null
     */
    private function sources(): ?array
    {
        if ($this->path_web === null) {
            return null;
        }

        $sources = [];

        foreach (Derivatives::FORMATS as $format) {
            $sources[$format] = Derivatives::srcset($this->disk, $this->path_web, $format, $this->width);
        }

        return $sources;
    }
}
