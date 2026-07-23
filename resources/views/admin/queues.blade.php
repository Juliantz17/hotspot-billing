@extends('admin.layout')

@section('title', 'Simple Queues')

@section('content')
@php
    if (!function_exists('formatQueueBytes')) {
        function formatQueueBytes($bytes, $precision = 2) {
            $bytes = floatval($bytes);
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $bytes = max($bytes, 0);
            $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
            $pow = min($pow, count($units) - 1);
            $bytes /= pow(1024, $pow);
            return round($bytes, $precision).' '.$units[$pow];
        }

        function formatQueueBytePair($value) {
            $parts = explode('/', (string) $value);
            if (count($parts) !== 2) {
                return $value ?: '0/0';
            }
            return formatQueueBytes($parts[0]).' / '.formatQueueBytes($parts[1]);
        }
    }
@endphp

@if($error)
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <p class="text-sm text-red-700 font-medium">{{ $error }}</p>
    </div>
@endif

<div class="mb-6 bg-white border border-gray-300 shadow-sm p-4 rounded-sm flex justify-between items-center">
    <div>
        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">MikroTik Simple Queues</h3>
        <p class="text-xs text-gray-500 mt-1">Rate-limit queues used to enforce package speeds for bypassed hotspot devices.</p>
    </div>
    <a href="{{ route('admin.queues') }}" class="bg-gray-800 hover:bg-gray-700 text-white text-xs px-3 py-1.5 rounded-sm border border-gray-800 shadow-sm">Refresh</a>
</div>

<div class="bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
            <thead class="table-header text-xs uppercase font-semibold">
                <tr>
                    <th class="px-4 py-2 border-r border-gray-600">Name</th>
                    <th class="px-4 py-2 border-r border-gray-600">Target</th>
                    <th class="px-4 py-2 border-r border-gray-600">Max Limit</th>
                    <th class="px-4 py-2 border-r border-gray-600">Limit At</th>
                    <th class="px-4 py-2 border-r border-gray-600">Current Rate</th>
                    <th class="px-4 py-2 border-r border-gray-600">Bytes</th>
                    <th class="px-4 py-2 border-r border-gray-600">Packets</th>
                    <th class="px-4 py-2 border-r border-gray-600">State</th>
                    <th class="px-4 py-2">Comment</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @forelse($queues as $queue)
                    <tr class="table-row border-b border-gray-200">
                        <td class="px-4 py-2 font-mono text-xs font-semibold text-gray-900">{{ $queue['name'] }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $queue['target'] }}</td>
                        <td class="px-4 py-2 font-mono text-xs text-blue-800">{{ $queue['max_limit'] }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $queue['limit_at'] }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $queue['rate'] }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ formatQueueBytePair($queue['bytes']) }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $queue['packets'] }}</td>
                        <td class="px-4 py-2">
                            @if($queue['disabled'])
                                <span class="text-xs font-bold text-gray-600 bg-gray-100 border border-gray-200 px-2 py-0.5 rounded">Disabled</span>
                            @else
                                <span class="text-xs font-bold text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded">Enabled</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-500 italic max-w-xs truncate" title="{{ $queue['comment'] }}">{{ $queue['comment'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-6 text-center text-gray-500 text-sm">No simple queues found on the router.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
