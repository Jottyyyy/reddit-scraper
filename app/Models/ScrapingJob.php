<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScrapingJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'reddit_link',
        'filters',
        'google_drive_link',
        'status',
        'error_message',
        'file_size',
        'content_type',
        'folder_name',
        'uploaded_by',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'filters' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];
}
