<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaperGrain;
use App\Enums\WorkStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('works', 'slug')->ignore($this->route('work'))],
            'year' => ['sometimes', 'integer', 'between:1900,2100'],
            'series_id' => ['sometimes', 'nullable', 'integer', 'exists:series,id'],
            'medium' => ['sometimes', 'string', 'max:255'],
            'paper_grain' => ['sometimes', Rule::enum(PaperGrain::class)],
            'width_mm' => ['sometimes', 'integer', 'between:20,2000'],
            'height_mm' => ['sometimes', 'integer', 'between:20,2000'],
            'status' => ['sometimes', Rule::enum(WorkStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
