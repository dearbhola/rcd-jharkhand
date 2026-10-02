<?php

namespace Tests\Feature\Phase10;

use App\Domain\Performance\DrillDown;
use App\Domain\Performance\PerformanceScope;
use App\Domain\Performance\PerformanceService;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;
use Tests\Support\WorkflowHelper;

class PerformanceTest extends SeededTestCase
{
    use WorkflowHelper;

    private int $contractorId;

    private Report $approved;

    private Report $overdue;

    private Report $repeat;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('evidence');
        $this->travelTo(now()->setDate(2026, 8, 1)->setTime(9, 0));

        $citizen = User::where('mobile', '9500000001')->firstOrFail();
        $this->approved = $this->fileReport($citizen, 'RCD-005', 5000);
        $r = $this->approved->currentResponsibility;
        $this->contractorId = $r->contractor_id;
        [$je, $ae, $ee] = [User::find($r->je_user_id), User::find($r->ae_user_id), User::find($r->ee_user_id)];
        $contractor = User::where('contractor_id', $this->contractorId)->first();

        // Task 1: rejected once by the JE, then repaired and approved.
        $this->act($je, $this->approved, 'validate')->assertOk();
        $this->travel(2)->hours();
        $this->act($contractor, $this->approved, 'acknowledge')->assertOk();
        $this->travel(10)->hours();
        $this->act($contractor, $this->approved, 'submit_repair', ['repair_description' => 'Patched', 'evidence' => $this->photos(2, 1)])->assertOk();
        $this->act($je, $this->approved, 'reject', ['comment' => 'Edges not sealed', 'evidence' => $this->photos(1, 2)])->assertOk();
        $this->act($contractor, $this->approved, 'submit_repair', ['repair_description' => 'Re-sealed', 'evidence' => $this->photos(2, 3)])->assertOk();
        $this->act($je, $this->approved, 'accept', ['evidence' => $this->photos(1, 4)])->assertOk();
        $this->act($ae, $this->approved, 'accept', ['evidence' => $this->photos(1, 5)])->assertOk();
        $this->act($ee, $this->approved, 'approve')->assertOk();

        // Task 2: assigned, never started → overdue.
        $this->overdue = $this->fileReport(User::where('mobile', '9500000002')->first(), 'RCD-005', 9000);
        $this->act($je, $this->overdue, 'validate')->assertOk();

