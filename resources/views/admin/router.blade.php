@extends('admin.layout')

@section('title', 'Router Panel')

@section('content')
<div class="space-y-4">
    <div class="bg-white border border-gray-300 shadow-sm rounded-sm p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-gray-900">MikroTik Router Health</h3>
            <p class="text-xs text-gray-500 mt-1">Router uptime, resources, interface state, and maintenance actions.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.active_sessions') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm px-3 py-1.5 rounded-sm border border-gray-300">Hosts</a>
            <a href="{{ route('admin.queues') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm px-3 py-1.5 rounded-sm border border-gray-300">Queues</a>
            <button type="button" id="refresh-router" class="bg-gray-800 hover:bg-gray-700 text-white text-sm px-3 py-1.5 rounded-sm border border-gray-900">Refresh</button>
            <form method="POST" action="{{ route('admin.router_reboot') }}" onsubmit="return confirm('Reboot the MikroTik router now? Connected users will disconnect.');" class="m-0">
                @csrf
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm px-3 py-1.5 rounded-sm border border-red-700">Reboot Router</button>
            </form>
        </div>
    </div>

    <div id="router-error" class="{{ $router['online'] ? 'hidden' : '' }} bg-red-50 border border-red-200 text-red-700 text-sm p-3 rounded-sm">
        Router offline or unreachable: <span data-router-field="error">{{ $router['error'] ?? 'Connection failed' }}</span>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white border border-gray-300 shadow-sm rounded-sm p-3">
            <span class="block text-[11px] font-bold uppercase text-gray-500 tracking-wider">Status</span>
            <span data-router-status class="mt-2 inline-flex items-center text-xs font-bold px-2 py-0.5 rounded border {{ $router['online'] ? 'text-green-700 bg-green-50 border-green-200' : 'text-red-700 bg-red-50 border-red-200' }}">{{ $router['online'] ? 'Online' : 'Offline' }}</span>
        </div>
        <div class="bg-white border border-gray-300 shadow-sm rounded-sm p-3">
            <span class="block text-[11px] font-bold uppercase text-gray-500 tracking-wider">Identity</span>
            <span data-router-field="identity" class="mt-2 block text-base font-semibold text-gray-900">{{ $router['identity'] }}</span>
        </div>
        <div class="bg-white border border-gray-300 shadow-sm rounded-sm p-3">
            <span class="block text-[11px] font-bold uppercase text-gray-500 tracking-wider">Uptime</span>
            <span data-router-field="uptime" class="mt-2 block text-base font-semibold text-gray-900">{{ $router['uptime'] }}</span>
        </div>
        <div class="bg-white border border-gray-300 shadow-sm rounded-sm p-3">
            <span class="block text-[11px] font-bold uppercase text-gray-500 tracking-wider">RouterOS</span>
            <span data-router-field="version" class="mt-2 block text-base font-semibold text-gray-900">{{ $router['version'] }}</span>
        </div>
        <div class="bg-white border border-gray-300 shadow-sm rounded-sm p-3">
            <span class="block text-[11px] font-bold uppercase text-gray-500 tracking-wider">CPU</span>
            <span data-router-field="cpu_load" class="mt-2 block text-base font-semibold text-purple-700">{{ $router['cpu_load'] }}</span>
        </div>
        <div class="bg-white border border-gray-300 shadow-sm rounded-sm p-3">
            <span class="block text-[11px] font-bold uppercase text-gray-500 tracking-wider">Memory Used</span>
            <span data-router-field="memory_used" class="mt-2 block text-base font-semibold text-indigo-700">{{ $router['memory_used'] }}</span>
        </div>
        <div class="bg-white border border-gray-300 shadow-sm rounded-sm p-3">
            <span class="block text-[11px] font-bold uppercase text-gray-500 tracking-wider">Hosts</span>
            <span data-router-field="active_hotspot_users" class="mt-2 block text-base font-semibold text-gray-900">{{ $router['active_hotspot_users'] }}</span>
        </div>
        <div class="bg-white border border-gray-300 shadow-sm rounded-sm p-3">
            <span class="block text-[11px] font-bold uppercase text-gray-500 tracking-wider">Queues</span>
            <span data-router-field="queues" class="mt-2 block text-base font-semibold text-gray-900">{{ $router['queues'] }}</span>
        </div>
    </div>

    <div class="bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-sm font-semibold text-gray-800">Interfaces</h3>
            <span class="text-xs text-gray-500">First 8 interfaces</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left whitespace-nowrap">
                <thead class="table-header text-xs uppercase font-semibold">
                    <tr>
                        <th class="px-4 py-2">Name</th>
                        <th class="px-4 py-2">Type</th>
                        <th class="px-4 py-2">State</th>
                    </tr>
                </thead>
                <tbody id="router-interfaces" class="text-gray-700">
                    @forelse($router['interfaces'] as $interface)
                        <tr class="table-row border-b border-gray-200">
                            <td class="px-4 py-2 font-mono text-xs">{{ $interface['name'] }}</td>
                            <td class="px-4 py-2">{{ $interface['type'] }}</td>
                            <td class="px-4 py-2">
                                @if($interface['disabled'])
                                    <span class="text-xs font-bold text-gray-600 bg-gray-100 border border-gray-200 px-2 py-0.5 rounded">Disabled</span>
                                @elseif($interface['running'])
                                    <span class="text-xs font-bold text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded">Running</span>
                                @else
                                    <span class="text-xs font-bold text-yellow-700 bg-yellow-50 border border-yellow-200 px-2 py-0.5 rounded">Idle</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-4 text-center text-gray-500 text-sm">No interface data available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const refreshButton = document.getElementById('refresh-router');
        const errorBox = document.getElementById('router-error');
        const statusBadge = document.querySelector('[data-router-status]');
        const interfacesBody = document.getElementById('router-interfaces');

        function escapeHtml(value) {
            return String(value ?? '-').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
        }

        function setField(name, value) {
            const node = document.querySelector(`[data-router-field="${name}"]`);
            if (node) node.textContent = value ?? 'N/A';
        }

        function renderInterfaces(interfaces) {
            if (!interfaces || interfaces.length === 0) {
                interfacesBody.innerHTML = '<tr><td colspan="3" class="px-4 py-4 text-center text-gray-500 text-sm">No interface data available.</td></tr>';
                return;
            }

            interfacesBody.innerHTML = interfaces.map(item => {
                let state = '<span class="text-xs font-bold text-yellow-700 bg-yellow-50 border border-yellow-200 px-2 py-0.5 rounded">Idle</span>';
                if (item.disabled) state = '<span class="text-xs font-bold text-gray-600 bg-gray-100 border border-gray-200 px-2 py-0.5 rounded">Disabled</span>';
                if (!item.disabled && item.running) state = '<span class="text-xs font-bold text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded">Running</span>';
                return `<tr class="table-row border-b border-gray-200"><td class="px-4 py-2 font-mono text-xs">${escapeHtml(item.name)}</td><td class="px-4 py-2">${escapeHtml(item.type)}</td><td class="px-4 py-2">${state}</td></tr>`;
            }).join('');
        }

        function renderRouter(data) {
            ['identity', 'version', 'uptime', 'cpu_load', 'memory_used', 'active_hotspot_users', 'queues', 'error'].forEach(key => setField(key, data[key]));
            statusBadge.textContent = data.online ? 'Online' : 'Offline';
            statusBadge.className = data.online
                ? 'mt-2 inline-flex items-center text-xs font-bold px-2 py-0.5 rounded border text-green-700 bg-green-50 border-green-200'
                : 'mt-2 inline-flex items-center text-xs font-bold px-2 py-0.5 rounded border text-red-700 bg-red-50 border-red-200';
            errorBox.classList.toggle('hidden', !!data.online);
            renderInterfaces(data.interfaces || []);
        }

        function refreshRouter() {
            refreshButton.disabled = true;
            refreshButton.textContent = 'Refreshing...';
            fetch("{{ route('admin.router_snapshot') }}")
                .then(response => response.json())
                .then(renderRouter)
                .catch(error => renderRouter({ online: false, error: error.message, interfaces: [] }))
                .finally(() => {
                    refreshButton.disabled = false;
                    refreshButton.textContent = 'Refresh';
                });
        }

        refreshButton.addEventListener('click', refreshRouter);
        setInterval(refreshRouter, 30000);
    });
</script>
@endsection
