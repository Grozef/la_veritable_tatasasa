<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SocialNetwork;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['work_id', 'network', 'external_id', 'posted_at', 'caption'])]
class SocialPost extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'network' => SocialNetwork::class,
            'posted_at' => 'datetime',
        ];
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }
}
