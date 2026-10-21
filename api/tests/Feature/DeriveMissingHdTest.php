<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MediaRole;
use App\Models\Work;
use App\Models\WorkMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `works:derivees-hd` rattrape la derivee haute definition des medias derives avant
 * elle. Additive seulement : rien d'existant n'est reecrit.
 */
class DeriveMissingHdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('originaux');
    }

    public function test_creates_the_missing_hd_at_the_original_width_without_touching_the_rest(): void
    {
        $media = $this->mediaDerive(MediaRole::Principal, 'works/x/photo');
        $original = Storage::disk('originaux')->get('works/x/photo.jpg');

        $this->artisan('works:derivees-hd')
            ->expectsOutputToContain('2 fichier(s) cree(s), 0 deja present(s), 0 media(s) sans original.')
            ->assertSuccessful();

        $public = Storage::disk('public');
        $public->assertExists('works/x/photo-1400.avif');
        $public->assertExists('works/x/photo-1400.webp');
        $this->assertSame(1400, getimagesize($public->path('works/x/photo-1400.webp'))[0]);

        // Ni les derivees existantes, ni l'original, ni la palette.
        $this->assertSame('derivee-800', $public->get('works/x/photo-800.webp'));
        $this->assertSame($original, Storage::disk('originaux')->get('works/x/photo.jpg'));
        $this->assertCount(0, $media->work->pigments);
        $public->assertMissing('works/x/photo-1600.webp');
    }

    public function test_an_existing_hd_is_left_untouched_and_a_second_run_does_nothing(): void
    {
        $this->mediaDerive(MediaRole::Principal, 'works/x/photo');
        Storage::disk('public')->put('works/x/photo-1400.avif', 'hd-avif');
        Storage::disk('public')->put('works/x/photo-1400.webp', 'hd-webp');

        $this->artisan('works:derivees-hd')
            ->expectsOutputToContain('0 fichier(s) cree(s), 2 deja present(s)')
            ->assertSuccessful();

        $this->assertSame('hd-avif', Storage::disk('public')->get('works/x/photo-1400.avif'));
        $this->assertSame('hd-webp', Storage::disk('public')->get('works/x/photo-1400.webp'));
    }

    public function test_the_raw_photo_is_ignored(): void
    {
        $this->mediaDerive(MediaRole::Brute, 'works/x/photo');

        $this->artisan('works:derivees-hd')
            ->expectsOutputToContain('0 fichier(s) cree(s), 0 deja present(s)')
            ->assertSuccessful();

        Storage::disk('public')->assertMissing('works/x/photo-1400.webp');
    }

    public function test_dry_run_writes_nothing_and_a_missing_original_is_reported(): void
    {
        $this->mediaDerive(MediaRole::Principal, 'works/x/photo');
        $orphelin = $this->mediaDerive(MediaRole::Detail, 'works/y/detail');
        Storage::disk('originaux')->delete($orphelin->path_original);

        $this->artisan('works:derivees-hd --dry-run')
            ->expectsOutputToContain('a creer works/x/photo-1400.webp')
            ->expectsOutputToContain('[dry-run] 2 fichier(s) a creer, 0 deja present(s), 1 media(s) sans original.')
            ->assertSuccessful();

        Storage::disk('public')->assertMissing('works/x/photo-1400.avif');
        Storage::disk('public')->assertMissing('works/x/photo-1400.webp');
    }

    private function mediaDerive(MediaRole $role, string $prefixe): WorkMedia
    {
        $image = imagecreatetruecolor(1400, 1050);
        imagefill($image, 0, 0, imagecolorallocate($image, 0x28, 0x46, 0xA0));
        ob_start();
        imagejpeg($image);
        Storage::disk('originaux')->put("{$prefixe}.jpg", (string) ob_get_clean());

        // Derivees d'avant la HD : paliers standards seulement.
        foreach (['400', '800'] as $largeur) {
            foreach (['avif', 'webp'] as $format) {
                Storage::disk('public')->put("{$prefixe}-{$largeur}.{$format}", "derivee-{$largeur}");
            }
        }

        return Work::factory()->create()->media()->create([
            'role' => $role,
            'disk' => 'public',
            'disk_original' => 'originaux',
            'path_original' => "{$prefixe}.jpg",
            'path_web' => $prefixe,
            'width' => 1400,
            'height' => 1050,
            'mime' => 'image/jpeg',
            'position' => 0,
        ]);
    }
}
