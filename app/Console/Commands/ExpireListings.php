<?php

namespace App\Console\Commands;

use App\Services\PropertyService;
use Illuminate\Console\Command;

class ExpireListings extends Command
{
    protected $signature = 'marketplace:expire-listings';

    protected $description = 'Mark published listings past their validity date as expired';

    public function handle(PropertyService $properties): int
    {
        $this->info('Expired '.$properties->expireDue().' listing(s).');

        return self::SUCCESS;
    }
}
