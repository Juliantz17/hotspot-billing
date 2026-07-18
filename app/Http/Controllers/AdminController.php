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
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $time = $request->query('time', 'all');

        $query = DB::table('hotspot_transactions');

        // Apply status filter
        if ($status === 'active') {
            $query->where('status', 'SUCCESS')
                  ->where('expires_at', '>', Carbon::now());
        } elseif ($status === 'pending') {
            $query->where('status', 'PENDING');
        } elseif ($status === 'failed') {
            $query->where('status', 'FAILED');
        } elseif ($status === 'expired') {
            $query->where('status', 'SUCCESS')
                  ->where(function ($q) {
                      $q->where('expires_at', '<=', Carbon::now())
                        ->orWhereNull('expires_at');
                  });
        }

        // Apply time filter
        if ($time === 'today') {
            $query->whereDate('created_at', Carbon::today());
        } elseif ($time === 'yesterday') {
            $query->whereDate('created_at', Carbon::yesterday());
        } elseif ($time === 'this_week') {
            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        } elseif ($time === 'last_week') {
            $query->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);
        } elseif ($time === 'this_month') {
            $query->whereMonth('created_at', Carbon::now()->month)->whereYear('created_at', Carbon::now()->year);
        } elseif ($time === 'last_month') {
            $query->whereMonth('created_at', Carbon::now()->subMonth()->month)->whereYear('created_at', Carbon::now()->subMonth()->year);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate(10)
            ->appends(['status' => $status, 'time' => $time]);

        return view('admin.dashboard', compact('transactions', 'status', 'time'));
    }

    public function analytics()
    {
        $visits = DB::table('checkout_visits')
            ->select(
                'checkout_visits.*',
                DB::raw('(SELECT count(*) FROM hotspot_transactions WHERE hotspot_transactions.mac_address = checkout_visits.mac_address AND hotspot_transactions.status = "SUCCESS" AND hotspot_transactions.created_at >= checkout_visits.created_at) as paid_after')
            )
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.analytics', compact('visits'));
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
            $routerClient = \App\Services\MikrotikService::getClient();

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

            $queues = $routerClient->query([
                '/queue/simple/print',
                '?name=RateLimit_' . $txn->mac_address
            ])->read();

            if (!empty($queues)) {
                $routerClient->query([
                    '/queue/simple/remove',
                    '=.id=' . $queues[0]['.id']
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
            $routerClient = \App\Services\MikrotikService::getClient();

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

                $bindings = $routerClient->query(['/ip/hotspot/ip-binding/print', '?mac-address=' . $macTarget])->read();
                foreach ($bindings as $b) {
                    $routerClient->query(['/ip/hotspot/ip-binding/remove', '=.id=' . $b['.id']])->read();
                }

                $queues = $routerClient->query(['/queue/simple/print', '?name=RateLimit_' . $macTarget])->read();
                foreach ($queues as $q) {
                    $routerClient->query(['/queue/simple/remove', '=.id=' . $q['.id']])->read();
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
