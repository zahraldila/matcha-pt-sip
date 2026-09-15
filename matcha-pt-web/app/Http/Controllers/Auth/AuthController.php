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
        ], [
            'login_id.required' => 'Email atau Nomor WhatsApp wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $loginId = trim($request->input('login_id'));
        $loginIdLower = strtolower($loginId);
        $cleanPhone = preg_replace('/[^0-9]/', '', $loginId);
        $password = $request->input('password');

        // Find user by lowercase email or exact/cleaned no_hp in tb_user
        $user = User::whereRaw('LOWER(email) = ?', [$loginIdLower])
            ->orWhere('no_hp', $loginId)
            ->when(!empty($cleanPhone), function ($query) use ($cleanPhone) {
                $query->orWhere('no_hp', $cleanPhone);
            })
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
        // Sanitize email and phone format
        if ($request->has('email')) {
            $request->merge(['email' => strtolower(trim($request->email))]);
        }
        if ($request->has('no_hp')) {
            // Remove extra spaces or dashes
            $cleanNoHp = preg_replace('/[^0-9]/', '', (string)$request->no_hp);
            $request->merge(['no_hp' => $cleanNoHp]);
        }

        $request->validate([
            'nama' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email:rfc,filter',
                'max:255',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
                'unique:tb_user,email',
            ],
            'no_hp' => [
                'required',
                'string',
                'regex:/^[0-9]{9,15}$/',
                'unique:tb_user,no_hp',
            ],
            'password' => 'required|string|min:6',
            'gender' => 'required|in:Male,Female',
            'usia' => 'required|integer|min:10|max:90',
            'level' => 'required|in:Newbie,Beginner,Intermediate,Advanced',
            'role' => 'required|in:member,venue_owner',
            'community_id' => 'nullable',
        ], [
            'nama.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid (contoh: nama@domain.com).',
            'email.regex' => 'Format alamat email tidak valid (contoh: nama@domain.com).',
            'email.unique' => 'Alamat email ini sudah terdaftar. Silakan gunakan email lain atau masuk ke akun Anda.',
            'no_hp.required' => 'Nomor WhatsApp / HP wajib diisi.',
            'no_hp.regex' => 'Nomor WhatsApp hanya boleh berupa angka (9 - 15 digit).',
            'no_hp.unique' => 'Nomor WhatsApp ini sudah terdaftar pada akun lain.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal harus 6 karakter.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.in' => 'Pilihan jenis kelamin tidak valid.',
            'usia.required' => 'Usia wajib diisi.',
            'usia.integer' => 'Usia harus berupa angka.',
            'usia.min' => 'Usia minimal adalah 10 tahun.',
            'usia.max' => 'Usia maksimal adalah 90 tahun.',
            'level.required' => 'Kategori skill level wajib dipilih.',
            'level.in' => 'Pilihan skill level tidak valid.',
            'role.required' => 'Pilihan peran wajib ditentukan.',
            'role.in' => 'Pilihan peran akun tidak valid.',
        ]);

        try {
            DB::beginTransaction();

            $emailClean = strtolower(trim($request->email));
            $noHpClean = preg_replace('/[^0-9]/', '', (string)$request->no_hp);

            // Double check case-insensitive unique email in tb_user
            if (User::whereRaw('LOWER(email) = ?', [$emailClean])->exists()) {
                DB::rollBack();
                return back()->withInput()->withErrors([
                    'email' => 'Alamat email ini sudah terdaftar. Silakan gunakan email lain.',
                ]);
            }

            // 1. Create tb_user
            $user = User::create([
                'nama' => trim($request->nama),
                'email' => $emailClean,
                'no_hp' => $noHpClean,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'is_host' => false,
            ]);

            // 2. Create tb_player with linked user_id and full user details
            $communityId = ($request->community_id && $request->community_id !== 'none') ? (int) $request->community_id : null;

            Player::create([
                'user_id' => $user->user_id,
                'community_id' => $communityId,
                'nama' => trim($request->nama),
                'usia' => (int) $request->usia,
                'gender' => $request->gender,
                'level' => $request->level,
                'rating' => 1.00,
                'no_hp' => $noHpClean,
                'email' => $emailClean,
            ]);

            DB::commit();

            // Auto-login user
            Auth::login($user);

            return redirect()->route('dashboard')->with('success', 'Pendaftaran akun member berhasil! Selamat datang di Matcha.');
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
