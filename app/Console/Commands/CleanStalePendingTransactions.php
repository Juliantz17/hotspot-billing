<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanStalePendingTransactions extends Command
{
    protected $signature = 'hotspot:clean-pending';

    protected $description = 'Deprecated: pending transactions are retained for payment reconciliation';

    public function handle(): int
    {
        $this->info('Pending hotspot transactions are retained for payment reconciliation; nothing was deleted.');

        return Command::SUCCESS;
    }
}
