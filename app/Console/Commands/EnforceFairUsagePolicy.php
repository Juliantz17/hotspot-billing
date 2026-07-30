<?php

namespace App\Console\Commands;

use App\Services\FupEnforcementService;
use Illuminate\Console\Command;

class EnforceFairUsagePolicy extends Command
{
    protected $signature = 'hotspot:enforce-fup';

    protected $description = 'Track active package usage and enforce private dynamic fair usage rules';

    public function handle(FupEnforcementService $service): int
    {
        $result = $service->enforce();
        $this->info("Checked {$result['checked']}; throttled {$result['throttled']}; restored {$result['restored']}.");

        return self::SUCCESS;
    }
}
