<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Tutorial extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'tutorials';

    protected $fillable = [
        'title',
        'category',
        'description',
        'thumbnail',
        'thumbnail_public_id',
        'published',
        'published_date',
    ];

    protected $casts = [
        'published' => 'boolean',
        'published_date' => 'datetime',
        'category' => 'array',
    ];
}