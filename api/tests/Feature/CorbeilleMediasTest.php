<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MediaRole;
use App\Models\Work;
use App\Models\WorkMedia;
use App\Services\CorbeilleMedias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Suppression d'un media ou d'une oeuvre : l'original part dans la corbeille privee,
 * les derivees publiques sont effacees, et tout echec remet chaque fichier en place.
 */
class CorbeilleMediasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('originaux');
    }

    /** Un `delete()` direct oublierait l'original : il est refuse. */
    public function test_direct_delete_is_refused(): void
    {
        $work = Work::factory()->create();
        $media = $this->media($work, 'originaux', 'works/x/photo.jpg');

        foreach ([$media, $work] as $modele) {
            try {
                $modele->delete();
                $this->fail('delete() direct aurait du etre refuse.');
            } catch (LogicException $e) {
                $this->assertStringContainsString(CorbeilleMedias::class, $e->getMessage());
            }
        }

        $this->assertNotNull($media->fresh());
        Storage::disk('originaux')->assertExists('works/x/photo.jpg');
    }

    /** Original encore public : copie verifiee vers la corbeille, collision suffixee. */
    public function test_trash_takes_public_originals_and_never_overwrites(): void
    {
        Storage::disk('originaux')->put('corbeille/works/x/photo.jpg', 'deja a la corbeille');
        $media = $this->media(Work::factory()->create(), 'public', 'works/x/photo.jpg', MediaRole::Brute);

        app(CorbeilleMedias::class)->supprimerMedia($media);

        $this->assertNull($media->fresh());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame('deja a la corbeille', Storage::disk('originaux')->get('corbeille/works/x/photo.jpg'));
        $this->assertSame('octets de works/x/photo.jpg', Storage::disk('originaux')->get('corbeille/works/x/photo-2.jpg'));
    }

    /** Move interne au disque prive : collision suffixee, chemin sans extension compris. */
    public function test_internal_move_never_overwrites_even_without_extension(): void
    {
        $work = Work::factory()->create();
        Storage::disk('originaux')->put('corbeille/works/x/photo.jpg', 'ancien');
        Storage::disk('originaux')->put('corbeille/works/x/sansext', 'ancien');
        $avec = $this->media($work, 'originaux', 'works/x/photo.jpg');
        $sans = $this->media($work, 'originaux', 'works/x/sansext', position: 1);

        app(CorbeilleMedias::class)->supprimerMedia($avec);
        app(CorbeilleMedias::class)->supprimerMedia($sans);

        Storage::disk('originaux')->assertMissing(['works/x/photo.jpg', 'works/x/sansext']);
        $this->assertSame('octets de works/x/photo.jpg', Storage::disk('originaux')->get('corbeille/works/x/photo-2.jpg'));
        $this->assertSame('octets de works/x/sansext', Storage::disk('originaux')->get('corbeille/works/x/sansext-2'));
        $this->assertSame('ancien', Storage::disk('originaux')->get('corbeille/works/x/sansext'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /** Copie fautive : retiree de la corbeille, source intacte, ligne conservee. */
    public function test_a_failed_trash_keeps_the_media_and_removes_the_bad_copy(): void
    {
        $media = $this->media(Work::factory()->create(), 'public', 'works/x/photo.jpg');
        $disque = Mockery::mock(Storage::disk('originaux'))->makePartial();
        $disque->shouldReceive('checksum')->andReturn('autre-empreinte');
        Storage::set('originaux', $disque);

        $this->assertEchec(fn () => app(CorbeilleMedias::class)->supprimerMedia($media), 'corbeille impossible');

        $this->assertNotNull($media->fresh());
        Storage::disk('public')->assertExists(['works/x/photo.jpg', 'works/x/photo-400.webp', 'works/x/photo-400.avif']);
        $this->assertSame([], Storage::disk('originaux')->allFiles());
    }

    /** Echec au 2e media d'une oeuvre : le 1er original revient, rien n'est supprime. */
    public function test_work_deletion_is_all_or_nothing_when_a_trash_fails(): void
    {
        $work = Work::factory()->create();
        $this->media($work, 'originaux', 'works/x/un.jpg');
        $this->media($work, 'originaux', 'works/x/deux.jpg', position: 1);

        $reel = Storage::disk('originaux');
        $disque = Mockery::mock($reel)->makePartial();
        $appels = 0;
        $disque->shouldReceive('move')->andReturnUsing(
            function (string $de, string $vers) use ($reel, &$appels): bool {
                return ++$appels === 2 ? false : $reel->move($de, $vers);
            },
        );
        Storage::set('originaux', $disque);

        $this->assertEchec(fn () => app(CorbeilleMedias::class)->supprimerOeuvre($work), 'corbeille impossible');

        $this->assertSame(3, $appels, 'aller du 1er, echec du 2e, retour du 1er');
        $this->assertEtatIntact($work);
    }

    /** DELETE en echec apres la corbeille : les originaux reviennent, la ligne reste. */
    public function test_a_failed_delete_restores_the_originals(): void
    {
        $work = Work::factory()->create();
        $media = $this->media($work, 'originaux', 'works/x/un.jpg');
        $this->media($work, 'public', 'works/x/deux.jpg', position: 1);

        // Le listener leve apres le DELETE, dans la transaction : la ligne revient par
        // rollback. L'instance, elle, garde `exists = false` : relire en base.
        WorkMedia::deleted(fn () => throw new RuntimeException('echec SQL simule'));
        $this->assertEchec(fn () => app(CorbeilleMedias::class)->supprimerMedia($media), 'echec SQL simule');
        $this->assertNotNull(WorkMedia::find($media->id));
        Storage::disk('originaux')->assertExists('works/x/un.jpg');

        Work::deleted(fn () => throw new RuntimeException('echec SQL simule'));
        $this->assertEchec(fn () => app(CorbeilleMedias::class)->supprimerOeuvre($work), 'echec SQL simule');

        $this->assertEtatIntact($work, ['works/x/un.jpg'], ['works/x/deux.jpg']);
    }

    /**
     * Original absent de son disque : cherche au meme chemin sur `originaux` (commande
     * interrompue), sinon avertissement et suppression autorisee.
     */
    public function test_a_missing_original_is_looked_up_then_logged(): void
    {
        Log::spy();
        $work = Work::factory()->create();
        $interrompu = $this->media($work, 'public', 'works/x/interrompu.jpg');
        Storage::disk('originaux')->put('works/x/interrompu.jpg', 'octets');
        Storage::disk('public')->delete('works/x/interrompu.jpg');
        $perdu = $this->media($work, 'originaux', 'works/x/perdu.jpg', position: 1);
        Storage::disk('originaux')->delete('works/x/perdu.jpg');

        app(CorbeilleMedias::class)->supprimerMedia($interrompu);
        app(CorbeilleMedias::class)->supprimerMedia($perdu);

        $this->assertSame('octets', Storage::disk('originaux')->get('corbeille/works/x/interrompu.jpg'));
        $this->assertNull($perdu->fresh());
        $this->assertSame([], Storage::disk('public')->allFiles());
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message): bool => str_contains($message, 'originaux:works/x/perdu.jpg introuvable'),
        );
    }

    /**
     * @param  array<int, string>  $prives
     * @param  array<int, string>  $publics
     */
    private function assertEtatIntact(Work $work, array $prives = ['works/x/un.jpg', 'works/x/deux.jpg'], array $publics = []): void
    {
        $this->assertNotNull(Work::find($work->id));
        $this->assertSame(2, WorkMedia::where('work_id', $work->id)->count());
        Storage::disk('originaux')->assertExists($prives);
        Storage::disk('public')->assertExists($publics);
        $this->assertSame([], Storage::disk('originaux')->allFiles('corbeille'));

        foreach ([...$prives, ...$publics] as $original) {
            $prefixe = substr($original, 0, (int) strrpos($original, '.'));
            Storage::disk('public')->assertExists(["{$prefixe}-400.webp", "{$prefixe}-400.avif"]);
        }
    }

    private function assertEchec(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('La suppression aurait du echouer.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    /** Original sur `$disque`, derivees 400 sur `public`. */
    private function media(Work $work, string $disque, string $original, MediaRole $role = MediaRole::Principal, int $position = 0): WorkMedia
    {
        Storage::disk($disque)->put($original, "octets de {$original}");
        $prefixe = str_contains(basename($original), '.') ? substr($original, 0, (int) strrpos($original, '.')) : $original;

        foreach (['webp', 'avif'] as $format) {
            Storage::disk('public')->put("{$prefixe}-400.{$format}", 'derivee');
        }

        return $work->media()->create([
            'role' => $role,
            'disk' => 'public',
            'disk_original' => $disque,
            'path_original' => $original,
            'path_web' => $prefixe,
            'width' => 1200,
            'height' => 900,
            'mime' => 'image/jpeg',
            'lqip_hex' => '#c1c0c9',
            'position' => $position,
        ]);
    }
}
