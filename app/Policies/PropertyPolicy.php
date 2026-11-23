<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    /** Only the account that created the listing may manage it. */
    public function manage(User $user, Property $property): bool
    {
        return $property->user_id === $user->id;
    }
}
