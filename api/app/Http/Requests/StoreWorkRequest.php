<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaperGrain;
use App\Enums\WorkStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:works,slug'],
            'year' => ['required', 'integer', 'between:1900,2100'],
            'series_id' => ['nullable', 'integer', 'exists:series,id'],
            'medium' => ['nullable', 'string', 'max:255'],
            'paper_grain' => ['nullable', Rule::enum(PaperGrain::class)],

            // Bornes en millimetres : 20 mm ecarte la saisie en centimetres,
            // 2000 mm couvre les plus grands formats papier du commerce.
            'width_mm' => ['required', 'integer', 'between:20,2000'],
            'height_mm' => ['required', 'integer', 'between:20,2000'],

            'status' => ['nullable', Rule::enum(WorkStatus::class)],
            'notes' => ['nullable', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
