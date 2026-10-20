<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Pigment;
use App\Support\Lab;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pigment>
 */
class PigmentFactory extends Factory
{
    protected $model = Pigment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->words(2, true));
        $hex = $this->faker->hexColor();

        // Toujours derive du hex : un Lab incoherent avec la pastille rendrait le
        // mapping de PaletteExtractor faux sans que rien ne le signale.
        [$l, $a, $b] = Lab::fromHex($hex);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'hex' => $hex,
            'brand' => $this->faker->optional()->company(),
            'lab_l' => $l,
            'lab_a' => $a,
            'lab_b' => $b,
            'position' => $this->faker->numberBetween(0, 30),
        ];
    }
}
