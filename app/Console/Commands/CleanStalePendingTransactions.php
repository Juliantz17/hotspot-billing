<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanStalePendingTransactions extends Command
{
    protected $signature = 'hotspot:clean-pending';

    protected $description = 'Deletes pending hotspot transactions older than three hours';

    public function handle(): int
    {
        $deleted = DB::table('hotspot_transactions')
            ->where('status', 'PENDING')
            ->where('created_at', '<=', now()->subHours(3))
            ->delete();

        if ($deleted > 0) {
            Log::info("Deleted {$deleted} pending hotspot transaction(s) older than three hours.");
        }

        return Command::SUCCESS;
    }
}
