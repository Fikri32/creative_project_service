<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $fillable = [
        'module_id',
        'title',
        'url_video',
        'duration'
    ];

}