<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PigmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'hex', 'brand', 'lab_l', 'lab_a', 'lab_b', 'position'])]
#[RouteKey('slug')]
class Pigment extends Model
{
    /** @use HasFactory<PigmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lab_l' => 'float',
            'lab_a' => 'float',
            'lab_b' => 'float',
            'position' => 'integer',
        ];
    }

    public function works(): BelongsToMany
    {
        return $this->belongsToMany(Work::class, 'work_pigment')
            ->withPivot(['weight', 'is_dominant'])
            ->withTimestamps();
    }
}
