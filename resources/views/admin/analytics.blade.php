@extends('admin.layout')

@section('title', 'Checkout Analytics')

@section('content')
<div class="mb-6 bg-white border border-gray-300 shadow-sm p-4 rounded-sm flex justify-between items-center">
    <div>
        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Checkout Page Visitors</h3>
        <p class="text-xs text-gray-500 mt-1">Track users who land on the checkout page and see if they proceed to pay.</p>
    </div>
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
@endsection
