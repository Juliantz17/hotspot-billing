<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotspot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            color-scheme: light;
            --surface: rgba(255, 255, 255, 0.82);
            --surface-solid: #ffffff;
            --canvas: #f5f5f7;
            --line: #d8dde6;
            --line-strong: #b8c0cc;
            --text: #1d1d1f;
            --muted: #687083;
            --accent: #0a84ff;
        }

        * { letter-spacing: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, sans-serif;
            background: var(--canvas);
            color: var(--text);
            font-size: 14px;
        }

        .admin-shell {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.72), rgba(255,255,255,0.1) 42%),
                var(--canvas);
        }

        .admin-sidebar {
            background: linear-gradient(180deg, #151b27 0%, #0f1724 100%);
            border-right: 1px solid #263244;
            box-shadow: 18px 0 44px rgba(15, 23, 42, .14);
        }

        .admin-sidebar h1,
        .admin-sidebar button {
            color: #ffffff;
        }

        .sidebar-link {
            color: #cbd5e1;
            border: 1px solid transparent;
            border-radius: 10px;
            margin: 0 10px;
            transition: background-color .16s ease, border-color .16s ease, color .16s ease;
        }

        .sidebar-link:hover {
            background: rgba(255,255,255,.08);
            border-color: rgba(255,255,255,.12);
            color: #ffffff;
        }

        .sidebar-link.bg-gray-800 {
            background: rgba(255,255,255,.14) !important;
            border-color: rgba(255,255,255,.22);
            color: #ffffff !important;
            box-shadow: inset 3px 0 0 #0a84ff;
        }

        .table-header {
            background: #eef2f7;
            color: #243041;
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line-strong);
        }

        .table-header th {
            border-color: var(--line-strong) !important;
            font-size: 11px;
            letter-spacing: 0;
        }

        .table-row:nth-child(even) { background-color: #fafbfc; }
        .table-row:hover { background-color: #eef6ff; }

        main .bg-white {
            background: var(--surface-solid) !important;
            border-color: var(--line) !important;
        }

        main .shadow-sm { box-shadow: 0 14px 36px rgba(15, 23, 42, .06) !important; }
        main .rounded-sm { border-radius: 12px !important; }
        main input, main select, main textarea { border-radius: 9px !important; }
        main table { font-size: 13px; }
        main td, main th { vertical-align: middle; }
    </style>
</head>
<body class="admin-shell flex flex-col md:flex-row h-screen overflow-hidden">
    <div class="admin-sidebar w-full md:w-64 flex flex-col md:h-full shrink-0">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h1 class="text-[13px] font-semibold text-gray-950 tracking-normal">Hotspot Admin</h1>
            <button class="md:hidden text-gray-700" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" aria-label="Toggle navigation">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
            </button>
        </div>
        <div id="mobile-menu" class="hidden md:flex flex-col flex-1 overflow-y-auto">
            <div class="py-3 space-y-1">
                <nav class="space-y-1">
                    <a href="{{ route('admin.dashboard') }}" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-gray-800 text-white' : '' }}">
                        <svg class="w-5 h-5 mr-3 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Dashboard / Users
                    </a>
                    <a href="{{ route('admin.active_sessions') }}" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.active_sessions') ? 'bg-gray-800 text-white' : '' }}">
                        <svg class="w-5 h-5 mr-3 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
                        Active Router Sessions
                    </a>
                    <a href="{{ route('admin.router') }}" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.router') ? 'bg-gray-800 text-white' : '' }}">
                        <svg class="w-5 h-5 mr-3 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        Router Panel
                    </a>
                    <a href="{{ route('admin.queues') }}" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.queues') ? 'bg-gray-800 text-white' : '' }}">
                        <svg class="w-5 h-5 mr-3 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"></path></svg>
                        Simple Queues
                    </a>
                    <a href="{{ route('admin.logs') }}" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.logs') ? 'bg-gray-800 text-white' : '' }}">
                        <svg class="w-5 h-5 mr-3 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        MikroTik Logs
                    </a>
                    <a href="{{ route('admin.packages') }}" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.packages') ? 'bg-gray-800 text-white' : '' }}">
                        <svg class="w-5 h-5 mr-3 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        Manage Packages
                    </a>
                    <a href="{{ route('admin.earnings') }}" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.earnings') ? 'bg-gray-800 text-white' : '' }}">
                        <svg class="w-5 h-5 mr-3 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Earnings Report
                    </a>
                    <a href="{{ route('admin.analytics') }}" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.analytics') ? 'bg-gray-800 text-white' : '' }}">
                        <svg class="w-5 h-5 mr-3 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        Analytics
                    </a>
                </nav>
            </div>
            <div class="mt-auto p-4 border-t border-gray-200">
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="w-full bg-white hover:bg-gray-50 text-gray-800 text-sm py-2 px-3 rounded-lg font-medium border border-gray-200 shadow-sm">Log Out</button>
                </form>
            </div>
        </div>
    </div>

    <div class="flex-1 flex flex-col h-full overflow-hidden">
        <header class="bg-white/80 backdrop-blur border-b border-gray-200 px-5 py-3 shrink-0 flex justify-between items-center">
            <h2 class="text-xl font-semibold text-gray-950">@yield('title')</h2>
            <div id="router-status-badge">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 border border-gray-200">
                    <span class="w-2 h-2 mr-1.5 bg-gray-400 rounded-full animate-pulse"></span>
                    Router: Checking...
                </span>
            </div>
        </header>
        <main class="flex-1 overflow-y-auto p-4 md:p-5">
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 p-3 mb-4 rounded-xl shadow-sm">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                </div>
            @endif
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 p-3 mb-4 rounded-xl shadow-sm">
                    <p class="text-sm text-red-700">{{ $errors->first() }}</p>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const badge = document.getElementById("router-status-badge");

            function checkStatus() {
                fetch("{{ route('admin.router_status') }}")
                    .then(res => res.json())
                    .then(data => {
                        if (data.online) {
                            badge.innerHTML = `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700 border border-green-200">
                                <span class="w-2 h-2 mr-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                Router: Online
                            </span>`;
                        } else {
                            badge.innerHTML = `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200" title="${data.error || 'Connection Failed'}">
                                <span class="w-2 h-2 mr-1.5 bg-red-500 rounded-full"></span>
                                Router: Offline
                            </span>`;
                        }
                    })
                    .catch(() => {
                        badge.innerHTML = `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
                            Router: Offline
                        </span>`;
                    });
            }

            checkStatus();
            setInterval(checkStatus, 30000);
        });
    </script>
</body>
</html>

