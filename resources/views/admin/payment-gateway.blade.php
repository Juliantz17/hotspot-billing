@extends('admin.layout')
@section('title', 'Payment Gateway')
@section('content')
<div class="max-w-xl bg-white border border-gray-300 shadow-sm rounded-sm p-5">
    <h3 class="text-base font-semibold text-gray-900">Active payment gateway</h3>
    <p class="text-sm text-gray-500 mt-1 mb-5">This applies to new checkouts only. Payments already started continue through their original gateway.</p>
    <form method="POST" action="{{ route('admin.payment_gateway.update') }}" class="space-y-4">
        @csrf
        @method('PUT')
        @foreach($gateways as $gateway)
            <label class="flex items-center gap-3 border border-gray-200 rounded-sm p-3 cursor-pointer">
                <input type="radio" name="payment_gateway" value="{{ $gateway }}" @checked($activeGateway === $gateway)>
                <span class="font-medium text-gray-900">{{ $gateway === 'azampay' ? 'Azam Pay' : ucfirst($gateway) }}</span>
            </label>
        @endforeach
        <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-white text-sm px-4 py-2 rounded-sm border border-gray-900">Save gateway</button>
    </form>
</div>
@endsection
