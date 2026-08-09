<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A private working copy of a not-yet-submitted article. */
class ArticleDraft extends Model
{
    protected $guarded = [];

    protected $casts = [
        'assets' => 'array',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (ArticleDraft $draft) => $draft->cleanupAssets());
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assetDirectory(): string
    {
        return rtrim((string) config('hondabase.draft_asset_path'), '/').'/'.$this->id;
    }

    public function assetPath(string $file): ?string
    {
        $file = basename($file);
        if (! in_array($file, $this->assets ?? [], true)) {
            return null;
        }

        $path = $this->assetDirectory().'/'.$file;

        return is_file($path) ? $path : null;
    }

    public function cleanupAssets(): void
    {
        $dir = $this->assetDirectory();
        if (! is_dir($dir)) {
            return;
        }

        foreach ((array) glob($dir.'/*') as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}
