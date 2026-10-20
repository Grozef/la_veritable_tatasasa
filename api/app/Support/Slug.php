<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class Slug
{
    /**
     * Slug derive du titre, suffixe tant qu'il est pris.
     *
     * Une artiste intitule volontiers deux aquarelles « Sans titre » : la collision
     * est le cas courant, pas le cas limite.
     *
     * @param  class-string<Model>  $model
     */
    public static function uniqueFor(string $model, string $source, string $column = 'slug'): string
    {
        $base = Str::slug($source) ?: 'sans-titre';
        $slug = $base;
        $suffix = 2;

        while ($model::query()->where($column, $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
