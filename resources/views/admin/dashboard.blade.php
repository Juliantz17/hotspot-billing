@extends('admin.layout')

@section('title', 'Active Users & Transactions')

@section('content')
<!-- Live System Overview Metrics -->
<div class="mb-6 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-3">
    <!-- Online Users -->
    <div class="bg-white p-3.5 rounded-sm border border-gray-300 shadow-sm flex flex-col justify-between">
        <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold uppercase text-gray-500 tracking-wider">Online Users</span>
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse"></span>
        </div>
        <div class="mt-2">
            <span id="stat-online-users" class="text-xl font-extrabold text-gray-900">🟢 {{ $metrics['online_users'] ?? 0 }}</span>
        </div>
    </div>

    <!-- Today's Revenue -->
    <div class="bg-white p-3.5 rounded-sm border border-gray-300 shadow-sm flex flex-col justify-between">
        <span class="text-[11px] font-bold uppercase text-gray-500 tracking-wider">Today's Revenue</span>
        <div class="mt-2">
            <span id="stat-revenue-today" class="text-base font-extrabold text-emerald-600">TZS {{ number_format($metrics['revenue_today'] ?? 0) }}</span>
        </div>
    </div>

    <!-- Current Bandwidth -->
    <div class="bg-white p-3.5 rounded-sm border border-gray-300 shadow-sm flex flex-col justify-between">
        <span class="text-[11px] font-bold uppercase text-gray-500 tracking-wider">Bandwidth</span>
        <div class="mt-2">
            <span id="stat-current-bandwidth" class="text-base font-bold text-blue-600">{{ $metrics['current_bandwidth'] ?? '0 Mbps' }}</span>
        </div>
    </div>

    <!-- Internet Status -->
    <div class="bg-white p-3.5 rounded-sm border border-gray-300 shadow-sm flex flex-col justify-between">
        <span class="text-[11px] font-bold uppercase text-gray-500 tracking-wider">Internet Status</span>
        <div class="mt-2">
            @if(!empty($metrics['internet_status']))
                <span id="stat-internet-status" class="inline-flex items-center text-xs font-bold text-green-700 bg-green-50 px-1.5 py-0.5 rounded border border-green-200">🟢 Online</span>
            @else
                <span id="stat-internet-status" class="inline-flex items-center text-xs font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded border border-red-200">🔴 Offline</span>
            @endif
        </div>
    </div>

    <!-- Router CPU -->
    <div class="bg-white p-3.5 rounded-sm border border-gray-300 shadow-sm flex flex-col justify-between">
        <span class="text-[11px] font-bold uppercase text-gray-500 tracking-wider">Router CPU</span>
        <div class="mt-2">
            <span id="stat-router-cpu" class="text-base font-bold text-purple-700">{{ $metrics['router_cpu'] ?? 'N/A' }}</span>
        </div>
    </div>

    <!-- Router Memory -->
    <div class="bg-white p-3.5 rounded-sm border border-gray-300 shadow-sm flex flex-col justify-between">
        <span class="text-[11px] font-bold uppercase text-gray-500 tracking-wider">Router Memory</span>
        <div class="mt-2">
            <span id="stat-router-memory" class="text-base font-bold text-indigo-700">{{ $metrics['router_memory'] ?? 'N/A' }}</span>
        </div>
    </div>

    <!-- Expired Sessions Today -->
    <div class="bg-white p-3.5 rounded-sm border border-gray-300 shadow-sm flex flex-col justify-between">
        <span class="text-[11px] font-bold uppercase text-gray-500 tracking-wider">Expired Today</span>
        <div class="mt-2">
            <span id="stat-expired-today" class="text-base font-extrabold text-gray-700">{{ $metrics['expired_sessions_today'] ?? 0 }}</span>
        </div>
    </div>
</div>

