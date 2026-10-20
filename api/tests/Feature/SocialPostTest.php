<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SocialPost;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_traces_a_share_without_publishing_anything(): void
    {
        $work = Work::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/studio/works/{$work->ulid}/social-posts", [
                'network' => 'instagram',
                'caption' => 'Sur le motif, hiver 2024.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.network', 'instagram')
            ->assertJsonPath('data.caption', 'Sur le motif, hiver 2024.')
            // La feuille de partage native ne rend aucun identifiant de publication :
            // pretendre le contraire rendrait la colonne inutilisable le jour ou l'API
            // Graph la remplira vraiment.
            ->assertJsonPath('data.externalId', null);

        $this->assertSame(1, $work->socialPosts()->count());
    }

    public function test_rejects_an_unknown_network_and_anonymous_callers(): void
    {
        $work = Work::factory()->create();

        $this->postJson("/api/studio/works/{$work->ulid}/social-posts", ['network' => 'instagram'])
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/studio/works/{$work->ulid}/social-posts", ['network' => 'mastodon'])
            ->assertUnprocessable();

        $this->assertSame(0, SocialPost::query()->count());
    }

    /**
     * Un partage fait dans un atelier sans reseau se trace au retour, avec SA date et non
     * celle de l'envoi — sinon l'historique ment sur l'ordre des publications.
     */
    public function test_keeps_the_date_of_the_share_and_not_the_date_of_the_call(): void
    {
        $work = Work::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/studio/works/{$work->ulid}/social-posts", [
                'network' => 'whatsapp',
                'posted_at' => '2026-08-01T09:30:00Z',
            ])
            ->assertCreated();

        $this->assertSame('2026-08-01 09:30:00', $work->socialPosts()->sole()->posted_at->toDateTimeString());
    }

    /** L'atelier voit l'historique ; le portfolio public ne doit meme pas avoir la cle. */
    public function test_history_is_exposed_to_the_studio_and_absent_from_public_routes(): void
    {
        $work = Work::factory()->published()->create();
        $work->socialPosts()->create(['network' => 'facebook', 'posted_at' => now()->subDay()]);
        $work->socialPosts()->create(['network' => 'instagram', 'posted_at' => now()]);

        $atelier = $this->actingAs(User::factory()->create())
            ->getJson('/api/studio/works')
            ->assertOk();

        // Du plus recent au plus ancien.
        $this->assertSame(['instagram', 'facebook'], $atelier->json('data.0.socialPosts.*.network'));

        $this->getJson('/api/works')
            ->assertOk()
            ->assertJsonMissingPath('data.0.socialPosts');

        $this->getJson("/api/works/{$work->slug}")
            ->assertOk()
            ->assertJsonMissingPath('data.socialPosts');
    }
}
