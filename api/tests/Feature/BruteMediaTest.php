<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MediaRole;
use App\Jobs\ProcessWorkMedia;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * L'app atelier televerse DEUX fichiers par oeuvre : l'image corrigee, qui est la
 * numerisation de reference, et la photo brute, qui n'est qu'une archive permettant de
 * refaire la correction sans rephotographier l'aquarelle.
 */
class BruteMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_raw_photo_never_appears_on_the_public_routes(): void
    {
        $work = Work::factory()->published()->create();
        $work->media()->create($this->media(MediaRole::Principal, 'works/corrigee.jpg', 0));
        $work->media()->create($this->media(MediaRole::Brute, 'works/brute.jpg', 1));

        $carnet = $this->getJson('/api/works')->assertOk();
        $this->assertSame(['principal'], $carnet->json('data.*.media.*.role'));

        $fiche = $this->getJson("/api/works/{$work->slug}")->assertOk();
        $this->assertSame(['principal'], $fiche->json('data.media.*.role'));
    }

    /** L'atelier, lui, doit la voir : c'est la copie qui lui sert a refaire la correction. */
    public function test_the_studio_still_lists_the_raw_photo(): void
    {
        $work = Work::factory()->create();
        $work->media()->create($this->media(MediaRole::Principal, 'works/corrigee.jpg', 0));
        $work->media()->create($this->media(MediaRole::Brute, 'works/brute.jpg', 1));

        $reponse = $this->actingAs(User::factory()->create())
            ->getJson('/api/studio/works')
            ->assertOk();

        $this->assertSame(['principal', 'brute'], $reponse->json('data.0.media.*.role'));
    }

    /**
     * Deriver la brute couterait deux reencodages par largeur pour un fichier que
     * personne n'affiche, et l'extraction de palette tournerait sur une photo non
     * redressee, fond compris.
     */
    public function test_uploading_a_raw_photo_queues_no_derivation_job(): void
    {
        Storage::fake('public');
        Bus::fake();

        $work = Work::factory()->create();
        $artiste = User::factory()->create();

        $this->actingAs($artiste)
            ->postJson("/api/studio/works/{$work->ulid}/media", [
                'file' => UploadedFile::fake()->image('brute.jpg', 1200, 900),
                'role' => 'brute',
            ])
            ->assertCreated();

        Bus::assertNotDispatched(ProcessWorkMedia::class);

        $this->actingAs($artiste)
            ->postJson("/api/studio/works/{$work->ulid}/media", [
                'file' => UploadedFile::fake()->image('corrigee.jpg', 1200, 900),
                'role' => 'principal',
            ])
            ->assertCreated();

        Bus::assertDispatched(ProcessWorkMedia::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function media(MediaRole $role, string $chemin, int $position): array
    {
        return [
            'role' => $role,
            'disk' => 'public',
            'path_original' => $chemin,
            'path_web' => null,
            'width' => 1200,
            'height' => 900,
            'mime' => 'image/jpeg',
            'lqip_hex' => '#c1c0c9',
            'position' => $position,
        ];
    }
}
