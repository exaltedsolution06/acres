<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // All money is stored in paise (integers) to avoid floating-point errors.
        Schema::create('credit_packs', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->unsignedInteger('credits');
            $t->unsignedInteger('price'); // rupees, excluding GST
            $t->decimal('gst_percent', 5, 2)->default(18);
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('promotion_packages', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->unsignedSmallInteger('duration_days');
            $t->unsignedInteger('price'); // rupees, excluding GST
            $t->decimal('gst_percent', 5, 2)->default(18);
            $t->boolean('show_on_homepage')->default(false);
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('type', 10); // percent | fixed
            $t->unsignedInteger('value'); // percent (1-100) or rupees
            $t->unsignedInteger('max_discount')->nullable(); // rupees cap for percent coupons
            $t->unsignedInteger('min_amount')->nullable();   // rupees, before GST
            $t->string('applies_to', 20)->default('all');    // all | subscription | credit_pack | promotion
            $t->unsignedInteger('usage_limit')->nullable();
            $t->unsignedInteger('per_user_limit')->default(1);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->string('txn_id', 32)->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('purpose', 20); // subscription | credit_pack | promotion
            $t->unsignedBigInteger('item_id');
            $t->unsignedBigInteger('property_id')->nullable();
            $t->string('description', 200);
            $t->unsignedBigInteger('subtotal_paise');
            $t->unsignedBigInteger('discount_paise')->default(0);
            $t->unsignedBigInteger('tax_paise')->default(0);
            $t->unsignedBigInteger('total_paise');
            $t->decimal('gst_percent', 5, 2)->default(18);
            $t->char('currency', 3)->default('INR');
            $t->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 12)->default('created'); // created | paid | failed | refunded
            $t->string('gateway', 20);
            $t->string('gateway_order_id')->nullable()->unique();
            $t->string('gateway_payment_id')->nullable()->unique();
            $t->json('billing')->nullable();
            $t->json('meta')->nullable();
            $t->string('failure_reason', 300)->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('fulfilled_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'status']);
            $t->index(['status', 'purpose', 'paid_at']);
        });

        Schema::table('subscriptions', function (Blueprint $t) {
            $t->timestamp('reminded_at')->nullable();
        });

        Schema::create('payment_gateway_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $t->string('gateway', 20);
            $t->string('event', 60);
            $t->json('payload')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('coupon_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->string('type', 12)->default('invoice'); // invoice | credit_note
            $t->string('number', 40)->unique();
            $t->unsignedBigInteger('related_invoice_id')->nullable();
            $t->timestamp('issued_at');
            $t->json('billing');
            $t->json('lines');
            $t->unsignedBigInteger('subtotal_paise');
            $t->unsignedBigInteger('discount_paise')->default(0);
            $t->unsignedBigInteger('cgst_paise')->default(0);
            $t->unsignedBigInteger('sgst_paise')->default(0);
            $t->unsignedBigInteger('igst_paise')->default(0);
            $t->unsignedBigInteger('total_paise');
            $t->timestamps();
            $t->index(['payment_id', 'type']);
        });

        Schema::create('invoice_counters', function (Blueprint $t) {
            $t->id();
            $t->string('series', 20)->unique(); // e.g. INV-2026-27
            $t->unsignedInteger('last_number')->default(0);
            $t->timestamps();
        });

        Schema::create('refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedBigInteger('amount_paise');
            $t->string('reason', 500);
            $t->string('status', 12)->default('requested'); // requested | rejected | processed | failed
            $t->string('admin_note', 500)->nullable();
            $t->string('gateway_refund_id')->nullable()->index();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('promotions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('promotion_package_id')->constrained()->restrictOnDelete();
            $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->boolean('homepage')->default(false);
            $t->string('status', 10)->default('active'); // active | refunded
            $t->timestamps();
            $t->index(['property_id', 'status']);
        });

        Schema::table('properties', function (Blueprint $t) {
            $t->timestamp('featured_until')->nullable()->index();
            $t->timestamp('homepage_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('properties', fn (Blueprint $t) => $t->dropColumn(['featured_until', 'homepage_until']));
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('invoice_counters');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('payment_gateway_logs');
        Schema::table('subscriptions', fn (Blueprint $t) => $t->dropColumn('reminded_at'));
        Schema::dropIfExists('payments');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('promotion_packages');
        Schema::dropIfExists('credit_packs');
    }
};
