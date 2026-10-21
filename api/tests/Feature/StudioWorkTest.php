<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaperGrain;
use App\Enums\WorkStatus;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudioWorkTest extends TestCase
{
    use RefreshDatabase;

    private function artiste(): User
    {
        return User::factory()->create();
    }

    public function test_studio_routes_reject_anonymous_callers(): void
    {
        $work = Work::factory()->create();

        $this->getJson('/api/studio/works')->assertUnauthorized();
        $this->postJson('/api/studio/works', [])->assertUnauthorized();
        $this->patchJson("/api/studio/works/{$work->ulid}", [])->assertUnauthorized();
        $this->deleteJson("/api/studio/works/{$work->ulid}")->assertUnauthorized();
    }

    /**
     * L'inverse de la route publique : l'atelier doit voir brouillons et privees,
     * sans quoi l'artiste ne peut plus les rouvrir.
     */
    public function test_studio_index_shows_drafts_and_private_works(): void
    {
        Work::factory()->create(['published_at' => null]);
        Work::factory()->privee()->create();
        Work::factory()->published()->create();

        $this->actingAs($this->artiste())
            ->getJson('/api/studio/works')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_creates_a_work_and_derives_its_slug_from_the_title(): void
    {
        $this->actingAs($this->artiste())
            ->postJson('/api/studio/works', [
                'title' => 'La chute lente',
                'year' => 2024,
                'width_mm' => 380,
                'height_mm' => 280,
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'la-chute-lente')
            // Defauts portes par le modele : sans eux la ressource cassait sur ->value
            ->assertJsonPath('data.paperGrain', PaperGrain::Torchon->value)
            ->assertJsonPath('data.status', WorkStatus::Atelier->value)
            ->assertJsonPath('data.medium', 'aquarelle');
    }

    /**
     * « Sans titre » deux fois est le cas courant chez une artiste, pas le cas limite.
     */
    public function test_slug_collision_is_disambiguated(): void
    {
        $artiste = $this->artiste();
        Work::factory()->create(['title' => 'Sans titre', 'slug' => 'sans-titre']);

        $this->actingAs($artiste)
            ->postJson('/api/studio/works', [
                'title' => 'Sans titre',
                'year' => 2025,
                'width_mm' => 200,
                'height_mm' => 200,
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'sans-titre-2');
    }

    public function test_rejects_out_of_range_dimensions(): void
    {
        $this->actingAs($this->artiste())
            ->postJson('/api/studio/works', [
                'title' => 'Trop grande',
                'year' => 2026,
                'width_mm' => 9000,
                'height_mm' => 200,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('width_mm');
    }

    public function test_rejects_an_unknown_status(): void
    {
        $this->actingAs($this->artiste())
            ->postJson('/api/studio/works', [
                'title' => 'Statut inconnu',
                'year' => 2026,
                'width_mm' => 200,
                'height_mm' => 200,
                'status' => 'encadree',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_publishing_a_work_makes_it_visible_publicly(): void
    {
        $work = Work::factory()->create(['published_at' => null]);

        $this->getJson('/api/works')->assertJsonCount(0, 'data');

        $this->actingAs($this->artiste())
            ->patchJson("/api/studio/works/{$work->ulid}", ['published_at' => now()->toIso8601String()])
            ->assertOk();

        $this->getJson('/api/works')->assertJsonCount(1, 'data');
    }

    public function test_deleting_a_work_cascades_to_its_media_and_trashes_originals(): void
    {
        Storage::fake('public');
        Storage::fake('originaux');
        $artiste = $this->artiste();
        $work = Work::factory()->create();

        $this->actingAs($artiste)
            ->postJson("/api/studio/works/{$work->ulid}/media", [
                'file' => UploadedFile::fake()->image('aquarelle.jpg', 600, 400),
            ])
            ->assertCreated();

        $this->assertDatabaseCount('work_media', 1);
        $this->assertNotEmpty(Storage::disk('public')->allFiles("works/{$work->ulid}"));

        $this->actingAs($artiste)
            ->deleteJson("/api/studio/works/{$work->ulid}")
            ->assertNoContent();

        $this->assertDatabaseCount('works', 0);
        $this->assertDatabaseCount('work_media', 0);
        // La cascade de schema n'emporte que les lignes : `Work` met l'original a la
        // corbeille privee et efface les derivees, rien ne reste sur `public`.
        $this->assertSame([], Storage::disk('originaux')->allFiles("works/{$work->ulid}"));
        $this->assertCount(1, Storage::disk('originaux')->allFiles("corbeille/works/{$work->ulid}"));
        $this->assertSame([], Storage::disk('public')->allFiles("works/{$work->ulid}"));
    }
}
