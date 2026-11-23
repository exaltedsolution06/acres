<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // State > city > locality
        Schema::create('locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('locations')->restrictOnDelete();
            $t->string('type', 10); // state | city | locality
            $t->string('name', 100);
            $t->string('slug', 120);
            $t->string('seo_slug', 160)->nullable()->unique(); // city: "kolkata", locality: "salt-lake-kolkata"
            $t->string('pin_code', 6)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['type', 'parent_id']);
        });

        Schema::create('property_categories', function (Blueprint $t) {
            $t->id();
            $t->string('group', 20); // residential | commercial
            $t->string('name', 80);
            $t->string('slug', 100)->unique();
            $t->boolean('has_bhk')->default(false);
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('amenities', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('slug', 100)->unique();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('property_categories');
        Schema::dropIfExists('locations');
    }
};
