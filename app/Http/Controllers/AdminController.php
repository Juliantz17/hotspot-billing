<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RouterOS\Config;
use RouterOS\Client as RouterClient;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function index()
    {
        $transactions = DB::table('hotspot_transactions')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('admin.dashboard', compact('transactions'));
    }

    public function earnings(Request $request)
    {
        $filter = $request->query('filter', 'today');
        
        $query = DB::table('hotspot_transactions')->where('status', 'SUCCESS');

        if ($filter === 'today') {
            $query->whereDate('created_at', Carbon::today());
        } elseif ($filter === 'yesterday') {
            $query->whereDate('created_at', Carbon::yesterday());
        } elseif ($filter === 'this_week') {
            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        } elseif ($filter === 'last_week') {
            $query->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);
        } elseif ($filter === 'this_month') {
            $query->whereMonth('created_at', Carbon::now()->month)->whereYear('created_at', Carbon::now()->year);
        } elseif ($filter === 'last_month') {
            $query->whereMonth('created_at', Carbon::now()->subMonth()->month)->whereYear('created_at', Carbon::now()->subMonth()->year);
        }

        $totalEarnings = clone $query;
        $totalEarnings = $totalEarnings->sum('amount');
        
        $transactionCount = clone $query;
        $transactionCount = $transactionCount->count();

        $transactions = $query->orderBy('created_at', 'desc')->paginate(10)->appends(['filter' => $filter]);

        return view('admin.earnings', compact('totalEarnings', 'transactionCount', 'transactions', 'filter'));
    }

    public function extend(Request $request, $id)
    {
        $request->validate([
            'extend_hours' => 'required|numeric|min:1'
        ]);

        $txn = DB::table('hotspot_transactions')->where('id', $id)->first();
        if (!$txn || $txn->status !== 'SUCCESS') {
            return back()->withErrors(['error' => 'Can only extend active (SUCCESS) transactions.']);
        }

        $newExpiry = Carbon::parse($txn->expires_at)->addHours($request->extend_hours);

        DB::table('hotspot_transactions')->where('id', $id)->update([
            'expires_at' => $newExpiry,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'User package extended successfully.');
    }

    public function kick($id)
    {
        $txn = DB::table('hotspot_transactions')->where('id', $id)->first();
        
        if (!$txn || $txn->status !== 'SUCCESS') {
            return back()->withErrors(['error' => 'User is not currently active.']);
        }

        try {
            $config = (new Config())
                ->set('host', env('MIKROTIK_HOST'))
                ->set('user', env('MIKROTIK_USER'))
                ->set('pass', env('MIKROTIK_PASS'))
                ->set('port', 8728);

            $routerClient = new RouterClient($config);

            $bindings = $routerClient->query([
                '/ip/hotspot/ip-binding/print',
                '?mac-address=' . $txn->mac_address
            ])->read();

            if (!empty($bindings)) {
                $routerClient->query([
                    '/ip/hotspot/ip-binding/remove',
                    '=.id=' . $bindings[0]['.id']
                ])->read();
            }

            DB::table('hotspot_transactions')->where('id', $id)->update([
                'expires_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("Admin kicked user Txn: {$txn->transaction_id}, MAC: {$txn->mac_address}");

            return back()->with('success', 'User has been kicked out of the network.');

        } catch (\Exception $e) {
            Log::error("Admin kick failed: " . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to connect to router to kick user.']);
        }
    }

    public function destroyTxn($id)
    {
        $txn = DB::table('hotspot_transactions')->where('id', $id)->first();
        if (!$txn || $txn->status === 'SUCCESS') {
            return back()->withErrors(['error' => 'Cannot delete successful transactions.']);
        }
        
        DB::table('hotspot_transactions')->where('id', $id)->delete();
        return back()->with('success', 'Transaction deleted successfully.');
    }
}
