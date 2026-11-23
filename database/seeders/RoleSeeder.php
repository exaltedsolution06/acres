<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['owner' => 'Owner', 'tenant' => 'Tenant', 'buyer' => 'Buyer', 'broker' => 'Broker'] as $slug => $name) {
            Role::updateOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true]);
        }
    }
}
