<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Pigment;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le nuancier EST la navigation du site : si le filtre se trompe, il n'y a plus de menu.
 */
class NuancierFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_works_by_a_single_pigment(): void
    {
        [$outremer, $garance] = Pigment::factory()->count(2)->create();

        $bleue = Work::factory()->published()->create();
        $bleue->pigments()->attach($outremer, ['weight' => 1.0, 'is_dominant' => true]);

        $rose = Work::factory()->published()->create();
        $rose->pigments()->attach($garance, ['weight' => 1.0, 'is_dominant' => true]);

        $this->getJson("/api/works?pigments={$outremer->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $bleue->slug);
    }

    /**
     * Multi-selection = INTERSECTION et non union : demander outremer ET garance doit
     * rendre les oeuvres qui portent les deux, pas celles qui portent l'un ou l'autre.
     */
    public function test_multiple_pigments_intersect(): void
    {
        [$outremer, $garance] = Pigment::factory()->count(2)->create();

        $lesDeux = Work::factory()->published()->create();
        $lesDeux->pigments()->attach($outremer, ['weight' => 0.6, 'is_dominant' => true]);
        $lesDeux->pigments()->attach($garance, ['weight' => 0.4, 'is_dominant' => false]);

        $unSeul = Work::factory()->published()->create();
        $unSeul->pigments()->attach($outremer, ['weight' => 1.0, 'is_dominant' => true]);

        $this->getJson("/api/works?pigments={$outremer->slug},{$garance->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $lesDeux->slug);
    }

    public function test_pigments_are_ordered_by_decreasing_weight(): void
    {
        [$minoritaire, $dominant] = Pigment::factory()->count(2)->create();

        $work = Work::factory()->published()->create();
        $work->pigments()->attach($minoritaire, ['weight' => 0.2, 'is_dominant' => false]);
        $work->pigments()->attach($dominant, ['weight' => 0.8, 'is_dominant' => true]);

        $this->getJson("/api/works/{$work->slug}")
            ->assertOk()
            ->assertJsonPath('data.pigments.0.slug', $dominant->slug)
            ->assertJsonPath('data.pigments.0.isDominant', true)
            ->assertJsonPath('data.pigments.1.slug', $minoritaire->slug);
    }

    /**
     * Une pastille qui ne filtre rien est un cul-de-sac : le nuancier public ne doit
     * exposer que les pigments effectivement portes par une oeuvre visible.
     */
    public function test_public_nuancier_omits_pigments_without_a_published_work(): void
    {
        [$utilise, $inutilise, $surPrivee] = Pigment::factory()->count(3)->create();

        Work::factory()->published()->create()->pigments()->attach($utilise, ['weight' => 1.0, 'is_dominant' => true]);
        Work::factory()->privee()->create()->pigments()->attach($surPrivee, ['weight' => 1.0, 'is_dominant' => true]);

        $response = $this->getJson('/api/pigments')->assertOk();

        $slugs = array_column($response->json('data'), 'slug');

        $this->assertContains($utilise->slug, $slugs);
        $this->assertNotContains($inutilise->slug, $slugs);
        $this->assertNotContains($surPrivee->slug, $slugs, 'Un pigment porte uniquement par une oeuvre privee reste cache');
    }

    public function test_public_nuancier_never_exposes_cielab_values(): void
    {
        $pigment = Pigment::factory()->create();
        Work::factory()->published()->create()->pigments()->attach($pigment, ['weight' => 1.0, 'is_dominant' => true]);

        $this->getJson('/api/pigments')
            ->assertOk()
            ->assertJsonMissingPath('data.0.lab_l');
    }
}
