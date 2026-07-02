@extends('admin.layout')

@section('title', 'Active Users & Transactions')

@section('content')
<div class="bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
            <thead class="table-header text-xs uppercase font-semibold">
                <tr>
                    <th class="px-4 py-2 border-r border-gray-600">ID / Date</th>
                    <th class="px-4 py-2 border-r border-gray-600">Phone</th>
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
                    <td colspan="6" class="px-4 py-4 text-center text-gray-500 text-sm">No transactions found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
        {{ $transactions->links() }}
    </div>
</div>
@endsection
