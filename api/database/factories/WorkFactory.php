<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaperGrain;
use App\Enums\WorkStatus;
use App\Models\Work;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Work>
 */
class WorkFactory extends Factory
{
    protected $model = Work::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = ucfirst($this->faker->unique()->words(3, true));

        return [
            'series_id' => null,
            'title' => $title,
            'slug' => Str::slug($title),
            'year' => $this->faker->numberBetween(2019, 2026),
            'medium' => 'aquarelle',
            'paper_grain' => $this->faker->randomElement(PaperGrain::cases()),
            'width_mm' => $this->faker->numberBetween(150, 760),
            'height_mm' => $this->faker->numberBetween(150, 560),
            'status' => WorkStatus::Atelier,
            'notes' => $this->faker->optional()->sentence(),
            'position' => $this->faker->numberBetween(0, 100),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'published_at' => now()->subDays($this->faker->numberBetween(1, 400)),
            'status' => $this->faker->randomElement([WorkStatus::Atelier, WorkStatus::Exposee, WorkStatus::Vendue]),
        ]);
    }

    /**
     * Publiee mais privee : le cas qui doit rester invisible du portfolio malgre
     * une date de publication renseignee.
     */
    public function privee(): static
    {
        return $this->state(fn (): array => [
            'published_at' => now()->subDay(),
            'status' => WorkStatus::Privee,
        ]);
    }
}
