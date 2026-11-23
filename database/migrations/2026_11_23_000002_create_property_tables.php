<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $t) {
            $t->id();
            $t->string('public_id', 10)->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('posted_by', 10); // owner | broker
            $t->foreignId('category_id')->constrained('property_categories')->restrictOnDelete();
            $t->string('purpose', 10);   // sell | rent | lease | pg
            $t->string('title', 150);
            $t->string('slug', 200);
            $t->text('description');
            $t->string('ownership_type', 20)->nullable();
            $t->string('availability', 20)->default('ready_to_move');
            $t->date('available_from')->nullable();
            $t->string('tenant_preference', 10)->nullable();

            // Pricing. list_price = price for sell, monthly_rent otherwise (denormalised for filtering and sorting).
            $t->unsignedBigInteger('price')->nullable();
            $t->unsignedInteger('monthly_rent')->nullable();
            $t->unsignedBigInteger('security_deposit')->nullable();
            $t->unsignedInteger('maintenance_charges')->nullable();
            $t->boolean('price_negotiable')->default(false);
            $t->unsignedInteger('price_per_sqft')->nullable();
            $t->unsignedBigInteger('list_price')->nullable()->index();

            // Specifications
            $t->unsignedTinyInteger('bhk')->nullable();
            $t->unsignedTinyInteger('bedrooms')->nullable();
            $t->unsignedTinyInteger('bathrooms')->nullable();
            $t->unsignedTinyInteger('balconies')->nullable();
            $t->unsignedInteger('carpet_area')->nullable();
            $t->unsignedInteger('built_up_area')->nullable();
            $t->unsignedInteger('super_built_up_area')->nullable();
            $t->string('furnishing', 20)->nullable();
            $t->smallInteger('floor_number')->nullable();
            $t->unsignedSmallInteger('total_floors')->nullable();
            $t->unsignedTinyInteger('property_age')->nullable(); // years, 0 = new
            $t->string('facing', 20)->nullable();
            $t->string('parking', 20)->nullable();
            $t->string('water_supply', 30)->nullable();
            $t->string('power_backup', 20)->nullable();

            // Location (full address and coordinates are never shown publicly)
            $t->foreignId('city_id')->constrained('locations')->restrictOnDelete();
            $t->foreignId('locality_id')->constrained('locations')->restrictOnDelete();
            $t->string('sub_locality', 100)->nullable();
            $t->string('pin_code', 6)->nullable()->index();
            $t->string('address', 300)->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();

            // Lifecycle
            $t->string('status', 20)->default('draft');
            $t->text('rejection_reason')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->unsignedTinyInteger('priority')->default(0); // snapshot of the poster's search_priority feature
            $t->unsignedInteger('views_count')->default(0);
            $t->char('fingerprint', 40)->nullable()->index();
            $t->unsignedBigInteger('duplicate_of_id')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->index(['status', 'purpose', 'city_id']);
            $t->index(['status', 'purpose', 'locality_id']);
            $t->index(['user_id', 'status']);
            $t->index('expires_at');
        });

        Schema::create('property_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->string('type', 12)->default('photo'); // photo | floor_plan
            $t->string('path');
            $t->string('thumb_path')->nullable();
            $t->string('alt', 160)->nullable();
            $t->boolean('is_cover')->default(false);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('property_videos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->unsignedInteger('size_kb')->default(0);
            $t->timestamps();
        });

        Schema::create('property_amenity', function (Blueprint $t) {
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $t->primary(['property_id', 'amenity_id']);
        });

        Schema::create('property_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->string('actor_type', 10); // user | admin | system
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('note', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_status_histories');
        Schema::dropIfExists('property_amenity');
        Schema::dropIfExists('property_videos');
        Schema::dropIfExists('property_images');
        Schema::dropIfExists('properties');
    }
};
