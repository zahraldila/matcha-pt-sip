@extends('layouts.app')

@section('content')
<div class="min-h-[calc(100vh-12rem)] flex items-center justify-center px-4 sm:px-6 lg:px-8 py-10 relative">

    <!-- Ambient Glowing Orb behind Glass Card -->
    <div class="absolute w-72 h-72 bg-[#A8E63A]/20 rounded-full blur-3xl pointer-events-none -top-10 -left-10"></div>
    <div class="absolute w-72 h-72 bg-[#063B00]/15 rounded-full blur-3xl pointer-events-none -bottom-10 -right-10"></div>

    <div class="max-w-md w-full space-y-6 relative z-10">
        
        <!-- Header -->
        <div class="text-center space-y-2">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-[#063B00] border border-[#063B00]/30 flex items-center justify-center text-white text-2xl shadow-md">
                <i class="fa-solid fa-table-tennis-paddle-ball text-[#A8E63A]"></i>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-[#050608] tracking-tight">
                Masuk ke Akun
            </h1>
            <p class="text-xs text-slate-500 max-w-xs mx-auto">
                Akses jadwal mabar, pimpin scoring pertandingan, dan simpan statistik karir Anda
            </p>
        </div>

        <!-- Glassmorphism Login Card -->
        <div class="glass-card !bg-white/85 !backdrop-blur-2xl rounded-3xl p-6 sm:p-8 space-y-5 border border-white shadow-[0_12px_40px_-10px_rgba(6,59,0,0.08)]">
            
            @if($errors->any())
                <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center gap-2.5">
                    <i class="fa-solid fa-circle-exclamation shrink-0"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            @if(session('success'))
                <div class="p-3.5 rounded-2xl bg-[#EBF8D8] border border-[#063B00]/20 text-[#063B00] text-xs flex items-center gap-2.5">
                    <i class="fa-solid fa-circle-check shrink-0 text-[#063B00]"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <form id="loginForm" action="{{ route('login.post') }}" method="POST" class="space-y-4 text-xs" novalidate onsubmit="handleLoginSubmit(event)">
                @csrf

                <!-- Input Login ID -->
                <div class="space-y-1.5">
                    <label class="block font-bold text-slate-800">
                        Email atau Nomor WhatsApp
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-envelope text-xs"></i>
                        </div>
                        <input type="text" name="login_id" id="loginId" value="{{ old('login_id', 'billy@matcha.app') }}" placeholder="nama@email.com atau 0812..." class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                    </div>
                    <p id="err_login_id" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                        <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Email atau Nomor WhatsApp wajib diisi.
                    </p>
                </div>

                <!-- Input Password -->
                <div class="space-y-1.5">
                    <div class="flex justify-between items-center">
                        <label class="font-bold text-slate-800">Password</label>
                        <a href="#" class="text-[11px] text-[#063B00] hover:underline font-bold">Lupa password?</a>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-lock text-xs"></i>
                        </div>
                        <input type="password" id="loginPassword" name="password" value="secret123" placeholder="••••••••" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl pl-10 pr-10 py-3 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        <button type="button" onclick="togglePasswordVisibility('loginPassword', 'eyeIcon')" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none">
                            <i class="fa-solid fa-eye text-xs" id="eyeIcon"></i>
                        </button>
                    </div>
                    <p id="err_login_password" class="hidden text-rose-500 font-bold text-[11px] items-center gap-1 mt-1">
                        <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Password wajib diisi.
                    </p>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 cursor-pointer text-slate-600 font-medium select-none">
                        <input type="checkbox" name="remember" value="1" checked class="w-4 h-4 rounded-md border-slate-300 text-[#063B00] focus:ring-[#063B00]">
                        <span>Ingat saya di perangkat ini</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full py-3.5 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 mt-2 cursor-pointer">
                    <span>Masuk Sekarang</span> <i class="fa-solid fa-arrow-right text-[10px] text-[#A8E63A]"></i>
                </button>
            </form>

            <div class="relative flex py-1 items-center">
                <div class="flex-grow border-t border-slate-200/60"></div>
                <span class="flex-shrink mx-3 text-slate-400 text-[11px] font-medium">atau</span>
                <div class="flex-grow border-t border-slate-200/60"></div>
            </div>

            <!-- Guest Mode Notice -->
            <div class="p-3.5 bg-gradient-to-r from-[#EBF8D8]/50 to-white rounded-2xl border border-[#063B00]/15 text-center space-y-1">
                <p class="text-[11px] text-slate-600 font-medium">Hanya ingin melihat jadwal atau ikut satu mabar?</p>
                <a href="{{ route('games.index') }}" class="text-xs text-[#063B00] hover:underline font-black inline-flex items-center gap-1">
                    Lanjut sebagai Tamu / Guest Player <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </a>
            </div>
        </div>

        <!-- Footer link -->
        <p class="text-center text-xs text-slate-500 font-medium">
            Belum punya akun member? 
            <a href="{{ route('register') }}" class="font-extrabold text-[#063B00] hover:underline">Daftar Member Baru</a>
        </p>
    </div>
</div>

@push('scripts')
<script>
    function togglePasswordVisibility(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function setLoginFieldError(fieldId, errId, message) {
        const input = document.getElementById(fieldId);
        const err = document.getElementById(errId);

        if (message) {
            input.classList.add('border-rose-400', 'bg-rose-50/40', 'focus:ring-rose-200', 'focus:border-rose-500');
            input.classList.remove('border-slate-200/80', 'bg-slate-50/80');
            err.innerHTML = `<i class="fa-solid fa-circle-exclamation text-[10px]"></i> ${message}`;
            err.classList.remove('hidden');
            err.classList.add('flex');
            return true;
        } else {
            input.classList.remove('border-rose-400', 'bg-rose-50/40', 'focus:ring-rose-200', 'focus:border-rose-500');
            input.classList.add('border-slate-200/80', 'bg-slate-50/80');
            err.classList.add('hidden');
            err.classList.remove('flex');
            return false;
        }
    }

    function handleLoginSubmit(e) {
        const loginId = document.getElementById('loginId').value.trim();
        const password = document.getElementById('loginPassword').value.trim();

        let hasError = false;

        if (setLoginFieldError('loginId', 'err_login_id', !loginId ? 'Email atau Nomor WhatsApp wajib diisi.' : null)) hasError = true;
        if (setLoginFieldError('loginPassword', 'err_login_password', !password ? 'Password wajib diisi.' : null)) hasError = true;

        if (hasError) {
            e.preventDefault();
            const firstErr = document.querySelector('.border-rose-400');
            if (firstErr) firstErr.focus();
            return false;
        }

        return true;
    }
</script>
@endpush
@endsection
