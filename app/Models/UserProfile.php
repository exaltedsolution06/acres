<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class UserProfile extends Model
{
    protected $guarded = [];

    protected $casts = ['preferred_locations' => 'array'];

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('uploads')->url($this->photo_path) : null;
    }
}
