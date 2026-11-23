<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('mobile', 15)->nullable()->unique();
            $t->timestamp('mobile_verified_at')->nullable();
            $t->string('status', 20)->default('active')->index(); // active | restricted | suspended
            $t->unsignedBigInteger('active_role_id')->nullable()->index();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['mobile']);
            $t->dropIndex(['status']);
            $t->dropIndex(['active_role_id']);
            $t->dropColumn(['mobile', 'mobile_verified_at', 'status', 'active_role_id', 'deleted_at']);
        });
    }
};
