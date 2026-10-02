<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Most specific matching rule wins (asset type + category + severity > ... > stage default).
        Schema::create('sla_rules', function (Blueprint $table) {
            $table->id();
            $table->string('stage', 30); // VALIDATION | RESPONSE | REPAIR | JE_REVIEW | AE_REVIEW | EE_APPROVAL
            $table->foreignId('asset_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('issue_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('severity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('hours', 8, 2);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['stage', 'is_active']);
        });

        Schema::create('sla_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 30);
            $table->foreignId('sla_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('hours', 8, 2); // copied from the rule so later rule edits don't rewrite history
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contractor_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('due_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('breached_at')->nullable();
            $table->boolean('is_test')->default(false)->index();
            $table->timestamps();
            $table->index(['completed_at', 'due_at']);
            $table->index(['contractor_id', 'stage']);
        });

        Schema::create('escalation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('stage', 30)->nullable(); // null = all stages
            $table->string('trigger', 20); // before_due | after_due
            $table->decimal('offset_hours', 8, 2);
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('action', 20); // remind | escalate
            $table->string('notify_role_code', 20)->nullable(); // JE | AE | EE (resolved from the mapping)
            $table->boolean('notify_assignee')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('escalation_rule_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('level');
            $table->foreignId('notified_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fired_at');
            $table->timestamps();
            $table->unique(['sla_instance_id', 'escalation_rule_id']); // makes the scan idempotent
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->nullable()->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20); // database | mail | sms | whatsapp | push
            $table->string('event', 50);
            $table->string('status', 20)->default('pending'); // pending | sent | failed | skipped
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // Append-only. No update/delete code paths exist; production DB grants should enforce it too.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('role_code', 20)->nullable();
            $table->string('action', 60);
            $table->string('auditable_type', 60)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('comment')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('request_id')->nullable();
            $table->boolean('is_test')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('group', 50)->index();
            $table->string('type', 20); // int | float | bool | string | json
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_public')->default(false); // exposed to the mobile app
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Offline-sync idempotency ledger.
        Schema::create('sync_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type', 30); // report | evidence | workflow_action
            $table->uuid('client_uuid');
            $table->unsignedBigInteger('server_entity_id')->nullable();
            $table->string('status', 20); // received | completed | failed
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->text('last_error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['entity_type', 'client_uuid']);
        });
    }

    public function down(): void
    {
        foreach ([
            'sync_records', 'system_settings', 'audit_logs', 'notification_deliveries', 'notifications',
            'escalations', 'escalation_rules', 'sla_instances', 'sla_rules',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
