<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Player;
use App\Models\Community;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        $communities = Community::all();
        return view('auth.register', compact('communities'));
    }

    public function login(Request $request)
    {
        $request->validate([
            'login_id' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginId = trim($request->input('login_id'));
        $password = $request->input('password');

        // Find user by email or no_hp in tb_user
        $user = User::where('email', $loginId)
            ->orWhere('no_hp', $loginId)
            ->first();

        if ($user && Hash::check($password, $user->password)) {
            Auth::login($user, false);
            return redirect()->intended(route('dashboard'))->with('success', "Selamat datang kembali, {$user->nama}!");
        }

        return back()->withInput($request->only('login_id'))->withErrors([
            'login_id' => 'Email/Nomor WhatsApp atau password yang Anda masukkan tidak sesuai.',
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:tb_user,email',
            'no_hp' => 'required|string|max:30',
            'password' => 'required|string|min:6',
            'gender' => 'required|in:Male,Female',
            'usia' => 'required|integer|min:10|max:90',
            'level' => 'required|in:Newbie,Beginner,Intermediate,Advanced',
            'role' => 'required|in:member,host,venue_owner',
            'community_id' => 'nullable',
        ]);

        try {
            DB::beginTransaction();

            // 1. Create tb_user
            $user = User::create([
                'nama' => $request->nama,
                'email' => $request->email,
                'no_hp' => $request->no_hp,
                'password' => Hash::make($request->password),
                'role' => $request->role,
            ]);

            // 2. Create tb_player
            $communityId = ($request->community_id && $request->community_id !== 'none') ? (int) $request->community_id : null;

            Player::create([
                'user_id' => $user->user_id,
                'community_id' => $communityId,
                'nama' => $request->nama,
                'usia' => (int) $request->usia,
                'gender' => $request->gender,
                'level' => $request->level,
                'rating' => 1.00,
                'no_hp' => $request->no_hp,
                'email' => $request->email,
            ]);

            DB::commit();

            // Auto-login user
            Auth::login($user);

            return redirect()->route('dashboard')->with('success', 'Pendaftaran member berhasil! Selamat datang di Matcha.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors([
                'error' => 'Gagal melakukan registrasi: ' . $e->getMessage(),
            ]);
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('dashboard')->with('success', 'Anda telah keluar dari akun.');
    }
}