<div class="mb-6 bg-white border border-gray-300 shadow-sm p-4 rounded-sm flex flex-col md:flex-row md:justify-between md:items-center space-y-4 md:space-y-0">
    <div>
        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Filters</h3>
        <p class="text-xs text-gray-500 mt-1">Refine active users and transaction history</p>
    </div>
    
    <div>
        <form method="GET" action="{{ route('admin.dashboard') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
            <div class="flex items-center space-x-2">
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Search Phone, MAC, Txn ID..." class="border border-gray-300 px-3 py-1.5 text-sm rounded-sm focus:outline-none focus:border-gray-500 bg-white w-full sm:w-64">
                <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-white text-sm px-3.5 py-1.5 rounded-sm border border-gray-800 shadow-sm">
                    Search
                </button>
            </div>

            <div class="flex items-center space-x-2">
                <label class="text-sm font-medium text-gray-700">Status:</label>
                <select name="status" onchange="this.form.submit()" class="border border-gray-300 px-3 py-1.5 text-sm rounded-sm focus:outline-none focus:border-gray-500 bg-white">
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Failed</option>
                    <option value="expired" {{ $status === 'expired' ? 'selected' : '' }}>Expired</option>
                </select>
            </div>
            
            <div class="flex items-center space-x-2">
                <label class="text-sm font-medium text-gray-700">Time:</label>
                <select name="time" onchange="this.form.submit()" class="border border-gray-300 px-3 py-1.5 text-sm rounded-sm focus:outline-none focus:border-gray-500 bg-white">
                    <option value="all" {{ $time === 'all' ? 'selected' : '' }}>All Time</option>
                    <option value="today" {{ $time === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="yesterday" {{ $time === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                    <option value="this_week" {{ $time === 'this_week' ? 'selected' : '' }}>This Week</option>
                    <option value="last_week" {{ $time === 'last_week' ? 'selected' : '' }}>Last Week</option>
                    <option value="this_month" {{ $time === 'this_month' ? 'selected' : '' }}>This Month</option>
                    <option value="last_month" {{ $time === 'last_month' ? 'selected' : '' }}>Last Month</option>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
            <thead class="table-header text-xs uppercase font-semibold">
                <tr>
                    <th class="px-4 py-2 border-r border-gray-600">ID / Date</th>
                    <th class="px-4 py-2 border-r border-gray-600">Phone</th>
                    <th class="px-4 py-2 border-r border-gray-600">Package</th>
                    <th class="px-4 py-2 border-r border-gray-600">MAC Address</th>
                    <th class="px-4 py-2 border-r border-gray-600">Status</th>
                    <th class="px-4 py-2 border-r border-gray-600">Expires At</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @forelse($transactions as $txn)
                <tr class="table-row border-b border-gray-200">
                    <td class="px-4 py-2 font-mono text-xs">
                        {{ $txn->transaction_id }}<br>
                        <span class="text-gray-500">{{ \Carbon\Carbon::parse($txn->created_at)->format('Y-m-d H:i') }}</span>
                    </td>
                    <td class="px-4 py-2 font-medium">{{ $txn->phone_number }}</td>
                    <td class="px-4 py-2">
                        <span class="px-2 py-0.5 inline-flex text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 rounded">
                            {{ $txn->package_name ?? '-' }}
                        </span>
                    </td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $txn->mac_address }}</td>
                    <td class="px-4 py-2">
                        @if($txn->status === 'SUCCESS')
                            @if(is_null($txn->expires_at) || \Carbon\Carbon::parse($txn->expires_at)->isPast())
                                <span class="px-2 py-0.5 inline-flex text-xs font-bold bg-red-100 text-red-800 border border-red-300 rounded">EXPIRED</span>
                            @else
                                <span class="px-2 py-0.5 inline-flex text-xs font-bold bg-green-100 text-green-800 border border-green-300 rounded">ACTIVE</span>
                            @endif
                        @elseif($txn->status === 'PENDING')
                            <span class="px-2 py-0.5 inline-flex text-xs font-bold bg-yellow-100 text-yellow-800 border border-yellow-300 rounded">PENDING</span>
                        @else
                            <span class="px-2 py-0.5 inline-flex text-xs font-bold bg-gray-100 text-gray-800 border border-gray-300 rounded">{{ $txn->status }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-xs">
                        {{ $txn->expires_at ?? '-' }}
                    </td>
                    <td class="px-4 py-2 text-right flex justify-end items-center space-x-2">
                        @if($txn->status === 'SUCCESS')
                            <form method="POST" action="{{ route('admin.extend', $txn->id) }}" class="flex items-center m-0">
                                @csrf
                                <input type="number" name="extend_hours" min="1" value="1" class="border border-gray-300 w-12 px-1 py-0.5 text-xs focus:outline-none focus:border-gray-500 rounded-sm" required>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-2 py-0.5 ml-1 rounded-sm border border-blue-700 shadow-sm">+ Hrs</button>
                            </form>

                            <form method="POST" action="{{ route('admin.kick', $txn->id) }}" onsubmit="return confirm('Disconnect this MAC from router instantly?');" class="m-0">
                                @csrf
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs px-2 py-0.5 rounded-sm border border-red-700 shadow-sm">Kick</button>
                            </form>
                        @else
                            @if($txn->status === 'PENDING')
                                <form method="POST" action="{{ route('admin.reconnect', $txn->id) }}" onsubmit="return confirm('Reconnect this user by transferring their active package to this new MAC address?');" class="m-0 mr-1">
                                    @csrf
                                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-xs px-2 py-0.5 rounded-sm shadow-sm border border-green-700">Reconnect</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.txn.destroy', $txn->id) }}" onsubmit="return confirm('Are you sure you want to delete this failed/pending transaction?');" class="m-0">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="bg-gray-500 hover:bg-gray-600 text-white text-xs px-2 py-0.5 rounded-sm shadow-sm">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-4 text-center text-gray-500 text-sm">No transactions found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
        {{ $transactions->links() }}
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        function updateLiveMetrics() {
            fetch("{{ route('admin.live_metrics') }}")
                .then(response => response.json())
                .then(data => {
                    if (data) {
                        const onlineElem = document.getElementById("stat-online-users");
                        if (onlineElem) onlineElem.textContent = "🟢 " + (data.online_users || 0);

                        const revElem = document.getElementById("stat-revenue-today");
                        if (revElem) revElem.textContent = data.revenue_today_formatted || "TZS 0";

                        const bwElem = document.getElementById("stat-current-bandwidth");
                        if (bwElem) bwElem.textContent = data.current_bandwidth || "0 Mbps";

                        const statusElem = document.getElementById("stat-internet-status");
                        if (statusElem) {
                            if (data.internet_status) {
                                statusElem.className = "inline-flex items-center text-xs font-bold text-green-700 bg-green-50 px-1.5 py-0.5 rounded border border-green-200";
                                statusElem.textContent = "🟢 Online";
                            } else {
                                statusElem.className = "inline-flex items-center text-xs font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded border border-red-200";
                                statusElem.textContent = "🔴 Offline";
                            }
                        }

                        const cpuElem = document.getElementById("stat-router-cpu");
                        if (cpuElem) cpuElem.textContent = data.router_cpu || "N/A";

                        const memElem = document.getElementById("stat-router-memory");
                        if (memElem) memElem.textContent = data.router_memory || "N/A";

                        const expElem = document.getElementById("stat-expired-today");
                        if (expElem) expElem.textContent = data.expired_sessions_today || 0;
                    }
                })
                .catch(err => console.warn("Live metrics update failed:", err));
        }

        // Poll live metrics every 5 seconds without reloading the page
        setInterval(updateLiveMetrics, 5000);
    });
</script>
@endsection
