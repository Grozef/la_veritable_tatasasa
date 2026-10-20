<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MediaRole;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudioMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_records_real_dimensions_and_keeps_the_original(): void
    {
        Storage::fake('public');
        $work = Work::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/studio/works/{$work->ulid}/media", [
                'file' => UploadedFile::fake()->image('aquarelle.jpg', 900, 600),
                'role' => MediaRole::Principal->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.width', 900)
            ->assertJsonPath('data.height', 600)
            ->assertJsonPath('data.role', MediaRole::Principal->value);

        $media = WorkMedia::firstOrFail();

        Storage::disk('public')->assertExists($media->path_original);
        $this->assertNotNull($media->lqip_hex, 'Le fond de chargement est lu a l\'upload');
    }

    /**
     * `sources` reste null tant que le job de derivation (Phase 2) n'a pas tourne.
     * Renvoyer des chaines vides ferait pointer des <img> vers des URL inexistantes.
     */
    public function test_sources_stay_null_until_derivatives_exist(): void
    {
        Storage::fake('public');
        $work = Work::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/studio/works/{$work->ulid}/media", [
                'file' => UploadedFile::fake()->image('aquarelle.jpg', 900, 600),
            ])
            ->assertCreated()
            ->assertJsonPath('data.sources', null);
    }

    public function test_rejects_a_file_that_is_not_an_image(): void
    {
        Storage::fake('public');
        $work = Work::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/studio/works/{$work->ulid}/media", [
                'file' => UploadedFile::fake()->create('notice.pdf', 100, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    /**
     * Un media ne doit pas pouvoir etre supprime via une AUTRE oeuvre que la sienne.
     */
    public function test_cannot_delete_a_media_through_a_foreign_work(): void
    {
        Storage::fake('public');
        $artiste = User::factory()->create();
        $work = Work::factory()->create();
        $autre = Work::factory()->create();

        $this->actingAs($artiste)
            ->postJson("/api/studio/works/{$work->ulid}/media", [
                'file' => UploadedFile::fake()->image('aquarelle.jpg', 400, 300),
            ])
            ->assertCreated();

        $media = WorkMedia::firstOrFail();

        $this->actingAs($artiste)
            ->deleteJson("/api/studio/works/{$autre->ulid}/media/{$media->id}")
            ->assertNotFound();

        $this->assertDatabaseCount('work_media', 1);
    }
}
