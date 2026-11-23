<?php

namespace App\Console\Commands;

use App\Services\VerificationService;
use Illuminate\Console\Command;

class MaintainVerifications extends Command
{
    protected $signature = 'marketplace:maintain-verifications';

    protected $description = 'Expire lapsed verifications and delete documents past their retention period';

    public function handle(VerificationService $verifications): int
    {
        $this->info('Expired '.$verifications->expireDue().' verification(s); deleted '.$verifications->purgeDocuments().' document(s).');

        return self::SUCCESS;
    }
}
