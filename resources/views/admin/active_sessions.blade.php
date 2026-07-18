@extends('admin.layout')

@section('title', 'Router Active Hotspot Sessions')

@section('content')
@php
    if (!function_exists('formatBytes')) {
        function formatBytes($bytes, $precision = 2) {
            $bytes = floatval($bytes);
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $bytes = max($bytes, 0);
            $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
            $pow = min($pow, count($units) - 1);
            $bytes /= pow(1024, $pow);
            return round($bytes, $precision) . ' ' . $units[$pow];
        }
    }
@endphp

@if($error)
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-700 font-medium">
                    {{ $error }}
                </p>
            </div>
        </div>
    </div>
@endif

<div class="mb-6 bg-white border border-gray-300 shadow-sm p-4 rounded-sm flex justify-between items-center">
    <div>
        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Router Host Connections</h3>
        <p class="text-xs text-gray-500 mt-1">Live connected hosts queried directly from the MikroTik router hosts list.</p>
    </div>
    <div>
        <a href="{{ route('admin.active_sessions') }}" class="bg-gray-800 hover:bg-gray-700 text-white text-xs px-3 py-1.5 rounded-sm border border-gray-800 shadow-sm">
            Refresh
        </a>
    </div>
</div>

<div class="bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
            <thead class="table-header text-xs uppercase font-semibold">
                <tr>
                    <th class="px-4 py-2 border-r border-gray-600">MAC Address</th>
                    <th class="px-4 py-2 border-r border-gray-600">IP Address</th>
                    <th class="px-4 py-2 border-r border-gray-600">Status</th>
                    <th class="px-4 py-2 border-r border-gray-600">Rates (Rx / Tx)</th>
                    <th class="px-4 py-2 border-r border-gray-600">Idle Time</th>
                    <th class="px-4 py-2 border-r border-gray-600">Current Session</th>
                    <th class="px-4 py-2 border-r border-gray-600">Cumulative Package</th>
                    <th class="px-4 py-2 border-r border-gray-600">Comment</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @forelse($activeSessions as $session)
                <tr class="table-row border-b border-gray-200">
                    <td class="px-4 py-2 font-mono text-xs font-semibold text-gray-900">
                        {{ $session['user'] }}
                    </td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $session['address'] }}</td>
                    <td class="px-4 py-2">
                        @if($session['bypassed'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-800 border border-green-300">
                                Bypassed (Active)
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-800 border border-gray-300">
                                DHCP Connected
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-600">
                        {{ $session['rx-rate'] }} / {{ $session['tx-rate'] }}
                    </td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $session['idle-time'] }}</td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-600">
                        <span class="text-gray-400">Rx:</span> {{ formatBytes($session['bytes-in']) }}<br>
                        <span class="text-gray-400">Tx:</span> {{ formatBytes($session['bytes-out']) }}
                    </td>
                    <td class="px-4 py-2 font-mono text-xs text-blue-900 font-semibold">
                        @if($session['bypassed'] && ($session['queue-in'] > 0 || $session['queue-out'] > 0))
                            <span class="text-blue-500">Rx:</span> {{ formatBytes($session['queue-in']) }}<br>
                            <span class="text-blue-500">Tx:</span> {{ formatBytes($session['queue-out']) }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-4 py-2 text-xs text-gray-500 italic max-w-xs truncate" title="{{ $session['comment'] }}">
                        {{ $session['comment'] }}
                    </td>
                    <td class="px-4 py-2 text-right">
                        @if(isset($session['.id']))
                            <form method="POST" action="{{ route('admin.active_sessions.kick', $session['.id']) }}" onsubmit="return confirm('Remove this host connection from the router hosts list?');" class="m-0 inline-block">
                                @csrf
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs px-2.5 py-0.5 rounded-sm border border-red-700 shadow-sm">
                                    Remove Host
                                </button>
                            </form>
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-6 text-center text-gray-500 text-sm">No connected hosts found on the router.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
