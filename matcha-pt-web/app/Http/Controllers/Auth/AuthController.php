<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\MatchaDummyDataService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister()
    {
        $communities = MatchaDummyDataService::getCommunities();
        return view('auth.register', compact('communities'));
    }

    public function login(Request $request)
    {
        // Prototype authentication simulation
        return redirect()->route('dashboard')->with('success', 'Berhasil masuk ke akun Matcha!');
    }

    public function register(Request $request)
    {
        // Prototype registration simulation
        return redirect()->route('dashboard')->with('success', 'Pendaftaran member berhasil! Selamat datang di Matcha.');
    }

    public function logout()
    {
        return redirect()->route('dashboard');
    }
}
