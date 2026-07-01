@extends('admin.layout')

@section('title', 'Manage Packages')

@section('content')

<div class="mb-6 bg-white border border-gray-300 shadow-sm p-4 rounded-sm">
    <h3 class="text-lg font-semibold mb-3">Create New Package</h3>
    <form method="POST" action="{{ route('admin.packages.store') }}" class="flex items-end space-x-4">
        @csrf
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-700 mb-1">Package Name</label>
            <input type="text" name="name" class="w-full border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:border-gray-500 rounded-sm" placeholder="e.g., 2 Hours" required>
        </div>
        <div class="w-32">
            <label class="block text-xs font-medium text-gray-700 mb-1">Duration (Mins)</label>
            <input type="number" name="duration_minutes" class="w-full border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:border-gray-500 rounded-sm" placeholder="120" required>
        </div>
        <div class="w-32">
            <label class="block text-xs font-medium text-gray-700 mb-1">Price (TZS)</label>
            <input type="number" name="price" class="w-full border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:border-gray-500 rounded-sm" placeholder="1000" required>
        </div>
        <div class="w-32">
            <label class="block text-xs font-medium text-gray-700 mb-1">Speed Limit</label>
            <input type="text" name="speed_limit" class="w-full border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:border-gray-500 rounded-sm" placeholder="e.g. 5M/5M" title="Leave blank for unlimited, or use Mikrotik format e.g. 5M/5M">
        </div>
        <div class="w-24 flex items-center mb-1.5">
            <input type="checkbox" name="is_active" value="1" checked class="mr-2">
            <label class="text-xs font-medium text-gray-700">Active</label>
        </div>
        <div>
            <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-white text-sm font-medium px-4 py-1.5 rounded-sm shadow-sm border border-gray-900">Add Package</button>
        </div>
    </form>
</div>

<div class="bg-white border border-gray-300 shadow-sm rounded-sm overflow-hidden">
    <table class="w-full text-sm text-left whitespace-nowrap">
        <thead class="table-header text-xs uppercase font-semibold">
            <tr>
                <th class="px-4 py-2 border-r border-gray-600">ID</th>
                <th class="px-4 py-2 border-r border-gray-600">Name</th>
                <th class="px-4 py-2 border-r border-gray-600">Duration</th>
                <th class="px-4 py-2 border-r border-gray-600">Price (TZS)</th>
                <th class="px-4 py-2 border-r border-gray-600">Speed Limit</th>
                <th class="px-4 py-2 border-r border-gray-600 text-center">Status</th>
                <th class="px-4 py-2 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="text-gray-700">
            @forelse($packages as $pkg)
            <tr class="table-row border-b border-gray-200">
                <form method="POST" action="{{ route('admin.packages.update', $pkg->id) }}">
                    @csrf
                    @method('PUT')
                    <td class="px-4 py-2 font-mono text-xs">{{ $pkg->id }}</td>
                    <td class="px-4 py-2">
                        <input type="text" name="name" value="{{ $pkg->name }}" class="border border-gray-300 px-1 py-0.5 text-sm w-full rounded-sm">
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" name="duration_minutes" value="{{ $pkg->duration_minutes }}" class="border border-gray-300 px-1 py-0.5 text-sm w-20 rounded-sm">
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" name="price" value="{{ $pkg->price }}" class="border border-gray-300 px-1 py-0.5 text-sm w-24 rounded-sm">
                    </td>
                    <td class="px-4 py-2">
                        <input type="text" name="speed_limit" value="{{ $pkg->speed_limit }}" placeholder="5M/5M" class="border border-gray-300 px-1 py-0.5 text-sm w-20 rounded-sm">
                    </td>
                    <td class="px-4 py-2 text-center">
                        <input type="checkbox" name="is_active" value="1" {{ $pkg->is_active ? 'checked' : '' }}>
                    </td>
                    <td class="px-4 py-2 text-right flex justify-end space-x-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-2 py-1 rounded-sm shadow-sm border border-blue-700">Save</button>
                </form>
                        <form method="POST" action="{{ route('admin.packages.destroy', $pkg->id) }}" onsubmit="return confirm('Delete this package permanently?');" class="m-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs px-2 py-1 rounded-sm shadow-sm border border-red-700">Delete</button>
                        </form>
                    </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-4 text-center text-gray-500 text-sm">No packages configured.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
        {{ $packages->links() }}
    </div>
</div>
@endsection
