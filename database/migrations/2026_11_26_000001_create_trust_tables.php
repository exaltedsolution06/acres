<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type', 24); // owner | broker | property_documents
            $t->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('status', 12)->default('pending'); // pending | approved | rejected | revoked | expired
            $t->text('applicant_note')->nullable();
            $t->unsignedBigInteger('reviewed_by')->nullable(); // admin id
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->string('reject_reason', 500)->nullable();
            $t->timestamps();
            $t->index(['status', 'type']);
            $t->index(['user_id', 'type', 'status']);
        });

        // Files live on the private_documents disk (storage/app/private/documents), never under public/uploads.
        Schema::create('verification_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('verification_request_id')->constrained()->cascadeOnDelete();
            $t->string('doc_type', 30);
            $t->string('path');
            $t->string('original_name', 120);
            $t->string('mime', 60);
            $t->unsignedInteger('size_kb')->default(0);
            $t->timestamps();
        });

        Schema::create('property_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $t->string('reason', 24);
            $t->text('details')->nullable();
            $t->string('status', 12)->default('open'); // open | actioned | dismissed
            $t->string('admin_note', 500)->nullable();
            $t->unsignedBigInteger('handled_by')->nullable();
            $t->timestamp('handled_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'property_id']);
            $t->index(['reporter_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('owner_verified_until')->nullable();
            $t->timestamp('broker_verified_until')->nullable();
        });

        Schema::table('properties', function (Blueprint $t) {
            $t->timestamp('documents_reviewed_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('properties', fn (Blueprint $t) => $t->dropColumn('documents_reviewed_until'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['owner_verified_until', 'broker_verified_until']));
        Schema::dropIfExists('property_reports');
        Schema::dropIfExists('verification_documents');
        Schema::dropIfExists('verification_requests');
    }
};
