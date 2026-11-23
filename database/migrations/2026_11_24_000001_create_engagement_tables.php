<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One enquiry (lead) per seeker and property. It records interest and/or a message.
        Schema::create('enquiries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('to_user_id')->constrained('users')->cascadeOnDelete(); // the poster
            $t->timestamp('interest_at')->nullable();
            $t->text('message')->nullable();
            $t->timestamp('enquired_at')->nullable();
            $t->string('status', 20)->default('new');
            $t->timestamp('closed_at')->nullable();
            $t->timestamp('follow_up_at')->nullable();
            $t->string('follow_up_note', 300)->nullable();
            $t->timestamp('last_activity_at')->nullable();
            $t->timestamps();
            $t->unique(['property_id', 'from_user_id']);
            $t->index(['to_user_id', 'status']);
            $t->index('follow_up_at');
        });

        // Internal notes, visible to the poster only.
        Schema::create('enquiry_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->timestamps();
        });

        Schema::create('site_visits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $t->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $t->timestamp('scheduled_at');
            $t->string('status', 20)->default('requested'); // requested | confirmed | declined | completed | cancelled
            $t->string('note', 300)->nullable();
            $t->timestamps();
        });

        Schema::create('requirements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 10); // tenant | buyer: the role it was posted under
            $t->string('purpose', 10);
            $t->foreignId('category_id')->nullable()->constrained('property_categories')->nullOnDelete();
            $t->foreignId('city_id')->constrained('locations')->restrictOnDelete();
            $t->foreignId('locality_id')->nullable()->constrained('locations')->nullOnDelete();
            $t->unsignedBigInteger('budget_min')->nullable();
            $t->unsignedBigInteger('budget_max')->nullable();
            $t->unsignedTinyInteger('bhk')->nullable();
            $t->string('furnishing', 20)->nullable();
            $t->string('title', 150);
            $t->text('description')->nullable();
            $t->string('status', 10)->default('active'); // active | closed | expired
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->index(['status', 'purpose', 'city_id']);
        });

        Schema::create('requirement_responses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $t->text('message');
            $t->timestamps();
            $t->unique(['requirement_id', 'user_id']);
        });

        Schema::create('requirement_matches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['requirement_id', 'property_id']);
        });

        Schema::create('saved_properties', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'property_id']);
        });

        Schema::create('saved_searches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->json('filters');
            $t->timestamp('last_checked_at');
            $t->timestamps();
        });

        Schema::create('notification_settings', function (Blueprint $t) {
            $t->id();
            $t->string('event', 60)->unique();
            $t->boolean('in_app')->default(true);
            $t->boolean('email')->default(true);
            $t->timestamps();
        });

        // Laravel's standard notifications table.
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'notification_settings', 'saved_searches', 'saved_properties', 'requirement_matches', 'requirement_responses', 'requirements', 'site_visits', 'enquiry_notes', 'enquiries'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
