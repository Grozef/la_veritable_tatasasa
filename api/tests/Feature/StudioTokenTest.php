<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class StudioTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_issues_a_working_token_for_valid_credentials(): void
    {
        User::factory()->create(['email' => 'atelier@tatasasa.test', 'password' => 'aquarelle']);

        $jeton = $this->postJson('/api/studio/token', [
            'email' => 'atelier@tatasasa.test',
            'password' => 'aquarelle',
            'device_name' => 'telephone atelier',
        ])->assertCreated()->json('data.token');

        // Un jeton qui n'ouvre pas les routes d'atelier ne vaut rien : on le rejoue.
        $this->withHeader('Authorization', "Bearer {$jeton}")
            ->getJson('/api/studio/works')
            ->assertOk();
    }

    public function test_rejects_a_wrong_password_without_revealing_that_the_account_exists(): void
    {
        User::factory()->create(['email' => 'atelier@tatasasa.test', 'password' => 'aquarelle']);

        $connu = $this->postJson('/api/studio/token', [
            'email' => 'atelier@tatasasa.test',
            'password' => 'gouache',
            'device_name' => 'telephone atelier',
        ])->assertUnprocessable();

        $inconnu = $this->postJson('/api/studio/token', [
            'email' => 'inconnu@tatasasa.test',
            'password' => 'gouache',
            'device_name' => 'telephone atelier',
        ])->assertUnprocessable();

        $this->assertSame($connu->json('errors'), $inconnu->json('errors'));
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    /** Reconnecter le meme appareil ne doit pas laisser derriere lui une trainee de jetons vivants. */
    public function test_reconnecting_the_same_device_replaces_its_token_and_spares_the_others(): void
    {
        $user = User::factory()->create(['email' => 'atelier@tatasasa.test', 'password' => 'aquarelle']);
        $autre = $user->createToken('tablette')->plainTextToken;

        $premier = $this->postJson('/api/studio/token', [
            'email' => 'atelier@tatasasa.test',
            'password' => 'aquarelle',
            'device_name' => 'telephone atelier',
        ])->json('data.token');

        $this->postJson('/api/studio/token', [
            'email' => 'atelier@tatasasa.test',
            'password' => 'aquarelle',
            'device_name' => 'telephone atelier',
        ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$premier}")
            ->getJson('/api/studio/works')
            ->assertUnauthorized();

        $this->withHeader('Authorization', "Bearer {$autre}")
            ->getJson('/api/studio/works')
            ->assertOk();
    }

    public function test_logging_out_revokes_only_the_token_that_asked(): void
    {
        $user = User::factory()->create();
        $telephone = $user->createToken('telephone atelier')->plainTextToken;
        $tablette = $user->createToken('tablette')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$telephone}")
            ->deleteJson('/api/studio/token')
            ->assertNoContent();

        /*
         * `RequestGuard` met en cache l'utilisateur qu'il vient de resoudre, et le
         * gestionnaire d'authentification survit d'une requete de test a l'autre dans le
         * meme processus : sans cet oubli, le jeton supprime continue d'ouvrir les routes
         * et le test passerait au vert sur une revocation qui n'a rien revoque.
         */
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$telephone}")
            ->getJson('/api/studio/works')
            ->assertUnauthorized();

        $this->withHeader('Authorization', "Bearer {$tablette}")
            ->getJson('/api/studio/works')
            ->assertOk();
    }
}
