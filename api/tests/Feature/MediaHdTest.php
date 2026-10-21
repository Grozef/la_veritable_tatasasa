<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MediaRole;
use App\Models\Work;
use App\Support\Derivatives;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La derivee haute definition sert le zoom de la fiche. Elle s'ajoute a `sources` sans
 * le modifier, et ne doit jamais agrandir l'original ni le laisser sortir.
 */
class MediaHdTest extends TestCase
{
    use RefreshDatabase;

    public function test_hd_width_follows_the_original_without_ever_enlarging_it(): void
    {
        $this->assertSame(1400, Derivatives::hdWidth(1400));
        $this->assertSame(3000, Derivatives::hdWidth(5000));
        $this->assertSame(300, Derivatives::hdWidth(300));

        $this->assertSame([400, 800, 1400], Derivatives::widthsFor(1400));
        // Au-dela du plafond, la HD se confond avec la derivee 3000 : pas de doublon.
        $this->assertSame([400, 800, 1600, 3000], Derivatives::widthsFor(5000));
        $this->assertSame([400, 800], Derivatives::widthsFor(800));
    }

    public function test_public_routes_expose_the_hd_next_to_unchanged_sources(): void
    {
        Storage::fake('public');
        $work = Work::factory()->published()->create();
        $this->media($work, MediaRole::Principal, 'works/x/photo.jpg', 'works/x/photo', 1400);

        $media = $this->getJson('/api/works')->assertOk()->json('data.0.media.0');

        $this->assertSame(1400, $media['hd']['width']);
        $this->assertStringEndsWith('works/x/photo-1400.avif', $media['hd']['avif']);
        $this->assertStringEndsWith('works/x/photo-1400.webp', $media['hd']['webp']);

        // Le srcset garde ses seuls paliers standards : la HD n'y entre pas, sinon un
        // ecran dense la telechargerait des le chargement de la fiche.
        $this->assertStringNotContainsString('1400', $media['sources']['avif']);
        $this->assertStringNotContainsString('1400', $media['sources']['webp']);

        $this->getJson("/api/works/{$work->slug}")
            ->assertOk()
            ->assertJsonPath('data.media.0.hd.width', 1400);
    }

    public function test_hd_stays_null_until_derivatives_exist(): void
    {
        $work = Work::factory()->published()->create();
        $this->media($work, MediaRole::Principal, 'works/x/photo.jpg', null, 1400);

        $this->getJson('/api/works')
            ->assertOk()
            ->assertJsonPath('data.0.media.0.sources', null)
            ->assertJsonPath('data.0.media.0.hd', null);
    }

    /** Ni l'original, ni la brute : aucune URL publique ne doit y mener. */
    public function test_neither_the_original_nor_the_raw_photo_leaks_publicly(): void
    {
        Storage::fake('public');
        $work = Work::factory()->published()->create();
        $this->media($work, MediaRole::Principal, 'works/x/corrigee.jpg', 'works/x/corrigee', 1400);
        $this->media($work, MediaRole::Brute, 'works/x/brute.jpg', null, 1600, 1);

        foreach (['/api/works', "/api/works/{$work->slug}"] as $route) {
            $corps = $this->getJson($route)->assertOk()->getContent();

            $this->assertStringNotContainsString('corrigee.jpg', $corps);
            $this->assertStringNotContainsString('brute', $corps);
        }
    }

    private function media(Work $work, MediaRole $role, string $original, ?string $web, int $largeur, int $position = 0): void
    {
        $work->media()->create([
            'role' => $role,
            'disk' => 'public',
            'path_original' => $original,
            'path_web' => $web,
            'width' => $largeur,
            'height' => (int) ($largeur * 0.75),
            'mime' => 'image/jpeg',
            'lqip_hex' => '#c1c0c9',
            'position' => $position,
        ]);
    }
}
