<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Permanent JE/AE/EE mapping with history. A change closes the current row
        // (sets effective_to) and inserts a new one; rows are never overwritten.
        Schema::create('responsibility_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type', 20); // road_section | asset
            $table->unsignedBigInteger('scope_id');
            $table->string('role_code', 20); // JE | AE | EE
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable(); // null = open-ended
            $table->string('remarks')->nullable();
            $table->boolean('is_test')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['scope_type', 'scope_id', 'role_code', 'effective_from'], 'resp_scope_role_idx');
            $table->index(['user_id', 'role_code', 'effective_to'], 'resp_user_idx');
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_uuid');
            $table->string('platform', 20)->default('android');
            $table->string('model', 100)->nullable();
            $table->string('os_version', 50)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->text('push_token')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'device_uuid']);
        });

        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 15)->index();
            $table->string('purpose', 30); // register | login | reset_password
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('responsibility_assignments');
    }
};
