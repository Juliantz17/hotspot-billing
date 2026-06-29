<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internet Hotspot Portal</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">

    <div class="bg-white p-6 rounded-2xl shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">Wi-Fi High Speed</h2>
        <p class="text-sm text-gray-500 text-center mb-6">Select a plan and pay with Mobile Money (M-Pesa, Mixx, Airtel Money,Halo Pesa)</p>

        <form action="{{ route('hotspot.pay') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="mac" value="{{ $mac }}">

            <div class="bg-blue-50 text-blue-700 text-xs p-3 rounded-lg border border-blue-200">
                Your Device MAC: <span class="font-mono font-bold">{{ $mac }}</span>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Choose Bundle</label>
                <select name="package" class="w-full border border-gray-300 p-3 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="1hour">1 Hour Access — 500 TZS</option>
                    <option value="24hours">24 Hours Access — 2,000 TZS</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Money Number</label>
                <input type="text" name="phone" placeholder="e.g. 0712345678" required
                       class="w-full border border-gray-300 p-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500">
                @error('phone')
                    <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold p-3 rounded-xl transition duration-200 shadow-sm mt-2">
                Pay and Connect
            </button>
        </form>
    </div>

</body>
</html>