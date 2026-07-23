<?php

namespace App\Http\Controllers;

use App\Events\WifiPaymentSuccess;
use App\Services\MikrotikService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RouterOS\Client;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $time = $request->query('time', 'all');
        $search = $request->query('search', '');

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

        // Apply search filter
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', '%'.$search.'%')
                    ->orWhere('phone_number', 'like', '%'.$search.'%')
                    ->orWhere('mac_address', 'like', '%'.$search.'%');
            });
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate(10)
            ->appends(['status' => $status, 'time' => $time, 'search' => $search]);

        // Fetch all packages for package name matching
        $allPackages = DB::table('packages')->get();

        foreach ($transactions as $txn) {
            $matchingPkg = $allPackages->first(function ($pkg) use ($txn) {
                return $pkg->duration_minutes == $txn->duration_minutes && $pkg->price == $txn->amount;
            });

            if ($matchingPkg) {
                $txn->package_name = $matchingPkg->name;
            } else {
                if ($txn->duration_minutes < 60) {
                    $txn->package_name = $txn->duration_minutes.' Min';
                } elseif ($txn->duration_minutes < 1440) {
                    $txn->package_name = round($txn->duration_minutes / 60, 1).' Hr';
                } else {
                    $txn->package_name = round($txn->duration_minutes / 1440, 1).' Day';
                }
            }
        }

        $metrics = $this->getDashboardMetrics();

        return view('admin.dashboard', compact('transactions', 'status', 'time', 'search', 'metrics'));
    }

    public function liveMetrics()
    {
        return response()->json($this->getDashboardMetrics());
    }

    private function getDashboardMetrics()
    {
        $revenueToday = DB::table('hotspot_transactions')
            ->where('status', 'SUCCESS')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');

        $expiredSessionsToday = DB::table('hotspot_transactions')
            ->where('status', 'SUCCESS')
            ->whereDate('expires_at', Carbon::today())
            ->where('expires_at', '<=', Carbon::now())
            ->count();

        $activeDbUsersCount = DB::table('hotspot_transactions')
            ->where('status', 'SUCCESS')
            ->where('expires_at', '>', Carbon::now())
            ->count();

        $onlineUsersCount = $activeDbUsersCount;
        $activeHotspotUsersCount = 0;
        $connectedHostsCount = 0;
        $internetStatus = false;
        $routerCpu = 'N/A';
        $routerMemory = 'N/A';
        $currentBandwidthBps = 0;

        try {
            if (! app()->environment('testing') || app()->bound(Client::class)) {
                $routerClient = MikrotikService::getClient();

                // Check Router Identity & Connection
                $identity = $routerClient->query('/system/identity/print')->read();
                if (! empty($identity)) {
                    $internetStatus = true;
                }

                // Query System Resource for CPU and Memory
                $resource = $routerClient->query('/system/resource/print')->read();
                if (! empty($resource[0])) {
                    $res = $resource[0];
                    if (isset($res['cpu-load'])) {
                        $routerCpu = $res['cpu-load'].'%';
                    }
                    if (isset($res['total-memory']) && isset($res['free-memory'])) {
                        $tot = floatval($res['total-memory']);
                        $free = floatval($res['free-memory']);
                        if ($tot > 0) {
                            $routerMemory = round((($tot - $free) / $tot) * 100).'%';
                        }
                    }
                }

                $activeUsers = $routerClient->query('/ip/hotspot/active/print')->read();
                $hosts = $routerClient->query('/ip/hotspot/host/print')->read();

                $activeHotspotUsersCount = count($activeUsers);
                $connectedHostsCount = count($hosts);
                $onlineUsersCount = max($activeHotspotUsersCount, $activeDbUsersCount);

                foreach ($activeUsers as $activeUser) {
                    $currentBandwidthBps += floatval($activeUser['rx-rate'] ?? 0) + floatval($activeUser['tx-rate'] ?? 0);
                }

                if ($currentBandwidthBps == 0) {
                    foreach ($hosts as $host) {
                        $currentBandwidthBps += floatval($host['rx-rate'] ?? 0) + floatval($host['tx-rate'] ?? 0);
                    }
                }

                if ($currentBandwidthBps == 0) {
                    $queues = $routerClient->query('/queue/simple/print')->read();
                    foreach ($queues as $queue) {
                        $parts = explode('/', (string) ($queue['rate'] ?? ''));
                        if (count($parts) === 2) {
                            $currentBandwidthBps += floatval($parts[0]) + floatval($parts[1]);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Router offline/unreachable fallback values set safely
        }

        // Format Bandwidth nicely
        if ($currentBandwidthBps >= 1000000) {
            $currentBandwidthFormatted = round($currentBandwidthBps / 1000000, 1).' Mbps';
        } elseif ($currentBandwidthBps >= 1000) {
            $currentBandwidthFormatted = round($currentBandwidthBps / 1000, 1).' Kbps';
        } else {
            $currentBandwidthFormatted = $currentBandwidthBps > 0 ? round($currentBandwidthBps).' bps' : '0 Mbps';
        }

        return [
            'online_users' => $onlineUsersCount,
            'active_hotspot_users' => $activeHotspotUsersCount,
            'connected_hosts' => $connectedHostsCount,
            'revenue_today' => $revenueToday,
            'revenue_today_formatted' => 'TZS '.number_format($revenueToday),
            'current_bandwidth' => $currentBandwidthFormatted,
            'internet_status' => $internetStatus,
            'router_cpu' => $routerCpu,
            'router_memory' => $routerMemory,
            'expired_sessions_today' => $expiredSessionsToday,
        ];
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

        // Compute summary metrics
        $totalVisits = DB::table('checkout_visits')->count();
        $uniqueVisits = DB::table('checkout_visits')->distinct('mac_address')->count('mac_address');

        $totalPaid = DB::table('hotspot_transactions')
            ->where('status', 'SUCCESS')
            ->count();
        $uniquePaid = DB::table('hotspot_transactions')
            ->where('status', 'SUCCESS')
            ->distinct('mac_address')
            ->count('mac_address');

        $conversionRate = $uniqueVisits > 0 ? round(($uniquePaid / $uniqueVisits) * 100, 1) : 0;

        // Revenue today and this week
        $revenueToday = DB::table('hotspot_transactions')
            ->where('status', 'SUCCESS')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');

        $revenueThisWeek = DB::table('hotspot_transactions')
            ->where('status', 'SUCCESS')
            ->where('created_at', '>=', Carbon::now()->startOfWeek())
            ->sum('amount');

        // Visitors who reached checkout but didn't pay (Abandoned Checkout)
        $paidMacs = DB::table('hotspot_transactions')
            ->where('status', 'SUCCESS')
            ->pluck('mac_address')
            ->unique()
            ->filter()
            ->toArray();

        $abandonedVisitsCount = DB::table('checkout_visits')
            ->whereNotIn('mac_address', $paidMacs)
            ->distinct('mac_address')
            ->count('mac_address');

        $abandonedRate = $uniqueVisits > 0 ? round(($abandonedVisitsCount / $uniqueVisits) * 100, 1) : 0;

        // Returning Customers (paying MAC addresses with > 1 successful transactions)
        $returningCustomersCount = DB::table('hotspot_transactions')
            ->where('status', 'SUCCESS')
            ->select('mac_address', DB::raw('count(*) as txn_count'))
            ->groupBy('mac_address')
            ->having('txn_count', '>', 1)
            ->get()
            ->count();

        $returningCustomersRate = $uniquePaid > 0 ? round(($returningCustomersCount / $uniquePaid) * 100, 1) : 0;

        // Peak Hours (when most visitors connect)
        $hourRaw = DB::connection()->getDriverName() === 'sqlite'
            ? DB::raw("CAST(strftime('%H', created_at) AS INTEGER) as hour")
            : DB::raw('HOUR(created_at) as hour');

        $hourlyVisitsRaw = DB::table('checkout_visits')
            ->select($hourRaw, DB::raw('count(*) as count'))
            ->groupBy('hour')
            ->pluck('count', 'hour')
            ->toArray();

        $hourlyChartLabels = [];
        $hourlyChartData = [];
        $peakHourStr = null;
        $peakHourCount = 0;

        for ($h = 0; $h < 24; $h++) {
            $count = $hourlyVisitsRaw[$h] ?? 0;
            $label = sprintf('%02d:00', $h);
            $hourlyChartLabels[] = $label;
            $hourlyChartData[] = $count;

            if ($count > $peakHourCount) {
                $peakHourCount = $count;
                $nextH = ($h + 1) % 24;
                $peakHourStr = sprintf('%02d:00 - %02d:00', $h, $nextH);
            }
        }

        $peakHourFormatted = $peakHourStr ? "$peakHourStr ($peakHourCount visits)" : 'N/A';

        // Most Popular Package
        $allPackages = DB::table('packages')->get();

        $popularPackagesRaw = DB::table('hotspot_transactions')
            ->where('status', 'SUCCESS')
            ->select('duration_minutes', 'amount', 'speed_limit', DB::raw('count(*) as total_sales'), DB::raw('sum(amount) as total_revenue'))
            ->groupBy('duration_minutes', 'amount', 'speed_limit')
            ->orderBy('total_sales', 'desc')
            ->get();

        $packagePopularity = [];
        $mostPopularPackageName = 'N/A';
        $mostPopularPackageSales = 0;

        foreach ($popularPackagesRaw as $idx => $p) {
            $matchingPkg = $allPackages->first(function ($pkg) use ($p) {
                return $pkg->duration_minutes == $p->duration_minutes && $pkg->price == $p->amount;
            });

            $pkgName = $matchingPkg ? $matchingPkg->name : ($p->duration_minutes.' Min ('.number_format($p->amount).' TZS)');

            $packagePopularity[] = [
                'name' => $pkgName,
                'duration_minutes' => $p->duration_minutes,
                'amount' => $p->amount,
                'sales' => $p->total_sales,
                'revenue' => $p->total_revenue,
            ];

            if ($idx === 0) {
                $mostPopularPackageName = $pkgName;
                $mostPopularPackageSales = $p->total_sales;
            }
        }

        // Average Data Used Per Customer
        $avgDataUsedFormatted = 'N/A';
        $activeHotspotUsersCount = 0;
        $connectedHostsCount = 0;
        $routerDataSource = 'Unavailable';
        try {
            if (! app()->environment('testing') || app()->bound(Client::class)) {
                $routerClient = MikrotikService::getClient();
                $activeUsers = $routerClient->query('/ip/hotspot/active/print')->read();
                $hosts = $routerClient->query('/ip/hotspot/host/print')->read();
                $queues = $routerClient->query('/queue/simple/print')->read();

                $activeHotspotUsersCount = count($activeUsers);
                $connectedHostsCount = count($hosts);
                $totalBytes = 0;
                $userCount = 0;

                foreach ($activeUsers as $activeUser) {
                    $bytesIn = floatval($activeUser['bytes-in'] ?? 0);
                    $bytesOut = floatval($activeUser['bytes-out'] ?? 0);
                    if ($bytesIn > 0 || $bytesOut > 0) {
                        $totalBytes += ($bytesIn + $bytesOut);
                        $userCount++;
                    }
                }

                if ($userCount > 0) {
                    $routerDataSource = 'Hotspot active sessions';
                }

                if ($userCount == 0) {
                    foreach ($hosts as $host) {
                        $bytesIn = floatval($host['bytes-in'] ?? 0);
                        $bytesOut = floatval($host['bytes-out'] ?? 0);
                        if ($bytesIn > 0 || $bytesOut > 0) {
                            $totalBytes += ($bytesIn + $bytesOut);
                            $userCount++;
                        }
                    }

                    if ($userCount > 0) {
                        $routerDataSource = 'Router hosts';
                    }
                }

                if ($userCount == 0) {
                    foreach ($queues as $queue) {
                        $parts = explode('/', (string) ($queue['bytes'] ?? ''));
                        if (count($parts) === 2) {
                            $uploadBytes = floatval($parts[0]);
                            $downloadBytes = floatval($parts[1]);
                            if ($uploadBytes > 0 || $downloadBytes > 0) {
                                $totalBytes += ($uploadBytes + $downloadBytes);
                                $userCount++;
                            }
                        }
                    }

                    if ($userCount > 0) {
                        $routerDataSource = 'Simple queues';
                    }
                }

                if ($userCount > 0) {
                    $avgBytes = $totalBytes / $userCount;
                    $avgDataUsedFormatted = $this->formatBytes($avgBytes);
                } else {
                    $avgDataUsedFormatted = '0 B';
                    $routerDataSource = 'No router usage yet';
                }
            }
        } catch (\Exception $e) {
            $avgDataUsedFormatted = 'N/A';
        }

        // Daily visits for the last 7 days (unique MAC addresses)
        $dailyVisits = DB::table('checkout_visits')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(distinct mac_address) as count'))
            ->where('created_at', '>=', Carbon::now()->subDays(6)->startOfDay())
            ->groupBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // Daily payments for the last 7 days (unique MAC addresses)
        $dailyPayments = DB::table('hotspot_transactions')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(distinct mac_address) as count'))
            ->where('status', 'SUCCESS')
            ->where('created_at', '>=', Carbon::now()->subDays(6)->startOfDay())
            ->groupBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        $chartLabels = [];
        $chartRates = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $label = Carbon::now()->subDays($i)->format('M d');

            $dayVisits = $dailyVisits[$date] ?? 0;
            $dayPayments = $dailyPayments[$date] ?? 0;
            $rate = $dayVisits > 0 ? round(($dayPayments / $dayVisits) * 100, 1) : 0;

            $chartLabels[] = $label;
            $chartRates[] = $rate;
        }

        return view('admin.analytics', compact(
            'visits',
            'totalVisits',
            'uniqueVisits',
            'totalPaid',
            'uniquePaid',
            'conversionRate',
            'chartLabels',
            'chartRates',
            'revenueToday',
            'revenueThisWeek',
            'abandonedVisitsCount',
            'abandonedRate',
            'returningCustomersCount',
            'returningCustomersRate',
            'peakHourFormatted',
            'hourlyChartLabels',
            'hourlyChartData',
            'mostPopularPackageName',
            'mostPopularPackageSales',
            'packagePopularity',
            'avgDataUsedFormatted',
            'activeHotspotUsersCount',
            'connectedHostsCount',
            'routerDataSource'
        ));
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $bytes = floatval($bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
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
            'extend_hours' => 'required|numeric|min:1',
        ]);

        $txn = DB::table('hotspot_transactions')->where('id', $id)->first();
        if (! $txn || $txn->status !== 'SUCCESS') {
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

        if (! $txn || $txn->status !== 'SUCCESS') {
            return back()->withErrors(['error' => 'User is not currently active.']);
        }

        try {
            $routerClient = MikrotikService::getClient();

            $bindings = $routerClient->query([
                '/ip/hotspot/ip-binding/print',
                '?mac-address='.$txn->mac_address,
            ])->read();

            if (! empty($bindings)) {
                $routerClient->query([
                    '/ip/hotspot/ip-binding/remove',
                    '=.id='.$bindings[0]['.id'],
                ])->read();
            }

            $queues = $routerClient->query([
                '/queue/simple/print',
                '?name=RateLimit_'.$txn->mac_address,
            ])->read();

            if (! empty($queues)) {
                $routerClient->query([
                    '/queue/simple/remove',
                    '=.id='.$queues[0]['.id'],
                ])->read();
            }

            DB::table('hotspot_transactions')->where('id', $id)->update([
                'expires_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("Admin kicked user Txn: {$txn->transaction_id}, MAC: {$txn->mac_address}");

            return back()->with('success', 'User has been kicked out of the network.');

        } catch (\Exception $e) {
            Log::error('Admin kick failed: '.$e->getMessage());

            return back()->withErrors(['error' => 'Failed to connect to router to kick user.']);
        }
    }

    public function destroyTxn($id)
    {
        $txn = DB::table('hotspot_transactions')->where('id', $id)->first();
        if (! $txn || $txn->status === 'SUCCESS') {
            return back()->withErrors(['error' => 'Cannot delete successful transactions.']);
        }

        DB::table('hotspot_transactions')->where('id', $id)->delete();

        return back()->with('success', 'Transaction deleted successfully.');
    }

    public function reconnectDevice($id)
    {
        $pendingTxn = DB::table('hotspot_transactions')->where('id', $id)->first();

        if (! $pendingTxn || $pendingTxn->status !== 'PENDING') {
            return back()->withErrors(['error' => 'Can only reconnect from PENDING transactions.']);
        }

        // Find the user's last SUCCESS transaction that still has time
        $activeTxn = DB::table('hotspot_transactions')
            ->where('phone_number', $pendingTxn->phone_number)
            ->where('status', 'SUCCESS')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $activeTxn) {
            return back()->withErrors(['error' => 'No active package found for this phone number.']);
        }

        $remainingMinutes = now()->diffInMinutes($activeTxn->expires_at);

        if ($remainingMinutes < 1) {
            return back()->withErrors(['error' => 'Active package has expired.']);
        }

        // Kick the old MAC address
        try {
            $routerClient = MikrotikService::getClient();

            $macsToClear = [
                strtolower($activeTxn->mac_address),
                strtoupper($activeTxn->mac_address),
            ];

            foreach ($macsToClear as $macTarget) {
                $activeUsers = $routerClient->query(['/ip/hotspot/active/print', '?mac-address='.$macTarget])->read();
                foreach ($activeUsers as $user) {
                    $routerClient->query(['/ip/hotspot/active/remove', '=.id='.$user['.id']])->read();
                }

                $hotspotUsers = $routerClient->query(['/ip/hotspot/user/print', '?name='.$macTarget])->read();
                foreach ($hotspotUsers as $user) {
                    $routerClient->query(['/ip/hotspot/user/remove', '=.id='.$user['.id']])->read();
                }

                $cookies = $routerClient->query(['/ip/hotspot/cookie/print', '?mac-address='.$macTarget])->read();
                foreach ($cookies as $cookie) {
                    $routerClient->query(['/ip/hotspot/cookie/remove', '=.id='.$cookie['.id']])->read();
                }

                $bindings = $routerClient->query(['/ip/hotspot/ip-binding/print', '?mac-address='.$macTarget])->read();
                foreach ($bindings as $b) {
                    $routerClient->query(['/ip/hotspot/ip-binding/remove', '=.id='.$b['.id']])->read();
                }

                $queues = $routerClient->query(['/queue/simple/print', '?name=RateLimit_'.$macTarget])->read();
                foreach ($queues as $q) {
                    $routerClient->query(['/queue/simple/remove', '=.id='.$q['.id']])->read();
                }

                $hosts = $routerClient->query(['/ip/hotspot/host/print', '?mac-address='.$macTarget])->read();
                foreach ($hosts as $h) {
                    $routerClient->query(['/ip/hotspot/host/remove', '=.id='.$h['.id']])->read();
                }
            }
        } catch (\Exception $e) {
            Log::warning("Could not kick old MAC {$activeTxn->mac_address} during admin reconnect.", ['error' => $e->getMessage()]);
        }

        DB::transaction(function () use ($pendingTxn, $activeTxn, $remainingMinutes) {
            // Expire the old transaction
            DB::table('hotspot_transactions')->where('id', $activeTxn->id)->update([
                'expires_at' => now(),
                'updated_at' => now(),
            ]);

            // Activate the new transaction
            DB::table('hotspot_transactions')->where('id', $pendingTxn->id)->update([
                'status' => 'SUCCESS',
                'duration_minutes' => $remainingMinutes,
                'expires_at' => now()->addMinutes($remainingMinutes),
                'updated_at' => now(),
            ]);
        });

        // Fetch updated transaction and trigger provision
        $updatedTxn = DB::table('hotspot_transactions')->where('id', $pendingTxn->id)->first();
        event(new WifiPaymentSuccess($updatedTxn));

        return back()->with('success', 'User device reconnected successfully for '.$remainingMinutes.' minutes.');
    }

    public function activeSessions()
    {
        $activeSessions = [];
        $error = null;
        try {
            $routerClient = MikrotikService::getClient();

            // 1. Query hosts table
            $hosts = $routerClient->query('/ip/hotspot/host/print')->read();

            // 2. Query IP Bindings to get comments
            $bindings = $routerClient->query('/ip/hotspot/ip-binding/print')->read();
            $bindingsMap = [];
            foreach ($bindings as $b) {
                if (! empty($b['mac-address'])) {
                    $bindingsMap[strtolower($b['mac-address'])] = $b;
                }
            }

            // 3. Query Simple Queues to get cumulative data usage
            $queues = $routerClient->query('/queue/simple/print')->read();
            $queuesMap = [];
            foreach ($queues as $q) {
                // Name format is: RateLimit_AA:BB:CC:DD:EE:FF
                if (! empty($q['name']) && strpos($q['name'], 'RateLimit_') === 0) {
                    $mac = strtolower(substr($q['name'], 10));
                    $queuesMap[$mac] = $q;
                }
            }

            // 4. Merge data
            foreach ($hosts as $h) {
                $mac = strtolower($h['mac-address'] ?? '');
                if (empty($mac)) {
                    continue;
                }

                $binding = $bindingsMap[$mac] ?? null;
                $queue = $queuesMap[$mac] ?? null;

                $isBypassed = isset($h['bypassed']) && ($h['bypassed'] === 'true' || $h['bypassed'] === true);

                // Queue bytes field format: "upload/download" (e.g. "12345/67890")
                $queueUploadBytes = 0;
                $queueDownloadBytes = 0;
                if (! empty($queue['bytes'])) {
                    $parts = explode('/', $queue['bytes']);
                    if (count($parts) === 2) {
                        $queueUploadBytes = floatval($parts[0]);
                        $queueDownloadBytes = floatval($parts[1]);
                    }
                }

                $activeSessions[] = [
                    '.id' => $h['.id'] ?? null,
                    'user' => $h['mac-address'] ?? 'Unknown',
                    'address' => $h['address'] ?? '-',
                    'bypassed' => $isBypassed,
                    'idle-time' => $h['idle-time'] ?? '-',
                    'rx-rate' => $h['rx-rate'] ?? '-',
                    'tx-rate' => $h['tx-rate'] ?? '-',

                    // Session bytes
                    'bytes-in' => $h['bytes-in'] ?? '0',
                    'bytes-out' => $h['bytes-out'] ?? '0',

                    // Cumulative Queue bytes (Package usage)
                    'queue-in' => $queueUploadBytes,
                    'queue-out' => $queueDownloadBytes,

                    'comment' => $h['comment'] ?? ($binding['comment'] ?? '-'),
                ];
            }
        } catch (\Exception $e) {
            $error = 'Failed to connect to MikroTik router: '.$e->getMessage();
        }

        return view('admin.active_sessions', compact('activeSessions', 'error'));
    }

    public function kickActiveSession($id)
    {
        try {
            $routerClient = MikrotikService::getClient();
            $routerClient->query(['/ip/hotspot/host/remove', '=.id='.$id])->read();

            return back()->with('success', 'Host connection removed successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to remove host connection: '.$e->getMessage()]);
        }
    }

    public function routerQueues()
    {
        $queues = [];
        $error = null;

        try {
            $routerClient = MikrotikService::getClient();
            $queues = collect($routerClient->query('/queue/simple/print')->read())
                ->map(fn ($queue) => [
                    '.id' => $queue['.id'] ?? null,
                    'name' => $queue['name'] ?? '-',
                    'target' => $queue['target'] ?? '-',
                    'max_limit' => $queue['max-limit'] ?? '-',
                    'limit_at' => $queue['limit-at'] ?? '-',
                    'rate' => $queue['rate'] ?? '-',
                    'bytes' => $queue['bytes'] ?? '0/0',
                    'packets' => $queue['packets'] ?? '0/0',
                    'disabled' => ($queue['disabled'] ?? 'false') === 'true',
                    'comment' => $queue['comment'] ?? '-',
                ])
                ->values()
                ->all();
        } catch (\Exception $e) {
            $error = 'Failed to connect to MikroTik router: '.$e->getMessage();
        }

        return view('admin.queues', compact('queues', 'error'));
    }

    public function routerLogs()
    {
        $logs = [];
        $error = null;

        try {
            $routerClient = MikrotikService::getClient();
            $logs = collect($routerClient->query('/log/print')->read())
                ->reverse()
                ->take(100)
                ->map(fn ($log) => [
                    '.id' => $log['.id'] ?? null,
                    'time' => $log['time'] ?? '-',
                    'topics' => $log['topics'] ?? '-',
                    'message' => $log['message'] ?? '-',
                ])
                ->values()
                ->all();
        } catch (\Exception $e) {
            $error = 'Failed to connect to MikroTik router: '.$e->getMessage();
        }

        return view('admin.logs', compact('logs', 'error'));
    }

    public function routerPanel()
    {
        return view('admin.router', [
            'router' => $this->getRouterSnapshot(),
        ]);
    }

    public function routerSnapshot()
    {
        return response()->json($this->getRouterSnapshot());
    }

    public function rebootRouter(Request $request)
    {
        try {
            $routerClient = MikrotikService::getClient();
            $routerClient->query('/system/reboot')->read();

            Log::warning('Admin requested MikroTik router reboot.');

            return back()->with('success', 'Router reboot command sent. It may take a few minutes to come back online.');
        } catch (\Exception $e) {
            Log::error('Router reboot failed: '.$e->getMessage());

            return back()->withErrors(['error' => 'Could not reboot router: '.$e->getMessage()]);
        }
    }

    private function getRouterSnapshot(): array
    {
        $snapshot = [
            'online' => false,
            'error' => null,
            'identity' => 'N/A',
            'version' => 'N/A',
            'uptime' => 'N/A',
            'cpu_load' => 'N/A',
            'memory_used' => 'N/A',
            'free_memory' => 'N/A',
            'total_memory' => 'N/A',
            'active_hotspot_users' => 0,
            'hosts' => 0,
            'queues' => 0,
            'interfaces' => [],
            'active_users' => [],
            'queue_details' => [],
        ];

        try {
            $routerClient = MikrotikService::getClient();

            $identity = $routerClient->query('/system/identity/print')->read();
            $resource = $routerClient->query('/system/resource/print')->read();
            $activeUsers = $routerClient->query('/ip/hotspot/active/print')->read();
            $hosts = $routerClient->query('/ip/hotspot/host/print')->read();
            $queues = $routerClient->query('/queue/simple/print')->read();
            $interfaces = $routerClient->query('/interface/print')->read();

            $resourceRow = $resource[0] ?? [];
            $totalMemory = (float) ($resourceRow['total-memory'] ?? 0);
            $freeMemory = (float) ($resourceRow['free-memory'] ?? 0);
            $usedMemoryPercent = $totalMemory > 0 ? round((($totalMemory - $freeMemory) / $totalMemory) * 100).'%' : 'N/A';

            return array_merge($snapshot, [
                'online' => true,
                'identity' => $identity[0]['name'] ?? 'MikroTik',
                'version' => $resourceRow['version'] ?? 'N/A',
                'uptime' => $resourceRow['uptime'] ?? 'N/A',
                'cpu_load' => isset($resourceRow['cpu-load']) ? $resourceRow['cpu-load'].'%' : 'N/A',
                'memory_used' => $usedMemoryPercent,
                'free_memory' => isset($resourceRow['free-memory']) ? $this->formatBytes($resourceRow['free-memory']) : 'N/A',
                'total_memory' => isset($resourceRow['total-memory']) ? $this->formatBytes($resourceRow['total-memory']) : 'N/A',
                'active_hotspot_users' => count($activeUsers),
                'hosts' => count($hosts),
                'queues' => count($queues),
                'interfaces' => collect($interfaces)
                    ->take(8)
                    ->map(fn ($interface) => [
                        'name' => $interface['name'] ?? '-',
                        'type' => $interface['type'] ?? '-',
                        'running' => ($interface['running'] ?? 'false') === 'true',
                        'disabled' => ($interface['disabled'] ?? 'false') === 'true',
                    ])
                    ->values()
                    ->all(),
            ]);
        } catch (\Exception $e) {
            $snapshot['error'] = $e->getMessage();

            return $snapshot;
        }
    }

    public function routerStatus()
    {
        try {
            $routerClient = MikrotikService::getClient();
            $routerClient->query('/system/identity/print')->read();

            return response()->json(['online' => true]);
        } catch (\Exception $e) {
            return response()->json(['online' => false, 'error' => $e->getMessage()]);
        }
    }
}
