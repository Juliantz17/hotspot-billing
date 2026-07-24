@extends('admin.layout')

@section('title', 'Router Sessions')

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
                <p class="text-sm text-red-700 font-medium">{{ $error }}</p>
            </div>
        </div>
    </div>
@endif

<div class="mb-6 bg-white border border-gray-300 shadow-sm p-4 rounded-sm flex flex-col gap-4 lg:flex-row lg:justify-between lg:items-center">
    <div>
        <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Router Sessions & Bindings</h3>
        <p class="text-xs text-gray-500 mt-1">Active shows authenticated logins only. Hotspot Hosts show devices seen by hotspot. DHCP Leases show DHCP-connected devices. IP bindings show saved router rules. Hotspot Users show configured MikroTik users and whether they are authenticating.</p>
    </div>
    <div>
        <a href="{{ route('admin.active_sessions') }}" class="bg-gray-800 hover:bg-gray-700 text-white text-xs px-3 py-1.5 rounded-sm border border-gray-800 shadow-sm">
            Refresh
        </a>
    </div>
</div>

<div class="mb-4 bg-white border border-gray-300 shadow-sm rounded-sm p-2 flex flex-wrap gap-2" data-session-tabs>
    <button type="button" data-tab-button="active" class="session-tab active px-3 py-2 rounded-sm text-xs font-semibold border">
        Active Sessions <span class="ml-1 text-gray-500">{{ count($activeSessions) }}</span>
    </button>
    <button type="button" data-tab-button="hosts" class="session-tab px-3 py-2 rounded-sm text-xs font-semibold border">
        Hotspot Hosts <span class="ml-1 text-gray-500">{{ count($hostsList) }}</span>
    </button>
    <button type="button" data-tab-button="dhcp" class="session-tab px-3 py-2 rounded-sm text-xs font-semibold border">
        DHCP Leases <span class="ml-1 text-gray-500">{{ count($dhcpLeases) }}</span>
    </button>
    <button type="button" data-tab-button="bindings" class="session-tab px-3 py-2 rounded-sm text-xs font-semibold border">
        IP Bindings <span class="ml-1 text-gray-500">{{ count($ipBindings) }}</span>
    </button>
    <button type="button" data-tab-button="users" class="session-tab px-3 py-2 rounded-sm text-xs font-semibold border">
        Hotspot Users <span class="ml-1 text-gray-500">{{ count($routerUsers) }}</span>
    </button>
</div>

