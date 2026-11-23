<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('role_id')->nullable();
            $t->string('feature_key', 60);
            $t->string('action', 20)->default('consume'); // consume | bonus | reset
            $t->integer('quantity')->default(1);
            $t->string('subject_type', 40)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->date('usage_date');
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'feature_key', 'usage_date']);
            $t->index(['subject_type', 'subject_id']);
        });

        // One row per user + feature + local day. Rows are locked for update when consuming.
        Schema::create('daily_usage_summaries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('feature_key', 60);
            $t->date('usage_date');
            $t->unsignedInteger('used')->default(0);
            $t->unsignedInteger('bonus')->default(0);
            $t->timestamps();
            $t->unique(['user_id', 'feature_key', 'usage_date'], 'daily_usage_unique');
        });

        Schema::create('contact_credits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedInteger('balance')->default(0);
            $t->unsignedInteger('lifetime_purchased')->default(0);
            $t->timestamps();
        });

        Schema::create('contact_credit_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type', 20); // purchase | spend | bonus | adjust | refund
            $t->integer('amount'); // signed
            $t->unsignedInteger('balance_after');
            $t->string('reference')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'type']);
        });

        // Contact access log: one row per granted reveal.
        Schema::create('contact_views', function (Blueprint $t) {
            $t->id();
            $t->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedBigInteger('property_id')->nullable()->index(); // FK added with properties in Milestone 4
            $t->string('mode', 20);
            $t->boolean('charged')->default(false);
            $t->string('charge_source', 20)->nullable(); // daily | credit
            $t->string('ip', 45)->nullable();
            $t->timestamps();
            $t->index(['viewer_id', 'target_user_id', 'charged']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_views');
        Schema::dropIfExists('contact_credit_transactions');
        Schema::dropIfExists('contact_credits');
        Schema::dropIfExists('daily_usage_summaries');
        Schema::dropIfExists('usage_logs');
    }
};
