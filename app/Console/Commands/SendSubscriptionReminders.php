<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Notifier;
use Illuminate\Console\Command;

class SendSubscriptionReminders extends Command
{
    protected $signature = 'marketplace:send-subscription-reminders {--days=7}';

    protected $description = 'Remind users whose paid plan ends soon';

    public function handle(Notifier $notifier): int
    {
        $sent = 0;

        Subscription::with(['user', 'package'])
            ->where('status', 'active')->whereNull('reminded_at')
            ->whereNotNull('ends_at')->whereBetween('ends_at', [now(), now()->addDays((int) $this->option('days'))])
            ->whereNotIn('source', ['trial'])
            ->each(function (Subscription $s) use ($notifier, &$sent) {
                if ($s->user && $s->user->isActive()) {
                    $notifier->send($s->user, 'subscription.expiring', 'Your plan ends soon',
                        "{$s->package->name} ends on ".$s->ends_at->timezone(config('marketplace.timezone'))->format('d M Y').'. Renew to keep your limits.',
                        route('pricing'));
                    $sent++;
                }

                $s->forceFill(['reminded_at' => now()])->save();
            });

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
