<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTokenRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],

            // Le jeton porte le nom de l'appareil : c'est ce qui permet a l'artiste de
            // revoquer un telephone perdu sans deconnecter les autres.
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }
}
