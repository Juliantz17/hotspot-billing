@extends('admin.layout')

@section('title', 'Earnings Report')

@section('content')
<div class="mb-6 bg-white border border-gray-300 shadow-sm p-4 rounded-sm flex justify-between items-center">
    <div>
        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Total Earnings</h3>
        <p class="text-3xl font-bold text-gray-900">{{ number_format($totalEarnings) }} TZS</p>
        <p class="text-xs text-gray-500 mt-1">From {{ number_format($transactionCount) }} successful transactions</p>
    </div>
    
    <div>
        <form method="GET" action="{{ route('admin.earnings') }}" class="flex items-center space-x-2">
            <label class="text-sm font-medium text-gray-700">Filter By:</label>
            <select name="filter" onchange="this.form.submit()" class="border border-gray-300 px-3 py-1.5 text-sm rounded-sm focus:outline-none focus:border-gray-500 bg-white">
                <option value="today" {{ $filter === 'today' ? 'selected' : '' }}>Today</option>
                <option value="yesterday" {{ $filter === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                <option value="this_week" {{ $filter === 'this_week' ? 'selected' : '' }}>This Week</option>
                <option value="last_week" {{ $filter === 'last_week' ? 'selected' : '' }}>Last Week</option>
                <option value="this_month" {{ $filter === 'this_month' ? 'selected' : '' }}>This Month</option>
                <option value="last_month" {{ $filter === 'last_month' ? 'selected' : '' }}>Last Month</option>
                <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>All Time</option>
            </select>
            <label class="text-sm font-medium text-gray-700 ml-3">Group Users By:</label>
            <select name="group_by" onchange="this.form.submit()" class="border border-gray-300 px-3 py-1.5 text-sm rounded-sm focus:outline-none focus:border-gray-500 bg-white">
                <option value="phone" {{ $groupBy === 'phone' ? 'selected' : '' }}>Phone</option>
                <option value="mac" {{ $groupBy === 'mac' ? 'selected' : '' }}>MAC Address</option>
            </select>
        </form>
    </div>
</div>

<div class="bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden mb-6">
    <div class="px-4 py-3 border-b border-gray-200">
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">
            Customer Total Earnings by {{ $groupBy === 'mac' ? 'MAC Address' : 'Phone' }}
        </h3>
        <p class="text-xs text-gray-500 mt-1">Successful payments within the selected period.</p>
    </div>
    <table class="w-full text-sm text-left whitespace-nowrap">
        <thead class="table-header text-xs uppercase font-semibold">
            <tr>
                <th class="px-4 py-2 border-r border-gray-600">{{ $groupBy === 'mac' ? 'MAC Address' : 'Customer Phone' }}</th>
                <th class="px-4 py-2 border-r border-gray-600 text-right">Transactions</th>
                <th class="px-4 py-2 border-r border-gray-600">Last Payment</th>
                <th class="px-4 py-2 text-right">Total Earnings (TZS)</th>
            </tr>
        </thead>
        <tbody class="text-gray-700">
            @forelse($customerEarnings as $customer)
            <tr class="table-row border-b border-gray-200">
                <td class="px-4 py-2 {{ $groupBy === 'mac' ? 'font-mono text-xs' : 'font-medium' }}">
                    {{ $groupBy === 'mac' ? strtoupper($customer->customer_identifier) : $customer->customer_identifier }}
                </td>
                <td class="px-4 py-2 text-right">{{ number_format($customer->transaction_count) }}</td>
                <td class="px-4 py-2 text-xs text-gray-600">
                    {{ \Carbon\Carbon::parse($customer->last_payment_at)->format('Y-m-d H:i') }}
                </td>
                <td class="px-4 py-2 text-right font-semibold text-green-700">{{ number_format($customer->total_earnings) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="px-4 py-6 text-center text-gray-500 text-sm">No customer earnings found for this period.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
        {{ $customerEarnings->links() }}
    </div>
</div>

<div class="bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-200">
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Individual Transactions</h3>
    </div>
    <table class="w-full text-sm text-left whitespace-nowrap">
        <thead class="table-header text-xs uppercase font-semibold">
            <tr>
                <th class="px-4 py-2 border-r border-gray-600">Transaction ID / Date</th>
                <th class="px-4 py-2 border-r border-gray-600">Customer Phone</th>
                <th class="px-4 py-2 text-right">Amount (TZS)</th>
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
                <td class="px-4 py-2 text-right font-semibold text-green-700">
                    +{{ number_format($txn->amount) }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="3" class="px-4 py-6 text-center text-gray-500 text-sm">No successful transactions found for this period.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
        {{ $transactions->links() }}
    </div>
</div>
@endsection
