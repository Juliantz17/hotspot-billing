<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotspot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Corporate / Data-dense styling */
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; color: #111827; }
        .table-header { background-color: #374151; color: #ffffff; }
        .table-row:nth-child(even) { background-color: #f9fafb; }
        .table-row:hover { background-color: #f3f4f6; }
        .sidebar-link { transition: all 0.2s; }
        .sidebar-link:hover { background-color: #374151; color: white; }
    </style>
</head>
<body class="flex flex-col md:flex-row h-screen overflow-hidden">
    <!-- Sidebar -->
    <div class="w-full md:w-64 bg-gray-900 text-gray-300 flex flex-col md:h-full shrink-0">
        <div class="p-4 border-b border-gray-700 bg-black flex justify-between items-center">
            <h1 class="text-lg font-bold text-white tracking-wide uppercase">Hotspot Admin</h1>
            <button class="md:hidden text-white" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
            </button>
        </div>
        <div id="mobile-menu" class="hidden md:flex flex-col flex-1 overflow-y-auto">
            <div class="py-4 space-y-1">
                <nav class="space-y-1">
                    <a href="{{ route('admin.dashboard') }}" class="sidebar-link flex items-center px-4 py-2 text-sm font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-gray-800 text-white' : '' }}">
                        <svg class="w-5 h-5 mr-3 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Dashboard / Users
                    </a>
                    <a href="{{ route('admin.packages') }}" class="sidebar-link flex items-center px-4 py-2 text-sm font-medium {{ request()->routeIs('admin.packages') ? 'bg-gray-800 text-white' : '' }}">
                        <svg class="w-5 h-5 mr-3 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        Manage Packages
                    </a>
                    <a href="{{ route('admin.earnings') }}" class="sidebar-link flex items-center px-4 py-2 text-sm font-medium {{ request()->routeIs('admin.earnings') ? 'bg-gray-800 text-white' : '' }}">
                        <svg class="w-5 h-5 mr-3 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Earnings Report
                    </a>
                </nav>
            </div>
            <div class="mt-auto p-4 border-t border-gray-700">
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="w-full bg-gray-700 hover:bg-gray-600 text-white text-sm py-1.5 px-3 rounded font-medium">Log Out</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col h-full overflow-hidden">
        <header class="bg-white shadow-sm border-b border-gray-200 p-4 shrink-0">
            <h2 class="text-xl font-semibold text-gray-800">@yield('title')</h2>
        </header>
        <main class="flex-1 overflow-y-auto p-4 bg-gray-50">
            @if(session('success'))
                <div class="bg-green-50 border-l-4 border-green-500 p-3 mb-4">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                </div>
            @endif
            @if($errors->any())
                <div class="bg-red-50 border-l-4 border-red-500 p-3 mb-4">
                    <p class="text-sm text-red-700">{{ $errors->first() }}</p>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
