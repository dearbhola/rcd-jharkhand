<?php

namespace Database\Seeders;

use App\Domain\Audit\AuditLogger;
use App\Support\Settings;
use App\Support\TestDataMode;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference/configuration data is always seeded (idempotent, production-safe).
     * Demo data (all is_test) is seeded outside production only.
     */
    public function run(): void
    {
        app(AuditLogger::class)->muted(function () {
            $this->call([
                RolePermissionSeeder::class,
                SystemSettingSeeder::class,
                ReferenceDataSeeder::class,
                WorkflowDefinitionSeeder::class,
                SlaSeeder::class,
            ]);

            if (! app()->isProduction()) {
                app(TestDataMode::class)->withTestData(fn () => $this->call(DemoDataSeeder::class));
            }
        });

        app(Settings::class)->flush();

        // Demo history through the real workflow (audited). Not in production or automated tests.
        if (! app()->isProduction() && ! app()->runningUnitTests() && env('SEED_DEMO_ACTIVITY', true)) {
            $this->call(DemoActivitySeeder::class);
        }
    }
}
