@extends('admin.layout')

@section('title', 'Checkout & Business Analytics')

@section('content')
<!-- Metric Cards Row 1: Revenue & Key Conversion -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
    <div class="bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Revenue Today</h3>
        <p class="text-2xl font-bold text-emerald-700 mt-1">TZS {{ number_format($revenueToday) }}</p>
        <span class="text-[10px] text-gray-400">Successful sales today</span>
    </div>

    <div class="bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Revenue This Week</h3>
        <p class="text-2xl font-bold text-emerald-800 mt-1">TZS {{ number_format($revenueThisWeek) }}</p>
        <span class="text-[10px] text-gray-400">Sales since start of week</span>
    </div>

    <div class="bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Peak Hours</h3>
        <p class="text-lg font-bold text-gray-900 mt-1 truncate" title="{{ $peakHourFormatted }}">{{ $peakHourFormatted }}</p>
        <span class="text-[10px] text-gray-400">When most visitors connect</span>
    </div>

    <div class="bg-blue-900 text-white border border-blue-950 p-4 rounded-sm shadow-sm flex flex-col justify-between">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-blue-200">Device Conversion Rate</h3>
        <p class="text-3xl font-extrabold mt-1 text-white">{{ $conversionRate }}%</p>
        <span class="text-[10px] text-blue-300">Payer MACs / Visitor MACs</span>
    </div>
</div>

<!-- Metric Cards Row 2: Popular Package, Customers & Data -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Most Popular Package</h3>
        <p class="text-lg font-bold text-indigo-700 mt-1 truncate" title="{{ $mostPopularPackageName }}">{{ $mostPopularPackageName }}</p>
        <span class="text-[10px] text-gray-400">{{ $mostPopularPackageSales }} purchase(s)</span>
    </div>

    <div class="bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Returning Customers</h3>
        <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($returningCustomersCount) }}</p>
        <span class="text-[10px] text-gray-400">{{ $returningCustomersRate }}% of paying devices</span>
    </div>

    <div class="bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Abandoned Checkout</h3>
        <p class="text-2xl font-bold text-red-600 mt-1">{{ number_format($abandonedVisitsCount) }}</p>
        <span class="text-[10px] text-gray-400">{{ $abandonedRate }}% reached checkout & didn't pay</span>
    </div>

    <div class="bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Avg Data / Customer</h3>
        <p class="text-2xl font-bold text-gray-900 mt-1">{{ $avgDataUsedFormatted }}</p>
        <span class="text-[10px] text-gray-400">Average bytes used per active user</span>
    </div>
</div>

<!-- Router Live Data -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Active Hotspot Users</h3>
        <p class="text-2xl font-bold text-green-700 mt-1">{{ number_format($activeHotspotUsersCount) }}</p>
        <span class="text-[10px] text-gray-400">Authenticated users from /ip/hotspot/active</span>
    </div>

    <div class="bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Connected Hosts</h3>
        <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($connectedHostsCount) }}</p>
        <span class="text-[10px] text-gray-400">Devices visible in /ip/hotspot/host</span>
    </div>

    <div class="bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Usage Source</h3>
        <p class="text-lg font-bold text-blue-700 mt-1 truncate" title="{{ $routerDataSource }}">{{ $routerDataSource }}</p>
        <span class="text-[10px] text-gray-400">Source for average data calculation</span>
    </div>
</div>
<!-- Charts Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- 7-Day Conversion Trend -->
    <div class="bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">7-Day Conversion Trend</h3>
        <div class="w-full" style="height: 220px;">
            <canvas id="conversionChart"></canvas>
        </div>
    </div>

    <!-- Hourly Traffic Distribution (Peak Hours) -->
    <div class="bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">24-Hour Traffic Distribution</h3>
        <div class="w-full" style="height: 220px;">
            <canvas id="hourlyChart"></canvas>
        </div>
    </div>
</div>

