@extends('admin.layout')

@section('title', 'Payment Webhook Audit')

@section('content')
<div class="mb-5 bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Gateway callbacks</h3>
            <p class="text-xs text-gray-500 mt-1">Every callback received by the application, including rejected and failed requests.</p>
        </div>
        <form method="GET" action="{{ route('admin.webhook_logs') }}" class="flex flex-col sm:flex-row gap-2">
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Order or gateway reference" class="border border-gray-300 rounded-sm px-3 py-2 text-sm w-full sm:w-64">
            <select name="gateway" class="border border-gray-300 rounded-sm px-3 py-2 text-sm">
                <option value="">All gateways</option>
                <option value="selcom" @selected(($filters['gateway'] ?? '') === 'selcom')>Selcom</option>
                <option value="azampay" @selected(($filters['gateway'] ?? '') === 'azampay')>AzamPay</option>
            </select>
            <select name="result" class="border border-gray-300 rounded-sm px-3 py-2 text-sm">
                <option value="">All results</option>
                <option value="processed" @selected(($filters['result'] ?? '') === 'processed')>Processed</option>
                <option value="rejected" @selected(($filters['result'] ?? '') === 'rejected')>Rejected</option>
                <option value="error" @selected(($filters['result'] ?? '') === 'error')>Error</option>
            </select>
            <button class="bg-gray-800 hover:bg-gray-700 text-white text-sm px-4 py-2 rounded-sm">Filter</button>
            <a href="{{ route('admin.webhook_logs') }}" class="border border-gray-300 text-gray-700 text-sm px-4 py-2 rounded-sm text-center">Reset</a>
        </form>
    </div>
</div>

<div class="bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
            <thead class="table-header text-xs uppercase font-semibold">
                <tr>
                    <th class="px-3 py-2">Received</th>
                    <th class="px-3 py-2">Gateway</th>
                    <th class="px-3 py-2">Order / Reference</th>
                    <th class="px-3 py-2">Gateway Status</th>
                    <th class="px-3 py-2">Result</th>
                    <th class="px-3 py-2">Local Transaction</th>
                    <th class="px-3 py-2 text-center">Details</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @forelse($logs as $log)
                    @php
                        $isError = filled($log->processing_error) || ($log->response_status ?? 0) >= 500;
                        $isRejected = ($log->response_status ?? 0) >= 400 && ($log->response_status ?? 0) < 500;
                        $resultLabel = $isError ? 'Processing failed' : ($isRejected ? 'Rejected' : (($log->response_status ?? 0) >= 200 && ($log->response_status ?? 0) < 300 ? 'Processed' : 'Received'));
                        $resultClass = $isError ? 'bg-red-100 text-red-800' : ($isRejected ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800');
                    @endphp
                    <tr class="table-row border-b border-gray-200 align-top">
                        <td class="px-3 py-2 font-mono text-xs">{{ \Carbon\Carbon::parse($log->received_at)->format('Y-m-d H:i:s') }}</td>
                        <td class="px-3 py-2 font-semibold">{{ $log->gateway === 'azampay' ? 'AzamPay' : ucfirst($log->gateway) }}</td>
                        <td class="px-3 py-2">
                            <div class="font-mono text-xs text-gray-900">{{ $log->order_id ?: 'Missing order ID' }}</div>
                            @if($log->gateway_reference)<div class="font-mono text-[11px] text-gray-500 mt-1">Ref: {{ $log->gateway_reference }}</div>@endif
                            @if($log->gateway_transaction_id)<div class="font-mono text-[11px] text-gray-500">Txn: {{ $log->gateway_transaction_id }}</div>@endif
                        </td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $log->payment_status ?: 'Unknown' }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $resultClass }}">{{ $resultLabel }}</span>
                            <div class="text-[11px] text-gray-500 mt-1">HTTP {{ $log->response_status ?? '—' }}</div>
                        </td>
                        <td class="px-3 py-2">
                            @if($log->local_transaction_id)
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">{{ $log->local_transaction_status }}</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">Not matched</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-center">
                            <button type="button" onclick="document.getElementById('webhook-{{ $log->id }}').classList.toggle('hidden')" class="border border-gray-300 px-2 py-1 rounded text-xs hover:bg-gray-50">View</button>
                        </td>
                    </tr>
                    <tr id="webhook-{{ $log->id }}" class="hidden border-b border-gray-200 bg-gray-50">
                        <td colspan="7" class="px-4 py-4">
                            @if($log->processing_error)
                                <div class="mb-3 p-3 border border-red-200 bg-red-50 text-red-800 text-xs rounded-sm"><strong>Error:</strong> {{ $log->processing_error }}</div>
                            @endif
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                <div>
                                    <h4 class="text-xs font-semibold uppercase text-gray-600 mb-1">Sanitized payload</h4>
                                    <pre class="bg-gray-900 text-gray-100 p-3 rounded-sm overflow-auto text-xs max-h-72 whitespace-pre-wrap">{{ $log->sanitized_payload ?: '{}' }}</pre>
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold uppercase text-gray-600 mb-1">Application response</h4>
                                    <pre class="bg-gray-900 text-gray-100 p-3 rounded-sm overflow-auto text-xs max-h-72 whitespace-pre-wrap">{{ $log->response_body ?: 'No response body recorded.' }}</pre>
                                    <p class="text-[11px] text-gray-500 mt-2">Source IP: {{ $log->source_ip ?: 'Unknown' }}</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No webhook callbacks found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">{{ $logs->links() }}</div>
</div>
@endsection
