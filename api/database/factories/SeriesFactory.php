<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Series;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Series>
 */
class SeriesFactory extends Factory
{
    protected $model = Series::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = ucfirst($this->faker->unique()->words(2, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'year' => $this->faker->numberBetween(2019, 2026),
            'notes' => $this->faker->optional()->paragraph(),
        ];
    }
}
