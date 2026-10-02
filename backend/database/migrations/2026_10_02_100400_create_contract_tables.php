<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('registration_no', 50)->nullable()->unique();
            $table->string('pan', 10)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('contact_person', 100)->nullable();
            $table->string('mobile', 15)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('status', 20)->default('active')->index(); // active | suspended | blacklisted | inactive
            $table->boolean('is_test')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('contractor_id')->references('id')->on('contractors')->nullOnDelete();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_no', 50)->unique();
            $table->string('name')->nullable();
            $table->foreignId('contractor_id')->constrained()->restrictOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agreement_no', 50)->nullable();
            $table->date('agreement_date')->nullable();
            $table->date('work_order_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            // Contractor maintenance responsibility is decided ONLY by these two dates.
            $table->date('maintenance_start_date')->nullable();
            $table->date('maintenance_end_date')->nullable();
            $table->decimal('contract_value', 15, 2)->nullable();
            $table->string('status', 20)->default('active')->index(); // draft | active | completed | terminated
            $table->text('remarks')->nullable();
            $table->boolean('is_test')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['maintenance_start_date', 'maintenance_end_date']);
        });

        Schema::create('contract_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('disk', 30);
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Which part of which road a contract covers, and when.
        // Rule: no overlapping chainage on the same road for overlapping effective dates
        // (enforced in ContractMappingService inside a locking transaction).
        Schema::create('contract_road_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->foreignId('road_id')->constrained()->restrictOnDelete();
            $table->foreignId('road_section_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('start_chainage_m');
            $table->unsignedInteger('end_chainage_m');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('active')->index(); // active | ended | cancelled
            $table->string('remarks')->nullable();
            $table->boolean('is_test')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['road_id', 'start_chainage_m', 'end_chainage_m'], 'crs_road_chainage_idx');
            $table->index(['road_section_id', 'effective_from', 'effective_to'], 'crs_section_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_road_sections');
        Schema::dropIfExists('contract_documents');
        Schema::dropIfExists('contracts');
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['contractor_id']));
        Schema::dropIfExists('contractors');
    }
};
