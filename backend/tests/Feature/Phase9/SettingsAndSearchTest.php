<?php

namespace Tests\Feature\Phase9;

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\SeededTestCase;
use Tests\Support\WorkflowHelper;

class SettingsAndSearchTest extends SeededTestCase
{
    use WorkflowHelper;

    #[Test]
    public function settings_are_typed_validated_audited_and_take_effect(): void
    {
        $admin = $this->userByEmail('admin@rcd.test');
        $this->actingAs($this->userByEmail('je001@rcd.test'))->get('/admin/settings')->assertForbidden();
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('report.location_radius_m');

        $this->actingAs($admin)->put('/admin/settings', ['settings' => ['report.location_radius_m' => 'fifty']])->assertSessionHasErrors('settings.report.location_radius_m');
        $this->actingAs($admin)->put('/admin/settings', ['settings' => ['duplicate.radius_m' => '-5']])->assertSessionHasErrors('settings.duplicate.radius_m');
        $this->actingAs($admin)->put('/admin/settings', ['settings' => ['evidence.image_mimes' => '[broken']])->assertSessionHasErrors('settings.evidence.image_mimes');
        $this->actingAs($admin)->put('/admin/settings', ['settings' => ['no.such.key' => '1']])->assertSessionHasErrors('settings.no.such.key');

        $this->actingAs($admin)->put('/admin/settings', ['settings' => [
            'report.location_radius_m' => '75',
            'notifications.mail_enabled' => '1',
            'evidence.image_mimes' => '["image/jpeg"]',
            'gps.max_accuracy_m' => '30', // unchanged → not written
        ]])->assertRedirect()->assertSessionHas('success', '3 setting(s) saved.');

        $this->assertSame(75, app(Settings::class)->int('report.location_radius_m'));
        $this->assertTrue(app(Settings::class)->bool('notifications.mail_enabled'));
        $this->assertSame(['image/jpeg'], app(Settings::class)->get('evidence.image_mimes'));
        $row = SystemSetting::where('key', 'report.location_radius_m')->first();
        $this->assertSame($admin->id, $row->updated_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'system_setting.updated', 'auditable_id' => $row->id, 'user_id' => $admin->id]);
    }

    #[Test]
    public function search_is_permission_aware(): void
    {
        $this->actingAs($this->userByEmail('admin@rcd.test'))->get('/search?q=RCD-00')->assertOk()
            ->assertSee('Roads')->assertSee('RCD-005')->assertSee('Assets');
        $this->actingAs($this->userByEmail('admin@rcd.test'))->get('/search?q=Alpha')->assertSee('Contractors')->assertSee('Alpha Infra Builders');
        $this->actingAs($this->userByEmail('admin@rcd.test'))->get('/search?q=je00')->assertSee('Users');

        $citizen = User::where('mobile', '9500000001')->first();
        $this->actingAs($citizen)->get('/search?q=RCD-00')->assertOk()->assertDontSee('Demo Road 5')->assertDontSee('Users');
        $this->actingAs($this->userByEmail('je001@rcd.test'))->get('/search?q=je00')->assertDontSee('>Users<', false);
    }

    #[Test]
    public function reports_are_found_by_number_and_by_road_and_chainage(): void
    {
        Storage::fake('evidence');
        $citizen = User::where('mobile', '9500000001')->first();
        $report = $this->fileReport($citizen, 'RCD-005', 14250);
        $other = User::where('mobile', '9500000002')->first();

        $this->actingAs($citizen)->get('/search?q='.urlencode(substr($report->report_no, -6)))->assertSee($report->report_no);
        // The page echoes the query back, so check the result groups rather than the HTML.
        $this->actingAs($other)->get('/search?q='.urlencode($report->report_no))->assertViewHas('groups', fn ($g) => ! isset($g['Reports']));

        $admin = $this->userByEmail('admin@rcd.test');
        $this->actingAs($admin)->get('/search?q=RCD-005+14.2')->assertSee('near km');
        $road = $this->road('RCD-005');
        $this->actingAs($admin)->get("/reports?road_id={$road->id}&km_from=14&km_to=14.5")->assertSee($report->report_no);
        $this->actingAs($admin)->get("/reports?road_id={$road->id}&km_from=1&km_to=2")->assertDontSee($report->report_no);
        $contractorId = $report->currentResponsibility->contractor_id;
        $this->actingAs($admin)->get("/reports?contractor_id={$contractorId}")->assertSee($report->report_no);
        $this->actingAs($admin)->get('/reports?reporter=9500000001')->assertSee($report->report_no);
    }
}
