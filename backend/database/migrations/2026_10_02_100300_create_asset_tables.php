<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // ROAD, BRIDGE, CULVERT, ...
            $table->string('name', 100);
            $table->string('geometry_kind', 20)->default('point'); // point | line
            $table->string('icon', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Damage/issue category tree per asset type (configurable, never hard-coded).
        Schema::create('issue_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('issue_categories')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['asset_type_id', 'code']);
        });

        Schema::create('severities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // LOW, MEDIUM, HIGH, CRITICAL
            $table->string('name', 50);
            $table->unsignedTinyInteger('rank'); // higher = more severe
            $table->string('color', 7)->default('#6c757d');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('asset_type_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->foreignId('road_id')->constrained()->restrictOnDelete();
            $table->foreignId('road_section_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('chainage_m')->nullable();
            $table->unsignedInteger('end_chainage_m')->nullable(); // for linear assets (drains, walls)
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->longText('geojson')->nullable();
            $table->text('description')->nullable();
            $table->json('attributes')->nullable(); // type-specific data (span, width, ...)
            $table->string('status', 20)->default('active')->index();
            $table->string('external_ref', 100)->nullable()->index();
            $table->boolean('is_test')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['road_id', 'chainage_m']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
        Schema::dropIfExists('severities');
        Schema::dropIfExists('issue_categories');
        Schema::dropIfExists('asset_types');
    }
};
