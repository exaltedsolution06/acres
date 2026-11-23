<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class RoleService
{
    public function assign(User $user, string $slug): void
    {
        $role = Role::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);

        if (! $user->active_role_id) {
            $user->forceFill(['active_role_id' => $role->id])->save();
            $user->unsetRelation('activeRole');
        }
    }

    /** Remove a role. If it was the active one, fall back to another role (or none). */
    public function revoke(User $user, string $slug): void
    {
        $role = Role::where('slug', $slug)->firstOrFail();
        $user->roles()->detach($role->id);

        if ($user->active_role_id === $role->id) {
            $user->forceFill(['active_role_id' => $user->roles()->value('roles.id')])->save();
            $user->unsetRelation('activeRole');
        }
    }

    /** Switch the dashboard role. Usage counters are per account, so nothing is reset here. */
    public function switchTo(User $user, string $slug): void
    {
        $role = $user->roles()->where('slug', $slug)->first();

        if (! $role) {
            throw new AuthorizationException('You are not authorised for this role.');
        }

        $user->forceFill(['active_role_id' => $role->id])->save();
        $user->unsetRelation('activeRole');
    }
}
