<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PropertyVideo extends Model
{
    protected $guarded = [];

    public function url(): string
    {
        return Storage::disk('uploads')->url($this->path);
    }
}
