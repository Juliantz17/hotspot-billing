<?php

namespace App\Http\Controllers;

use App\Events\WifiPaymentSuccess;
use App\Services\MikrotikService;
use App\Services\RouterProvisioningService;
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
                'mac_address',
                'ip_address',
                DB::raw('MIN(created_at) as first_visited_at'),
                DB::raw('MAX(created_at) as last_visited_at'),
                DB::raw('COUNT(*) as visit_count')
            )
            ->groupBy('mac_address', 'ip_address')
            ->orderByRaw('MAX(created_at) DESC')
            ->paginate(20);

        $visits->getCollection()->transform(function ($visit) {
            $historyQuery = DB::table('checkout_visits')
                ->where('mac_address', $visit->mac_address)
                ->orderBy('created_at', 'desc');

            if ($visit->ip_address === null) {
                $historyQuery->whereNull('ip_address');
            } else {
                $historyQuery->where('ip_address', $visit->ip_address);
            }

            $visit->history = $historyQuery->get();
            $visit->paid_after = DB::table('hotspot_transactions')
                ->where('mac_address', $visit->mac_address)
                ->where('status', 'SUCCESS')
                ->where('created_at', '>=', $visit->first_visited_at)
                ->count();

            return $visit;
        });

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

        $baseExpiry = $txn->expires_at ? Carbon::parse($txn->expires_at) : now();
        $newExpiry = $baseExpiry->isFuture()
            ? $baseExpiry->addHours((float) $request->extend_hours)
            : now()->addHours((float) $request->extend_hours);

        DB::table('hotspot_transactions')->where('id', $id)->update([
            'expires_at' => $newExpiry,
            'updated_at' => now(),
        ]);

        $updatedTxn = DB::table('hotspot_transactions')->where('id', $id)->first();

        try {
            app(RouterProvisioningService::class)->provisionAccess($updatedTxn, 'Admin Extend Txn');
        } catch (\Throwable $e) {
            Log::error('Failed to re-provision extended user on MikroTik: '.$e->getMessage(), [
                'transaction_id' => $updatedTxn->transaction_id ?? null,
                'mac_address' => $updatedTxn->mac_address ?? null,
            ]);

            return back()->withErrors(['error' => 'Package time was extended, but router access could not be restored: '.$e->getMessage()]);
        }

        return back()->with('success', 'User package extended and router access restored successfully.');
    }

    public function kick($id)
    {
        $txn = DB::table('hotspot_transactions')->where('id', $id)->first();

        if (! $txn || $txn->status !== 'SUCCESS') {
            return back()->withErrors(['error' => 'User is not currently active.']);
        }

        try {
            app(RouterProvisioningService::class)->removeMacAccess($txn->mac_address, true);

            DB::table('hotspot_transactions')->where('id', $id)->update([
                'expires_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("Admin kicked user Txn: {$txn->transaction_id}, MAC: {$txn->mac_address}");

            return back()->with('success', 'User has been kicked out of MikroTik and marked expired.');

        } catch (\Throwable $e) {
            Log::error('Admin kick failed: '.$e->getMessage(), [
                'transaction_id' => $txn->transaction_id ?? null,
                'mac_address' => $txn->mac_address ?? null,
            ]);

            return back()->withErrors(['error' => 'Failed to kick user from MikroTik: '.$e->getMessage()]);
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
        $hostsList = [];
        $dhcpLeases = [];
        $ipBindings = [];
        $routerUsers = [];
        $activeMap = [];
        $hostsMap = [];
        $bindingsMap = [];
        $error = null;

        try {
            $routerClient = MikrotikService::getClient();

            $activeUsers = $routerClient->query('/ip/hotspot/active/print')->read();
            $hosts = $routerClient->query('/ip/hotspot/host/print')->read();
            $bindings = $routerClient->query('/ip/hotspot/ip-binding/print')->read();
            $hotspotUsers = $routerClient->query('/ip/hotspot/user/print')->read();
            $leases = $routerClient->query('/ip/dhcp-server/lease/print')->read();
            $queues = $routerClient->query('/queue/simple/print')->read();
            foreach ($activeUsers as $activeUser) {
                $mac = strtolower($activeUser['mac-address'] ?? $activeUser['user'] ?? '');
                if (! empty($mac)) {
                    $activeMap[$mac] = $activeUser;
                }
            }
            foreach ($hosts as $host) {
                if (! empty($host['mac-address'])) {
                    $hostsMap[strtolower($host['mac-address'])] = $host;
                }
            }
            foreach ($bindings as $binding) {
                if (! empty($binding['mac-address'])) {
                    $bindingsMap[strtolower($binding['mac-address'])] = $binding;
                }
            }

            $queuesMap = [];
            foreach ($queues as $queue) {
                if (! empty($queue['name']) && strpos($queue['name'], 'RateLimit_') === 0) {
                    $mac = strtolower(substr($queue['name'], 10));
                    $queuesMap[$mac] = $queue;
                }
            }

            foreach ($activeUsers as $activeUser) {
                $mac = strtolower($activeUser['mac-address'] ?? $activeUser['user'] ?? '');
                if (empty($mac)) {
                    continue;
                }

                $host = $hostsMap[$mac] ?? null;
                $binding = $bindingsMap[$mac] ?? null;
                $queue = $queuesMap[$mac] ?? null;
                [$queueUploadBytes, $queueDownloadBytes] = $this->splitRouterCounter($queue['bytes'] ?? null);

                $activeSessions[] = [
                    '.id' => $activeUser['.id'] ?? null,
                    'host_id' => $host['.id'] ?? null,
                    'user' => $activeUser['user'] ?? $activeUser['mac-address'] ?? strtoupper($mac),
                    'mac' => $activeUser['mac-address'] ?? ($host['mac-address'] ?? strtoupper($mac)),
                    'address' => $activeUser['address'] ?? ($host['address'] ?? '-'),
                    'host_address' => $host['address'] ?? '-',
                    'host_seen' => $host !== null,
                    'has_binding' => $binding !== null,
                    'uptime' => $activeUser['uptime'] ?? '-',
                    'idle-time' => $activeUser['idle-time'] ?? ($host['idle-time'] ?? '-'),
                    'rx-rate' => $activeUser['rx-rate'] ?? ($host['rx-rate'] ?? '-'),
                    'tx-rate' => $activeUser['tx-rate'] ?? ($host['tx-rate'] ?? '-'),
                    'bytes-in' => $activeUser['bytes-in'] ?? '0',
                    'bytes-out' => $activeUser['bytes-out'] ?? '0',
                    'queue-in' => $queueUploadBytes,
                    'queue-out' => $queueDownloadBytes,
                    'comment' => $activeUser['comment'] ?? ($host['comment'] ?? ($binding['comment'] ?? '-')),
                ];
            }

            foreach ($hosts as $host) {
                $mac = strtolower($host['mac-address'] ?? '');
                if (empty($mac)) {
                    continue;
                }

                $activeUser = $activeMap[$mac] ?? null;
                $binding = $bindingsMap[$mac] ?? null;
                $queue = $queuesMap[$mac] ?? null;
                [$queueUploadBytes, $queueDownloadBytes] = $this->splitRouterCounter($queue['bytes'] ?? null);

                $hostsList[] = [
                    '.id' => $host['.id'] ?? null,
                    'mac' => $host['mac-address'] ?? strtoupper($mac),
                    'address' => $host['address'] ?? '-',
                    'authenticated' => $activeUser !== null,
                    'bypassed' => ($host['bypassed'] ?? 'false') === 'true',
                    'has_binding' => $binding !== null,
                    'idle-time' => $host['idle-time'] ?? '-',
                    'uptime' => $activeUser['uptime'] ?? '-',
                    'rx-rate' => $host['rx-rate'] ?? ($activeUser['rx-rate'] ?? '-'),
                    'tx-rate' => $host['tx-rate'] ?? ($activeUser['tx-rate'] ?? '-'),
                    'bytes-in' => $host['bytes-in'] ?? ($activeUser['bytes-in'] ?? '0'),
                    'bytes-out' => $host['bytes-out'] ?? ($activeUser['bytes-out'] ?? '0'),
                    'queue-in' => $queueUploadBytes,
                    'queue-out' => $queueDownloadBytes,
                    'comment' => $host['comment'] ?? ($binding['comment'] ?? '-'),
                ];
            }

            foreach ($leases as $lease) {
                $mac = strtolower($lease['mac-address'] ?? '');
                if (empty($mac)) {
                    continue;
                }

                $activeUser = $activeMap[$mac] ?? null;
                $host = $hostsMap[$mac] ?? null;
                $binding = $bindingsMap[$mac] ?? null;

                $dhcpLeases[] = [
                    '.id' => $lease['.id'] ?? null,
                    'mac' => $lease['mac-address'] ?? strtoupper($mac),
                    'address' => $lease['address'] ?? '-',
                    'host_name' => $lease['host-name'] ?? '-',
                    'server' => $lease['server'] ?? '-',
                    'status' => $lease['status'] ?? '-',
                    'dynamic' => ($lease['dynamic'] ?? 'false') === 'true',
                    'disabled' => ($lease['disabled'] ?? 'false') === 'true',
                    'last_seen' => $lease['last-seen'] ?? '-',
                    'expires_after' => $lease['expires-after'] ?? '-',
                    'router_active' => $activeUser !== null,
                    'host_seen' => $host !== null,
                    'has_binding' => $binding !== null,
                    'comment' => $lease['comment'] ?? '-',
                ];
            }
            foreach ($hotspotUsers as $hotspotUser) {
                $mac = $this->macFromHotspotUser($hotspotUser);
                $activeUser = $mac !== '' ? ($activeMap[$mac] ?? null) : null;
                $host = $mac !== '' ? ($hostsMap[$mac] ?? null) : null;
                $binding = $mac !== '' ? ($bindingsMap[$mac] ?? null) : null;

                $routerUsers[] = [
                    '.id' => $hotspotUser['.id'] ?? null,
                    'name' => $hotspotUser['name'] ?? '-',
                    'mac' => $hotspotUser['mac-address'] ?? ($mac !== '' ? strtoupper($mac) : '-'),
                    'profile' => $hotspotUser['profile'] ?? '-',
                    'server' => $hotspotUser['server'] ?? '-',
                    'limit_uptime' => $hotspotUser['limit-uptime'] ?? '-',
                    'uptime' => $hotspotUser['uptime'] ?? '-',
                    'bytes_in' => $hotspotUser['bytes-in'] ?? '0',
                    'bytes_out' => $hotspotUser['bytes-out'] ?? '0',
                    'disabled' => ($hotspotUser['disabled'] ?? 'false') === 'true',
                    'router_active' => $activeUser !== null,
                    'host_seen' => $host !== null,
                    'has_binding' => $binding !== null,
                    'comment' => $hotspotUser['comment'] ?? '-',
                ];
            }
            foreach ($bindings as $binding) {
                $mac = strtolower($binding['mac-address'] ?? '');
                $activeUser = $mac !== '' ? ($activeMap[$mac] ?? null) : null;
                $host = $mac !== '' ? ($hostsMap[$mac] ?? null) : null;

                $ipBindings[] = [
                    '.id' => $binding['.id'] ?? null,
                    'mac' => $binding['mac-address'] ?? '-',
                    'address' => $binding['address'] ?? '-',
                    'to_address' => $binding['to-address'] ?? '-',
                    'type' => $binding['type'] ?? '-',
                    'server' => $binding['server'] ?? '-',
                    'disabled' => ($binding['disabled'] ?? 'false') === 'true',
                    'online' => $activeUser !== null || $host !== null,
                    'authenticated' => $activeUser !== null,
                    'host_seen' => $host !== null,
                    'comment' => $binding['comment'] ?? '-',
                ];
            }
        } catch (\Exception $e) {
            $error = 'Failed to connect to MikroTik router: '.$e->getMessage();
        }

        return view('admin.active_sessions', compact('activeSessions', 'hostsList', 'dhcpLeases', 'ipBindings', 'routerUsers', 'error'));

    }

    private function macFromHotspotUser(array $hotspotUser): string
    {
        $mac = strtolower($hotspotUser['mac-address'] ?? '');
        if ($mac !== '') {
            return $mac;
        }

        $name = strtolower($hotspotUser['name'] ?? '');
        if (preg_match('/^hs_([0-9a-f]{12})$/', $name, $matches) === 1) {
            return implode(':', str_split($matches[1], 2));
        }

        if (preg_match('/^[0-9a-f]{2}(:[0-9a-f]{2}){5}$/', $name) === 1) {
            return $name;
        }

        return '';
    }

    private function splitRouterCounter(?string $counter): array
    {
        if (empty($counter)) {
            return [0, 0];
        }

        $parts = explode('/', $counter);
        if (count($parts) !== 2) {
            return [0, 0];
        }

        return [floatval($parts[0]), floatval($parts[1])];
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
            app(RouterProvisioningService::class)->repairRateLimitQueueOrder();

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
