<?php

use App\Domain\Delegation\DelegationService;
use App\Domain\Performance\CompletionNotifier;
use App\Domain\Sla\EscalationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('rcd:delegations', function (DelegationService $delegations) {
    $result = $delegations->processDue();
    $this->info("Delegations activated: {$result['activated']}, ended: {$result['ended']}");
})->purpose('Start due delegations and end expired ones (task routing for leave)');

Artisan::command('rcd:sla-scan', function (EscalationService $escalations) {
    $result = $escalations->scan();
    $this->info("SLA breaches recorded: {$result['breached']}, escalation rules fired: {$result['fired']}");
})->purpose('Record SLA breaches and fire reminders/escalations');

Artisan::command('rcd:contract-completions', function (CompletionNotifier $notifier) {
    $this->info('Completion reports announced: '.$notifier->run());
})->purpose('Announce completion reports for contracts whose maintenance period has ended');

Schedule::command('rcd:delegations')->everyMinute()->withoutOverlapping();
Schedule::command('rcd:contract-completions')->dailyAt('06:30');
Schedule::command('rcd:sla-scan')->everyFiveMinutes()->withoutOverlapping();
