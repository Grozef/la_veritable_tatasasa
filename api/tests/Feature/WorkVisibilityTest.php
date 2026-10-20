<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WorkStatus;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regle metier centrale du portfolio : ce qui sort en public, et ce qui n'en sort pas.
 * C'est la seule ou une erreur expose une oeuvre que l'artiste voulait garder privee.
 */
class WorkVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_work_appears_in_the_index(): void
    {
        $work = Work::factory()->published()->create(['title' => 'Visible']);

        $this->getJson('/api/works')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $work->slug);
    }

    public function test_draft_is_absent_from_the_index(): void
    {
        Work::factory()->create(['published_at' => null]);

        $this->getJson('/api/works')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Le cas piege. L'oeuvre PORTE une date de publication : un filtre qui ne teste
     * que `published_at` la laisse passer. C'est le statut qui doit primer.
     */
    public function test_private_work_is_absent_even_though_it_is_dated(): void
    {
        $work = Work::factory()->privee()->create();

        $this->assertNotNull($work->published_at, 'La fixture doit bien porter une date');
        $this->assertSame(WorkStatus::Privee, $work->status);

        $this->getJson('/api/works')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_work_published_in_the_future_is_absent(): void
    {
        Work::factory()->create(['published_at' => now()->addWeek()]);

        $this->getJson('/api/works')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_show_returns_the_published_work(): void
    {
        $work = Work::factory()->published()->create();

        $this->getJson("/api/works/{$work->slug}")
            ->assertOk()
            ->assertJsonPath('data.ulid', $work->ulid);
    }

    /**
     * 404 et non 403 : repondre « interdit » revelerait qu'une oeuvre existe a ce slug.
     */
    public function test_show_hides_draft_and_private_behind_a_404(): void
    {
        $draft = Work::factory()->create(['published_at' => null]);
        $private = Work::factory()->privee()->create();

        $this->getJson("/api/works/{$draft->slug}")->assertNotFound();
        $this->getJson("/api/works/{$private->slug}")->assertNotFound();
    }

    public function test_response_shape_matches_the_shared_contract(): void
    {
        Work::factory()->published()->create();

        $this->getJson('/api/works')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'ulid', 'slug', 'title', 'year', 'medium', 'paperGrain',
                    'widthMm', 'heightMm', 'status', 'notes', 'pigments', 'media', 'publishedAt',
                ]],
            ]);
    }
}
