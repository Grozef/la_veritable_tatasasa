<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MediaRole;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApercuTest extends TestCase
{
    use RefreshDatabase;

    public function test_serves_the_largest_derivative_the_original_could_feed(): void
    {
        Storage::fake('public');
        Storage::fake('originaux');
        $work = $this->workAvecMedia(1200);

        // 1600 existe sur le disque mais depasse l'original : la servir reviendrait a
        // agrandir sans ajouter de detail.
        Storage::disk('public')->put('works/x/photo-400.webp', 'quatre-cents');
        Storage::disk('public')->put('works/x/photo-800.webp', 'huit-cents');
        Storage::disk('public')->put('works/x/photo-1600.webp', 'seize-cents');

        $reponse = $this->actingAs(User::factory()->create())
            ->get("/api/studio/works/{$work->ulid}/apercu")
            ->assertOk();

        $this->assertSame('huit-cents', $reponse->streamedContent());
    }

    public function test_falls_back_to_the_original_before_the_job_has_run(): void
    {
        Storage::fake('public');
        Storage::fake('originaux');
        $work = $this->workAvecMedia(1200, derivees: false);
        Storage::disk('originaux')->put('works/x/photo.jpg', 'original');

        $reponse = $this->actingAs(User::factory()->create())
            ->get("/api/studio/works/{$work->ulid}/apercu")
            ->assertOk();

        $this->assertSame('original', $reponse->streamedContent());
    }

    public function test_refuses_anonymous_callers_and_works_without_a_main_image(): void
    {
        Storage::fake('public');
        Storage::fake('originaux');
        $work = $this->workAvecMedia(1200);

        /*
         * `getJson` et non `get` : sans en-tete JSON, le middleware d'authentification
         * cherche a REDIRIGER vers une route `login` que ce projet n'a pas, et rend 500
         * au lieu de 401. Comportement prealable a cette route — il vaut pour tout le
         * groupe `studio` — signale et non corrige ici. L'app envoie toujours son jeton.
         */
        $this->getJson("/api/studio/works/{$work->ulid}/apercu")->assertUnauthorized();

        $nue = Work::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/studio/works/{$nue->ulid}/apercu")
            ->assertNotFound();
    }

    private function workAvecMedia(int $largeur, bool $derivees = true): Work
    {
        $work = Work::factory()->create();

        $work->media()->create([
            'role' => MediaRole::Principal,
            'disk' => 'public',
            'path_original' => 'works/x/photo.jpg',
            'path_web' => $derivees ? 'works/x/photo' : null,
            'width' => $largeur,
            'height' => (int) ($largeur * 0.75),
            'mime' => 'image/jpeg',
            'lqip_hex' => '#c1c0c9',
            'position' => 0,
        ]);

        return $work;
    }
}
