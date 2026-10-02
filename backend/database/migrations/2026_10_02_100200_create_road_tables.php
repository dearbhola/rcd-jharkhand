<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roads, sections and GIS storage.
 *
 * Business tables keep geometry as GeoJSON (portable, engine-agnostic).
 * The GIS engine maintains `gis_features`, a spatially indexed SRID 4326
 * copy used for proximity lookups. Swapping MySQL for PostGIS only changes
 * how that table is written and queried.
 *
 * Chainage is stored in metres (integer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('road_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // SH, MDR, ODR, ...
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('roads', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('road_number', 30)->nullable()->index();
            $table->foreignId('road_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('division_id')->constrained()->restrictOnDelete(); // primary division
            $table->foreignId('sub_division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->string('start_location')->nullable();
            $table->string('end_location')->nullable();
            $table->unsignedInteger('start_chainage_m')->default(0);
            $table->unsignedInteger('end_chainage_m')->default(0);
            $table->unsignedInteger('length_m')->default(0);
            $table->string('status', 20)->default('active')->index(); // active | inactive | under_construction
            $table->text('description')->nullable();
            $table->string('external_ref', 100)->nullable()->index(); // future official GIS/project system id
            $table->boolean('is_test')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Versioned road centre-line geometry. Rows are never updated; a new version is added instead.
        Schema::create('road_geometries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('road_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('geojson'); // GeoJSON LineString / MultiLineString, WGS84
            $table->unsignedInteger('length_m');
            $table->string('source', 20)->default('drawn'); // drawn | imported | official
            $table->boolean('is_current')->default(false);
            $table->string('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['road_id', 'version']);
            $table->index(['road_id', 'is_current']);
        });

        // Known chainage reference points (km stones) used to calibrate linear referencing.
        Schema::create('road_chainage_markers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('road_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chainage_m');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('label', 50)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['road_id', 'chainage_m']);
        });

        Schema::create('road_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('road_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name')->nullable();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->foreignId('sub_division_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('start_chainage_m');
            $table->unsignedInteger('end_chainage_m');
            $table->unsignedInteger('length_m');
            $table->longText('geojson')->nullable(); // cut from the road geometry by chainage
            $table->string('status', 20)->default('active')->index();
            $table->boolean('is_test')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['road_id', 'code']);
            $table->index(['road_id', 'start_chainage_m', 'end_chainage_m']);
        });

        // Spatial index table maintained by the GIS engine.
        Schema::create('gis_features', function (Blueprint $table) {
            $table->id();
            $table->string('feature_type', 30); // road | road_section | asset
            $table->unsignedBigInteger('feature_id');
            $table->geometry('geom', srid: 4326);
            $table->decimal('min_lat', 10, 7);
            $table->decimal('max_lat', 10, 7);
            $table->decimal('min_lng', 10, 7);
            $table->decimal('max_lng', 10, 7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['feature_type', 'feature_id']);
            $table->spatialIndex('geom');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gis_features');
        Schema::dropIfExists('road_sections');
        Schema::dropIfExists('road_chainage_markers');
        Schema::dropIfExists('road_geometries');
        Schema::dropIfExists('roads');
        Schema::dropIfExists('road_categories');
    }
};
