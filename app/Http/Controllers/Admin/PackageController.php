<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index()
    {
        $packages = Package::paginate(20);
        return view('admin.packages.index', compact('packages'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:1',
            'price' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'speed_limit' => 'nullable|string|max:50'
        ]);

        Package::create([
            'name' => $request->name,
            'duration_minutes' => $request->duration_minutes,
            'price' => $request->price,
            'is_active' => $request->has('is_active') ? true : false,
            'speed_limit' => $request->speed_limit,
        ]);

        return back()->with('success', 'Package created successfully.');
    }

    public function update(Request $request, Package $package)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:1',
            'price' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'speed_limit' => 'nullable|string|max:50'
        ]);

        $package->update([
            'name' => $request->name,
            'duration_minutes' => $request->duration_minutes,
            'price' => $request->price,
            'is_active' => $request->has('is_active') ? true : false,
            'speed_limit' => $request->speed_limit,
        ]);

        return back()->with('success', 'Package updated successfully.');
    }

    public function destroy(Package $package)
    {
        $package->delete();
        return back()->with('success', 'Package deleted successfully.');
    }
}
