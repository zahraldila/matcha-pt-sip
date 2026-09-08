@extends('layouts.app')

@section('content')
<div class="min-h-[calc(100vh-12rem)] flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12">
    <div class="max-w-md w-full space-y-6">
        
        <!-- Header -->
        <div class="text-center space-y-2">
            <div class="w-12 h-12 mx-auto rounded-xl bg-emerald-600 flex items-center justify-center text-white text-lg shadow-sm">
                <i class="fa-solid fa-table-tennis-paddle-ball"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">
                Masuk ke Akun Matcha
            </h1>
            <p class="text-xs text-slate-500">
                Akses riwayat pertandingan, statistik win rate, dan buat jadwal mabar sebagai Host
            </p>
        </div>

        <!-- Login Card -->
        <div class="clean-card rounded-2xl p-6 sm:p-8 space-y-5 shadow-sm">
            <form onsubmit="handleLoginSubmit(event)" class="space-y-4 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Email atau Nomor WhatsApp</label>
                    <input type="text" value="billy@matcha.app" placeholder="nama@email.com atau 0812..." class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2.5 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none" required>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="font-semibold text-slate-700">Password</label>
                        <a href="#" class="text-[11px] text-emerald-700 hover:underline font-medium">Lupa password?</a>
                    </div>
                    <input type="password" value="secret123" placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2.5 text-slate-800 focus:bg-white focus:border-emerald-600 focus:outline-none" required>
                </div>

                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 cursor-pointer text-slate-600">
                        <input type="checkbox" checked class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span>Ingat saya di perangkat ini</span>
                    </label>
                </div>

                <button type="submit" class="w-full py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-sm transition-colors mt-2">
                    Masuk Sekarang
                </button>
            </form>

            <div class="relative flex py-2 items-center">
                <div class="flex-grow border-t border-slate-200"></div>
                <span class="flex-shrink mx-3 text-slate-400 text-[11px]">atau</span>
                <div class="flex-grow border-t border-slate-200"></div>
            </div>

            <!-- Guest Mode Notice (Sesuai MoM) -->
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-center space-y-1">
                <p class="text-[11px] text-slate-600 font-medium">Hanya ingin ikut satu sesi mabar tanpa akun?</p>
                <a href="{{ route('games.index') }}" class="text-xs text-emerald-700 hover:underline font-bold inline-flex items-center gap-1">
                    Lanjut sebagai Tamu / Guest Player &rarr;
                </a>
            </div>
        </div>

        <p class="text-center text-xs text-slate-500">
            Belum punya akun member? 
            <a href="{{ route('register') }}" class="font-bold text-emerald-700 hover:underline">Daftar Member Baru</a>
        </p>
    </div>
</div>

@push('scripts')
<script>
    function handleLoginSubmit(e) {
        e.preventDefault();
        showToast('Login berhasil! Selamat datang kembali, Billy.');
        setTimeout(() => {
            window.location.href = "{{ route('dashboard') }}";
        }, 1000);
    }
</script>
@endpush
@endsection
