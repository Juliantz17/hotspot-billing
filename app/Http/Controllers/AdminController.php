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

        $newExpiry = Carbon::parse($txn->expires_at)->addHours((float) $request->extend_hours);

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

    public function reconnectDevice($id)
    {
        $pendingTxn = DB::table('hotspot_transactions')->where('id', $id)->first();

        if (!$pendingTxn || $pendingTxn->status !== 'PENDING') {
            return back()->withErrors(['error' => 'Can only reconnect from PENDING transactions.']);
        }

        // Find the user's last SUCCESS transaction that still has time
        $activeTxn = DB::table('hotspot_transactions')
            ->where('phone_number', $pendingTxn->phone_number)
            ->where('status', 'SUCCESS')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$activeTxn) {
            return back()->withErrors(['error' => 'No active package found for this phone number.']);
        }

        $remainingMinutes = now()->diffInMinutes($activeTxn->expires_at);

        if ($remainingMinutes < 1) {
            return back()->withErrors(['error' => 'Active package has expired.']);
        }

        // Kick the old MAC address
        try {
            $config = (new Config())
                ->set('host', env('MIKROTIK_HOST'))
                ->set('user', env('MIKROTIK_USER'))
                ->set('pass', env('MIKROTIK_PASS'))
                ->set('port', 8728);

            $routerClient = new RouterClient($config);

            $macsToClear = [
                strtolower($activeTxn->mac_address),
                strtoupper($activeTxn->mac_address)
            ];

            foreach ($macsToClear as $macTarget) {
                $activeUsers = $routerClient->query(['/ip/hotspot/active/print', '?mac-address=' . $macTarget])->read();
                foreach ($activeUsers as $user) {
                    $routerClient->query(['/ip/hotspot/active/remove', '=.id=' . $user['.id']])->read();
                }

                $hotspotUsers = $routerClient->query(['/ip/hotspot/user/print', '?name=' . $macTarget])->read();
                foreach ($hotspotUsers as $user) {
                    $routerClient->query(['/ip/hotspot/user/remove', '=.id=' . $user['.id']])->read();
                }

                $cookies = $routerClient->query(['/ip/hotspot/cookie/print', '?mac-address=' . $macTarget])->read();
                foreach ($cookies as $cookie) {
                    $routerClient->query(['/ip/hotspot/cookie/remove', '=.id=' . $cookie['.id']])->read();
                }
            }
        } catch (\Exception $e) {
            Log::warning("Could not kick old MAC {$activeTxn->mac_address} during admin reconnect.", ['error' => $e->getMessage()]);
        }

        DB::transaction(function () use ($pendingTxn, $activeTxn, $remainingMinutes) {
            // Expire the old transaction
            DB::table('hotspot_transactions')->where('id', $activeTxn->id)->update([
                'expires_at' => now(),
                'updated_at' => now()
            ]);

            // Activate the new transaction
            DB::table('hotspot_transactions')->where('id', $pendingTxn->id)->update([
                'status' => 'SUCCESS',
                'duration_minutes' => $remainingMinutes,
                'expires_at' => now()->addMinutes($remainingMinutes),
                'updated_at' => now()
            ]);
        });

        // Fetch updated transaction and trigger provision
        $updatedTxn = DB::table('hotspot_transactions')->where('id', $pendingTxn->id)->first();
        event(new \App\Events\WifiPaymentSuccess($updatedTxn));

        return back()->with('success', 'User device reconnected successfully for ' . $remainingMinutes . ' minutes.');
    }
}
