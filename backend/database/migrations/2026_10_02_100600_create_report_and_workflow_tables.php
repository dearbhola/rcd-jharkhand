<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reports, the data-driven workflow engine, delegation, repair attempts,
 * inspections and evidence. History tables are append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_no', 30)->unique();
            $table->uuid('client_uuid')->unique(); // idempotency key from the device / web form
            $table->string('source', 10)->default('mobile'); // mobile | web
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->string('reporter_role_code', 20);
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();

            // Server-resolved location (never accepted from the client).
            $table->foreignId('road_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('road_section_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_type_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('chainage_m')->nullable();
            $table->decimal('distance_from_road_m', 8, 2)->nullable();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();

            // Raw device GPS.
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('gps_accuracy_m', 8, 2)->nullable();
            $table->boolean('is_mock_location')->default(false);
            $table->timestamp('captured_at_device')->nullable();
            $table->timestamp('gps_fix_at')->nullable();
            $table->timestamp('received_at');
            $table->json('location_flags')->nullable(); // plausibility warnings
            $table->boolean('location_override')->default(false); // authorised test-mode override

            $table->foreignId('issue_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('severity_id')->constrained()->restrictOnDelete();
            $table->text('description')->nullable();

            // Current workflow state, denormalised for fast filtering. Source of truth: workflow_instances.
            $table->string('status', 40)->index();
            $table->timestamp('finalized_at')->nullable(); // evidence complete, workflow started
            $table->timestamp('closed_at')->nullable();
            $table->boolean('is_test')->default(false);
            $table->timestamps();

            $table->index(['is_test', 'status', 'created_at']);
            $table->index(['road_section_id', 'created_at']);
            $table->index(['road_id', 'chainage_m']);
            $table->index(['latitude', 'longitude']);
            $table->index(['reporter_id', 'created_at']);
        });

        // Responsibility resolved for a report. A new row is added when routing changes
        // (e.g. maintenance expired → department flow); earlier rows are kept.
        Schema::create('report_responsibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_road_section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contractor_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('maintenance_active')->default(false);
            $table->foreignId('je_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ae_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('route', 20); // contractor | department
            $table->string('reason', 50); // initial | maintenance_expired | manual
            $table->boolean('is_current')->default(true);
            $table->timestamp('resolved_at');
            $table->timestamps();
            $table->index(['report_id', 'is_current']);
            $table->index(['contractor_id', 'is_current']);
        });

        Schema::create('report_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('linked_report_id')->constrained('reports')->cascadeOnDelete();
            $table->string('link_type', 20); // duplicate_of | related
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['report_id', 'linked_report_id', 'link_type']);
        });

        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40); // CONTRACTOR_MAINTENANCE, DEPARTMENT
            $table->unsignedInteger('version')->default(1);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['code', 'version']);
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40); // PENDING_VALIDATION, ASSIGNED, JE_REVIEW, ...
            $table->string('name');
            $table->string('actor_role_code', 20)->nullable(); // role that acts at this step
            $table->string('sla_stage', 30)->nullable(); // SLA rule stage measured while in this step
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_terminal')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['workflow_definition_id', 'code']);
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->foreignId('to_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->string('action_code', 40); // acknowledge, submit_repair, accept, reject, approve, ...
            $table->string('name');
            $table->json('allowed_role_codes')->nullable(); // null = system/automatic only
            $table->boolean('requires_reason')->default(false);
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('requires_location')->default(false);
            $table->json('guards')->nullable(); // registered guard keys
            $table->json('effects')->nullable(); // registered effect keys
            $table->boolean('is_automatic')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['from_step_id', 'action_code']);
        });

        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_definition_id')->constrained()->restrictOnDelete();
            $table->foreignId('current_step_id')->constrained('workflow_steps')->restrictOnDelete();
            $table->string('status', 20)->default('active'); // active | completed | superseded
            $table->unsignedInteger('version')->default(1); // optimistic concurrency token
            $table->foreignId('superseded_by_id')->nullable()->constrained('workflow_instances')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_test')->default(false)->index();
            $table->timestamps();
            $table->index(['report_id', 'status']);
            $table->index(['current_step_id', 'status']);
        });

        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('primary_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('delegate_user_id')->constrained('users')->restrictOnDelete();
            $table->string('role_code', 20); // JE | AE | EE
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('reason');
            $table->string('transfer_mode', 20); // all_pending | new_only | selective
            $table->boolean('return_on_end')->default(true); // hand open tasks back when the delegation ends
            $table->string('status', 20)->default('scheduled')->index(); // scheduled | active | ended | cancelled
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_test')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['primary_user_id', 'role_code', 'starts_at', 'ends_at'], 'delegation_lookup_idx');
        });

        // Who currently holds a task. Reassignment deactivates a row and adds a new one.
        Schema::create('workflow_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role_code', 20);
            $table->string('assigned_via', 20); // primary | delegation | manual | escalation
            $table->foreignId('original_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delegation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('previous_assignment_id')->nullable()->constrained('workflow_assignments')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 30)->nullable(); // completed | reassigned | delegated | returned
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'is_active']);
            $table->index(['workflow_instance_id', 'is_active']);
        });

        // Append-only transition history (also serves as workflow_history).
        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_transition_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action_code', 40);
            $table->foreignId('from_step_id')->nullable()->constrained('workflow_steps')->nullOnDelete();
            $table->foreignId('to_step_id')->constrained('workflow_steps')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); // null = system
            $table->string('actor_role_code', 20)->nullable();
            $table->text('comment')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy_m', 8, 2)->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['workflow_instance_id', 'id']);
            $table->index(['action_code', 'created_at']);
        });

        // One row per contractor (or departmental) repair submission. Never overwritten.
        Schema::create('repair_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->foreignId('contractor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('workflow_action_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->text('comments')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('gps_accuracy_m', 8, 2)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('submitted_at');
            // pending | rejected | accepted_je | accepted_ae | approved
            $table->string('outcome', 20)->default('pending')->index();
            $table->string('rejected_stage', 20)->nullable(); // JE | AE | EE
            $table->text('rejection_reason')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->boolean('is_test')->default(false)->index();
            $table->timestamps();
            $table->unique(['report_id', 'attempt_no']);
            $table->index(['contractor_id', 'outcome']);
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_attempt_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_action_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stage', 20); // VALIDATION | JE_REVIEW | AE_REVIEW | EE_APPROVAL
            $table->foreignId('inspector_id')->constrained('users')->restrictOnDelete();
            $table->string('inspector_role_code', 20);
            $table->string('decision', 20); // valid | invalid | accepted | rejected | approved
            $table->text('comment')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy_m', 8, 2)->nullable();
            $table->decimal('distance_from_site_m', 8, 2)->nullable();
            $table->boolean('location_verified')->default(false);
            $table->boolean('location_override')->default(false);
            $table->timestamp('inspected_at');
            $table->boolean('is_test')->default(false)->index();
            $table->timestamps();
            $table->index(['inspector_id', 'stage', 'decision']);
        });

        Schema::create('evidences', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->morphs('evidenceable'); // report | repair_attempt | inspection
            $table->foreignId('workflow_action_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10); // photo | video
            $table->string('disk', 30);
            $table->string('original_path');
            $table->string('display_path')->nullable(); // watermarked copy
            $table->string('thumbnail_path')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('duration_s')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('sha256', 64)->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('gps_accuracy_m', 8, 2)->nullable();
            $table->timestamp('captured_at');
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('processing_status', 20)->default('pending'); // pending | done | failed
            $table->json('exif')->nullable();
            $table->json('flags')->nullable(); // e.g. duplicate_elsewhere, exif_gps_mismatch
            $table->boolean('is_test')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'evidences', 'inspections', 'repair_attempts', 'workflow_actions', 'workflow_assignments',
            'delegations', 'workflow_instances', 'workflow_transitions', 'workflow_steps',
            'workflow_definitions', 'report_links', 'report_responsibilities', 'reports',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