<!-- Package Popularity Breakdown Table -->
<div class="mb-6">
    <div class="mb-2 flex justify-between items-center">
        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Package Popularity & Revenue Breakdown</h3>
    </div>
    <div class="bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden">
        <table class="w-full text-sm text-left whitespace-nowrap">
            <thead class="table-header text-xs uppercase font-semibold">
                <tr>
                    <th class="px-4 py-2 border-r border-gray-600">Package Name</th>
                    <th class="px-4 py-2 border-r border-gray-600">Duration</th>
                    <th class="px-4 py-2 border-r border-gray-600">Price</th>
                    <th class="px-4 py-2 border-r border-gray-600 text-center">Total Sales</th>
                    <th class="px-4 py-2 text-right">Total Revenue</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @forelse($packagePopularity as $pkg)
                <tr class="table-row border-b border-gray-200">
                    <td class="px-4 py-2 font-medium text-gray-900">{{ $pkg['name'] }}</td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $pkg['duration_minutes'] }} mins</td>
                    <td class="px-4 py-2 font-mono text-xs">TZS {{ number_format($pkg['amount']) }}</td>
                    <td class="px-4 py-2 text-center font-bold">{{ number_format($pkg['sales']) }}</td>
                    <td class="px-4 py-2 text-right font-mono font-semibold text-emerald-700">TZS {{ number_format($pkg['revenue']) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-gray-500 text-sm">No package sales recorded yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Checkout Page Visitors Log -->
<div class="mb-4 flex justify-between items-center">
    <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Checkout Page Visitors Log</h3>
</div>

<div class="bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden">
    <table class="w-full text-sm text-left whitespace-nowrap">
        <thead class="table-header text-xs uppercase font-semibold">
            <tr>
                <th class="px-4 py-2 border-r border-gray-600">Time of Visit</th>
                <th class="px-4 py-2 border-r border-gray-600">MAC Address</th>
                <th class="px-4 py-2 border-r border-gray-600">IP Address</th>
                <th class="px-4 py-2 text-center">Status</th>
            </tr>
        </thead>
        <tbody class="text-gray-700">
            @forelse($visits as $visit)
            <tr class="table-row border-b border-gray-200">
                <td class="px-4 py-2 font-mono text-xs">
                    {{ \Carbon\Carbon::parse($visit->created_at)->format('Y-m-d H:i:s') }}
                </td>
                <td class="px-4 py-2 font-mono text-xs">{{ strtoupper($visit->mac_address) }}</td>
                <td class="px-4 py-2 font-mono text-xs">{{ $visit->ip_address ?: 'N/A' }}</td>
                <td class="px-4 py-2 text-center">
                    @if($visit->paid_after > 0)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                            Paid Afterwards
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                            Stuck at Checkout
                        </span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="px-4 py-6 text-center text-gray-500 text-sm">No analytics data found yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
        {{ $visits->links() }}
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // 7-Day Conversion Trend Chart
        const ctxConv = document.getElementById('conversionChart').getContext('2d');
        new Chart(ctxConv, {
            type: 'line',
            data: {
                labels: @json($chartLabels),
                datasets: [{
                    label: 'Conversion Rate (%)',
                    data: @json($chartRates),
                    borderColor: '#1e3a8a',
                    backgroundColor: 'rgba(30, 58, 138, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: '#1e3a8a',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) { return `Conversion Rate: ${context.parsed.y}%`; }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { callback: function(value) { return value + "%"; } }
                    }
                }
            }
        });

        // 24-Hour Traffic Bar Chart
        const ctxHourly = document.getElementById('hourlyChart').getContext('2d');
        new Chart(ctxHourly, {
            type: 'bar',
            data: {
                labels: @json($hourlyChartLabels),
                datasets: [{
                    label: 'Visitor Connections',
                    data: @json($hourlyChartData),
                    backgroundColor: '#3b82f6',
                    borderRadius: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) { return `Visits: ${context.parsed.y}`; }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    });
</script>
@endsection
