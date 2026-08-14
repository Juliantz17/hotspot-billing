<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->session()->has('admin_logged_in')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function processLogin(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $adminUser = (string) config('admin.username', '');
        $adminPass = (string) config('admin.password', '');
        $username = (string) $request->input('username');
        $password = (string) $request->input('password');

        if ($adminUser !== '' && $adminPass !== ''
            && hash_equals($adminUser, $username)
            && hash_equals($adminPass, $password)) {
            $request->session()->regenerate();
            $request->session()->put('admin_logged_in', true);

            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors(['error' => 'Invalid credentials']);
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
