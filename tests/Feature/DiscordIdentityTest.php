<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class DiscordIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_stores_current_discord_global_name_and_username(): void
    {
        config([
            'services.discord.guild_id' => null,
            'services.hondabase.sso_secret' => 'test-secret',
        ]);

        User::create([
            'name' => 'Old Display',
            'discord_id' => '1234',
            'discord_username' => 'old_username',
            'discord_global_name' => 'Old Display',
        ]);

        $discord = (new SocialiteUser())
            ->setRaw(['id' => '1234', 'username' => 'stable_username', 'global_name' => 'Current Display'])
            ->map(['id' => '1234', 'name' => 'stable_username', 'avatar' => 'https://example.test/avatar.png'])
            ->setToken('token');
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($discord);
        Socialite::shouldReceive('driver')->once()->with('discord')->andReturn($provider);

        $this->get('/auth/callback')->assertRedirect('/');

        $user = User::sole();
        $this->assertSame(1, User::count());
        $this->assertSame('Current Display', $user->discord_global_name);
        $this->assertSame('stable_username', $user->discord_username);
        $this->assertSame('Current Display (@stable_username)', $user->displayName());
        $this->assertAuthenticatedAs($user);
    }
}
