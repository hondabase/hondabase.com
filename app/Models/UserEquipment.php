<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEquipment extends Model
{
    protected $table = 'user_equipment';

    protected $guarded = [];

    public const KINDS = [
        'ecu'      => 'ECU',
        'wideband' => 'Wideband',
        'software' => 'Software',
        'tool'     => 'Tool',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst($this->kind);
    }
}
