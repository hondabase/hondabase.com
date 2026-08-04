<?php

namespace Tests\Feature;

use App\Models\ManualFavorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualFavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_toggle_returns_manuals_login_return_url(): void
    {
        $this->withHeader('Origin', 'https://manuals.hondabase.com')
            ->postJson('/manuals/favorites', [
                'path' => '/cars/honda/Honda_B16A_Block.pdf',
                'name' => 'Honda_B16A_Block.pdf',
                'url' => 'https://manuals.hondabase.com/cars/honda/Honda_B16A_Block.pdf',
                'return' => 'https://manuals.hondabase.com/cars/honda/',
            ])
            ->assertUnauthorized()
            ->assertHeader('Access-Control-Allow-Origin', 'https://manuals.hondabase.com')
            ->assertJsonPath('authenticated', false)
            ->assertJsonPath('login_url', 'https://www.hondabase.com/auth/login?return=https%3A%2F%2Fmanuals.hondabase.com%2Fcars%2Fhonda%2F');
    }

    public function test_authenticated_user_can_toggle_manual_favorite(): void
    {
        $user = User::factory()->create();

        $payload = [
            'path' => '/cars/honda/Honda_B16A_Block.pdf',
            'name' => 'Honda_B16A_Block.pdf',
            'url' => 'https://manuals.hondabase.com/cars/honda/Honda_B16A_Block.pdf',
            'return' => 'https://manuals.hondabase.com/cars/honda/',
        ];

        $this->actingAs($user)
            ->withHeader('Origin', 'https://manuals.hondabase.com')
            ->postJson('/manuals/favorites', $payload)
            ->assertOk()
            ->assertJsonPath('saved', true);

        $this->assertDatabaseHas('manual_favorites', [
            'user_id' => $user->id,
            'path' => '/cars/honda/Honda_B16A_Block.pdf',
            'name' => 'Honda_B16A_Block.pdf',
        ]);

        $this->actingAs($user)
            ->withHeader('Origin', 'https://manuals.hondabase.com')
            ->getJson('/manuals/favorites?paths%5B%5D=/cars/honda/Honda_B16A_Block.pdf')
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('saved.0', '/cars/honda/Honda_B16A_Block.pdf')
            ->assertJsonPath('user.profile_url', 'https://www.hondabase.com/me')
            ->assertJsonPath('user.favorites_url', 'https://www.hondabase.com/me')
            ->assertJsonPath('user.logout_url', 'https://www.hondabase.com/manuals/logout');

        $this->actingAs($user)
            ->withHeader('Origin', 'https://manuals.hondabase.com')
            ->postJson('/manuals/favorites', $payload)
            ->assertOk()
            ->assertJsonPath('saved', false);

        $this->assertSame(0, ManualFavorite::count());
    }

    public function test_manual_favorite_canonicalizes_space_paths(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('Origin', 'https://manuals.hondabase.com')
            ->postJson('/manuals/favorites', [
                'path' => '/cars/honda/Honda%20HR-V%20ESM.zip',
                'name' => 'Honda HR-V ESM.zip',
                'url' => 'https://manuals.hondabase.com/cars/honda/Honda%20HR-V%20ESM.zip',
                'return' => 'https://manuals.hondabase.com/cars/honda/',
            ])
            ->assertOk()
            ->assertJsonPath('saved', true);

        $this->assertDatabaseHas('manual_favorites', [
            'user_id' => $user->id,
            'path' => '/cars/honda/Honda HR-V ESM.zip',
            'url' => 'https://manuals.hondabase.com/cars/honda/Honda%20HR-V%20ESM.zip',
        ]);

        $this->actingAs($user)
            ->withHeader('Origin', 'https://manuals.hondabase.com')
            ->getJson('/manuals/favorites?paths%5B%5D=/cars/honda/Honda%20HR-V%20ESM.zip')
            ->assertOk()
            ->assertJsonPath('saved.0', '/cars/honda/Honda%20HR-V%20ESM.zip');
    }

    public function test_manual_favorite_rejects_non_manual_urls(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/manuals/favorites', [
                'path' => '/cars/honda/Honda_B16A_Block.pdf',
                'name' => 'Honda_B16A_Block.pdf',
                'url' => 'https://evil.example/cars/honda/Honda_B16A_Block.pdf',
                'return' => 'https://manuals.hondabase.com/cars/honda/',
            ])
            ->assertUnprocessable();
    }

    public function test_manuals_logout_clears_authenticated_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('Origin', 'https://manuals.hondabase.com')
            ->postJson('/manuals/logout')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://manuals.hondabase.com')
            ->assertJsonPath('authenticated', false);

        $this->assertGuest();
    }
}
