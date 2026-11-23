<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'marketplace:expire-subscriptions';

    protected $description = 'Mark subscriptions past their end and grace date as expired';

    public function handle(SubscriptionService $subscriptions): int
    {
        $this->info('Expired '.$subscriptions->expireDue().' subscription(s).');

        return self::SUCCESS;
    }
}
