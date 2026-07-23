<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Hotspot Billing</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, sans-serif; background: #f5f5f7; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 text-gray-950">
    <div class="w-full max-w-sm bg-white/90 backdrop-blur border border-gray-200 rounded-2xl shadow-[0_24px_70px_rgba(15,23,42,.12)] p-7">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-4 h-12 w-12 rounded-2xl bg-gray-950 text-white flex items-center justify-center shadow-sm">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
            <h1 class="text-2xl font-semibold tracking-normal">Admin Login</h1>
        </div>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-gray-700 text-sm font-medium mb-1.5" for="username">Username</label>
                <input class="border border-gray-300 rounded-xl w-full py-2.5 px-3 text-gray-900 bg-white focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500" id="username" name="username" type="text" placeholder="Username" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-medium mb-1.5" for="password">Password</label>
                <input class="border border-gray-300 rounded-xl w-full py-2.5 px-3 text-gray-900 bg-white focus:outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-500" id="password" name="password" type="password" placeholder="Password" required>
            </div>
            <button class="w-full bg-gray-950 hover:bg-gray-800 text-white font-semibold py-2.5 px-4 rounded-xl shadow-sm" type="submit">
                Sign In
            </button>
        </form>
    </div>
</body>
</html>
