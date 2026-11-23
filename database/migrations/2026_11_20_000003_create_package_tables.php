<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Feature registry: one row per feature key (section 9 of the PRD).
        Schema::create('package_features', function (Blueprint $t) {
            $t->id();
            $t->string('key', 60)->unique();
            $t->string('name', 120);
            $t->text('description')->nullable();
            $t->string('type', 20); // boolean | limit | enum
            $t->string('default_value')->nullable();
            $t->boolean('is_daily')->default(false);
            $t->json('options')->nullable(); // allowed values for enum features
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('packages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('role_id')->constrained()->restrictOnDelete(); // package category
            $t->string('name', 120);
            $t->string('slug', 140)->unique();
            $t->text('description')->nullable();
            $t->decimal('price', 10, 2)->default(0);
            $t->decimal('discounted_price', 10, 2)->nullable();
            $t->decimal('gst_percent', 5, 2)->default(18);
            $t->unsignedInteger('validity_days')->nullable(); // null = never expires (free)
            $t->unsignedInteger('grace_days')->default(0);
            $t->unsignedInteger('trial_days')->default(0);
            $t->decimal('renewal_discount_percent', 5, 2)->default(0);
            $t->boolean('auto_renew')->default(false);
            $t->boolean('refund_eligible')->default(false);
            $t->boolean('is_free')->default(false);
            $t->boolean('is_default')->default(false); // fallback package when a user has no active subscription
            $t->boolean('is_recommended')->default(false);
            $t->string('color', 20)->nullable();
            $t->unsignedInteger('display_order')->default(0);
            $t->text('terms')->nullable();
            $t->json('rules')->nullable(); // upgrade / downgrade rules
            $t->string('status', 20)->default('active')->index();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('package_feature_values', function (Blueprint $t) {
            $t->id();
            $t->foreignId('package_id')->constrained()->cascadeOnDelete();
            $t->foreignId('feature_id')->constrained('package_features')->cascadeOnDelete();
            $t->string('value')->nullable();
            $t->boolean('is_unlimited')->default(false);
            $t->timestamps();
            $t->unique(['package_id', 'feature_id']);
        });

        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('package_id')->constrained()->restrictOnDelete();
            $t->foreignId('role_id')->constrained()->restrictOnDelete();
            $t->string('status', 20)->default('active'); // active | expired | cancelled
            $t->timestamp('starts_at');
            $t->timestamp('ends_at')->nullable();
            $t->timestamp('grace_ends_at')->nullable();
            $t->decimal('price_paid', 10, 2)->default(0);
            $t->boolean('auto_renew')->default(false);
            $t->unsignedBigInteger('payment_id')->nullable()->index(); // FK added with payments in Milestone 5
            $t->string('source', 30)->default('purchase'); // purchase | admin | trial | renewal
            $t->timestamps();
            $t->index(['user_id', 'role_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('package_feature_values');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('package_features');
    }
};
