<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RedditPost extends Model
{
    protected $fillable = [
        'title', 'author', 'upvotes', 'url', 'images'
    ];

    protected $casts = [
        'images' => 'array',
    ];
}
