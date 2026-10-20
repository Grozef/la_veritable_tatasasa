<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SeriesFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// « Series » est invariable au pluriel : sans cet attribut, Eloquent chercherait
// une table nommee d'apres une pluralisation qu'il ne sait pas trancher.
#[Table('series')]
#[Fillable(['title', 'slug', 'year', 'notes'])]
#[RouteKey('slug')]
class Series extends Model
{
    /** @use HasFactory<SeriesFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
        ];
    }

    public function works(): HasMany
    {
        return $this->hasMany(Work::class)->orderBy('position');
    }
}
