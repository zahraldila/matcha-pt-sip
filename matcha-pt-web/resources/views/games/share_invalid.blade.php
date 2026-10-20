@extends('layouts.app')

@section('content')
<div class="min-h-[70vh] flex items-center justify-center px-4 py-12">
    <div class="max-w-md w-full clean-card rounded-2xl p-8 sm:p-10 text-center shadow-lg border border-slate-200">
        <!-- Icon Banner -->
        <div class="mx-auto w-20 h-20 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600 mb-6 shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>

        <!-- Heading & Message -->
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 mb-3 tracking-tight">
            Sesi Tidak Ditemukan
        </h1>
        <p class="text-slate-600 text-sm leading-relaxed mb-8">
            Sesi tidak ditemukan atau tautan sudah kedaluwarsa. Silakan periksa kembali tautan yang dibagikan atau temukan jadwal sesi mabar lainnya.
        </p>

        <!-- Action Buttons -->
        <div class="space-y-3">
            <a href="{{ route('games.index') }}" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-[#063B00] hover:bg-[#084D00] text-white font-bold text-sm transition-all shadow-md active:scale-95">
                <i class="fa-solid fa-calendar-days text-[#A8E63A]"></i>
                Lihat Jadwal Mabar
            </a>
            <a href="{{ route('dashboard') }}" class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 font-semibold text-xs transition-colors">
                <i class="fa-solid fa-house text-slate-400"></i>
                Kembali ke Beranda
            </a>
        </div>
    </div>
</div>
@endsection
