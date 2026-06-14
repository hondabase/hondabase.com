<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'email', 'password', 'discord_id', 'discord_username', 'discord_global_name',
    'avatar', 'github_id', 'github_login', 'is_staff', 'is_legacy_author', 'legacy_source',
    'legacy_handle', 'legacy_key',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_staff' => 'bool',
            'is_legacy_author' => 'bool',
        ];
    }

    public function follows(): HasMany
    {
        return $this->hasMany(Follow::class);
    }

    public function articleAuthorships(): HasMany
    {
        return $this->hasMany(ArticleAuthor::class);
    }

    public function authorAliases(): HasMany
    {
        return $this->hasMany(AuthorAlias::class);
    }

    public function isOwner(): bool
    {
        return (string) $this->discord_id === (string) config('services.discord.owner_id')
            && config('services.discord.owner_id') !== null;
    }

    /** Staff manage articles (review edits + auto-apply their own). The owner is always staff. */
    public function isStaff(): bool
    {
        return $this->is_staff === true || $this->isOwner();
    }

    /**
     * The ['name','email'] used to attribute this user in a git commit trailer. A user who
     * linked GitHub gets their real GitHub no-reply address (so the commit shows up on their
     * GitHub profile); otherwise a stable synthetic address derived from their Discord id, so
     * attribution is still unique and traceable without leaking any PII.
     */
    public function gitIdentity(): array
    {
        if ($this->github_login && $this->github_id) {
            return [
                'name'  => $this->github_login,
                'email' => "{$this->github_id}+{$this->github_login}@" . config('hondabase.git.noreply_domain'),
            ];
        }

        $handle = $this->discord_username ?: ($this->name ?: 'user' . $this->id);
        $local  = ($this->discord_id ?: $this->id) . '+' . \Illuminate\Support\Str::slug($handle);

        return [
            'name'  => $handle,
            'email' => $local . '@' . config('hondabase.git.synthetic_domain'),
        ];
    }

    /** Short display name for the commit subject "(by …)". */
    public function displayName(): string
    {
        if ($this->is_legacy_author) {
            return $this->legacy_handle ?: $this->name;
        }

        if ($this->discord_username) {
            $username = '@' . ltrim($this->discord_username, '@');
            return $this->discord_global_name
                ? "{$this->discord_global_name} ({$username})"
                : $username;
        }

        return $this->name ?: ($this->github_login ?: 'user' . $this->id);
    }

    public function avatarUrl(): string
    {
        if ($this->avatar && str_starts_with($this->avatar, 'http')) {
            return $this->avatar;
        }
        if ($this->avatar) {
            return "https://cdn.discordapp.com/avatars/{$this->discord_id}/{$this->avatar}.png?size=64";
        }
        return 'https://cdn.discordapp.com/embed/avatars/0.png';
    }
}