<style>
    .session-tab { background: #f8fafc; border-color: #cbd5e1; color: #334155; }
    .session-tab.active { background: #111827; border-color: #111827; color: #ffffff; }
    .session-tab.active span { color: #d1d5db; }
    .session-panel { display: none; }
    .session-panel.active { display: block; }
</style>

<div data-tab-panel="active" class="session-panel active bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-200">
        <h4 class="text-sm font-semibold text-gray-800">Authenticated Active Sessions</h4>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
            <thead class="table-header text-xs uppercase font-semibold">
                <tr>
                    <th class="px-4 py-2 border-r border-gray-600">User / MAC</th>
                    <th class="px-4 py-2 border-r border-gray-600">IP Address</th>
                    <th class="px-4 py-2 border-r border-gray-600">Status</th>
                    <th class="px-4 py-2 border-r border-gray-600">Rates (Rx / Tx)</th>
                    <th class="px-4 py-2 border-r border-gray-600">Uptime / Idle</th>
                    <th class="px-4 py-2 border-r border-gray-600">Active Usage</th>
                    <th class="px-4 py-2 border-r border-gray-600">Queue Cumulative</th>
                    <th class="px-4 py-2 border-r border-gray-600">Comment</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @forelse($activeSessions as $session)
                <tr class="table-row border-b border-gray-200">
                    <td class="px-4 py-2 font-mono text-xs font-semibold text-gray-900">
                        {{ $session['user'] }}<br><span class="text-gray-500 font-normal">{{ $session['mac'] }}</span>
                    </td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $session['address'] }}</td>
                    <td class="px-4 py-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-800 border border-green-300">Authenticated</span>
                        @if($session['host_seen'])
                            <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">Host Seen</span>
                        @endif
                        @if($session['has_binding'])
                            <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-slate-50 text-slate-700 border border-slate-200">Has Binding</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $session['rx-rate'] }} / {{ $session['tx-rate'] }}</td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $session['uptime'] }}<br><span class="text-gray-500">Idle: {{ $session['idle-time'] }}</span></td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-600">
                        <span class="text-gray-400">Rx:</span> {{ formatBytes($session['bytes-in']) }}<br>
                        <span class="text-gray-400">Tx:</span> {{ formatBytes($session['bytes-out']) }}
                    </td>
                    <td class="px-4 py-2 font-mono text-xs text-blue-900 font-semibold">
                        @if($session['queue-in'] > 0 || $session['queue-out'] > 0)
                            <span class="text-blue-500">Rx:</span> {{ formatBytes($session['queue-in']) }}<br>
                            <span class="text-blue-500">Tx:</span> {{ formatBytes($session['queue-out']) }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-4 py-2 text-xs text-gray-500 italic max-w-xs truncate" title="{{ $session['comment'] }}">{{ $session['comment'] }}</td>
                    <td class="px-4 py-2 text-right">
                        @if(!empty($session['host_id']))
                            <form method="POST" action="{{ route('admin.active_sessions.kick', $session['host_id']) }}" onsubmit="return confirm('Remove this host connection from the router?');" class="m-0 inline-block">
                                @csrf
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs px-2.5 py-0.5 rounded-sm border border-red-700 shadow-sm">Remove Host</button>
                            </form>
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-6 text-center text-gray-500 text-sm">No authenticated active hotspot users found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div data-tab-panel="hosts" class="session-panel bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-200">
        <h4 class="text-sm font-semibold text-gray-800">Hotspot Hosts</h4>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
            <thead class="table-header text-xs uppercase font-semibold">
                <tr>
                    <th class="px-4 py-2 border-r border-gray-600">MAC</th>
                    <th class="px-4 py-2 border-r border-gray-600">IP Address</th>
                    <th class="px-4 py-2 border-r border-gray-600">Status</th>
                    <th class="px-4 py-2 border-r border-gray-600">Rates (Rx / Tx)</th>
                    <th class="px-4 py-2 border-r border-gray-600">Uptime / Idle</th>
                    <th class="px-4 py-2 border-r border-gray-600">Host Usage</th>
                    <th class="px-4 py-2 border-r border-gray-600">Queue Cumulative</th>
                    <th class="px-4 py-2 border-r border-gray-600">Comment</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @forelse($hostsList as $host)
                <tr class="table-row border-b border-gray-200">
                    <td class="px-4 py-2 font-mono text-xs font-semibold text-gray-900">{{ $host['mac'] }}</td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $host['address'] }}</td>
                    <td class="px-4 py-2">
                        @if($host['authenticated'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-800 border border-green-300">Authenticated</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">Host Only</span>
                        @endif
                        @if($host['bypassed'])
                            <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200">Bypassed</span>
                        @endif
                        @if($host['has_binding'])
                            <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-slate-50 text-slate-700 border border-slate-200">Has Binding</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $host['rx-rate'] }} / {{ $host['tx-rate'] }}</td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $host['uptime'] }}<br><span class="text-gray-500">Idle: {{ $host['idle-time'] }}</span></td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-600">
                        <span class="text-gray-400">Rx:</span> {{ formatBytes($host['bytes-in']) }}<br>
                        <span class="text-gray-400">Tx:</span> {{ formatBytes($host['bytes-out']) }}
                    </td>
                    <td class="px-4 py-2 font-mono text-xs text-blue-900 font-semibold">
                        @if($host['queue-in'] > 0 || $host['queue-out'] > 0)
                            <span class="text-blue-500">Rx:</span> {{ formatBytes($host['queue-in']) }}<br>
                            <span class="text-blue-500">Tx:</span> {{ formatBytes($host['queue-out']) }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-4 py-2 text-xs text-gray-500 italic max-w-xs truncate" title="{{ $host['comment'] }}">{{ $host['comment'] }}</td>
                    <td class="px-4 py-2 text-right">
                        @if(!empty($host['.id']))
                            <form method="POST" action="{{ route('admin.active_sessions.kick', $host['.id']) }}" onsubmit="return confirm('Remove this host connection from the router?');" class="m-0 inline-block">
                                @csrf
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs px-2.5 py-0.5 rounded-sm border border-red-700 shadow-sm">Remove Host</button>
                            </form>
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-6 text-center text-gray-500 text-sm">No hotspot hosts found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>


<div data-tab-panel="dhcp" class="session-panel bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-200">
        <h4 class="text-sm font-semibold text-gray-800">DHCP Leases</h4>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
            <thead class="table-header text-xs uppercase font-semibold">
                <tr>
                    <th class="px-4 py-2 border-r border-gray-600">IP Address</th>
                    <th class="px-4 py-2 border-r border-gray-600">MAC</th>
                    <th class="px-4 py-2 border-r border-gray-600">Host Name</th>
                    <th class="px-4 py-2 border-r border-gray-600">DHCP Status</th>
                    <th class="px-4 py-2 border-r border-gray-600">Router Seen</th>
                    <th class="px-4 py-2 border-r border-gray-600">Last Seen / Expires</th>
                    <th class="px-4 py-2">Comment</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @forelse($dhcpLeases as $lease)
                <tr class="table-row border-b border-gray-200">
                    <td class="px-4 py-2 font-mono text-xs font-semibold text-gray-900">{{ $lease['address'] }}</td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $lease['mac'] }}</td>
                    <td class="px-4 py-2 text-xs">{{ $lease['host_name'] }}</td>
                    <td class="px-4 py-2">
                        @if($lease['disabled'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-700 border border-gray-300">Disabled</span>
                        @elseif($lease['status'] === 'bound')
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-800 border border-green-300">Bound</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">{{ $lease['status'] }}</span>
                        @endif
                        @if($lease['dynamic'])
                            <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">Dynamic</span>
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        @if($lease['router_active'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-800 border border-green-300">Authenticated</span>
                        @endif
                        @if($lease['host_seen'])
                            <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">Hotspot Host</span>
                        @endif
                        @if($lease['has_binding'])
                            <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-slate-50 text-slate-700 border border-slate-200">Has Binding</span>
                        @endif
                        @if(!$lease['router_active'] && !$lease['host_seen'] && !$lease['has_binding'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-700 border border-gray-300">DHCP Only</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $lease['last_seen'] }}<br><span class="text-gray-500">Expires: {{ $lease['expires_after'] }}</span></td>
                    <td class="px-4 py-2 text-xs text-gray-500 italic max-w-sm truncate" title="{{ $lease['comment'] }}">{{ $lease['comment'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-6 text-center text-gray-500 text-sm">No DHCP leases found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div data-tab-panel="bindings" class="session-panel bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-200">
        <h4 class="text-sm font-semibold text-gray-800">Hotspot IP Bindings</h4>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
            <thead class="table-header text-xs uppercase font-semibold">
                <tr>
                    <th class="px-4 py-2 border-r border-gray-600">MAC</th>
                    <th class="px-4 py-2 border-r border-gray-600">Address</th>
                    <th class="px-4 py-2 border-r border-gray-600">To Address</th>
                    <th class="px-4 py-2 border-r border-gray-600">Type</th>
                    <th class="px-4 py-2 border-r border-gray-600">State</th>
                    <th class="px-4 py-2 border-r border-gray-600">Server</th>
                    <th class="px-4 py-2">Comment</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @forelse($ipBindings as $binding)
                <tr class="table-row border-b border-gray-200">
                    <td class="px-4 py-2 font-mono text-xs font-semibold text-gray-900">{{ $binding['mac'] }}</td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $binding['address'] }}</td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $binding['to_address'] }}</td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $binding['type'] }}</td>
                    <td class="px-4 py-2">
                        @if($binding['disabled'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-700 border border-gray-300">Disabled</span>
                        @elseif($binding['authenticated'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-800 border border-green-300">Authenticated</span>
                        @elseif($binding['host_seen'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">Host Seen</span>
                        @elseif($binding['online'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-green-50 text-green-700 border border-green-200">Online</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">Not Connected</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $binding['server'] }}</td>
                    <td class="px-4 py-2 text-xs text-gray-500 italic max-w-sm truncate" title="{{ $binding['comment'] }}">{{ $binding['comment'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-6 text-center text-gray-500 text-sm">No hotspot IP bindings found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div data-tab-panel="users" class="session-panel bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-200">
        <h4 class="text-sm font-semibold text-gray-800">MikroTik Hotspot Users</h4>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
            <thead class="table-header text-xs uppercase font-semibold">
                <tr>
                    <th class="px-4 py-2 border-r border-gray-600">User</th>
                    <th class="px-4 py-2 border-r border-gray-600">MAC</th>
                    <th class="px-4 py-2 border-r border-gray-600">Profile / Server</th>
                    <th class="px-4 py-2 border-r border-gray-600">Uptime Limit</th>
                    <th class="px-4 py-2 border-r border-gray-600">Used</th>
                    <th class="px-4 py-2 border-r border-gray-600">Auth Check</th>
                    <th class="px-4 py-2">Comment</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @forelse($routerUsers as $user)
                <tr class="table-row border-b border-gray-200">
                    <td class="px-4 py-2 font-mono text-xs font-semibold text-gray-900">{{ $user['name'] }}</td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $user['mac'] }}</td>
                    <td class="px-4 py-2 text-xs">
                        <span class="font-semibold text-gray-900">{{ $user['profile'] }}</span><br>
                        <span class="text-gray-500">{{ $user['server'] }}</span>
                    </td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $user['limit_uptime'] }}</td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-600">
                        {{ $user['uptime'] }}<br>
                        <span class="text-gray-400">Rx:</span> {{ formatBytes($user['bytes_in']) }} / <span class="text-gray-400">Tx:</span> {{ formatBytes($user['bytes_out']) }}
                    </td>
                    <td class="px-4 py-2">
                        @if($user['disabled'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-700 border border-gray-300">Disabled</span>
                        @elseif($user['router_active'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-800 border border-green-300">Authenticated Now</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">Not Authenticated</span>
                        @endif
                        @if($user['host_seen'])
                            <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">Host Seen</span>
                        @endif
                        @if($user['has_binding'])
                            <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-slate-50 text-slate-700 border border-slate-200">Has Binding</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-xs text-gray-500 italic max-w-sm truncate" title="{{ $user['comment'] }}">{{ $user['comment'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-6 text-center text-gray-500 text-sm">No MikroTik hotspot users found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const buttons = document.querySelectorAll('[data-tab-button]');
        const panels = document.querySelectorAll('[data-tab-panel]');

        function activate(tab) {
            buttons.forEach((button) => button.classList.toggle('active', button.dataset.tabButton === tab));
            panels.forEach((panel) => panel.classList.toggle('active', panel.dataset.tabPanel === tab));
            if (history.replaceState) {
                history.replaceState(null, '', '#' + tab);
            }
        }

        buttons.forEach((button) => button.addEventListener('click', function () {
            activate(this.dataset.tabButton);
        }));

        const requested = window.location.hash.replace('#', '');
        if (requested && document.querySelector('[data-tab-button="' + requested + '"]')) {
            activate(requested);
        }
    });
</script>
@endsection
