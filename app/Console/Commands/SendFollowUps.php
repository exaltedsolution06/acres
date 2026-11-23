<?php

namespace App\Console\Commands;

use App\Models\Enquiry;
use App\Services\Notifier;
use Illuminate\Console\Command;

class SendFollowUps extends Command
{
    protected $signature = 'marketplace:send-followups';

    protected $description = 'Remind posters about lead follow-ups that have come due';

    public function handle(Notifier $notifier): int
    {
        $count = 0;

        Enquiry::with(['poster', 'property', 'seeker'])
            ->whereNotNull('follow_up_at')->where('follow_up_at', '<=', now())
            ->whereNotIn('status', config('engagement.closed_statuses'))
            ->each(function (Enquiry $e) use ($notifier, &$count) {
                if ($e->poster) {
                    $notifier->send($e->poster, 'followup.due', 'Follow-up due: '.$e->seeker->name,
                        ($e->follow_up_note ?: 'Time to follow up').' ('.$e->property->title.')', route('leads.show', $e));
                    $count++;
                }

                $e->forceFill(['follow_up_at' => null, 'follow_up_note' => null])->save(); // one reminder per follow-up
            });

        $this->info("Sent {$count} reminder(s).");

        return self::SUCCESS;
    }
}
