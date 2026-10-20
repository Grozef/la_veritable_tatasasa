<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MediaRole;
use App\Jobs\ProcessWorkMedia;
use App\Models\Work;
use App\Models\WorkMedia;
use Database\Seeders\PigmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessWorkMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_derives_only_the_widths_the_original_can_feed(): void
    {
        $this->seed(PigmentSeeder::class);
        $media = $this->uploadedMedia(MediaRole::Principal);

        dispatch_sync(new ProcessWorkMedia($media));

        $disk = Storage::disk('public');

        foreach (['400', '800'] as $width) {
            $disk->assertExists("works/sonde/aquarelle-{$width}.avif");
            $disk->assertExists("works/sonde/aquarelle-{$width}.webp");
        }

        // L'original fait 900 px de large : 1600 et 3000 n'ajouteraient aucun detail et
        // `Derivatives::srcset` ne les publie pas non plus.
        $disk->assertMissing('works/sonde/aquarelle-1600.avif');
        $disk->assertMissing('works/sonde/aquarelle-3000.avif');

        $this->assertSame('works/sonde/aquarelle', $media->fresh()->path_web);
    }

    public function test_it_records_the_palette_of_the_main_image(): void
    {
        $this->seed(PigmentSeeder::class);
        $media = $this->uploadedMedia(MediaRole::Principal);

        dispatch_sync(new ProcessWorkMedia($media));

        $pigment = $media->work->pigments()->firstOrFail();

        $this->assertSame('outremer', $pigment->slug);
        $this->assertTrue((bool) $pigment->pivot->is_dominant);
    }

    /**
     * Un detail est cadre sur une zone : ses proportions ne sont pas celles de l'oeuvre.
     * Il produit des derivees, jamais de nuancier.
     */
    public function test_a_detail_shot_does_not_touch_the_palette(): void
    {
        $this->seed(PigmentSeeder::class);
        $media = $this->uploadedMedia(MediaRole::Detail);

        dispatch_sync(new ProcessWorkMedia($media));

        Storage::disk('public')->assertExists('works/sonde/aquarelle-800.webp');
        $this->assertCount(0, $media->work->pigments);
    }

    private function uploadedMedia(MediaRole $role): WorkMedia
    {
        Storage::fake('public');

        $image = imagecreatetruecolor(900, 600);
        imagefill($image, 0, 0, imagecolorallocate($image, 0xFC, 0xFA, 0xF5));
        imagefilledrectangle($image, 0, 0, 899, 199, imagecolorallocate($image, 0x28, 0x46, 0xA0));

        ob_start();
        imagejpeg($image);
        Storage::disk('public')->put('works/sonde/aquarelle.jpg', (string) ob_get_clean());

        return Work::factory()->create()->media()->create([
            'role' => $role,
            'disk' => 'public',
            'path_original' => 'works/sonde/aquarelle.jpg',
            'width' => 900,
            'height' => 600,
            'mime' => 'image/jpeg',
            'position' => 0,
        ]);
    }
}
