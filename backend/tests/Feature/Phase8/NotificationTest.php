<?php

namespace Tests\Feature\Phase8;

use App\Domain\Notifications\ChannelRouter;
use App\Domain\Notifications\MessageGateway;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\SeededTestCase;
use Tests\Support\WorkflowHelper;

class NotificationTest extends SeededTestCase
{
    use WorkflowHelper;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('evidence');
    }

    #[Test]
    public function the_notification_centre_lists_opens_and_marks_read(): void
    {
        $report = $this->fileReport(User::where('mobile', '9500000001')->firstOrFail());
        $je = User::find($report->currentResponsibility->je_user_id);
        $notification = $je->notifications()->sole();

        $this->actingAs($je)->get('/dashboard')->assertSee('badge rounded-pill bg-danger', false);
        $this->actingAs($je)->get('/notifications')->assertOk()->assertSee($report->report_no)->assertSee('new');
        $this->actingAs($je)->get('/notifications?filter=read')->assertOk()->assertDontSee($report->report_no);

        $this->actingAs($je)->get("/notifications/{$notification->id}/open")->assertRedirect("/reports/{$report->id}");
        $this->assertNotNull($notification->fresh()->read_at);
        $this->actingAs($je)->get('/notifications?filter=read')->assertSee($report->report_no);

        // Another user's notification is not reachable.
        $this->actingAs($this->userByEmail('je008@rcd.test'))->get("/notifications/{$notification->id}/open")->assertNotFound();
    }

    #[Test]
    public function mark_all_read_and_unsafe_links_are_not_followed(): void
    {
        $user = $this->userByEmail('je001@rcd.test');
        $user->notify(new SystemNotification('delegation.started', 'Test', 'https://evil.example/'));
        $user->notify(new SystemNotification('delegation.started', 'Test 2', '//evil.example/'));
        $ids = $user->notifications()->pluck('id');

        foreach ($ids as $id) {
            $this->actingAs($user)->get("/notifications/{$id}/open")->assertRedirect('/notifications');
        }
        $user->notify(new SystemNotification('delegation.started', 'Test 3'));
        $this->actingAs($user)->post('/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    #[Test]
    public function external_channels_are_used_only_when_enabled_for_listed_events(): void
    {
        $router = app(ChannelRouter::class);
        $je = $this->userByEmail('je001@rcd.test');

        $this->assertSame(['database'], $router->channelsFor($je, 'task.assigned'));

        app(Settings::class)->set('notifications.sms_enabled', true);
        app(Settings::class)->set('notifications.mail_enabled', true);
        $this->assertSame(['database', 'mail', 'sms'], app(ChannelRouter::class)->channelsFor($je, 'task.assigned'));
        $this->assertSame(['database'], app(ChannelRouter::class)->channelsFor($je, 'report.status')); // not an external event

        $citizen = User::where('mobile', '9500000001')->first(); // no email
        $this->assertSame(['database', 'sms'], app(ChannelRouter::class)->channelsFor($citizen, 'task.assigned'));
    }

    #[Test]
    public function every_external_delivery_is_recorded_including_failures(): void
    {
        app(Settings::class)->set('notifications.sms_enabled', true);
        app(Settings::class)->set('notifications.whatsapp_enabled', true);
        $this->app->bind('rcd.gateway.whatsapp', fn () => new class implements MessageGateway
        {
            public function send(string $to, string $message, array $meta = []): void
            {
                throw new RuntimeException('Provider down');
            }
        });

        $report = $this->fileReport(User::where('mobile', '9500000001')->firstOrFail());
        $je = User::find($report->currentResponsibility->je_user_id);

        $this->assertDatabaseHas('notification_deliveries', ['user_id' => $je->id, 'channel' => 'sms', 'event' => 'task.assigned', 'status' => 'sent']);
        $failed = NotificationDelivery::where('user_id', $je->id)->where('channel', 'whatsapp')->sole();
        $this->assertSame(['failed', 'Provider down'], [$failed->status, $failed->last_error]);
        $this->assertTrue($je->notifications()->exists()); // in-app still delivered
    }

    #[Test]
    public function delegations_notify_the_stand_in_and_the_officer(): void
    {
        $je = $this->userByEmail('je001@rcd.test');
        $standIn = $this->userByEmail('je009@rcd.test');
        $this->actingAs($this->userByEmail('ae.sdn1@rcd.test'))->post('/delegations', [
            'primary_user_id' => $je->id, 'delegate_user_id' => $standIn->id, 'role_code' => 'JE',
            'starts_at' => now()->subMinute()->toDateTimeString(), 'ends_at' => now()->addDays(2)->toDateTimeString(),
            'reason' => 'Casual leave', 'transfer_mode' => 'new_only', 'return_on_end' => 1,
        ])->assertRedirect();

        $this->assertStringContainsString('standing in for', $standIn->notifications()->sole()->data['message']);
        $this->assertStringContainsString('routed to', $je->notifications()->sole()->data['message']);
    }
}
