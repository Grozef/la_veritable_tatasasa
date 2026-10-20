<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MediaRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkMediaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // 40 Mo : une photo d'aquarelle redressee en pleine resolution depuis un
            // telephone recent depasse largement le defaut de 2 Mo de PHP.
            'file' => ['required', 'image', 'max:40960'],

            'role' => ['nullable', Rule::enum(MediaRole::class)],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
