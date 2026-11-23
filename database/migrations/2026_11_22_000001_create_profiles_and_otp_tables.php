<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mobile-only (OTP) accounts have no email address.
        Schema::table('users', function (Blueprint $t) {
            $t->string('email')->nullable()->change();
        });

        Schema::create('user_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('city', 80)->nullable();
            $t->json('preferred_locations')->nullable();
            $t->string('photo_path')->nullable();
            $t->string('account_type', 20)->default('individual'); // individual | agency (brokers)
            $t->string('business_name', 120)->nullable();
            $t->unsignedTinyInteger('experience_years')->nullable();
            $t->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $t) {
            $t->id();
            $t->string('mobile', 15)->index();
            $t->string('purpose', 30); // register | login | verify_mobile
            $t->string('code_hash', 64);
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
            $t->index(['mobile', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('user_profiles');
    }
};
