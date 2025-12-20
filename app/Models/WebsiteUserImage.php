<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebsiteUserImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'image_file_path',
        'is_deleted',
    ];

    public function website()
    {
        return $this->belongsTo(Website::class);
    }
}
