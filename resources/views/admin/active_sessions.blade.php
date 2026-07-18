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
        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Live Active Sessions</h3>
        <p class="text-xs text-gray-500 mt-1">Live active connections queried directly from the MikroTik hotspot.</p>
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
                    <th class="px-4 py-2 border-r border-gray-600">User / MAC</th>
                    <th class="px-4 py-2 border-r border-gray-600">IP Address</th>
                    <th class="px-4 py-2 border-r border-gray-600">Uptime</th>
                    <th class="px-4 py-2 border-r border-gray-600">Uploaded</th>
                    <th class="px-4 py-2 border-r border-gray-600">Downloaded</th>
                    <th class="px-4 py-2 border-r border-gray-600">Session Comment</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @forelse($activeSessions as $session)
                <tr class="table-row border-b border-gray-200">
                    <td class="px-4 py-2 font-mono text-xs font-semibold text-gray-900">
                        {{ $session['user'] ?? ($session['mac-address'] ?? 'Unknown') }}
                    </td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $session['address'] ?? '-' }}</td>
                    <td class="px-4 py-2 font-mono text-xs text-blue-700 font-semibold">{{ $session['uptime'] ?? '-' }}</td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-600">
                        {{ formatBytes($session['bytes-in'] ?? 0) }}
                    </td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-600">
                        {{ formatBytes($session['bytes-out'] ?? 0) }}
                    </td>
                    <td class="px-4 py-2 text-xs text-gray-500 italic max-w-xs truncate" title="{{ $session['comment'] ?? '' }}">
                        {{ $session['comment'] ?? '-' }}
                    </td>
                    <td class="px-4 py-2 text-right">
                        @if(isset($session['.id']))
                            <form method="POST" action="{{ route('admin.active_sessions.kick', $session['.id']) }}" onsubmit="return confirm('Terminate this active connection from the router?');" class="m-0 inline-block">
                                @csrf
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs px-2.5 py-0.5 rounded-sm border border-red-700 shadow-sm">
                                    Disconnect
                                </button>
                            </form>
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-6 text-center text-gray-500 text-sm">No active sessions found on the router.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
