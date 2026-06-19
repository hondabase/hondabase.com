<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleLinkClick extends Model
{
    protected $guarded = [];

    protected $casts = [
        'click_count' => 'int',
        'last_clicked_at' => 'datetime',
    ];
}
