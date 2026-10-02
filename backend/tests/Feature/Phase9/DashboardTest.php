<?php

namespace Tests\Feature\Phase9;

use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;
use Tests\Support\WorkflowHelper;

class DashboardTest extends SeededTestCase
{
    use WorkflowHelper;

    private Report $report;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('evidence');
        Cache::flush();
        $this->report = $this->fileReport(User::where('mobile', '9500000001')->firstOrFail());
    }

    private function people(): array
    {
        $r = $this->report->currentResponsibility;

        return [User::find($r->je_user_id), User::find($r->ae_user_id), User::find($r->ee_user_id), User::where('contractor_id', $r->contractor_id)->first()];
    }

    #[Test]
    public function the_citizen_sees_their_own_reports(): void
    {
        $this->actingAs(User::where('mobile', '9500000001')->first())->get('/dashboard')->assertOk()
            ->assertSee('Citizen dashboard')->assertSee($this->report->report_no)->assertSee('Awaiting validation');
        $this->actingAs(User::where('mobile', '9500000002')->first())->get('/dashboard')->assertOk()
            ->assertDontSee($this->report->report_no)->assertSee('You have not reported anything yet');
    }

    #[Test]
    public function the_je_dashboard_shows_tasks_roads_and_citizen_validations(): void
    {
        [$je] = $this->people();

        $this->actingAs($je)->get('/dashboard')->assertOk()
            ->assertSee('Junior Engineer dashboard')
            ->assertSee($this->report->report_no)          // in My tasks
            ->assertSee('Citizen reports to validate')
            ->assertSee('RCD-005')                         // My roads
            ->assertViewHas('d', fn ($d) => $d['citizen_waiting'] === 1 && $d['tasks']->count() === 1 && $d['open'] === 1);
    }

    #[Test]
    public function the_contractor_dashboard_buckets_their_repairs(): void
    {
        [$je, , , $contractor] = $this->people();
        $this->actingAs($contractor)->get('/dashboard')->assertViewHas('d', fn ($d) => $d['buckets'][0][1] === 0); // not yet validated

        $this->act($je, $this->report, 'validate')->assertOk();
        Cache::flush();

        $this->actingAs($contractor)->get('/dashboard')->assertOk()->assertSee('Assigned repairs')->assertSee($this->report->report_no)
            ->assertViewHas('d', fn ($d) => $d['buckets'][0][0] === 'New' && $d['buckets'][0][1] === 1);
    }

    #[Test]
    public function the_ee_dashboard_has_the_division_overview_chart_and_contractors(): void
    {
        [$je, , $ee] = $this->people();
        $this->act($je, $this->report, 'validate')->assertOk();
        Cache::flush();

        $this->actingAs($ee)->get('/dashboard')->assertOk()
            ->assertSee('trendChart', false)
            ->assertSee('Contractors in division')
            ->assertViewHas('d', fn ($d) => $d['open'] === 1
                && collect($d['trend'])->last()['reported'] === 1
                && $d['contractors']->first()['open'] === 1
                && $d['by_status']['ASSIGNED'] === 1);
    }

    #[Test]
    public function the_admin_dashboard_shows_system_health(): void
    {
        $this->actingAs($this->userByEmail('admin@rcd.test'))->get('/dashboard')->assertOk()
            ->assertSee('Reports blocked by missing mapping')
            ->assertViewHas('d', fn ($d) => $d['open'] === 1 && $d['masters'][0][1] === 20 && $d['unmapped_sections'] === 0);
    }

    #[Test]
    public function multi_role_users_can_switch_dashboards_but_not_to_roles_they_lack(): void
    {
        $je = $this->userByEmail('je001@rcd.test');
        $je->roles()->attach(Role::where('code', 'AE')->value('id'));

        $this->actingAs($je->fresh())->get('/dashboard?as=AE')->assertOk()->assertSee('Assistant Engineer dashboard');
        $this->actingAs($je->fresh())->get('/dashboard?as=EE')->assertOk()->assertSee('Assistant Engineer dashboard'); // falls back to highest held
    }

    #[Test]
    public function dashboards_hide_test_data_when_excluded(): void
    {
        $admin = $this->userByEmail('admin@rcd.test');
        $this->actingAs($admin)->post('/test-data/toggle');
        Cache::flush();

        $this->actingAs($admin)->get('/dashboard')->assertViewHas('d', fn ($d) => $d['open'] === 0 && $d['masters'][0][1] === 0);
    }
}
