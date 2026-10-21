<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MediaRole;
use App\Models\Series;
use App\Models\Work;
use App\Models\WorkMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Originaux et brutes vivent sur un disque prive. Sur `public`, ils etaient
 * telechargeables par quiconque devinait leur nom depuis celui des derivees.
 */
class OriginauxPrivesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('originaux');
    }

    public function test_the_private_disk_has_no_public_url_and_is_not_linked(): void
    {
        $config = config('filesystems.disks.originaux');

        $this->assertArrayNotHasKey('url', $config);
        $this->assertFalse($config['serve'] ?? false);
        $this->assertNotContains($config['root'], config('filesystems.links'));
        $this->assertStringStartsNotWith(storage_path('app/public'), $config['root']);
        $this->assertStringStartsNotWith(storage_path('app/private'), $config['root']);
    }

    public function test_the_command_moves_old_originals_and_updates_the_database_once(): void
    {
        $work = Work::factory()->published()->create();
        $principal = $this->ancienMedia($work, MediaRole::Principal, 'works/x/corrigee.jpg', 'works/x/corrigee');
        $brute = $this->ancienMedia($work, MediaRole::Brute, 'works/x/brute.jpg', null, 1);
        Storage::disk('public')->put('works/x/corrigee-800.webp', 'derivee');

        $this->artisan('works:originaux-prives --dry-run')
            ->expectsOutputToContain('[dry-run] 2 original(aux) a deplacer, 0 deja prive(s), 0 introuvable(s).')
            ->assertSuccessful();

        Storage::disk('public')->assertExists('works/x/corrigee.jpg');
        $this->assertSame('public', $principal->fresh()->disk_original);

        $this->artisan('works:originaux-prives')
            ->expectsOutputToContain('2 original(aux) deplace(s), 0 deja prive(s), 0 introuvable(s).')
            ->assertSuccessful();

        foreach ([$principal, $brute] as $media) {
            $media->refresh();
            $this->assertSame(WorkMedia::DISQUE_ORIGINAUX, $media->disk_original);
            Storage::disk('public')->assertMissing($media->path_original);
            Storage::disk('originaux')->assertExists($media->path_original);
        }

        $this->assertSame('octets de works/x/corrigee.jpg', Storage::disk('originaux')->get('works/x/corrigee.jpg'));
        // Les derivees restent publiques, au meme endroit.
        $this->assertSame('public', $principal->disk);
        $this->assertSame('derivee', Storage::disk('public')->get('works/x/corrigee-800.webp'));

        $this->artisan('works:originaux-prives')
            ->expectsOutputToContain('0 original(aux) deplace(s), 2 deja prive(s), 0 introuvable(s).')
            ->assertSuccessful();

        $this->assertSame('octets de works/x/corrigee.jpg', Storage::disk('originaux')->get('works/x/corrigee.jpg'));
    }

    /** Une execution interrompue apres la suppression publique se termine a la reprise. */
    public function test_a_resumed_run_only_updates_the_database(): void
    {
        $media = $this->ancienMedia(Work::factory()->create(), MediaRole::Principal, 'works/x/photo.jpg', null);
        Storage::disk('originaux')->put('works/x/photo.jpg', Storage::disk('public')->get('works/x/photo.jpg'));
        Storage::disk('public')->delete('works/x/photo.jpg');

        $this->artisan('works:originaux-prives')
            ->expectsOutputToContain('1 original(aux) deplace(s)')
            ->assertSuccessful();

        $this->assertSame(WorkMedia::DISQUE_ORIGINAUX, $media->fresh()->disk_original);
    }

    public function test_no_public_route_exposes_a_private_path(): void
    {
        $series = Series::factory()->create();
        $work = Work::factory()->published()->create(['series_id' => $series->id]);
        $this->ancienMedia($work, MediaRole::Principal, 'works/x/corrigee.jpg', 'works/x/corrigee');
        $this->ancienMedia($work, MediaRole::Brute, 'works/x/brute.jpg', null, 1);
        $this->artisan('works:originaux-prives')->assertSuccessful();

        $routes = ['/api/works', "/api/works/{$work->slug}", "/api/series/{$series->slug}", '/api/pigments'];

        foreach ($routes as $route) {
            $corps = $this->getJson($route)->assertOk()->getContent();

            foreach (['corrigee.jpg', 'brute', 'originaux', 'disk', storage_path()] as $interdit) {
                $this->assertStringNotContainsString($interdit, $corps, "{$route} contient « {$interdit} »");
            }
        }

        $media = $this->getJson('/api/works')->json('data.0.media.0');
        $this->assertSame(['role', 'width', 'height', 'lqipHex', 'sources', 'hd'], array_keys($media));
    }

    public function test_public_orphans_are_listed_and_moved_only_with_the_option(): void
    {
        $media = $this->ancienMedia(Work::factory()->create(), MediaRole::Principal, 'works/x/vivant.jpg', 'works/x/vivant');
        Storage::disk('public')->put('works/x/vivant-400.webp', 'derivee');
        Storage::disk('public')->put('works/y/orphelin.png', 'octets orphelins');
        Storage::disk('public')->put('works/y/orphelin-800.avif', 'derivee orpheline');

        $this->artisan('works:originaux-prives --dry-run --orphelins')
            ->expectsOutputToContain('orphelin a deplacer public:works/y/orphelin.png (16 octets)')
            ->expectsOutputToContain('[dry-run] 1 orphelin(s) public(s) a deplacer.')
            ->assertSuccessful();

        $this->artisan('works:originaux-prives')
            ->expectsOutputToContain('orphelin public:works/y/orphelin.png')
            ->expectsOutputToContain('1 orphelin(s) public(s) laisse(s) en place (--orphelins pour les deplacer).')
            ->assertSuccessful();

        Storage::disk('public')->assertExists('works/y/orphelin.png');

        $this->artisan('works:originaux-prives --orphelins')
            ->expectsOutputToContain('1 orphelin(s) public(s) deplace(s) sous orphelins/.')
            ->assertSuccessful();

        Storage::disk('public')->assertMissing('works/y/orphelin.png');
        $this->assertSame('octets orphelins', Storage::disk('originaux')->get('orphelins/works/y/orphelin.png'));
        // Ni les derivees (orphelines ou non) ni l'original reference ne sont des orphelins.
        Storage::disk('public')->assertExists(['works/y/orphelin-800.avif', 'works/x/vivant-400.webp']);
        Storage::disk('originaux')->assertExists($media->path_original);
        Storage::disk('originaux')->assertMissing('orphelins/works/x/vivant.jpg');
    }

    /** Une copie differente, ou un checksum illisible des deux cotes, ne supprime rien. */
    public function test_a_checksum_mismatch_fails_without_deleting_the_source(): void
    {
        $media = $this->ancienMedia(Work::factory()->create(), MediaRole::Principal, 'works/x/photo.jpg', null);

        // Copie differente, puis checksum illisible des deux cotes (`false === false`).
        foreach ([['originaux' => 'autre-empreinte'], ['public' => false, 'originaux' => false]] as $checksums) {
            Storage::fake('public');
            Storage::fake('originaux');
            Storage::disk('public')->put('works/x/photo.jpg', 'octets');

            foreach ($checksums as $nom => $checksum) {
                $disque = Mockery::mock(Storage::disk($nom))->makePartial();
                $disque->shouldReceive('checksum')->andReturn($checksum);
                Storage::set($nom, $disque);
            }

            $this->artisan('works:originaux-prives')
                ->expectsOutputToContain('copie differente de la source (ou checksum illisible), copie retiree, source intacte.')
                ->assertFailed();

            Storage::disk('public')->assertExists('works/x/photo.jpg');
            // La copie fautive est retiree de la cible.
            $this->assertSame([], Storage::disk('originaux')->allFiles());
            $this->assertSame('public', $media->fresh()->disk_original);
        }
    }

    public function test_rollback_is_refused_until_the_originals_are_brought_back(): void
    {
        $migration = require database_path('migrations/2026_10_21_120000_add_disk_original_to_work_media_table.php');
        $media = $this->ancienMedia(Work::factory()->create(), MediaRole::Principal, 'works/x/photo.jpg', null);
        $this->artisan('works:originaux-prives')->assertSuccessful();

        try {
            $migration->down();
            $this->fail('down() aurait du refuser.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('works:originaux-prives --retour', $e->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('work_media', 'disk_original'));

        $this->artisan('works:originaux-prives --retour --dry-run')
            ->expectsOutputToContain('[dry-run] 1 original(aux) a ramener, 0 introuvable(s).')
            ->assertSuccessful();
        Storage::disk('originaux')->assertExists('works/x/photo.jpg');

        $this->artisan('works:originaux-prives --retour')
            ->expectsOutputToContain('1 original(aux) ramene(s), 0 introuvable(s).')
            ->assertSuccessful();

        Storage::disk('originaux')->assertMissing('works/x/photo.jpg');
        $this->assertSame('octets de works/x/photo.jpg', Storage::disk('public')->get('works/x/photo.jpg'));
        $this->assertSame('public', $media->fresh()->disk_original);

        $migration->down();
        $this->assertFalse(Schema::hasColumn('work_media', 'disk_original'));
        $migration->up();
    }

    /** Media tel que televerse avant le disque prive : original a cote des derivees. */
    private function ancienMedia(Work $work, MediaRole $role, string $original, ?string $web, int $position = 0): WorkMedia
    {
        Storage::disk('public')->put($original, "octets de {$original}");

        return $work->media()->create([
            'role' => $role,
            'disk' => 'public',
            'disk_original' => 'public',
            'path_original' => $original,
            'path_web' => $web,
            'width' => 1200,
            'height' => 900,
            'mime' => 'image/jpeg',
            'lqip_hex' => '#c1c0c9',
            'position' => $position,
        ]);
    }
}
