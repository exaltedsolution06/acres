<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'marketplace:create-admin {email} {--name=Administrator} {--staff : Create a staff account instead of a super admin}';

    protected $description = 'Create an admin account (password is prompted, never stored in seeders)';

    public function handle(): int
    {
        $password = $this->secret('Password (min 10 characters)');

        if (strlen((string) $password) < 10) {
            $this->error('Password must be at least 10 characters.');

            return self::FAILURE;
        }

        Admin::updateOrCreate(['email' => $this->argument('email')], [
            'name' => $this->option('name'),
            'password' => $password,
            'role' => $this->option('staff') ? 'staff' : 'super_admin',
            'is_active' => true,
        ]);

        $this->info('Admin saved. Log in at /admin/login');

        return self::SUCCESS;
    }
}
