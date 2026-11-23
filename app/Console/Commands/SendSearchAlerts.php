<?php

namespace App\Console\Commands;

use App\Services\SavedSearchService;
use Illuminate\Console\Command;

class SendSearchAlerts extends Command
{
    protected $signature = 'marketplace:send-search-alerts';

    protected $description = 'Notify users about new listings matching their saved searches';

    public function handle(SavedSearchService $searches): int
    {
        $this->info('Sent '.$searches->sendAlerts().' alert(s).');

        return self::SUCCESS;
    }
}
