<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin and staff accounts are separate from marketplace users (PRD section 2).
        Schema::create('admins', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('email')->unique();
            $t->string('password');
            $t->string('role', 20)->default('staff'); // super_admin | staff
            $t->boolean('is_active')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('admin_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('admin_id')->nullable()->index();
            $t->string('action', 60)->index();
            $t->string('subject_type', 40)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('admins');
    }
};
