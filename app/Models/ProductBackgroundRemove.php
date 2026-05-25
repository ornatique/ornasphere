<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBackgroundRemove extends Model
{
    protected $fillable = [
        'title',
        'original_image',
        'removed_image',
        'thumbnail_image',
        'status',
        'error_message',
        'created_by',
    ];

    protected $casts = [
        'original_image' => 'array',
        'removed_image' => 'array',
    ];
}
