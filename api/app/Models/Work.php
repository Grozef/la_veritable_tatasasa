<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaperGrain;
use App\Enums\WorkStatus;
use App\Policies\WorkPolicy;
use App\Services\CorbeilleMedias;
use Database\Factories\WorkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'series_id', 'title', 'slug', 'year', 'medium', 'paper_grain',
    'width_mm', 'height_mm', 'status', 'notes', 'position', 'published_at',
])]
#[RouteKey('slug')]
#[UsePolicy(WorkPolicy::class)]
class Work extends Model
{
    /** @use HasFactory<WorkFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * Garde-fou : la suppression passe par `CorbeilleMedias`. La cascade de schema
     * emporterait les lignes `work_media` sans mettre leurs originaux a l'abri.
     */
    protected static function booted(): void
    {
        static::deleting(fn () => CorbeilleMedias::exigerPassage());
    }

    /**
     * La cle primaire reste l'auto-increment ; le ULID est une colonne a part,
     * destinee a l'app studio qui cree l'oeuvre avant d'en televerser les medias.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * Doit rester le miroir des `default()` de la migration.
     *
     * Un defaut de colonne s'applique a l'INSERT, pas a l'instance Eloquent : sans ces
     * valeurs, une oeuvre creee sans `paper_grain` revient avec l'attribut a null et
     * la ressource API casse sur `->value` (verifie : HTTP 500 le 2026-08-10).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'medium' => 'aquarelle',
        'paper_grain' => 'torchon',
        'status' => 'atelier',
        'position' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'position' => 'integer',
            'status' => WorkStatus::class,
            'paper_grain' => PaperGrain::class,
            'published_at' => 'datetime',
        ];
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(WorkMedia::class)->orderBy('position');
    }

    /**
     * Le nuancier : pigments detectes par PaletteExtractor, du plus couvrant au moins.
     */
    public function pigments(): BelongsToMany
    {
        return $this->belongsToMany(Pigment::class, 'work_pigment')
            ->withPivot(['weight', 'is_dominant'])
            ->withTimestamps()
            ->orderByPivot('weight', 'desc');
    }

    /**
     * Remplace le nuancier de l'oeuvre par le resultat d'une extraction. `sync` et non
     * `attach` : rejouer l'extraction apres une correction du nuancier doit RETIRER les
     * pigments qui ne ressortent plus, sinon les anciens s'accumulent.
     *
     * @param  array<int, float>  $palette  pigment_id => poids, du plus couvrant au moins
     */
    public function syncPalette(array $palette): void
    {
        $dominant = array_key_first($palette);

        $this->pigments()->sync(
            collect($palette)
                ->map(fn (float $weight, int $id): array => [
                    'weight' => $weight,
                    'is_dominant' => $id === $dominant,
                ])
                ->all()
        );
    }

    /** Du partage le plus recent au plus ancien : l'atelier veut savoir ou ca en est. */
    public function socialPosts(): HasMany
    {
        return $this->hasMany(SocialPost::class)->orderByDesc('posted_at');
    }

    /**
     * Visible du portfolio public. Une oeuvre `privee` ne l'est jamais, meme datee :
     * le statut prime sur la date de publication.
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('status', '!=', WorkStatus::Privee);
    }
}
