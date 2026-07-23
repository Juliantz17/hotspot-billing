@extends('admin.layout')

@section('title', 'MikroTik Logs')

@section('content')
@php
    if (! function_exists('routerLogSeverity')) {
        function routerLogSeverity($topics) {
            $topics = strtolower((string) $topics);

            if (str_contains($topics, 'critical') || str_contains($topics, 'error')) {
                return ['label' => 'Error', 'class' => 'text-red-700 bg-red-50 border-red-200'];
            }

            if (str_contains($topics, 'warning')) {
                return ['label' => 'Warning', 'class' => 'text-amber-700 bg-amber-50 border-amber-200'];
            }

            if (str_contains($topics, 'info')) {
                return ['label' => 'Info', 'class' => 'text-blue-700 bg-blue-50 border-blue-200'];
            }

            return ['label' => 'Log', 'class' => 'text-gray-700 bg-gray-50 border-gray-200'];
        }
    }
@endphp

@if($error)
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <p class="text-sm text-red-700 font-medium">{{ $error }}</p>
    </div>
@endif

<div class="mb-6 bg-white border border-gray-300 shadow-sm p-4 rounded-sm flex justify-between items-center gap-4">
    <div>
        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">RouterOS System Logs</h3>
        <p class="text-xs text-gray-500 mt-1">Latest 100 MikroTik log entries, newest first.</p>
    </div>
    <a href="{{ route('admin.logs') }}" class="bg-gray-800 hover:bg-gray-700 text-white text-xs px-3 py-1.5 rounded-sm border border-gray-800 shadow-sm shrink-0">Refresh</a>
</div>

<div class="bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="table-header text-xs uppercase font-semibold">
                <tr>
                    <th class="px-4 py-2 border-r border-gray-600 whitespace-nowrap">Time</th>
                    <th class="px-4 py-2 border-r border-gray-600 whitespace-nowrap">Topics</th>
                    <th class="px-4 py-2 border-r border-gray-600 whitespace-nowrap">Level</th>
                    <th class="px-4 py-2">Message</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @forelse($logs as $log)
                    @php($severity = routerLogSeverity($log['topics']))
                    <tr class="table-row border-b border-gray-200 align-top">
                        <td class="px-4 py-2 font-mono text-xs text-gray-900 whitespace-nowrap">{{ $log['time'] }}</td>
                        <td class="px-4 py-2 font-mono text-xs text-gray-700 whitespace-nowrap">{{ $log['topics'] }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <span class="text-xs font-bold border px-2 py-0.5 rounded {{ $severity['class'] }}">{{ $severity['label'] }}</span>
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-800 min-w-96">{{ $log['message'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500 text-sm">No logs found on the router.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
