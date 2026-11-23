<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PropertyImage extends Model
{
    protected $guarded = [];

    protected $casts = ['is_cover' => 'boolean'];

    public function url(): string
    {
        return Storage::disk('uploads')->url($this->path);
    }

    public function thumbUrl(): string
    {
        return Storage::disk('uploads')->url($this->thumb_path ?: $this->path);
    }
}