        // Task 3: the same defect again at the same place within the repeat window → repeat defect.
        $this->travel(20)->days();
        $this->repeat = $this->fileReport($citizen, 'RCD-005', 5040);
        $this->act($je, $this->repeat, 'validate')->assertOk();
        $this->travel(5)->days();
    }

    private function scope(array $extra = []): PerformanceScope
    {
        return PerformanceScope::fromInput(['contractor_id' => $this->contractorId] + $extra);
    }

    #[Test]
    public function metrics_are_derived_from_the_workflow_records(): void
    {
        $m = app(PerformanceService::class)->metrics($this->scope());

        $this->assertSame(3, $m['tasks_total']);
        $this->assertSame(1, $m['tasks_completed']);
        $this->assertSame(2, $m['tasks_open']);
        $this->assertSame(2, $m['tasks_overdue']);
        $this->assertSame(2, $m['repair_attempts']);
        $this->assertSame(1, $m['rejections_je']);
        $this->assertSame(0, $m['rejections_ae']);
        $this->assertSame(1, $m['reopened_repairs']);
        $this->assertSame(1, $m['repeat_defects']);
        $this->assertSame(2, $m['sla_breaches']);
        $this->assertEqualsWithDelta(12.0, $m['avg_repair_hours'], 0.2); // assignment → first submission
        $this->assertNotNull($m['sla_compliance']);
        $this->assertGreaterThanOrEqual(1, $m['contracts_total']);
        $this->assertGreaterThanOrEqual(1, $m['roads_maintained']);
    }

    #[Test]
    public function every_figure_drills_down_to_exactly_the_records_it_counts(): void
    {
        $svc = app(PerformanceService::class);
        $drill = app(DrillDown::class);
        $scope = $this->scope();

        foreach (PerformanceService::METRICS as $key => [$label, $kind]) {
            $table = $drill->table($key, $scope, true, []);
            $value = $svc->value($key, $scope);

            if (in_array($kind, ['reports', 'attempts', 'contracts', 'roads'], true)) {
                $this->assertSame($value, count($table->rows), "{$key}: figure and drill-down differ");
            } elseif ($kind === 'hours' && $value !== null) {
                $this->assertEqualsWithDelta($value, collect($table->rows)->avg('hours'), 0.1, "{$key}: average not traceable");
            } elseif ($kind === 'percent' && $value !== null) {
                $onTime = collect($table->rows)->where('result', 'On time')->count();
                $this->assertEqualsWithDelta($value, round($onTime / count($table->rows) * 100, 1), 0.01, "{$key}: % not traceable");
            }
        }

        $reopened = $drill->table('reopened_repairs', $scope, true, []);
        $this->assertSame([$this->approved->report_no], array_column($reopened->rows, 'report'));
        $this->assertSame([$this->repeat->report_no], array_column($drill->table('repeat_defects', $scope, true, [])->rows, 'report'));
    }

    #[Test]
    public function the_period_scope_limits_what_is_counted(): void
    {
        $svc = app(PerformanceService::class);
        $this->assertSame(3, $svc->value('tasks_total', $this->scope(['fy' => '2026-27'])));
        $this->assertSame(0, $svc->value('tasks_total', $this->scope(['fy' => '2025-26'])));
        $this->assertSame(1, $svc->value('tasks_total', $this->scope(['from' => '2026-08-15'])));
    }

    #[Test]
    public function screens_render_and_figures_link_to_their_records(): void
    {
        $ee = $this->userByEmail('ee.dn@rcd.test');

        $this->actingAs($ee)->get('/performance')->assertOk()->assertSee('Contractor performance')
            ->assertSee(route('performance.drill', ['metric' => 'reopened_repairs', 'contractor_id' => $this->contractorId]), false);
        $this->actingAs($ee)->get("/performance/contractors/{$this->contractorId}")->assertOk()->assertSee('Repeat defects')->assertSee('By contract');
        $this->actingAs($ee)->get("/performance/drill/reopened_repairs?contractor_id={$this->contractorId}")->assertOk()
            ->assertSee($this->approved->report_no)->assertSee('1 record(s)');
        $this->actingAs($ee)->get('/performance/drill/nonsense')->assertNotFound();
        $this->actingAs($this->userByEmail('je001@rcd.test'))->get('/performance')->assertForbidden();
    }

    #[Test]
    public function exports_are_official_unless_test_data_is_explicitly_included(): void
    {
        $ee = $this->userByEmail('ee.dn@rcd.test');
        $ae = $this->userByEmail('ae.sdn1@rcd.test');
        $url = "/performance/drill/tasks_total?contractor_id={$this->contractorId}";

        // All demo data is test data → the official export has no rows and no TEST banner.
        $official = $this->actingAs($ee)->get($url.'&export=csv')->assertOk()->streamedContent();
        $this->assertStringNotContainsString($this->approved->report_no, $official);
        $this->assertStringNotContainsString('TEST DATA INCLUDED', $official);

        // EE lacks testdata.include → the checkbox is ignored.
        $this->assertStringNotContainsString($this->approved->report_no, $this->actingAs($ee)->get($url.'&export=csv&include_test=1')->streamedContent());

        $admin = $this->userByEmail('admin@rcd.test');
        $withTest = $this->actingAs($admin)->get($url.'&export=csv&include_test=1')->streamedContent();
        $this->assertStringContainsString($this->approved->report_no, $withTest);
        $this->assertStringContainsString('TEST DATA INCLUDED — NOT AN OFFICIAL REPORT', $withTest);

        $this->actingAs($admin)->get($url.'&export=xlsx&include_test=1')->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($admin)->get($url.'&export=pdf&include_test=1')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($ae)->get($url.'&export=csv')->assertForbidden(); // AE: view but not export
    }
}
