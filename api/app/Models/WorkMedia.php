<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaRole;
use App\Services\CorbeilleMedias;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Eloquent pluraliserait « WorkMedia » en « work_medias ».
#[Table('work_media')]
#[Fillable([
    'work_id', 'role', 'disk', 'disk_original', 'path_original', 'path_web',
    'width', 'height', 'mime', 'lqip_hex', 'position',
])]
class WorkMedia extends Model
{
    /**
     * Disque prive des originaux et des brutes (`config/filesystems.php`). `disk` ne
     * porte plus que les derivees publiques.
     */
    public const DISQUE_ORIGINAUX = 'originaux';

    /** Prefixe, sur le disque prive, des originaux de medias supprimes. */
    public const PREFIXE_CORBEILLE = 'corbeille/';

    /**
     * Double le defaut de la migration : il ne s'applique qu'a l'INSERT.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'disk_original' => self::DISQUE_ORIGINAUX,
    ];

    /** Garde-fou : la suppression passe par `CorbeilleMedias`, qui met l'original a l'abri. */
    protected static function booted(): void
    {
        static::deleting(fn () => CorbeilleMedias::exigerPassage());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MediaRole::class,
            'width' => 'integer',
            'height' => 'integer',
            'position' => 'integer',
        ];
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    /**
     * Ce que le portfolio a le droit de voir. La brute d'appareil est une archive
     * d'atelier : sans ce filtre elle apparaitrait dans le carnet, qui retombe sur le
     * premier media quand aucun n'est marque principal.
     */
    #[Scope]
    protected function publics(Builder $query): void
    {
        $query->where('role', '!=', MediaRole::Brute);
    }
}
