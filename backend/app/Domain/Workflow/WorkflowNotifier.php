<?php

namespace App\Domain\Workflow;

use App\Models\Report;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Notifications\WorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Sends notifications. They are queued and dispatched after the surrounding transaction commits,
 * so a rolled-back action never notifies anyone.
 */
class WorkflowNotifier
{
    /** @param iterable<User> $users */
    public function system(iterable $users, string $event, string $message, ?string $url = null): void
    {
        $recipients = collect($users)->filter()->unique('id')->values();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new SystemNotification($event, $message, $url));
        }
    }

    /** @param iterable<User> $users */
    public function send(iterable $users, string $event, Report $report, string $message, ?User $except = null): void
    {
        $recipients = collect($users)->filter()->unique('id')->reject(fn (User $u) => $except && $u->is($except))->values();
        if ($recipients->isEmpty()) {
            return;
        }

        DB::afterCommit(fn () => Notification::send($recipients, new WorkflowNotification($event, $report->fresh() ?? $report, $message)));
    }
}
