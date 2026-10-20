<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePigmentRequest extends FormRequest
{
    /**
     * Les valeurs CIELAB ne sont pas acceptees en entree : elles sont derivees du hex
     * cote serveur. Un Lab incoherent avec la pastille fausserait tout le mapping
     * sans qu'aucune erreur ne se manifeste.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:pigments,slug'],
            'hex' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
