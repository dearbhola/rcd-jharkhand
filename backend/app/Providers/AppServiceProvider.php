<?php

namespace App\Providers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Otp\LogOtpSender;
use App\Domain\Auth\Otp\OtpSender;
use App\Domain\Gis\GisEngine;
use App\Domain\Gis\MysqlGisEngine;
use App\Domain\Gis\RoadLineRepository;
use App\Domain\Notifications\LogGateway;
use App\Models;
use App\Models\User;
use App\Policies\ReportPolicy;
use App\Support\Settings;
use App\Support\TestDataMode;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: reset per request / queued job so state never leaks between them.
        $this->app->scoped(TestDataMode::class);
        $this->app->scoped(Settings::class);
        $this->app->scoped(RoadLineRepository::class);
        $this->app->scoped(AuditLogger::class, fn ($app) => new AuditLogger($app->bound('request') ? $app['request'] : null));

        $this->app->bind(OtpSender::class, fn () => match (config('rcd.otp.driver')) {
            'log' => new LogOtpSender,
            default => throw new \RuntimeException('Unsupported OTP driver ['.config('rcd.otp.driver').'].'),
        });

        // Outbound message providers (not yet chosen): log drivers until configured.
        foreach (['sms', 'whatsapp', 'push'] as $channel) {
            $this->app->bind("rcd.gateway.{$channel}", fn () => new LogGateway($channel));
        }

        $this->app->singleton(GisEngine::class, fn () => match (config('rcd.gis.engine')) {
            'mysql' => new MysqlGisEngine,
            default => throw new \RuntimeException('Unsupported GIS engine ['.config('rcd.gis.engine').'].'),
        });
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Paginator::useBootstrapFive();

        // Manual RBAC: dotted abilities ("road.view") are database permissions.
        // Other abilities fall through to policies. Super Admin passes everything.
        Gate::before(function (User $user, string $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            return str_contains($ability, '.') ? $user->hasPermission($ability) : null;
        });

        Gate::policy(Models\Report::class, ReportPolicy::class);
        Gate::define('view-evidence', [ReportPolicy::class, 'viewEvidence']);
        Gate::define('view-original-evidence', [ReportPolicy::class, 'viewOriginalEvidence']);
        RateLimiter::for('reports', fn (Request $request) => Limit::perMinute(10)->by('report-user:'.$request->user()?->id));

        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perHour(10)->by('otp-ip:'.$request->ip()),
            Limit::perHour(5)->by('otp-mobile:'.$request->input('mobile')),
        ]);
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by('login-ip:'.$request->ip()));

        // Stable short names in polymorphic columns (audit_logs, evidences, notifications, tokens).
        Relation::enforceMorphMap([
            'user' => User::class,
            'role' => Models\Role::class,
            'division' => Models\Division::class,
            'sub_division' => Models\SubDivision::class,
            'road_category' => Models\RoadCategory::class,
            'road' => Models\Road::class,
            'road_chainage_marker' => Models\RoadChainageMarker::class,
            'road_section' => Models\RoadSection::class,
            'asset_type' => Models\AssetType::class,
            'issue_category' => Models\IssueCategory::class,
            'severity' => Models\Severity::class,
            'asset' => Models\Asset::class,
            'contractor' => Models\Contractor::class,
            'contract' => Models\Contract::class,
            'contract_document' => Models\ContractDocument::class,
            'contract_road_section' => Models\ContractRoadSection::class,
            'responsibility_assignment' => Models\ResponsibilityAssignment::class,
            'report' => Models\Report::class,
            'repair_attempt' => Models\RepairAttempt::class,
            'inspection' => Models\Inspection::class,
            'evidence' => Models\Evidence::class,
            'workflow_definition' => Models\WorkflowDefinition::class,
            'workflow_step' => Models\WorkflowStep::class,
            'workflow_transition' => Models\WorkflowTransition::class,
            'workflow_instance' => Models\WorkflowInstance::class,
            'delegation' => Models\Delegation::class,
            'sla_rule' => Models\SlaRule::class,
            'escalation_rule' => Models\EscalationRule::class,
            'system_setting' => Models\SystemSetting::class,
        ]);
    }
}
