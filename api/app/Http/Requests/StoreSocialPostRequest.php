<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SocialNetwork;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialPostRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'network' => ['required', Rule::enum(SocialNetwork::class)],

            // Optionnel : un partage fait hors ligne se trace au retour du reseau, avec
            // SA date et non celle de l'envoi. Defaut = maintenant.
            'posted_at' => ['nullable', 'date'],

            'caption' => ['nullable', 'string', 'max:2200'],

            // `external_id` n'est PAS acceptable ici : en v1 la feuille de partage native
            // ne rend aucun identifiant de publication, et laisser le client en inventer
            // un rendrait la colonne inutilisable le jour ou l'API Graph la remplira.
        ];
    }
}
