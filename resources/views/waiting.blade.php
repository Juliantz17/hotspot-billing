<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Payment...</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">

    <div class="bg-white p-6 rounded-2xl shadow-xl w-full max-w-md text-center border border-gray-100">
        
        <div class="relative flex items-center justify-center mx-auto mb-6">
            <div class="animate-spin inline-block w-16 h-16 border-4 border-blue-600 border-t-transparent rounded-full"></div>
            <div class="absolute w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center font-bold text-blue-600 text-xs">TZS</div>
        </div>
        
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Angalia Simu Yako!</h2>
        <p class="text-sm text-gray-600 mb-4 px-2">
            Tumekutumia ujumbe wa malipo (**STK Push**) kwenye simu yako. Tafadhali **weka PIN yako** ya sasa ya Mtandao ili kukamilisha ununuzi wa internet.
        </p>
        
        <div class="bg-gray-50 rounded-xl p-3 border border-gray-200 text-xs text-gray-500 font-mono mb-6">
            <span class="block text-gray-400 font-sans text-[10px] uppercase font-bold tracking-wider mb-1">Kumbukumbu ya Muamala</span>
            {{ $txn }}
        </div>
        
        @if($status === 'SUCCESS')
        <div class="flex items-center justify-center gap-2 text-xs text-green-600 font-medium bg-green-50 py-2.5 px-4 rounded-xl">
            <span>Malipo Yamekamilika! Tunakuunganisha...</span>
        </div>
        @elseif($status === 'FAILED')
        <div class="flex flex-col items-center justify-center gap-2 text-xs text-red-600 font-medium bg-red-50 py-2.5 px-4 rounded-xl">
            <span>Malipo Yameshindikana.</span>
            <a href="{{ route('hotspot.checkout', ['mac' => $mac]) }}" class="mt-1 bg-red-600 text-white px-3 py-1 rounded shadow-sm text-[10px] uppercase font-bold tracking-wider hover:bg-red-700">Jaribu Tena</a>
        </div>
        @else
        <div class="flex items-center justify-center gap-2 text-xs text-blue-600 font-medium bg-blue-50 py-2.5 px-4 rounded-xl animate-pulse">
            <span>Inasubiri malipo yako...</span>
        </div>
        @endif

        <p class="text-[11px] text-gray-400 mt-6">
            Ukurasa huu utajifunga na internet itafunguka yenyewe punde tu ukishaweka PIN yako.
        </p>
    </div>

    <script>
        // Only reload if the payment is still pending
        @if($status !== 'SUCCESS' && $status !== 'FAILED')
        setTimeout(function() {
            window.location.reload();
        }, 5000);
        @endif
    </script>

</body>
</html>