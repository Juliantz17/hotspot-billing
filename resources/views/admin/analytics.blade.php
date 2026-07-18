@extends('admin.layout')

@section('title', 'Checkout Analytics')

@section('content')
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Checkout Visits</h3>
        <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalVisits) }}</p>
        <span class="text-[10px] text-gray-400">All-time page hits</span>
    </div>
    
    <div class="bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Unique Devices Seen</h3>
        <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($uniqueVisits) }}</p>
        <span class="text-[10px] text-gray-400">Unique MAC addresses</span>
    </div>

    <div class="bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Unique Payers</h3>
        <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($uniquePaid) }}</p>
        <span class="text-[10px] text-gray-400">Unique successful buyers</span>
    </div>

    <div class="bg-blue-900 text-white border border-blue-950 p-4 rounded-sm shadow-sm flex flex-col justify-between">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-blue-200">Device Conversion Rate</h3>
        <p class="text-3xl font-extrabold mt-1 text-white">{{ $conversionRate }}%</p>
        <span class="text-[10px] text-blue-300">Payer MACs / Visitor MACs</span>
    </div>
</div>

<!-- Conversion Chart -->
<div class="mb-6 bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">7-Day Conversion Trend</h3>
    <div class="w-full" style="height: 250px;">
        <canvas id="conversionChart"></canvas>
    </div>
</div>

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
        const ctx = document.getElementById('conversionChart').getContext('2d');
        const labels = @json($chartLabels);
        const rates = @json($chartRates);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Conversion Rate (%)',
                    data: rates,
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
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Conversion Rate: ${context.parsed.y}%`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + "%";
                            }
                        }
                    }
                }
            }
        });
    });
</script>
@endsection
